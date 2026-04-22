ALTER TABLE institutions
    ADD COLUMN slug VARCHAR(120) NULL AFTER short_name;

UPDATE institutions
SET slug = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(short_name, name), ' ', '-'), '/', '-'), '.', ''), '--', '-'))
WHERE slug IS NULL OR slug = '';

ALTER TABLE institutions
    MODIFY slug VARCHAR(120) NOT NULL,
    ADD UNIQUE KEY uq_institutions_slug (slug);

ALTER TABLE payments
    ADD INDEX idx_payments_external_reference (external_reference),
    ADD INDEX idx_payments_internal_reference (internal_reference),
    ADD INDEX idx_payments_order_id (order_id),
    ADD INDEX idx_payments_user_id (user_id),
    ADD UNIQUE KEY uq_payments_internal_reference (internal_reference);

ALTER TABLE invoices
    ADD INDEX idx_invoices_order_id (order_id);

ALTER TABLE orders
    ADD INDEX idx_orders_user_id (user_id);

ALTER TABLE ai_jobs
    ADD INDEX idx_ai_jobs_order_id (order_id),
    ADD INDEX idx_ai_jobs_order_stage_status (order_id, stage, status);

ALTER TABLE human_review_queue
    ADD INDEX idx_human_review_queue_order_id (order_id);

ALTER TABLE generated_documents
    ADD INDEX idx_generated_documents_order_id (order_id),
    ADD UNIQUE KEY uq_generated_documents_order_version (order_id, version);

ALTER TABLE user_discounts
    ADD INDEX idx_user_discounts_user_id (user_id);

ALTER TABLE coupons
    ADD INDEX idx_coupons_code (code);

ALTER TABLE user_roles
    ADD CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE;

ALTER TABLE courses
    ADD CONSTRAINT fk_courses_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE;

ALTER TABLE disciplines
    ADD CONSTRAINT fk_disciplines_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_disciplines_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL;

ALTER TABLE invoices
    ADD CONSTRAINT fk_invoices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_invoices_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE;

ALTER TABLE payments
    ADD CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE;

ALTER TABLE ai_jobs
    ADD CONSTRAINT fk_ai_jobs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE;

ALTER TABLE human_review_queue
    ADD CONSTRAINT fk_hrq_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_hrq_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE generated_documents
    ADD CONSTRAINT fk_generated_documents_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE;

ALTER TABLE user_discounts
    ADD CONSTRAINT fk_user_discounts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
