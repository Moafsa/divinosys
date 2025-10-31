-- Script para identificar e corrigir filiais com tenant_id incorreto
-- IMPORTANTE: Execute este script apenas após verificar manualmente as filiais

-- 1. LISTAR TODAS AS FILIAIS COM SEU TENANT_ID ATUAL
SELECT 
    f.id as filial_id,
    f.nome as filial_nome,
    f.tenant_id as tenant_id_atual,
    t.nome as tenant_nome,
    COUNT(DISTINCT f2.id) as total_filiais_do_tenant
FROM filiais f
LEFT JOIN tenants t ON f.tenant_id = t.id
LEFT JOIN filiais f2 ON f2.tenant_id = f.tenant_id
GROUP BY f.id, f.nome, f.tenant_id, t.nome
ORDER BY f.tenant_id, f.id;

-- 2. VERIFICAR FILIAIS QUE PODEM TER TENANT_ID ERRADO
-- (Por exemplo, filiais criadas após 2024-01-01 que estão no tenant_id = 1 mas não deveriam)
-- Ajuste a data conforme necessário
SELECT 
    f.id as filial_id,
    f.nome as filial_nome,
    f.tenant_id as tenant_id_atual,
    f.created_at,
    -- Verificar qual tenant_id deveria ser baseado na primeira filial do tenant
    (SELECT tenant_id FROM filiais WHERE tenant_id = (
        SELECT tenant_id FROM filiais ORDER BY id LIMIT 1
    ) ORDER BY id LIMIT 1) as tenant_id_sugerido
FROM filiais f
WHERE f.created_at > '2024-01-01'
AND f.tenant_id = 1
ORDER BY f.created_at DESC;

-- 3. CORRIGIR UMA FILIAL ESPECÍFICA (USE COM CUIDADO!)
-- Substitua X pelo ID da filial e Y pelo tenant_id correto
-- UPDATE filiais SET tenant_id = Y WHERE id = X;

-- 4. CORRIGIR TODAS AS FILIAIS DE UM TENANT ESPECÍFICO
-- Se você souber que todas as filiais de um tenant devem ter tenant_id = Y
-- UPDATE filiais f
-- SET tenant_id = Y
-- WHERE f.id IN (
--     SELECT f2.id FROM filiais f2
--     WHERE f2.nome LIKE '%nome_do_tenant%'
--     AND f2.tenant_id != Y
-- );

-- 5. CORRIGIR USUARIOS_ESTABELECIMENTO ASSOCIADOS
-- Quando corrigir uma filial, também precisa corrigir os vínculos
-- UPDATE usuarios_estabelecimento
-- SET tenant_id = Y
-- WHERE filial_id = X AND tenant_id != Y;

-- 6. CORRIGIR USUARIOS ASSOCIADOS
-- UPDATE usuarios
-- SET tenant_id = Y
-- WHERE filial_id = X AND tenant_id != Y;

