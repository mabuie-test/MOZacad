# Política de Logging Seguro (Operacional)

## Pode ser registado
- `request_id`, método HTTP, URI, estado interno/externo.
- IDs técnicos (`payment_id`, `order_id`, `queue_id`, `invoice_id`).
- Resumos de payload/response (apenas chaves e campos operacionais mínimos).

## Deve ser truncado
- Strings extensas (> 2048 chars) são truncadas automaticamente.
- Mensagens de provider são reduzidas para amostras curtas.

## Deve ser mascarado
- `msisdn`: mantém prefixo/sufixo e mascara dígitos intermédios.
- Campos com nomes sensíveis (`*token*`, `*secret*`, `*signature*`, `authorization`) são redigidos.

## Nunca persistir em claro
- Tokens de autenticação, segredos, API keys, passwords.
- Headers completos de autorização/assinatura.
- Payloads integrais de pagamento quando não estritamente necessários.

## Rotação mínima
- `storage/logs/debito.log` e `storage/logs/application.log` rodam automaticamente ao atingir `LOG_MAX_FILE_SIZE_MB`.
