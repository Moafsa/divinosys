-- Script SQL completo para corrigir problemas online
-- Execute este script no banco PostgreSQL online

-- Verificar estrutura atual
SELECT 'Estrutura atual da tabela categorias:' as info;
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'categorias' 
ORDER BY ordinal_position;

SELECT 'Estrutura atual da tabela ingredientes:' as info;
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_name = 'ingredientes' 
ORDER BY ordinal_position;

-- Adicionar colunas faltantes na tabela categorias
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS descricao TEXT;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS ordem INTEGER DEFAULT 0;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS parent_id INTEGER;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS imagem VARCHAR(255);

-- Adicionar colunas faltantes na tabela ingredientes
ALTER TABLE ingredientes ADD COLUMN IF NOT EXISTS descricao TEXT;
ALTER TABLE ingredientes ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true;

-- Verificar estado atual das sequences
SELECT 'Estado atual das sequences:' as info;
SELECT 'Categorias' as tabela, last_value as sequence_atual, (SELECT MAX(id) FROM categorias) as max_id FROM categorias_id_seq
UNION ALL
SELECT 'Ingredientes' as tabela, last_value as sequence_atual, (SELECT MAX(id) FROM ingredientes) as max_id FROM ingredientes_id_seq;

-- Corrigir sequences
SELECT setval('categorias_id_seq', (SELECT MAX(id) FROM categorias) + 1);
SELECT setval('ingredientes_id_seq', (SELECT MAX(id) FROM ingredientes) + 1);

-- Verificar sequences após correção
SELECT 'Sequences após correção:' as info;
SELECT 'Categorias' as tabela, last_value as sequence_final FROM categorias_id_seq
UNION ALL
SELECT 'Ingredientes' as tabela, last_value as sequence_final FROM ingredientes_id_seq;

-- Teste de inserção (opcional - descomente se quiser testar)
/*
-- Teste categoria
INSERT INTO categorias (nome, descricao, ativo, tenant_id, filial_id) 
VALUES ('Teste Categoria', 'Teste de funcionamento', true, 1, 1);

-- Verificar se foi inserida
SELECT id, nome FROM categorias WHERE nome = 'Teste Categoria';

-- Remover teste
DELETE FROM categorias WHERE nome = 'Teste Categoria';

-- Teste ingrediente
INSERT INTO ingredientes (nome, descricao, tipo, preco_adicional, ativo, tenant_id, filial_id) 
VALUES ('Teste Ingrediente', 'Teste de funcionamento', 'teste', 0, true, 1, 1);

-- Verificar se foi inserido
SELECT id, nome FROM ingredientes WHERE nome = 'Teste Ingrediente';

-- Remover teste
DELETE FROM ingredientes WHERE nome = 'Teste Ingrediente';
*/

SELECT 'Correção completa finalizada!' as resultado;
