INSERT IGNORE INTO roles (name, description, created_at, updated_at) VALUES
('user','Utilizador padrão',NOW(),NOW()),
('admin','Administrador operacional',NOW(),NOW()),
('human_reviewer','Revisor humano',NOW(),NOW()),
('superadmin','Administrador global',NOW(),NOW());


INSERT IGNORE INTO permissions (code, name, description, category, is_active, created_at, updated_at) VALUES
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
('permissions.manage','Gerir permissões','Gestão da matriz de permissões por papel','security',1,NOW(),NOW()),
('exceptions.manage','Gerir exceções pós-pagamento','Operar fluxo de exceções administrativas e compliance','operations',1,NOW(),NOW());

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
  'governance.templates.view','governance.templates.manage',
  'exceptions.manage'
)
WHERE r.name = 'admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at, updated_at)
SELECT r.id, p.id, NOW(), NOW()
FROM roles r
INNER JOIN permissions p ON p.code IN ('human_review.queue.view','human_review.assign','human_review.approve')
WHERE r.name = 'human_reviewer';

INSERT IGNORE INTO institutions (name, short_name, slug, city, country, is_active, created_at, updated_at) VALUES
('Universidade Eduardo Mondlane','UEM','uem','Maputo','Moçambique',1,NOW(),NOW()),
('Universidade Pedagógica de Maputo','UP Maputo','up-maputo','Maputo','Moçambique',1,NOW(),NOW()),
('Instituto Superior de Ciências e Tecnologia de Moçambique','ISCTEM','isctem','Maputo','Moçambique',1,NOW(),NOW());

INSERT IGNORE INTO academic_levels (name, slug, multiplier, description, is_active) VALUES
('Técnico','tecnico',1.0,'Nível técnico',1),
('Licenciatura','licenciatura',1.2,'Graduação',1),
('Pós-graduação','pos-graduacao',1.35,'Especialização',1),
('Mestrado','mestrado',1.6,'Mestrado',1),
('Doutoramento','doutoramento',2.0,'Doutoramento',1);

INSERT IGNORE INTO work_types (name, slug, description, is_active, base_price, default_complexity, allows_full_auto_generation, requires_human_review, is_premium_type, display_order) VALUES
('Trabalho de pesquisa','trabalho-pesquisa','Pesquisa académica',1,800,'medium',1,0,0,1),
('Projecto de pesquisa','projecto-pesquisa','Projecto estruturado',1,1500,'medium',1,0,0,2),
('Relatório de estágio','relatorio-estagio','Relatório profissional',1,2000,'high',1,0,1,3),
('Relatório de práticas','relatorio-praticas','Práticas laboratoriais',1,1800,'medium',1,0,0,4),
('Artigo científico','artigo-cientifico','Artigo académico',1,1200,'high',1,0,1,5),
('Resenha crítica','resenha-critica','Análise crítica',1,700,'medium',1,0,0,6),
('Ensaio académico','ensaio-academico','Ensaio formal',1,900,'medium',1,0,0,7),
('Trabalho de campo','trabalho-campo','Pesquisa de campo',1,1800,'high',1,0,1,8),
('Revisão de literatura','revisao-literatura','Estado da arte',1,1400,'high',1,0,1,9),
('Estudo de caso','estudo-caso','Caso aplicado',1,1600,'high',1,0,1,10),
('Proposta de TCC','proposta-tcc','Proposta inicial',1,1700,'high',1,0,1,11),
('Monografia','monografia','Trabalho final de curso',1,4500,'very_high',0,1,1,12);

INSERT IGNORE INTO language_profiles (name, locale, vocabulary_rules_json, syntax_rules_json, anti_ai_patterns_json, academic_tone_level, is_active) VALUES
('academic_formal','pt_MZ','{}','{}','{}','high',1),
('academic_humanized','pt_MZ','{}','{}','{}','high',1),
('critical_reflective','pt_MZ','{}','{}','{}','high',1),
('technical_methodological','pt_MZ','{}','{}','{}','high',1);

INSERT IGNORE INTO citation_profiles (name, code, rules_json, is_active) VALUES
('APA 7','APA','{}',1),
('ABNT','ABNT','{}',1);

INSERT IGNORE INTO pricing_extras (extra_code, name, amount, is_active, created_at, updated_at) VALUES
('needs_institution_cover','Capa personalizada',200,1,NOW(),NOW()),
('needs_bilingual_abstract','Abstract bilingue',300,1,NOW(),NOW()),
('premium_references','Referências premium',250,1,NOW(),NOW()),
('needs_methodology_review','Revisão metodológica',500,1,NOW(),NOW()),
('needs_humanized_revision','Revisão humanizada',400,1,NOW(),NOW()),
('needs_slides','Apresentação de slides',800,1,NOW(),NOW()),
('needs_defense_summary','Resumo de defesa',450,1,NOW(),NOW());

-- Credenciais seed:
-- vip@mozacad.test / admin123
-- teste@admin.com / admin123
-- teste@teste.com / admin123 (admin operacional)
INSERT IGNORE INTO users (name, email, phone, password_hash, is_active, created_at, updated_at) VALUES
('VIP Student','vip@mozacad.test','841111111','$2y$12$FKAVAGTDq8h//J9Kba80gOVGeJUhKWQjnBqVQ7ZPuJEQXWtOuqiLa',1,NOW(),NOW()),
('Super Admin','teste@admin.com','851111111','$2y$12$FKAVAGTDq8h//J9Kba80gOVGeJUhKWQjnBqVQ7ZPuJEQXWtOuqiLa',1,NOW(),NOW()),
('Admin Teste','teste@teste.com','852222222','$2y$12$bsviR6u36PvtaV8TJr1y6.0wJtstYuqhu5SMVEQLsdxNmx9iaLhI2',1,NOW(),NOW())
ON DUPLICATE KEY UPDATE
name = VALUES(name),
phone = VALUES(phone),
password_hash = VALUES(password_hash),
is_active = VALUES(is_active),
updated_at = NOW();

INSERT IGNORE INTO user_discounts (user_id, name, discount_type, discount_value, usage_limit, used_count, starts_at, ends_at, is_active, created_by_admin_id, notes, created_at, updated_at) VALUES
(1,'VIP 10%','percent',10,100,0,NOW(),DATE_ADD(NOW(), INTERVAL 30 DAY),1,2,'Benefício promocional VIP',NOW(),NOW());


INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
INNER JOIN roles r ON r.name = 'user'
WHERE u.email = 'vip@mozacad.test';

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
INNER JOIN roles r ON r.name = 'admin'
WHERE u.email = 'teste@admin.com';

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
INNER JOIN roles r ON r.name = 'superadmin'
WHERE u.email = 'teste@admin.com';

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
INNER JOIN roles r ON r.name = 'admin'
WHERE u.email = 'teste@teste.com';

INSERT IGNORE INTO institution_rules (institution_id, font_family, font_size, heading_font_size, line_spacing, margin_top, margin_right, margin_bottom, margin_left, references_style, front_page_rules_json, notes, created_at, updated_at) VALUES
(1, 'Times New Roman', 12, 14, 1.5, 2.5, 3, 2.5, 3, 'APA', '{"institution_name":"Universidade Eduardo Mondlane","city":"Maputo"}', 'Regras base UEM', NOW(), NOW()),
(2, 'Times New Roman', 12, 14, 1.5, 2.5, 3, 2.5, 3, 'APA', '{"institution_name":"Universidade Pedagógica de Maputo","city":"Maputo"}', 'Regras base UP Maputo', NOW(), NOW());

INSERT IGNORE INTO courses (institution_id, name, code, is_active, created_at, updated_at) VALUES
(1, 'Engenharia Informática', 'EINF', 1, NOW(), NOW()),
(1, 'Direito', 'DIR', 1, NOW(), NOW()),
(2, 'Administração e Gestão', 'ADG', 1, NOW(), NOW());

INSERT IGNORE INTO disciplines (institution_id, course_id, name, code, is_active, created_at, updated_at) VALUES
(1, 1, 'Metodologia de Investigação', 'METINV', 1, NOW(), NOW()),
(1, 1, 'Programação Web', 'PWEB', 1, NOW(), NOW()),
(2, 3, 'Seminário de Pesquisa', 'SEMPES', 1, NOW(), NOW());

INSERT IGNORE INTO work_type_structures (work_type_id, section_code, section_title, section_order, is_required, min_words, max_words, notes) VALUES
(1, 'introducao', 'Introdução', 1, 1, 350, 700, 'Secção introdutória'),
(1, 'metodologia', 'Metodologia', 2, 1, 400, 900, 'Descrever abordagem'),
(1, 'resultados', 'Resultados e Discussão', 3, 1, 500, 1200, 'Análise crítica'),
(1, 'conclusao', 'Conclusão', 4, 1, 250, 600, 'Síntese final'),
(1, 'references', 'Referências', 5, 1, 50, 400, 'Lista de referências');
