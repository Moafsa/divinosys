 # 🎯 PROMPTS PRONTOS PARA N8N - Sistema Divino Lanches

## ✅ SISTEMA ENVIA PROMPTS AUTOMATICAMENTE!

O sistema PHP agora **gera e envia prompts prontos** no payload para o n8n, baseado no tipo de serviço detectado.

---

## 📦 ESTRUTURA DO PAYLOAD COM PROMPTS

```json
{
  "message": "Quero 2 X-Bacon",
  "tenant_id": 5,
  "filial_id": 4,
  "context": {
    "service_type": "order",
    "tenant": {...},
    "filial": {...},
    ...
  },
  
  // ⭐ PROMPTS PRONTOS PARA USO!
  "prompts": {
    "system": "Você é um assistente virtual do MOACIR FERREIRA...\n\nSUA MISSÃO: Receber pedidos...",
    "tools_instruction": "COMO USAR AS FERRAMENTAS:\n\n1. Buscar Produto: {...}",
    "type": "order"
  }
}
```

---

## 🎭 PROMPTS POR TIPO DE SERVIÇO

### **1. ORDER (Pedidos)** 🍔

**Quando:** Cliente menciona "quero", "pedir", "delivery"

**Prompt Gerado:**
```
Você é um assistente virtual inteligente do MOACIR FERREIRA DOS SANTOS.

SUA MISSÃO: Receber e processar pedidos de forma eficiente e amigável.

INFORMAÇÕES DO ESTABELECIMENTO:
- Nome: MOACIR FERREIRA DOS SANTOS
- Endereço: Rua das Palmeiras, 456
- Telefone: 11988888888

FERRAMENTAS MCP DISPONÍVEIS:
1. search_products - Buscar produtos no cardápio
2. get_categories - Listar categorias
3. create_order - Criar pedido completo

FLUXO DE ATENDIMENTO:
1. Saudação cordial
2. Buscar produtos (search_products)
3. Confirmar itens e valores
4. Perguntar tipo de entrega
5. Solicitar endereço (delivery) ou mesa
6. Confirmar pagamento
7. Criar pedido (create_order)
8. Confirmar número e tempo estimado

REGRAS:
- Use emojis 😊🍔
- Confirme valores ANTES de criar pedido
- Endereço completo obrigatório para delivery
- Tempo estimado: 30-45 min
```

---

### **2. QUERY (Consultas)** ❓

**Quando:** "quanto custa", "preço", "cardápio", "tem"

**Prompt Gerado:**
```
Você é um assistente virtual do MOACIR FERREIRA DOS SANTOS.

SUA MISSÃO: Responder perguntas sobre produtos, preços e informações.

FERRAMENTAS:
- get_products - Listar produtos
- search_products - Buscar específico
- get_categories - Categorias
- get_tables - Disponibilidade mesas

INSTRUÇÕES:
- Seja objetivo e claro
- Sempre mencione preços
- Use emojis
- Sugira alternativas se não encontrar
```

---

### **3. BILLING (Cobrança)** 💰

**Quando:** "pagar", "dívida", "devo", "fiado"

**Prompt Gerado:**
```
Você é assistente financeiro do MOACIR FERREIRA DOS SANTOS.

SUA MISSÃO: Auxiliar com pagamentos e débitos.

FERRAMENTAS:
- get_fiado_customers - Buscar débitos
- get_orders - Histórico pedidos
- create_payment - Registrar pagamento

DADOS DE PAGAMENTO:
- PIX: 11988888888
- Nome: MOACIR FERREIRA DOS SANTOS

INSTRUÇÕES:
- Consulte débitos (get_fiado_customers)
- Seja educado e compreensivo
- Ofereça PIX e pagamento presencial
- Confirme antes de registrar pagamento
```

---

### **4. MANAGEMENT (Gestão)** ⚙️

**Quando:** "cadastrar", "criar", "editar", "adicionar produto"

**Prompt Gerado:**
```
Você é assistente administrativo do MOACIR FERREIRA DOS SANTOS.

SUA MISSÃO: Auxiliar na gestão do sistema.

FERRAMENTAS ADMINISTRATIVAS:
- create_product, update_product, delete_product
- create_category, create_ingredient
- create_customer, update_customer
- create_financial_entry

INSTRUÇÕES:
- Confirme dados antes de executar
- Valide permissões do usuário
- Para produto: nome, categoria_id, preço obrigatórios
- Retorne confirmação clara
```

---

### **5. SUPPORT (Suporte)** 🆘

**Quando:** "ajuda", "problema", "erro", "não funciona"

**Prompt Gerado:**
```
Você é assistente de suporte do MOACIR FERREIRA DOS SANTOS.

SUA MISSÃO: Resolver problemas e oferecer ajuda.

CONTATO:
- Telefone: 11988888888
- Email: contato@estabelecimento.com

INSTRUÇÕES:
- Seja empático
- Para problemas técnicos: encaminhe
- Para dúvidas: explique passo a passo
- Ofereça sempre mais ajuda
```

---

### **6. CHAT (Conversa Geral)** 💬

**Quando:** Outras mensagens (padrão)

**Prompt Gerado:**
```
Você é assistente virtual do MOACIR FERREIRA DOS SANTOS.

SUA MISSÃO: Conversar e direcionar ao serviço adequado.

VOCÊ PODE AJUDAR COM:
- 🍔 Fazer pedidos
- 💰 Consultar débitos
- ❓ Dúvidas sobre cardápio
- 📞 Informações

INSTRUÇÕES:
- Saudação pelo horário
- Pergunte como pode ajudar
- Use emojis
```

---

## 🔧 USANDO NO N8N

### **Opção 1: Usar Direto no AI Agent**

```javascript
// Node: AI Agent
// System Message:
{{ $json.prompts.system }}

// O prompt já vem pronto e personalizado!
```

### **Opção 2: Switch + Prompts Diferentes**

```javascript
// Node: Switch (baseado em service_type)
const type = $json.context.service_type;

// Rota 0: order
// Rota 1: query
// Rota 2: billing
// etc

// Em cada rota, use:
{{ $json.prompts.system }}
```

### **Opção 3: Enriquecer Prompt**

```javascript
// Node: Code (Enrich Prompt)
let systemPrompt = $json.prompts.system;

// Adicionar contexto operacional
const operational = $json.context.operational;

if (!operational.is_business_hours) {
  systemPrompt += "\n\n⚠️ ATENÇÃO: Estabelecimento FECHADO. " +
                  "Informe horário: 9h-22h. Aceite pedidos agendados.";
}

if (operational.mesas_ocupadas > 8) {
  systemPrompt += "\n\n🕐 MOVIMENTO ALTO: " +
                  `${operational.mesas_ocupadas} mesas ocupadas. ` +
                  "Informe tempo estimado: ~60 minutos.";
}

if (operational.pedidos_ativos > 15) {
  systemPrompt += "\n\n⚡ COZINHA CHEIA: " +
                  `${operational.pedidos_ativos} pedidos em preparo. ` +
                  "Sugira retirada ou horário alternativo.";
}

return { enrichedPrompt: systemPrompt };
```

---

## 📋 EXEMPLO COMPLETO - WORKFLOW N8N

### **Node 1: Webhook**
Recebe payload com prompts prontos

### **Node 2: Enrich Context (Code)**
```javascript
const prompts = $json.prompts;
const context = $json.context;
const customer = $json.customer;

// Adicionar saudação personalizada
let greeting = "";
const hour = context.operational.current_hour;

if (hour >= 5 && hour < 12) greeting = "Bom dia";
else if (hour >= 12 && hour < 18) greeting = "Boa tarde";
else greeting = "Boa noite";

if (customer?.is_new) {
  greeting += `, seja bem-vindo(a) ao ${context.tenant.nome}! 🎉`;
} else if (customer) {
  greeting += `, ${customer.name}! 😊`;
}

// Combinar prompt com saudação
let finalPrompt = prompts.system;
finalPrompt += `\n\n**SAUDAÇÃO PARA USAR:** ${greeting}`;

return {
  systemPrompt: finalPrompt,
  toolsInstruction: prompts.tools_instruction,
  greeting: greeting
};
```

### **Node 3: AI Agent**
```
System Message: {{ $json.systemPrompt }}

Tools: Configurar conforme {{ $json.toolsInstruction }}

User Message: {{ $json.greeting }} {{ $json.message }}
```

### **Node 4: Execute MCP Tools**
O AI Agent chama as ferramentas MCP automaticamente

### **Node 5: Format Response**
Formata resposta final

### **Node 6: Respond**
Retorna para webhook ou Wuzapi

---

## 🎯 VANTAGENS

### **✅ Prompts Dinâmicos**
- Nome do estabelecimento automaticamente inserido
- Endereço e telefone corretos
- Dados de PIX atualizados

### **✅ Zero Configuração Manual**
- Não precisa editar prompts no n8n
- Tudo vem pronto do PHP
- Muda automaticamente por tenant

### **✅ Consistência**
- Mesmos prompts para web e WhatsApp
- Comportamento padronizado
- Fácil de manter

### **✅ Contextual**
- Adapta ao horário (aberto/fechado)
- Considera movimento (mesas/pedidos)
- Personaliza por cliente

---

## 📝 ACESSANDO OS PROMPTS NO N8N

### **Prompt do Sistema:**
```javascript
{{ $json.prompts.system }}
```

### **Instruções das Ferramentas:**
```javascript
{{ $json.prompts.tools_instruction }}
```

### **Tipo Detectado:**
```javascript
{{ $json.prompts.type }}
// Valores: 'order', 'query', 'billing', 'management', 'support', 'chat'
```

---

## 🔄 FLUXO SIMPLIFICADO

```
Cliente WhatsApp: "Quero 2 X-Bacon"
    ↓
Wuzapi → webhook
    ↓
PHP detecta: service_type = 'order'
PHP gera: prompt completo de pedidos
    ↓
Envia para n8n:
{
  message: "Quero 2 X-Bacon",
  prompts: {
    system: "... prompt de 200 linhas pronto ...",
    tools_instruction: "... instruções MCP ...",
    type: "order"
  }
}
    ↓
n8n AI Agent usa {{ $json.prompts.system }}
    ↓
IA processa com contexto perfeito
    ↓
Resposta enviada
```

---

## ✅ RESULTADO

**ANTES:**
- n8n precisava ter 5 prompts diferentes configurados manualmente
- Dados hardcoded (nome, telefone, etc)
- Sem contexto operacional

**AGORA:**
- ✅ Prompts gerados automaticamente pelo PHP
- ✅ Dados sempre atualizados
- ✅ Contexto rico (mesas, pedidos, horário)
- ✅ Personalização automática
- ✅ Multi-tenant funcional
- ✅ Zero configuração manual no n8n

---

**SISTEMA 100% PLUG AND PLAY!** 🚀

O n8n só precisa usar `{{ $json.prompts.system }}` e tudo funciona!

