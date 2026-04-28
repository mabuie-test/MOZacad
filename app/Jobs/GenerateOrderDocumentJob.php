<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Helpers\Database;
use App\Repositories\AcademicLevelRepository;
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
        $resolvedRulesDto = (new RuleResolverService())->resolve($institutionRules, $institutionWorkTypeRules, $academicLevel, $normContext, $templateResolution);
        $resolvedRules = [
            'visualRules' => $resolvedRulesDto->visualRules,
            'referenceRules' => $resolvedRulesDto->referenceRules,
            'structureRules' => $resolvedRulesDto->structureRules,
            'meta' => $resolvedRulesDto->meta,
        ];

        $sections = $workTypeRepo->getStructureByWorkType((int) $order['work_type_id']);
        $blueprint = (new StructureBuilderService())->build($sections, $resolvedRulesDto->structureRules);
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
        }

        $formatted = (new InstitutionFormattingService())->apply($cited, $resolvedRules);
        $doc = (new DocxAssemblyService())->assemble($formatted, (string) $briefing['title']);

        $documents = new GeneratedDocumentRepository();
        $latest = $documents->findLatestByOrderId($orderId);
        $nextVersion = ((int) ($latest['version'] ?? 0)) + 1;
        $filename = sprintf('order-%d-v%d-%s.docx', $orderId, $nextVersion, date('YmdHis'));
        $path = (new ExportService())->saveDocx($doc, $filename);
        if (!$this->documentFileExists($path)) {
            throw new RuntimeException('Falha ao persistir documento DOCX no storage.');
        }

        $requiresReview = (bool) ($order['work_type_id'] && ((new WorkTypeRepository())->findById((int) $order['work_type_id'])['requires_human_review'] ?? false));
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
            $orders->updateStatus($orderId, $requiresReview ? 'under_human_review' : 'ready');

            $documentId = $documents->create($orderId, $path, $documentStatus, $effectiveVersion);
            if ($requiresReview) {
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
            'queued_for_human_review' => $requiresReview,
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
}
