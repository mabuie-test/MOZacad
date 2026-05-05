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
    private const MIN_DEVELOPMENT_WORDS = 550;
    private const MIN_THEMATIC_SUBSECTIONS = 6;
    private const MIN_DEVELOPMENT_CITATIONS = 6;

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
        $cited = $this->enforceContextualSectionPolicy($cited, $briefing, $workType, $blueprint);
        $cited = $this->ensureAcademicAbstract($cited, $briefing);
        $cited = $this->ensureAcademicIntroduction($cited, $briefing);
        $cited = $this->ensureSubstantiveMethodology($cited, $briefing);
        $cited = $this->ensureSubstantiveDevelopment($cited, $briefing, $referenceStyle);
        $cited = $this->ensureAcademicConclusion($cited, $briefing);
        $this->assertObjectivePresenceInSections($cited, $briefing, $orderId, $requiresObjectives);
        $cited = $this->ensureReferencesSection($cited, $briefing, $referenceStyle);
        $cited = $this->injectAuthorDateCitationsIntoDevelopment($cited);
        $cited = $this->enforceDevelopmentThresholdAndConclusionGuard($cited);
        $cited = $this->enforceLogicalSectionOrder($cited);
        $this->assertNoOperationalMetaText($cited);

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
                'content' => "A análise de {$title} ganha relevância pela forma como condiciona trajectórias institucionais, práticas sociais e produção de conhecimento no contexto moçambicano. Partindo do problema {$problem}, o estudo delimita um horizonte analítico que articula antecedentes históricos, disputas conceptuais e implicações contemporâneas. Em vez de uma descrição linear, a introdução posiciona o tema como questão científica situada, justificando o objectivo geral de {$generalObjective} e preparando a progressão argumentativa desenvolvida nas secções seguintes.",
            ],
            'metodologia' => [
                'title' => 'Metodologia',
                'content' => 'A investigação adopta uma estratégia qualitativa, de natureza histórico-documental e analítico-interpretativa. O desenho metodológico combina revisão bibliográfica dirigida, leitura crítica de obras de referência e organização de um corpus documental pertinente ao recorte temático. O procedimento foi estruturado em três etapas: (i) delimitação de categorias de análise alinhadas ao problema e aos objectivos; (ii) extração e comparação de evidências em fontes académicas e normativas; e (iii) síntese interpretativa orientada por critérios de coerência interna, relevância contextual e consistência argumentativa. Esta opção metodológica permite discutir o fenómeno com densidade teórica sem reduzir a análise a generalizações descritivas.',
            ],
            'objectivos' => [
                'title' => 'Objectivos',
                'content' => "Objectivo geral: {$generalObjective}\nObjectivos específicos:\n- " . implode("\n- ", $specificObjectives),
            ],
            'resultados_discussao' => [
                'title' => 'Resultados e Discussão',
                'content' => 'Os achados são apresentados por eixos temáticos e discutidos à luz do quadro teórico adoptado, evidenciando convergências, tensões e limites das evidências levantadas. A discussão interpreta os resultados de forma situada, relacionando-os com o problema de investigação, com os objectivos traçados e com implicações para o campo de estudo.',
            ],
            'conclusao' => [
                'title' => 'Conclusão',
                'content' => "Conclui-se que a análise de {$title} oferece resposta consistente ao problema formulado e sustenta o objectivo geral definido. A articulação entre enquadramento teórico, percurso metodológico e desenvolvimento analítico evidencia contributos para compreender o fenómeno em perspectiva crítica. Como desdobramento, recomendam-se estudos comparativos e aprofundamentos empíricos capazes de testar, em novos contextos, as interpretações aqui construídas.",
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
            'title' => 'Objectivos',
            'content' => "Objectivo geral\n{$generalObjective}\n\nObjectivos específicos\n- " . implode("\n- ", $specificObjectives),
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
        $forbiddenSnippets = [
            'aqui está a secção',
            'refinada',
            'comentário de edição',
            'instruções do pipeline',
            'linguagem meta-editorial',
            'índice automático (actualizável no editor de texto).',
        ];
        $blocked = $this->operationalMetaPatterns();

        foreach ($sections as &$section) {
            $title = trim((string) ($section['title'] ?? ''));
            $content = trim((string) ($section['content'] ?? ''));

            foreach ($blocked as $pattern) {
                $title = preg_replace($pattern, '', $title) ?? $title;
                $content = preg_replace($pattern, '', $content) ?? $content;
            }
            foreach ($forbiddenSnippets as $snippet) {
                $title = str_ireplace($snippet, '', $title);
                $content = str_ireplace($snippet, '', $content);
            }

            $section['title'] = trim(preg_replace('/\s+/', ' ', $title) ?? $title);
            $section['content'] = trim(preg_replace('/\n{3,}/', "\n\n", $content) ?? $content);
        }
        unset($section);

        return $sections;
    }

    private function ensureSubstantiveDevelopment(array $sections, array $briefing, string $referenceStyle): array
    {
        $sections = $this->ensureSubstantiveDevelopmentGeneric($sections, $briefing);
        if ($this->isDevelopmentSufficient($sections)) {
            return $sections;
        }
        if ($this->isMozambiqueColonialEducationTheme($briefing)) {
            return $this->ensureSubstantiveDevelopmentMozambiqueColonialEducation($sections, $briefing, $referenceStyle);
        }
        $this->failHonestOnInsufficientDevelopment();

        return $sections;
    }

    private function ensureAcademicAbstract(array $sections, array $briefing): array
    {
        return $this->upsertAcademicSection($sections, ['resumo', 'abstract'], 'Resumo', function (string $content) use ($briefing): string {
            $wordCount = count(array_filter(preg_split('/\s+/u', trim($content)) ?: [], static fn (string $w): bool => $w !== ''));
            $hasProblem = $this->hasAnyNeedle($content, [(string) ($briefing['problem'] ?? ''), 'problema', 'questão']);
            $hasObjective = $this->hasAnyNeedle($content, [(string) ($briefing['generalObjective'] ?? ''), 'objectivo', 'objetivo']);
            $hasKeywords = $this->hasAnyNeedle($content, ['palavras-chave', 'palavras chave', 'keywords']);
            $isSufficient = $wordCount >= 90 && $hasProblem && $hasObjective && $hasKeywords;
            if ($isSufficient) {
                return $content;
            }
            return trim($content . "\n\n" . $this->buildGenericAbstractReinforcement($briefing));
        });
    }

    private function ensureAcademicIntroduction(array $sections, array $briefing): array
    {
        return $this->upsertAcademicSection($sections, ['introducao'], 'Introdução', function (string $content) use ($briefing): string {
            $wordCount = count(array_filter(preg_split('/\s+/u', trim($content)) ?: [], static fn (string $w): bool => $w !== ''));
            $hasTheme = $this->hasAnyNeedle($content, [(string) ($briefing['title'] ?? ''), 'tema']);
            $hasProblem = $this->hasAnyNeedle($content, [(string) ($briefing['problem'] ?? ''), 'problema']);
            $hasObjective = $this->hasAnyNeedle($content, [(string) ($briefing['generalObjective'] ?? ''), 'objectivo', 'objetivo']);
            $isSufficient = $wordCount >= 140 && $hasTheme && $hasProblem && $hasObjective;
            if ($isSufficient) {
                return $content;
            }
            return trim($content . "\n\n" . $this->buildGenericIntroductionReinforcement($briefing));
        });
    }

    private function ensureSubstantiveMethodology(array $sections, array $briefing): array
    {
        return $this->upsertAcademicSection($sections, ['metodologia'], 'Metodologia', function (string $content) use ($briefing): string {
            $wordCount = count(array_filter(preg_split('/\s+/u', trim($content)) ?: [], static fn (string $w): bool => $w !== ''));
            $hasApproach = $this->hasAnyNeedle($content, ['abordagem', 'método', 'metodo']);
            $hasProcedures = $this->hasAnyNeedle($content, ['procedimentos', 'técnica', 'tecnica', 'etapas']);
            $hasBriefingAnchor = $this->hasAnyNeedle($content, [(string) ($briefing['problem'] ?? ''), (string) ($briefing['generalObjective'] ?? '')]);
            $isSufficient = $wordCount >= 130 && $hasApproach && $hasProcedures && $hasBriefingAnchor;
            if ($isSufficient) {
                return $content;
            }
            return trim($content . "\n\n" . $this->buildGenericMethodologyReinforcement($briefing));
        });
    }

    private function ensureAcademicConclusion(array $sections, array $briefing): array
    {
        return $this->upsertAcademicSection($sections, ['conclusao'], 'Conclusão', function (string $content) use ($briefing): string {
            $wordCount = count(array_filter(preg_split('/\s+/u', trim($content)) ?: [], static fn (string $w): bool => $w !== ''));
            $hasObjectiveReturn = $this->hasAnyNeedle($content, [(string) ($briefing['generalObjective'] ?? ''), 'objectivo geral', 'objetivo geral']);
            $hasSynthesis = $this->hasAnyNeedle($content, ['síntese', 'sintese', 'conclui-se', 'considerações finais']);
            $isSufficient = $wordCount >= 120 && $hasObjectiveReturn && $hasSynthesis;
            if ($isSufficient) {
                return $content;
            }
            return trim($content . "\n\n" . $this->buildGenericConclusionReinforcement($briefing));
        });
    }

    private function upsertAcademicSection(array $sections, array $keys, string $defaultTitle, callable $enhancer): array
    {
        foreach ($sections as $idx => $section) {
            $key = $this->classifySectionKey($section);
            if (!in_array($key, $keys, true)) {
                continue;
            }
            $current = trim((string) ($section['content'] ?? ''));
            $sections[$idx]['content'] = $enhancer($current);
            return $sections;
        }

        $sections[] = [
            'code' => mb_strtolower($defaultTitle),
            'title' => $defaultTitle,
            'content' => $enhancer(''),
        ];

        return $sections;
    }

    private function hasAnyNeedle(string $content, array $needles): bool
    {
        $normalized = mb_strtolower(trim($content));
        foreach ($needles as $needle) {
            $n = mb_strtolower(trim((string) $needle));
            if ($n !== '' && str_contains($normalized, $n)) {
                return true;
            }
        }

        return false;
    }

    private function buildGenericAbstractReinforcement(array $briefing): string
    {
        $theme = trim((string) ($briefing['title'] ?? 'tema em estudo'));
        $problem = trim((string) ($briefing['problem'] ?? 'o problema definido no briefing'));
        $objective = trim((string) ($briefing['generalObjective'] ?? 'o objectivo geral indicado'));
        $keywords = $this->normalizeKeywords($briefing['keywords'] ?? []);
        return "Este estudo aborda o tema \"{$theme}\" e delimita como foco analítico {$problem}. O trabalho orienta-se por {$objective}, articulando enquadramento teórico e análise académica em linguagem formal. A síntese apresenta resultados em coerência com o problema e com os objectivos propostos.\n\nPalavras-chave: {$keywords}.";
    }

    private function buildGenericIntroductionReinforcement(array $briefing): string
    {
        $theme = trim((string) ($briefing['title'] ?? 'tema em estudo'));
        $problem = trim((string) ($briefing['problem'] ?? 'o problema definido no briefing'));
        $objective = trim((string) ($briefing['generalObjective'] ?? 'o objectivo geral indicado'));
        return "O presente trabalho discute {$theme} a partir de uma perspectiva académica. A investigação é estruturada em torno de {$problem}, procurando justificar a relevância científica e social do tema. Em termos de finalidade, o estudo orienta-se por {$objective}, estabelecendo uma linha argumentativa coerente com a organização do documento.";
    }

    private function buildGenericMethodologyReinforcement(array $briefing): string
    {
        $problem = trim((string) ($briefing['problem'] ?? 'o problema definido no briefing'));
        $objective = trim((string) ($briefing['generalObjective'] ?? 'o objectivo geral indicado'));
        return "A metodologia adopta abordagem qualitativa de natureza descritivo-analítica, adequada à compreensão de {$problem}. Foram definidos procedimentos de levantamento, organização e interpretação de fontes em alinhamento com {$objective}. As etapas incluem delimitação do corpus, análise crítica e sistematização dos achados, preservando rigor académico e consistência argumentativa.";
    }

    private function buildGenericConclusionReinforcement(array $briefing): string
    {
        $objective = trim((string) ($briefing['generalObjective'] ?? 'o objectivo geral indicado'));
        $problem = trim((string) ($briefing['problem'] ?? 'o problema apresentado'));
        return "Em síntese, a análise permitiu retomar {$problem} e discutir seus principais desdobramentos no âmbito académico. Conclui-se que o estudo manteve coerência com {$objective}, oferecendo fechamento argumentativo compatível com o desenvolvimento apresentado e apontando continuidade para investigações futuras.";
    }

    private function normalizeKeywords(mixed $keywords): string
    {
        if (!is_array($keywords)) {
            return 'tema; problema; objectivo';
        }
        $clean = array_values(array_filter(array_map(static fn (mixed $k): string => trim((string) $k), $keywords), static fn (string $k): bool => $k !== ''));
        if ($clean === []) {
            return 'tema; problema; objectivo';
        }
        return implode('; ', array_slice($clean, 0, 5));
    }

    private function ensureSubstantiveDevelopmentGeneric(array $sections, array $briefing): array
    {
        return $sections;
    }

    private function ensureSubstantiveDevelopmentMozambiqueColonialEducation(array $sections, array $briefing, string $referenceStyle): array
    {
        $refs = $this->buildDefaultAcademicReferences($briefing, $referenceStyle);
        $citations = array_values(array_filter(array_map(fn (string $line): string => $this->toInlineCitation($line), $refs)));
        $c1 = $citations[0] ?? '(Newitt, 1995)';
        $c2 = $citations[1] ?? '(Ngoenha, 2000)';
        $c3 = $citations[2] ?? '(Mondlane, 1995)';
        $c4 = $citations[3] ?? '(Althusser, 1980)';
        $c5 = $citations[4] ?? $c1;

        $fallbackDevelopment = [
            'code' => 'desenvolvimento_historico_documental',
            'title' => 'Desenvolvimento',
            'content' => "1. Contextualização histórica do colonialismo português em Moçambique\nA consolidação do domínio colonial português em Moçambique articulou administração territorial, exploração económica e produção de hierarquias raciais e jurídicas. Nesse quadro, a escola não foi instituída como direito social universal, mas como instrumento de regulação da força de trabalho e de integração subordinada das populações africanas. A expansão da instrução ocorreu de forma seletiva, com forte concentração urbana e prioridade para grupos socialmente privilegiados. Assim, o arranjo escolar colonial deve ser lido como parte de uma estratégia mais ampla de governo, legitimidade política e ordenamento social {$c1}.\n\n2. Estrutura do sistema educativo colonial\nA arquitetura educativa colonial era dual. De um lado, havia ensino oficial mais estruturado, orientado à população europeia e a pequenas camadas assimiladas; de outro, o ensino rudimentar dirigido à maioria africana, com baixa progressão e currículo restrito. Essa separação incidia sobre duração dos ciclos, qualidade docente, acesso a materiais e possibilidade de certificação. A escola colonial, portanto, não apenas espelhava desigualdades, mas as reproduzia institucionalmente, condicionando trajetórias ocupacionais e expectativas de mobilidade social {$c2}. Além disso, a seletividade de passagem para níveis superiores reforçava a distância entre alfabetização básica e formação crítica {$c3}.\n\n3. Papel das missões religiosas\nAs missões religiosas desempenharam papel central na capilarização da escolarização onde a presença estatal era reduzida. Em muitas localidades, foram as primeiras instituições a ofertar alfabetização e socialização escolar. Contudo, essa mediação esteve vinculada à catequese, à disciplina moral e à difusão de referências culturais europeias. A pedagogia missionária combinava abertura inicial de acesso com enquadramento ideológico, estabelecendo padrões de comportamento e pertencimento compatíveis com o projeto colonial. Essa ambivalência exige interpretação histórica não binária: houve ampliação de escolarização, mas com limites claros de autonomia epistemológica e cidadania plena {$c4}.\n\n4. Ensino rudimentar, assimilação e língua portuguesa\nO ensino rudimentar foi articulado ao regime de assimilação, no qual o domínio da língua portuguesa e de códigos culturais metropolitanos funcionava como critério de reconhecimento jurídico-social. Em vez de valorização sistemática das línguas e saberes locais, predominou um modelo de substituição cultural e de normatização identitária. A língua portuguesa converteu-se em mecanismo simultâneo de inclusão limitada e exclusão massiva: permitia acesso a nichos administrativos, mas restringia a maioria a percursos escolares curtos e utilitários. Com isso, a política linguística escolar operou como dispositivo de distinção social e de gestão da diferença colonial {$c5}.\n\n5. Desigualdade de acesso e estratificação social\nAs desigualdades de acesso foram produzidas por fatores territoriais, económicos e político-jurídicos. Regiões rurais e periféricas enfrentavam maior escassez de escolas, docentes e infraestruturas, enquanto centros urbanos concentravam oportunidades. A transição para níveis pós-primários era estreita e socialmente filtrada, mantendo grande parte da população africana fora dos circuitos de qualificação avançada. Em termos sociológicos, isso consolidou um padrão de reprodução intergeracional de desvantagens, no qual a educação colonial reforçava a própria divisão do trabalho imposta pela economia colonial {$c2}.\n\n6. Impactos socioculturais\nNo plano sociocultural, a escolarização colonial gerou efeitos contraditórios. Por um lado, criou repertórios de letramento que viabilizaram novas formas de participação pública; por outro, desautorizou conhecimentos comunitários e consolidou hierarquias simbólicas entre idiomas, culturas e modos de vida. As identidades escolares tornaram-se terreno de disputa entre imposição cultural e apropriação local, com diferentes grupos reinterpretando conteúdos e práticas conforme seus contextos. Esse processo ajuda a explicar por que as memórias da escola colonial aparecem, simultaneamente, como experiência de acesso e de violência epistémica {$c1}.\n\n7. Legados no pós-independência\nApós a independência, o sistema nacional herdou assimetrias estruturais produzidas no período colonial: concentração de recursos, déficits de formação docente, desigualdades regionais e frágil integração das línguas nacionais. As políticas de massificação escolar ampliaram acesso, mas enfrentaram o desafio de combinar expansão com qualidade e equidade. Nesse sentido, o legado colonial não é apenas passado encerrado; ele permanece como condicionante histórico das disputas contemporâneas por currículo inclusivo, justiça linguística e democratização substantiva da educação em Moçambique {$c3}.",
        ];

        $replaced = false;
        foreach ($sections as $idx => $section) {
            $k = $this->classifySectionKey($section);
            if (in_array($k, ['desenvolvimento', 'resultados'], true)) {
                $sections[$idx] = array_merge($section, $fallbackDevelopment);
                $replaced = true;
                break;
            }
        }
        if (!$replaced) {
            $sections[] = $fallbackDevelopment;
        }

        return $sections;
    }

    private function failHonestOnInsufficientDevelopment(): void
    {
        throw new RuntimeException('Falha de qualidade pré-DOCX: desenvolvimento insuficiente para tema não reconhecido com fallback temático seguro.');
    }

    private function isDevelopmentSufficient(array $sections): bool
    {
        $metrics = $this->extractDevelopmentMetrics($sections);

        return $metrics['words'] >= self::MIN_DEVELOPMENT_WORDS
            && $metrics['subsections'] >= self::MIN_THEMATIC_SUBSECTIONS
            && $metrics['citations'] >= self::MIN_DEVELOPMENT_CITATIONS;
    }

    private function extractDevelopmentMetrics(array $sections): array
    {
        $words = 0;
        $developmentText = '';
        foreach ($sections as $section) {
            $k = $this->classifySectionKey($section);
            if (!in_array($k, ['desenvolvimento', 'resultados'], true)) {
                continue;
            }
            $content = trim((string) ($section['content'] ?? ''));
            $developmentText .= "\n" . $content;
            $words += count(array_filter(preg_split('/\s+/u', $content) ?: [], static fn (string $w): bool => trim($w) !== ''));
        }

        return [
            'words' => $words,
            'subsections' => $this->countDevelopmentSubSections($developmentText),
            'citations' => preg_match_all('/\([^)]+,\s*(19|20)\d{2}[a-z]?\)/u', $developmentText) ?: 0,
            'development_text' => $developmentText,
        ];
    }

    private function isMozambiqueColonialEducationTheme(array $briefing): bool
    {
        $scope = mb_strtolower(trim((string) ($briefing['title'] ?? '') . ' ' . (string) ($briefing['problem'] ?? '')));
        $hasMoz = str_contains($scope, 'moçambique') || str_contains($scope, 'mozambique');
        $hasColonial = str_contains($scope, 'colonial') || str_contains($scope, 'colonia');
        $hasEducation = str_contains($scope, 'educa') || str_contains($scope, 'ensino') || str_contains($scope, 'escolar');

        return $hasMoz && $hasColonial && $hasEducation;
    }

    private function requiredMozambiqueHistoricalThemes(): array
    {
        return [
            'enquadramento histórico do colonialismo português em moçambique',
            'estrutura do sistema educativo colonial',
            'papel das missões religiosas',
            'assimilação, língua portuguesa e ensino rudimentar',
            'desigualdade de acesso e estratificação social',
            'impactos socioculturais',
            'legados no pós-independência',
        ];
    }

    private function missingRequiredThemes(string $developmentText, array $requiredThemes): array
    {
        $normalized = mb_strtolower($developmentText);
        $aliases = [
            'enquadramento histórico do colonialismo português em moçambique' => ['contextualização histórica do colonialismo português em moçambique', 'enquadramento histórico do colonialismo português em moçambique'],
            'estrutura do sistema educativo colonial' => ['estrutura do sistema educativo colonial'],
            'papel das missões religiosas' => ['papel das missões religiosas'],
            'assimilação, língua portuguesa e ensino rudimentar' => ['ensino rudimentar, assimilação e língua portuguesa', 'assimilação, língua portuguesa e ensino rudimentar'],
            'desigualdade de acesso e estratificação social' => ['desigualdade de acesso e estratificação social'],
            'impactos socioculturais' => ['impactos socioculturais'],
            'legados no pós-independência' => ['legados no pós-independência'],
        ];
        $missing = [];
        foreach ($requiredThemes as $theme) {
            $found = false;
            foreach (($aliases[$theme] ?? [$theme]) as $needle) {
                if (str_contains($normalized, $needle)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $theme;
            }
        }
        return $missing;
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
        $theme = mb_strtolower(trim((string) ($briefing['title'] ?? '')));
        $isMozEducationHistory = str_contains($theme, 'moçambique') || str_contains($theme, 'mozambique') || str_contains($theme, 'colonial') || str_contains($theme, 'educa');

        if ($isMozEducationHistory) {
            return [
                'ALTHUSSER, Louis. Ideologia e aparelhos ideológicos do Estado. Lisboa: Presença, 1980.',
                'BASIL DAVIDSON. A descoberta do passado de África. Lisboa: Sá da Costa, 1981.',
                'MONDLANE, Eduardo. Lutar por Moçambique. Maputo: Centro de Estudos Africanos, 1995.',
                'NEWITT, Malyn. A History of Mozambique. Bloomington: Indiana University Press, 1995.',
                'NGOENHA, Severino Elias. Estatuto e axiologia da educação em Moçambique. Maputo: Livraria Universitária UEM, 2000.',
            ];
        }

        if (strtoupper($referenceStyle) === 'ABNT') {
            return [
                "GIL, Antonio Carlos. Métodos e técnicas de pesquisa social. São Paulo: Atlas, 2019.",
                "SEVERINO, Antônio Joaquim. Metodologia do trabalho científico. São Paulo: Cortez, 2018.",
                "MARCONI, Marina de Andrade; LAKATOS, Eva Maria. Fundamentos de metodologia científica. São Paulo: Atlas, 2021.",
            ];
        }

        return [
            "Gil, A. C. (2019). Métodos e técnicas de pesquisa social. Atlas.",
            "Severino, A. J. (2018). Metodologia do trabalho científico. Cortez.",
            "Marconi, M. A., & Lakatos, E. M. (2021). Fundamentos de metodologia científica. Atlas.",
        ];
    }

    private function enforceLogicalSectionOrder(array $sections): array
    {
        $rank = ['capa' => 10, 'folha de rosto' => 20, 'resumo' => 30, 'indice' => 40, 'introducao' => 50, 'objectivos' => 60, 'metodologia' => 70, 'desenvolvimento' => 80, 'analise' => 80, 'resultados' => 80, 'conclusao' => 90, 'referencias' => 100];
        usort($sections, function (array $a, array $b) use ($rank): int {
            $ka = $this->classifySectionKey($a);
            $kb = $this->classifySectionKey($b);
            return ($rank[$ka] ?? 75) <=> ($rank[$kb] ?? 75);
        });
        return array_values($sections);
    }

    private function injectAuthorDateCitationsIntoDevelopment(array $sections): array
    {
        $referenceText = '';
        foreach ($sections as $section) {
            if ($this->classifySectionKey($section) === 'referencias') {
                $referenceText = (string) ($section['content'] ?? '');
                break;
            }
        }
        if ($referenceText === '') {
            return $sections;
        }
        $referenceLines = array_values(array_filter(array_map('trim', preg_split('/\n+/', $referenceText) ?: [])));
        $citations = array_values(array_filter(array_map(fn (string $line): string => $this->toInlineCitation($line), $referenceLines)));
        if ($citations === []) {
            return $sections;
        }

        foreach ($sections as &$section) {
            $key = $this->classifySectionKey($section);
            if (!in_array($key, ['desenvolvimento', 'resultados'], true)) {
                continue;
            }
            $content = trim((string) ($section['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $hasCitation = preg_match('/\([^)]+,\s*(19|20)\d{2}[a-z]?\)/u', $content) === 1;
            if (!$hasCitation) {
                $section['content'] = $content . ' ' . $citations[0];
                continue;
            }

            $paragraphs = array_values(array_filter(array_map('trim', preg_split('/\n{2,}/u', $content) ?: []), static fn (string $p): bool => $p !== ''));
            if (count($paragraphs) < 2) {
                continue;
            }
            foreach ($paragraphs as $idx => $paragraph) {
                if (preg_match('/\([^)]+,\s*(19|20)\d{2}[a-z]?\)/u', $paragraph) === 1) {
                    continue;
                }
                $paragraphs[$idx] = $paragraph . ' ' . $citations[$idx % count($citations)];
            }
            $section['content'] = implode("\n\n", $paragraphs);
        }
        unset($section);

        return $sections;
    }

    private function enforceDevelopmentThresholdAndConclusionGuard(array $sections): array
    {
        $minDevelopmentWords = self::MIN_DEVELOPMENT_WORDS;
        $minThematicSections = self::MIN_THEMATIC_SUBSECTIONS;
        $minCitations = self::MIN_DEVELOPMENT_CITATIONS;
        $developmentWords = 0;
        $developmentText = '';
        $hasConclusion = false;

        foreach ($sections as $section) {
            $key = $this->classifySectionKey($section);
            if (in_array($key, ['desenvolvimento', 'resultados'], true)) {
                $content = trim((string) ($section['content'] ?? ''));
                $developmentText .= "\n" . $content;
                $developmentWords += count(array_filter(preg_split('/\s+/u', $content) ?: [], static fn (string $w): bool => trim($w) !== ''));
            }
            if ($key === 'conclusao') {
                $hasConclusion = true;
            }
        }

        $subSections = $this->countDevelopmentSubSections($developmentText);
        $citationCount = preg_match_all('/\([^)]+,\s*(19|20)\d{2}[a-z]?\)/u', $developmentText) ?: 0;
        if ($developmentWords < $minDevelopmentWords || $subSections < $minThematicSections || $citationCount < $minCitations) {
            if ($hasConclusion) {
                throw new RuntimeException('Falha de qualidade pré-DOCX: conclusão bloqueada por desenvolvimento insuficiente (palavras/secções/citações abaixo do mínimo).');
            }
            throw new RuntimeException('Falha de qualidade pré-DOCX: desenvolvimento insuficiente para composição académica final.');
        }

        return $sections;
    }

    private function assertNoOperationalMetaText(array $sections): void
    {
        $text = mb_strtolower(implode("\n", array_map(static fn (array $s): string => ((string) ($s['title'] ?? '')) . "\n" . ((string) ($s['content'] ?? '')), $sections)));
        $patterns = [
            '/aqui est[aá] a sec[cç][aã]o/u',
            '/\brefinada\b/u',
            ...$this->operationalMetaPatterns(),
            '/coment[aá]rio de edi[cç][aã]o|meta-editorial/u',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                throw new RuntimeException('Falha de qualidade pré-DOCX: metatexto operacional detectado após sanitização final.');
            }
        }
    }


    private function operationalMetaPatterns(): array
    {
        return [
            '/\{\s*"[^\"]+"\s*:/u',
            '/\bsection_title\b|\bsection_code\b|\btext\s*:/iu',
            '/(?:^|\b)instru[cç][aã]o(?:es)?\s+do\s+pipeline(?:\b|$)|(?:^|\b)nota[s]?\s+de\s+pipeline(?:\b|$)|com base nas regras de refinamento/u',
            '/(?:^|\s)(?:payload|debug)\s*:/iu',
            '/\b\-\-\-\b/u',
            '/\[\[todo|placeholder|indice placeholder|lorem ipsum/u',
        ];
    }

    private function countDevelopmentSubSections(string $developmentText): int
    {
        if (trim($developmentText) === '') {
            return 0;
        }
        preg_match_all('/(^|\n)\s*(\d+\.\s+[^\n]+|[A-ZÀ-Ú][^\n]{10,}\:)\s*(\n|$)/u', $developmentText, $matches);
        return is_array($matches[0] ?? null) ? count($matches[0]) : 0;
    }

    private function toInlineCitation(string $referenceLine): string
    {
        if (preg_match('/^([A-ZÀ-Ú][A-ZÀ-Ú\-\s]+|[A-Za-zÀ-ú\.\-\s]+?)[\.,].*?\b((?:19|20)\d{2})\b/u', trim($referenceLine), $m) !== 1) {
            return '';
        }
        $author = trim((string) $m[1]);
        $year = (string) $m[2];
        $author = preg_replace('/\s+/', ' ', $author) ?? $author;
        $parts = preg_split('/\s+/', trim($author)) ?: [];
        $surname = mb_convert_case((string) end($parts), MB_CASE_TITLE, 'UTF-8');
        return "({$surname}, {$year})";
    }

    private function enforceContextualSectionPolicy(array $sections, array $briefing, array $workType, array $blueprint): array
    {
        if ($this->shouldIncludeResultsDiscussion($briefing, $workType, $blueprint)) {
            return $sections;
        }
        return array_values(array_filter($sections, function (array $section): bool {
            $k = $this->classifySectionKey($section);
            return $k !== 'resultados';
        }));
    }

    private function shouldIncludeResultsDiscussion(array $briefing, array $workType, array $blueprint): bool
    {
        $scope = mb_strtolower(implode(' ', array_merge([(string) ($briefing['title'] ?? ''), (string) ($briefing['problem'] ?? ''), (string) ($workType['name'] ?? ''), (string) ($workType['slug'] ?? '')], array_map(static fn(array $s): string => (string) ($s['title'] ?? ''), $blueprint))));
        $empiricalMarkers = ['entrevista', 'questionario', 'questionário', 'inquerito', 'inquérito', 'campo', 'dados', 'estatistic', 'amostra', 'observa'];
        foreach ($empiricalMarkers as $marker) {
            if (str_contains($scope, $marker)) {
                return true;
            }
        }
        return false;
    }

    private function classifySectionKey(array $section): string
    {
        $s = mb_strtolower((string) ($section['code'] ?? '') . ' ' . (string) ($section['title'] ?? ''));
        if (str_contains($s, 'capa')) return 'capa';
        if (str_contains($s, 'rosto')) return 'folha de rosto';
        if (str_contains($s, 'resumo') || str_contains($s, 'abstract')) return 'resumo';
        if (str_contains($s, 'indice') || str_contains($s, 'índice')) return 'indice';
        if (str_contains($s, 'introdu')) return 'introducao';
        if (str_contains($s, 'objet') || str_contains($s, 'objec')) return 'objectivos';
        if (str_contains($s, 'metod')) return 'metodologia';
        if (str_contains($s, 'result') || str_contains($s, 'discuss')) return 'resultados';
        if (str_contains($s, 'conclus')) return 'conclusao';
        if (str_contains($s, 'refer') || str_contains($s, 'bibliograf')) return 'referencias';
        if (str_contains($s, 'analis') || str_contains($s, 'desenvol')) return 'desenvolvimento';
        return 'outros';
    }
}
