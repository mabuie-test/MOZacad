<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class OpenAIProvider implements AIProviderInterface
{
    private Client $http;
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) Env::get('OPENAI_API_KEY', '');
        $this->http = new Client([
            'base_uri' => rtrim((string) Env::get('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/') . '/',
            'timeout' => (int) Env::get('OPENAI_TIMEOUT', 60),
        ]);
    }

    public function generate(string $prompt): string
    {
        return $this->request(
            (string) Env::get('OPENAI_MODEL_CONTENT', Env::get('OPENAI_MODEL', 'gpt-5')),
            $prompt
        );
    }

    public function refine(string $text, array $rules = []): string
    {
        $rulesText = $rules !== [] ? 'Regras de refinamento: ' . json_encode($rules, JSON_UNESCAPED_UNICODE) . "\n\n" : '';
        return $this->request((string) Env::get('OPENAI_MODEL_REFINEMENT', Env::get('OPENAI_MODEL', 'gpt-5')), $rulesText . $text);
    }

    private function request(string $model, string $input): string
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY não configurada.');
        }

        $payload = [
            'model' => $model,
            'input' => $input,
            'max_output_tokens' => (int) Env::get('OPENAI_MAX_OUTPUT_TOKENS', 4000),
            'temperature' => (float) Env::get('OPENAI_TEMPERATURE', 0.7),
        ];

        if (filter_var(Env::get('OPENAI_ENABLE_STRUCTURED_OUTPUT', false), FILTER_VALIDATE_BOOL)) {
            $payload['text'] = ['format' => ['type' => 'text']];
        }

        try {
            $response = $this->http->post('responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
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
            throw new RuntimeException('Resposta inválida da OpenAI.');
        }

        $outputText = $decoded['output_text'] ?? null;
        if (is_string($outputText) && $outputText !== '') {
            return $outputText;
        }

        $parts = [];
        foreach (($decoded['output'] ?? []) as $outputItem) {
            foreach (($outputItem['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text' && !empty($content['text'])) {
                    $parts[] = $content['text'];
                }
            }
        }

        if ($parts === []) {
            throw new RuntimeException('OpenAI retornou resposta sem conteúdo textual.');
        }

        return implode("\n", $parts);
    }

    private function assertSuccessResponse(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $decoded = json_decode((string) $response->getBody(), true);
            $error = is_array($decoded)
                ? (string) ($decoded['error']['message'] ?? $decoded['message'] ?? 'Erro desconhecido da OpenAI.')
                : 'Erro desconhecido da OpenAI.';

            throw new RuntimeException(sprintf('OpenAI retornou HTTP %d: %s', $status, $error));
        }
    }
}
