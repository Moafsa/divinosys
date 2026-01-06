# Solução: Erro 400 ao Criar Fatura PIX - Asaas

## 🔴 Problema Identificado

Ao tentar gerar fatura PIX, o sistema retorna erro 400 com a mensagem:
```
Não foi possível criar ou encontrar cliente no Asaas
```

## 🔍 Causa Raiz

A integração Asaas **não está habilitada** no sistema, mesmo que a API Key tenha sido configurada.

**Evidência:**
- API Key configurada: ✅ `$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY...`
- Environment: ✅ `sandbox`
- API URL: ✅ `https://sandbox.asaas.com/api/v3`
- **Enabled: ❌ NÃO** ← Este é o problema!

## ✅ Solução

### Passo 1: Habilitar Integração Asaas

1. Faça login no sistema
2. Vá em **Configurações** (ícone de engrenagem no menu lateral)
3. Clique na aba **"Integrações"**
4. Localize a seção **"Asaas - Gateway de Pagamento"**
5. **Marque a checkbox "Habilitar Integração Asaas"**
6. Clique em **"Salvar Configurações"**

### Passo 2: Verificar Configuração

Execute o script de teste para verificar se tudo está correto:

```
http://localhost:8080/test_asaas_config.php
```

Você deve ver:
```
=== Configuração Asaas ===
Enabled: SIM  ← Deve estar SIM agora
Environment: sandbox
API URL: https://sandbox.asaas.com/api/v3
API Key: Configurada ($aact_hmlg_000Mzk...)
✓ Configuração básica OK
```

### Passo 3: Testar Criação de Fatura PIX

1. Acesse um pedido em aberto
2. Clique em "Gerar Fatura PIX"
3. Preencha os dados (ou deixe em branco para usar cliente genérico)
4. Clique em "Gerar"

Agora deve funcionar! ✅

---

## 📋 Checklist de Configuração Asaas

Para gerar faturas PIX, você precisa:

- [ ] **API Key configurada** (Sandbox ou Produção)
- [ ] **Integração habilitada** ← IMPORTANTE!
- [ ] **Environment selecionado** (sandbox/production)
- [ ] Cliente Asaas criado (automático se não existir)

---

## 🔧 Detalhes Técnicos

### Por que a integração precisa estar habilitada?

O código verifica se `asaas_enabled` é `true` antes de tentar criar faturas:

```php
if (!$asaasConfig || !$asaasConfig['asaas_enabled']) {
    throw new \Exception('Integração Asaas não está habilitada para este estabelecimento.');
}
```

Mesmo com API Key configurada, se `asaas_enabled = false`, o sistema não permite criar faturas.

### Onde é salvo?

A configuração é salva na tabela `tenants` ou `filiais`:

```sql
-- Para tenant
UPDATE tenants SET 
    asaas_enabled = true,
    asaas_api_key = '...',
    asaas_environment = 'sandbox',
    asaas_api_url = 'https://sandbox.asaas.com/api/v3'
WHERE id = ?;

-- Para filial (opcional, herda do tenant se vazio)
UPDATE filiais SET 
    asaas_enabled = true,
    asaas_api_key = '...'
WHERE id = ? AND tenant_id = ?;
```

---

## 🎯 Resumo

**Problema:** Integração Asaas não habilitada  
**Solução:** Marcar checkbox "Habilitar Integração Asaas" nas configurações  
**Tempo:** 30 segundos  
**Dificuldade:** Muito fácil  

---

## 🧪 Script de Teste

Foi criado o arquivo `test_asaas_config.php` para facilitar o diagnóstico.

**Como usar:**
1. Acesse: `http://localhost:8080/test_asaas_config.php`
2. Veja o status da configuração
3. Se tudo estiver OK, o script tentará criar um cliente teste

**O que o script faz:**
- ✓ Verifica se Asaas está habilitado
- ✓ Verifica se API Key está configurada
- ✓ Testa conexão com API Asaas
- ✓ Tenta criar um cliente genérico
- ✓ Salva o Customer ID no banco

---

**Data:** 18/12/2025  
**Versão:** 2.0  
**Status:** Problema identificado, solução documentada








