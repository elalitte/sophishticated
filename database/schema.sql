-- ============================================================
-- Sophishticated - Database Schema
-- MariaDB / MySQL - InnoDB, utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS sophishticated
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sophishticated;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(255) NOT NULL UNIQUE,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('admin', 'manager', 'viewer') NOT NULL DEFAULT 'viewer',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at   DATETIME NULL DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: recipients
-- ============================================================
CREATE TABLE IF NOT EXISTS recipients (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    display_name    VARCHAR(255) NULL,
    first_name      VARCHAR(255) NULL,
    last_name       VARCHAR(255) NULL,
    job_title       VARCHAR(255) NULL,
    department      VARCHAR(255) NULL,
    graph_id        VARCHAR(255) UNIQUE NULL DEFAULT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    synced_from_graph TINYINT(1) NOT NULL DEFAULT 1,
    last_synced_at  DATETIME NULL DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: groups
-- ============================================================
CREATE TABLE IF NOT EXISTS `groups` (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL UNIQUE,
    description     TEXT NULL,
    type            ENUM('team', 'category', 'custom') NOT NULL DEFAULT 'custom',
    color           VARCHAR(7) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: group_recipient (pivot)
-- ============================================================
CREATE TABLE IF NOT EXISTS group_recipient (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_id    BIGINT UNSIGNED NOT NULL,
    group_id        BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_recipient_group (recipient_id, group_id),
    CONSTRAINT fk_gr_recipient FOREIGN KEY (recipient_id) REFERENCES recipients(id) ON DELETE CASCADE,
    CONSTRAINT fk_gr_group     FOREIGN KEY (group_id)     REFERENCES `groups`(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: landing_pages  (created BEFORE email_templates for FK)
-- ============================================================
CREATE TABLE IF NOT EXISTS landing_pages (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    html_content    LONGTEXT NULL,
    capture_fields  JSON NULL,
    redirect_url    VARCHAR(2048) NULL,
    awareness_html  LONGTEXT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lp_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: email_templates
-- ============================================================
CREATE TABLE IF NOT EXISTS email_templates (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    subject         VARCHAR(255) NOT NULL,
    sender_name     VARCHAR(255) NULL,
    sender_email    VARCHAR(255) NULL,
    html_body       LONGTEXT NULL,
    landing_page_id BIGINT UNSIGNED NULL,
    difficulty_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    tags            JSON NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_et_landing_page FOREIGN KEY (landing_page_id) REFERENCES landing_pages(id) ON DELETE SET NULL,
    CONSTRAINT fk_et_created_by   FOREIGN KEY (created_by)      REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: campaigns
-- ============================================================
CREATE TABLE IF NOT EXISTS campaigns (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    status          ENUM('draft', 'scheduled', 'running', 'paused', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    email_template_id BIGINT UNSIGNED NOT NULL,
    scheduled_at    DATETIME NULL DEFAULT NULL,
    started_at      DATETIME NULL DEFAULT NULL,
    completed_at    DATETIME NULL DEFAULT NULL,
    send_mode       ENUM('immediate', 'staggered') NOT NULL DEFAULT 'immediate',
    stagger_minutes SMALLINT UNSIGNED NULL DEFAULT NULL,
    created_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_camp_template   FOREIGN KEY (email_template_id) REFERENCES email_templates(id) ON DELETE RESTRICT,
    CONSTRAINT fk_camp_created_by FOREIGN KEY (created_by)        REFERENCES users(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: campaign_recipients
-- ============================================================
CREATE TABLE IF NOT EXISTS campaign_recipients (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_id           BIGINT UNSIGNED NOT NULL,
    recipient_id          BIGINT UNSIGNED NOT NULL,
    unique_token          CHAR(36) NOT NULL UNIQUE,
    mail_status           ENUM('pending', 'delivered', 'failed') NOT NULL DEFAULT 'pending',
    delivered_at          DATETIME NULL DEFAULT NULL,
    opened                TINYINT(1) NOT NULL DEFAULT 0,
    opened_at             DATETIME NULL DEFAULT NULL,
    open_count            INT UNSIGNED NOT NULL DEFAULT 0,
    clicked               TINYINT(1) NOT NULL DEFAULT 0,
    clicked_at            DATETIME NULL DEFAULT NULL,
    click_count           INT UNSIGNED NOT NULL DEFAULT 0,
    submitted_credentials TINYINT(1) NOT NULL DEFAULT 0,
    submitted_at          DATETIME NULL DEFAULT NULL,
    submission_count      INT UNSIGNED NOT NULL DEFAULT 0,
    submitted_data        JSON NULL,
    user_agent            TEXT NULL,
    ip_address            VARCHAR(45) NULL,
    UNIQUE KEY uq_campaign_recipient (campaign_id, recipient_id),
    CONSTRAINT fk_cr_campaign  FOREIGN KEY (campaign_id)  REFERENCES campaigns(id)  ON DELETE CASCADE,
    CONSTRAINT fk_cr_recipient FOREIGN KEY (recipient_id) REFERENCES recipients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: tracking_events
-- ============================================================
CREATE TABLE IF NOT EXISTS tracking_events (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campaign_recipient_id BIGINT UNSIGNED NOT NULL,
    event_type            ENUM('delivered', 'opened', 'clicked', 'submitted', 'page_visited') NOT NULL,
    event_data            JSON NULL,
    ip_address            VARCHAR(45) NULL,
    user_agent            TEXT NULL,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_te_event_type  (event_type),
    INDEX idx_te_created_at  (created_at),
    CONSTRAINT fk_te_campaign_recipient FOREIGN KEY (campaign_recipient_id) REFERENCES campaign_recipients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: audit_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NULL,
    action          VARCHAR(255) NOT NULL,
    target_type     VARCHAR(255) NULL,
    target_id       BIGINT UNSIGNED NULL,
    details         JSON NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_al_action     (action),
    INDEX idx_al_created_at (created_at),
    CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: job_queue
-- ============================================================
CREATE TABLE IF NOT EXISTS job_queue (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_class       VARCHAR(255) NOT NULL,
    payload         JSON NULL,
    status          ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    priority        TINYINT UNSIGNED NOT NULL DEFAULT 5,
    attempts        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts    TINYINT UNSIGNED NOT NULL DEFAULT 3,
    available_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at      DATETIME NULL DEFAULT NULL,
    completed_at    DATETIME NULL DEFAULT NULL,
    error_message   TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_jq_status_available (status, available_at),
    INDEX idx_jq_priority         (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
