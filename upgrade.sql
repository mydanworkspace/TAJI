-- TAJI upgrade: brings an older install up to schema version 2. Safe to run more than once.
-- Needs MariaDB 10.3+ (most shared hosts). On MySQL 8, remove "IF NOT EXISTS" from the
-- ALTER lines and skip any column that already exists.
-- Back up your database before running this.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS schema_version (
  version INT PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- v2: extra social links, photo and curator note on artists
ALTER TABLE artists ADD COLUMN IF NOT EXISTS photo_url VARCHAR(255) NULL;
ALTER TABLE artists ADD COLUMN IF NOT EXISTS curator_note VARCHAR(200) NULL;
ALTER TABLE artists ADD COLUMN IF NOT EXISTS yt_url VARCHAR(255) NULL;
ALTER TABLE artists ADD COLUMN IF NOT EXISTS ig_url VARCHAR(255) NULL;
ALTER TABLE artists ADD COLUMN IF NOT EXISTS tiktok_url VARCHAR(255) NULL;
ALTER TABLE artists ADD COLUMN IF NOT EXISTS x_url VARCHAR(255) NULL;
ALTER TABLE artists ADD COLUMN IF NOT EXISTS fb_url VARCHAR(255) NULL;

-- v2: email-code login and vote rate limiting
CREATE TABLE IF NOT EXISTS otp_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  used TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vote_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO schema_version (version) VALUES (2);
