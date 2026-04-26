<?php

declare(strict_types=1);

namespace App\Services;


final class InstitutionNormDocumentService
{
    public function __construct(private readonly \App\Repositories\TemplateArtifactRepository $artifacts = new \App\Repositories\TemplateArtifactRepository()) {}
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
            $extracted = $this->extractTextFromPdf($pdfPath);
            if ($extracted !== '') {
                $content = $extracted;
                $source = 'pdf_extracted';
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

    private function extractTextFromPdf(string $pdfPath): string
    {
        $binary = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($binary === '') {
            return '';
        }

        $outputPath = tempnam(sys_get_temp_dir(), 'norm_txt_');
        if (!is_string($outputPath) || $outputPath === '') {
            return '';
        }

        $command = sprintf(
            '%s -layout %s %s 2>/dev/null',
            escapeshellarg($binary),
            escapeshellarg($pdfPath),
            escapeshellarg($outputPath)
        );
        exec($command, $unusedOutput, $statusCode);
        if ($statusCode !== 0 || !is_file($outputPath)) {
            @unlink($outputPath);
            return '';
        }

        $raw = (string) file_get_contents($outputPath);
        @unlink($outputPath);
        return $this->cleanText($raw);
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
