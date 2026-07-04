-- Migration 002: widen port columns from SMALLINT to INT UNSIGNED
USE digipharmai_db;

ALTER TABLE ai_data_sources
    MODIFY ssh_port INT UNSIGNED NOT NULL DEFAULT 22,
    MODIFY db_port  INT UNSIGNED NOT NULL DEFAULT 3306;
