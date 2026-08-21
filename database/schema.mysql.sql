CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    city          VARCHAR(100) NOT NULL,
    rating        DECIMAL(3,1) NOT NULL DEFAULT 5.0,
    sold_count    INT NOT NULL DEFAULT 0,
    verified      TINYINT(1) NOT NULL DEFAULT 0,
    is_admin      TINYINT(1) NOT NULL DEFAULT 0,
    is_moderator  TINYINT(1) NOT NULL DEFAULT 0,
    is_banned     TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
    id               INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL,
    title            VARCHAR(200) NOT NULL,
    category         VARCHAR(100) NOT NULL,
    age_min          INT,
    age_max          INT,
    size             VARCHAR(20),
    season           VARCHAR(20),
    condition_label  VARCHAR(100),
    price            INT NOT NULL DEFAULT 0,
    city             VARCHAR(100) NOT NULL,
    description      TEXT,
    photos           TEXT,
    is_giveaway      TINYINT(1) NOT NULL DEFAULT 0,
    status           VARCHAR(20) NOT NULL DEFAULT 'active',
    buyer_id         INT UNSIGNED,
    confirmed_at     DATETIME NULL,
    search_lc        VARCHAR(500) NOT NULL DEFAULT '',
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_items_category (category),
    KEY idx_items_status (status),
    CONSTRAINT fk_items_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_items_buyer FOREIGN KEY (buyer_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auctions (
    id               INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id          INT UNSIGNED NOT NULL,
    title            VARCHAR(200) NOT NULL,
    category         VARCHAR(100) NOT NULL,
    age_min          INT,
    age_max          INT,
    size             VARCHAR(20),
    season           VARCHAR(20),
    condition_label  VARCHAR(100),
    description      TEXT,
    photos           TEXT,
    start_price      INT NOT NULL,
    current_price    INT NOT NULL,
    min_bid_step     INT NOT NULL,
    duration_days    INT NOT NULL DEFAULT 3,
    end_at           DATETIME NOT NULL,
    bin_price        INT,
    status           VARCHAR(20) NOT NULL DEFAULT 'active',
    winner_bid_id    INT UNSIGNED,
    confirmed_at     DATETIME NULL,
    search_lc        VARCHAR(500) NOT NULL DEFAULT '',
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_auctions_status (status),
    CONSTRAINT fk_auctions_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bids (
    id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    auction_id   INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    amount       INT NOT NULL,
    is_proxy     TINYINT(1) NOT NULL DEFAULT 0,
    proxy_limit  INT,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_bids_auction (auction_id),
    CONSTRAINT fk_bids_auction FOREIGN KEY (auction_id) REFERENCES auctions(id),
    CONSTRAINT fk_bids_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    type         VARCHAR(30) NOT NULL,
    text         TEXT NOT NULL,
    link         VARCHAR(255) NOT NULL DEFAULT '',
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user (user_id, is_read),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS conversations (
    id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    buyer_id     INT UNSIGNED NOT NULL,
    seller_id    INT UNSIGNED NOT NULL,
    item_id      INT UNSIGNED,
    auction_id   INT UNSIGNED,
    subject      VARCHAR(200) NOT NULL,
    item_url     VARCHAR(255) NOT NULL DEFAULT '',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    archived_at  DATETIME NULL,
    KEY idx_conversations_buyer (buyer_id, updated_at),
    KEY idx_conversations_seller (seller_id, updated_at),
    CONSTRAINT fk_conversations_buyer FOREIGN KEY (buyer_id) REFERENCES users(id),
    CONSTRAINT fk_conversations_seller FOREIGN KEY (seller_id) REFERENCES users(id),
    CONSTRAINT fk_conversations_item FOREIGN KEY (item_id) REFERENCES items(id),
    CONSTRAINT fk_conversations_auction FOREIGN KEY (auction_id) REFERENCES auctions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
    id              INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id       INT UNSIGNED NOT NULL,
    text            TEXT NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_messages_conversation (conversation_id, created_at),
    CONSTRAINT fk_messages_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(id),
    CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS favorites (
    id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    item_id    INT UNSIGNED,
    auction_id INT UNSIGNED,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fav_item (user_id, item_id),
    UNIQUE KEY uq_fav_auction (user_id, auction_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_favorites_item FOREIGN KEY (item_id) REFERENCES items(id),
    CONSTRAINT fk_favorites_auction FOREIGN KEY (auction_id) REFERENCES auctions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reviews (
    id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    author_id  INT UNSIGNED NOT NULL,
    item_id    INT UNSIGNED,
    auction_id INT UNSIGNED,
    rating     TINYINT NOT NULL,
    text       TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_reviews_item (author_id, item_id),
    UNIQUE KEY uq_reviews_auction (author_id, auction_id),
    KEY idx_reviews_user (user_id),
    CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_reviews_author FOREIGN KEY (author_id) REFERENCES users(id),
    CONSTRAINT fk_reviews_item FOREIGN KEY (item_id) REFERENCES items(id),
    CONSTRAINT fk_reviews_auction FOREIGN KEY (auction_id) REFERENCES auctions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reports (
    id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    item_id    INT UNSIGNED,
    auction_id INT UNSIGNED,
    reason     VARCHAR(50) NOT NULL,
    comment    TEXT,
    status     VARCHAR(20) NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reports_status (status),
    UNIQUE KEY uq_reports_item (user_id, item_id),
    UNIQUE KEY uq_reports_auction (user_id, auction_id),
    CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_reports_item FOREIGN KEY (item_id) REFERENCES items(id),
    CONSTRAINT fk_reports_auction FOREIGN KEY (auction_id) REFERENCES auctions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS view_history (
    id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    item_id    INT UNSIGNED,
    auction_id INT UNSIGNED,
    viewed_at  DATETIME NOT NULL,
    KEY idx_view_history_user (user_id, viewed_at),
    CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_history_item FOREIGN KEY (item_id) REFERENCES items(id),
    CONSTRAINT fk_history_auction FOREIGN KEY (auction_id) REFERENCES auctions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS saved_searches (
    id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    params     VARCHAR(1000) NOT NULL,
    label      VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_saved_searches_user (user_id, params(255)),
    CONSTRAINT fk_saved_search_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
