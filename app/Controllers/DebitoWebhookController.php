<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PaymentWebhookService;
use RuntimeException;

final class DebitoWebhookController extends BaseController
{
    public function handle(): void
    {
        $rawBody = file_get_contents('php://input') ?: '';

        $headers = [
            'content_type' => (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'x_debito_signature' => (string) ($_SERVER['HTTP_X_DEBITO_SIGNATURE'] ?? ''),
            'x-webhook-signature' => (string) ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? ''),
        ];

        try {
            $result = (new PaymentWebhookService())->processDebitoWebhook($rawBody, $headers);
        } catch (RuntimeException) {
            $this->json(['received' => true, 'processed' => false, 'reason' => 'processing_error'], 500);
            return;
        }

        $status = (int) ($result['http_status'] ?? 200);
        unset($result['http_status']);

        $this->json($result, $status);
    }
}
