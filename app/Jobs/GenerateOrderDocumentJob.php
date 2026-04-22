<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\AcademicLevelRepository;
use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\InstitutionRepository;
use App\Repositories\OrderRepository;
use App\Repositories\OrderRequirementRepository;
use App\Repositories\WorkTypeRepository;
use App\Services\AIOrchestrationService;
use App\Services\AcademicRefinementService;
use App\Services\CitationFormatterService;
use App\Services\DocxAssemblyService;
use App\Services\ExportService;
use App\Services\HumanReviewQueueService;
use App\Services\InstitutionFormattingService;
use App\Services\InstitutionNormDocumentService;
use App\Services\MozPortugueseHumanizerService;
use App\Services\PromptComposerService;
use App\Services\RequirementInterpreterService;
use App\Services\RuleResolverService;
use App\Services\StructureBuilderService;
use RuntimeException;

final class GenerateOrderDocumentJob
{
    public function handle(int $orderId): array
    {
        $orders = new OrderRepository();
        $order = $orders->findById($orderId);
        if ($order === null) {
            throw new RuntimeException('Pedido não encontrado para geração documental.');
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

        $resolvedRulesDto = (new RuleResolverService())->resolve($institutionRules, $institutionWorkTypeRules, $academicLevel, $normContext);
        $resolvedRules = [
            'visualRules' => $resolvedRulesDto->visualRules,
            'referenceRules' => $resolvedRulesDto->referenceRules,
            'structureRules' => $resolvedRulesDto->structureRules,
            'meta' => $resolvedRulesDto->meta,
        ];

        $sections = $workTypeRepo->getStructureByWorkType((int) $order['work_type_id']);
        $blueprint = (new StructureBuilderService())->build($sections, $resolvedRulesDto->structureRules);
        $prompts = (new PromptComposerService())->compose($blueprint, $resolvedRules, $briefing);

        $generated = (new AIOrchestrationService())->run($prompts, $blueprint);
        $refined = (new AcademicRefinementService())->refine($generated, [
            'reference_style' => (string) ($resolvedRules['referenceRules']['style'] ?? 'APA'),
        ]);
        $humanized = (new MozPortugueseHumanizerService())->humanize(
            $refined,
            'academic_humanized_pt_mz',
            (bool) ($briefing['extras']['needs_humanized_revision'] ?? true)
        );
        $cited = (new CitationFormatterService())->format($humanized, (string) ($resolvedRules['referenceRules']['style'] ?? 'APA'));
        $formatted = (new InstitutionFormattingService())->apply($cited, $resolvedRules);

        $doc = (new DocxAssemblyService())->assemble($formatted, $briefing['title']);

        $latest = (new GeneratedDocumentRepository())->findLatestByOrderId($orderId);
        $nextVersion = ((int) ($latest['version'] ?? 0)) + 1;
        $filename = sprintf('order-%d-v%d-%s.docx', $orderId, $nextVersion, date('YmdHis'));
        $path = (new ExportService())->saveDocx($doc, $filename);

        $requiresReview = (bool) ($order['work_type_id'] && ((new WorkTypeRepository())->findById((int) $order['work_type_id'])['requires_human_review'] ?? false));
        $documentStatus = $requiresReview ? 'pending_human_review' : 'generated';
        $orders->updateStatus($orderId, $requiresReview ? 'under_human_review' : 'ready');

        $documentId = (new GeneratedDocumentRepository())->create($orderId, $path, $documentStatus, $nextVersion);

        $queueId = null;
        if ($requiresReview) {
            $queueId = (new HumanReviewQueueService())->enqueue($orderId);
        }

        return [
            'order_id' => $orderId,
            'generated_document_id' => $documentId,
            'version' => $nextVersion,
            'file_path' => $path,
            'queued_for_human_review' => $requiresReview,
            'human_review_queue_id' => $queueId,
        ];
    }
}
