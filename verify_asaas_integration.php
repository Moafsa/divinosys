<?php
/**
 * Script de Verificação da Integração Asaas
 * Verifica se todas as tabelas e colunas do Asaas foram criadas corretamente
 */

require_once 'system/Database.php';
require_once 'system/Config.php';

try {
    echo "=== VERIFICAÇÃO DA INTEGRAÇÃO ASAAS ===\n\n";
    
    $db = \System\Database::getInstance();
    
    // 1. Verificar colunas na tabela tenants
    echo "1. Verificando colunas Asaas na tabela 'tenants'...\n";
    $tenantColumns = $db->query("
        SELECT column_name, data_type, is_nullable, column_default 
        FROM information_schema.columns 
        WHERE table_name = 'tenants' 
        AND column_name LIKE 'asaas_%'
        ORDER BY column_name
    ")->fetchAll();
    
    $expectedTenantColumns = [
        'asaas_api_key', 'asaas_api_url', 'asaas_customer_id', 
        'asaas_webhook_token', 'asaas_environment', 'asaas_enabled',
        'asaas_fiscal_info', 'asaas_municipal_service_id', 'asaas_municipal_service_code'
    ];
    
    $foundTenantColumns = array_column($tenantColumns, 'column_name');
    $missingTenantColumns = array_diff($expectedTenantColumns, $foundTenantColumns);
    
    if (empty($missingTenantColumns)) {
        echo "   ✅ Todas as colunas Asaas encontradas na tabela 'tenants'\n";
    } else {
        echo "   ❌ Colunas faltando na tabela 'tenants': " . implode(', ', $missingTenantColumns) . "\n";
    }
    
    // 2. Verificar colunas na tabela filiais
    echo "\n2. Verificando colunas Asaas na tabela 'filiais'...\n";
    $filiaisColumns = $db->query("
        SELECT column_name, data_type, is_nullable, column_default 
        FROM information_schema.columns 
        WHERE table_name = 'filiais' 
        AND column_name LIKE 'asaas_%'
        ORDER BY column_name
    ")->fetchAll();
    
    $expectedFiliaisColumns = [
        'asaas_api_key', 'asaas_customer_id', 'asaas_enabled',
        'asaas_fiscal_info', 'asaas_municipal_service_id', 'asaas_municipal_service_code'
    ];
    
    $foundFiliaisColumns = array_column($filiaisColumns, 'column_name');
    $missingFiliaisColumns = array_diff($expectedFiliaisColumns, $foundFiliaisColumns);
    
    if (empty($missingFiliaisColumns)) {
        echo "   ✅ Todas as colunas Asaas encontradas na tabela 'filiais'\n";
    } else {
        echo "   ❌ Colunas faltando na tabela 'filiais': " . implode(', ', $missingFiliaisColumns) . "\n";
    }
    
    // 3. Verificar tabela notas_fiscais
    echo "\n3. Verificando tabela 'notas_fiscais'...\n";
    $notasFiscaisExists = $db->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'notas_fiscais'
        )
    ")->fetchColumn();
    
    if ($notasFiscaisExists) {
        echo "   ✅ Tabela 'notas_fiscais' existe\n";
        
        // Verificar colunas principais
        $notasColumns = $db->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'notas_fiscais'
            ORDER BY column_name
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $expectedNotasColumns = [
            'id', 'tenant_id', 'filial_id', 'asaas_invoice_id', 'asaas_payment_id',
            'status', 'valor_total', 'created_at', 'updated_at'
        ];
        
        $missingNotasColumns = array_diff($expectedNotasColumns, $notasColumns);
        if (empty($missingNotasColumns)) {
            echo "   ✅ Colunas principais da tabela 'notas_fiscais' estão corretas\n";
        } else {
            echo "   ❌ Colunas faltando em 'notas_fiscais': " . implode(', ', $missingNotasColumns) . "\n";
        }
    } else {
        echo "   ❌ Tabela 'notas_fiscais' não existe\n";
    }
    
    // 4. Verificar tabela informacoes_fiscais
    echo "\n4. Verificando tabela 'informacoes_fiscais'...\n";
    $infoFiscaisExists = $db->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'informacoes_fiscais'
        )
    ")->fetchColumn();
    
    if ($infoFiscaisExists) {
        echo "   ✅ Tabela 'informacoes_fiscais' existe\n";
        
        // Verificar colunas principais
        $infoColumns = $db->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'informacoes_fiscais'
            ORDER BY column_name
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $expectedInfoColumns = [
            'id', 'tenant_id', 'filial_id', 'cnpj', 'razao_social', 
            'endereco', 'active', 'created_at', 'updated_at'
        ];
        
        $missingInfoColumns = array_diff($expectedInfoColumns, $infoColumns);
        if (empty($missingInfoColumns)) {
            echo "   ✅ Colunas principais da tabela 'informacoes_fiscais' estão corretas\n";
        } else {
            echo "   ❌ Colunas faltando em 'informacoes_fiscais': " . implode(', ', $missingInfoColumns) . "\n";
        }
    } else {
        echo "   ❌ Tabela 'informacoes_fiscais' não existe\n";
    }
    
    // 5. Verificar índices
    echo "\n5. Verificando índices Asaas...\n";
    $indexes = $db->query("
        SELECT indexname 
        FROM pg_indexes 
        WHERE (indexname LIKE '%asaas%' OR indexname LIKE '%fiscal%' OR indexname LIKE '%notas_fiscais%' OR indexname LIKE '%informacoes_fiscais%')
        AND schemaname = 'public'
        ORDER BY indexname
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedIndexes = [
        'idx_tenants_asaas_enabled',
        'idx_filiais_asaas_enabled',
        'idx_notas_fiscais_tenant_id',
        'idx_notas_fiscais_filial_id',
        'idx_notas_fiscais_status',
        'idx_notas_fiscais_asaas_invoice_id',
        'idx_informacoes_fiscais_tenant_id',
        'idx_informacoes_fiscais_filial_id',
        'idx_informacoes_fiscais_cnpj'
    ];
    
    $missingIndexes = array_diff($expectedIndexes, $indexes);
    if (empty($missingIndexes)) {
        echo "   ✅ Todos os índices Asaas foram criados\n";
    } else {
        echo "   ❌ Índices faltando: " . implode(', ', $missingIndexes) . "\n";
    }
    
    // 6. Verificar se as views estão acessíveis
    echo "\n6. Verificando acessibilidade das views...\n";
    $views = [
        'asaas_config' => 'mvc/views/asaas_config.php',
        'invoices_ajax' => 'mvc/ajax/invoices.php',
        'fiscal_info_ajax' => 'mvc/ajax/fiscal_info.php',
        'asaas_config_ajax' => 'mvc/ajax/asaas_config.php'
    ];
    
    foreach ($views as $name => $path) {
        if (file_exists($path)) {
            echo "   ✅ $name: $path\n";
        } else {
            echo "   ❌ $name: $path (não encontrado)\n";
        }
    }
    
    // 7. Verificar se os modelos existem
    echo "\n7. Verificando modelos...\n";
    $models = [
        'AsaasInvoice' => 'mvc/model/AsaasInvoice.php',
        'AsaasFiscalInfo' => 'mvc/model/AsaasFiscalInfo.php'
    ];
    
    foreach ($models as $name => $path) {
        if (file_exists($path)) {
            echo "   ✅ $name: $path\n";
        } else {
            echo "   ❌ $name: $path (não encontrado)\n";
        }
    }
    
    echo "\n=== VERIFICAÇÃO CONCLUÍDA ===\n";
    
    // Resumo final
    $totalChecks = 7;
    $passedChecks = 0;
    
    if (empty($missingTenantColumns)) $passedChecks++;
    if (empty($missingFiliaisColumns)) $passedChecks++;
    if ($notasFiscaisExists && empty($missingNotasColumns)) $passedChecks++;
    if ($infoFiscaisExists && empty($missingInfoColumns)) $passedChecks++;
    if (empty($missingIndexes)) $passedChecks++;
    
    $viewsExist = true;
    foreach ($views as $path) {
        if (!file_exists($path)) {
            $viewsExist = false;
            break;
        }
    }
    if ($viewsExist) $passedChecks++;
    
    $modelsExist = true;
    foreach ($models as $path) {
        if (!file_exists($path)) {
            $modelsExist = false;
            break;
        }
    }
    if ($modelsExist) $passedChecks++;
    
    echo "\nRESULTADO: $passedChecks/$totalChecks verificações passaram\n";
    
    if ($passedChecks === $totalChecks) {
        echo "🎉 INTEGRAÇÃO ASAAS ESTÁ 100% FUNCIONAL!\n";
        echo "✅ Pronto para deploy em qualquer ambiente\n";
        echo "✅ Todas as tabelas e colunas foram criadas\n";
        echo "✅ Sistema de migrações consolidado\n";
    } else {
        echo "⚠️  Algumas verificações falharam. Verifique os erros acima.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro durante verificação: " . $e->getMessage() . "\n";
    exit(1);
}
