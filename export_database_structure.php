<?php
// Script para exportar estrutura do banco local
require_once 'system/Database.php';

try {
    $db = \System\Database::getInstance();
    
    echo "=== EXPORTANDO ESTRUTURA DO BANCO LOCAL ===\n\n";
    
    // Listar todas as tabelas
    $tables = $db->fetchAll("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
    
    $sql = "-- Estrutura completa do banco divino_lanches\n";
    $sql .= "-- Exportado em: " . date('Y-m-d H:i:s') . "\n\n";
    
    foreach ($tables as $table) {
        $tableName = $table['tablename'];
        echo "Exportando tabela: $tableName\n";
        
        // Obter estrutura da tabela
        $createTable = $db->fetchAll("
            SELECT 
                'CREATE TABLE ' || schemaname || '.' || tablename || ' (' ||
                string_agg(
                    column_name || ' ' || data_type ||
                    CASE 
                        WHEN character_maximum_length IS NOT NULL 
                        THEN '(' || character_maximum_length || ')'
                        ELSE ''
                    END ||
                    CASE 
                        WHEN is_nullable = 'NO' THEN ' NOT NULL'
                        ELSE ''
                    END ||
                    CASE 
                        WHEN column_default IS NOT NULL 
                        THEN ' DEFAULT ' || column_default
                        ELSE ''
                    END,
                    ', '
                ) || ');' as create_statement
            FROM information_schema.columns 
            WHERE table_schema = 'public' AND table_name = '$tableName'
            GROUP BY schemaname, tablename
        ");
        
        if (!empty($createTable)) {
            $sql .= "\n-- Tabela: $tableName\n";
            $sql .= $createTable[0]['create_statement'] . "\n";
        }
        
        // Obter constraints
        $constraints = $db->fetchAll("
            SELECT conname, contype, pg_get_constraintdef(oid) as definition
            FROM pg_constraint 
            WHERE conrelid = '$tableName'::regclass
        ");
        
        foreach ($constraints as $constraint) {
            if ($constraint['contype'] === 'p') {
                $sql .= "ALTER TABLE $tableName ADD CONSTRAINT {$constraint['conname']} PRIMARY KEY;\n";
            } elseif ($constraint['contype'] === 'f') {
                $sql .= "ALTER TABLE $tableName ADD CONSTRAINT {$constraint['conname']} {$constraint['definition']};\n";
            } elseif ($constraint['contype'] === 'c') {
                $sql .= "ALTER TABLE $tableName ADD CONSTRAINT {$constraint['conname']} CHECK {$constraint['definition']};\n";
            }
        }
        
        $sql .= "\n";
    }
    
    // Salvar arquivo
    file_put_contents('database_complete_structure.sql', $sql);
    
    echo "\n✅ Estrutura exportada para: database_complete_structure.sql\n";
    echo "📊 Total de tabelas: " . count($tables) . "\n";
    
    // Listar tabelas encontradas
    echo "\n📋 Tabelas encontradas:\n";
    foreach ($tables as $table) {
        echo "- " . $table['tablename'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
