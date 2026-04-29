<?php

declare(strict_types=1);

namespace App\Services;


final class InstitutionNormDocumentService
{
    public function __construct(
        private readonly \App\Repositories\TemplateArtifactRepository $artifacts = new \App\Repositories\TemplateArtifactRepository(),
        private readonly ApplicationLoggerService $logger = new ApplicationLoggerService(),
    ) {}
    /**
     * @return array{slug:string,base_path:string,txt_path:?string,pdf_path:?string,metadata_path:?string,content:string,source:string,metadata:array<string,mixed>,notes:array<int,string>,reference_style:?string,visual_overrides:array<string,mixed>,front_page_overrides:array<string,mixed>,structure_overrides:array<string,mixed>}
     */
    public function resolveForInstitution(array $institution): array
    {
        $slug = $this->institutionSlug($institution);
        $basePath = $this->basePath() . '/' . $slug;

        $txtPath = $this->resolveExisting($basePath . '/norma.txt');
        $pdfPath = $this->resolveExisting($basePath . '/norma.pdf');
        $metadataPath = $this->resolveExisting($basePath . '/metadata.json');

        $institutionId = (int) ($institution['id'] ?? 0);
        $sqlNormTxt = $this->artifacts->findActive($institutionId, null, 'norm_txt');
        $sqlNormPdf = $this->artifacts->findActive($institutionId, null, 'norm_pdf');
        $sqlNormMetadata = $this->artifacts->findActive($institutionId, null, 'norm_metadata');

        $metadata = $this->readMetadata($metadataPath);
        $content = '';
        $source = 'none';

        if ($txtPath !== null) {
            $content = $this->cleanText((string) file_get_contents($txtPath));
            $source = 'txt';
        } elseif (!empty($metadata['normalized_text']) && is_string($metadata['normalized_text'])) {
            $content = $this->cleanText($metadata['normalized_text']);
            $source = 'metadata';
        } elseif ($pdfPath !== null) {
            $extraction = $this->extractTextFromPdf($pdfPath);
            $this->recordParsingMetrics((int) ($institution['id'] ?? 0), $pdfPath, $extraction);
            if ($extraction['text'] !== '') {
                $content = $extraction['text'];
                $source = $extraction['method'];
                $metadata = $this->persistNormalizedText($basePath, $metadata, $content);
                $txtPath = $this->resolveExisting($basePath . '/norma.txt');
                $metadataPath = $this->resolveExisting($basePath . '/metadata.json');
            } else {
                $source = 'pdf_unparsed';
            }
        }

        return [
            'slug' => $slug,
            'base_path' => $basePath,
            'txt_path' => $txtPath,
            'pdf_path' => $pdfPath,
            'metadata_path' => $metadataPath,
            'content' => $content,
            'source' => $source,
            'metadata' => $metadata,
            'notes' => $this->normalizeNotes($metadata['notes'] ?? []),
            'reference_style' => $this->normalizeReferenceStyle($metadata['reference_style'] ?? null),
            'visual_overrides' => is_array($metadata['visual_overrides'] ?? null) ? $metadata['visual_overrides'] : [],
            'front_page_overrides' => is_array($metadata['front_page_overrides'] ?? null) ? $metadata['front_page_overrides'] : [],
            'structure_overrides' => is_array($metadata['structure_overrides'] ?? null) ? $metadata['structure_overrides'] : [],
            'institution_profile' => [
                'name' => (string) ($metadata['institution_name'] ?? ($institution['name'] ?? '')),
                'faculty' => (string) ($metadata['faculty'] ?? ''),
                'department' => (string) ($metadata['department'] ?? ''),
            ],
            'traceability' => [
                'sql_norm_txt' => $sqlNormTxt,
                'sql_norm_pdf' => $sqlNormPdf,
                'sql_norm_metadata' => $sqlNormMetadata,
                'filesystem_sql_converged' => ($txtPath === null || (string) ($sqlNormTxt['file_path'] ?? '') === $txtPath)
                    && ($pdfPath === null || (string) ($sqlNormPdf['file_path'] ?? '') === $pdfPath)
                    && ($metadataPath === null || (string) ($sqlNormMetadata['file_path'] ?? '') === $metadataPath),
            ],
        ];
    }

    private function institutionSlug(array $institution): string
    {
        $candidate = trim((string) ($institution['slug'] ?? ''));
        if ($candidate === '') {
            $candidate = trim((string) ($institution['short_name'] ?? ''));
        }
        if ($candidate === '') {
            $candidate = trim((string) ($institution['name'] ?? 'institution'));
        }

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $candidate);
        $ascii = $ascii === false ? $candidate : $ascii;
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii) ?? 'institution');
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'institution';
    }

    private function basePath(): string
    {
        return (new StoragePathService())->normsBase();
    }

    private function resolveExisting(string $path): ?string
    {
        return is_file($path) ? $path : null;
    }

    /**
     * @return array<string,mixed>
     */
    private function readMetadata(?string $metadataPath): array
    {
        if ($metadataPath === null) {
            return [];
        }

        $raw = file_get_contents($metadataPath);
        $decoded = json_decode(is_string($raw) ? $raw : '', true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeReferenceStyle(mixed $referenceStyle): ?string
    {
        if (!is_string($referenceStyle)) {
            return null;
        }

        $normalized = strtoupper(trim($referenceStyle));
        if ($normalized === '') {
            return null;
        }

        return preg_replace('/[^A-Z0-9\-_]/', '', $normalized) ?: null;
    }

    private function cleanText(string $content): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $normalized = preg_replace('/[\t ]+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\n{3,}/', "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @return array{text:string,method:string,pages:int,size:int,status:string}
     */
    private function extractTextFromPdf(string $pdfPath): array
    {
        $directText = $this->runPdftotext($pdfPath);
        if ($directText !== '') {
            return [
                'text' => $directText,
                'method' => 'pdf_extracted',
                'pages' => $this->countPdfPages($pdfPath),
                'size' => strlen($directText),
                'status' => 'success',
            ];
        }

        $ocrText = $this->runOcrFallback($pdfPath);
        if ($ocrText !== '') {
            return [
                'text' => $ocrText,
                'method' => 'pdf_ocr_fallback',
                'pages' => $this->countPdfPages($pdfPath),
                'size' => strlen($ocrText),
                'status' => 'success',
            ];
        }

        return ['text' => '', 'method' => 'pdf_unparsed', 'pages' => $this->countPdfPages($pdfPath), 'size' => 0, 'status' => 'failure'];
    }

    private function runPdftotext(string $pdfPath): string
    {
        $binary = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($binary === '') {
            return '';
        }
        $outputPath = tempnam(sys_get_temp_dir(), 'norm_txt_');
        if (!is_string($outputPath) || $outputPath === '') {
            return '';
        }
        $command = sprintf('%s -layout %s %s 2>/dev/null', escapeshellarg($binary), escapeshellarg($pdfPath), escapeshellarg($outputPath));
        exec($command, $unusedOutput, $statusCode);
        if ($statusCode !== 0 || !is_file($outputPath)) { @unlink($outputPath); return ''; }
        $raw = (string) file_get_contents($outputPath);
        @unlink($outputPath);
        return $this->cleanText($raw);
    }

    private function runOcrFallback(string $pdfPath): string
    {
        $ocrmypdf = trim((string) shell_exec('command -v ocrmypdf 2>/dev/null'));
        if ($ocrmypdf !== '') {
            $ocrPdf = tempnam(sys_get_temp_dir(), 'norm_ocr_');
            if (is_string($ocrPdf) && $ocrPdf !== '') {
                $ocrPdfWithExt = $ocrPdf . '.pdf';
                $cmd = sprintf('%s --skip-text %s %s 2>/dev/null', escapeshellarg($ocrmypdf), escapeshellarg($pdfPath), escapeshellarg($ocrPdfWithExt));
                exec($cmd, $o, $status);
                if ($status === 0 && is_file($ocrPdfWithExt)) {
                    $text = $this->runPdftotext($ocrPdfWithExt);
                    @unlink($ocrPdfWithExt);
                    if ($text !== '') {
                        return $text;
                    }
                }
            }
        }

        $internal = getenv('NORM_OCR_PIPELINE_ENDPOINT');
        if (is_string($internal) && trim($internal) !== '') {
            $this->logger->alert('norm.parsing.ocr_pipeline_configured_but_not_implemented', ['endpoint' => $internal]);
        }
        return '';
    }

    private function countPdfPages(string $pdfPath): int
    {
        $pdfinfo = trim((string) shell_exec('command -v pdfinfo 2>/dev/null'));
        if ($pdfinfo === '') {
            return 0;
        }
        $output = shell_exec(sprintf('%s %s 2>/dev/null', escapeshellarg($pdfinfo), escapeshellarg($pdfPath)));
        if (!is_string($output) || $output === '') {
            return 0;
        }
        if (preg_match('/Pages:\s+(\d+)/', $output, $m) === 1) {
            return (int) $m[1];
        }
        return 0;
    }

    private function persistNormalizedText(string $basePath, array $metadata, string $content): array
    {
        $normalized = $this->cleanText($content);
        if ($normalized === '') {
            return $metadata;
        }
        @file_put_contents($basePath . '/norma.txt', $normalized . PHP_EOL);
        $metadata['normalized_text'] = $normalized;
        @file_put_contents($basePath . '/metadata.json', (string) json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $metadata;
    }

    private function recordParsingMetrics(int $institutionId, string $pdfPath, array $extraction): void
    {
        $this->logger->info('norm.parsing.metrics', [
            'institution_id' => $institutionId,
            'pdf_path' => $pdfPath,
            'status' => $extraction['status'] ?? 'failure',
            'method' => $extraction['method'] ?? 'pdf_unparsed',
            'pages_processed' => (int) ($extraction['pages'] ?? 0),
            'extracted_size' => (int) ($extraction['size'] ?? 0),
        ]);
    }

    /**
     * @return array<int,string>
     */
    private function normalizeNotes(mixed $notes): array
    {
        if (is_string($notes) && trim($notes) !== '') {
            return [trim($notes)];
        }
        if (!is_array($notes)) {
            return [];
        }

        $normalized = [];
        foreach ($notes as $note) {
            $value = trim((string) $note);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }
}
