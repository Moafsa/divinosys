-- Tabelas para integração com Chatwoot

-- Tabela para armazenar usuários do Chatwoot
CREATE TABLE IF NOT EXISTS chatwoot_users (
    id SERIAL PRIMARY KEY,
    estabelecimento_id INTEGER NOT NULL,
    chatwoot_user_id INTEGER NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(estabelecimento_id),
    FOREIGN KEY (estabelecimento_id) REFERENCES filiais(id) ON DELETE CASCADE
);

-- Tabela para armazenar inboxes do Chatwoot
CREATE TABLE IF NOT EXISTS chatwoot_inboxes (
    id SERIAL PRIMARY KEY,
    estabelecimento_id INTEGER NOT NULL,
    inbox_id INTEGER NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(estabelecimento_id),
    FOREIGN KEY (estabelecimento_id) REFERENCES filiais(id) ON DELETE CASCADE
);

-- Tabela para armazenar conversas do Chatwoot
CREATE TABLE IF NOT EXISTS chatwoot_conversations (
    id SERIAL PRIMARY KEY,
    estabelecimento_id INTEGER NOT NULL,
    chatwoot_conversation_id INTEGER NOT NULL,
    contact_id INTEGER,
    status VARCHAR(50) DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(chatwoot_conversation_id),
    FOREIGN KEY (estabelecimento_id) REFERENCES filiais(id) ON DELETE CASCADE
);

-- Tabela para armazenar mensagens do Chatwoot
CREATE TABLE IF NOT EXISTS chatwoot_messages (
    id SERIAL PRIMARY KEY,
    chatwoot_conversation_id INTEGER NOT NULL,
    chatwoot_message_id INTEGER NOT NULL,
    content TEXT,
    message_type VARCHAR(50),
    sender_type VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chatwoot_conversation_id) REFERENCES chatwoot_conversations(chatwoot_conversation_id) ON DELETE CASCADE
);

-- Índices para melhor performance
CREATE INDEX IF NOT EXISTS idx_chatwoot_users_estabelecimento ON chatwoot_users(estabelecimento_id);
CREATE INDEX IF NOT EXISTS idx_chatwoot_inboxes_estabelecimento ON chatwoot_inboxes(estabelecimento_id);
CREATE INDEX IF NOT EXISTS idx_chatwoot_conversations_estabelecimento ON chatwoot_conversations(estabelecimento_id);
CREATE INDEX IF NOT EXISTS idx_chatwoot_messages_conversation ON chatwoot_messages(chatwoot_conversation_id);

-- Comentários das tabelas
COMMENT ON TABLE chatwoot_users IS 'Usuários criados no Chatwoot para cada estabelecimento';
COMMENT ON TABLE chatwoot_inboxes IS 'Inboxes do WhatsApp criados no Chatwoot';
COMMENT ON TABLE chatwoot_conversations IS 'Conversas do Chatwoot vinculadas aos estabelecimentos';
COMMENT ON TABLE chatwoot_messages IS 'Mensagens das conversas do Chatwoot';
