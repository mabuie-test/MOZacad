INSERT INTO roles (name, description, created_at, updated_at) VALUES
('user','Utilizador padrão',NOW(),NOW()),
('admin','Administrador operacional',NOW(),NOW()),
('human_reviewer','Revisor humano',NOW(),NOW()),
('superadmin','Administrador global',NOW(),NOW());

INSERT INTO institutions (name, short_name, city, country, is_active, created_at, updated_at) VALUES
('Universidade Eduardo Mondlane','UEM','Maputo','Moçambique',1,NOW(),NOW()),
('Universidade Pedagógica de Maputo','UP Maputo','Maputo','Moçambique',1,NOW(),NOW()),
('Instituto Superior de Ciências e Tecnologia de Moçambique','ISCTEM','Maputo','Moçambique',1,NOW(),NOW());

INSERT INTO academic_levels (name, slug, multiplier, description, is_active) VALUES
('Técnico','tecnico',1.0,'Nível técnico',1),
('Licenciatura','licenciatura',1.2,'Graduação',1),
('Pós-graduação','pos-graduacao',1.35,'Especialização',1),
('Mestrado','mestrado',1.6,'Mestrado',1),
('Doutoramento','doutoramento',2.0,'Doutoramento',1);

INSERT INTO work_types (name, slug, description, is_active, base_price, default_complexity, allows_full_auto_generation, requires_human_review, is_premium_type, display_order) VALUES
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

INSERT INTO language_profiles (name, locale, vocabulary_rules_json, syntax_rules_json, anti_ai_patterns_json, academic_tone_level, is_active) VALUES
('academic_formal','pt_MZ','{}','{}','{}','high',1),
('academic_humanized','pt_MZ','{}','{}','{}','high',1),
('critical_reflective','pt_MZ','{}','{}','{}','high',1),
('technical_methodological','pt_MZ','{}','{}','{}','high',1);

INSERT INTO citation_profiles (name, code, rules_json, is_active) VALUES
('APA 7','APA','{}',1),
('ABNT','ABNT','{}',1);

INSERT INTO pricing_extras (extra_code, name, amount, is_active, created_at, updated_at) VALUES
('needs_institution_cover','Capa personalizada',200,1,NOW(),NOW()),
('needs_bilingual_abstract','Abstract bilingue',300,1,NOW(),NOW()),
('premium_references','Referências premium',250,1,NOW(),NOW()),
('needs_methodology_review','Revisão metodológica',500,1,NOW(),NOW()),
('needs_humanized_revision','Revisão humanizada',400,1,NOW(),NOW()),
('needs_slides','Apresentação de slides',800,1,NOW(),NOW()),
('needs_defense_summary','Resumo de defesa',450,1,NOW(),NOW());

INSERT INTO users (name, email, phone, password_hash, is_active, created_at, updated_at) VALUES
('VIP Student','vip@mozacad.test','841111111','$2y$12$FKAVAGTDq8h//J9Kba80gOVGeJUhKWQjnBqVQ7ZPuJEQXWtOuqiLa',1,NOW(),NOW()),
('Super Admin','superadmin@mozacad.test','851111111','$2y$12$FKAVAGTDq8h//J9Kba80gOVGeJUhKWQjnBqVQ7ZPuJEQXWtOuqiLa',1,NOW(),NOW());

INSERT INTO user_discounts (user_id, name, discount_type, discount_value, usage_limit, used_count, starts_at, ends_at, is_active, created_by_admin_id, notes, created_at, updated_at) VALUES
(1,'VIP 10%','percent',10,100,0,NOW(),DATE_ADD(NOW(), INTERVAL 30 DAY),1,2,'Benefício promocional VIP',NOW(),NOW());


INSERT INTO user_roles (user_id, role_id) VALUES
(1, 1),
(2, 2),
(2, 4);

INSERT INTO institution_rules (institution_id, font_family, font_size, heading_font_size, line_spacing, margin_top, margin_right, margin_bottom, margin_left, references_style, front_page_rules_json, notes, created_at, updated_at) VALUES
(1, 'Times New Roman', 12, 14, 1.5, 2.5, 3, 2.5, 3, 'APA', '{"institution_name":"Universidade Eduardo Mondlane","city":"Maputo"}', 'Regras base UEM', NOW(), NOW()),
(2, 'Times New Roman', 12, 14, 1.5, 2.5, 3, 2.5, 3, 'APA', '{"institution_name":"Universidade Pedagógica de Maputo","city":"Maputo"}', 'Regras base UP Maputo', NOW(), NOW());

INSERT INTO courses (institution_id, name, code, is_active, created_at, updated_at) VALUES
(1, 'Engenharia Informática', 'EINF', 1, NOW(), NOW()),
(1, 'Direito', 'DIR', 1, NOW(), NOW()),
(2, 'Administração e Gestão', 'ADG', 1, NOW(), NOW());

INSERT INTO disciplines (institution_id, course_id, name, code, is_active, created_at, updated_at) VALUES
(1, 1, 'Metodologia de Investigação', 'METINV', 1, NOW(), NOW()),
(1, 1, 'Programação Web', 'PWEB', 1, NOW(), NOW()),
(2, 3, 'Seminário de Pesquisa', 'SEMPES', 1, NOW(), NOW());

INSERT INTO work_type_structures (work_type_id, section_code, section_title, section_order, is_required, min_words, max_words, notes) VALUES
(1, 'introducao', 'Introdução', 1, 1, 350, 700, 'Secção introdutória'),
(1, 'metodologia', 'Metodologia', 2, 1, 400, 900, 'Descrever abordagem'),
(1, 'resultados', 'Resultados e Discussão', 3, 1, 500, 1200, 'Análise crítica'),
(1, 'conclusao', 'Conclusão', 4, 1, 250, 600, 'Síntese final'),
(1, 'references', 'Referências', 5, 1, 50, 400, 'Lista de referências');
