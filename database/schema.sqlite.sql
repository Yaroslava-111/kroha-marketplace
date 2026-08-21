PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    name          TEXT NOT NULL,
    email         TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    city          TEXT NOT NULL,
    rating        REAL NOT NULL DEFAULT 5.0,
    sold_count    INTEGER NOT NULL DEFAULT 0,
    verified      INTEGER NOT NULL DEFAULT 0,
    is_admin      INTEGER NOT NULL DEFAULT 0,
    is_moderator  INTEGER NOT NULL DEFAULT 0,
    is_banned     INTEGER NOT NULL DEFAULT 0,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS items (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id          INTEGER NOT NULL REFERENCES users(id),
    title            TEXT NOT NULL,
    category         TEXT NOT NULL,
    age_min          INTEGER,
    age_max          INTEGER,
    size             TEXT,
    season           TEXT,
    condition_label  TEXT,
    price            INTEGER NOT NULL DEFAULT 0,
    city             TEXT NOT NULL,
    description      TEXT,
    photos           TEXT,
    is_giveaway      INTEGER NOT NULL DEFAULT 0,
    status           TEXT NOT NULL DEFAULT 'active',
    buyer_id         INTEGER REFERENCES users(id),
    confirmed_at     TEXT,
    search_lc        TEXT NOT NULL DEFAULT '',
    created_at       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_items_category ON items(category);

CREATE INDEX IF NOT EXISTS idx_items_status ON items(status);

CREATE TABLE IF NOT EXISTS auctions (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id          INTEGER NOT NULL REFERENCES users(id),
    title            TEXT NOT NULL,
    category         TEXT NOT NULL,
    age_min          INTEGER,
    age_max          INTEGER,
    size             TEXT,
    season           TEXT,
    condition_label  TEXT,
    description      TEXT,
    photos           TEXT,
    start_price      INTEGER NOT NULL,
    current_price    INTEGER NOT NULL,
    min_bid_step     INTEGER NOT NULL,
    duration_days    INTEGER NOT NULL DEFAULT 3,
    end_at           TEXT NOT NULL,
    bin_price        INTEGER,
    status           TEXT NOT NULL DEFAULT 'active',
    winner_bid_id    INTEGER,
    confirmed_at     TEXT,
    search_lc        TEXT NOT NULL DEFAULT '',
    created_at       TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_auctions_status ON auctions(status);

CREATE TABLE IF NOT EXISTS bids (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    auction_id   INTEGER NOT NULL REFERENCES auctions(id),
    user_id      INTEGER NOT NULL REFERENCES users(id),
    amount       INTEGER NOT NULL,
    is_proxy     INTEGER NOT NULL DEFAULT 0,
    proxy_limit  INTEGER,
    created_at   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_bids_auction ON bids(auction_id);

CREATE TABLE IF NOT EXISTS notifications (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL REFERENCES users(id),
    type         TEXT NOT NULL,
    text         TEXT NOT NULL,
    link         TEXT NOT NULL DEFAULT '',
    is_read      INTEGER NOT NULL DEFAULT 0,
    created_at   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, is_read);

CREATE TABLE IF NOT EXISTS conversations (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    buyer_id     INTEGER NOT NULL REFERENCES users(id),
    seller_id    INTEGER NOT NULL REFERENCES users(id),
    item_id      INTEGER REFERENCES items(id),
    auction_id   INTEGER REFERENCES auctions(id),
    subject      TEXT NOT NULL,
    item_url     TEXT NOT NULL DEFAULT '',
    created_at   TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at   TEXT NOT NULL DEFAULT (datetime('now')),
    archived_at  TEXT
);

CREATE INDEX IF NOT EXISTS idx_conversations_buyer ON conversations(buyer_id, updated_at);

CREATE INDEX IF NOT EXISTS idx_conversations_seller ON conversations(seller_id, updated_at);

CREATE TABLE IF NOT EXISTS messages (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL REFERENCES conversations(id),
    sender_id       INTEGER NOT NULL REFERENCES users(id),
    text            TEXT NOT NULL,
    is_read         INTEGER NOT NULL DEFAULT 0,
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages(conversation_id, created_at);

CREATE TABLE IF NOT EXISTS favorites (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id),
    item_id    INTEGER REFERENCES items(id),
    auction_id INTEGER REFERENCES auctions(id),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_favorites_item ON favorites(user_id, item_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_favorites_auction ON favorites(user_id, auction_id);

CREATE TABLE IF NOT EXISTS reviews (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id),
    author_id  INTEGER NOT NULL REFERENCES users(id),
    item_id    INTEGER REFERENCES items(id),
    auction_id INTEGER REFERENCES auctions(id),
    rating     INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
    text       TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_reviews_item ON reviews(author_id, item_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_reviews_auction ON reviews(author_id, auction_id);

CREATE INDEX IF NOT EXISTS idx_reviews_user ON reviews(user_id);

CREATE TABLE IF NOT EXISTS reports (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id),
    item_id    INTEGER REFERENCES items(id),
    auction_id INTEGER REFERENCES auctions(id),
    reason     TEXT NOT NULL,
    comment    TEXT,
    status     TEXT NOT NULL DEFAULT 'new',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_reports_status ON reports(status);

CREATE UNIQUE INDEX IF NOT EXISTS idx_reports_item ON reports(user_id, item_id);

CREATE UNIQUE INDEX IF NOT EXISTS idx_reports_auction ON reports(user_id, auction_id);

CREATE TABLE IF NOT EXISTS password_resets (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id),
    token      TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    used_at    TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token);

CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets(user_id);

CREATE TABLE IF NOT EXISTS view_history (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id),
    item_id    INTEGER REFERENCES items(id),
    auction_id INTEGER REFERENCES auctions(id),
    viewed_at  TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_view_history_user ON view_history(user_id, viewed_at);

CREATE TABLE IF NOT EXISTS saved_searches (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL REFERENCES users(id),
    params     TEXT NOT NULL,
    label      TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_saved_searches_user ON saved_searches(user_id, params);
