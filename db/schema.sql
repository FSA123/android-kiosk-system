-- Kiosk System SQLite Schema
-- Run via: php db/init_db.php

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- Media assets stored in the media/ directory
CREATE TABLE IF NOT EXISTS assets (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    filename    TEXT    NOT NULL UNIQUE,
    filepath    TEXT    NOT NULL,
    type        TEXT    NOT NULL CHECK(type IN ('image','video')),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Registered tablet / kiosk devices
CREATE TABLE IF NOT EXISTS devices (
    id          TEXT    PRIMARY KEY,          -- e.g. "tablet-01" or a UUID sent by the device
    ip          TEXT,
    name        TEXT,
    last_seen   DATETIME,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Playback events logged by each device
CREATE TABLE IF NOT EXISTS playback_logs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id   TEXT     NOT NULL,
    asset_id    INTEGER  NOT NULL,
    played_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id)   ON DELETE CASCADE,
    FOREIGN KEY (asset_id)  REFERENCES assets(id)    ON DELETE CASCADE
);

-- Admin users for dashboard / CMS access
CREATE TABLE IF NOT EXISTS admin_users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL UNIQUE,
    password_hash TEXT    NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Remote command queue: PC admin → tablet
CREATE TABLE IF NOT EXISTS command_queue (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    device_id   TEXT     NOT NULL,           -- target device ID (registered in devices table)
    command     TEXT     NOT NULL,            -- e.g. REBOOT, SYNC_NOW, RELOAD_PLAYLIST
    payload     TEXT,                         -- optional JSON payload
    status      TEXT     NOT NULL DEFAULT 'pending'
                         CHECK(status IN ('pending','executed','failed')),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    executed_at DATETIME,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);
