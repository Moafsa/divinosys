# 🚀 Ativar Cardápio Online - Guia Rápido

## ✅ Implementação Completa

O sistema de cardápio online foi totalmente implementado e está pronto para uso!

## 📋 Passos para Ativar

### 1. Executar Migrations (Automático)

As migrations serão executadas automaticamente na próxima inicialização do sistema via `database_migrate.php`.

**OU execute manualmente:**

```bash
# Via Docker
docker exec -it divino-lanches-app php database_migrate.php

# Ou conecte-se ao PostgreSQL e execute:
psql -U postgres -d divino_lanches -f database/migrations/create_cardapio_online_fields.sql
psql -U postgres -d divino_lanches -f database/migrations/add_asaas_payment_fields_pedido.sql
```

### 2. Ativar Cardápio Online em uma Filial

Execute no banco de dados:

```sql
-- Ativar cardápio online para uma filial específica
UPDATE filiais 
SET 
    cardapio_online_ativo = true,
    taxa_delivery_fixa = 5.00,                    -- Taxa fixa (se não usar cálculo)
    usar_calculo_distancia = false,               -- false = taxa fixa, true = cálculo n8n
    n8n_webhook_distancia = '',                  -- URL do webhook n8n (se usar cálculo)
    raio_entrega_km = 10.00,                     -- Raio máximo de entrega em km
    tempo_medio_preparo = 30,                     -- Tempo médio em minutos
    aceita_pagamento_online = true,               -- Aceita pagamento online via Asaas
    aceita_pagamento_na_hora = true               -- Aceita pagamento na hora
WHERE id = 1;  -- Substitua pelo ID da sua filial
```

### 3. Configurar n8n Webhook (Opcional - apenas se usar cálculo de distância)

Se você quiser usar cálculo automático de distância via n8n:

1. Crie um workflow no n8n com webhook
2. Configure para receber:
   ```json
   {
       "endereco_estabelecimento": "Rua Exemplo, 123",
       "endereco_cliente": "Rua Cliente, 456"
   }
   ```
3. Retorne:
   ```json
   {
       "success": true,
       "distancia": 5.2,
       "valor": 8.50
   }
   ```
4. Atualize a filial:
   ```sql
   UPDATE filiais 
   SET usar_calculo_distancia = true,
       n8n_webhook_distancia = 'https://seu-n8n.com/webhook/calcular-distancia'
   WHERE id = 1;
   ```

### 4. Acessar o Cardápio Online

URL de acesso:
```
http://seudominio.com/index.php?view=cardapio_online&tenant=1&filial=1
```

Substitua:
- `tenant=1` pelo ID do seu tenant
- `filial=1` pelo ID da sua filial

## 🎯 Funcionalidades Disponíveis

✅ Cardápio público responsivo  
✅ Carrinho de compras com localStorage  
✅ Retirada no balcão (sem taxa)  
✅ Delivery (com taxa fixa ou calculada)  
✅ Cálculo de distância via n8n (opcional)  
✅ Pagamento online via Asaas (PIX)  
✅ Pagamento na hora  
✅ Pedidos aparecem automaticamente na tela de pedidos  

## 📝 Verificar se Está Funcionando

1. **Verificar colunas criadas:**
   ```sql
   SELECT column_name 
   FROM information_schema.columns 
   WHERE table_name = 'filiais' 
   AND column_name LIKE '%cardapio%' OR column_name LIKE '%delivery%';
   ```

2. **Verificar filial ativa:**
   ```sql
   SELECT id, nome, cardapio_online_ativo 
   FROM filiais 
   WHERE cardapio_online_ativo = true;
   ```

3. **Testar acesso:**
   - Acesse a URL do cardápio
   - Deve exibir produtos da filial
   - Deve permitir adicionar ao carrinho
   - Deve permitir finalizar pedido

## 🔧 Troubleshooting

### Cardápio não aparece
- Verifique se `cardapio_online_ativo = true` na filial
- Verifique se a filial tem produtos ativos
- Verifique se `status = 'ativo'` na filial

### Erro ao calcular distância
- Verifique se o webhook n8n está configurado corretamente
- Verifique se a URL está acessível
- Verifique logs do servidor

### Erro ao processar pagamento
- Verifique se Asaas está configurado na filial/tenant
- Verifique se `aceita_pagamento_online = true`
- Verifique logs do servidor

## 📚 Documentação Completa

Veja `CARDAPIO_ONLINE_IMPLEMENTACAO.md` para documentação técnica detalhada.

