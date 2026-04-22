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

        try {
            $result = (new PaymentWebhookService())->processDebitoWebhook($rawBody, [
                'content_type' => (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]);
        } catch (RuntimeException) {
            $this->json(['received' => true, 'processed' => false, 'reason' => 'processing_error'], 500);
            return;
        }

        $status = (int) ($result['http_status'] ?? 200);
        unset($result['http_status']);

        $this->json($result, $status);
    }
}
