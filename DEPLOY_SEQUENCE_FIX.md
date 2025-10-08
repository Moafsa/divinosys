# Correção de Sequences - Deploy Online

## Problema Identificado
Os cadastros de categorias e ingredientes estavam falhando devido a sequences desatualizadas no PostgreSQL:
- Categorias: sequence em 4, mas MAX(id) era 233
- Ingredientes: sequence em 13, mas MAX(id) era 137

## Solução Aplicada
Correção das sequences para o valor correto (MAX(id) + 1).

## Arquivos Criados
1. `fix_sequences_online.sql` - Script SQL para execução direta
2. `fix_sequences_online.php` - Script PHP para execução no servidor

## Como Aplicar no Servidor Online

### Opção 1: Via Script PHP (Recomendado)
1. Faça upload do arquivo `fix_sequences_online.php` para o servidor
2. Execute via navegador: `https://seudominio.com/fix_sequences_online.php`
3. Verifique a saída para confirmar que as correções foram aplicadas
4. **IMPORTANTE:** Delete o arquivo após a execução por segurança

### Opção 2: Via SQL Direto
1. Conecte ao banco PostgreSQL do servidor online
2. Execute o conteúdo do arquivo `fix_sequences_online.sql`

## Comandos SQL para Correção Manual
```sql
-- Corrigir sequence de categorias
SELECT setval('categorias_id_seq', (SELECT MAX(id) FROM categorias) + 1);

-- Corrigir sequence de ingredientes
SELECT setval('ingredientes_id_seq', (SELECT MAX(id) FROM ingredientes) + 1);
```

## Verificação
Após aplicar as correções, teste:
1. Cadastrar uma nova categoria
2. Cadastrar um novo ingrediente
3. Ambos devem funcionar sem erros de constraint

## Resultado Esperado
- ✅ Cadastro de categorias funcionando
- ✅ Cadastro de ingredientes funcionando
- ✅ Sem erros de "duplicate key value violates unique constraint"
