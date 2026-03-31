CREATE TABLE IF NOT EXISTS sync_state (
    table_name        VARCHAR(100) PRIMARY KEY,
    is_dirty          TINYINT(1)   NOT NULL DEFAULT 1,
    last_synced_at    DATETIME     NULL,
    last_attempted_at DATETIME     NULL,
    last_synced_count INT          NOT NULL DEFAULT 0,
    last_error        TEXT         NULL,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
