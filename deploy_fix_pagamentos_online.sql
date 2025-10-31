-- =====================================================
-- DEPLOY: Correção do Popup de Pagamentos - ONLINE
-- Data: 2025-10-20
-- Descrição: Corrige problemas de exibição na aba de pagamentos
-- =====================================================

-- 1. Adicionar campo usuario_global_id se não existir
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'pagamentos_pedido' 
        AND column_name = 'usuario_global_id'
    ) THEN
        ALTER TABLE pagamentos_pedido 
        ADD COLUMN usuario_global_id INTEGER REFERENCES usuarios_globais(id);
        
        RAISE NOTICE 'Campo usuario_global_id adicionado à tabela pagamentos_pedido';
    ELSE
        RAISE NOTICE 'Campo usuario_global_id já existe na tabela pagamentos_pedido';
    END IF;
END $$;

-- 2. Criar índice para performance
CREATE INDEX IF NOT EXISTS idx_pagamentos_pedido_usuario_global_id 
ON pagamentos_pedido(usuario_global_id);

-- 3. Atualizar registros existentes
UPDATE pagamentos_pedido 
SET usuario_global_id = p.usuario_global_id
FROM pedido p 
WHERE pagamentos_pedido.pedido_id = p.idpedido 
AND pagamentos_pedido.usuario_global_id IS NULL;

-- 4. Verificar estrutura da tabela
SELECT 
    column_name, 
    data_type, 
    is_nullable
FROM information_schema.columns 
WHERE table_name = 'pagamentos_pedido' 
ORDER BY ordinal_position;

-- 5. Verificar dados atualizados
SELECT 
    COUNT(*) as total_pagamentos,
    COUNT(usuario_global_id) as com_usuario_global_id,
    COUNT(*) - COUNT(usuario_global_id) as sem_usuario_global_id
FROM pagamentos_pedido;

-- 6. Mostrar alguns registros de exemplo
SELECT 
    id,
    pedido_id,
    valor_pago,
    forma_pagamento,
    usuario_global_id,
    created_at
FROM pagamentos_pedido 
ORDER BY created_at DESC 
LIMIT 5;

RAISE NOTICE 'Migração concluída com sucesso!';













