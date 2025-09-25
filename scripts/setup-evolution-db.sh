#!/bin/bash

echo "=== EVOLUTION API DATABASE SETUP ==="
echo "Setting up evolution_db database with required tables..."

# Wait for PostgreSQL to be ready
echo "Waiting for PostgreSQL to be ready..."
sleep 5

# Connect to evolution_db and create required tables
echo "Creating Evolution API tables..."

PGPASSWORD=${POSTGRES_PASSWORD} psql -h postgres -U ${POSTGRES_USER} -d evolution_db << EOF

-- Create instances table
CREATE TABLE IF NOT EXISTS instances (
    name VARCHAR(255) PRIMARY KEY,
    status VARCHAR(50) DEFAULT 'disconnected',
    qrcode TEXT,
    webhook_url VARCHAR(500),
    webhook_by_events BOOLEAN DEFAULT false,
    webhook_base64 BOOLEAN DEFAULT false,
    webhook_events TEXT[],
    reject_calls BOOLEAN DEFAULT false,
    msg_retry_count_calls INTEGER DEFAULT 3,
    msg_retry_count_chat INTEGER DEFAULT 3,
    qrcode_limit INTEGER DEFAULT 30,
    qrcode_color VARCHAR(7) DEFAULT '#198754',
    delay_send_message INTEGER DEFAULT 1000,
    delay_read_message INTEGER DEFAULT 1000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create messages table
CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    key_id VARCHAR(255) NOT NULL,
    remote_jid VARCHAR(255) NOT NULL,
    from_me BOOLEAN DEFAULT false,
    message TEXT,
    message_type VARCHAR(50),
    status VARCHAR(50),
    timestamp BIGINT,
    instance_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instance_name) REFERENCES instances(name) ON DELETE CASCADE
);

-- Create contacts table
CREATE TABLE IF NOT EXISTS contacts (
    id SERIAL PRIMARY KEY,
    remote_jid VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    notify VARCHAR(255),
    verified_name VARCHAR(255),
    is_user BOOLEAN DEFAULT false,
    is_group BOOLEAN DEFAULT false,
    is_broadcast BOOLEAN DEFAULT false,
    instance_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instance_name) REFERENCES instances(name) ON DELETE CASCADE
);

-- Create chats table
CREATE TABLE IF NOT EXISTS chats (
    id SERIAL PRIMARY KEY,
    remote_jid VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    is_group BOOLEAN DEFAULT false,
    is_read_only BOOLEAN DEFAULT false,
    unread_count INTEGER DEFAULT 0,
    instance_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instance_name) REFERENCES instances(name) ON DELETE CASCADE
);

-- Create groups table
CREATE TABLE IF NOT EXISTS groups (
    id SERIAL PRIMARY KEY,
    remote_jid VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    description TEXT,
    owner VARCHAR(255),
    creation_timestamp BIGINT,
    instance_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instance_name) REFERENCES instances(name) ON DELETE CASCADE
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_messages_remote_jid ON messages(remote_jid);
CREATE INDEX IF NOT EXISTS idx_messages_instance ON messages(instance_name);
CREATE INDEX IF NOT EXISTS idx_contacts_remote_jid ON contacts(remote_jid);
CREATE INDEX IF NOT EXISTS idx_contacts_instance ON contacts(instance_name);
CREATE INDEX IF NOT EXISTS idx_chats_remote_jid ON chats(remote_jid);
CREATE INDEX IF NOT EXISTS idx_chats_instance ON chats(instance_name);
CREATE INDEX IF NOT EXISTS idx_groups_remote_jid ON groups(remote_jid);
CREATE INDEX IF NOT EXISTS idx_groups_instance ON groups(instance_name);

-- Insert default instance if not exists
INSERT INTO instances (name, status, webhook_url, webhook_by_events, webhook_events) 
VALUES ('default', 'disconnected', '', false, ARRAY['connection.update', 'qrcode.updated', 'messages.upsert'])
ON CONFLICT (name) DO NOTHING;

EOF

echo "Evolution API database setup completed successfully!"
echo "Tables created: instances, messages, contacts, chats, groups"
echo "Default instance 'default' created"
