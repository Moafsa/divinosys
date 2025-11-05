# 🤖 STATUS ATUAL DA INTEGRAÇÃO IA - Divino Lanches

## 📊 RESUMO EXECUTIVO

**Estado:** ✅ **80% IMPLEMENTADO**
- ✅ MCP Server funcionando
- ✅ Operações de leitura (100%)
- ✅ Operações de escrita básicas (60%)
- ❌ Create Order completo (faltando)
- ❌ Cobrança WhatsApp (faltando)
- ❌ Integração Wuzapi (faltando)

---

## 🏗️ ARQUITETURA ATUAL

```
┌──────────────────┐
│   WhatsApp       │  Cliente envia mensagem
│   (Wuzapi)       │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│   n8n Workflow   │  Processa conversa
│  (Seu Servidor)  │  - Classifica intenção
└────────┬─────────┘  - Decide ação
         │
         ▼
┌──────────────────┐
│   AI Agent       │  OpenAI GPT-4o-mini
│   (OpenAI)       │  - Entende contexto
└────────┬─────────┘  - Monta chamadas
         │
         ├─────────────────────────────┐
         │                             │
         ▼                             ▼
┌──────────────────┐         ┌────────────────┐
│   MCP Server     │         │  Wuzapi API    │
│  (Node.js 3100)  │         │  (WhatsApp)    │
└────────┬─────────┘         └────────────────┘
         │
         ▼
┌──────────────────┐
│   PostgreSQL     │  Banco de dados
│  Divino Lanches  │
└──────────────────┘
```

---

## ✅ O QUE JÁ ESTÁ FUNCIONANDO

### 1. **MCP Server (Node.js - Porta 3100)**

**Localização:** `n8n-mcp-server/server.js`

#### **Operações de Leitura (100% ✅)**

| Ferramenta | O que faz | Status |
|------------|-----------|--------|
| `get_products` | Lista produtos com filtros | ✅ |
| `search_products` | Busca produtos por nome | ✅ |
| `get_product_details` | Detalhes completos de produto | ✅ |
| `get_ingredients` | Lista ingredientes por tipo | ✅ |
| `get_categories` | Lista todas categorias | ✅ |
| `get_orders` | Lista pedidos com filtros | ✅ |
| `get_order_details` | Detalhes completos do pedido | ✅ |
| `get_tables` | Lista mesas com status | ✅ |

#### **Operações de Escrita (60% ✅)**

| Ferramenta | O que faz | Status | Observação |
|------------|-----------|--------|------------|
| **PRODUTOS** |
| `create_product` | Criar produto | ✅ | Funcionando |
| `update_product` | Atualizar produto | ✅ | Funcionando |
| `delete_product` | Excluir produto | ✅ | Soft delete (disponivel=false) |
| **INGREDIENTES** |
| `create_ingredient` | Criar ingrediente | ✅ | Funcionando |
| `update_ingredient` | Atualizar ingrediente | ✅ | Funcionando |
| `delete_ingredient` | Excluir ingrediente | ✅ | Soft delete |
| **CATEGORIAS** |
| `create_category` | Criar categoria | ✅ | Funcionando |
| `update_category` | Atualizar categoria | ✅ | Funcionando |
| `delete_category` | Excluir categoria | ✅ | Soft delete |
| **FINANCEIRO** |
| `create_financial_entry` | Criar lançamento | ✅ | Funcionando |
| **PEDIDOS** |
| `update_order_status` | Atualizar status | ✅ | Funcionando |
| `create_payment` | Registrar pagamento | ✅ | Funcionando |
| `create_order` | **CRIAR PEDIDO COMPLETO** | ❌ | **FALTANDO!** |

---

### 2. **N8nAIService (PHP)**

**Localização:** `system/N8nAIService.php`

**Status:** ✅ Funcionando

**Funcionalidades:**
- ✅ Envio de mensagens para n8n
- ✅ Processamento de arquivos (base64)
- ✅ Context injection (tenant_id, filial_id)
- ✅ Fallback para OpenAI direto
- ✅ Tratamento de erros

---

### 3. **Autenticação e Segurança**

**Status:** ✅ Implementado

**Mecanismos:**
- ✅ API Key obrigatória para operações de escrita
- ✅ Validação de tenant_id em todas operações
- ✅ Prepared statements (SQL injection protection)
- ✅ Middleware de autenticação no MCP

---

## ❌ O QUE ESTÁ FALTANDO

### 1. **CREATE ORDER COMPLETO** ⚠️ **CRÍTICO**

**Problema:** A função `create_order` não está implementada no MCP Server!

**O que precisa:**

```javascript
async function createOrder(params, tenantId, filialId) {
  const { 
    cliente, 
    telefone_cliente, 
    tipo_entrega, // 'mesa', 'delivery', 'balcao'
    mesa_id,      // se tipo = mesa
    endereco,     // se tipo = delivery
    itens,        // array: [{ produto_id, quantidade, observacao, ingredientes_adicionais: [], ingredientes_removidos: [] }]
    observacoes,
    forma_pagamento
  } = params;
  
  // 1. Validar dados obrigatórios
  // 2. Iniciar transação
  // 3. Criar pedido (INSERT INTO pedido)
  // 4. Inserir itens (INSERT INTO pedido_item para cada item)
  // 5. Atualizar status da mesa (se aplicável)
  // 6. Commit transaction
  // 7. Retornar pedido criado com ID
}
```

**Exemplo de uso pela IA:**
```json
{
  "tool": "create_order",
  "parameters": {
    "cliente": "João Silva",
    "telefone_cliente": "11999999999",
    "tipo_entrega": "delivery",
    "endereco": "Rua das Flores, 123",
    "itens": [
      {
        "produto_id": 5,
        "quantidade": 2,
        "observacao": "Sem cebola",
        "ingredientes_removidos": [3, 7]
      },
      {
        "produto_id": 12,
        "quantidade": 1
      }
    ],
    "observacoes": "Entregar com guardanapos extras",
    "forma_pagamento": "PIX"
  },
  "context": {
    "tenant_id": 5,
    "filial_id": 4
  }
}
```

---

### 2. **COBRANÇA WHATSAPP AUTOMÁTICA** ⚠️ **IMPORTANTE**

**Status:** ❌ Não implementado

**O que precisa:**

#### **A. Nova ferramenta MCP: `get_fiado_customers`**

```javascript
async function getFiadoCustomers(params, tenantId, filialId) {
  // Busca clientes com saldo devedor
  const sql = `
    SELECT 
      c.id,
      c.nome,
      c.telefone,
      SUM(p.saldo_devedor) as total_devedor,
      COUNT(p.idpedido) as quantidade_pedidos,
      MAX(p.data) as ultima_compra
    FROM clientes c
    JOIN pedido p ON p.cliente = c.nome AND p.tenant_id = c.tenant_id
    WHERE c.tenant_id = $1 
      AND c.filial_id = $2
      AND p.saldo_devedor > 0
    GROUP BY c.id, c.nome, c.telefone
    HAVING SUM(p.saldo_devedor) > 0
    ORDER BY total_devedor DESC
    LIMIT $3
  `;
  
  const result = await pool.query(sql, [tenantId, filialId, params.limit || 50]);
  return result.rows;
}
```

#### **B. Workflow n8n para Cobrança**

**Fluxo sugerido:**
```
1. Trigger (Cron - diário 9h)
   ↓
2. MCP: get_fiado_customers (pegar clientes devedores)
   ↓
3. Loop para cada cliente:
   a. AI Agent: Gerar mensagem personalizada
   b. Wuzapi: Enviar mensagem WhatsApp
   c. Aguardar 2 segundos (rate limit)
   ↓
4. Log de envios
```

**Exemplo de mensagem gerada pela IA:**
```
Olá João! 😊

Passando para lembrar que você tem um saldo pendente de R$ 45,50 
referente às suas últimas compras no Divino Lanches.

Você pode pagar via PIX:
Chave: 11999999999
Nome: Divino Lanches

Qualquer dúvida, estamos à disposição! 🍔
```

---

### 3. **INTEGRAÇÃO WUZAPI** ⚠️ **IMPORTANTE**

**Status:** ❌ Não implementado

**O que precisa:**

#### **A. Configuração Wuzapi**

Adicionar ao `.env`:
```env
# Wuzapi Configuration
WUZAPI_URL=https://sua-instancia.wuzapi.com
WUZAPI_TOKEN=seu-token-aqui
WUZAPI_INSTANCE_ID=sua-instancia-id
```

#### **B. WuzapiService.php**

```php
<?php
namespace System;

class WuzapiService
{
    private $apiUrl;
    private $token;
    private $instanceId;
    
    public function __construct() {
        $config = Config::getInstance();
        $this->apiUrl = $config->getEnv('WUZAPI_URL');
        $this->token = $config->getEnv('WUZAPI_TOKEN');
        $this->instanceId = $config->getEnv('WUZAPI_INSTANCE_ID');
    }
    
    public function sendMessage($phone, $message) {
        $url = "{$this->apiUrl}/api/send";
        
        $data = [
            'instanceId' => $this->instanceId,
            'phone' => $this->formatPhone($phone),
            'message' => $message
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer {$this->token}"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new \Exception("Erro ao enviar mensagem WhatsApp: $response");
        }
        
        return json_decode($response, true);
    }
    
    private function formatPhone($phone) {
        // Remove caracteres não numéricos
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Adiciona código do país se não tiver
        if (strlen($phone) === 11) {
            $phone = '55' . $phone;
        }
        
        return $phone;
    }
}
```

#### **C. Webhook Wuzapi para receber mensagens**

**Arquivo:** `mvc/ajax/wuzapi_webhook.php`

```php
<?php
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';
require_once __DIR__ . '/../../system/N8nAIService.php';

// Recebe mensagem do Wuzapi
$input = file_get_contents('php://input');
$data = json_decode($input, true);

error_log("Wuzapi Webhook: " . $input);

// Extrair dados
$from = $data['from'] ?? '';
$message = $data['message'] ?? '';
$instanceId = $data['instanceId'] ?? '';

// Buscar tenant/filial pela instância WhatsApp
$db = \System\Database::getInstance();
$instance = $db->fetch(
    "SELECT tenant_id, filial_id FROM whatsapp_instances WHERE instance_id = ?",
    [$instanceId]
);

if (!$instance) {
    error_log("Instância WhatsApp não encontrada: $instanceId");
    http_response_code(404);
    echo json_encode(['error' => 'Instance not found']);
    exit;
}

// Processar com IA
$aiService = new \System\N8nAIService();
$response = $aiService->processMessage($message, [], $instance['tenant_id'], $instance['filial_id']);

// Enviar resposta via Wuzapi
$wuzapi = new \System\WuzapiService();
$wuzapi->sendMessage($from, $response['message']);

http_response_code(200);
echo json_encode(['success' => true]);
```

---

## 🎯 PRÓXIMOS PASSOS (PRIORIDADE)

### **FASE 1: Completar CREATE ORDER** ⚠️ **URGENTE**

1. ✅ Implementar `createOrder()` no MCP Server
2. ✅ Testar criação de pedido via MCP
3. ✅ Validar cálculo de totais
4. ✅ Testar com ingredientes adicionais/removidos

### **FASE 2: Integração Wuzapi**

1. ✅ Criar `WuzapiService.php`
2. ✅ Configurar webhook Wuzapi
3. ✅ Testar envio/recebimento de mensagens
4. ✅ Integrar com N8nAIService

### **FASE 3: Cobrança Automática**

1. ✅ Implementar `get_fiado_customers` no MCP
2. ✅ Criar workflow n8n de cobrança
3. ✅ Testar envio em massa
4. ✅ Adicionar logs de cobrança

### **FASE 4: Melhorias**

1. ✅ Adicionar histórico de conversas
2. ✅ Dashboard de métricas IA
3. ✅ Relatório de cobranças enviadas
4. ✅ Rate limiting Wuzapi

---

## 📝 EXEMPLO COMPLETO DE USO

### **Cenário: Cliente faz pedido via WhatsApp**

**1. Cliente:** "Oi, quero 2 X-Bacon sem cebola e 1 Coca-Cola"

**2. n8n recebe e envia para IA:**
```json
{
  "message": "Oi, quero 2 X-Bacon sem cebola e 1 Coca-Cola",
  "tenant_id": 5,
  "filial_id": 4,
  "customer_phone": "5511999999999"
}
```

**3. IA decide ações:**
- **Ação 1:** Buscar produtos
  ```json
  {
    "tool": "search_products",
    "parameters": { "term": "X-Bacon", "limit": 5 }
  }
  ```

- **Ação 2:** Buscar bebidas
  ```json
  {
    "tool": "search_products",
    "parameters": { "term": "Coca-Cola", "limit": 5 }
  }
  ```

- **Ação 3:** Criar pedido
  ```json
  {
    "tool": "create_order",
    "parameters": {
      "cliente": "Cliente WhatsApp",
      "telefone_cliente": "5511999999999",
      "tipo_entrega": "delivery",
      "itens": [
        {
          "produto_id": 15,
          "quantidade": 2,
          "observacao": "Sem cebola",
          "ingredientes_removidos": [8]
        },
        {
          "produto_id": 42,
          "quantidade": 1
        }
      ],
      "forma_pagamento": "A combinar"
    }
  }
  ```

**4. IA responde:**
```
Pedido registrado! 🎉

✅ 2x X-Bacon (sem cebola) - R$ 31,80
✅ 1x Coca-Cola 350ml - R$ 5,00

💰 Total: R$ 36,80

Qual será a forma de pagamento?
- PIX
- Dinheiro
- Cartão na entrega
```

---

## 📚 DOCUMENTAÇÃO EXISTENTE

- ✅ `n8n-mcp-server/README.md` - Documentação do MCP
- ✅ `QUICK_START_N8N.md` - Guia rápido de configuração
- ✅ `CONFIGURAR_N8N_EXTERNO.md` - Setup n8n externo
- ✅ `IMPLEMENTACAO_IA_COMPLETA.md` - Detalhes da implementação
- ✅ `AI_AGENT_SETUP.md` - Configuração do AI Agent

---

## 🔑 VARIÁVEIS DE AMBIENTE NECESSÁRIAS

```env
# AI Configuration
USE_N8N_AI=true
AI_N8N_WEBHOOK_URL=https://wapp.conext.click/webhook/ai-chat
AI_N8N_TIMEOUT=30

# MCP Server
MCP_API_KEY=sua-chave-secreta-aqui
MCP_SERVER_URL=https://divinosys.conext.click:3100

# Wuzapi (ADICIONAR)
WUZAPI_URL=https://sua-instancia.wuzapi.com
WUZAPI_TOKEN=seu-token
WUZAPI_INSTANCE_ID=sua-instancia
```

---

## ✅ CHECKLIST FINAL

### **Funcionando:**
- [x] MCP Server rodando
- [x] Operações de leitura (GET)
- [x] Criar/Editar/Excluir Produtos
- [x] Criar/Editar/Excluir Ingredientes
- [x] Criar/Editar/Excluir Categorias
- [x] Criar lançamentos financeiros
- [x] Atualizar status de pedidos
- [x] Registrar pagamentos
- [x] Autenticação com API Key
- [x] Multi-tenant support

### **Faltando:**
- [ ] **CREATE ORDER COMPLETO** ⚠️
- [ ] **Integração Wuzapi**
- [ ] **Cobrança automática WhatsApp**
- [ ] **Webhook Wuzapi**
- [ ] **Histórico de conversas**
- [ ] **Dashboard métricas IA**

---

**Próximo passo sugerido:** Implementar `create_order` no MCP Server! 🚀



