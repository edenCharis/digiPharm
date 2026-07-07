-- Migration 002: digiMind chat history (conversations + messages)
-- Run: mysql -u root digipharmai_db < ai/schema/migration_002_chat.sql

USE digipharmai_db;

CREATE TABLE IF NOT EXISTS ai_chat_conversations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    pharmacy_id INT          NOT NULL,
    title       VARCHAR(150) NOT NULL DEFAULT 'Nouvelle conversation',
    created_at  DATETIME     DEFAULT NOW(),
    updated_at  DATETIME     DEFAULT NOW() ON UPDATE NOW(),

    FOREIGN KEY (user_id) REFERENCES ai_users(id) ON DELETE CASCADE,
    FOREIGN KEY (pharmacy_id) REFERENCES ai_pharmacies(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ai_chat_messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT                       NOT NULL,
    role            ENUM('user','assistant')  NOT NULL,
    content         TEXT                      NOT NULL,
    created_at      DATETIME                  DEFAULT NOW(),

    FOREIGN KEY (conversation_id) REFERENCES ai_chat_conversations(id) ON DELETE CASCADE
);
