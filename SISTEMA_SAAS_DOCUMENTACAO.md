# 📚 Sistema de Assinatura SaaS - Divino Lanches

## 📋 Visão Geral

Sistema completo de assinatura multi-tenant implementado para transformar o Divino Lanches em um SaaS (Software as a Service). O sistema permite que múltiplos estabelecimentos usem a plataforma, cada um com seus próprios dados isolados, planos de assinatura e filiais.

---

## 🏗️ Arquitetura do Sistema

### Estrutura Multi-Tenant

```
SuperAdmin (Nível 999)
    ↓
Tenant (Estabelecimento)
    ↓
Filiais
    ↓
Usuários
```

### Hierarquia de Dados

- **SuperAdmin**: Controla todo o sistema, gerencia estabelecimentos e planos
- **Tenant**: Estabelecimento (ex: Rede de Lanchonetes)
- **Filial**: Unidade do estabelecimento
- **Usuário**: Funcionários vinculados a um tenant/filial

---

## 📁 Estrutura de Arquivos Criados

### Banco de Dados
```
database/init/10_create_saas_tables.sql
```
- Tabelas de assinaturas
- Tabelas de pagamentos
- Tabelas de uso de recursos
- Tabelas de auditoria e notificações

### Models
```
mvc/model/
├── Tenant.php          # Gerenciamento de estabelecimentos
├── Plan.php            # Gerenciamento de planos
├── Subscription.php    # Gerenciamento de assinaturas
└── Payment.php         # Gerenciamento de pagamentos
```

### Controllers
```
mvc/controller/
├── SuperAdminController.php  # API do SuperAdmin
├── TenantController.php      # API do Tenant
└── OnboardingController.php  # Cadastro de novos estabelecimentos
```

### Middleware
```
mvc/middleware/
└── SubscriptionMiddleware.php  # Verificação de assinatura e limites
```

### Views
```
mvc/views/
├── superadmin_dashboard.php      # Dashboard do SuperAdmin
├── tenant_dashboard.php          # Dashboard do Estabelecimento
├── onboarding.php                # Cadastro de novos clientes
├── subscription_expired.php      # Página de assinatura expirada
└── login_admin.php               # Login administrativo
```

### Sistema
```
system/
└── Database.php  # Classe Singleton para conexão com PostgreSQL
```

---

## 💾 Estrutura do Banco de Dados

### Tabelas Principais

#### `tenants`
Estabelecimentos cadastrados no sistema
- id, nome, subdomain, cnpj, telefone, email
- endereco, logo_url, cor_primaria
- status (ativo/inativo/suspenso)
- plano_id

#### `planos`
Planos de assinatura disponíveis
- id, nome, preco_mensal
- max_mesas, max_usuarios, max_produtos, max_pedidos_mes
- recursos (JSON com features)

#### `filiais`
Filiais de cada estabelecimento
- id, tenant_id, nome, endereco
- telefone, email, cnpj, status

#### `assinaturas`
Assinaturas ativas
- id, tenant_id, plano_id, status
- data_inicio, data_fim, data_proxima_cobranca
- valor, periodicidade, trial_ate

#### `pagamentos`
Histórico de pagamentos
- id, assinatura_id, tenant_id, valor
- status, metodo_pagamento, data_pagamento
- data_vencimento, gateway_payment_id

#### `uso_recursos`
Controle de uso mensal de recursos
- id, tenant_id, mes_referencia
- mesas_usadas, usuarios_usados, produtos_usados
- pedidos_mes, storage_mb

#### `audit_logs`
Logs de auditoria do sistema
- id, tenant_id, usuario_id, acao
- entidade, entidade_id, dados_anteriores, dados_novos
- ip_address, user_agent

#### `notificacoes`
Sistema de notificações
- id, tenant_id, usuario_id, tipo
- titulo, mensagem, lida, link, prioridade

#### `tenant_config`
Configurações específicas de cada tenant
- id, tenant_id, chave, valor, tipo

---

## 🎯 Planos de Assinatura

### Starter - R$ 49,90/mês
- 5 mesas
- 2 usuários
- 50 produtos
- 500 pedidos/mês
- Relatórios básicos
- Suporte por email

### Professional - R$ 149,90/mês
- 15 mesas
- 5 usuários
- 200 produtos
- 2000 pedidos/mês
- Relatórios avançados
- Suporte por email e WhatsApp
- Backup diário
- API de acesso

### Business - R$ 299,90/mês
- 30 mesas
- 10 usuários
- 500 produtos
- 5000 pedidos/mês
- Relatórios customizados
- Suporte prioritário
- Backup diário
- API de acesso

### Enterprise - R$ 999,90/mês
- Recursos ilimitados
- Relatórios customizados
- Suporte dedicado
- Backup em tempo real
- White label
- Integrações customizadas

---

## 🔐 Sistema de Autenticação

### Níveis de Usuário

- **999**: SuperAdmin (acesso total ao sistema)
- **1**: Administrador do Tenant
- **0**: Operador (usuário comum)

### Fluxo de Login

1. **Login Normal** (`login.php`):
   - Login via telefone com WhatsApp
   - Seleção de estabelecimento/filial
   - Redirecionamento para dashboard

2. **Login Administrativo** (`login_admin.php`):
   - Login com usuário e senha
   - Verificação de nível
   - Superadmin → Dashboard SuperAdmin
   - Outros → Dashboard Principal

---

## 🚀 APIs Disponíveis

### SuperAdminController

```php
GET /mvc/controller/SuperAdminController.php?action=getDashboardStats
GET /mvc/controller/SuperAdminController.php?action=listTenants
POST /mvc/controller/SuperAdminController.php?action=createTenant
PUT /mvc/controller/SuperAdminController.php?action=updateTenant
POST /mvc/controller/SuperAdminController.php?action=toggleTenantStatus
GET /mvc/controller/SuperAdminController.php?action=listPlans
POST /mvc/controller/SuperAdminController.php?action=createPlan
PUT /mvc/controller/SuperAdminController.php?action=updatePlan
DELETE /mvc/controller/SuperAdminController.php?action=deletePlan
GET /mvc/controller/SuperAdminController.php?action=listPayments
POST /mvc/controller/SuperAdminController.php?action=markPaymentAsPaid
```

### TenantController

```php
GET /mvc/controller/TenantController.php?action=getTenantInfo
POST /mvc/controller/TenantController.php?action=updateTenantInfo
GET /mvc/controller/TenantController.php?action=listFiliais
POST /mvc/controller/TenantController.php?action=createFilial
POST /mvc/controller/TenantController.php?action=updateFilial
DELETE /mvc/controller/TenantController.php?action=deleteFilial
GET /mvc/controller/TenantController.php?action=getPaymentHistory
GET /mvc/controller/TenantController.php?action=checkSubscriptionStatus
```

### OnboardingController

```php
POST /mvc/controller/OnboardingController.php
GET /mvc/controller/OnboardingController.php?action=checkSubdomain&subdomain=exemplo
```

---

## 📊 Dashboard do SuperAdmin

### Funcionalidades

1. **Estatísticas Gerais**
   - Total de estabelecimentos
   - Assinaturas ativas
   - Receita mensal
   - Contas em trial

2. **Gerenciamento de Estabelecimentos**
   - Listar todos os tenants
   - Criar novo estabelecimento
   - Editar informações
   - Suspender/Reativar

3. **Gerenciamento de Planos**
   - Criar novos planos
   - Editar planos existentes
   - Definir limites e recursos
   - Definir preços

4. **Gerenciamento de Assinaturas**
   - Ver todas as assinaturas
   - Renovar assinaturas
   - Cancelar assinaturas

5. **Gerenciamento de Pagamentos**
   - Ver histórico de pagamentos
   - Marcar pagamentos como pagos manualmente
   - Ver pagamentos vencidos

6. **Análises**
   - Gráficos de receita
   - Métricas de crescimento
   - Taxa de churn

---

## 🏪 Dashboard do Estabelecimento

### Funcionalidades

1. **Informações da Conta**
   - Dados do estabelecimento
   - Plano atual
   - Status da assinatura
   - Próxima cobrança

2. **Uso de Recursos**
   - Mesas: X / Y utilizadas
   - Usuários: X / Y utilizados
   - Produtos: X / Y cadastrados
   - Pedidos: X / Y no mês

3. **Gerenciamento de Filiais**
   - Listar filiais
   - Adicionar nova filial
   - Editar filial
   - Inativar filial

4. **Histórico de Pagamentos**
   - Ver faturas
   - Baixar comprovantes
   - Ver pagamentos pendentes

5. **Ações Rápidas**
   - Ir para dashboard principal
   - Editar dados do estabelecimento
   - Fazer upgrade de plano
   - Solicitar suporte

---

## 🎓 Sistema de Onboarding

### Fluxo de Cadastro

**Passo 1: Dados Básicos**
- Nome do estabelecimento
- Subdomain (ex: meu-negocio.divinolanches.com.br)
- CNPJ, telefone, email
- Dados do administrador

**Passo 2: Escolha do Plano**
- Visualização de todos os planos
- Comparação de recursos
- 14 dias grátis em qualquer plano

**Passo 3: Configurações Iniciais**
- Quantidade de mesas
- Cor do sistema
- Tipo de operação (delivery, mesas, balcão)

**Passo 4: Finalização**
- Confirmação dos dados
- Criação automática de:
  - Tenant
  - Usuário administrador
  - Assinatura trial
  - Categorias padrão
  - Mesas
  - Configurações

---

## 🔒 Middleware de Assinatura

### SubscriptionMiddleware

Verifica automaticamente:

1. **Status da Assinatura**
   - Se está ativa
   - Se trial expirou
   - Se pagamento está em dia

2. **Limites do Plano**
   - Antes de criar mesas
   - Antes de adicionar usuários
   - Antes de cadastrar produtos
   - Ao criar pedidos (limite mensal)

3. **Ações Automáticas**
   - Bloquear acesso se assinatura expirada
   - Redirecionar para página de renovação
   - Enviar notificações

### Uso no Código

```php
// Verificar se assinatura está ativa
SubscriptionMiddleware::protect();

// Verificar limite antes de criar recurso
SubscriptionMiddleware::checkResourceLimit('mesas');
```

---

## 🎨 Personalização

### Cores por Tenant

Cada estabelecimento pode personalizar:
- Cor primária do sistema
- Logo
- Favicon
- Temas customizados (planos superiores)

### White Label (Enterprise)

No plano Enterprise:
- Domínio próprio
- Remoção de branding
- Customização completa

---

## 📧 Sistema de Notificações

### Tipos de Notificações

1. **Pagamentos**
   - Pagamento recebido
   - Pagamento vencido
   - Falha na cobrança

2. **Assinatura**
   - Trial expirando em 3 dias
   - Assinatura renovada
   - Upgrade de plano

3. **Limites**
   - 80% de uso de mesas
   - 80% de uso de produtos
   - Limite mensal atingido

4. **Sistema**
   - Novos recursos
   - Manutenções programadas
   - Avisos importantes

---

## 🔄 Migração e Instalação

### 1. Executar Migrations

```bash
psql -U postgres -d divino_lanches -f database/init/10_create_saas_tables.sql
```

### 2. Criar Usuário SuperAdmin

O script já cria automaticamente:
- **Login**: superadmin
- **Senha**: password (ALTERAR EM PRODUÇÃO)
- **Nível**: 999

### 3. Acessar Sistema

```
http://seu-dominio.com/index.php?view=login_admin

Usuário: superadmin
Senha: password
```

### 4. Primeiro Acesso

1. Alterar senha do superadmin
2. Configurar planos de assinatura
3. Criar primeiro estabelecimento de teste
4. Configurar gateway de pagamento

---

## 🛡️ Segurança

### Isolamento de Dados

- Cada tenant tem seus dados completamente isolados
- Queries sempre incluem `tenant_id`
- Middleware valida contexto do tenant em cada requisição

### Auditoria

- Todos os logs são gravados em `audit_logs`
- IP e User Agent são registrados
- Dados anteriores e novos são salvos em JSON

### Backup

- Backup diário automático (Professional+)
- Backup em tempo real (Enterprise)
- Retenção de 30 dias para contas canceladas

---

## 💳 Integração com Gateway de Pagamento

### Gateways Suportados

O sistema está preparado para integração com:
- Stripe
- PagSeguro
- Mercado Pago
- Asaas
- Outros via webhook

### Implementação

```php
// Criar pagamento
$payment = new Payment();
$payment_id = $payment->create([
    'assinatura_id' => $subscription_id,
    'tenant_id' => $tenant_id,
    'valor' => 149.90,
    'data_vencimento' => date('Y-m-d', strtotime('+30 days'))
]);

// Webhook do gateway
// Atualizar status do pagamento
$payment->updateStatus($payment_id, 'pago', [
    'gateway_payment_id' => $gateway_transaction_id,
    'gateway_response' => json_encode($gateway_response)
]);

// Renovar assinatura
$subscription = new Subscription();
$subscription->renew($subscription_id);
```

---

## 📈 Métricas e KPIs

### Para SuperAdmin

- MRR (Monthly Recurring Revenue)
- Churn Rate
- CAC (Customer Acquisition Cost)
- LTV (Lifetime Value)
- Taxa de conversão trial → pago
- Planos mais populares

### Para Tenant

- Pedidos no mês
- Receita no mês
- Produtos mais vendidos
- Uso de recursos
- Dias até renovação

---

## 🆘 Suporte

### Níveis de Suporte

**Email** (Todos os planos)
- Resposta em até 48h

**WhatsApp** (Professional+)
- Resposta em até 24h

**Telefone** (Business+)
- Resposta em até 12h

**Dedicado** (Enterprise)
- Gerente de conta dedicado
- Resposta em até 4h

---

## 🚀 Próximos Passos

1. **Implementar gateway de pagamento real**
2. **Sistema de emails automáticos**
3. **API pública para integrações**
4. **App móvel**
5. **Integrações com marketplaces (iFood, Rappi)**
6. **Sistema de cupons e promoções**
7. **Programa de afiliados**
8. **Marketplace de plugins**

---

## 📝 Notas de Desenvolvimento

### Tecnologias Utilizadas

- PHP 8.2+
- PostgreSQL 14+
- Bootstrap 5
- jQuery 3.7
- SweetAlert2
- Chart.js

### Padrões de Código

- PSR-4 para autoloading
- Singleton para Database
- MVC para organização
- RESTful para APIs

### Convenções

- Nomes de tabelas em minúsculo
- Nomes de campos em snake_case
- Nomes de classes em PascalCase
- Nomes de métodos em camelCase

---

## ✅ Checklist de Implementação

- [x] Estrutura de banco de dados
- [x] Models (Tenant, Plan, Subscription, Payment)
- [x] Controllers (SuperAdmin, Tenant, Onboarding)
- [x] Middleware de assinatura
- [x] Dashboard do SuperAdmin
- [x] Dashboard do Tenant
- [x] Sistema de onboarding
- [x] Autenticação multi-nível
- [ ] Integração com gateway de pagamento
- [ ] Sistema de emails
- [ ] Testes automatizados
- [ ] Documentação de API completa

---

**Divino Lanches SaaS v1.0**
Sistema de Assinatura Multi-Tenant
© 2025 Todos os direitos reservados

