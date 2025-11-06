# 💰 Ferramentas Financeiras - MCP Server

## 📋 Ferramentas Disponíveis

1. **get_financial_entries** - Listar lançamentos (sem autenticação)
2. **create_financial_entry** - Criar lançamento (requer autenticação)
3. **delete_financial_entry** - Deletar lançamento (requer autenticação)

---

## 📊 1. get_financial_entries (Consultar Lançamentos)

### **JSON:**

```json
{
  "tool": "get_financial_entries",
  "parameters": {
    "tipo": "receita",
    "categoria": "vendas",
    "data_inicio": "2025-11-01",
    "data_fim": "2025-11-30",
    "limit": 50
  },
  "context": {
    "tenant_id": 4,
    "filial_id": 1
  }
}
```

### **Parâmetros (todos opcionais):**
- `tipo` - "receita" ou "despesa"
- `categoria` - Categoria do lançamento
- `data_inicio` - Data início (formato: YYYY-MM-DD)
- `data_fim` - Data fim (formato: YYYY-MM-DD)
- `limit` - Máximo de resultados (padrão: 50)

### **Exemplos:**

**Listar todas as receitas:**
```json
{
  "tool": "get_financial_entries",
  "parameters": {
    "tipo": "receita"
  },
  "context": {"tenant_id": 4, "filial_id": 1}
}
```

**Listar despesas de novembro:**
```json
{
  "tool": "get_financial_entries",
  "parameters": {
    "tipo": "despesa",
    "data_inicio": "2025-11-01",
    "data_fim": "2025-11-30"
  },
  "context": {"tenant_id": 4, "filial_id": 1}
}
```

**Listar por categoria:**
```json
{
  "tool": "get_financial_entries",
  "parameters": {
    "categoria": "compras"
  },
  "context": {"tenant_id": 4, "filial_id": 1}
}
```

---

## ➕ 2. create_financial_entry (Criar Lançamento)

### **⚠️ Requer autenticação:** `x-api-key: mcp_divinosys_2024_secret_key`

### **JSON:**

```json
{
  "tool": "create_financial_entry",
  "parameters": {
    "tipo": "receita",
    "valor": 150.50,
    "descricao": "Venda de lanches - Mesa 5",
    "categoria": "vendas"
  },
  "context": {
    "tenant_id": 4,
    "filial_id": 1
  }
}
```

### **Parâmetros:**
- `tipo` ✅ **Obrigatório** - "receita" ou "despesa"
- `valor` ✅ **Obrigatório** - Valor do lançamento
- `descricao` ✅ **Obrigatório** - Descrição do lançamento
- `categoria` ⏺️ Opcional - Categoria (padrão: "outros")

### **Exemplos:**

**Registrar receita:**
```json
{
  "tool": "create_financial_entry",
  "parameters": {
    "tipo": "receita",
    "valor": 250.00,
    "descricao": "Venda do dia - Delivery",
    "categoria": "vendas"
  },
  "context": {"tenant_id": 4, "filial_id": 1}
}
```

**Registrar despesa:**
```json
{
  "tool": "create_financial_entry",
  "parameters": {
    "tipo": "despesa",
    "valor": 80.00,
    "descricao": "Compra de ingredientes",
    "categoria": "compras"
  },
  "context": {"tenant_id": 4, "filial_id": 1}
}
```

**Registrar com categoria padrão:**
```json
{
  "tool": "create_financial_entry",
  "parameters": {
    "tipo": "despesa",
    "valor": 50.00,
    "descricao": "Manutenção equipamento"
  },
  "context": {"tenant_id": 4, "filial_id": 1}
}
```

---

## 🗑️ 3. delete_financial_entry (Deletar Lançamento)

### **⚠️ Requer autenticação:** `x-api-key: mcp_divinosys_2024_secret_key`

### **JSON:**

```json
{
  "tool": "delete_financial_entry",
  "parameters": {
    "id": 123
  },
  "context": {
    "tenant_id": 4,
    "filial_id": 1
  }
}
```

### **Parâmetros:**
- `id` ✅ **Obrigatório** - ID do lançamento financeiro

### **Exemplo:**

```json
{
  "tool": "delete_financial_entry",
  "parameters": {
    "id": 45
  },
  "context": {"tenant_id": 4, "filial_id": 1}
}
```

### **Resposta:**

```json
{
  "success": true,
  "message": "Lançamento financeiro excluído com sucesso!",
  "deleted_entry": {
    "id": 45,
    "tipo": "despesa",
    "valor": 80.00,
    "descricao": "Compra de ingredientes",
    "data": "2025-11-05"
  }
}
```

---

## 📝 Fluxo Completo: Gerenciar Lançamentos

### **1. Listar lançamentos para encontrar ID:**

```json
{
  "tool": "get_financial_entries",
  "parameters": {
    "tipo": "despesa",
    "limit": 10
  },
  "context": {"tenant_id": 4, "filial_id": 1}
}
```

### **2. Criar novo lançamento:**

```json
{
  "tool": "create_financial_entry",
  "parameters": {
    "tipo": "despesa",
    "valor": 120.00,
    "descricao": "Conta de luz",
    "categoria": "contas"
  },
  "context": {"tenant_id": 4, "filial_id": 1}
}
```

### **3. Deletar lançamento (se necessário):**

```json
{
  "tool": "delete_financial_entry",
  "parameters": {
    "id": 123
  },
  "context": {"tenant_id": 4, "filial_id": 1}
}
```

---

## 🎯 Categorias Comuns

Sugestões de categorias para lançamentos:

**Receitas:**
- `vendas` - Vendas de produtos
- `servicos` - Serviços prestados
- `outros` - Outras receitas

**Despesas:**
- `compras` - Compra de insumos/ingredientes
- `contas` - Contas (luz, água, internet)
- `salarios` - Folha de pagamento
- `manutencao` - Manutenção e reparos
- `marketing` - Marketing e publicidade
- `impostos` - Impostos e taxas
- `outros` - Outras despesas

---

## 🧪 Teste Completo no n8n/HTTP Request

```json
{
  "tool": "create_financial_entry",
  "parameters": {
    "tipo": "receita",
    "valor": 500.00,
    "descricao": "Vendas do dia 06/11/2025",
    "categoria": "vendas"
  },
  "context": {
    "tenant_id": 4,
    "filial_id": 1
  }
}
```

**Lembre-se:** Precisa da autenticação configurada (x-api-key)!

---

## 📊 Resumo das Ferramentas Financeiras

| Ferramenta | Autenticação | Uso |
|------------|--------------|-----|
| **get_financial_entries** | ❌ Não | Listar/consultar lançamentos |
| **create_financial_entry** | ✅ Sim | Criar receita ou despesa |
| **delete_financial_entry** | ✅ Sim | Deletar lançamento por ID |

**Todas as ferramentas requerem tenant_id e filial_id no context!**

