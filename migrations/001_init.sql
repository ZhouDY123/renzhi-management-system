PRAGMA journal_mode=WAL;
PRAGMA foreign_keys=ON;

CREATE TABLE IF NOT EXISTS user (
 id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL,
 real_name TEXT NOT NULL, mobile TEXT, role TEXT NOT NULL CHECK(role IN ('admin','leader','hr','interviewer')),
 status INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS post (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, company TEXT, series TEXT, status TEXT NOT NULL DEFAULT 'recruiting',
 duty TEXT, q_apply_token TEXT UNIQUE, created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS candidate_pre_register (
 id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, name TEXT NOT NULL, mobile TEXT NOT NULL UNIQUE,
 status TEXT NOT NULL DEFAULT 'registered', created_by INTEGER, created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
 FOREIGN KEY(post_id) REFERENCES post(id), FOREIGN KEY(created_by) REFERENCES user(id)
);
CREATE TABLE IF NOT EXISTS candidate (
 id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, mobile TEXT NOT NULL UNIQUE, gender TEXT, birth_date TEXT,
 edu TEXT, school_tier TEXT, major TEXT, title TEXT, politics TEXT, health TEXT, work_years INTEGER DEFAULT 0, prof_years INTEGER DEFAULT 0,
 group_co_years INTEGER DEFAULT 0, group_co_mgmt INTEGER DEFAULT 0, listed_co_years INTEGER DEFAULT 0, listed_co_mgmt INTEGER DEFAULT 0,
 private_co_years INTEGER DEFAULT 0, private_co_mgmt INTEGER DEFAULT 0, work_bg TEXT, computer_skill TEXT, language TEXT,
 created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS answer (
 id INTEGER PRIMARY KEY AUTOINCREMENT, candidate_id INTEGER NOT NULL UNIQUE, post_id INTEGER NOT NULL,
 candidate_snapshot TEXT NOT NULL, post_snapshot TEXT NOT NULL DEFAULT '{}', question_set_id INTEGER NOT NULL DEFAULT 0,
 idempotency_key TEXT NOT NULL UNIQUE, submit_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
 FOREIGN KEY(candidate_id) REFERENCES candidate(id), FOREIGN KEY(post_id) REFERENCES post(id)
);
CREATE TABLE IF NOT EXISTS result (
 id INTEGER PRIMARY KEY AUTOINCREMENT, answer_id INTEGER NOT NULL UNIQUE, eval_score REAL NOT NULL DEFAULT 0,
 suzhi_score REAL NOT NULL DEFAULT 0, postq_score REAL NOT NULL DEFAULT 0, total_score REAL NOT NULL DEFAULT 0,
 standard_version INTEGER NOT NULL DEFAULT 1, weight_snapshot TEXT NOT NULL DEFAULT '{"basic_conditions":50,"basic_quality":25,"professional":25}',
 score_status TEXT NOT NULL DEFAULT 'completed', review_status TEXT NOT NULL DEFAULT 'pending',
 FOREIGN KEY(answer_id) REFERENCES answer(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS review (
 id INTEGER PRIMARY KEY AUTOINCREMENT, result_id INTEGER NOT NULL, user_id INTEGER NOT NULL, opinion TEXT NOT NULL,
 note TEXT, stage TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
 FOREIGN KEY(result_id) REFERENCES result(id), FOREIGN KEY(user_id) REFERENCES user(id)
);
CREATE TABLE IF NOT EXISTS interview_session (
 id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, interview_date TEXT NOT NULL, time_range TEXT,
 location TEXT, status TEXT NOT NULL DEFAULT 'pending', qr_token TEXT NOT NULL UNIQUE, created_by INTEGER,
 created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')), FOREIGN KEY(post_id) REFERENCES post(id),
 UNIQUE(post_id,interview_date)
);
CREATE TABLE IF NOT EXISTS interview_candidate (
 id INTEGER PRIMARY KEY AUTOINCREMENT, session_id INTEGER NOT NULL, candidate_id INTEGER NOT NULL, answer_id INTEGER NOT NULL,
 seq INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT 'pending', FOREIGN KEY(session_id) REFERENCES interview_session(id) ON DELETE CASCADE,
 FOREIGN KEY(candidate_id) REFERENCES candidate(id), FOREIGN KEY(answer_id) REFERENCES answer(id), UNIQUE(session_id,candidate_id)
);
CREATE TABLE IF NOT EXISTS session_interviewer (
 id INTEGER PRIMARY KEY AUTOINCREMENT, session_id INTEGER NOT NULL, user_id INTEGER NOT NULL, weight REAL NOT NULL DEFAULT 1,
 is_lead INTEGER NOT NULL DEFAULT 0, confirmed INTEGER NOT NULL DEFAULT 0,
 FOREIGN KEY(session_id) REFERENCES interview_session(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES user(id), UNIQUE(session_id,user_id)
);
CREATE TABLE IF NOT EXISTS interview_score (
 id INTEGER PRIMARY KEY AUTOINCREMENT, session_id INTEGER NOT NULL, interviewer_id INTEGER NOT NULL,
 interview_candidate_id INTEGER NOT NULL, suzhi_score REAL NOT NULL, postq_score REAL NOT NULL, total_score REAL,
 detail_json TEXT, strengths TEXT, risks TEXT, development TEXT, recommendation TEXT, salary_range TEXT, available_date TEXT, comment TEXT,
 submitted_at TEXT NOT NULL DEFAULT (datetime('now','localtime')), FOREIGN KEY(session_id) REFERENCES interview_session(id),
 FOREIGN KEY(interviewer_id) REFERENCES session_interviewer(id), FOREIGN KEY(interview_candidate_id) REFERENCES interview_candidate(id),
 UNIQUE(interviewer_id,interview_candidate_id)
);
CREATE TABLE IF NOT EXISTS op_log (
 id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT NOT NULL, target TEXT, detail TEXT, ip TEXT,
 created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS scoring_standard (
 id INTEGER PRIMARY KEY AUTOINCREMENT, category TEXT NOT NULL, dim_code TEXT NOT NULL, dim_name TEXT NOT NULL,
 tier_label TEXT NOT NULL, match_type TEXT NOT NULL DEFAULT 'eq', match_rule TEXT NOT NULL DEFAULT '{}',
 tier_value REAL NOT NULL DEFAULT 0, version INTEGER NOT NULL DEFAULT 1, status TEXT NOT NULL DEFAULT 'draft', sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_standard_dim_version ON scoring_standard(dim_code,version);
CREATE TABLE IF NOT EXISTS suzhi_item (
 id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, dim_code TEXT NOT NULL, dim_name TEXT NOT NULL,
 point_desc TEXT, weight REAL NOT NULL DEFAULT 0, sort INTEGER NOT NULL DEFAULT 0,
 FOREIGN KEY(post_id) REFERENCES post(id) ON DELETE CASCADE, UNIQUE(post_id,dim_code)
);
CREATE TABLE IF NOT EXISTS question_base (
 id INTEGER PRIMARY KEY AUTOINCREMENT, group_type TEXT NOT NULL, dim_code TEXT, q_type TEXT NOT NULL,
 stem TEXT NOT NULL, options TEXT, answer TEXT, score REAL NOT NULL DEFAULT 0, status INTEGER NOT NULL DEFAULT 1,
 sort INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS question_post (
 id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, q_type TEXT NOT NULL, stem TEXT NOT NULL,
 options TEXT, answer TEXT, point_desc TEXT, score REAL NOT NULL DEFAULT 0, status INTEGER NOT NULL DEFAULT 1,
 sort INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
 FOREIGN KEY(post_id) REFERENCES post(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS question_set (
 id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, version INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'draft',
 published_at TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
 FOREIGN KEY(post_id) REFERENCES post(id) ON DELETE CASCADE, UNIQUE(post_id,version)
);
CREATE TABLE IF NOT EXISTS question_set_item (
 id INTEGER PRIMARY KEY AUTOINCREMENT, question_set_id INTEGER NOT NULL, question_type TEXT NOT NULL, question_id INTEGER NOT NULL,
 sort INTEGER NOT NULL DEFAULT 0, q_type TEXT, stem_snapshot TEXT, options_snapshot TEXT, answer_snapshot TEXT, score_snapshot REAL, FOREIGN KEY(question_set_id) REFERENCES question_set(id) ON DELETE CASCADE,
 UNIQUE(question_set_id,question_type,question_id)
);
CREATE TABLE IF NOT EXISTS answer_detail (
 id INTEGER PRIMARY KEY AUTOINCREMENT, answer_id INTEGER NOT NULL, question_type TEXT NOT NULL, question_id INTEGER NOT NULL,
 answer_val TEXT, score REAL NOT NULL DEFAULT 0, max_score REAL NOT NULL DEFAULT 0,
 score_status TEXT NOT NULL DEFAULT 'auto', stem_snapshot TEXT NOT NULL, options_snapshot TEXT, answer_snapshot TEXT,
 FOREIGN KEY(answer_id) REFERENCES answer(id) ON DELETE CASCADE,
 UNIQUE(answer_id,question_type,question_id)
);
CREATE TABLE IF NOT EXISTS eval_score_detail (
 id INTEGER PRIMARY KEY AUTOINCREMENT, answer_id INTEGER NOT NULL, dim_code TEXT NOT NULL, dim_name TEXT NOT NULL,
 matched_tier TEXT, score REAL NOT NULL DEFAULT 0, FOREIGN KEY(answer_id) REFERENCES answer(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS answer_manual_score (
 id INTEGER PRIMARY KEY AUTOINCREMENT, answer_detail_id INTEGER NOT NULL, user_id INTEGER NOT NULL, score REAL NOT NULL,
 note TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
 FOREIGN KEY(answer_detail_id) REFERENCES answer_detail(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES user(id)
);
CREATE TABLE IF NOT EXISTS app_setting (
 key TEXT PRIMARY KEY, value TEXT, label TEXT, updated_by INTEGER,
 updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE TABLE IF NOT EXISTS rate_limit (
 limit_key TEXT PRIMARY KEY, attempts INTEGER NOT NULL DEFAULT 0, window_start INTEGER NOT NULL, blocked_until INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS interview_score_draft (
 id INTEGER PRIMARY KEY AUTOINCREMENT, interviewer_id INTEGER NOT NULL, interview_candidate_id INTEGER NOT NULL,
 suzhi_score REAL, postq_score REAL, total_score REAL, detail_json TEXT, strengths TEXT, risks TEXT, development TEXT,
 recommendation TEXT, salary_range TEXT, available_date TEXT, comment TEXT, updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
 UNIQUE(interviewer_id,interview_candidate_id)
);
