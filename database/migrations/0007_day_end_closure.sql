ALTER TABLE daily_closings
    ADD COLUMN status ENUM('open','closed') NOT NULL DEFAULT 'closed' AFTER difference;

ALTER TABLE daily_closings
    ADD COLUMN report_snapshot LONGTEXT NULL AFTER closed_at;

ALTER TABLE daily_closings
    ADD COLUMN reopened_by INT NULL AFTER report_snapshot;

ALTER TABLE daily_closings
    ADD COLUMN reopened_at DATETIME NULL AFTER reopened_by;

ALTER TABLE daily_closings
    ADD COLUMN reopen_reason TEXT NULL AFTER reopened_at;
