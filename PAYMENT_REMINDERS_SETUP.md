# 🔔 Configuração de Lembretes de Pagamento

## 📋 Visão Geral

O sistema de lembretes de pagamento envia automaticamente uma mensagem WhatsApp para o cliente 10 minutos após a criação do pedido, caso o pagamento ainda não tenha sido concluído.

## 🔧 Como Funciona

### 1. **Agendamento Automático**
Quando um pedido é criado com pagamento online:
- Sistema envia mensagem inicial com fatura e código PIX
- Sistema agenda automaticamente um lembrete para 10 minutos depois
- Lembrete é salvo na tabela `payment_reminders` com status `pending`

### 2. **Processamento de Lembretes**

O sistema processa lembretes de duas formas:

#### **Opção A: Processamento Automático (Recomendado)**
- **Cron Job no Docker**: Executa a cada 2 minutos automaticamente
- Configurado no `docker/start.sh` durante a inicialização do container
- Não requer configuração adicional

#### **Opção B: Processamento via Requisições**
- Processa lembretes quando há requisições à aplicação
- Usa sistema de lock file para evitar processamento excessivo
- Executa no máximo a cada 2 minutos

### 3. **Verificação de Status**

Antes de enviar o lembrete, o sistema:
- Verifica se o pagamento já foi concluído
- Se já foi pago, cancela o lembrete automaticamente
- Se não foi pago, envia a mensagem de lembrete

## 🚀 Instalação

### Passo 1: Verificar Tabela

Certifique-se de que a tabela `payment_reminders` existe:

```sql
SELECT * FROM payment_reminders LIMIT 1;
```

Se não existir, execute a migration:
```bash
php database/migrations/run_payment_reminders_migration.php
```

### Passo 2: Reconstruir Container Docker (se necessário)

Se você adicionou o cron job recentemente, reconstrua o container:

```bash
docker-compose down
docker-compose build
docker-compose up -d
```

### Passo 3: Verificar Cron Job

Verifique se o cron está rodando no container:

```bash
docker exec -it divino-lanches-app crontab -l
```

Deve mostrar:
```
*/2 * * * * curl -s http://localhost/mvc/ajax/process_payment_reminders.php > /dev/null 2>&1
```

## 🧪 Teste Manual

### Testar Endpoint Diretamente

```bash
curl http://localhost:8080/mvc/ajax/process_payment_reminders.php
```

Resposta esperada:
```json
{
  "success": true,
  "processed": 0,
  "failed": 0,
  "total": 0,
  "message": "Nenhum lembrete pendente"
}
```

### Verificar Lembretes no Banco

```sql
SELECT 
    id,
    pedido_id,
    cliente_nome,
    cliente_telefone,
    scheduled_for,
    status,
    sent_at,
    error_message
FROM payment_reminders
ORDER BY scheduled_for DESC
LIMIT 10;
```

## 📊 Monitoramento

### Logs do Sistema

Os logs são salvos automaticamente. Verifique:

```bash
# Logs do container
docker logs divino-lanches-app --tail 50 | grep -i "reminder\|PaymentNotification"

# Logs do PHP
tail -f logs/error.log | grep -i "reminder\|PaymentNotification"
```

### Verificar Status dos Lembretes

```sql
SELECT 
    status,
    COUNT(*) as total,
    COUNT(CASE WHEN sent_at IS NOT NULL THEN 1 END) as enviados,
    COUNT(CASE WHEN error_message IS NOT NULL THEN 1 END) as com_erro
FROM payment_reminders
GROUP BY status;
```

## 🔍 Troubleshooting

### Lembretes não estão sendo enviados

1. **Verificar se há lembretes pendentes:**
   ```sql
   SELECT * FROM payment_reminders 
   WHERE status = 'pending' 
   AND scheduled_for <= NOW();
   ```

2. **Verificar se o cron está rodando:**
   ```bash
   docker exec -it divino-lanches-app service cron status
   ```

3. **Testar endpoint manualmente:**
   ```bash
   curl http://localhost:8080/mvc/ajax/process_payment_reminders.php
   ```

4. **Verificar logs de erro:**
   ```bash
   docker logs divino-lanches-app 2>&1 | grep -i "reminder\|error"
   ```

### Cron não está funcionando

Se o cron não estiver funcionando, você pode:

1. **Processar manualmente via requisições** (já implementado no `index.php`)
2. **Usar um serviço externo** (n8n, EasyCron, etc.) para chamar o endpoint
3. **Configurar cron no host** (fora do Docker):
   ```bash
   */2 * * * * curl -s http://localhost:8080/mvc/ajax/process_payment_reminders.php > /dev/null 2>&1
   ```

## 📝 Mensagem de Lembrete

A mensagem enviada inclui:
- Texto motivacional ("Falta pouco para concluir seu pedido!")
- Nome do cliente
- Número do pedido
- Valor do pedido
- Código PIX (se disponível)
- Link da fatura
- Instruções para finalizar o pagamento

## ⚙️ Configuração Avançada

### Alterar Intervalo de Lembrete

Edite `system/WhatsApp/PaymentNotificationService.php`:

```php
// Linha 189 - Alterar de 10 minutos para outro valor
$scheduledFor = date('Y-m-d H:i:s', strtotime('+15 minutes')); // 15 minutos
```

### Alterar Frequência do Cron

Edite `docker/start.sh`:

```bash
# Alterar de */2 (a cada 2 minutos) para */5 (a cada 5 minutos)
echo "*/5 * * * * curl -s http://localhost/mvc/ajax/process_payment_reminders.php > /dev/null 2>&1" | crontab -
```

## ✅ Checklist de Verificação

- [ ] Tabela `payment_reminders` existe
- [ ] Cron job está configurado no Docker
- [ ] Endpoint `process_payment_reminders.php` está acessível
- [ ] Instância WhatsApp está ativa e conectada
- [ ] Logs estão sendo gerados corretamente
- [ ] Teste manual funcionou

## 📞 Suporte

Se os lembretes não estiverem funcionando:
1. Verifique os logs do sistema
2. Teste o endpoint manualmente
3. Verifique se há lembretes pendentes no banco
4. Confirme que a instância WhatsApp está ativa

