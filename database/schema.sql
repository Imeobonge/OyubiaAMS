-- ============================================================
-- OyubiaCYF — Oyubia Christian Youth Forum Attendance Mgmt System
-- MySQL schema (works on cPanel shared hosting / MySQL 5.7+/8)
-- ============================================================
SET NAMES utf8mb4;
SET time_zone = '+01:00'; -- West Africa Time

-- ----- Yearly editions (one per December event) -----
CREATE TABLE IF NOT EXISTS editions (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,           -- e.g. "OYCF 2026"
  year        INT NOT NULL,
  start_date  DATE NULL,
  end_date    DATE NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 0,
  self_register_open TINYINT(1) NOT NULL DEFAULT 0,  -- public QR self check-in on/off
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_year (year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- Staff / admin logins -----
CREATE TABLE IF NOT EXISTS users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(160) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- Congregations (master list, carries across years) -----
CREATE TABLE IF NOT EXISTS congregations (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(160) NOT NULL,
  code           VARCHAR(16) NOT NULL,          -- e.g. UYO (used in reg number)
  minister_name  VARCHAR(120) NULL,
  minister_phone VARCHAR(40) NULL,
  address        VARCHAR(255) NULL,
  home_state     VARCHAR(80) NULL,
  home_city      VARCHAR(80) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- Attendees (people; carry across years) -----
CREATE TABLE IF NOT EXISTS attendees (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  full_name   VARCHAR(160) NOT NULL,
  gender      ENUM('male','female') NULL,
  is_member   TINYINT(1) NOT NULL DEFAULT 1,    -- 1 = Church of Christ member
  phone       VARCHAR(40) NULL,
  email       VARCHAR(160) NULL,
  birth_day   TINYINT NULL,                     -- 1-31 (no year, by design)
  birth_month TINYINT NULL,                     -- 1-12
  home_state  VARCHAR(80) NULL,
  home_city   VARCHAR(80) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_name (full_name),
  KEY idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- Registrations (one per attendee per edition) -----
CREATE TABLE IF NOT EXISTS registrations (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  edition_id         INT NOT NULL,
  attendee_id        INT NOT NULL,
  congregation_id    INT NULL,                  -- NULL for visitors
  category           ENUM('group','solo','visitor') NOT NULL,
  reg_number         VARCHAR(40) NULL,          -- assigned on server
  reg_seq            INT NULL,
  accommodation      ENUM('camping','outside') NULL,
  accommodation_note VARCHAR(255) NULL,
  registered_by      INT NULL,
  batch_id           CHAR(36) NULL,             -- groups all members of one QR group scan
  client_uuid        VARCHAR(64) NULL,          -- offline dedupe key
  registered_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_reg_number (reg_number),
  UNIQUE KEY uniq_client_uuid (client_uuid),
  UNIQUE KEY uniq_edition_attendee (edition_id, attendee_id),
  KEY idx_edition (edition_id),
  KEY idx_congregation (congregation_id),
  KEY idx_batch (batch_id),
  CONSTRAINT fk_reg_edition      FOREIGN KEY (edition_id)      REFERENCES editions(id)      ON DELETE CASCADE,
  CONSTRAINT fk_reg_attendee     FOREIGN KEY (attendee_id)     REFERENCES attendees(id)     ON DELETE CASCADE,
  CONSTRAINT fk_reg_congregation FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE SET NULL,
  CONSTRAINT fk_reg_user         FOREIGN KEY (registered_by)   REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- Visitor-specific details (category = visitor) -----
CREATE TABLE IF NOT EXISTS visitor_details (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  registration_id INT NOT NULL,
  church_attended VARCHAR(160) NULL,
  invited_by      VARCHAR(160) NULL,
  how_heard       VARCHAR(255) NULL,
  expectations    TEXT NULL,
  UNIQUE KEY uniq_registration (registration_id),
  CONSTRAINT fk_vis_reg FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- Congregation accommodation handout tracking (per edition) -----
CREATE TABLE IF NOT EXISTS congregation_accommodation (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  edition_id       INT NOT NULL,
  congregation_id  INT NOT NULL,
  brothers_done    TINYINT(1) NOT NULL DEFAULT 0,
  sisters_done     TINYINT(1) NOT NULL DEFAULT 0,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_ca (edition_id, congregation_id),
  CONSTRAINT fk_ca_edition FOREIGN KEY (edition_id)      REFERENCES editions(id)      ON DELETE CASCADE,
  CONSTRAINT fk_ca_cong    FOREIGN KEY (congregation_id) REFERENCES congregations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- Reg-number counters (atomic sequence per edition+congregation) -----
CREATE TABLE IF NOT EXISTS reg_counters (
  edition_id       INT NOT NULL,
  congregation_key VARCHAR(16) NOT NULL,        -- congregation code, or 'VIS'
  last_seq         INT NOT NULL DEFAULT 0,
  PRIMARY KEY (edition_id, congregation_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Custom forms (Google-Forms-style builder + public responses)
-- ============================================================

-- ----- A form: title, description, public slug, status -----
CREATE TABLE IF NOT EXISTS forms (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(200) NOT NULL,
  description TEXT NULL,
  slug        VARCHAR(32) NOT NULL,             -- unguessable public URL token
  status      ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
  created_by  INT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_form_slug (slug),
  CONSTRAINT fk_form_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- Questions belonging to a form -----
CREATE TABLE IF NOT EXISTS form_fields (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  form_id     INT NOT NULL,
  label       VARCHAR(255) NOT NULL,
  help_text   VARCHAR(255) NULL,
  type        ENUM('short_text','paragraph','multiple_choice','checkboxes','dropdown','email','phone','number','date','file_upload')
                NOT NULL DEFAULT 'short_text',
  options     TEXT NULL,                        -- JSON array of choices (choice types)
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  position    INT NOT NULL DEFAULT 0,
  KEY idx_field_form (form_id),
  CONSTRAINT fk_field_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- One submission of a form -----
CREATE TABLE IF NOT EXISTS form_responses (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  form_id       INT NOT NULL,
  submitted_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  respondent_ip VARCHAR(45) NULL,
  KEY idx_resp_form (form_id),
  CONSTRAINT fk_resp_form FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----- One answer (per field) within a response -----
CREATE TABLE IF NOT EXISTS form_answers (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  response_id INT NOT NULL,
  field_id    INT NOT NULL,
  value       TEXT NULL,                        -- checkboxes stored as JSON array
  KEY idx_ans_resp (response_id),
  CONSTRAINT fk_ans_resp  FOREIGN KEY (response_id) REFERENCES form_responses(id) ON DELETE CASCADE,
  CONSTRAINT fk_ans_field FOREIGN KEY (field_id)    REFERENCES form_fields(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
