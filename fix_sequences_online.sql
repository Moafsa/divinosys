-- Script para corrigir sequences no ambiente online
-- Execute este script no banco de dados online para corrigir os problemas de sequence

-- Corrigir sequence da tabela categorias
SELECT setval('categorias_id_seq', (SELECT MAX(id) FROM categorias) + 1);

-- Corrigir sequence da tabela ingredientes  
SELECT setval('ingredientes_id_seq', (SELECT MAX(id) FROM ingredientes) + 1);

-- Verificar se as sequences foram corrigidas
SELECT 'categorias_id_seq' as sequence_name, last_value FROM categorias_id_seq
UNION ALL
SELECT 'ingredientes_id_seq' as sequence_name, last_value FROM ingredientes_id_seq;

-- Verificar MAX IDs das tabelas
SELECT 'categorias' as table_name, MAX(id) as max_id FROM categorias
UNION ALL  
SELECT 'ingredientes' as table_name, MAX(id) as max_id FROM ingredientes;
