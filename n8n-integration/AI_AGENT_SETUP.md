# 🤖 AI Agent Setup - Divino Lanches

## 📋 **Visão Geral**

Este fluxo usa um **AI Agent** inteligente que:
- Recebe a pergunta do usuário com todos os parâmetros
- Analisa o que precisa buscar no sistema
- Chama o MCP apenas com os dados necessários
- Retorna uma resposta contextualizada

## 🔧 **Configuração do Fluxo**

### **1. Importar o Fluxo**
1. Acesse o n8n: `wapp.conext.click`
2. Vá em **Workflows** → **Import**
3. Cole o conteúdo do arquivo `workflow-ai-agent.json`
4. Salve o workflow

### **2. Configurar Credenciais**

#### **OpenAI API**
- **Nome:** `OpenAi account`
- **API Key:** Sua chave da OpenAI
- **Model:** `gpt-4o-mini` (recomendado para custo/performance)

#### **Redis Memory**
- **Nome:** `Redis Ricardo`
- **Host:** `redis.conext.click` (ou seu Redis)
- **Port:** `6379`
- **Password:** (se necessário)

#### **MCP Client**
- **Endpoint:** `https://divinosys.conext.click:3100/execute`
- **API Key:** Sua chave do MCP (se necessário)

### **3. Configurar Webhook**
- **Path:** `/webhook/ai-chat`
- **Method:** `POST`
- **URL Final:** `https://wapp.conext.click/webhook/ai-chat`

## 🚀 **Como Funciona**

### **Fluxo de Execução:**
1. **Webhook** recebe a pergunta com parâmetros
2. **Map Parameters** extrai os dados necessários
3. **AI Agent** analisa a pergunta e decide quais ferramentas usar
4. **MCP Client** busca apenas os dados necessários
5. **Format Response** formata a resposta final
6. **Respond to Webhook** retorna para o sistema

### **Parâmetros Recebidos:**
```json
{
  "message": "Qual o status da mesa 5?",
  "tenant_id": "1",
  "filial_id": "1", 
  "user_id": "1",
  "timestamp": "2025-01-08 20:00:00"
}
```

### **Resposta Formatada:**
```json
{
  "success": true,
  "response": {
    "type": "response",
    "message": "🍽️ Mesa 5 está ocupada com 2 pedidos ativos..."
  },
  "timestamp": "2025-01-08T20:00:00.000Z",
  "tenant_id": "1",
  "filial_id": "1",
  "user_id": "1"
}
```

## 🎯 **Vantagens do AI Agent**

### **Inteligência Contextual:**
- Analisa a pergunta e decide o que buscar
- Não faz chamadas desnecessárias ao MCP
- Mantém contexto da conversa via Redis

### **Otimização de Custos:**
- Usa apenas as ferramentas MCP necessárias
- Reduz tokens enviados para OpenAI
- Melhora performance geral

### **Flexibilidade:**
- Funciona com qualquer tipo de pergunta
- Adapta-se a diferentes contextos
- Aprende com o histórico da conversa

## 🔍 **Ferramentas MCP Disponíveis**

O AI Agent pode usar estas ferramentas conforme necessário:

- **`get_products`** - Lista todos os produtos
- **`search_products`** - Busca produtos específicos
- **`get_ingredients`** - Lista ingredientes
- **`get_categories`** - Lista categorias
- **`get_orders`** - Lista pedidos ativos
- **`get_tables`** - Status das mesas
- **`get_order_details`** - Detalhes de pedido
- **`get_table_orders`** - Pedidos de uma mesa

## 🧪 **Testando o Fluxo**

### **1. Teste Manual:**
```bash
curl -X POST https://wapp.conext.click/webhook/ai-chat \
  -H "Content-Type: application/json" \
  -d '{
    "message": "Qual o status da mesa 5?",
    "tenant_id": "1",
    "filial_id": "1",
    "user_id": "1",
    "timestamp": "2025-01-08 20:00:00"
  }'
```

### **2. Teste no n8n:**
1. Abra o workflow
2. Clique em **Test workflow**
3. Envie uma pergunta de teste
4. Verifique a execução

## 🛠️ **Configuração no Sistema Principal**

### **Variáveis de Ambiente:**
```env
# n8n Integration
USE_N8N_AI=true
AI_N8N_WEBHOOK_URL=https://wapp.conext.click/webhook/ai-chat
AI_N8N_TIMEOUT=30
```

### **Teste de Integração:**
1. Configure as variáveis no `.env`
2. Acesse o chat AI no sistema
3. Faça uma pergunta sobre mesas/produtos
4. Verifique se a resposta vem do n8n

## 📊 **Monitoramento**

### **Logs do n8n:**
- Acesse **Executions** no n8n
- Veja o histórico de execuções
- Analise performance e erros

### **Logs do MCP:**
- Verifique logs do servidor MCP
- Monitore chamadas das ferramentas
- Analise tempo de resposta

## 🚨 **Troubleshooting**

### **Erro de Conexão:**
- Verifique se o MCP está rodando
- Confirme a URL do endpoint
- Teste conectividade

### **Erro de Credenciais:**
- Verifique OpenAI API key
- Confirme Redis connection
- Teste credenciais no n8n

### **Resposta Vazia:**
- Verifique se o AI Agent está configurado
- Confirme se o MCP retorna dados
- Analise logs de execução

## ✅ **Checklist de Configuração**

- [ ] Fluxo importado no n8n
- [ ] Credenciais OpenAI configuradas
- [ ] Redis Memory configurado
- [ ] MCP Client configurado
- [ ] Webhook ativo
- [ ] Sistema principal configurado
- [ ] Teste de integração funcionando
- [ ] Monitoramento ativo

## 🎉 **Próximos Passos**

1. **Teste o fluxo** com diferentes tipos de pergunta
2. **Monitore performance** e custos
3. **Ajuste prompts** conforme necessário
4. **Expanda funcionalidades** se necessário

---

**💡 Dica:** Este fluxo é muito mais inteligente que o anterior, pois o AI Agent decide automaticamente quais dados buscar, otimizando custos e performance!
