CREATE TABLE IF NOT EXISTS auth_login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip_address VARCHAR(64) NOT NULL,
  failed_attempts INT NOT NULL DEFAULT 0,
  last_failed_at DATETIME NULL,
  locked_until DATETIME NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uq_auth_login_attempts_email_ip (email, ip_address),
  INDEX idx_auth_login_attempts_locked_until (locked_until)
);
