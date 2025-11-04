# 📤 PAYLOAD COMPLETO ENVIADO PARA N8N

## 🎯 VISÃO GERAL

O sistema agora envia um **payload rico e contextual** para o n8n, permitindo que a IA:
- Escolha o prompt adequado baseado no tipo de serviço
- Tenha contexto completo do estabelecimento
- Saiba quem está falando (cliente ou usuário interno)
- Entenda o estado operacional (mesas ocupadas, pedidos ativos)
- Adapte respostas ao horário e dia da semana

---

## 📋 ESTRUTURA COMPLETA DO PAYLOAD

```json
{
  // ============ MENSAGEM PRINCIPAL ============
  "message": "Quero 2 X-Bacon sem cebola",
  "timestamp": "2025-11-04 16:30:00",
  
  // ============ IDs PARA MCP QUERIES ============
  "tenant_id": 5,
  "filial_id": 4,
  "user_id": 12,
  
  // ============ CONTEXTO RICO ============
  "context": {
    
    // --- Informações do Estabelecimento ---
    "tenant": {
      "id": 5,
      "nome": "MOACIR FERREIRA DOS SANTOS",
      "subdomain": "moacir",
      "telefone": "11999999999",
      "email": "moacir@divino.com",
      "cnpj": "12345678000199"
    },
    
    // --- Informações da Filial ---
    "filial": {
      "id": 4,
      "nome": "Loja Centro",
      "endereco": "Rua das Flores, 123",
      "telefone": "11988888888"
    },
    
    // --- Usuário/Operador (se aplicável) ---
    "user": {
      "id": 12,
      "login": "atendente01",
      "nivel": 3,
      "is_admin": false,
      "role": "operator"  // 'admin', 'manager', 'operator'
    },
    // OU null se for cliente via WhatsApp
    
    // --- Metadados da Mensagem ---
    "source": "whatsapp",        // 'web', 'whatsapp', 'api', 'n8n'
    "message_type": "order",      // 'chat', 'order', 'query', 'billing', 'management', 'support'
    "channel": "whatsapp",        // 'whatsapp' ou 'web'
    
    // --- Contexto Operacional ---
    "operational": {
      "is_business_hours": true,       // true se entre 9h-22h
      "current_hour": 16,               // Hora atual (0-23)
      "day_of_week": 1,                 // 0=domingo, 6=sábado
      "is_weekend": false,              // true se sábado/domingo
      "pedidos_hoje": 23,               // Pedidos criados hoje
      "mesas_ocupadas": 5,              // Mesas ocupadas agora
      "mesas_disponiveis": 10,          // Mesas disponíveis
      "pedidos_ativos": 8               // Pedidos em andamento
    },
    
    // --- Tipo de Serviço Detectado ---
    "service_type": "order"  // Auto-detectado por keywords
  },
  
  // ============ CLIENTE (se WhatsApp) ============
  "customer": {
    "phone": "5511999999999",
    "name": "João Silva",
    "whatsapp": "5511999999999",
    "is_new": false  // true se cliente recém-criado
  },
  // OU null se for usuário interno
  
  // ============ SESSÃO ============
  "session": {
    "conversation_id": "conv_6545a3b2f1e90",
    "platform": "whatsapp",
    "language": "pt-BR",
    "timezone": "America/Sao_Paulo"
  },
  
  // ============ ANEXOS (se houver) ============
  "attachments": [
    {
      "name": "cardapio.pdf",
      "type": "application/pdf",
      "path": "/uploads/temp/cardapio.pdf",
      "content": "base64_encoded_content_here...",
      "size": 245678
    }
  ]
}
```

---

## 🎭 TIPOS DE SERVIÇO DETECTADOS

A IA detecta automaticamente o tipo de serviço baseado em **keywords** na mensagem:

| Tipo | Keywords | Exemplo | Comportamento da IA |
|------|----------|---------|---------------------|
| **order** | quero, pedir, delivery, levar | "Quero 2 X-Bacon" | Foco em criar pedido, confirmar itens |
| **query** | quanto custa, preço, cardápio, tem | "Quanto custa o X-Tudo?" | Buscar informações, mostrar opções |
| **billing** | pagar, dívida, débito, fiado | "Quanto eu devo?" | Consultar saldo, oferecer formas de pagamento |
| **management** | cadastrar, adicionar, criar, editar | "Cadastrar novo produto" | Operações administrativas (requer auth) |
| **support** | ajuda, suporte, problema | "Não consigo acessar" | Assistência técnica, troubleshooting |
| **chat** | (outros) | "Oi", "Bom dia" | Conversa casual, saudação |

---

## 🔀 FLUXO NO N8N (SUGESTÃO)

### **Node 1: Webhook Trigger**
Recebe o payload completo acima.

### **Node 2: Switch - Roteamento por Service Type**

```javascript
// Baseado em context.service_type
switch ({{$json.context.service_type}}) {
  case 'order':
    return 0; // Rota para Prompt de Pedidos
  case 'query':
    return 1; // Rota para Prompt de Consultas
  case 'billing':
    return 2; // Rota para Prompt de Cobrança
  case 'management':
    return 3; // Rota para Prompt Admin
  default:
    return 4; // Rota para Prompt Geral
}
```

### **Node 3a: AI Agent - Prompt de Pedidos**

```
Você é um atendente virtual do {{$json.context.tenant.nome}}.

CONTEXTO ATUAL:
- Estabelecimento: {{$json.context.tenant.nome}}
- Endereço: {{$json.context.filial.endereco}}
- Telefone: {{$json.context.filial.telefone}}
- Horário: {{$json.context.operational.current_hour}}h
- Status: {{$json.context.operational.is_business_hours ? "Aberto" : "Fechado"}}
- Mesas disponíveis: {{$json.context.operational.mesas_disponiveis}}
- Pedidos ativos: {{$json.context.operational.pedidos_ativos}}

CLIENTE:
- Nome: {{$json.customer.name}}
- Telefone: {{$json.customer.phone}}
- Primeiro pedido: {{$json.customer.is_new ? "Sim" : "Não"}}

SUA MISSÃO: Ajudar o cliente a fazer um pedido.

FERRAMENTAS DISPONÍVEIS:
- search_products: Buscar produtos no cardápio
- create_order: Criar pedido completo
- get_customers: Buscar histórico do cliente

INSTRUÇÕES:
1. Se cliente novo: Dê boas-vindas
2. Busque os produtos mencionados
3. Confirme itens e valores
4. Pergunte tipo de entrega (delivery/balcão)
5. Se delivery: Pergunte endereço
6. Crie o pedido
7. Confirme número do pedido e tempo estimado

EXEMPLO DE RESPOSTA:
"Olá João! Bem-vindo ao Divino Lanches! 😊

Encontrei no cardápio:
🍔 X-Bacon - R$ 15,90

Você quer 2 unidades? (Total: R$ 31,80)
Sem cebola, anotado! ✅

Será para delivery ou retirada no balcão?"
```

### **Node 3b: AI Agent - Prompt de Consultas**

```
Você é um assistente de informações do {{$json.context.tenant.nome}}.

HORÁRIO ATUAL: {{$json.context.operational.current_hour}}h
STATUS: {{$json.context.operational.is_business_hours ? "Aberto" : "Fechado"}}

SUA MISSÃO: Responder perguntas sobre:
- Cardápio e preços
- Horários de funcionamento
- Formas de pagamento
- Localização

FERRAMENTAS:
- get_products: Listar produtos
- get_categories: Listar categorias
- search_products: Buscar item específico

Seja objetivo e amigável. Use emojis! 😊
```

### **Node 3c: AI Agent - Prompt de Cobrança**

```
Você é o assistente financeiro do {{$json.context.tenant.nome}}.

CLIENTE: {{$json.customer.name}}
TELEFONE: {{$json.customer.phone}}

SUA MISSÃO: Auxiliar com pagamentos e dívidas.

FERRAMENTAS:
- get_fiado_customers: Buscar débitos do cliente
- get_orders: Histórico de pedidos

INSTRUÇÕES:
1. Consulte débitos do cliente
2. Informe valores e datas
3. Ofereça formas de pagamento:
   - PIX: {{$json.context.filial.telefone}}
   - Presencial no estabelecimento
4. Seja educado e compreensivo

EXEMPLO:
"Olá João! 😊

Você tem um saldo pendente de R$ 45,50:
- Pedido #123 (02/11): R$ 25,00
- Pedido #145 (03/11): R$ 20,50

Pode pagar via:
💳 PIX: 11988888888 (MOACIR FERREIRA DOS SANTOS)
🏪 No estabelecimento: Rua das Flores, 123

Qualquer dúvida, estou à disposição!"
```

---

## 📊 DADOS ENVIADOS POR FONTE

### **WhatsApp (via Wuzapi):**
```json
{
  "message": "...",
  "tenant_id": 5,
  "filial_id": 4,
  "context": {
    "source": "whatsapp",
    "message_type": "order",  // auto-detectado
    "service_type": "order",
    "tenant": {...},
    "filial": {...},
    "operational": {...}
  },
  "customer": {
    "phone": "5511999999999",
    "name": "João Silva",
    "is_new": false
  },
  "session": {
    "conversation_id": "conv_xyz123",
    "platform": "whatsapp"
  }
}
```

### **Web (usuário logado):**
```json
{
  "message": "...",
  "tenant_id": 5,
  "filial_id": 4,
  "user_id": 12,
  "context": {
    "source": "web",
    "message_type": "management",
    "service_type": "management",
    "tenant": {...},
    "filial": {...},
    "user": {
      "id": 12,
      "login": "gerente",
      "nivel": 2,
      "role": "manager",
      "is_admin": false
    },
    "operational": {...}
  },
  "customer": null,
  "session": {...}
}
```

---

## 🎯 USANDO O CONTEXTO NO N8N

### **Exemplo 1: Prompt Dinâmico**

```javascript
// Node: Set Prompt
const serviceType = $json.context.service_type;
const tenantName = $json.context.tenant.nome;
const isBusinessHours = $json.context.operational.is_business_hours;

let systemPrompt = `Você é assistente do ${tenantName}.`;

if (!isBusinessHours) {
  systemPrompt += `\n\nATENÇÃO: Estabelecimento FECHADO no momento. Informe horário de funcionamento: 9h-22h.`;
}

if (serviceType === 'order') {
  systemPrompt += `\n\nFOCO: Receber e processar pedidos.`;
  systemPrompt += `\nMesas disponíveis: ${$json.context.operational.mesas_disponiveis}`;
} else if (serviceType === 'billing') {
  systemPrompt += `\n\nFOCO: Auxiliar com pagamentos.`;
}

return { prompt: systemPrompt };
```

### **Exemplo 2: Filtrar por Permissão**

```javascript
// Node: Check Permission
const serviceType = $json.context.service_type;
const userRole = $json.context.user?.role;

// Management operations require admin/manager
if (serviceType === 'management') {
  if (!userRole || !['admin', 'manager'].includes(userRole)) {
    return {
      error: true,
      message: "Operação administrativa requer permissão de gerente ou administrador."
    };
  }
}

// Continue processing
return $json;
```

### **Exemplo 3: Personalização por Cliente**

```javascript
// Node: Personalize Response
const customerName = $json.customer?.name;
const isNew = $json.customer?.is_new;

let greeting = isNew 
  ? `Bem-vindo ao ${$json.context.tenant.nome}, ${customerName}! 🎉` 
  : `Olá novamente, ${customerName}! 😊`;

return { greeting };
```

---

## 📱 EXEMPLO REAL - PAYLOAD WHATSAPP

```json
{
  "message": "Oi, quero 2 X-Bacon sem cebola e 1 Coca-Cola 2L para delivery",
  "timestamp": "2025-11-04 16:45:30",
  "tenant_id": 5,
  "filial_id": 4,
  "user_id": null,
  
  "context": {
    "tenant": {
      "id": 5,
      "nome": "MOACIR FERREIRA DOS SANTOS - DIVINO LANCHES",
      "subdomain": "moacir",
      "telefone": "11999999999",
      "email": "contato@divinolanches.com",
      "cnpj": "12345678000199"
    },
    "filial": {
      "id": 4,
      "nome": "Matriz Centro",
      "endereco": "Rua das Palmeiras, 456 - Centro",
      "telefone": "11988888888"
    },
    "user": null,
    
    "source": "whatsapp",
    "message_type": "order",
    "channel": "whatsapp",
    
    "operational": {
      "is_business_hours": true,
      "current_hour": 16,
      "day_of_week": 1,      // Segunda-feira
      "is_weekend": false,
      "pedidos_hoje": 23,
      "mesas_ocupadas": 5,
      "mesas_disponiveis": 10,
      "pedidos_ativos": 8
    },
    
    "service_type": "order"  // ⭐ Auto-detectado!
  },
  
  "customer": {
    "phone": "5511987654321",
    "name": "João Silva",
    "whatsapp": "5511987654321",
    "is_new": false
  },
  
  "session": {
    "conversation_id": "conv_6545a3b2f1e90",
    "platform": "whatsapp",
    "language": "pt-BR",
    "timezone": "America/Sao_Paulo"
  }
}
```

---

## 💻 EXEMPLO REAL - PAYLOAD WEB

```json
{
  "message": "Criar nova categoria de produtos: Sobremesas",
  "timestamp": "2025-11-04 10:30:00",
  "tenant_id": 5,
  "filial_id": 4,
  "user_id": 12,
  
  "context": {
    "tenant": {
      "id": 5,
      "nome": "MOACIR FERREIRA DOS SANTOS",
      ...
    },
    "filial": {...},
    
    "user": {
      "id": 12,
      "login": "gerente01",
      "nivel": 2,
      "is_admin": false,
      "role": "manager"  // ⭐ Tem permissão!
    },
    
    "source": "web",
    "message_type": "management",
    "channel": "web",
    
    "operational": {...},
    "service_type": "management"  // ⭐ Operação administrativa
  },
  
  "customer": null,  // Não é cliente, é usuário interno
  
  "session": {
    "conversation_id": "conv_abc123",
    "platform": "web",
    "language": "pt-BR"
  }
}
```

---

## 🎯 BENEFÍCIOS DO PAYLOAD RICO

### **1. Prompts Inteligentes**
✅ IA sabe se é pedido, consulta ou cobrança
✅ Adapta tom e conteúdo da resposta
✅ Usa informações corretas do estabelecimento

### **2. Contexto Operacional**
✅ Sabe se está aberto ou fechado
✅ Conhece disponibilidade de mesas
✅ Pode avisar sobre tempo de espera alto

### **3. Personalização**
✅ Chama cliente pelo nome
✅ Boas-vindas para novos clientes
✅ Reconhece clientes recorrentes

### **4. Segurança**
✅ Identifica quem está fazendo a requisição
✅ Valida permissões para operações admin
✅ Audit trail completo

### **5. Multi-canal**
✅ Mesmo sistema para WhatsApp e Web
✅ Comportamento adaptado ao canal
✅ Histórico unificado

---

## 📝 USANDO NO N8N - EXEMPLOS PRÁTICOS

### **1. Saudação Personalizada**

```javascript
const customer = $json.customer;
const isNew = customer?.is_new;
const tenantName = $json.context.tenant.nome;
const hour = $json.context.operational.current_hour;

let greeting = "";

// Saudação por horário
if (hour >= 5 && hour < 12) greeting = "Bom dia";
else if (hour >= 12 && hour < 18) greeting = "Boa tarde";
else greeting = "Boa noite";

// Personalizar
if (isNew) {
  greeting += `, seja bem-vindo(a) ao ${tenantName}! 🎉`;
} else if (customer) {
  greeting += `, ${customer.name}! 😊`;
}

return { greeting };
```

### **2. Validar Horário de Funcionamento**

```javascript
const isOpen = $json.context.operational.is_business_hours;
const isWeekend = $json.context.operational.is_weekend;

if (!isOpen) {
  return {
    shouldRespond: false,
    autoMessage: `Olá! No momento estamos fechados. 🕐\n\n` +
                 `Nosso horário de funcionamento:\n` +
                 `Segunda a Sexta: 9h às 22h\n` +
                 `Sábado e Domingo: 10h às 23h\n\n` +
                 `Fique à vontade para fazer seu pedido agora, ` +
                 `processaremos assim que abrirmos! 🍔`
  };
}

return { shouldRespond: true };
```

### **3. Sugestões Baseadas em Movimento**

```javascript
const pedidosHoje = $json.context.operational.pedidos_hoje;
const mesasOcupadas = $json.context.operational.mesas_ocupadas;

let suggestion = "";

if (mesasOcupadas > 8) {
  suggestion = "\n\n⏰ Dica: Estamos com movimento alto. " +
               "Recomendo fazer pedido para delivery ou reservar mesa!";
} else if (pedidosHoje < 5) {
  suggestion = "\n\n🎁 Hoje temos uma promoção especial! " +
               "Pergunte sobre nossos combos!";
}

return { suggestion };
```

---

## ✅ RESUMO

**Informações enviadas para n8n:**

1. ✅ **Mensagem** do usuário
2. ✅ **Tenant/Filial** completos (nome, endereço, telefone)
3. ✅ **Usuário** (se web) com nível de permissão
4. ✅ **Cliente** (se WhatsApp) com telefone e nome
5. ✅ **Source** (web/whatsapp)
6. ✅ **Service Type** (order/query/billing/etc)
7. ✅ **Contexto Operacional** (mesas, pedidos, horário)
8. ✅ **Session ID** para rastreamento
9. ✅ **Anexos** (se houver)

**Com isso, o n8n pode:**
- 🎯 Escolher o prompt certo
- 🤖 Personalizar respostas
- ⚡ Tomar decisões inteligentes
- 🔒 Validar permissões
- 📊 Adaptar ao contexto operacional

---

**SISTEMA TOTALMENTE CONTEXTUAL! 🚀**

