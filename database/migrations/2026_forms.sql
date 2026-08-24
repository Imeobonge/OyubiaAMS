-- ============================================================
-- Migration: custom forms feature (run once per database)
-- ============================================================
-- Apply on EXISTING installs where install.php has been removed.
-- cPanel → phpMyAdmin → select the database → SQL tab → paste & Go.
-- Run on BOTH databases: bndpitco_oyubiacyf (production) and
-- bndpitco_oyubiacyfdemo (demo). Safe to re-run (CREATE TABLE IF NOT EXISTS).
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS forms (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) NOT NULL,
  description TEXT NULL,
  slug        VARCHAR(32) NOT NULL,
  status      ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
  created_by  INT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_form_slug (slug),
  CONSTRAINT fk_form_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS form_fields (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  form_id     INT NOT NULL,
  label       VARCHAR(255) NOT NULL,
  help_text   VARCHAR(255) NULL,
  type        ENUM('short_text','paragraph','multiple_choice','checkboxes','dropdown','email','phone','number','date')
                NOT NULL DEFAULT 'short_text',
  options     TEXT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  position    INT NOT NULL DEFAULT 0,
  KEY idx_field_form (form_id),
  CONSTRAINT fk_field_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS form_responses (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  form_id       INT NOT NULL,
  submitted_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  respondent_ip VARCHAR(45) NULL,
  KEY idx_resp_form (form_id),
  CONSTRAINT fk_resp_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS form_answers (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  response_id INT NOT NULL,
  field_id    INT NOT NULL,
  value       TEXT NULL,
  KEY idx_ans_resp (response_id),
  CONSTRAINT fk_ans_resp  FOREIGN KEY (response_id) REFERENCES form_responses(id) ON DELETE CASCADE,
  CONSTRAINT fk_ans_field FOREIGN KEY (field_id)    REFERENCES form_fields(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
