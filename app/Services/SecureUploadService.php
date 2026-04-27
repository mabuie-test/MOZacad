<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use RuntimeException;

final class SecureUploadService
{
    public function __construct(private readonly StoragePathService $paths = new StoragePathService()) {}

    public function storeMany(array $files, string $subDir = 'orders'): array
    {
        $normalized = $this->normalizeFiles($files);
        if ($normalized === []) return [];

        $maxSizeBytes = (int) max(1, (int) Env::get('UPLOAD_MAX_SIZE_MB', 10)) * 1024 * 1024;
        $allowedMime = array_filter(array_map('trim', explode(',', (string) Env::get('UPLOAD_ALLOWED_MIME', 'application/pdf'))));
        $allowedExtensionsGlobal = array_values(array_filter(array_map(
            static fn (string $ext): string => strtolower(trim($ext)),
            explode(',', (string) Env::get('UPLOAD_ALLOWED_EXTENSIONS', 'pdf'))
        )));
        $allowedExtensionsByMime = [
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'text/plain' => ['txt'],
        ];
        $enforceAntimalware = filter_var((string) Env::get('UPLOAD_ANTIMALWARE_ENABLED', false), FILTER_VALIDATE_BOOL);
        if ($this->isProduction() && !$enforceAntimalware) {
            throw new RuntimeException('Produção exige UPLOAD_ANTIMALWARE_ENABLED=true para aceitar uploads.');
        }

        $cleanSubDir = trim(preg_replace('/\.\.+/', '', $subDir) ?? $subDir, '/');
        $targetDir = $this->paths->uploadsBase() . '/' . $cleanSubDir;
        $this->paths->ensureDirectory($targetDir);

        $stored = [];
        foreach ($normalized as $file) {
            if ((int) $file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no upload de ficheiro.');
            if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxSizeBytes) throw new RuntimeException('Ficheiro fora do limite permitido.');

            $tmpName = (string) $file['tmp_name'];
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmpName);
            if (!in_array($mime, $allowedMime, true)) throw new RuntimeException('Tipo de ficheiro não permitido.');

            $originalName = basename((string) $file['name']);
            $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension === '') {
                throw new RuntimeException('Ficheiro sem extensão não permitido.');
            }
            if (!in_array($extension, $allowedExtensionsGlobal, true)) {
                throw new RuntimeException('Extensão de ficheiro não permitida.');
            }
            $allowedExtensions = $allowedExtensionsByMime[$mime] ?? [];
            if ($allowedExtensions !== [] && !in_array($extension, $allowedExtensions, true)) {
                throw new RuntimeException('Extensão incompatível com o tipo de ficheiro.');
            }

            $this->assertAntimalwareClean($tmpName);

            $storedName = sprintf('%s_%s.%s', date('YmdHis'), bin2hex(random_bytes(8)), $extension !== '' ? $extension : 'bin');
            $targetPath = $targetDir . '/' . $storedName;

            if (!move_uploaded_file($tmpName, $targetPath)) throw new RuntimeException('Falha ao guardar ficheiro no storage.');

            $stored[] = ['original_name' => $originalName, 'stored_name' => $storedName, 'path' => $targetPath, 'mime' => $mime];
        }

        return $stored;
    }

    private function normalizeFiles(array $files): array
    {
        if (!isset($files['name'])) return [];
        if (!is_array($files['name'])) return [$files];

        $normalized = [];
        for ($i = 0; $i < count($files['name']); $i++) {
            $normalized[] = ['name' => $files['name'][$i] ?? '', 'tmp_name' => $files['tmp_name'][$i] ?? '', 'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $files['size'][$i] ?? 0];
        }

        return array_filter($normalized, static fn (array $f): bool => (int) $f['error'] !== UPLOAD_ERR_NO_FILE);
    }

    private function assertAntimalwareClean(string $tmpPath): void
    {
        $enabled = filter_var((string) Env::get('UPLOAD_ANTIMALWARE_ENABLED', false), FILTER_VALIDATE_BOOL);
        if (!$enabled) {
            return;
        }

        $configured = trim((string) Env::get('UPLOAD_ANTIMALWARE_COMMAND', 'clamscan --no-summary'));
        if ($configured === '') {
            throw new RuntimeException('Scanner antimalware não configurado.');
        }

        $binary = strtok($configured, ' ');
        if (!is_string($binary) || trim($binary) === '') {
            throw new RuntimeException('Comando antimalware inválido.');
        }

        $binaryPath = trim((string) shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));
        if ($binaryPath === '') {
            throw new RuntimeException('Scanner antimalware indisponível no ambiente.');
        }

        $command = $configured . ' ' . escapeshellarg($tmpPath) . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode === 0) {
            return;
        }

        if ($exitCode === 1) {
            throw new RuntimeException('Ficheiro bloqueado por verificação antimalware.');
        }

        throw new RuntimeException('Falha na verificação antimalware do upload.');
    }

    private function isProduction(): bool
    {
        return strtolower(trim((string) Env::get('APP_ENV', 'production'))) === 'production';
    }
}
