CREATE TABLE IF NOT EXISTS permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(120) NOT NULL,
  name VARCHAR(150) NOT NULL,
  description VARCHAR(255) NULL,
  category VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_permissions_code (code),
  INDEX idx_permissions_category_code (category, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id BIGINT UNSIGNED NOT NULL,
  permission_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_role_permission (role_id, permission_id),
  INDEX idx_role_permissions_permission_id (permission_id),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE audit_logs
  ADD COLUMN IF NOT EXISTS permission_code VARCHAR(120) NULL AFTER payload_json,
  ADD INDEX IF NOT EXISTS idx_audit_logs_permission_code (permission_code);

INSERT INTO permissions (code, name, description, category, is_active, created_at, updated_at) VALUES
('admin.overview.view','Ver overview admin','Acesso ao centro operacional','admin_view',1,NOW(),NOW()),
('admin.users.view','Ver utilizadores','Acesso à secção de utilizadores','admin_view',1,NOW(),NOW()),
('admin.orders.view','Ver pedidos','Acesso à secção de pedidos','admin_view',1,NOW(),NOW()),
('admin.payments.view','Ver pagamentos','Acesso à secção de pagamentos','admin_view',1,NOW(),NOW()),
('catalog.institutions.view','Ver instituições','Leitura de instituições','catalog',1,NOW(),NOW()),
('catalog.institutions.manage','Gerir instituições','Criar/editar instituições','catalog',1,NOW(),NOW()),
('catalog.courses.view','Ver cursos','Leitura de cursos','catalog',1,NOW(),NOW()),
('catalog.courses.manage','Gerir cursos','Criar/editar cursos','catalog',1,NOW(),NOW()),
('catalog.disciplines.view','Ver disciplinas','Leitura de disciplinas','catalog',1,NOW(),NOW()),
('catalog.disciplines.manage','Gerir disciplinas','Criar/editar disciplinas','catalog',1,NOW(),NOW()),
('catalog.work_types.view','Ver tipos de trabalho','Leitura de tipos de trabalho','catalog',1,NOW(),NOW()),
('catalog.work_types.manage','Gerir tipos de trabalho','Criar/editar tipos de trabalho','catalog',1,NOW(),NOW()),
('human_review.queue.view','Ver fila humana','Acesso à fila de revisão','human_review',1,NOW(),NOW()),
('human_review.assign','Atribuir revisão humana','Atribuir revisor para fila humana','human_review',1,NOW(),NOW()),
('human_review.approve','Decidir revisão humana','Aprovar/rejeitar revisão humana','human_review',1,NOW(),NOW()),
('payments.confirm_manual','Confirmar pagamento manual','Confirmação manual de pagamento','payments',1,NOW(),NOW()),
('operations.process_ai_queue','Processar fila AI','Execução manual da fila de IA','operations',1,NOW(),NOW()),
('pricing.view','Ver pricing','Leitura de regras e extras','pricing',1,NOW(),NOW()),
('pricing.manage','Gerir pricing','Editar regras e extras de pricing','pricing',1,NOW(),NOW()),
('commercial.discounts.view','Ver descontos','Leitura de descontos','commercial',1,NOW(),NOW()),
('commercial.discounts.manage','Gerir descontos','Criar/editar descontos','commercial',1,NOW(),NOW()),
('commercial.coupons.view','Ver cupões','Leitura de cupões','commercial',1,NOW(),NOW()),
('commercial.coupons.manage','Gerir cupões','Criar/editar/activar cupões','commercial',1,NOW(),NOW()),
('governance.rules.view','Ver regras institucionais','Leitura das regras institucionais','governance',1,NOW(),NOW()),
('governance.rules.manage','Gerir regras institucionais','Editar regras institucionais','governance',1,NOW(),NOW()),
('governance.templates.view','Ver templates','Leitura de templates e artefactos','governance',1,NOW(),NOW()),
('governance.templates.manage','Gerir templates','Publicação e activação de templates','governance',1,NOW(),NOW()),
('permissions.manage','Gerir permissões','Gestão da matriz de permissões por papel','security',1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  category = VALUES(category),
  is_active = VALUES(is_active),
  updated_at = NOW();

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at, updated_at)
SELECT r.id, p.id, NOW(), NOW()
FROM roles r
INNER JOIN permissions p ON 1=1
WHERE r.name = 'superadmin';

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at, updated_at)
SELECT r.id, p.id, NOW(), NOW()
FROM roles r
INNER JOIN permissions p ON p.code IN (
  'admin.overview.view','admin.users.view','admin.orders.view','admin.payments.view',
  'catalog.institutions.view','catalog.institutions.manage',
  'catalog.courses.view','catalog.courses.manage',
  'catalog.disciplines.view','catalog.disciplines.manage',
  'catalog.work_types.view','catalog.work_types.manage',
  'human_review.queue.view','human_review.assign','human_review.approve',
  'payments.confirm_manual','operations.process_ai_queue',
  'pricing.view','pricing.manage',
  'commercial.discounts.view','commercial.discounts.manage',
  'commercial.coupons.view','commercial.coupons.manage',
  'governance.rules.view','governance.rules.manage',
  'governance.templates.view','governance.templates.manage'
)
WHERE r.name = 'admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at, updated_at)
SELECT r.id, p.id, NOW(), NOW()
FROM roles r
INNER JOIN permissions p ON p.code IN (
  'human_review.queue.view','human_review.assign','human_review.approve'
)
WHERE r.name = 'human_reviewer';
