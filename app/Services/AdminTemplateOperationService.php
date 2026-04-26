<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InstitutionRepository;
use RuntimeException;

final class AdminTemplateOperationService
{
    public function __construct(
        private readonly InstitutionRepository $institutions = new InstitutionRepository(),
        private readonly StoragePathService $paths = new StoragePathService(),
    ) {}

    /** @param array<string,mixed> $files */
    public function publishNormArtifacts(int $institutionId, array $files, int $actorId): array
    {
        $institution = $this->institutions->findById($institutionId);
        if ($institution === null) {
            throw new RuntimeException('Instituição não encontrada para publicação de normas.');
        }

        $slug = trim((string) ($institution['slug'] ?? ''));
        if ($slug === '') {
            throw new RuntimeException('Instituição sem slug válido.');
        }

        $normDir = $this->paths->normsBase() . '/' . $slug;
        $this->paths->ensureDirectory($normDir);

        $published = [];
        $txt = $this->single($files['norm_txt'] ?? null);
        if ($txt !== null) {
            $published['norma.txt'] = $this->storeFile($txt, $normDir . '/norma.txt', [
                'text/plain',
                'application/octet-stream',
            ], 3 * 1024 * 1024);
        }

        $pdf = $this->single($files['norm_pdf'] ?? null);
        if ($pdf !== null) {
            $published['norma.pdf'] = $this->storeFile($pdf, $normDir . '/norma.pdf', [
                'application/pdf',
            ], 20 * 1024 * 1024);
        }

        $meta = $this->single($files['norm_metadata'] ?? null);
        if ($meta !== null) {
            $metadataPath = $this->storeFile($meta, $normDir . '/metadata.json', [
                'application/json',
                'text/plain',
                'application/octet-stream',
            ], 2 * 1024 * 1024);

            $raw = (string) file_get_contents($metadataPath);
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                @unlink($metadataPath);
                throw new RuntimeException('metadata.json inválido: conteúdo deve ser JSON object/array.');
            }
            $normalized = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if (!is_string($normalized)) {
                throw new RuntimeException('Falha ao normalizar metadata.json.');
            }
            file_put_contents($metadataPath, $normalized . PHP_EOL);
            $published['metadata.json'] = $metadataPath;
        }

        if ($published === []) {
            throw new RuntimeException('Envie pelo menos um artefacto de norma (txt, pdf ou metadata).');
        }

        return [
            'institution_id' => $institutionId,
            'institution_slug' => $slug,
            'actor_id' => $actorId,
            'published_files' => array_keys($published),
            'published_at' => date('c'),
        ];
    }

    /** @param array<string,mixed>|null $file */
    public function publishWorkTypeTemplate(int $institutionId, int $workTypeId, ?array $file, int $actorId): array
    {
        $institution = $this->institutions->findById($institutionId);
        if ($institution === null) {
            throw new RuntimeException('Instituição não encontrada para publicação de template.');
        }

        if ($file === null) {
            throw new RuntimeException('Ficheiro template_docx é obrigatório.');
        }

        $slug = trim((string) ($institution['slug'] ?? ''));
        if ($slug === '') {
            throw new RuntimeException('Instituição sem slug válido.');
        }

        $templateDir = $this->paths->templatesBase() . '/' . $slug;
        $this->paths->ensureDirectory($templateDir);

        $filename = sprintf('work-type-%d.docx', $workTypeId);
        $stored = $this->storeFile($file, $templateDir . '/' . $filename, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ], 25 * 1024 * 1024);

        return [
            'institution_id' => $institutionId,
            'institution_slug' => $slug,
            'work_type_id' => $workTypeId,
            'template_path' => $stored,
            'actor_id' => $actorId,
            'published_at' => date('c'),
        ];
    }

    /** @param mixed $file */
    private function single(mixed $file): ?array
    {
        if (!is_array($file) || !isset($file['error'])) {
            return null;
        }

        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    /** @param array<string,mixed> $file @param array<int,string> $allowedMime */
    private function storeFile(array $file, string $target, array $allowedMime, int $maxSize): string
    {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload do ficheiro enviado.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxSize) {
            throw new RuntimeException('Ficheiro excede limite permitido.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Upload inválido.');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, $allowedMime, true)) {
            throw new RuntimeException('Tipo de ficheiro não permitido para esta operação.');
        }

        if (!move_uploaded_file($tmp, $target)) {
            throw new RuntimeException('Falha ao publicar ficheiro no storage.');
        }

        return $target;
    }
}
