CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) UNIQUE NOT NULL,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  institution_id BIGINT UNSIGNED NULL,
  course_id BIGINT UNSIGNED NULL,
  discipline_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE user_roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_user_role (user_id, role_id)
);
CREATE TABLE admin_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL
);
CREATE TABLE institutions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  short_name VARCHAR(60) NULL,
  city VARCHAR(80) NULL,
  country VARCHAR(80) NULL,
  logo_path VARCHAR(255) NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE institution_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id BIGINT UNSIGNED NOT NULL,
  font_family VARCHAR(80) DEFAULT 'Times New Roman',
  font_size DECIMAL(5,2) DEFAULT 12,
  heading_font_size DECIMAL(5,2) DEFAULT 14,
  line_spacing DECIMAL(4,2) DEFAULT 1.5,
  margin_top DECIMAL(5,2) DEFAULT 2.5,
  margin_right DECIMAL(5,2) DEFAULT 3,
  margin_bottom DECIMAL(5,2) DEFAULT 2.5,
  margin_left DECIMAL(5,2) DEFAULT 3,
  references_style VARCHAR(20) DEFAULT 'APA',
  citation_profile_id BIGINT UNSIGNED NULL,
  front_page_rules_json JSON NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE courses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(40) NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE disciplines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id BIGINT UNSIGNED NULL,
  course_id BIGINT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  code VARCHAR(40) NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE academic_levels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL,
  multiplier DECIMAL(5,2) NOT NULL DEFAULT 1,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) DEFAULT 1
);
CREATE TABLE work_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(150) UNIQUE NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) DEFAULT 1,
  base_price DECIMAL(12,2) DEFAULT 0,
  default_complexity VARCHAR(30) DEFAULT 'medium',
  allows_full_auto_generation TINYINT(1) DEFAULT 1,
  requires_human_review TINYINT(1) DEFAULT 0,
  is_premium_type TINYINT(1) DEFAULT 0,
  display_order INT DEFAULT 0
);
CREATE TABLE work_type_structures (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  work_type_id BIGINT UNSIGNED NOT NULL,
  section_code VARCHAR(80) NOT NULL,
  section_title VARCHAR(190) NOT NULL,
  section_order INT NOT NULL,
  is_required TINYINT(1) DEFAULT 1,
  min_words INT NULL,
  max_words INT NULL,
  applies_to_level BIGINT UNSIGNED NULL,
  notes TEXT NULL
);
CREATE TABLE institution_work_type_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id BIGINT UNSIGNED NOT NULL,
  work_type_id BIGINT UNSIGNED NOT NULL,
  custom_structure_json JSON NULL,
  custom_visual_rules_json JSON NULL,
  custom_reference_rules_json JSON NULL,
  notes TEXT NULL
);
CREATE TABLE citation_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  code VARCHAR(30) NOT NULL,
  rules_json JSON NULL,
  is_active TINYINT(1) DEFAULT 1
);
CREATE TABLE language_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  locale VARCHAR(20) NOT NULL,
  vocabulary_rules_json JSON NULL,
  syntax_rules_json JSON NULL,
  anti_ai_patterns_json JSON NULL,
  academic_tone_level VARCHAR(40) NULL,
  is_active TINYINT(1) DEFAULT 1
);
CREATE TABLE templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id BIGINT UNSIGNED NULL,
  work_type_id BIGINT UNSIGNED NULL,
  template_type VARCHAR(40) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  institution_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  discipline_id BIGINT UNSIGNED NOT NULL,
  academic_level_id BIGINT UNSIGNED NOT NULL,
  work_type_id BIGINT UNSIGNED NOT NULL,
  title_or_theme VARCHAR(255) NOT NULL,
  subtitle VARCHAR(255) NULL,
  problem_statement TEXT NULL,
  general_objective TEXT NULL,
  specific_objectives_json JSON NULL,
  hypothesis TEXT NULL,
  keywords_json JSON NULL,
  target_pages INT NOT NULL,
  complexity_level VARCHAR(20) NOT NULL,
  deadline_date DATETIME NOT NULL,
  notes TEXT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'draft',
  final_price DECIMAL(12,2) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE order_requirements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  needs_institution_cover TINYINT(1) DEFAULT 0,
  needs_abstract TINYINT(1) DEFAULT 1,
  needs_bilingual_abstract TINYINT(1) DEFAULT 0,
  needs_methodology_review TINYINT(1) DEFAULT 0,
  needs_humanized_revision TINYINT(1) DEFAULT 0,
  needs_slides TINYINT(1) DEFAULT 0,
  needs_defense_summary TINYINT(1) DEFAULT 0,
  notes TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE order_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  attachment_type VARCHAR(50) NOT NULL,
  file_name VARCHAR(190) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NULL,
  created_at TIMESTAMP NULL
);
CREATE TABLE invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  invoice_number VARCHAR(60) NOT NULL UNIQUE,
  amount DECIMAL(12,2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'MZN',
  status VARCHAR(30) NOT NULL,
  issued_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(40) NOT NULL,
  method VARCHAR(40) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency VARCHAR(10) NOT NULL,
  msisdn VARCHAR(20) NOT NULL,
  status VARCHAR(40) NOT NULL,
  internal_reference VARCHAR(80) NOT NULL,
  external_reference VARCHAR(80) NULL,
  provider_transaction_id VARCHAR(120) NULL,
  provider_status VARCHAR(60) NULL,
  status_message VARCHAR(255) NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  paid_at DATETIME NULL
);
CREATE TABLE debito_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NULL,
  wallet_id VARCHAR(40) NOT NULL,
  debito_reference VARCHAR(100) NULL,
  request_payload_json JSON NOT NULL,
  response_payload_json JSON NOT NULL,
  last_status_payload_json JSON NULL,
  provider_response_code VARCHAR(40) NULL,
  provider_response_message VARCHAR(255) NULL,
  status VARCHAR(40) NOT NULL,
  last_checked_at DATETIME NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE payment_status_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(40) NOT NULL,
  provider_status VARCHAR(80) NULL,
  payload_json JSON NULL,
  source VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NULL
);
CREATE TABLE pricing_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_code VARCHAR(100) NOT NULL UNIQUE,
  rule_value VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE pricing_extras (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  extra_code VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE coupons (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  discount_type VARCHAR(20) NOT NULL,
  discount_value DECIMAL(12,2) NOT NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  usage_limit INT NULL,
  used_count INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1
);
CREATE TABLE user_discounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  discount_type VARCHAR(20) NOT NULL,
  discount_value DECIMAL(12,2) NOT NULL,
  work_type_id BIGINT UNSIGNED NULL,
  extra_code VARCHAR(80) NULL,
  usage_limit INT NULL,
  used_count INT NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_by_admin_id BIGINT UNSIGNED NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE discount_usage_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_discount_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NOT NULL,
  amount_discounted DECIMAL(12,2) NOT NULL,
  details_json JSON NULL,
  created_at TIMESTAMP NULL
);
CREATE TABLE generated_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  version INT DEFAULT 1,
  status VARCHAR(40) NOT NULL,
  created_at TIMESTAMP NULL
);
CREATE TABLE revisions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  reason TEXT NOT NULL,
  status VARCHAR(30) NOT NULL,
  reviewer_comment TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE human_review_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  reviewer_id BIGINT UNSIGNED NULL,
  status VARCHAR(30) NOT NULL,
  decision VARCHAR(30) NULL,
  comments TEXT NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  read_at DATETIME NULL,
  created_at TIMESTAMP NULL
);
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  subject_type VARCHAR(80) NOT NULL,
  subject_id BIGINT UNSIGNED NULL,
  payload_json JSON NULL,
  created_at TIMESTAMP NULL
);
CREATE TABLE ai_jobs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  stage VARCHAR(60) NOT NULL,
  status VARCHAR(30) NOT NULL,
  payload_json JSON NULL,
  result_json JSON NULL,
  error_text TEXT NULL,
  next_retry_at DATETIME NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL
);
