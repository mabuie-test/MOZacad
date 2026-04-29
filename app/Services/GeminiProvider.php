<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class GeminiProvider implements AIProviderInterface
{
    private Client $http;
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = trim((string) Env::get('GEMINI_API_KEY', ''));
        $this->http = new Client([
            'base_uri' => rtrim((string) Env::get('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/') . '/',
            'timeout' => (int) Env::get('GEMINI_TIMEOUT', 60),
        ]);
    }

    public function generate(string $prompt, array $context = []): string
    {
        $contextText = $context === []
            ? ''
            : "Contexto adicional (JSON):\n" . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n\n";

        return $this->requestText($this->resolveModel('content'), $contextText . $prompt);
    }

    public function refine(string $text, array $rules = []): string
    {
        $rulesText = $rules === []
            ? ''
            : "Regras de refinamento (JSON):\n" . json_encode($rules, JSON_UNESCAPED_UNICODE) . "\n\n";

        return $this->requestText($this->resolveModel('refinement'), $rulesText . $text);
    }

    public function humanize(string $text, string $profile = 'academic_humanized_pt_mz'): string
    {
        $prompt = <<<PROMPT
Reescreve o texto em português de Moçambique com fluidez humana e tom académico.
Perfil: {$profile}
- Mantém o sentido original.
- Não inventes factos nem citações.
- Reduz marcas de texto artificial.

Texto:
{$text}
PROMPT;

        return $this->requestText($this->resolveModel('humanizer'), $prompt);
    }

    public function generateStructured(string $prompt, array $schema): array
    {
        if ($schema === []) {
            throw new RuntimeException('Schema estruturado inválido para generateStructured().');
        }

        $schemaInstruction = "Responde exclusivamente com JSON válido, sem Markdown, sem ```json, sem explicações. "
            . "O JSON deve obedecer ao seguinte schema:\n"
            . json_encode($schema, JSON_UNESCAPED_UNICODE)
            . "\n\nPedido:\n"
            . $prompt;

        $outputText = $this->requestText($this->resolveModel('structure'), $schemaInstruction);
        $cleaned = $this->stripMarkdownFences($outputText);
        $structured = json_decode($cleaned, true);

        if (!is_array($structured)) {
            throw new RuntimeException('Gemini retornou structured output não-JSON válido.');
        }

        return $structured;
    }

    private function requestText(string $model, string $input): string
    {
        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $input]],
            ]],
            'generationConfig' => [
                'temperature' => (float) Env::get('GEMINI_TEMPERATURE', 0.7),
                'maxOutputTokens' => (int) Env::get('GEMINI_MAX_OUTPUT_TOKENS', 4000),
            ],
        ];

        $decoded = $this->sendRequest($model, $payload);

        return $this->extractOutputText($decoded);
    }

    private function sendRequest(string $model, array $payload): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY não configurada.');
        }

        $endpoint = sprintf('models/%s:generateContent?key=%s', rawurlencode($model), rawurlencode($this->apiKey));

        try {
            $response = $this->http->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Falha na comunicação com Gemini: ' . $e->getMessage(), 0, $e);
        }

        $this->assertSuccessResponse($response);

        $decoded = json_decode((string) $response->getBody(), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida da Gemini (JSON malformado).');
        }

        return $decoded;
    }

    private function extractOutputText(array $decoded): string
    {
        $parts = [];
        foreach (($decoded['candidates'] ?? []) as $candidate) {
            foreach (($candidate['content']['parts'] ?? []) as $part) {
                $text = is_string($part['text'] ?? null) ? trim((string) $part['text']) : '';
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        if ($parts === []) {
            throw new RuntimeException('Gemini retornou resposta sem conteúdo textual utilizável.');
        }

        return implode("\n", $parts);
    }

    private function resolveModel(string $useCase): string
    {
        $baseModel = (string) Env::get('GEMINI_MODEL', 'gemini-2.5-flash');

        return match ($useCase) {
            'structure' => (string) Env::get('GEMINI_MODEL_STRUCTURE', $baseModel),
            'content' => (string) Env::get('GEMINI_MODEL_CONTENT', $baseModel),
            'refinement' => (string) Env::get('GEMINI_MODEL_REFINEMENT', $baseModel),
            'humanizer' => (string) Env::get('GEMINI_MODEL_HUMANIZER', $baseModel),
            default => $baseModel,
        };
    }

    private function stripMarkdownFences(string $text): string
    {
        $trimmed = trim($text);
        $trimmed = preg_replace('/^```json\s*/i', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/^```\s*/', '', $trimmed) ?? $trimmed;
        $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;

        return trim($trimmed);
    }

    private function assertSuccessResponse(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300) {
            return;
        }

        $rawBody = (string) $response->getBody();
        $decoded = json_decode($rawBody, true);
        $errorMessage = is_array($decoded)
            ? (string) ($decoded['error']['message'] ?? $decoded['message'] ?? 'Erro desconhecido da Gemini.')
            : 'Erro desconhecido da Gemini.';

        throw new RuntimeException(sprintf('Gemini retornou HTTP %d: %s', $status, $errorMessage));
    }
}
