-- meterio by MeckByte – Datenbankschema (MySQL 5.7+/MariaDB 10.3+)
-- Import: netcup WSC -> phpMyAdmin -> Datenbank wählen -> Importieren
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  twofa_method ENUM('totp','email') NOT NULL DEFAULT 'email',
  twofa_ready TINYINT(1) NOT NULL DEFAULT 0,
  totp_secret_enc TEXT NULL,
  totp_last_step BIGINT NOT NULL DEFAULT 0,
  force_pw_change TINYINT(1) NOT NULL DEFAULT 0,
  reminder_mode ENUM('off','monthly','contract_year') NOT NULL DEFAULT 'monthly',
  reminder_day TINYINT UNSIGNED NOT NULL DEFAULT 1,
  last_reminder_at DATE NULL,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('reset','invite') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tokens_hash (token_hash),
  KEY ix_tokens_user (user_id),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS otp_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_otp_user (user_id),
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS backup_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  used_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_backup_user (user_id),
  CONSTRAINT fk_backup_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind VARCHAR(16) NOT NULL DEFAULT 'login',
  ip VARCHAR(45) NOT NULL,
  ident_hash CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_attempts_ip (kind, ip, created_at),
  KEY ix_attempts_ident (kind, ident_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sensible Felder (*_enc) werden anwendungsseitig mit AES-256-GCM verschlüsselt.
CREATE TABLE IF NOT EXISTS meters (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  name_enc TEXT NOT NULL,
  location_enc TEXT NULL,
  note_enc TEXT NULL,
  kind ENUM('analog','digital') NOT NULL DEFAULT 'digital',
  archived TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_meters_user (user_id),
  CONSTRAINT fk_meters_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  meter_id INT UNSIGNED NOT NULL,
  obis_enc TEXT NULL,
  label_enc TEXT NOT NULL,
  tariff ENUM('ET','HT','NT','EXPORT','OTHER') NOT NULL DEFAULT 'ET',
  sort_order SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_registers_meter (meter_id),
  CONSTRAINT fk_registers_meter FOREIGN KEY (meter_id) REFERENCES meters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS readings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  meter_id INT UNSIGNED NOT NULL,
  read_at DATETIME NOT NULL,
  note_enc TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_readings_meter (meter_id, read_at),
  CONSTRAINT fk_readings_meter FOREIGN KEY (meter_id) REFERENCES meters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reading_values (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reading_id INT UNSIGNED NOT NULL,
  register_id INT UNSIGNED NOT NULL,
  value_enc TEXT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reading_register (reading_id, register_id),
  KEY ix_rv_register (register_id),
  CONSTRAINT fk_rv_reading FOREIGN KEY (reading_id) REFERENCES readings (id) ON DELETE CASCADE,
  CONSTRAINT fk_rv_register FOREIGN KEY (register_id) REFERENCES registers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reading_photos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reading_id INT UNSIGNED NOT NULL,
  file_name CHAR(40) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_photos_reading (reading_id),
  CONSTRAINT fk_photos_reading FOREIGN KEY (reading_id) REFERENCES readings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- data_enc: JSON (Anbieter, Preise, Grundgebühr, Jahresverbrauch, Abschlag ...)
CREATE TABLE IF NOT EXISTS contracts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  meter_id INT UNSIGNED NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NULL,
  data_enc MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_contracts_user (user_id),
  KEY ix_contracts_meter (meter_id),
  CONSTRAINT fk_contracts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_contracts_meter FOREIGN KEY (meter_id) REFERENCES meters (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- data_enc: JSON (Betrag, Status, Art, Notiz)
CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contract_id INT UNSIGNED NOT NULL,
  paid_on DATE NOT NULL,
  data_enc TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_payments_contract (contract_id, paid_on),
  CONSTRAINT fk_payments_contract FOREIGN KEY (contract_id) REFERENCES contracts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
