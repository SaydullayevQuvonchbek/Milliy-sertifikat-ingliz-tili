-- Multilevel Mock platformasi — MySQL 5.7+/MariaDB 10.3+ sxemasi.
-- Vaqtlar: *_at — unix soniya, *_ms — unix millisoniya.

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(16) NOT NULL,
    full_name VARCHAR(190) NOT NULL,
    login VARCHAR(100) NOT NULL,
    phone VARCHAR(32) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    created_at INT UNSIGNED NOT NULL,
    last_login_at INT UNSIGNED NULL,
    UNIQUE KEY uq_users_login (login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mocks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'draft',
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 2,
    source_json MEDIUMTEXT NOT NULL,
    content_json MEDIUMTEXT NOT NULL,
    key_json MEDIUMTEXT NOT NULL,
    settings_json TEXT NOT NULL,
    stats_json MEDIUMTEXT NOT NULL,
    available_from INT UNSIGNED NULL,
    available_to INT UNSIGNED NULL,
    results_published_at INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at INT UNSIGNED NOT NULL,
    updated_at INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    mock_id INT UNSIGNED NOT NULL,
    kind VARCHAR(16) NOT NULL,
    file VARCHAR(190) NOT NULL,
    original_name VARCHAR(255) NOT NULL DEFAULT '',
    mime VARCHAR(100) NOT NULL,
    size INT UNSIGNED NOT NULL DEFAULT 0,
    duration DOUBLE NULL,
    created_at INT UNSIGNED NOT NULL,
    KEY idx_assets_mock (mock_id),
    CONSTRAINT fk_assets_mock FOREIGN KEY (mock_id) REFERENCES mocks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    mock_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    attempt_no TINYINT UNSIGNED NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'in_progress',
    sections VARCHAR(16) NOT NULL,
    stage VARCHAR(8) NOT NULL,
    stage_state VARCHAR(16) NOT NULL DEFAULT 'pending',
    stage_since_ms BIGINT NOT NULL,
    section_started_ms BIGINT NULL,
    section_deadline_ms BIGINT NULL,
    answers_json MEDIUMTEXT NOT NULL,
    writing_json MEDIUMTEXT NOT NULL,
    meta_json MEDIUMTEXT NOT NULL,
    save_seq INT UNSIGNED NOT NULL DEFAULT 0,
    client_id VARCHAR(64) NULL,
    last_seen_ms BIGINT NULL,
    violations INT UNSIGNED NOT NULL DEFAULT 0,
    anon_code VARCHAR(16) NOT NULL,
    started_at INT UNSIGNED NOT NULL,
    finished_at INT UNSIGNED NULL,
    ip VARCHAR(64) NULL,
    user_agent VARCHAR(300) NULL,
    l_raw SMALLINT NULL,
    r_raw SMALLINT NULL,
    l_theta DOUBLE NULL,
    r_theta DOUBLE NULL,
    l_score DOUBLE NULL,
    r_score DOUBLE NULL,
    w_raw DOUBLE NULL,
    w_score DOUBLE NULL,
    s_raw DOUBLE NULL,
    s_score DOUBLE NULL,
    overall DOUBLE NULL,
    level VARCHAR(16) NULL,
    score_method VARCHAR(16) NULL,
    grade_w VARCHAR(8) NULL,
    grade_s VARCHAR(8) NULL,
    UNIQUE KEY uq_attempt_no (mock_id, user_id, attempt_no),
    UNIQUE KEY uq_attempt_anon (anon_code),
    KEY idx_attempts_user (user_id, status),
    KEY idx_attempts_mock (mock_id, status),
    KEY idx_attempts_grade_w (grade_w),
    KEY idx_attempts_grade_s (grade_s),
    CONSTRAINT fk_attempts_mock FOREIGN KEY (mock_id) REFERENCES mocks (id) ON DELETE CASCADE,
    CONSTRAINT fk_attempts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attempt_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    type VARCHAR(40) NOT NULL,
    section VARCHAR(4) NULL,
    detail VARCHAR(500) NULL,
    is_violation TINYINT NOT NULL DEFAULT 0,
    created_ms BIGINT NOT NULL,
    client_ms BIGINT NULL,
    event_key VARCHAR(40) NULL,
    KEY idx_events_attempt (attempt_id),
    UNIQUE KEY uq_events_key (attempt_id, event_key),
    CONSTRAINT fk_events_attempt FOREIGN KEY (attempt_id) REFERENCES attempts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS speaking_answers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    q_no TINYINT UNSIGNED NOT NULL,
    file VARCHAR(190) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    size INT UNSIGNED NOT NULL,
    duration DOUBLE NULL,
    created_at INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_speaking (attempt_id, q_no),
    CONSTRAINT fk_speaking_attempt FOREIGN KEY (attempt_id) REFERENCES attempts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ratings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    skill CHAR(1) NOT NULL,
    expert_id INT UNSIGNED NOT NULL,
    round TINYINT UNSIGNED NOT NULL,
    scores_json TEXT NOT NULL,
    flags_json TEXT NOT NULL,
    raw_total DOUBLE NOT NULL,
    comment TEXT NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_rating (attempt_id, skill, expert_id),
    CONSTRAINT fk_ratings_attempt FOREIGN KEY (attempt_id) REFERENCES attempts (id) ON DELETE CASCADE,
    CONSTRAINT fk_ratings_expert FOREIGN KEY (expert_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS grading_claims (
    attempt_id INT UNSIGNED NOT NULL,
    skill CHAR(1) NOT NULL,
    expert_id INT UNSIGNED NOT NULL,
    expires_ms BIGINT NOT NULL,
    PRIMARY KEY (attempt_id, skill, expert_id),
    CONSTRAINT fk_claims_attempt FOREIGN KEY (attempt_id) REFERENCES attempts (id) ON DELETE CASCADE,
    CONSTRAINT fk_claims_expert FOREIGN KEY (expert_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(64) NOT NULL PRIMARY KEY,
    value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_throttle (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    throttle_key VARCHAR(190) NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    KEY idx_throttle_key (throttle_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(64) NOT NULL,
    target VARCHAR(190) NOT NULL DEFAULT '',
    detail TEXT NOT NULL,
    created_at INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
