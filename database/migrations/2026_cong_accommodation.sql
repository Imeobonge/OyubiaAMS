-- Track per-edition accommodation handout status per congregation (brothers and sisters separately).
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
