# Sistema de Lembretes de Reservas

## 📋 Descrição

Sistema automático que envia mensagens via WhatsApp às 8h da manhã no dia da reserva, perguntando se está tudo certo para a reserva confirmada.

## 🚀 Configuração

### 1. Executar Migration

Primeiro, execute a migration para adicionar o campo `lembrete_enviado` à tabela `reservas`:

```bash
php run_reservas_lembrete_migration.php
```

### 2. Configurar Cron Job

O script `process_reservation_reminders.php` deve ser executado periodicamente. Existem duas opções:

#### Opção 1: Executar exatamente às 8h (Recomendado)
```bash
0 8 * * * curl -s http://localhost:8080/mvc/ajax/process_reservation_reminders.php > /dev/null 2>&1
```

#### Opção 2: Executar a cada 10 minutos (verifica se é 8h)
```bash
*/10 * * * * curl -s http://localhost:8080/mvc/ajax/process_reservation_reminders.php > /dev/null 2>&1
```

**Nota:** Ajuste a URL `http://localhost:8080` para o domínio do seu servidor.

### 3. Configurar Crontab

Para editar o crontab no Linux:
```bash
crontab -e
```

Adicione uma das linhas acima e salve.

## ⚙️ Como Funciona

1. **Horário de Execução**: O script verifica se está entre 7:45 e 8:15 (janela de 30 minutos)
2. **Busca Reservas**: Busca todas as reservas confirmadas para o dia atual que ainda não receberam lembrete
3. **Envia Mensagens**: Para cada reserva encontrada:
   - Busca instância WhatsApp ativa do tenant/filial
   - Envia mensagem personalizada com dados da reserva
   - Marca `lembrete_enviado = true` após envio bem-sucedido

## 📱 Mensagem Enviada

```
👋 *Bom dia!*

Olá [Nome do Cliente],

Hoje é o dia da sua reserva! 🎉

📅 *Data:* [DD/MM/AAAA]
🕐 *Hora:* [HH:MM]
👥 *Convidados:* [Número]

Está tudo certo para sua reserva? Confirme se conseguirá comparecer.

Aguardamos você! 🍽️
```

## 🔄 Reset Automático

Quando uma reserva é confirmada, o campo `lembrete_enviado` é automaticamente resetado para `false`, permitindo que o lembrete seja enviado no dia da reserva mesmo que a reserva tenha sido confirmada após a criação.

## 📊 Logs

O sistema registra logs detalhados:
- Sucesso: `RESERVAS_LEMBRETE - Lembrete enviado para reserva #X`
- Erros: `RESERVAS_LEMBRETE - Erro ao enviar lembrete para reserva #X`

## 🧪 Teste Manual

Para testar manualmente (fora do horário de 8h), você pode:

1. Ajustar temporariamente a verificação de horário no código
2. Ou chamar diretamente via curl:
```bash
curl http://localhost:8080/mvc/ajax/process_reservation_reminders.php
```

## ⚠️ Requisitos

- Instância WhatsApp ativa configurada no sistema
- Campo `lembrete_enviado` adicionado à tabela `reservas`
- Cron job configurado e funcionando
- Reservas com status `confirmada` e telefone válido

## 🔍 Troubleshooting

### Lembretes não estão sendo enviados

1. Verifique se o cron job está rodando: `crontab -l`
2. Verifique os logs do PHP para erros
3. Confirme que há reservas confirmadas para hoje
4. Verifique se há instância WhatsApp ativa
5. Teste manualmente chamando o endpoint

### Mensagens duplicadas

O sistema evita duplicatas verificando o campo `lembrete_enviado`. Se houver duplicatas, verifique:
- Se o campo está sendo atualizado corretamente
- Se há múltiplos cron jobs configurados













