<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AIOrchestrationService;
use App\Services\AcademicRefinementService;
use App\Services\CitationFormatterService;
use App\Services\DocxAssemblyService;
use App\Services\ExportService;
use App\Services\InstitutionFormattingService;
use App\Services\MozPortugueseHumanizerService;
use App\Services\PromptComposerService;
use App\Services\StructureBuilderService;

final class GenerateOrderDocumentJob
{
    public function handle(array $briefing, array $rules, array $sections): string
    {
        $blueprint = (new StructureBuilderService())->build($sections);
        $prompts = (new PromptComposerService())->compose($blueprint, $rules, $briefing);
        $generated = (new AIOrchestrationService())->run($prompts);
        $refined = (new AcademicRefinementService())->refine($generated);
        $humanized = (new MozPortugueseHumanizerService())->humanize($refined);
        $cited = (new CitationFormatterService())->format($humanized, $rules['references_style'] ?? 'APA');
        $formatted = (new InstitutionFormattingService())->apply($cited, $rules);
        $doc = (new DocxAssemblyService())->assemble($formatted, $briefing['title'] ?? 'Documento');
        return (new ExportService())->saveDocx($doc, 'order-' . date('YmdHis') . '.docx');
    }
}
