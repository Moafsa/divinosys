# Correção de Erros - Wuzapi e Asaas

## Problemas Identificados e Resolvidos

### 1. ❌ Erro 404 - Wuzapi não acessível

**Sintoma:** Erro 404 ao tentar usar funcionalidades do WhatsApp (Wuzapi)

**Causa:** O `WuzAPIManager` estava configurado para usar a URL interna do Docker (`http://wuzapi:8080`), mas o sistema está rodando localmente sem containers Docker.

**Solução Implementada:**
- Adicionada detecção automática de ambiente no `WuzAPIManager.php`
- Quando não está em Docker, usa automaticamente `http://localhost:8081`
- Log detalhado da URL sendo usada

**Arquivo Modificado:**
- `system/WhatsApp/WuzAPIManager.php`

**Código Alterado:**
```php
// Auto-detect: if running locally (not in Docker), use localhost:8081
$defaultUrl = 'http://wuzapi:8080';
if (!isset($_ENV['DOCKER_CONTAINER']) && gethostname() !== 'divino-lanches-app') {
    $defaultUrl = 'http://localhost:8081';
    error_log("WuzAPIManager::__construct - Detectado ambiente local, usando: $defaultUrl");
}
$this->wuzapiUrl = $_ENV['WUZAPI_URL'] ?? $defaultUrl;
```

---

### 2. ⚠️ Erro 400 - Criação de Fatura PIX

**Sintoma:** Erro 400 ao tentar gerar fatura PIX com mensagem "Não foi possível criar ou encontrar cliente no Asaas"

**Causa:** Mensagem de erro genérica não indicava claramente qual era o problema real (Asaas não configurado, API key ausente, etc.)

**Solução Implementada:**
- Melhorado tratamento de erro com mensagens mais específicas
- Adicionados logs detalhados da configuração do Asaas
- Mensagens de erro agora indicam exatamente o que está faltando

**Arquivos Modificados:**
- `mvc/ajax/pagamentos_parciais.php` (2 locações: `gerar_fatura_pix` e `gerar_fatura_pix_mesa`)

**Código Alterado:**
```php
// Provide more specific error message
$errorMsg = 'Não foi possível criar ou encontrar cliente no Asaas. ';
if (empty($asaasConfig) || !$asaasConfig['asaas_enabled']) {
    $errorMsg .= 'Integração Asaas não está habilitada para este estabelecimento.';
} elseif (empty($asaasConfig['asaas_api_key'])) {
    $errorMsg .= 'Chave de API do Asaas não configurada.';
} else {
    $errorMsg .= 'Verifique a configuração do Asaas no painel de configurações.';
}
```

---

## Como Testar

### Teste 1: Verificar Wuzapi

1. Abrir navegador em `http://localhost:8081`
2. Deve carregar a interface do Wuzapi
3. Verificar logs do PHP para confirmar URL detectada:
   ```
   WuzAPIManager::__construct - Detectado ambiente local, usando: http://localhost:8081
   ```

### Teste 2: Verificar Erro de Fatura PIX

1. Acessar sistema como usuário
2. Ir para "Fechar Pedido" de um pedido ativo
3. Tentar gerar fatura PIX
4. Se der erro, a mensagem agora será mais específica:
   - "Integração Asaas não está habilitada" → Habilitar Asaas nas configurações
   - "Chave de API do Asaas não configurada" → Adicionar API key nas configurações
   - "Verifique a configuração do Asaas" → Problema na criação do cliente

### Teste 3: Configurar Asaas (se necessário)

1. Acessar **Configurações** → **Integrações** → **Asaas**
2. Habilitar integração
3. Adicionar API Key (sandbox ou produção)
4. Salvar configurações
5. Tentar gerar fatura PIX novamente

---

## Verificação dos Logs

Para depurar problemas, verificar os logs do PHP:

```bash
# No Windows (PowerShell)
Get-Content logs/error.log -Tail 50

# Ou verificar diretamente no arquivo
# logs/error.log ou logs/php_error.log
```

**Logs importantes a procurar:**
- `WuzAPIManager::__construct - Wuzapi URL: ...` → Confirma URL usada
- `PAGAMENTOS_PARCIAIS: Asaas Config: ...` → Mostra configuração do Asaas
- `PAGAMENTOS_PARCIAIS: Cliente encontrado/criado...` → Fluxo de criação de cliente

---

## Próximos Passos

1. ✅ Wuzapi corrigido - detecta ambiente automaticamente
2. ✅ Mensagens de erro melhoradas para Asaas
3. ⏳ Testar criação de fatura PIX com Asaas configurado
4. ⏳ Verificar integração completa Wuzapi + Asaas

---

## Notas Técnicas

### Wuzapi - Portas

- **Porta 8080**: Backend Wuzapi (interno Docker ou localhost)
- **Porta 8081**: Mapeamento externo para acesso local
- **Porta 3000**: Frontend React do Wuzapi

### Asaas - Configuração Mínima

Para gerar faturas PIX, é necessário:
1. Conta Asaas (sandbox ou produção)
2. API Key configurada no sistema
3. Integração habilitada nas configurações
4. Cliente Asaas (criado automaticamente se não existir)

### Fluxo de Criação de Cliente Asaas

1. Se telefone informado → Busca cliente no BD
2. Se cliente tem `asaas_customer_id` → Usa ID existente
3. Se não tem → Cria novo cliente no Asaas
4. Se sem telefone → Usa cliente genérico do tenant/filial
5. Se não existe → Cria cliente genérico com dados do tenant

---

**Data:** 18/12/2025  
**Versão:** 2.0  
**Status:** Correções implementadas, aguardando testes








