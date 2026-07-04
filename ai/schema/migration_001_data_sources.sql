-- Migration 001: data sources table (dynamic connection config)
-- Run: mysql -u root digipharmai_db < ai/schema/migration_001_data_sources.sql

USE digipharmai_db;

CREATE TABLE IF NOT EXISTS ai_data_sources (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    pharmacy_id     INT          NOT NULL,
    name            VARCHAR(100) NOT NULL DEFAULT 'Source principale',

    -- Connection type
    conn_type       ENUM('ssh','direct') NOT NULL DEFAULT 'ssh',

    -- SSH settings (conn_type = 'ssh')
    ssh_host        VARCHAR(200),
    ssh_port        SMALLINT     DEFAULT 22,
    ssh_user        VARCHAR(80)  DEFAULT 'root',
    ssh_password    TEXT,        -- AES-256-CBC encrypted

    -- Remote MySQL (accessed through tunnel, or directly if conn_type = 'direct')
    db_host         VARCHAR(200) DEFAULT '127.0.0.1',
    db_port         SMALLINT     DEFAULT 3306,
    db_name         VARCHAR(100),
    db_user         VARCHAR(80)  DEFAULT 'root',
    db_password     TEXT,        -- AES-256-CBC encrypted

    -- Schema mapping (JSON): table/column names in the source DB
    -- Keys: sales_table, sales_id_col, sales_date_col, items_table,
    --        items_sale_fk, items_product_fk, items_quantity_col, items_unit_price_col,
    --        products_table, products_id_col, products_name_col, products_stock_col,
    --        products_cost_col, products_price_col, products_expiry_col
    schema_map      JSON,

    -- State
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    last_tested_at  DATETIME,
    last_test_ok    TINYINT(1),
    last_test_error TEXT,
    last_synced_at  DATETIME,
    last_sync_rows  INT          DEFAULT 0,

    created_at      DATETIME     DEFAULT NOW(),
    updated_at      DATETIME     DEFAULT NOW() ON UPDATE NOW(),

    FOREIGN KEY (pharmacy_id) REFERENCES ai_pharmacies(id) ON DELETE CASCADE
);
