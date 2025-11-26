# 🔧 Correção de Timezone - Sistema de Horários

## ❌ Problema Identificado

O sistema estava usando o horário do servidor (UTC ou outro timezone) ao invés do horário do estabelecimento. Isso causava problemas como:
- Por volta das 21h-22h, o sistema mudava de dia como se fosse meia-noite
- Pedidos criados com data/hora incorretas
- Verificação de horário de funcionamento incorreta

## ✅ Solução Implementada

### 1. Migration de Banco de Dados
**Arquivo:** `database/migrations/add_timezone_to_filiais.sql`

Adiciona o campo `timezone` na tabela `filiais` com valor padrão `'America/Sao_Paulo'`.

### 2. Classe Helper TimeHelper
**Arquivo:** `system/TimeHelper.php`

Classe helper que fornece métodos para trabalhar com timezone do estabelecimento:
- `getFilialTimezone($filialId)` - Obtém o timezone da filial
- `now($format, $filialId)` - Data/hora atual no timezone do estabelecimento
- `today($filialId)` - Data atual (Y-m-d)
- `currentTime($filialId)` - Hora atual (H:i:s)
- `currentHour($filialId)` - Hora atual (H:i)
- `currentDayName($filialId)` - Nome do dia em português

### 3. Arquivos Atualizados

#### ✅ `mvc/views/cardapio_online.php`
- Verificação de horário de funcionamento agora usa `TimeHelper::currentDayName()` e `TimeHelper::currentHour()`

#### ✅ `mvc/ajax/pedidos_online.php`
- Data e hora do pedido agora usam `TimeHelper::today()` e `TimeHelper::currentTime()`
- Timestamps de criação/atualização também atualizados

#### ✅ `mvc/ajax/pedidos.php`
- Criação de pedidos agora usa timezone do estabelecimento

#### ✅ `mvc/views/pedidos.php`
- Filtro de pedidos de hoje agora usa `TimeHelper::today()`

## 📋 Como Aplicar

### Passo 1: Executar a Migration

Execute a migration SQL no banco de dados. Você pode:

**Opção A - Via script PHP:**
```bash
php run_timezone_migration.php
```

**Opção B - Via SQL direto:**
Execute o conteúdo de `database/migrations/add_timezone_to_filiais.sql` no seu banco de dados.

**Opção C - Manualmente:**
```sql
ALTER TABLE filiais 
ADD COLUMN IF NOT EXISTS timezone VARCHAR(50) DEFAULT 'America/Sao_Paulo';

UPDATE filiais SET timezone = 'America/Sao_Paulo' WHERE timezone IS NULL;
```

### Passo 2: Configurar Timezone por Filial (Opcional)

Se você tem filiais em diferentes fusos horários, atualize o campo `timezone` na tabela `filiais`:

```sql
-- Exemplo: Filial em Manaus
UPDATE filiais SET timezone = 'America/Manaus' WHERE id = 2;

-- Exemplo: Filial em Brasília
UPDATE filiais SET timezone = 'America/Sao_Paulo' WHERE id = 1;
```

### Passo 3: Verificar Funcionamento

1. Acesse o cardápio online e verifique se o horário de funcionamento está correto
2. Crie um pedido e verifique se a data/hora estão corretas
3. Verifique se após as 21h-22h o sistema não muda de dia prematuramente

## 🎯 Timezones Suportados

O sistema suporta qualquer timezone válido do PHP. Exemplos comuns no Brasil:

- `America/Sao_Paulo` - Brasília, São Paulo, Rio de Janeiro (UTC-3)
- `America/Manaus` - Manaus (UTC-4)
- `America/Fortaleza` - Fortaleza, Recife (UTC-3)
- `America/Campo_Grande` - Campo Grande (UTC-4)

## 📝 Notas Importantes

1. **Valor Padrão:** Se uma filial não tiver timezone configurado, o sistema usa `America/Sao_Paulo` como padrão.

2. **Compatibilidade:** O sistema mantém compatibilidade com código antigo que usa `date()`, mas para operações relacionadas a pedidos e horários de funcionamento, sempre use `TimeHelper`.

3. **Performance:** A classe `TimeHelper` faz cache do timezone da filial durante a execução da requisição.

## 🔍 Verificação

Para verificar se está funcionando corretamente:

```php
// Em qualquer arquivo PHP após carregar o sistema
$filialId = 1; // ID da sua filial
echo "Timezone: " . \System\TimeHelper::getFilialTimezone($filialId) . "\n";
echo "Data/Hora Atual: " . \System\TimeHelper::now('Y-m-d H:i:s', $filialId) . "\n";
echo "Dia da Semana: " . \System\TimeHelper::currentDayName($filialId) . "\n";
```

## ⚠️ Importante

Após executar a migration, **reinicie o servidor web** (se aplicável) para garantir que as mudanças sejam aplicadas.

