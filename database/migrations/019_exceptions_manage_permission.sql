INSERT INTO permissions (code, name, description, category, is_active, created_at, updated_at)
VALUES ('exceptions.manage','Gerir exceções pós-pagamento','Operar fluxo de exceções administrativas e compliance','operations',1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  category = VALUES(category),
  is_active = VALUES(is_active),
  updated_at = NOW();

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at, updated_at)
SELECT r.id, p.id, NOW(), NOW()
FROM roles r
INNER JOIN permissions p ON p.code = 'exceptions.manage'
WHERE r.name IN ('admin','superadmin');
