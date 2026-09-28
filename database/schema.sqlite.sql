-- Multilevel Mock platformasi — SQLite sxemasi.
-- Vaqtlar: *_at — unix soniya, *_ms — unix millisoniya.

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role TEXT NOT NULL CHECK (role IN ('admin', 'expert', 'student')),
    full_name TEXT NOT NULL,
    login TEXT NOT NULL UNIQUE,
    phone TEXT,
    password_hash TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'blocked')),
    created_at INTEGER NOT NULL,
    last_login_at INTEGER
);

CREATE TABLE IF NOT EXISTS mocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'active', 'frozen', 'archived')),
    max_attempts INTEGER NOT NULL DEFAULT 2,
    source_json TEXT NOT NULL,
    content_json TEXT NOT NULL,
    key_json TEXT NOT NULL,
    settings_json TEXT NOT NULL,
    stats_json TEXT NOT NULL DEFAULT '{}',
    available_from INTEGER,
    available_to INTEGER,
    results_published_at INTEGER,
    created_by INTEGER,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mock_id INTEGER NOT NULL REFERENCES mocks(id) ON DELETE CASCADE,
    kind TEXT NOT NULL CHECK (kind IN ('audio', 'image')),
    file TEXT NOT NULL,
    original_name TEXT NOT NULL DEFAULT '',
    mime TEXT NOT NULL,
    size INTEGER NOT NULL DEFAULT 0,
    duration REAL,
    created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_assets_mock ON assets (mock_id);

CREATE TABLE IF NOT EXISTS attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mock_id INTEGER NOT NULL REFERENCES mocks(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    attempt_no INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'in_progress' CHECK (status IN ('in_progress', 'completed', 'terminated')),
    sections TEXT NOT NULL,
    stage TEXT NOT NULL,
    stage_state TEXT NOT NULL DEFAULT 'pending' CHECK (stage_state IN ('pending', 'active')),
    stage_since_ms INTEGER NOT NULL,
    section_started_ms INTEGER,
    section_deadline_ms INTEGER,
    answers_json TEXT NOT NULL DEFAULT '{}',
    writing_json TEXT NOT NULL DEFAULT '{}',
    meta_json TEXT NOT NULL DEFAULT '{}',
    save_seq INTEGER NOT NULL DEFAULT 0,
    client_id TEXT,
    last_seen_ms INTEGER,
    violations INTEGER NOT NULL DEFAULT 0,
    anon_code TEXT NOT NULL,
    started_at INTEGER NOT NULL,
    finished_at INTEGER,
    ip TEXT,
    user_agent TEXT,
    l_raw INTEGER,
    r_raw INTEGER,
    l_theta REAL,
    r_theta REAL,
    l_score REAL,
    r_score REAL,
    w_raw REAL,
    w_score REAL,
    s_raw REAL,
    s_score REAL,
    overall REAL,
    level TEXT,
    score_method TEXT,
    grade_w TEXT,
    grade_s TEXT,
    UNIQUE (mock_id, user_id, attempt_no)
);
CREATE INDEX IF NOT EXISTS idx_attempts_user ON attempts (user_id, status);
CREATE INDEX IF NOT EXISTS idx_attempts_mock ON attempts (mock_id, status);
CREATE UNIQUE INDEX IF NOT EXISTS idx_attempts_anon ON attempts (anon_code);
CREATE INDEX IF NOT EXISTS idx_attempts_grade_w ON attempts (grade_w);
CREATE INDEX IF NOT EXISTS idx_attempts_grade_s ON attempts (grade_s);

CREATE TABLE IF NOT EXISTS attempt_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    attempt_id INTEGER NOT NULL REFERENCES attempts(id) ON DELETE CASCADE,
    type TEXT NOT NULL,
    section TEXT,
    detail TEXT,
    is_violation INTEGER NOT NULL DEFAULT 0,
    created_ms INTEGER NOT NULL,
    client_ms INTEGER
);
CREATE INDEX IF NOT EXISTS idx_events_attempt ON attempt_events (attempt_id);

CREATE TABLE IF NOT EXISTS speaking_answers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    attempt_id INTEGER NOT NULL REFERENCES attempts(id) ON DELETE CASCADE,
    q_no INTEGER NOT NULL,
    file TEXT NOT NULL,
    mime TEXT NOT NULL,
    size INTEGER NOT NULL,
    duration REAL,
    created_at INTEGER NOT NULL,
    UNIQUE (attempt_id, q_no)
);

CREATE TABLE IF NOT EXISTS ratings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    attempt_id INTEGER NOT NULL REFERENCES attempts(id) ON DELETE CASCADE,
    skill TEXT NOT NULL CHECK (skill IN ('W', 'S')),
    expert_id INTEGER NOT NULL REFERENCES users(id),
    round INTEGER NOT NULL,
    scores_json TEXT NOT NULL,
    flags_json TEXT NOT NULL DEFAULT '{}',
    raw_total REAL NOT NULL,
    comment TEXT NOT NULL DEFAULT '',
    created_at INTEGER NOT NULL,
    UNIQUE (attempt_id, skill, expert_id)
);

CREATE TABLE IF NOT EXISTS grading_claims (
    attempt_id INTEGER NOT NULL REFERENCES attempts(id) ON DELETE CASCADE,
    skill TEXT NOT NULL,
    expert_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    expires_ms INTEGER NOT NULL,
    PRIMARY KEY (attempt_id, skill, expert_id)
);

CREATE TABLE IF NOT EXISTS settings (
    name TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS login_throttle (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    throttle_key TEXT NOT NULL,
    created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_throttle_key ON login_throttle (throttle_key, created_at);

CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    target TEXT NOT NULL DEFAULT '',
    detail TEXT NOT NULL DEFAULT '',
    created_at INTEGER NOT NULL
);
