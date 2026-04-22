<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;

final class InstitutionNormDocumentService
{
    /**
     * @return array{slug:string,base_path:string,txt_path:?string,pdf_path:?string,metadata_path:?string,content:string,source:string,metadata:array<string,mixed>}
     */
    public function resolveForInstitution(array $institution): array
    {
        $slug = $this->institutionSlug($institution);
        $basePath = $this->basePath() . '/' . $slug;

        $txtPath = $this->resolveExisting($basePath . '/norma.txt');
        $pdfPath = $this->resolveExisting($basePath . '/norma.pdf');
        $metadataPath = $this->resolveExisting($basePath . '/metadata.json');

        $metadata = $this->readMetadata($metadataPath);
        $content = '';
        $source = 'none';

        if ($txtPath !== null) {
            $content = $this->cleanText((string) file_get_contents($txtPath));
            $source = 'txt';
        } elseif (!empty($metadata['normalized_text']) && is_string($metadata['normalized_text'])) {
            $content = $this->cleanText($metadata['normalized_text']);
            $source = 'metadata';
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
        return dirname(__DIR__, 2) . '/' . trim((string) Env::get('STORAGE_NORMS_PATH', 'storage/norms'), '/');
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

    private function cleanText(string $content): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $content);
        $normalized = preg_replace('/[\t ]+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\n{3,}/', "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }
}
