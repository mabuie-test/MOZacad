ALTER TABLE human_review_queue
    ADD COLUMN IF NOT EXISTS created_by BIGINT UNSIGNED NULL AFTER reviewer_id,
    ADD COLUMN IF NOT EXISTS assigned_by BIGINT UNSIGNED NULL AFTER created_by,
    ADD COLUMN IF NOT EXISTS last_decided_by BIGINT UNSIGNED NULL AFTER assigned_by,
    ADD COLUMN IF NOT EXISTS approval_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER decision,
    ADD COLUMN IF NOT EXISTS required_approvals INT UNSIGNED NOT NULL DEFAULT 1 AFTER approval_count,
    ADD INDEX IF NOT EXISTS idx_hrq_reviewer_status (reviewer_id, status),
    ADD INDEX IF NOT EXISTS idx_hrq_stage_counts (status, approval_count, required_approvals);

CREATE TABLE IF NOT EXISTS human_review_decisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    human_review_queue_id BIGINT UNSIGNED NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    stage VARCHAR(40) NOT NULL,
    decision VARCHAR(20) NOT NULL,
    justification TEXT NULL,
    decided_at DATETIME NOT NULL,
    created_at TIMESTAMP NULL,
    INDEX idx_hrd_queue_stage (human_review_queue_id, stage, decided_at),
    INDEX idx_hrd_actor_decided (actor_id, decided_at),
    CONSTRAINT fk_hrd_queue FOREIGN KEY (human_review_queue_id) REFERENCES human_review_queue(id) ON DELETE CASCADE,
    CONSTRAINT fk_hrd_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT
);
