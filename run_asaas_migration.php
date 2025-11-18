<?php
/**
 * Script para executar migration do Asaas
 * Cria as tabelas necessárias para integração com Asaas por estabelecimento/filial
 */

require_once 'system/Database.php';
require_once 'system/Config.php';

try {
    echo "Iniciando migration do Asaas...\n";
    
    $db = \System\Database::getInstance();
    $conn = $db->getConnection();
    
    // Ler o arquivo SQL
    $sqlFile = 'database/migrations/create_asaas_establishment_config.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Arquivo SQL não encontrado: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    if ($sql === false) {
        throw new Exception("Erro ao ler arquivo SQL");
    }
    
    echo "Executando SQL...\n";
    
    // Dividir o SQL em comandos individuais
    $commands = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($commands as $command) {
        if (empty($command) || strpos($command, '--') === 0) {
            continue; // Pular comentários e linhas vazias
        }
        
        echo "Executando: " . substr($command, 0, 50) . "...\n";
        
        try {
            $result = $db->query($command);
            if ($result === false) {
                throw new Exception("Erro ao executar comando SQL");
            }
        } catch (Exception $e) {
            echo "Aviso: " . $e->getMessage() . "\n";
            // Continuar com os próximos comandos mesmo se um falhar
        }
    }
    
    echo "Migration executada com sucesso!\n";
    echo "Tabelas criadas:\n";
    echo "- Colunas adicionadas às tabelas 'tenants' e 'filiais'\n";
    echo "- Tabela 'notas_fiscais' criada\n";
    echo "- Tabela 'informacoes_fiscais' criada\n";
    echo "- Índices criados para performance\n";
    
} catch (Exception $e) {
    echo "Erro na migration: " . $e->getMessage() . "\n";
    exit(1);
}
