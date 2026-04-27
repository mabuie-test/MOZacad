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
        $db->beginTransaction();
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
                $db->commit();
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
            $db->commit();
            $nextVersion = $effectiveVersion;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
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
        $fallbackByCode = [
            'resumo' => 'Este trabalho apresenta uma síntese académica do tema, destacando enquadramento conceptual, objectivo central e relevância para o contexto moçambicano.',
            'introducao' => 'A introdução enquadra o tema no cenário académico, define o problema orientador e evidencia a pertinência científica da investigação proposta.',
            'metodologia' => 'A metodologia adopta uma abordagem compatível com investigação aplicada, com revisão bibliográfica e análise crítica de fundamentos teóricos.',
            'resultados_discussao' => 'A discussão apresenta interpretações consistentes com a literatura, destacando implicações teóricas e contribuições para práticas institucionais.',
            'conclusao' => 'A conclusão sintetiza os principais contributos, reafirma o objectivo geral e propõe recomendações para aprofundamento académico.',
            'referencias' => "Referências organizadas conforme {$referenceStyle}.",
        ];

        $sections = [];
        foreach ($blueprint as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            $title = (string) ($item['title'] ?? 'Secção');

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
            } else {
                continue;
            }

            $sections[] = [
                'code' => $code,
                'title' => $title,
                'content' => $fallbackByCode[$key],
            ];
        }

        if ($sections === []) {
            $title = trim((string) ($briefing['title'] ?? 'Trabalho Académico'));
            $sections = [
                ['code' => 'resumo', 'title' => 'Resumo', 'content' => $fallbackByCode['resumo']],
                ['code' => 'introducao', 'title' => 'Introdução', 'content' => "{$title}. {$fallbackByCode['introducao']}"],
                ['code' => 'metodologia', 'title' => 'Metodologia', 'content' => $fallbackByCode['metodologia']],
                ['code' => 'resultados_discussao', 'title' => 'Resultados e Discussão', 'content' => $fallbackByCode['resultados_discussao']],
                ['code' => 'conclusao', 'title' => 'Conclusão', 'content' => $fallbackByCode['conclusao']],
                ['code' => 'referencias', 'title' => 'Referências', 'content' => $fallbackByCode['referencias']],
            ];
        }

        return $sections;
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
