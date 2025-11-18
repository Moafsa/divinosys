# Deploy: Correção do Popup de Pagamentos

## Problema Corrigido
- Valores "R$ NaN" na aba de pagamentos do popup de clientes
- Status "undefined" na aba de pagamentos
- Campo `usuario_global_id` ausente na tabela `pagamentos_pedido`

## Correções Aplicadas Localmente ✅

### 1. Estrutura da Tabela
```sql
-- Campo adicionado (já existe)
ALTER TABLE pagamentos_pedido ADD COLUMN IF NOT EXISTS usuario_global_id INTEGER REFERENCES usuarios_globais(id);

-- Índice criado
CREATE INDEX IF NOT EXISTS idx_pagamentos_pedido_usuario_global_id ON pagamentos_pedido(usuario_global_id);

-- Dados atualizados
UPDATE pagamentos_pedido SET usuario_global_id = p.usuario_global_id 
FROM pedido p 
WHERE pagamentos_pedido.pedido_id = p.idpedido 
AND pagamentos_pedido.usuario_global_id IS NULL;
```

### 2. Código Corrigido
- **Arquivo**: `mvc/views/clientes.php`
- **Função**: `renderizarHistoricoPagamentos()`
- **Correções**:
  - `pagamento.valor` → `pagamento.valor_pago`
  - `pagamento.status` → `'Confirmado'` (valor padrão)
  - Tratamento de valores nulos
  - Formatação segura de valores monetários

## Para Aplicar Online

### Passo 1: Executar Migração SQL
```sql
-- Conectar ao banco de dados online
-- Executar os comandos SQL acima
```

### Passo 2: Fazer Upload dos Arquivos
```bash
# Fazer upload do arquivo corrigido
scp mvc/views/clientes.php usuario@servidor:/caminho/para/aplicacao/mvc/views/
```

### Passo 3: Verificar Aplicação
1. Acessar o sistema online
2. Ir para a página de Clientes
3. Abrir popup de qualquer cliente
4. Clicar na aba "Pagamentos"
5. Verificar se os valores aparecem corretamente (não mais "R$ NaN")
6. Verificar se o status aparece como "Confirmado" (não mais "undefined")

## Arquivos Modificados
- `mvc/views/clientes.php` - Interface corrigida
- `mvc/model/Cliente.php` - Lógica de consulta melhorada
- `database/migrations/fix_pagamentos_pedido_usuario_global_id.sql` - Migração

## Status
- ✅ Correções aplicadas localmente
- ⏳ Aguardando deploy online
- ✅ Testes locais funcionando

## Comandos para Deploy Online

### Via SSH/Console:
```bash
# 1. Conectar ao servidor
ssh usuario@servidor

# 2. Navegar para o diretório da aplicação
cd /caminho/para/aplicacao

# 3. Executar migração SQL
psql -U usuario -d database -f database/migrations/fix_pagamentos_pedido_usuario_global_id.sql

# 4. Fazer backup dos arquivos atuais
cp mvc/views/clientes.php mvc/views/clientes.php.backup
cp mvc/model/Cliente.php mvc/model/Cliente.php.backup

# 5. Fazer upload dos arquivos corrigidos (já feito via scp)

# 6. Verificar se os arquivos foram atualizados
ls -la mvc/views/clientes.php
ls -la mvc/model/Cliente.php

# 7. Reiniciar serviços se necessário
sudo systemctl restart nginx
sudo systemctl restart php-fpm
```

### Via Docker (se aplicável):
```bash
# 1. Executar migração no container
docker exec -i container-db psql -U usuario -d database -f /path/to/migration.sql

# 2. Reiniciar container da aplicação
docker restart container-app
```

## Verificação Final
Após o deploy, verificar:
1. ✅ Popup de clientes abre corretamente
2. ✅ Aba "Pagamentos" mostra valores corretos (R$ X,XX)
3. ✅ Status aparece como "Confirmado"
4. ✅ Histórico de pedidos mostra pedidos pagos
5. ✅ Estabelecimentos aparecem corretamente













