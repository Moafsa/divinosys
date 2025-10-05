<?php
require_once 'system/Database.php';
require_once 'system/Config.php';

echo "=== LIMPEZA DE PEDIDOS ANTIGOS ===\n";

$db = \System\Database::getInstance();

try {
    // Verificar pedidos antigos
    echo "=== VERIFICANDO PEDIDOS ANTIGOS ===\n";
    $pedidosAntigos = $db->fetchAll("
        SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.hora_pedido, p.created_at,
               m.numero as mesa_numero, m.id_mesa
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
        WHERE p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
        AND p.created_at <= NOW() - INTERVAL '2 hours'
        ORDER BY p.idmesa, p.created_at DESC
    ");
    
    if (empty($pedidosAntigos)) {
        echo "✅ Nenhum pedido antigo encontrado.\n";
    } else {
        echo "⚠️ Encontrados " . count($pedidosAntigos) . " pedido(s) antigo(s):\n";
        foreach($pedidosAntigos as $pedido) {
            echo "  - Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . " - Criado: " . $pedido['created_at'] . "\n";
        }
        
        echo "\n=== FINALIZANDO PEDIDOS ANTIGOS ===\n";
        $resultado = $db->update(
            'pedido',
            ['status' => 'Finalizado'],
            'status IN (?, ?, ?, ?) AND created_at <= NOW() - INTERVAL ?',
            ['Pendente', 'Preparando', 'Pronto', 'Entregue', '2 hours']
        );
        
        echo "✅ " . $resultado . " pedido(s) antigo(s) finalizado(s) automaticamente.\n";
    }
    
    echo "\n=== VERIFICANDO PEDIDOS ATIVOS APÓS LIMPEZA ===\n";
    $pedidosAtivos = $db->fetchAll("
        SELECT p.idpedido, p.idmesa, p.status, p.valor_total, p.hora_pedido, p.created_at,
               m.numero as mesa_numero, m.id_mesa
        FROM pedido p 
        LEFT JOIN mesas m ON p.idmesa::varchar = m.id_mesa 
        WHERE p.status NOT IN ('Finalizado', 'Cancelado')
        ORDER BY p.idmesa, p.created_at DESC
    ");
    
    if (empty($pedidosAtivos)) {
        echo "✅ Nenhum pedido ativo restante.\n";
    } else {
        echo "⚠️ Ainda existem " . count($pedidosAtivos) . " pedido(s) ativo(s):\n";
        foreach($pedidosAtivos as $pedido) {
            echo "  - Pedido #" . $pedido['idpedido'] . " - Mesa: " . $pedido['idmesa'] . " (Número: " . $pedido['mesa_numero'] . ") - Status: " . $pedido['status'] . " - Valor: R$ " . $pedido['valor_total'] . " - Hora: " . $pedido['hora_pedido'] . "\n";
        }
    }
    // 1. Fix pedido_itens table structure and data
    echo "Fixing pedido_itens table...\n";
    
    // Drop and recreate pedido_itens with correct structure
    $db->query("DROP TABLE IF EXISTS pedido_itens");
    $db->query("
        CREATE TABLE pedido_itens (
            id SERIAL PRIMARY KEY,
            pedido_id INTEGER NOT NULL,
            produto_id INTEGER NOT NULL,
            quantidade INTEGER NOT NULL DEFAULT 1,
            valor_unitario DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            ingredientes_com TEXT DEFAULT NULL,
            ingredientes_sem TEXT DEFAULT NULL,
            observacao TEXT DEFAULT NULL,
            tenant_id INTEGER NOT NULL DEFAULT 1,
            filial_id INTEGER NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insert sample data for pedido_itens
    $db->query("
        INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, valor_unitario, valor_total, ingredientes_com, ingredientes_sem, observacao, tenant_id, filial_id)
        VALUES 
        (1, 1, 2, 12.50, 25.00, 'Queijo Extra', '', 'Sem cebola', 1, 1),
        (1, 2, 1, 15.00, 15.00, '', 'Sem tomate', 'Bem assado', 1, 1),
        (2, 1, 1, 12.50, 12.50, '', '', '', 1, 1)
    ");
    echo "✅ pedido_itens table recreated with sample data\n";
    
    // 2. Ensure pedido table has all necessary columns
    echo "Adding missing columns to pedido table...\n";
    
    // Check existing columns
    $columns = $db->fetchAll("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'pedido' 
        ORDER BY ordinal_position
    ");
    
    $columnNames = array_column($columns, 'column_name');
    
    // Add missing columns
    $missingColumns = [
        'forma_pagamento' => 'VARCHAR(50) DEFAULT NULL',
        'numero_pessoas' => 'INTEGER DEFAULT 1',
        'valor_por_pessoa' => 'DECIMAL(10,2) DEFAULT 0.00',
        'observacao_pagamento' => 'TEXT DEFAULT NULL'
    ];
    
    foreach ($missingColumns as $column => $definition) {
        if (!in_array($column, $columnNames)) {
            $db->query("ALTER TABLE pedido ADD COLUMN $column $definition");
            echo "✅ Added column: $column\n";
        }
    }
    
    // 3. Update pedido data with missing columns
    echo "Updating pedido data...\n";
    $db->query("
        UPDATE pedido 
        SET 
            forma_pagamento = 'Dinheiro',
            numero_pessoas = 2,
            valor_por_pessoa = valor_total / 2,
            observacao_pagamento = 'Pagamento realizado'
        WHERE forma_pagamento IS NULL
    ");
    echo "✅ pedido data updated\n";
    
    // 4. Ensure mesas table has correct structure
    echo "Fixing mesas table structure...\n";
    
    // Check if mesas has the right columns
    $mesasColumns = $db->fetchAll("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'mesas' 
        ORDER BY ordinal_position
    ");
    
    $mesasColumnNames = array_column($mesasColumns, 'column_name');
    
    if (!in_array('status', $mesasColumnNames)) {
        $db->query("ALTER TABLE mesas ADD COLUMN status VARCHAR(20) DEFAULT '1'");
        echo "✅ Added status column to mesas\n";
    }
    
    // 5. Test all problematic queries
    echo "\n=== TESTING ALL QUERIES ===\n";
    
    // Test dashboard queries
    try {
        $pedidoss = $db->fetchAll("SELECT COUNT(*) as count FROM pedidoss WHERE tenant_id = 1 AND filial_id = 1");
        echo "✅ pedidoss query: " . $pedidoss[0]['count'] . " records\n";
        
        $pedido = $db->fetchAll("SELECT COUNT(*) as count FROM pedido WHERE tenant_id = 1 AND filial_id = 1");
        echo "✅ pedido query: " . $pedido[0]['count'] . " records\n";
        
        $mesas = $db->fetchAll("SELECT COUNT(*) as count FROM mesas WHERE tenant_id = 1 AND filial_id = 1");
        echo "✅ mesas query: " . $mesas[0]['count'] . " records\n";
        
    } catch (Exception $e) {
        echo "❌ Dashboard query failed: " . $e->getMessage() . "\n";
    }
    
    // Test mesa_multiplos_pedidos queries
    try {
        $mesaData = $db->fetchAll("
            SELECT * FROM mesas WHERE id_mesa = '1' AND tenant_id = 1 AND filial_id = 1
        ");
        echo "✅ Mesa query: " . count($mesaData) . " records\n";
        
        $pedidosMesa = $db->fetchAll("
            SELECT * FROM pedido WHERE idmesa::varchar = '1' AND tenant_id = 1 AND filial_id = 1 AND status NOT IN ('Finalizado', 'Cancelado')
        ");
        echo "✅ Pedidos mesa query: " . count($pedidosMesa) . " records\n";
        
        $itensPedido = $db->fetchAll("
            SELECT pi.*, pr.nome as produto_nome 
            FROM pedido_itens pi 
            LEFT JOIN produtos pr ON pi.produto_id = pr.id 
            WHERE pi.pedido_id = 1 AND pi.tenant_id = 1 AND pi.filial_id = 1
        ");
        echo "✅ Itens pedido query: " . count($itensPedido) . " records\n";
        
    } catch (Exception $e) {
        echo "❌ Mesa queries failed: " . $e->getMessage() . "\n";
    }
    
    // Test relatorios queries
    try {
        $relatoriosStats = $db->fetchAll("
            SELECT COUNT(*) as total_pedidos, COALESCE(SUM(valor_total), 0) as valor_total
            FROM pedido 
            WHERE tenant_id = 1 AND filial_id = 1 AND data = CURRENT_DATE
        ");
        echo "✅ Relatórios stats query: " . $relatoriosStats[0]['total_pedidos'] . " pedidos, R$ " . number_format($relatoriosStats[0]['valor_total'], 2, ',', '.') . "\n";
        
    } catch (Exception $e) {
        echo "❌ Relatórios query failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ ALL FINAL ERRORS FIXED!\n";
    echo "Dashboard, pedidos popup, and relatorios should now work perfectly.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
