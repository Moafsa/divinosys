# 📱 Sistema de Notificações WhatsApp para Pagamentos

## 📋 Visão Geral

Este sistema envia automaticamente mensagens WhatsApp para clientes quando uma fatura é gerada no Asaas, incluindo:
- Link da fatura
- Código PIX copia e cola (se pagamento via PIX)
- Instruções para finalizar o pagamento
- Lembrete automático após 10 minutos se o pagamento não foi concluído

## 🏗️ Arquitetura

```
┌─────────────────────┐
│  Pedido Criado      │
│  (pedidos_online.php)│
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Fatura Gerada      │
│  (Asaas)            │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  PaymentNotification│
│  Service            │
└──────────┬──────────┘
           │
           ├─────────────────┐
           │                 │
           ▼                 ▼
┌──────────────────┐  ┌──────────────────┐
│  Enviar Mensagem │  │  Agendar Lembrete│
│  Inicial         │  │  (10 minutos)    │
└──────────────────┘  └────────┬─────────┘
                                │
                                ▼
                    ┌──────────────────────┐
                    │  Cron Job            │
                    │  (process_payment_    │
                    │   reminders.php)     │
                    └──────────┬───────────┘
                               │
                               ▼
                    ┌──────────────────────┐
                    │  Verificar Status    │
                    │  Enviar Lembrete     │
                    └──────────────────────┘
```

## 📦 Componentes

### 1. **Tabela `payment_reminders`**
Armazena lembretes agendados de pagamento.

**Campos principais:**
- `pedido_id`: ID do pedido
- `asaas_payment_id`: ID do pagamento no Asaas
- `cliente_telefone`: Telefone do cliente
- `payment_url`: URL da fatura
- `pix_copy_paste`: Código PIX (se disponível)
- `billing_type`: Tipo de pagamento (PIX, CREDIT_CARD, BOLETO)
- `reminder_type`: Tipo de lembrete (initial, followup)
- `scheduled_for`: Data/hora agendada para envio
- `status`: Status (pending, sent, cancelled, failed)

### 2. **PaymentNotificationService**
Serviço principal para envio de notificações.

**Métodos principais:**
- `sendPaymentNotification()`: Envia mensagem inicial com fatura
- `processScheduledReminders()`: Processa lembretes agendados
- `scheduleReminder()`: Agenda lembrete para 10 minutos depois

### 3. **Integração no pedidos_online.php**
Após criar o pagamento no Asaas, o sistema automaticamente:
1. Envia mensagem WhatsApp com fatura e código PIX
2. Agenda lembrete para 10 minutos depois

### 4. **Endpoint de Processamento**
`mvc/ajax/process_payment_reminders.php` - Processa lembretes agendados.

## 🚀 Instalação

### Passo 1: Executar Migration

Execute a migration para criar a tabela:

```bash
php database/migrations/run_payment_reminders_migration.php
```

Ou execute o SQL diretamente:

```bash
psql -U seu_usuario -d seu_banco -f database/migrations/create_payment_reminders_table.sql
```

### Passo 2: Configurar Cron Job

Configure um cron job para processar lembretes a cada 1-2 minutos:

```bash
# Editar crontab
crontab -e

# Adicionar linha (executa a cada 2 minutos)
*/2 * * * * curl -s http://localhost:8080/mvc/ajax/process_payment_reminders.php > /dev/null 2>&1
```

**Para Docker:**
Adicione no `docker-compose.yml`:

```yaml
services:
  app:
    # ... outras configurações
    command: >
      sh -c "
        # Iniciar cron para processar lembretes
        echo '*/2 * * * * curl -s http://localhost:8080/mvc/ajax/process_payment_reminders.php > /dev/null 2>&1' | crontab -
        crond -f &
        # Iniciar aplicação PHP
        php-fpm
      "
```

**Alternativa sem cron:**
Você pode chamar o endpoint manualmente ou usar um serviço externo (como n8n) para fazer requisições periódicas.

## 📝 Fluxo de Funcionamento

### 1. **Criação do Pedido com Pagamento Online**

Quando um cliente cria um pedido com pagamento online:

1. Sistema cria pagamento no Asaas
2. Sistema cria pedido no banco de dados
3. **NOVO:** Sistema envia mensagem WhatsApp automaticamente com:
   - Informações do pedido
   - Link da fatura
   - Código PIX copia e cola (se PIX)
   - Instruções de pagamento
4. **NOVO:** Sistema agenda lembrete para 10 minutos depois

### 2. **Processamento de Lembretes**

O cron job executa a cada 2 minutos:

1. Busca lembretes pendentes com `scheduled_for <= NOW()`
2. Para cada lembrete:
   - Verifica se o pagamento já foi concluído
   - Se já foi pago, cancela o lembrete
   - Se não foi pago, envia mensagem de lembrete
   - Atualiza status do lembrete

### 3. **Mensagem de Lembrete**

A mensagem de lembrete inclui:
- Texto motivacional ("Falta pouco para concluir seu pedido!")
- Informações do pedido
- Código PIX (se disponível)
- Link da fatura
- Call-to-action para finalizar pagamento

## 🔧 Configuração

### Requisitos

1. **Instância WhatsApp configurada**
   - Acesse: Configurações > WhatsApp - WuzAPI
   - Crie uma instância ativa
   - A instância deve estar com status "connected" ou "open"

2. **Integração Asaas configurada**
   - API Key do Asaas configurada
   - Webhook configurado (opcional, para atualização automática de status)

### Verificação

Para verificar se está funcionando:

1. **Criar um pedido online** com pagamento PIX
2. **Verificar logs** do PHP:
   ```bash
   tail -f /var/log/php/error.log | grep PaymentNotificationService
   ```
3. **Verificar tabela** `payment_reminders`:
   ```sql
   SELECT * FROM payment_reminders ORDER BY created_at DESC LIMIT 10;
   ```

## 📊 Monitoramento

### Verificar Lembretes Pendentes

```sql
SELECT 
    pr.id,
    pr.pedido_id,
    pr.cliente_nome,
    pr.valor_total,
    pr.scheduled_for,
    pr.status,
    p.status_pagamento
FROM payment_reminders pr
JOIN pedido p ON p.id = pr.pedido_id
WHERE pr.status = 'pending'
ORDER BY pr.scheduled_for ASC;
```

### Verificar Lembretes Enviados

```sql
SELECT 
    pr.id,
    pr.pedido_id,
    pr.cliente_nome,
    pr.sent_at,
    pr.status,
    p.status_pagamento
FROM payment_reminders pr
JOIN pedido p ON p.id = pr.pedido_id
WHERE pr.status = 'sent'
ORDER BY pr.sent_at DESC
LIMIT 20;
```

### Verificar Falhas

```sql
SELECT 
    pr.id,
    pr.pedido_id,
    pr.cliente_nome,
    pr.error_message,
    pr.status,
    pr.updated_at
FROM payment_reminders pr
WHERE pr.status = 'failed'
ORDER BY pr.updated_at DESC
LIMIT 20;
```

## 🐛 Troubleshooting

### Mensagem não está sendo enviada

1. **Verificar instância WhatsApp:**
   ```sql
   SELECT * FROM whatsapp_instances 
   WHERE tenant_id = ? AND status IN ('open', 'connected') AND ativo = true;
   ```

2. **Verificar logs:**
   ```bash
   tail -f /var/log/php/error.log | grep PaymentNotificationService
   ```

3. **Verificar se telefone está correto:**
   - Telefone deve estar no formato correto (com DDD)
   - Verificar se há instância WhatsApp ativa para o tenant/filial

### Lembrete não está sendo enviado

1. **Verificar cron job:**
   ```bash
   crontab -l
   ```

2. **Testar endpoint manualmente:**
   ```bash
   curl http://localhost:8080/mvc/ajax/process_payment_reminders.php
   ```

3. **Verificar lembretes pendentes:**
   ```sql
   SELECT * FROM payment_reminders 
   WHERE status = 'pending' AND scheduled_for <= NOW();
   ```

### Pagamento já foi pago mas lembrete ainda foi enviado

O sistema verifica o status antes de enviar, mas pode haver delay. Isso é normal e o lembrete será cancelado automaticamente na próxima verificação.

## 📈 Melhorias Futuras

- [ ] Adicionar mais tipos de lembretes (30 min, 1 hora)
- [ ] Personalizar mensagens por filial
- [ ] Adicionar métricas de conversão
- [ ] Dashboard de lembretes enviados
- [ ] Suporte a múltiplos idiomas

## 📞 Suporte

Em caso de problemas, verifique:
1. Logs do PHP (`error_log`)
2. Status da instância WhatsApp
3. Configuração do Asaas
4. Tabela `payment_reminders` para ver status dos lembretes

