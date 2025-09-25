<?php

/**
 * Script de teste para o sistema Evolution API
 * Execute: php test_evolution.php
 */

require_once 'system/Database.php';
require_once 'system/EvolutionAPI.php';
require_once 'system/Auth.php';

use System\Database;
use System\EvolutionAPI;
use System\Auth;

// Inicializar sistema
$db = Database::getInstance();

echo "=== TESTE DO SISTEMA EVOLUTION API ===\n\n";

// Teste 1: Verificar conexão com banco
echo "1. Testando conexão com banco de dados...\n";
try {
    $result = $db->fetch("SELECT 1 as test");
    if ($result && $result['test'] == 1) {
        echo "✅ Conexão com banco OK\n\n";
    } else {
        echo "❌ Erro na conexão com banco\n\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Teste 2: Verificar tabelas
echo "2. Verificando tabelas do sistema...\n";
$tables = ['usuarios_globais', 'usuarios_telefones', 'evolution_instancias', 'tokens_autenticacao'];
foreach ($tables as $table) {
    try {
        $result = $db->fetch("SELECT COUNT(*) as count FROM $table");
        echo "✅ Tabela $table: {$result['count']} registros\n";
    } catch (Exception $e) {
        echo "❌ Tabela $table: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Teste 3: Testar criação de usuário
echo "3. Testando criação de usuário...\n";
try {
    $telefone = '11999999999';
    $usuario = Auth::findUserByPhone($telefone);
    
    if (!$usuario) {
        $usuarioId = Auth::createUser([
            'nome' => 'Usuário Teste',
            'ativo' => true
        ]);
        Auth::addUserPhone($usuarioId, $telefone, 'principal');
        echo "✅ Usuário criado com sucesso (ID: $usuarioId)\n";
    } else {
        echo "✅ Usuário já existe (ID: {$usuario['id']})\n";
    }
} catch (Exception $e) {
    echo "❌ Erro ao criar usuário: " . $e->getMessage() . "\n";
}
echo "\n";

// Teste 4: Testar geração de token
echo "4. Testando geração de token...\n";
try {
    $usuario = Auth::findUserByPhone('11999999999');
    if ($usuario) {
        $token = Auth::generateToken($usuario['id'], 'login');
        echo "✅ Token gerado: " . substr($token, 0, 20) . "...\n";
        
        // Testar validação do token
        $tokenData = Auth::validateToken($token);
        if ($tokenData) {
            echo "✅ Token validado com sucesso\n";
        } else {
            echo "❌ Erro na validação do token\n";
        }
    } else {
        echo "❌ Usuário não encontrado\n";
    }
} catch (Exception $e) {
    echo "❌ Erro ao gerar token: " . $e->getMessage() . "\n";
}
echo "\n";

// Teste 5: Testar instâncias Evolution
echo "5. Testando instâncias Evolution...\n";
try {
    $instancias = EvolutionAPI::getInstances(1, 1);
    echo "✅ Instâncias encontradas: " . count($instancias) . "\n";
    
    foreach ($instancias as $instancia) {
        echo "   - {$instancia['nome_instancia']} ({$instancia['status']})\n";
    }
} catch (Exception $e) {
    echo "❌ Erro ao listar instâncias: " . $e->getMessage() . "\n";
}
echo "\n";

// Teste 6: Testar envio de mensagem LGPD (simulado)
echo "6. Testando envio de mensagem LGPD...\n";
try {
    $usuario = Auth::findUserByPhone('11999999999');
    if ($usuario) {
        // Simular envio (sem realmente enviar)
        echo "✅ Dados preparados para envio:\n";
        echo "   - Nome: {$usuario['nome']}\n";
        echo "   - Telefone: 11999999999\n";
        echo "   - Estância: teste_instancia\n";
        echo "   - Mensagem: [Mensagem LGPD personalizada]\n";
    } else {
        echo "❌ Usuário não encontrado\n";
    }
} catch (Exception $e) {
    echo "❌ Erro ao preparar mensagem LGPD: " . $e->getMessage() . "\n";
}
echo "\n";

// Teste 7: Verificar configurações
echo "7. Verificando configurações...\n";
try {
    $config = require_once 'config/evolution.php';
    echo "✅ Configurações carregadas:\n";
    echo "   - Base URL: {$config['base_url']}\n";
    echo "   - API Key: " . (strlen($config['api_key']) > 0 ? 'Configurada' : 'Não configurada') . "\n";
    echo "   - Webhook n8n: {$config['n8n_webhook_url']}\n";
} catch (Exception $e) {
    echo "❌ Erro ao carregar configurações: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== TESTE CONCLUÍDO ===\n";
echo "Para testar o sistema completo:\n";
echo "1. Acesse: http://localhost:8080/mvc/views/evolution_config.php\n";
echo "2. Configure uma instância Evolution\n";
echo "3. Teste o envio de mensagens\n";
echo "4. Verifique os logs em /var/www/html/logs/\n";
