ALTER TABLE audit_logs
  ADD COLUMN IF NOT EXISTS previous_hash CHAR(64) NULL AFTER permission_code,
  ADD COLUMN IF NOT EXISTS event_hash CHAR(64) NULL AFTER previous_hash,
  ADD INDEX IF NOT EXISTS idx_audit_logs_actor_created_at (actor_id, created_at),
  ADD INDEX IF NOT EXISTS idx_audit_logs_action_created_at (action, created_at),
  ADD INDEX IF NOT EXISTS idx_audit_logs_subject (subject_type, subject_id),
  ADD UNIQUE INDEX IF NOT EXISTS uq_audit_logs_event_hash (event_hash);

CREATE TABLE IF NOT EXISTS audit_log_archives (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  archived_until DATETIME NOT NULL,
  storage_uri VARCHAR(255) NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  created_at TIMESTAMP NULL,
  INDEX idx_audit_log_archives_archived_until (archived_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO permissions (code, name, description, category, is_active, created_at, updated_at) VALUES
('audit.logs.view','Ver trilha de auditoria','Pesquisa de eventos de auditoria','security',1,NOW(),NOW()),
('audit.logs.export','Exportar trilha de auditoria','Exportação CSV/JSON para auditoria externa','security',1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  category = VALUES(category),
  is_active = VALUES(is_active),
  updated_at = NOW();
