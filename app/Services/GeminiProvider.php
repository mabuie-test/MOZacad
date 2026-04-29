<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class GeminiProvider implements AIProviderInterface
{
    private const STRUCTURED_MAX_RETRIES = 2;

    private Client $http;
    private string $apiKey;
    /** @var array<string,mixed> */
    private array $config;
    private ApplicationLoggerService $logger;
    private LogSanitizerService $sanitizer;

    public function __construct()
    {
        $this->config = Config::get('ai');
        $gemini = (array) ($this->config['gemini'] ?? []);
        $this->apiKey = trim((string) ($gemini['api_key'] ?? ''));
        $this->logger = new ApplicationLoggerService();
        $this->sanitizer = new LogSanitizerService();
        $this->http = new Client([
            'base_uri' => rtrim((string) ($gemini['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/') . '/',
            'timeout' => (int) ($gemini['timeout'] ?? 60),
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

        $schemaInstruction = $this->buildStructuredInstruction($prompt, $schema);
        $lastError = 'Falha desconhecida de structured output.';
        $lastRawOutput = null;

        for ($attempt = 1; $attempt <= self::STRUCTURED_MAX_RETRIES + 1; $attempt++) {
            $outputText = $this->requestText($this->resolveModel('structure'), $schemaInstruction);
            $lastRawOutput = $outputText;
            $cleaned = $this->stripMarkdownFences($outputText);
            $structured = json_decode($cleaned, true);

            if (!is_array($structured)) {
                $lastError = 'Gemini retornou structured output não-JSON válido.';
            } else {
                $schemaError = $this->validateSchema($structured, $schema);
                if ($schemaError === null) {
                    return $structured;
                }
                $lastError = 'Gemini retornou JSON fora do schema: ' . $schemaError;
            }

            $this->logger->error('ai.provider.gemini.structured_output.invalid', [
                'attempt' => $attempt,
                'max_attempts' => self::STRUCTURED_MAX_RETRIES + 1,
                'reason' => $lastError,
                'payload' => $this->sanitizer->sanitize([
                    'model' => $this->resolveModel('structure'),
                    'schema' => $schema,
                    'output' => $cleaned,
                ]),
            ]);

            if ($attempt <= self::STRUCTURED_MAX_RETRIES) {
                $schemaInstruction = $this->buildSchemaCorrectionInstruction($prompt, $schema, $cleaned, $lastError);
            }
        }

        throw new RuntimeException('Gemini structured output falhou definitivamente: ' . $lastError . ' (attempts=' . (self::STRUCTURED_MAX_RETRIES + 1) . ').');
    }

    private function buildStructuredInstruction(string $prompt, array $schema): string
    {
        return "Responde exclusivamente com JSON válido, sem Markdown, sem ```json, sem explicações. "
            . "O JSON deve obedecer ao seguinte schema:\n"
            . json_encode($schema, JSON_UNESCAPED_UNICODE)
            . "\n\nPedido:\n"
            . $prompt;
    }

    private function buildSchemaCorrectionInstruction(string $prompt, array $schema, string $previousOutput, string $error): string
    {
        return "A resposta anterior não cumpriu o schema exigido.\n"
            . "Erro de validação: {$error}\n"
            . "Resposta anterior:\n{$previousOutput}\n\n"
            . "Corrige automaticamente e responde APENAS com JSON válido, sem Markdown, sem explicações.\n"
            . "Schema obrigatório:\n"
            . json_encode($schema, JSON_UNESCAPED_UNICODE)
            . "\n\nPedido original:\n{$prompt}";
    }

    private function validateSchema(mixed $data, array $schema, string $path = '$'): ?string
    {
        $expectedType = is_string($schema['type'] ?? null) ? (string) $schema['type'] : null;
        if ($expectedType !== null) {
            $typeError = $this->assertType($data, $expectedType, $path);
            if ($typeError !== null) {
                return $typeError;
            }
        }

        if ($expectedType === 'object' && is_array($data)) {
            $required = is_array($schema['required'] ?? null) ? $schema['required'] : [];
            foreach ($required as $requiredKey) {
                if (is_string($requiredKey) && !array_key_exists($requiredKey, $data)) {
                    return sprintf('%s.%s ausente (campo obrigatório).', $path, $requiredKey);
                }
            }

            $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
            foreach ($properties as $key => $propertySchema) {
                if (!is_string($key) || !is_array($propertySchema) || !array_key_exists($key, $data)) {
                    continue;
                }

                $childError = $this->validateSchema($data[$key], $propertySchema, $path . '.' . $key);
                if ($childError !== null) {
                    return $childError;
                }
            }
        }

        if ($expectedType === 'array' && is_array($data) && is_array($schema['items'] ?? null)) {
            foreach ($data as $index => $item) {
                $childError = $this->validateSchema($item, $schema['items'], $path . '[' . $index . ']');
                if ($childError !== null) {
                    return $childError;
                }
            }
        }

        return null;
    }

    private function assertType(mixed $value, string $expectedType, string $path): ?string
    {
        $matches = match ($expectedType) {
            'object' => is_array($value) && array_is_list($value) === false,
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => true,
        };

        if ($matches) {
            return null;
        }

        return sprintf('%s deve ser %s, recebido %s.', $path, $expectedType, get_debug_type($value));
    }

    private function requestText(string $model, string $input): string
    {
        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $input]],
            ]],
            'generationConfig' => [
                'temperature' => (float) ($this->config['gemini']['temperature'] ?? 0.7),
                'maxOutputTokens' => (int) ($this->config['gemini']['max_output_tokens'] ?? 4000),
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
        $defaults = (array) ($this->config['models']['gemini'] ?? []);
        $baseModel = (string) ($defaults['default'] ?? 'gemini-2.5-flash');
        $tasks = (array) ($defaults['tasks'] ?? []);

        $resolved = match ($useCase) {
            'structure' => (string) ($tasks['structure'] ?? ''),
            'content' => (string) ($tasks['content'] ?? ''),
            'refinement' => (string) ($tasks['refinement'] ?? ''),
            'humanizer' => (string) ($tasks['humanizer'] ?? ''),
            default => '',
        };

        return trim($resolved) !== '' ? $resolved : $baseModel;
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
