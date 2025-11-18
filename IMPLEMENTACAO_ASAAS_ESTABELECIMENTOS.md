# 🏢 Implementação Asaas por Estabelecimento/Filial

## 📋 Resumo da Implementação

Sistema completo implementado para permitir que cada estabelecimento e filial configure sua própria integração com o Asaas, incluindo emissão de notas fiscais, gestão de informações fiscais e cobrança de pedidos.

## ✅ Funcionalidades Implementadas

### **1. Configuração Individual do Asaas**
- ✅ **Chave API própria** para cada estabelecimento/filial
- ✅ **Configuração de ambiente** (sandbox/produção)
- ✅ **ID do cliente** no Asaas
- ✅ **Habilitação/desabilitação** da integração
- ✅ **Herança de configuração** (filial herda do estabelecimento se não tiver própria)

### **2. Gestão de Notas Fiscais**
- ✅ **Agendar nota fiscal** (`POST /v3/invoices`)
- ✅ **Emitir nota fiscal** (`POST /v3/invoices/{id}/issue`)
- ✅ **Cancelar nota fiscal** (`POST /v3/invoices/{id}/cancel`)
- ✅ **Listar notas fiscais** (`GET /v3/invoices`)
- ✅ **Buscar nota específica** (`GET /v3/invoices/{id}`)
- ✅ **Criar nota a partir de pedido**

### **3. Gestão de Informações Fiscais**
- ✅ **Criar/atualizar informações fiscais** (`POST /v3/fiscalInfo`)
- ✅ **Recuperar informações fiscais** (`GET /v3/fiscalInfo`)
- ✅ **Listar configurações municipais** (`GET /v3/fiscalInfo/municipalOptions`)
- ✅ **Listar serviços municipais** (`GET /v3/fiscalInfo/municipalServices`)
- ✅ **Listar códigos NBS** (`GET /v3/fiscalInfo/nbsCodes`)
- ✅ **Configurar portal emissor** (`POST /v3/fiscalInfo/issuerPortal`)

### **4. Interface de Usuário**
- ✅ **Página de configuração** (`/index.php?view=asaas_config`)
- ✅ **Formulário de configuração** do Asaas
- ✅ **Gestão de informações fiscais**
- ✅ **Dashboard de notas fiscais**
- ✅ **Teste de conexão** com Asaas

## 🏗️ Arquitetura Implementada

### **Estrutura de Banco de Dados**

#### **Tabela `tenants` (Estabelecimentos)**
```sql
-- Colunas adicionadas:
asaas_api_key VARCHAR(255)
asaas_api_url VARCHAR(255) DEFAULT 'https://sandbox.asaas.com/api/v3'
asaas_customer_id VARCHAR(100)
asaas_webhook_token VARCHAR(255)
asaas_environment VARCHAR(20) DEFAULT 'sandbox'
asaas_enabled BOOLEAN DEFAULT false
asaas_fiscal_info JSONB
asaas_municipal_service_id VARCHAR(100)
asaas_municipal_service_code VARCHAR(100)
```

#### **Tabela `filiais` (Filiais)**
```sql
-- Colunas adicionadas:
asaas_api_key VARCHAR(255)
asaas_customer_id VARCHAR(100)
asaas_enabled BOOLEAN DEFAULT false
asaas_fiscal_info JSONB
asaas_municipal_service_id VARCHAR(100)
asaas_municipal_service_code VARCHAR(100)
```

#### **Tabela `notas_fiscais` (Gestão de Notas)**
```sql
CREATE TABLE notas_fiscais (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id),
    filial_id INTEGER REFERENCES filiais(id),
    asaas_invoice_id VARCHAR(100) NOT NULL,
    asaas_payment_id VARCHAR(100),
    numero_nota VARCHAR(50),
    serie_nota VARCHAR(10),
    chave_acesso VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending',
    valor_total DECIMAL(10,2) NOT NULL,
    valor_impostos DECIMAL(10,2) DEFAULT 0.00,
    data_emissao TIMESTAMP,
    data_cancelamento TIMESTAMP,
    xml_content TEXT,
    pdf_url VARCHAR(500),
    observacoes TEXT,
    asaas_response JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### **Tabela `informacoes_fiscais` (Dados Fiscais)**
```sql
CREATE TABLE informacoes_fiscais (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id),
    filial_id INTEGER REFERENCES filiais(id),
    cnpj VARCHAR(18) NOT NULL,
    razao_social VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255),
    inscricao_estadual VARCHAR(50),
    inscricao_municipal VARCHAR(50),
    endereco JSONB NOT NULL,
    contato JSONB,
    regime_tributario VARCHAR(50),
    optante_simples_nacional BOOLEAN DEFAULT false,
    municipal_service_id VARCHAR(100),
    municipal_service_code VARCHAR(100),
    municipal_service_name VARCHAR(255),
    nbs_codes JSONB,
    active BOOLEAN DEFAULT true,
    asaas_sync_status VARCHAR(20) DEFAULT 'pending',
    asaas_response JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### **Modelos Implementados**

#### **1. `AsaasInvoice` - Gestão de Notas Fiscais**
- `scheduleInvoice()` - Agendar nota fiscal
- `issueInvoice()` - Emitir nota fiscal
- `cancelInvoice()` - Cancelar nota fiscal
- `listInvoices()` - Listar notas fiscais
- `getInvoice()` - Buscar nota específica
- `getAsaasConfig()` - Obter configuração do Asaas

#### **2. `AsaasFiscalInfo` - Gestão de Informações Fiscais**
- `createOrUpdateFiscalInfo()` - Criar/atualizar dados fiscais
- `getFiscalInfo()` - Obter informações fiscais
- `listMunicipalOptions()` - Listar opções municipais
- `listMunicipalServices()` - Listar serviços municipais
- `listNBSCodes()` - Listar códigos NBS
- `configureIssuerPortal()` - Configurar portal emissor
- `validateCNPJ()` - Validar CNPJ

### **Controllers Implementados**

#### **1. `InvoiceController` - Controle de Notas Fiscais**
- `scheduleInvoice()` - Agendar nota
- `issueInvoice()` - Emitir nota
- `cancelInvoice()` - Cancelar nota
- `listInvoices()` - Listar notas
- `getInvoice()` - Obter nota
- `getInvoiceStats()` - Estatísticas
- `createInvoiceFromOrder()` - Criar nota de pedido

#### **2. `FiscalInfoController` - Controle de Dados Fiscais**
- `createOrUpdateFiscalInfo()` - Salvar dados fiscais
- `getFiscalInfo()` - Obter dados fiscais
- `listMunicipalOptions()` - Opções municipais
- `listMunicipalServices()` - Serviços municipais
- `listNBSCodes()` - Códigos NBS
- `configureIssuerPortal()` - Portal emissor
- `validateCNPJ()` - Validar CNPJ

### **AJAX Handlers**

#### **1. `mvc/ajax/invoices.php` - Notas Fiscais**
- `scheduleInvoice` - Agendar nota
- `issueInvoice` - Emitir nota
- `cancelInvoice` - Cancelar nota
- `listInvoices` - Listar notas
- `getInvoice` - Obter nota
- `getInvoiceStats` - Estatísticas
- `createInvoiceFromOrder` - Criar de pedido

#### **2. `mvc/ajax/fiscal_info.php` - Dados Fiscais**
- `createOrUpdateFiscalInfo` - Salvar dados
- `getFiscalInfo` - Obter dados
- `listMunicipalOptions` - Opções municipais
- `listMunicipalServices` - Serviços municipais
- `listNBSCodes` - Códigos NBS
- `configureIssuerPortal` - Portal emissor
- `validateCNPJ` - Validar CNPJ
- `getFiscalStats` - Estatísticas
- `deactivateFiscalInfo` - Desativar dados

#### **3. `mvc/ajax/asaas_config.php` - Configuração**
- `saveConfig` - Salvar configuração
- `testConnection` - Testar conexão
- `getConfig` - Obter configuração

## 🎯 Como Usar

### **1. Configuração Inicial**

1. **Acesse a configuração do Asaas:**
   ```
   http://localhost:8080/index.php?view=asaas_config
   ```

2. **Configure sua chave API:**
   - Obtenha sua chave API em [www.asaas.com](https://www.asaas.com)
   - Configure o ambiente (sandbox para testes, produção para live)
   - Adicione o ID do cliente no Asaas

3. **Configure informações fiscais:**
   - CNPJ da empresa
   - Razão social
   - Dados de endereço
   - Serviços municipais

### **2. Emissão de Notas Fiscais**

1. **Criar nota a partir de pedido:**
   ```javascript
   // Via AJAX
   $.ajax({
       url: 'mvc/ajax/invoices.php?action=createInvoiceFromOrder',
       method: 'POST',
       data: JSON.stringify({
           tenant_id: 1,
           filial_id: 1,
           pedido_id: 123
       }),
       contentType: 'application/json'
   });
   ```

2. **Emitir nota fiscal:**
   ```javascript
   $.ajax({
       url: 'mvc/ajax/invoices.php?action=issueInvoice',
       method: 'POST',
       data: JSON.stringify({
           tenant_id: 1,
           filial_id: 1,
           asaas_invoice_id: 'inv_123456'
       }),
       contentType: 'application/json'
   });
   ```

### **3. Gestão de Informações Fiscais**

1. **Salvar dados fiscais:**
   ```javascript
   $.ajax({
       url: 'mvc/ajax/fiscal_info.php?action=createOrUpdateFiscalInfo',
       method: 'POST',
       data: JSON.stringify({
           tenant_id: 1,
           filial_id: 1,
           cnpj: '12.345.678/0001-90',
           razao_social: 'Empresa Exemplo LTDA',
           endereco: {
               logradouro: 'Rua Exemplo',
               numero: '123',
               bairro: 'Centro',
               cidade: 'São Paulo',
               uf: 'SP',
               cep: '01234-567'
           },
           municipal_service_id: '123',
           municipal_service_code: '456'
       }),
       contentType: 'application/json'
   });
   ```

## 🔧 Configuração de Permissões

### **Níveis de Acesso**

- **Admin do Estabelecimento**: Acesso total à configuração do Asaas
- **Admin da Filial**: Acesso à configuração da filial específica
- **Operadores**: Acesso apenas à emissão de notas (sem configuração)

### **Adicionado ao Sistema de Permissões**

```php
// Em system/Auth.php
'admin' => [
    'dashboard', 'pedidos', 'delivery', 'produtos', 'estoque', 
    'financeiro', 'relatorios', 'clientes', 'configuracoes', 'usuarios',
    'novo_pedido', 'relatorios_avancados', 'asaas_config',
],
```

## 📊 Benefícios da Implementação

### **Para Estabelecimentos**
- ✅ **Controle total** sobre suas cobranças e notas fiscais
- ✅ **Configuração independente** do sistema principal
- ✅ **Dados fiscais próprios** para cada unidade
- ✅ **Integração direta** com o Asaas

### **Para Filiais**
- ✅ **Configuração própria** ou herança do estabelecimento
- ✅ **Gestão independente** de notas fiscais
- ✅ **Dados fiscais específicos** da filial
- ✅ **Controle de cobranças** local

### **Para o Sistema**
- ✅ **Escalabilidade** para múltiplos estabelecimentos
- ✅ **Isolamento de dados** por tenant/filial
- ✅ **Flexibilidade** de configuração
- ✅ **Integração robusta** com Asaas

## 🚀 Próximos Passos

1. **Testar a integração** com dados reais do Asaas
2. **Configurar webhooks** para notificações automáticas
3. **Implementar relatórios** de notas fiscais
4. **Adicionar validações** adicionais de CNPJ
5. **Criar documentação** de uso para estabelecimentos

## 📝 Notas Importantes

- **Sandbox vs Produção**: Configure corretamente o ambiente
- **Chaves API**: Mantenha as chaves seguras e não as exponha
- **CNPJ**: Valide sempre o CNPJ antes de salvar
- **Webhooks**: Configure os webhooks do Asaas para notificações automáticas
- **Backup**: Faça backup regular dos dados fiscais

---

**Implementação concluída com sucesso!** 🎉

O sistema agora permite que cada estabelecimento e filial gerencie sua própria integração com o Asaas, emitindo notas fiscais e controlando suas cobranças de forma independente.
