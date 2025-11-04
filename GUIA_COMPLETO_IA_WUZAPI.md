# 🤖 GUIA COMPLETO - IA + Wuzapi + n8n

## ✅ TUDO IMPLEMENTADO!

Este guia mostra como configurar e usar o sistema completo de IA com WhatsApp.

---

## 📦 O QUE FOI CRIADO

### **1. MCP Server** (`n8n-mcp-server/server.js`)
- ✅ `create_order` - Criar pedido completo com itens
- ✅ `get_fiado_customers` - Buscar clientes devedores
- ✅ Todas operações CRUD de produtos, ingredientes, categorias
- ✅ Operações financeiras completas
- ✅ 15 ferramentas disponíveis

### **2. WuzapiService** (`system/WuzapiService.php`)
- ✅ Enviar mensagens de texto
- ✅ Enviar mídia (imagens, documentos)
- ✅ Envio em massa com rate limiting
- ✅ Formatação automática de telefone
- ✅ Status da instância

### **3. Webhook Wuzapi** (`mvc/ajax/wuzapi_webhook.php`)
- ✅ Recebe mensagens do WhatsApp
- ✅ Identifica tenant/filial pela instância
- ✅ Cria cliente automaticamente se não existir
- ✅ Processa com IA (n8n + OpenAI)
- ✅ Envia resposta automática
- ✅ Log de conversas

### **4. Database** (`database/init/11_whatsapp_messages_table.sql`)
- ✅ Tabela de histórico de mensagens
- ✅ Indexes para performance
- ✅ Log de erros

### **5. Configuração** (`env.example`)
- ✅ Variáveis Wuzapi adicionadas
- ✅ Documentação inline

---

## 🔧 CONFIGURAÇÃO PASSO A PASSO

### **PASSO 1: Configurar Variáveis de Ambiente**

Edite seu `.env`:

```env
# AI Configuration
USE_N8N_AI=true
AI_N8N_WEBHOOK_URL=https://wapp.conext.click/webhook/ai-chat
AI_N8N_TIMEOUT=30

# MCP Server
MCP_API_KEY=sua-chave-secreta-super-segura
MCP_SERVER_URL=https://divinosys.conext.click:3100

# Wuzapi Configuration
WUZAPI_URL=https://sua-instancia.wuzapi.com
WUZAPI_TOKEN=seu-token-wuzapi
WUZAPI_INSTANCE_ID=sua-instancia-id
WUZAPI_TIMEOUT=30
```

---

### **PASSO 2: Iniciar MCP Server**

```bash
cd n8n-mcp-server
npm install
npm start
```

**Saída esperada:**
```
🚀 Divino Lanches MCP Server running on port 3100
🔒 Security enabled for write operations
📊 Health check: http://localhost:3100/health
🔧 Tools endpoint: http://localhost:3100/tools
```

**Testar:**
```bash
curl http://localhost:3100/health
curl http://localhost:3100/tools
```

---

### **PASSO 3: Configurar Wuzapi**

1. **Acesse seu painel Wuzapi**
2. **Vá em Configurações → Webhooks**
3. **Configure webhook URL:**
   ```
   https://divinosys.conext.click/mvc/ajax/wuzapi_webhook.php
   ```
4. **Eventos:** Marque "Mensagens Recebidas"
5. **Salvar**

---

### **PASSO 4: Cadastrar Instância WhatsApp no Sistema**

Execute no banco de dados:

```sql
-- Verificar se tabela whatsapp_instances existe
SELECT * FROM whatsapp_instances;

-- Se não existir, criar (normalmente já existe)
-- Se existir, inserir sua instância:

INSERT INTO whatsapp_instances (
    instance_id, 
    phone, 
    nome, 
    tenant_id, 
    filial_id, 
    ativo
) VALUES (
    'sua-instancia-id',           -- Do Wuzapi
    '5511999999999',               -- Número do WhatsApp Business
    'WhatsApp Divino Lanches',     -- Nome descritivo
    5,                             -- Seu tenant_id
    4,                             -- Sua filial_id
    true
);
```

---

### **PASSO 5: Configurar n8n Workflow**

No seu servidor n8n (`wapp.conext.click`), você precisa de um workflow que:

#### **Estrutura do Workflow:**

```
1. Webhook Trigger (/webhook/ai-chat)
   ↓
2. Extract Parameters
   ↓
3. AI Agent (OpenAI GPT-4o-mini)
   ↓
   Tools configuradas:
   - HTTP Request para MCP Server
   - Header: x-api-key: sua-chave-mcp
   - URL: https://divinosys.conext.click:3100/execute
   ↓
4. Format Response
   ↓
5. Respond to Webhook
```

#### **Configuração do AI Agent:**

**System Prompt:**
```
Você é um assistente virtual do Divino Lanches, especializado em:

1. PEDIDOS:
   - Receber pedidos via WhatsApp
   - Buscar produtos disponíveis
   - Criar pedidos completos com itens
   - Confirmar valores e formas de pagamento

2. CONSULTAS:
   - Status de pedidos
   - Cardápio e preços
   - Status de mesas
   - Ingredientes disponíveis

3. GESTÃO:
   - Criar/editar/excluir produtos
   - Gerenciar categorias
   - Lançamentos financeiros

4. COBRANÇA:
   - Listar clientes com dívidas
   - Enviar lembretes de pagamento

IMPORTANTE:
- Sempre confirme valores antes de finalizar pedido
- Seja cordial e profissional
- Use emojis para melhor comunicação
- Pergunte endereço para delivery
- Confirme mesa para pedidos presenciais

CONTEXTO:
- tenant_id: {{$json.tenant_id}}
- filial_id: {{$json.filial_id}}
- customer_phone: {{$json.customer_phone}}
```

**Tools configuradas para o Agent:**

```json
[
  {
    "name": "search_products",
    "description": "Search for products in the menu by name",
    "schema": {
      "type": "object",
      "properties": {
        "term": {"type": "string"},
        "limit": {"type": "number"}
      }
    }
  },
  {
    "name": "create_order",
    "description": "Create a new order with items",
    "schema": {
      "type": "object",
      "properties": {
        "cliente": {"type": "string"},
        "telefone_cliente": {"type": "string"},
        "tipo_entrega": {"type": "string", "enum": ["mesa", "delivery", "balcao"]},
        "itens": {
          "type": "array",
          "items": {
            "type": "object",
            "properties": {
              "produto_id": {"type": "number"},
              "quantidade": {"type": "number"},
              "observacao": {"type": "string"}
            }
          }
        },
        "forma_pagamento": {"type": "string"}
      }
    }
  },
  {
    "name": "get_fiado_customers",
    "description": "Get list of customers with pending debt",
    "schema": {
      "type": "object",
      "properties": {
        "limit": {"type": "number"},
        "min_value": {"type": "number"}
      }
    }
  }
]
```

---

## 🎯 CASOS DE USO

### **CASO 1: Cliente faz pedido via WhatsApp**

**Cliente:** "Oi, quero 2 X-Bacon sem cebola e 1 Coca-Cola 2L"

**Fluxo:**
1. Wuzapi recebe → Envia para webhook
2. Webhook identifica tenant/filial
3. n8n processa com AI Agent
4. AI Agent chama MCP:
   - `search_products` → Busca "X-Bacon"
   - `search_products` → Busca "Coca-Cola 2L"
   - `create_order` → Cria pedido
5. AI responde ao cliente
6. Wuzapi envia resposta

**IA responde:**
```
Pedido registrado! 🎉

✅ 2x X-Bacon (sem cebola) - R$ 31,80
✅ 1x Coca-Cola 2L - R$ 9,00

💰 Total: R$ 40,80

Será delivery ou retirada no balcão?
```

---

### **CASO 2: Cliente consulta cardápio**

**Cliente:** "Quais lanches vocês têm?"

**Fluxo:**
1. AI Agent chama `get_products` ou `get_categories`
2. Recebe lista de produtos
3. Formata resposta amigável

**IA responde:**
```
Nosso cardápio de lanches:

🍔 X-BURGER - R$ 12,90
🥓 X-BACON - R$ 15,90
🧀 X-TUDO - R$ 18,90
🌶️ X-PICANTE - R$ 14,90
🥗 X-SALADA - R$ 13,90

Qual você gostaria de pedir? 😊
```

---

### **CASO 3: Cobrança Automática (n8n Cron)**

**Workflow n8n separado:**

```
1. Schedule Trigger (Diário 9h)
   ↓
2. HTTP Request: GET /execute
   tool: get_fiado_customers
   parameters: {min_value: 10, limit: 50}
   ↓
3. Loop sobre cada cliente:
   ↓
   a. AI Agent: Gerar mensagem personalizada
      Input: {
        "cliente": "{{$item.nome}}",
        "valor": "{{$item.total_devedor}}",
        "quantidade_pedidos": "{{$item.quantidade_pedidos}}"
      }
      Prompt: "Gere uma mensagem de cobrança amigável"
   ↓
   b. Wuzapi: Enviar mensagem
      phone: {{$item.telefone}}
      message: {{$ai.output}}
   ↓
   c. Wait 2 seconds (rate limit)
   ↓
4. Log de envios
```

**Exemplo de mensagem gerada:**
```
Olá João! 😊

Passando para lembrar que você tem um saldo pendente de R$ 45,50 
referente aos seus últimos pedidos no Divino Lanches.

Você pode pagar via:
💳 PIX: 11999999999
💰 No estabelecimento

Qualquer dúvida, estamos à disposição! 🍔

Att,
Divino Lanches
```

---

## 📊 FERRAMENTAS MCP DISPONÍVEIS

### **LEITURA (8 ferramentas):**
| Ferramenta | Descrição |
|------------|-----------|
| `get_products` | Lista produtos com filtros |
| `search_products` | Busca produtos por nome |
| `get_product_details` | Detalhes de um produto |
| `get_ingredients` | Lista ingredientes |
| `get_categories` | Lista categorias |
| `get_orders` | Lista pedidos |
| `get_order_details` | Detalhes de pedido |
| `get_tables` | Lista mesas |
| `get_fiado_customers` | **NOVO** - Clientes devedores |

### **ESCRITA (10 ferramentas):**
| Ferramenta | Descrição |
|------------|-----------|
| `create_order` | **NOVO** - Criar pedido completo |
| `create_product` | Criar produto |
| `update_product` | Atualizar produto |
| `delete_product` | Excluir produto |
| `create_ingredient` | Criar ingrediente |
| `update_ingredient` | Atualizar ingrediente |
| `delete_ingredient` | Excluir ingrediente |
| `create_category` | Criar categoria |
| `update_category` | Atualizar categoria |
| `delete_category` | Excluir categoria |
| `create_financial_entry` | Criar lançamento financeiro |
| `update_order_status` | Atualizar status do pedido |
| `create_payment` | Registrar pagamento |

---

## 🧪 TESTANDO A INTEGRAÇÃO

### **Teste 1: MCP Server está rodando?**

```bash
curl http://localhost:3100/health

# Esperado:
{
  "status": "ok",
  "timestamp": "2025-11-04T...",
  "security": "enabled",
  "write_operations_protected": true
}
```

### **Teste 2: Listar ferramentas**

```bash
curl http://localhost:3100/tools

# Retorna array com 18 ferramentas
```

### **Teste 3: Buscar produtos (sem auth)**

```bash
curl -X POST http://localhost:3100/execute \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "search_products",
    "parameters": {"term": "bacon", "limit": 5},
    "context": {"tenant_id": 5, "filial_id": 4}
  }'
```

### **Teste 4: Criar pedido (com auth)**

```bash
curl -X POST http://localhost:3100/execute \
  -H "Content-Type: application/json" \
  -H "x-api-key: sua-chave-mcp" \
  -d '{
    "tool": "create_order",
    "parameters": {
      "cliente": "João Silva",
      "telefone_cliente": "11999999999",
      "tipo_entrega": "delivery",
      "endereco": "Rua das Flores, 123",
      "itens": [
        {"produto_id": 15, "quantidade": 2, "observacao": "Sem cebola"}
      ],
      "forma_pagamento": "PIX"
    },
    "context": {"tenant_id": 5, "filial_id": 4}
  }'
```

### **Teste 5: Buscar clientes fiado**

```bash
curl -X POST http://localhost:3100/execute \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "get_fiado_customers",
    "parameters": {"min_value": 10, "limit": 10},
    "context": {"tenant_id": 5, "filial_id": 4}
  }'
```

### **Teste 6: Wuzapi Status**

Crie arquivo `test_wuzapi.php`:
```php
<?php
require_once 'system/Config.php';
require_once 'system/WuzapiService.php';

$wuzapi = new \System\WuzapiService();

// Test 1: Get status
$status = $wuzapi->getStatus();
var_dump($status);

// Test 2: Send message
$result = $wuzapi->sendMessage('5511999999999', 'Teste de mensagem automática!');
var_dump($result);
```

Executar: `http://localhost:8080/test_wuzapi.php`

---

## 🔄 FLUXO COMPLETO DE PEDIDO

### **1. Cliente envia mensagem:**
```
"Oi, quero 2 X-Bacon sem cebola e 1 Coca 2L"
```

### **2. Wuzapi → Webhook:**
```json
POST /mvc/ajax/wuzapi_webhook.php
{
  "from": "5511999999999@c.us",
  "message": "Oi, quero 2 X-Bacon sem cebola e 1 Coca 2L",
  "instanceId": "sua-instancia-id",
  "type": "text"
}
```

### **3. Webhook → n8n:**
```json
POST https://wapp.conext.click/webhook/ai-chat
{
  "message": "Oi, quero 2 X-Bacon sem cebola e 1 Coca 2L",
  "tenant_id": 5,
  "filial_id": 4,
  "customer_phone": "5511999999999",
  "customer_name": "João Silva",
  "source": "whatsapp"
}
```

### **4. n8n → AI Agent:**

AI Agent analisa e chama MCP:

**Chamada 1: Buscar X-Bacon**
```json
POST http://divinosys.conext.click:3100/execute
{
  "tool": "search_products",
  "parameters": {"term": "X-Bacon", "limit": 5},
  "context": {"tenant_id": 5, "filial_id": 4}
}
```

**Resposta:**
```json
{
  "success": true,
  "products": [
    {"id": 15, "nome": "X-Bacon", "preco_normal": 15.90, ...}
  ]
}
```

**Chamada 2: Buscar Coca**
```json
{
  "tool": "search_products",
  "parameters": {"term": "Coca", "limit": 5}
}
```

**Chamada 3: Criar Pedido**
```json
{
  "tool": "create_order",
  "parameters": {
    "cliente": "João Silva",
    "telefone_cliente": "5511999999999",
    "tipo_entrega": "delivery",
    "itens": [
      {"produto_id": 15, "quantidade": 2, "observacao": "Sem cebola"},
      {"produto_id": 42, "quantidade": 1}
    ],
    "forma_pagamento": "A combinar"
  },
  "context": {"tenant_id": 5, "filial_id": 4}
}
```

**Resposta MCP:**
```json
{
  "success": true,
  "message": "Pedido criado com sucesso!",
  "order": {
    "id": 156,
    "valor_total": 40.80,
    "quantidade_itens": 2,
    "itens": [...]
  }
}
```

### **5. AI Agent → n8n:**
```
Pedido registrado! 🎉

✅ 2x X-Bacon (sem cebola) - R$ 31,80
✅ 1x Coca-Cola 2L - R$ 9,00

💰 Total: R$ 40,80

Qual o endereço para entrega? 📍
```

### **6. n8n → Webhook Response:**
```json
{
  "success": true,
  "message": "Pedido registrado! 🎉\n\n✅ 2x X-Bacon...",
  "order_id": 156
}
```

### **7. Webhook → Wuzapi:**
```
wuzapi->sendMessage("5511999999999@c.us", "Pedido registrado!...")
```

### **8. Cliente recebe no WhatsApp! ✅**

---

## 🔐 SEGURANÇA

### **Autenticação:**
- ✅ Operações de escrita exigem API Key
- ✅ Validação de tenant_id em todas operações
- ✅ Prepared statements (SQL injection protection)
- ✅ CORS configurado

### **Logs:**
- ✅ Todas mensagens WhatsApp logadas
- ✅ Erros de processamento registrados
- ✅ Histórico completo para auditoria

---

## 📞 CONFIGURAÇÃO WUZAPI (Detalhes)

### **Onde obter as credenciais:**

1. **WUZAPI_URL:** URL da sua instância Wuzapi
   - Exemplo: `https://api.wuzapi.com` ou IP do servidor

2. **WUZAPI_TOKEN:** Token de autenticação
   - Geralmente obtido no painel Wuzapi
   - Ou gerado via API

3. **WUZAPI_INSTANCE_ID:** ID da instância WhatsApp
   - Encontrado no painel Wuzapi
   - Formato: alfanumérico

### **Formato do telefone:**

O sistema aceita:
- `11999999999` → Converte para `5511999999999@c.us`
- `5511999999999` → Adiciona `@c.us`
- `+55 11 99999-9999` → Remove caracteres, converte

---

## 📈 PRÓXIMOS PASSOS SUGERIDOS

### **1. Dashboard de IA**
- [ ] Página para visualizar conversas
- [ ] Métricas de uso da IA
- [ ] Taxa de conversão de pedidos

### **2. Melhorias de IA**
- [ ] Histórico de conversas por cliente
- [ ] Sugestões baseadas em pedidos anteriores
- [ ] Upsell automático

### **3. Automações**
- [ ] Cobrança automática diária
- [ ] Confirmação de pedidos
- [ ] Status de entrega

---

## 🆘 TROUBLESHOOTING

### **Erro: "jQuery is not defined"**
✅ **JÁ CORRIGIDO** - jQuery adicionado em `financeiro.php`

### **Erro: "WUZAPI_URL not configured"**
- Verifique se `.env` está configurado
- Reinicie o container após mudar `.env`

### **Erro: "Instance not found"**
- Execute o INSERT em `whatsapp_instances`
- Verifique se `instance_id` está correto

### **Mensagens não chegam:**
- Verifique webhook configurado no Wuzapi
- Teste endpoint: `curl http://seu-dominio/mvc/ajax/wuzapi_webhook.php`
- Veja logs: `tail -f /var/log/php-errors.log`

---

## ✅ CHECKLIST FINAL

- [x] MCP Server implementado
- [x] create_order funcionando
- [x] get_fiado_customers funcionando
- [x] WuzapiService criado
- [x] Webhook Wuzapi criado
- [x] Tabela whatsapp_messages
- [x] Variáveis de ambiente documentadas
- [x] N8nAIService atualizado
- [x] Autenticação configurada
- [x] Logs implementados

---

**TUDO PRONTO PARA USO!** 🚀

Configure as variáveis de ambiente e coloque para rodar!

