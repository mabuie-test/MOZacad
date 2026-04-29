<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class OpenAIProvider implements AIProviderInterface
{
    private Client $http;
    private string $apiKey;
    /** @var array<string,mixed> */
    private array $config;

    public function __construct()
    {
        $this->config = Config::get('ai');
        $openAI = (array) ($this->config['openai'] ?? []);
        $this->apiKey = trim((string) ($openAI['api_key'] ?? ''));
        $this->http = new Client([
            'base_uri' => rtrim((string) ($openAI['base_url'] ?? 'https://api.openai.com/v1'), '/') . '/',
            'timeout' => (int) ($openAI['timeout'] ?? 60),
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

        $payload = $this->buildBasePayload($this->resolveModel('structure'), $prompt);
        $payload['text'] = [
            'format' => [
                'type' => 'json_schema',
                'name' => 'mozacad_structured_output',
                'schema' => $schema,
                'strict' => true,
            ],
        ];

        $decoded = $this->sendRequest($payload);
        $outputText = $this->extractOutputText($decoded);
        $structured = json_decode($outputText, true);

        if (!is_array($structured)) {
            throw new RuntimeException('OpenAI retornou structured output não-JSON válido.');
        }

        return $structured;
    }

    private function requestText(string $model, string $input): string
    {
        $payload = $this->buildBasePayload($model, $input);

        if ((bool) (($this->config['openai']['enable_structured_output'] ?? false))) {
            $payload['text'] = ['format' => ['type' => 'text']];
        }

        $decoded = $this->sendRequest($payload);

        return $this->extractOutputText($decoded);
    }

    private function buildBasePayload(string $model, string $input): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY não configurada.');
        }

        $payload = [
            'model' => $model,
            'input' => $input,
            'max_output_tokens' => (int) ($this->config['openai']['max_output_tokens'] ?? 4000),
        ];

        if ($this->supportsTemperature($model)) {
            $payload['temperature'] = (float) ($this->config['openai']['temperature'] ?? 0.7);
        }

        return $payload;
    }

    private function sendRequest(array $payload): array
    {
        try {
            $response = $this->http->post('responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Falha na comunicação com OpenAI: ' . $e->getMessage(), 0, $e);
        }

        $this->assertSuccessResponse($response);

        $decoded = json_decode((string) $response->getBody(), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida da OpenAI (JSON malformado).');
        }

        return $decoded;
    }

    private function extractOutputText(array $decoded): string
    {
        if ((string) ($decoded['status'] ?? '') === 'incomplete') {
            throw new RuntimeException('OpenAI retornou resposta incompleta; aumente ai.openai.max_output_tokens.');
        }

        $outputText = $decoded['output_text'] ?? null;
        if (is_string($outputText) && trim($outputText) !== '') {
            return trim($outputText);
        }

        $parts = [];
        foreach (($decoded['output'] ?? []) as $outputItem) {
            foreach (($outputItem['content'] ?? []) as $content) {
                $contentType = (string) ($content['type'] ?? '');
                $text = '';
                if (is_string($content['text'] ?? null)) {
                    $text = (string) $content['text'];
                } elseif (is_array($content['text'] ?? null) && is_string($content['text']['value'] ?? null)) {
                    $text = (string) $content['text']['value'];
                }

                if (in_array($contentType, ['output_text', 'text'], true) && trim($text) !== '') {
                    $parts[] = trim($text);
                }
            }
        }

        if ($parts === []) {
            throw new RuntimeException('OpenAI retornou resposta sem conteúdo textual utilizável.');
        }

        return implode("\n", $parts);
    }

    private function resolveModel(string $useCase): string
    {
        $defaults = (array) ($this->config['models']['openai'] ?? []);
        $baseModel = (string) ($defaults['default'] ?? 'gpt-5');
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


    private function supportsTemperature(string $model): bool
    {
        $normalized = strtolower(trim($model));

        foreach (['gpt-5', 'o1', 'o3'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return false;
            }
        }

        return true;
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
            ? (string) ($decoded['error']['message'] ?? $decoded['message'] ?? 'Erro desconhecido da OpenAI.')
            : 'Erro desconhecido da OpenAI.';

        $errorType = is_array($decoded) ? (string) ($decoded['error']['type'] ?? '') : '';
        $requestId = $response->getHeaderLine('x-request-id');

        $details = trim(implode(' | ', array_filter([
            $errorType !== '' ? 'type=' . $errorType : null,
            $requestId !== '' ? 'request_id=' . $requestId : null,
        ])));

        throw new RuntimeException(sprintf(
            'OpenAI retornou HTTP %d: %s%s',
            $status,
            $errorMessage,
            $details !== '' ? ' (' . $details . ')' : ''
        ));
    }
}
