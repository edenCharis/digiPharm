-- DigiPharm AI — Analytics Database
-- Separate from the ERP DB. Multi-tenant: one row per pharmacy.
-- Run: mysql -u root -p < ai/schema/digipharmai.sql

CREATE DATABASE IF NOT EXISTS digipharmai_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE digipharmai_db;

-- ── Pharmacies subscribed to the AI dashboard ─────────────────────────────
CREATE TABLE IF NOT EXISTS ai_pharmacies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    slug        VARCHAR(80)  NOT NULL UNIQUE,
    api_key     CHAR(64)     NOT NULL UNIQUE,
    plan        ENUM('starter','pro','enterprise') NOT NULL DEFAULT 'starter',
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME DEFAULT NOW(),
    updated_at  DATETIME DEFAULT NOW() ON UPDATE NOW()
);

-- ── Dashboard users (belong to a pharmacy) ────────────────────────────────
CREATE TABLE IF NOT EXISTS ai_users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id INT         NOT NULL,
    email       VARCHAR(150) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    display_name VARCHAR(120),
    role        ENUM('admin','viewer') NOT NULL DEFAULT 'viewer',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    last_login  DATETIME,
    created_at  DATETIME DEFAULT NOW(),
    UNIQUE KEY uk_email (email),
    FOREIGN KEY (pharmacy_id) REFERENCES ai_pharmacies(id) ON DELETE CASCADE
);

-- ── Normalized sales (aggregated per product per day) ─────────────────────
-- source_sale_id is the PK from the source DB — used for deduplication.
CREATE TABLE IF NOT EXISTS ai_sales (
    id             BIGINT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id    INT         NOT NULL,
    sale_date      DATE        NOT NULL,
    product_id     VARCHAR(64) NOT NULL,
    product_name   VARCHAR(200),
    category       VARCHAR(100),
    quantity       DECIMAL(10,3) NOT NULL DEFAULT 0,
    unit_price     DECIMAL(12,2) NOT NULL DEFAULT 0,
    revenue        DECIMAL(14,2) NOT NULL DEFAULT 0,
    cost           DECIMAL(14,2),
    source_sale_id VARCHAR(64),
    created_at     DATETIME DEFAULT NOW(),
    INDEX  idx_pharm_date    (pharmacy_id, sale_date),
    INDEX  idx_pharm_product (pharmacy_id, product_id),
    UNIQUE KEY uk_source     (pharmacy_id, source_sale_id, product_id)
);

-- ── Inventory snapshots (taken at each ETL run) ───────────────────────────
CREATE TABLE IF NOT EXISTS ai_inventory (
    id              BIGINT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id     INT         NOT NULL,
    snapshot_date   DATE        NOT NULL,
    product_id      VARCHAR(64) NOT NULL,
    product_name    VARCHAR(200),
    category        VARCHAR(100),
    stock_quantity  DECIMAL(10,3) NOT NULL DEFAULT 0,
    unit_cost       DECIMAL(12,2),
    unit_price      DECIMAL(12,2),
    expiry_date     DATE,
    created_at      DATETIME DEFAULT NOW(),
    INDEX  idx_pharm_snap (pharmacy_id, snapshot_date),
    UNIQUE KEY uk_snap    (pharmacy_id, snapshot_date, product_id)
);

-- ── ETL run log ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ai_etl_runs (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id      INT NOT NULL,
    adapter          VARCHAR(80),
    run_at           DATETIME DEFAULT NOW(),
    status           ENUM('success','partial','failed') NOT NULL,
    rows_synced      INT DEFAULT 0,
    last_synced_date DATE,
    error_message    TEXT,
    duration_sec     DECIMAL(8,2)
);

-- ── Seed: Pharmacie Galy ──────────────────────────────────────────────────
-- api_key generated with: python3 -c "import secrets; print(secrets.token_hex(32))"
INSERT IGNORE INTO ai_pharmacies (name, slug, api_key, plan)
VALUES ('Pharmacie Galy', 'galy', REPLACE(UUID(),'-',''), 'pro');
