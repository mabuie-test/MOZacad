<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use RuntimeException;

final class SecureUploadService
{
    /**
     * @return array<int, array{original_name:string,stored_name:string,path:string,mime:string}>
     */
    public function storeMany(array $files, string $subDir = 'orders'): array
    {
        $normalized = $this->normalizeFiles($files);
        if ($normalized === []) {
            return [];
        }

        $maxSizeBytes = (int) max(1, (int) Env::get('UPLOAD_MAX_SIZE_MB', 10)) * 1024 * 1024;
        $allowedMime = array_filter(array_map('trim', explode(',', (string) Env::get('UPLOAD_ALLOWED_MIME', 'application/pdf'))));

        $basePath = rtrim((string) Env::get('STORAGE_UPLOADS_PATH', 'storage/uploads'), '/');
        $targetDir = $basePath . '/' . trim($subDir, '/');
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Falha ao criar directório de uploads.');
        }

        $stored = [];
        foreach ($normalized as $file) {
            if ((int) $file['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Falha no upload de ficheiro.');
            }

            if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxSizeBytes) {
                throw new RuntimeException('Ficheiro fora do limite permitido.');
            }

            $tmpName = (string) $file['tmp_name'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string) $finfo->file($tmpName);
            if (!in_array($mime, $allowedMime, true)) {
                throw new RuntimeException('Tipo de ficheiro não permitido.');
            }

            $originalName = basename((string) $file['name']);
            $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            $storedName = sprintf('%s_%s.%s', date('YmdHis'), bin2hex(random_bytes(8)), $extension !== '' ? $extension : 'bin');
            $targetPath = $targetDir . '/' . $storedName;

            if (!move_uploaded_file($tmpName, $targetPath)) {
                throw new RuntimeException('Falha ao guardar ficheiro no storage.');
            }

            $stored[] = [
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'path' => $targetPath,
                'mime' => $mime,
            ];
        }

        return $stored;
    }

    private function normalizeFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $normalized[] = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }

        return array_filter($normalized, static fn (array $f): bool => (int) $f['error'] !== UPLOAD_ERR_NO_FILE);
    }
}
