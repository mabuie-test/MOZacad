ALTER TABLE payments
    ADD UNIQUE KEY uq_payments_provider_external_reference (provider, external_reference);

ALTER TABLE payments
    ADD INDEX idx_payments_order_status_updated (order_id, status, updated_at);

ALTER TABLE invoices
    ADD INDEX idx_invoices_order_status (order_id, status);

ALTER TABLE debito_transactions
    ADD UNIQUE KEY uq_debito_transactions_payment (payment_id),
    ADD UNIQUE KEY uq_debito_transactions_reference (debito_reference);

ALTER TABLE human_review_queue
    ADD INDEX idx_hrq_order_status_updated (order_id, status, updated_at);
