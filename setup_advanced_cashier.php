<?php
/**
 * Script para configurar o sistema de caixa avançado
 * Executa a estrutura de banco de dados e dados iniciais
 */

// Configuração do banco (Docker)
$host = 'postgres';
$dbname = 'divino_db';
$username = 'divino_user';
$password = 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔗 Conectado ao banco com sucesso!\n\n";
    
    // Ler arquivo SQL
    $sqlFile = '/var/www/html/05_advanced_cashier_system.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo SQL não encontrado: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    if (!$sql) {
        throw new Exception("Erro ao ler arquivo SQL");
    }
    
    echo "📄 Arquivo SQL carregado: " . strlen($sql) . " caracteres\n";
    
    // Dividir em comandos individuais
    $commands = array_filter(array_map('trim', explode(';', $sql)));
    
    echo "🔧 Executando " . count($commands) . " comandos SQL...\n\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($commands as $index => $command) {
        if (empty($command) || strpos($command, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($command);
            $successCount++;
            
            // Mostrar progresso
            if (($index + 1) % 10 == 0) {
                echo "✅ Executados " . ($index + 1) . " comandos...\n";
            }
            
        } catch (PDOException $e) {
            $errorCount++;
            echo "❌ Erro no comando " . ($index + 1) . ": " . $e->getMessage() . "\n";
            
            // Continuar mesmo com erros (alguns podem ser de tabelas já existentes)
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "   (Tabela já existe - continuando...)\n";
            }
        }
    }
    
    echo "\n📊 Resumo da Execução:\n";
    echo "✅ Comandos executados com sucesso: $successCount\n";
    echo "❌ Comandos com erro: $errorCount\n";
    
    // Verificar tabelas criadas
    echo "\n🔍 Verificando tabelas criadas...\n";
    
    $tables = [
        'clientes_fiado',
        'vendas_fiadas', 
        'pagamentos_fiado',
        'tipos_desconto',
        'descontos_aplicados',
        'configuracao_pagamento',
        'transacoes_pagamento',
        'categorias_financeiras',
        'contas_financeiras',
        'movimentacoes_financeiras_detalhadas',
        'relatorios_financeiros'
    ];
    
    $createdTables = [];
    foreach ($tables as $table) {
        $exists = $pdo->query("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_name = '$table'
            )
        ")->fetchColumn();
        
        if ($exists) {
            $createdTables[] = $table;
            echo "✅ $table\n";
        } else {
            echo "❌ $table (não criada)\n";
        }
    }
    
    // Verificar dados iniciais
    echo "\n🌱 Verificando dados iniciais...\n";
    
    $tiposDesconto = $pdo->query("SELECT COUNT(*) FROM tipos_desconto")->fetchColumn();
    $categorias = $pdo->query("SELECT COUNT(*) FROM categorias_financeiras")->fetchColumn();
    $contas = $pdo->query("SELECT COUNT(*) FROM contas_financeiras")->fetchColumn();
    
    echo "📊 Tipos de desconto: $tiposDesconto\n";
    echo "📊 Categorias financeiras: $categorias\n";
    echo "📊 Contas financeiras: $contas\n";
    
    echo "\n🎉 Sistema de caixa avançado configurado com sucesso!\n";
    echo "\n📝 Próximos passos:\n";
    echo "1. Implementar interface de gestão de clientes\n";
    echo "2. Implementar sistema de vendas fiadas\n";
    echo "3. Implementar sistema de descontos\n";
    echo "4. Implementar integração com gateways\n";
    echo "5. Implementar relatórios financeiros\n";
    
} catch (PDOException $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
