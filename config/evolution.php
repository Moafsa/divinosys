<?php

/**
 * Configurações da Evolution API
 */

return [
    // URL base da Evolution API (configurar via .env)
    'base_url' => $_ENV['EVOLUTION_BASE_URL'] ?? 'http://localhost:8080/evolution-api',
    
    // Chave da API (configurar via .env)
    'api_key' => $_ENV['EVOLUTION_API_KEY'] ?? 'your-api-key-here',
    
    // Webhook do n8n para LGPD (configurar via .env)
    'n8n_webhook_url' => $_ENV['N8N_WEBHOOK_URL'] ?? 'https://whook.conext.click/webhook/divinosyslgpd',
    
    // Configurações de timeout
    'timeout' => 30,
    
    // Configurações de retry
    'max_retries' => 3,
    'retry_delay' => 1000, // milissegundos
    
    // Configurações de webhook
    'webhook_events' => [
        'connection.update',
        'messages.upsert',
        'messages.update',
        'send.message'
    ],
    
    // Configurações de mensagens LGPD
    'lgpd' => [
        'message_template' => "Olá {nome}! 👋\n\nDetectamos que você já é cliente em outro estabelecimento que usa nossa plataforma. Para facilitar seu pedido, podemos compartilhar seus dados entre estabelecimentos?\n\n✅ Responda SIM para autorizar\n❌ Responda NÃO para não compartilhar\n\nSeus dados serão usados apenas para:\n• Facilitar seus pedidos\n• Manter seu histórico de compras\n• Melhorar seu atendimento\n\nVocê pode revogar este consentimento a qualquer momento.",
        'expiration_minutes' => 5
    ]
];
