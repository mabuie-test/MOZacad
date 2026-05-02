<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Helpers\Database;
use App\Repositories\AcademicLevelRepository;
use App\Services\DocumentComplianceValidationService;
use App\Services\DocumentEditorialQualityGateService;
use App\Repositories\DocumentComplianceValidationRepository;
use App\Repositories\DeliveryChecklistRepository;
use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\InstitutionRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderRequirementRepository;
use App\Repositories\WorkTypeRepository;
use App\Services\AIOrchestrationService;
use App\Services\AcademicRefinementService;
use App\Services\ApplicationLoggerService;
use App\Services\CitationFormatterService;
use App\Services\DocxAssemblyService;
use App\Services\DynamicAcademicStructureService;
use App\Services\AcademicBriefingAutoCompletionService;
use App\Services\AcademicBriefingQualityService;
use App\Services\AcademicContentQualityService;
use App\Services\ExportService;
use App\Services\HumanReviewQueueService;
use App\Services\InstitutionFormattingService;
use App\Services\InstitutionNormDocumentService;
use App\Services\InstitutionTemplateService;
use App\Services\MozPortugueseHumanizerService;
use App\Services\PromptComposerService;
use App\Services\RequirementInterpreterService;
use App\Services\RuleResolverService;
use App\Services\StoragePathService;
use App\Services\StructureBuilderService;
use App\Services\VisualIdentityComplianceService;
use App\Repositories\AuditLogRepository;
use RuntimeException;

final class GenerateOrderDocumentJob
{
    public function handle(int $orderId): array
    {
        $logger = new ApplicationLoggerService();
        $orders = new OrderRepository();
        $order = $orders->findById($orderId);
        if ($order === null) {
            throw new RuntimeException('Pedido não encontrado para geração documental.');
        }
        if (!in_array((string) ($order['status'] ?? ''), ['queued', 'revision_requested', 'under_human_review', 'ready'], true)) {
            throw new RuntimeException('Pedido ainda não está elegível para geração documental.');
        }

        $latestExisting = (new GeneratedDocumentRepository())->findLatestByOrderId($orderId);
        if (
            $latestExisting !== null
            && in_array((string) ($latestExisting['status'] ?? ''), ['generated', 'approved', 'pending_human_review'], true)
            && in_array((string) ($order['status'] ?? ''), ['ready', 'under_human_review'], true)
            && $this->documentFileExists((string) ($latestExisting['file_path'] ?? ''))
        ) {
            $logger->info('ai_job.document_generation.reused_latest', ['order_id' => $orderId, 'document_id' => (int) $latestExisting['id']]);

            return [
                'order_id' => $orderId,
                'generated_document_id' => (int) $latestExisting['id'],
                'version' => (int) ($latestExisting['version'] ?? 1),
                'file_path' => (string) ($latestExisting['file_path'] ?? ''),
                'queued_for_human_review' => (string) ($latestExisting['status'] ?? '') === 'pending_human_review',
                'human_review_queue_id' => null,
                'reused_existing_document' => true,
                'regeneration_cycle' => false,
            ];
        }

        $requirements = (new OrderRequirementRepository())->findByOrderId($orderId) ?? [];
        $briefingDto = (new RequirementInterpreterService())->interpret($order, $requirements);
        $briefing = [
            'orderId' => $briefingDto->orderId,
            'title' => $briefingDto->title,
            'problem' => $briefingDto->problem,
            'generalObjective' => $briefingDto->generalObjective,
            'specificObjectives' => $briefingDto->specificObjectives,
            'keywords' => $briefingDto->keywords,
            'extras' => $briefingDto->extras,
        ];

        $institutionRepo = new InstitutionRepository();
        $workTypeRepo = new WorkTypeRepository();
        $academicRepo = new AcademicLevelRepository();

        $institution = $institutionRepo->findById((int) $order['institution_id']) ?? [];
        $institutionRules = $institutionRepo->findRuleByInstitutionId((int) $order['institution_id']) ?? [];
        $institutionWorkTypeRules = $workTypeRepo->findInstitutionWorkTypeRule((int) $order['institution_id'], (int) $order['work_type_id']) ?? [];
        $academicLevel = $academicRepo->findById((int) $order['academic_level_id']) ?? [];
        $normContext = (new InstitutionNormDocumentService())->resolveForInstitution($institution);

        $templateResolution = (new InstitutionTemplateService())->resolve($institution, (int) $order['work_type_id']);
        $validTemplateModes = ['template_published_tracked', 'drift_filesystem_only', 'drift_path_mismatch'];
        if (!in_array((string) ($templateResolution['mode'] ?? ''), $validTemplateModes, true)) {
            $templateResolution = [
                'mode' => 'programmatic_fallback',
                'selected_template' => null,
                'reason' => 'Template ausente/inválido. Aplicado fallback explícito de montagem programática.',
                'candidate_path' => $templateResolution['candidate_path'] ?? null,
                'traceability' => $templateResolution['traceability'] ?? [],
            ];
        }
        $resolvedRulesDto = (new RuleResolverService())->resolve($institutionRules, $institutionWorkTypeRules, $academicLevel, $normContext, $templateResolution);
        $resolvedRules = [
            'visualRules' => $resolvedRulesDto->visualRules,
            'referenceRules' => $resolvedRulesDto->referenceRules,
            'structureRules' => $resolvedRulesDto->structureRules,
            'meta' => $resolvedRulesDto->meta,
        ];

        $sections = $workTypeRepo->getStructureByWorkType((int) $order['work_type_id']);
        $blueprint = (new StructureBuilderService())->build($sections, $resolvedRulesDto->structureRules);
        $blueprint = (new DynamicAcademicStructureService())->buildDynamicBlueprint($order, $briefing, $workTypeRepo->findById((int) $order['work_type_id']) ?? [], $blueprint, $resolvedRulesDto->structureRules);
        $workType = $workTypeRepo->findById((int) $order['work_type_id']) ?? [];

        if ((bool) ($_ENV['BRIEFING_AUTOCOMPLETE_ENABLED'] ?? true)) {
            $completed = (new AcademicBriefingAutoCompletionService())->complete($order, $requirements, []);
            $briefing['problem'] = $completed['problem_statement'] ?? $briefing['problem'];
            $briefing['generalObjective'] = $completed['general_objective'] ?? $briefing['generalObjective'];
            $briefing['specificObjectives'] = $completed['specific_objectives'] ?? $briefing['specificObjectives'];
            $briefing['keywords'] = $completed['keywords'] ?? $briefing['keywords'];
        }
        $briefingQuality = (new AcademicBriefingQualityService())->evaluate($briefing);

        $requiresObjectives = $this->workTypeRequiresObjectives($workType, $blueprint, $briefing);
        if ($requiresObjectives && !$briefingQuality['ok']) {
            throw new RuntimeException('Falha de qualidade pré-DOCX no briefing académico: ' . implode(', ', $briefingQuality['issues']));
        }
        $prompts = (new PromptComposerService())->compose($blueprint, $resolvedRules, $briefing);
        $referenceStyle = (string) ($resolvedRules['referenceRules']['style'] ?? 'APA');

        try {
            $generated = (new AIOrchestrationService())->run($prompts, $blueprint);
            $refined = (new AcademicRefinementService())->refine($generated, [
                'reference_style' => $referenceStyle,
            ]);
            $humanized = (new MozPortugueseHumanizerService())->humanize(
                $refined,
                'academic_humanized_pt_mz',
                (bool) ($briefing['extras']['needs_humanized_revision'] ?? true)
            );
            $cited = (new CitationFormatterService())->format($humanized, $referenceStyle);
        } catch (\Throwable $e) {
            $logger->error('ai_job.document_generation.ai_failed_using_local_fallback', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            $cited = $this->buildLocalFallbackSections($blueprint, $briefing, $referenceStyle);
            $logger->warning('ai_job.document_generation.local_fallback_used', ['order_id' => $orderId]);
        }

        $this->assertObjectiveQualityOrFail($briefing, $orderId, $workType, $requiresObjectives);
        $cited = $this->sanitizeOperationalMetaText($cited);
        $cited = $this->enforceObjectivesSection($cited, $briefing, $orderId, $requiresObjectives, $logger);
        $this->assertObjectivePresenceInSections($cited, $briefing, $orderId, $requiresObjectives);
        $cited = $this->ensureReferencesSection($cited, $briefing, $referenceStyle);

        $contentQuality = (new AcademicContentQualityService())->validateDocument($cited, $briefing, $blueprint);
        $hasWeakContent = !$contentQuality['ok'];

        $editorialGate = (new DocumentEditorialQualityGateService())->validate($cited);
        if (!$editorialGate['ok']) {
            throw new RuntimeException('Falha no quality gate editorial: ' . json_encode($editorialGate['issues'], JSON_UNESCAPED_UNICODE));
        }

        $formatted = (new InstitutionFormattingService())->apply($cited, $resolvedRules);
        $docxAssembly = new DocxAssemblyService();
        $doc = $docxAssembly->assemble($formatted, (string) $briefing['title'], $templateResolution);
        $templateApplication = $docxAssembly->buildTemplateApplicationRecord($templateResolution);

        $documents = new GeneratedDocumentRepository();
        $latest = $documents->findLatestByOrderId($orderId);
        $nextVersion = ((int) ($latest['version'] ?? 0)) + 1;
        $filename = sprintf('order-%d-v%d-%s.docx', $orderId, $nextVersion, date('YmdHis'));
        $path = (new ExportService())->saveDocx($doc, $filename);
        if (!$this->documentFileExists($path)) {
            throw new RuntimeException('Falha ao persistir documento DOCX no storage.');
        }

        $requiresReview = (bool) ($workType['requires_human_review'] ?? false);
        $documentStatus = $requiresReview ? 'pending_human_review' : 'generated';

        $queueId = null;
        $db = Database::connect();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            $lockedOrder = $orders->lockByIdForUpdate($orderId);
            if (!is_array($lockedOrder)) {
                throw new RuntimeException('Pedido não encontrado para persistência documental.');
            }

            $lockedLatest = $documents->findLatestByOrderIdForUpdate($orderId);
            if (
                $lockedLatest !== null
                && in_array((string) ($lockedLatest['status'] ?? ''), ['generated', 'approved', 'pending_human_review'], true)
                && in_array((string) ($lockedOrder['status'] ?? ''), ['ready', 'under_human_review'], true)
                && $this->documentFileExists((string) ($lockedLatest['file_path'] ?? ''))
            ) {
                if ($ownsTransaction && $db->inTransaction()) {
                    $db->commit();
                }
                $this->cleanupGeneratedFile($path);

                return [
                    'order_id' => $orderId,
                    'generated_document_id' => (int) $lockedLatest['id'],
                    'version' => (int) ($lockedLatest['version'] ?? 1),
                    'file_path' => (string) ($lockedLatest['file_path'] ?? ''),
                    'queued_for_human_review' => (string) ($lockedLatest['status'] ?? '') === 'pending_human_review',
                    'human_review_queue_id' => null,
                    'reused_existing_document' => true,
                    'regeneration_cycle' => false,
                ];
            }

            $effectiveVersion = ((int) ($lockedLatest['version'] ?? 0)) + 1;
            $documentId = $documents->create($orderId, $path, $documentStatus, $effectiveVersion, $templateApplication);

            $validation = (new DocumentComplianceValidationService())->validate($cited, $blueprint, $resolvedRules);
            $visualIssues = (new VisualIdentityComplianceService())->validate($resolvedRules, $templateResolution);
            if ($visualIssues !== []) {
                $validation['non_conformities'] = array_merge($validation['non_conformities'] ?? [], $visualIssues);
                foreach ($visualIssues as $issue) {
                    $severity = (string) ($issue['severity'] ?? 'minor');
                    if (isset($validation['summary'][$severity])) {
                        $validation['summary'][$severity] = ((int) $validation['summary'][$severity]) + 1;
                    }
                }
                $validation['is_compliant'] = ((int) ($validation['summary']['critical'] ?? 0)) === 0;
            }
            (new DocumentComplianceValidationRepository())->create($documentId, $effectiveVersion, $validation);
            (new DeliveryChecklistRepository())->ensureDefaults($documentId, $effectiveVersion);
            (new DeliveryChecklistRepository())->syncComplianceItemFromValidation($documentId, $effectiveVersion, $validation);
            (new DeliveryChecklistRepository())->syncReferencesCompletenessFromSections($documentId, $effectiveVersion, $cited);
            $hasComplianceBlocker = ((int) ($validation['summary']['critical'] ?? 0) > 0) || (($validation['is_compliant'] ?? true) === false);

            if ($hasComplianceBlocker) {
                $orders->updateStatus($orderId, 'delivery_blocked');
                $documents->updateLatestStatusByOrderId($orderId, 'rejected');
                (new AuditLogRepository())->log(
                    null,
                    'compliance.delivery_blocked',
                    'order',
                    $orderId,
                    [
                        'document_id' => $documentId,
                        'version' => $effectiveVersion,
                        'is_compliant' => (bool) ($validation['is_compliant'] ?? false),
                        'summary' => $validation['summary'] ?? [],
                        'critical_non_conformities' => array_values(array_filter(
                            (array) ($validation['non_conformities'] ?? []),
                            static fn (array $item): bool => (string) ($item['severity'] ?? '') === 'critical'
                        )),
                        'action' => 'regenerar_ou_corrigir',
                    ]
                );
            } elseif ($hasWeakContent) {
                $orders->updateStatus($orderId, 'under_human_review');
                $documents->updateLatestStatusByOrderId($orderId, 'pending_human_review');
            } else {
                $orders->updateStatus($orderId, $requiresReview ? 'under_human_review' : 'ready');
            }

            if ($requiresReview && !$hasComplianceBlocker) {
                $queueId = (new HumanReviewQueueService())->enqueue($orderId, $documentId, $effectiveVersion);
            }
            if ($ownsTransaction && $db->inTransaction()) {
                $db->commit();
            }
            $nextVersion = $effectiveVersion;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            $this->cleanupGeneratedFile($path);
            throw $e;
        }

        $isRegeneration = (string) ($order['status'] ?? '') === 'revision_requested';
        $logger->info('ai_job.document_generation.completed', ['order_id' => $orderId, 'document_id' => $documentId, 'version' => $nextVersion, 'requires_review' => $requiresReview, 'regeneration_cycle' => $isRegeneration, 'template_resolution' => $templateResolution]);

        return [
            'order_id' => $orderId,
            'generated_document_id' => $documentId,
            'version' => $nextVersion,
            'file_path' => $path,
            'queued_for_human_review' => $requiresReview && ($queueId !== null),
            'human_review_queue_id' => $queueId,
            'regeneration_cycle' => (string) ($order['status'] ?? '') === 'revision_requested',
        ];
    }

    private function buildLocalFallbackSections(array $blueprint, array $briefing, string $referenceStyle): array
    {
        $title = trim((string) ($briefing['title'] ?? 'Trabalho Académico'));
        $problem = trim((string) ($briefing['problem'] ?? ''));
        $generalObjective = trim((string) ($briefing['generalObjective'] ?? ''));

        if ($problem === '') {
            $problem = 'Investigar o tema em contexto académico moçambicano, delimitando fundamentos teóricos, pertinência social e relevância científica.';
        }

        if ($generalObjective === '') {
            $generalObjective = 'Desenvolver uma análise académica coerente do tema, com linguagem formal e estrutura metodológica consistente.';
        }

        $specificObjectives = [];
        $rawSpecific = $briefing['specificObjectives'] ?? [];
        if (is_array($rawSpecific)) {
            foreach ($rawSpecific as $item) {
                $candidate = trim((string) $item);
                if ($candidate !== '') {
                    $specificObjectives[] = $candidate;
                }
            }
        }
        if ($specificObjectives === []) {
            $specificObjectives = [
                'Caracterizar os conceitos fundamentais relacionados ao tema.',
                'Apresentar uma metodologia de investigação adequada aos objectivos.',
                'Discutir implicações académicas e recomendações coerentes com a análise desenvolvida.',
            ];
        }

        $specificObjectivesText = implode('; ', $specificObjectives);

        $canonical = [
            'resumo' => [
                'title' => 'Resumo',
                'content' => "{$title}. O estudo apresenta síntese académica do tema, enuncia o problema orientador ({$problem}) e assume como objectivo geral {$generalObjective}. A abordagem valoriza consistência teórica e clareza argumentativa.",
            ],
            'introducao' => [
                'title' => 'Introdução',
                'content' => "No contexto do tema {$title}, estabelece-se como problema orientador {$problem}. O objectivo geral consiste em {$generalObjective}. Como objectivos específicos, consideram-se: {$specificObjectivesText}.",
            ],
            'metodologia' => [
                'title' => 'Metodologia',
                'content' => 'A investigação adopta abordagem qualitativa de natureza descritiva e analítica, com revisão bibliográfica e sistematização conceptual. O percurso metodológico assegura coerência entre problema, objectivo geral e objectivos específicos, preservando rigor académico.',
            ],
            'objectivos' => [
                'title' => 'Objectivos',
                'content' => "Objectivo geral: {$generalObjective}\nObjectivos específicos:\n- " . implode("\n- ", $specificObjectives),
            ],
            'resultados_discussao' => [
                'title' => 'Resultados e Discussão',
                'content' => 'A discussão académica evidencia relações entre fundamentos teóricos e implicações para o contexto analisado. A interpretação privilegia consistência argumentativa, articulação lógica entre categorias e contributos para aprofundamento do tema em ambiente institucional.',
            ],
            'conclusao' => [
                'title' => 'Conclusão',
                'content' => "Conclui-se que o desenvolvimento do tema {$title} permite responder ao problema orientador e sustentar o objectivo geral proposto. Os objectivos específicos são retomados de forma integrada, reforçando contributos académicos e encaminhamentos para estudos futuros.",
            ],
            'referencias' => [
                'title' => 'Referências',
                'content' => "Referências organizadas conforme {$referenceStyle}, com normalização formal para revisão editorial subsequente.",
            ],
        ];

        $resolved = [];
        foreach ($blueprint as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            $titleFromBlueprint = trim((string) ($item['title'] ?? ''));
            $key = null;

            if (str_contains($code, 'resumo')) {
                $key = 'resumo';
            } elseif (str_contains($code, 'introdu')) {
                $key = 'introducao';
            } elseif (str_contains($code, 'metod')) {
                $key = 'metodologia';
            } elseif (str_contains($code, 'objec') || str_contains($code, 'objet')) {
                $key = 'objectivos';
            } elseif (str_contains($code, 'resultado') || str_contains($code, 'discuss')) {
                $key = 'resultados_discussao';
            } elseif (str_contains($code, 'conclus')) {
                $key = 'conclusao';
            } elseif (str_contains($code, 'refer')) {
                $key = 'referencias';
            }

            if ($key === null || isset($resolved[$key])) {
                continue;
            }

            $resolved[$key] = [
                'code' => $code !== '' ? $code : $key,
                'title' => $titleFromBlueprint !== '' ? $titleFromBlueprint : $canonical[$key]['title'],
                'content' => $canonical[$key]['content'],
            ];
        }

        foreach ($canonical as $key => $data) {
            if (isset($resolved[$key])) {
                continue;
            }

            $resolved[$key] = [
                'code' => $key,
                'title' => $data['title'],
                'content' => $data['content'],
            ];
        }

        return array_values($resolved);
    }

    private function documentFileExists(string $candidatePath): bool
    {
        if (trim($candidatePath) === '') {
            return false;
        }

        $paths = new StoragePathService();
        try {
            $fullPath = $paths->ensurePathInside($candidatePath, $paths->generatedBase());
        } catch (RuntimeException) {
            return false;
        }

        return is_file($fullPath) && filesize($fullPath) > 0;
    }

    private function cleanupGeneratedFile(string $candidatePath): void
    {
        if (trim($candidatePath) === '') {
            return;
        }

        $paths = new StoragePathService();
        try {
            $fullPath = $paths->ensurePathInside($candidatePath, $paths->generatedBase());
        } catch (RuntimeException) {
            return;
        }

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function workTypeRequiresObjectives(array $workType, array $blueprint, array $briefing): bool
    {
        if (($briefing['extras']['needs_abstract'] ?? false) === true) {
            return true;
        }

        $slug = mb_strtolower(trim((string) ($workType['slug'] ?? '')));
        if (in_array($slug, ['monografia', 'tcc', 'dissertacao', 'tese', 'projecto', 'projeto'], true)) {
            return true;
        }

        foreach ($blueprint as $section) {
            $code = mb_strtolower((string) ($section['code'] ?? ''));
            $title = mb_strtolower((string) ($section['title'] ?? ''));
            if (str_contains($code, 'introdu') || str_contains($title, 'introdu') || str_contains($code, 'resumo') || str_contains($title, 'resumo')) {
                return true;
            }
        }

        return false;
    }

    private function assertObjectiveQualityOrFail(array $briefing, int $orderId, array $workType, bool $requiresObjectives): void
    {
        if (!$requiresObjectives) {
            return;
        }

        $generalObjective = trim((string) ($briefing['generalObjective'] ?? ''));
        $specificObjectives = array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), is_array($briefing['specificObjectives'] ?? null) ? $briefing['specificObjectives'] : []), static fn (string $value): bool => $value !== ''));
        if ($generalObjective !== '' && $specificObjectives !== []) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Falha de qualidade pré-DOCX: objectivos obrigatórios ausentes no pedido (order_id=%d, work_type=%s).',
            $orderId,
            (string) ($workType['slug'] ?? $workType['name'] ?? 'unknown')
        ));
    }

    private function enforceObjectivesSection(array $sections, array $briefing, int $orderId, bool $requiresObjectives, ApplicationLoggerService $logger): array
    {
        if (!$requiresObjectives) {
            return $sections;
        }

        if ($this->containsObjectiveSection($sections, $briefing)) {
            return $sections;
        }

        $generalObjective = trim((string) ($briefing['generalObjective'] ?? ''));
        $specificObjectives = array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), is_array($briefing['specificObjectives'] ?? null) ? $briefing['specificObjectives'] : []), static fn (string $value): bool => $value !== ''));

        $logger->warning('ai_job.document_generation.objectives_omitted_by_model', [
            'order_id' => $orderId,
            'reason' => 'objective_section_not_found_after_post_processing',
            'specific_objectives_count' => count($specificObjectives),
        ]);

        $injected = [
            'code' => 'objectivos',
            'title' => 'Objectivos do Estudo',
            'content' => "Objectivo geral: {$generalObjective}\nObjectivos específicos:\n- " . implode("\n- ", $specificObjectives),
        ];

        array_unshift($sections, $injected);

        return $sections;
    }

    private function assertObjectivePresenceInSections(array $sections, array $briefing, int $orderId, bool $requiresObjectives): void
    {
        if (!$requiresObjectives || $this->containsObjectiveSection($sections, $briefing)) {
            return;
        }

        throw new RuntimeException(sprintf('Falha de qualidade pré-DOCX: pós-processamento sem secção válida de objectivos (order_id=%d).', $orderId));
    }

    private function containsObjectiveSection(array $sections, array $briefing): bool
    {
        $generalObjective = mb_strtolower(trim((string) ($briefing['generalObjective'] ?? '')));
        $specificObjectives = array_values(array_filter(array_map(static fn (mixed $item): string => mb_strtolower(trim((string) $item)), is_array($briefing['specificObjectives'] ?? null) ? $briefing['specificObjectives'] : []), static fn (string $value): bool => $value !== ''));

        foreach ($sections as $section) {
            $title = mb_strtolower(trim((string) ($section['title'] ?? '')));
            $content = mb_strtolower(trim((string) ($section['content'] ?? '')));
            $hasObjectiveSignals = str_contains($title, 'objectiv') || str_contains($content, 'objectivo geral');
            if (!$hasObjectiveSignals) {
                continue;
            }

            $hasGeneral = $generalObjective !== '' && str_contains($content, $generalObjective);
            $specificHits = 0;
            foreach ($specificObjectives as $objective) {
                if (str_contains($content, $objective)) {
                    $specificHits++;
                }
            }

            if ($hasGeneral && $specificHits > 0) {
                return true;
            }
        }

        return false;
    }

    private function sanitizeOperationalMetaText(array $sections): array
    {
        $blocked = [
            '/\{\s*"[^"]+"\s*:/u',
            '/\bsection_title\b|\bsection_code\b|\btext\s*:/iu',
            '/com base nas regras de refinamento|instru[cç][aã]o|pipeline|payload|debug|serializa[cç][aã]o/u',
            '/\b\-\-\-\b/u',
            '/\[\[todo|placeholder|indice placeholder|lorem ipsum/u',
        ];

        foreach ($sections as &$section) {
            $title = trim((string) ($section['title'] ?? ''));
            $content = trim((string) ($section['content'] ?? ''));

            foreach ($blocked as $pattern) {
                $title = preg_replace($pattern, '', $title) ?? $title;
                $content = preg_replace($pattern, '', $content) ?? $content;
            }

            $section['title'] = trim(preg_replace('/\s+/', ' ', $title) ?? $title);
            $section['content'] = trim(preg_replace('/\n{3,}/', "\n\n", $content) ?? $content);
        }
        unset($section);

        return $sections;
    }

    private function ensureReferencesSection(array $sections, array $briefing, string $referenceStyle): array
    {
        $index = null;
        foreach ($sections as $i => $section) {
            $code = mb_strtolower((string) ($section['code'] ?? ''));
            $title = mb_strtolower((string) ($section['title'] ?? ''));
            if (str_contains($code, 'refer') || str_contains($title, 'refer') || str_contains($title, 'bibliograf')) {
                $index = $i;
                break;
            }
        }

        $refs = $index !== null ? $this->extractRealReferenceLines((string) ($sections[$index]['content'] ?? '')) : [];
        if (count($refs) < 3) {
            $refs = $this->buildDefaultAcademicReferences($briefing, $referenceStyle);
        }
        if (count($refs) < 3) {
            throw new RuntimeException('Falha de qualidade pré-DOCX: bibliografia insuficiente após saneamento (mínimo 3 referências reais).');
        }

        $payload = [
            'code' => 'references',
            'title' => 'Referências',
            'content' => implode("\n", $refs),
            'citation_style' => $referenceStyle,
        ];

        if ($index === null) {
            $sections[] = $payload;
        } else {
            $sections[$index] = array_merge($sections[$index], $payload);
        }

        return $sections;
    }

    private function extractRealReferenceLines(string $raw): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/', $raw) ?: []), static fn (string $line): bool => $line !== ''));
        $invalid = '/refer[eê]ncia incompleta|preencher autor|an[aá]lise documental|como usar fontes|placeholder|todo|pipeline|payload|debug/u';
        $valid = [];

        foreach ($lines as $line) {
            if (preg_match($invalid, mb_strtolower($line)) === 1) {
                continue;
            }
            if (preg_match('/\b(19|20)\d{2}\b/u', $line) !== 1) {
                continue;
            }
            if (mb_strlen($line) < 30) {
                continue;
            }
            $valid[] = $line;
        }

        return array_values(array_unique($valid));
    }

    private function buildDefaultAcademicReferences(array $briefing, string $referenceStyle): array
    {
        $title = trim((string) ($briefing['title'] ?? 'Tema Académico'));
        $year = (string) date('Y');

        if (strtoupper($referenceStyle) === 'ABNT') {
            return [
                "GIL, Antonio Carlos. Métodos e técnicas de pesquisa social. São Paulo: Atlas, 2019.",
                "SEVERINO, Antônio Joaquim. Metodologia do trabalho científico. São Paulo: Cortez, 2018.",
                "MARCONI, Marina de Andrade; LAKATOS, Eva Maria. Fundamentos de metodologia científica aplicados a {$title}. São Paulo: Atlas, {$year}.",
            ];
        }

        return [
            "Gil, A. C. (2019). Métodos e técnicas de pesquisa social. Atlas.",
            "Severino, A. J. (2018). Metodologia do trabalho científico. Cortez.",
            "Marconi, M. A., & Lakatos, E. M. ({$year}). Fundamentos de metodologia científica aplicados a {$title}. Atlas.",
        ];
    }
}
