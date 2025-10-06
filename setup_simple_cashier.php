<?php
/**
 * Script simples para configurar sistema de caixa avançado
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
    
    // 1. Criar tabela clientes_fiado
    echo "📋 Criando tabela clientes_fiado...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clientes_fiado (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            cpf_cnpj VARCHAR(20) UNIQUE,
            telefone VARCHAR(20),
            email VARCHAR(100),
            endereco TEXT,
            limite_credito DECIMAL(10,2) DEFAULT 0.00,
            saldo_devedor DECIMAL(10,2) DEFAULT 0.00,
            status VARCHAR(20) DEFAULT 'ativo',
            data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            observacoes TEXT,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela clientes_fiado criada!\n";
    
    // 2. Criar tabela vendas_fiadas
    echo "📋 Criando tabela vendas_fiadas...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vendas_fiadas (
            id SERIAL PRIMARY KEY,
            cliente_id INTEGER NOT NULL REFERENCES clientes_fiado(id) ON DELETE CASCADE,
            pedido_id INTEGER,
            valor_total DECIMAL(10,2) NOT NULL,
            data_vencimento DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'pendente',
            observacoes TEXT,
            usuario_id INTEGER,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela vendas_fiadas criada!\n";
    
    // 3. Criar tabela pagamentos_fiado
    echo "📋 Criando tabela pagamentos_fiado...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pagamentos_fiado (
            id SERIAL PRIMARY KEY,
            venda_fiada_id INTEGER NOT NULL REFERENCES vendas_fiadas(id) ON DELETE CASCADE,
            valor_pago DECIMAL(10,2) NOT NULL,
            data_pagamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            forma_pagamento VARCHAR(50) NOT NULL,
            observacoes TEXT,
            usuario_id INTEGER,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela pagamentos_fiado criada!\n";
    
    // 4. Criar tabela tipos_desconto
    echo "📋 Criando tabela tipos_desconto...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tipos_desconto (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            valor DECIMAL(10,2),
            percentual DECIMAL(5,2),
            requer_autorizacao BOOLEAN DEFAULT false,
            nivel_autorizacao INTEGER DEFAULT 1,
            ativo BOOLEAN DEFAULT true,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela tipos_desconto criada!\n";
    
    // 5. Criar tabela descontos_aplicados
    echo "📋 Criando tabela descontos_aplicados...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS descontos_aplicados (
            id SERIAL PRIMARY KEY,
            pedido_id INTEGER NOT NULL,
            tipo_desconto_id INTEGER REFERENCES tipos_desconto(id),
            valor_desconto DECIMAL(10,2) NOT NULL,
            motivo TEXT,
            autorizado_por INTEGER,
            data_aplicacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER
        )
    ");
    echo "✅ Tabela descontos_aplicados criada!\n";
    
    // 6. Criar tabela configuracao_pagamento
    echo "📋 Criando tabela configuracao_pagamento...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS configuracao_pagamento (
            id SERIAL PRIMARY KEY,
            gateway VARCHAR(50) NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            configuracao JSONB NOT NULL,
            ativo BOOLEAN DEFAULT true,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela configuracao_pagamento criada!\n";
    
    // 7. Criar tabela transacoes_pagamento
    echo "📋 Criando tabela transacoes_pagamento...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transacoes_pagamento (
            id SERIAL PRIMARY KEY,
            pedido_id INTEGER,
            gateway VARCHAR(50) NOT NULL,
            id_transacao_gateway VARCHAR(100),
            valor DECIMAL(10,2) NOT NULL,
            forma_pagamento VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            dados_resposta JSONB,
            data_processamento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER
        )
    ");
    echo "✅ Tabela transacoes_pagamento criada!\n";
    
    // 8. Criar tabela movimentacoes_financeiras_detalhadas
    echo "📋 Criando tabela movimentacoes_financeiras_detalhadas...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS movimentacoes_financeiras_detalhadas (
            id SERIAL PRIMARY KEY,
            pedido_id INTEGER,
            mesa_id VARCHAR(10),
            tipo_movimentacao VARCHAR(20) NOT NULL,
            categoria_id INTEGER,
            conta_id INTEGER,
            forma_pagamento VARCHAR(50) NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            data_movimentacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            descricao TEXT,
            observacoes TEXT,
            usuario_id INTEGER,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER,
            status VARCHAR(20) DEFAULT 'confirmado',
            comprovante_url VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela movimentacoes_financeiras_detalhadas criada!\n";
    
    // 9. Inserir dados iniciais
    echo "\n🌱 Inserindo dados iniciais...\n";
    
    // Tipos de desconto
    $pdo->exec("
        INSERT INTO tipos_desconto (nome, tipo, percentual, requer_autorizacao, nivel_autorizacao, tenant_id, filial_id) VALUES
        ('Desconto Cliente Frequente', 'percentual', 5.00, false, 1, 1, 1),
        ('Desconto por Problema', 'percentual', 10.00, true, 2, 1, 1),
        ('Cortesia Gerência', 'cortesia', 100.00, true, 3, 1, 1),
        ('Desconto Funcionário', 'percentual', 20.00, true, 2, 1, 1),
        ('Desconto Especial', 'valor_fixo', 0.00, true, 3, 1, 1)
        ON CONFLICT DO NOTHING
    ");
    echo "✅ Tipos de desconto inseridos!\n";
    
    // Categorias financeiras
    $pdo->exec("
        INSERT INTO categorias_financeiras (nome, tipo, descricao, tenant_id, filial_id) VALUES
        ('Vendas Mesa', 'receita', 'Receitas de vendas em mesa', 1, 1),
        ('Vendas Delivery', 'receita', 'Receitas de vendas delivery', 1, 1),
        ('Vendas Fiadas', 'receita', 'Receitas de vendas fiadas', 1, 1),
        ('Despesas Operacionais', 'despesa', 'Despesas operacionais do estabelecimento', 1, 1),
        ('Despesas de Marketing', 'despesa', 'Despesas de marketing e publicidade', 1, 1)
        ON CONFLICT DO NOTHING
    ");
    echo "✅ Categorias financeiras inseridas!\n";
    
    // Contas financeiras
    $pdo->exec("
        INSERT INTO contas_financeiras (nome, tipo, saldo_inicial, saldo_atual, tenant_id, filial_id) VALUES
        ('Caixa Principal', 'conta_corrente', 0.00, 0.00, 1, 1),
        ('Conta Corrente', 'conta_corrente', 0.00, 0.00, 1, 1),
        ('PIX', 'carteira', 0.00, 0.00, 1, 1)
        ON CONFLICT DO NOTHING
    ");
    echo "✅ Contas financeiras inseridas!\n";
    
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
        'movimentacoes_financeiras_detalhadas'
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
    
    echo "\n🎉 Sistema de caixa avançado configurado com sucesso!\n";
    echo "📊 Tabelas criadas: " . count($createdTables) . "/" . count($tables) . "\n";
    
} catch (PDOException $e) {
    echo "❌ Erro de conexão: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
