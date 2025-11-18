# 🏢 Sistema de Filiais - Divino Lanches

## 📋 Visão Geral

Sistema completo para criação e gestão de filiais no Divino Lanches, permitindo que um estabelecimento principal crie e gerencie múltiplas filiais, cada uma com seu próprio cardápio, usuários, mesas e configurações, mas com controle financeiro centralizado.

## 🎯 Funcionalidades Principais

### Para o Estabelecimento Principal
- ✅ Criar novas filiais
- ✅ Gerar login/senha para filiais
- ✅ Acesso a relatórios financeiros de todas as filiais
- ✅ Controle centralizado de usuários
- ✅ Configurações globais
- ✅ Dashboard consolidado

### Para cada Filial
- ✅ Cardápio próprio e independente
- ✅ Usuários próprios
- ✅ Mesas próprias
- ✅ Relatórios próprios
- ✅ Configurações próprias
- ✅ Sistema completo de pedidos

## 🏗️ Arquitetura do Sistema

### Hierarquia de Dados
```
ESTABELECIMENTO PRINCIPAL (Tenant)
├── Filial Centro (filial_id: 1)
│   ├── Usuários da Filial
│   ├── Produtos da Filial
│   ├── Mesas da Filial
│   └── Pedidos da Filial
├── Filial Zona Sul (filial_id: 2)
│   ├── Usuários da Filial
│   ├── Produtos da Filial
│   ├── Mesas da Filial
│   └── Pedidos da Filial
└── Filial Shopping (filial_id: 3)
    ├── Usuários da Filial
    ├── Produtos da Filial
    ├── Mesas da Filial
    └── Pedidos da Filial
```

### Controle de Acesso
- **Estabelecimento Principal**: Acesso total a todas as filiais
- **Filial**: Acesso apenas aos dados da própria filial
- **Usuários**: Vinculados a uma filial específica

## 📊 Estrutura do Banco de Dados

### Tabelas Principais (já existentes)
- `tenants` - Estabelecimentos
- `filiais` - Filiais de cada estabelecimento
- `usuarios_globais` - Usuários do sistema
- `usuarios_estabelecimento` - Vinculação usuário-estabelecimento-filial
- `produtos` - Produtos (com tenant_id e filial_id)
- `mesas` - Mesas (com tenant_id e filial_id)
- `pedido` - Pedidos (com tenant_id e filial_id)
- `categorias` - Categorias de produtos

### Novas Funcionalidades
- **Criação de Filiais**: Interface para criar novas filiais
- **Geração de Login**: Sistema automático de login/senha
- **Seletor de Filial**: Interface para alternar entre filiais
- **Relatórios Consolidados**: Dashboard com dados de todas as filiais

## 🎨 Interface do Usuário

### Dashboard Principal (Estabelecimento)
```
┌─────────────────────────────────────────────┐
│  🏢 DASHBOARD ESTABELECIMENTO               │
├─────────────────────────────────────────────┤
│                                             │
│  📊 Resumo Geral                            │
│  ├─ Total Filiais: 3                       │
│  ├─ Receita Hoje: R$ 1.250,00              │
│  ├─ Pedidos Hoje: 45                       │
│  └─ Usuários Ativos: 12                     │
│                                             │
│  🏪 Minhas Filiais                          │
│  ├─ Filial Centro - ✅ Ativa                │
│  │   ├─ Receita: R$ 450,00                 │
│  │   ├─ Pedidos: 18                        │
│  │   └─ [Gerenciar] [Relatórios]           │
│  ├─ Filial Zona Sul - ✅ Ativa              │
│  │   ├─ Receita: R$ 380,00                 │
│  │   ├─ Pedidos: 15                        │
│  │   └─ [Gerenciar] [Relatórios]           │
│  └─ Filial Shopping - ✅ Ativa              │
│      ├─ Receita: R$ 420,00                 │
│      ├─ Pedidos: 12                        │
│      └─ [Gerenciar] [Relatórios]           │
│                                             │
│  🔧 Ações Rápidas                           │
│  ├─ [+ Nova Filial]                        │
│  ├─ [Relatórios Consolidados]              │
│  ├─ [Gerenciar Usuários]                   │
│  └─ [Configurações Globais]                │
│                                             │
└─────────────────────────────────────────────┘
```

### Dashboard da Filial
```
┌─────────────────────────────────────────────┐
│  🏪 FILIAL CENTRO                            │
├─────────────────────────────────────────────┤
│                                             │
│  📊 Resumo da Filial                        │
│  ├─ Receita Hoje: R$ 450,00                │
│  ├─ Pedidos Hoje: 18                        │
│  ├─ Mesas Ocupadas: 8/15                    │
│  └─ Usuários Online: 3                     │
│                                             │
│  🍽️ Mesas (15)                              │
│  ├─ Mesa 1: ✅ Ocupada - R$ 45,00          │
│  ├─ Mesa 2: ✅ Ocupada - R$ 32,00          │
│  ├─ Mesa 3: ❌ Livre                        │
│  └─ ...                                     │
│                                             │
│  📋 Ações Rápidas                           │
│  ├─ [Novo Pedido]                          │
│  ├─ [Gerenciar Produtos]                   │
│  ├─ [Relatórios]                           │
│  └─ [Configurações]                        │
│                                             │
└─────────────────────────────────────────────┘
```

## 🔐 Sistema de Autenticação

### Níveis de Acesso
- **999**: SuperAdmin (sistema)
- **1**: Admin do Estabelecimento (acesso a todas as filiais)
- **0**: Admin da Filial (acesso apenas à própria filial)
- **-1**: Operador da Filial (acesso limitado à filial)

### Fluxo de Login
1. **Login Principal**: Acesso ao estabelecimento
2. **Seleção de Filial**: Escolher filial para trabalhar
3. **Dashboard da Filial**: Interface específica da filial

## 💰 Controle Financeiro

### Relatórios Consolidados
- **Receita Total**: Soma de todas as filiais
- **Despesas Totais**: Consolidação de despesas
- **Lucro por Filial**: Análise individual
- **Comparativo**: Performance entre filiais

### Relatórios por Filial
- **Receita da Filial**: Apenas dados da filial
- **Produtos Mais Vendidos**: Específicos da filial
- **Horários de Pico**: Análise temporal
- **Clientes Frequentes**: Base de clientes da filial

## 🛠️ Implementação Técnica

### 1. Banco de Dados
```sql
-- Tabela de filiais (já existe)
CREATE TABLE filiais (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id),
    nome VARCHAR(255) NOT NULL,
    endereco TEXT,
    telefone VARCHAR(20),
    email VARCHAR(255),
    status VARCHAR(20) DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de usuários por filial (já existe)
CREATE TABLE usuarios_estabelecimento (
    id SERIAL PRIMARY KEY,
    usuario_global_id INTEGER NOT NULL REFERENCES usuarios_globais(id),
    tenant_id INTEGER NOT NULL REFERENCES tenants(id),
    filial_id INTEGER REFERENCES filiais(id),
    tipo_usuario VARCHAR(50) NOT NULL,
    ativo BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 2. Controllers
- `FilialController.php` - Gestão de filiais
- `EstabelecimentoController.php` - Dashboard principal
- `RelatorioController.php` - Relatórios consolidados

### 3. Views
- `dashboard_estabelecimento.php` - Dashboard principal
- `gerenciar_filiais.php` - Gestão de filiais
- `dashboard_filial.php` - Dashboard da filial
- `relatorios_consolidados.php` - Relatórios consolidados

### 4. APIs
- `GET /filiais` - Listar filiais
- `POST /filiais` - Criar filial
- `PUT /filiais/{id}` - Atualizar filial
- `DELETE /filiais/{id}` - Excluir filial
- `GET /relatorios/consolidados` - Relatórios consolidados

## 🚀 Fluxo de Criação de Filial

### 1. Criação da Filial
```php
// Dados da nova filial
$filialData = [
    'tenant_id' => $tenantId,
    'nome' => 'Filial Zona Sul',
    'endereco' => 'Rua das Flores, 123',
    'telefone' => '(11) 99999-9999',
    'email' => 'zonasul@divinolanches.com'
];

// Criar filial
$filialId = $filialController->create($filialData);
```

### 2. Geração de Login
```php
// Gerar login e senha
$login = 'filial_' . $filialId;
$senha = generateRandomPassword();

// Criar usuário administrador da filial
$usuarioData = [
    'usuario_global_id' => $usuarioGlobalId,
    'tenant_id' => $tenantId,
    'filial_id' => $filialId,
    'tipo_usuario' => 'admin_filial',
    'login' => $login,
    'senha' => password_hash($senha, PASSWORD_DEFAULT)
];
```

### 3. Configuração Inicial
```php
// Criar mesas padrão
$mesas = createDefaultMesas($filialId, 15);

// Criar categorias padrão
$categorias = createDefaultCategories($filialId);

// Configurar produtos básicos
$produtos = createDefaultProducts($filialId);
```

## 📱 Interface Mobile

### Responsividade
- **Dashboard Adaptável**: Funciona em tablets e celulares
- **Gestão de Mesas**: Interface touch-friendly
- **Relatórios**: Gráficos responsivos
- **Navegação**: Menu lateral colapsável

### Funcionalidades Mobile
- **Pedidos Rápidos**: Interface otimizada para tablets
- **Gestão de Mesas**: Drag & drop em telas touch
- **Relatórios**: Visualização otimizada para mobile
- **Notificações**: Push notifications para pedidos

## 🔒 Segurança

### Isolamento de Dados
- **Filiais Isoladas**: Dados não se misturam
- **Controle de Acesso**: Usuários só acessam sua filial
- **Auditoria**: Log de todas as ações
- **Backup**: Backup automático por filial

### Permissões
- **Admin Estabelecimento**: Acesso total
- **Admin Filial**: Acesso apenas à filial
- **Operador**: Acesso limitado
- **Cliente**: Acesso apenas aos próprios pedidos

## 📊 Métricas e KPIs

### Para o Estabelecimento
- **Receita Total**: Soma de todas as filiais
- **Performance por Filial**: Comparativo
- **Crescimento**: Evolução mensal
- **Eficiência**: Pedidos por funcionário

### Para cada Filial
- **Receita Diária**: Performance da filial
- **Pedidos por Hora**: Análise temporal
- **Produtos Mais Vendidos**: Análise de vendas
- **Clientes Frequentes**: Base de clientes

## 🎯 Benefícios

### Para o Negócio
- **Escalabilidade**: Fácil expansão
- **Controle Centralizado**: Gestão unificada
- **Relatórios Consolidados**: Visão completa
- **Flexibilidade**: Cada filial com suas características

### Para os Usuários
- **Interface Familiar**: Mesmo sistema
- **Dados Isolados**: Segurança
- **Performance**: Sistema otimizado
- **Mobilidade**: Acesso de qualquer lugar

## 🚀 Próximos Passos

### Fase 1: Implementação Base
1. ✅ Estrutura do banco de dados
2. ✅ Controllers e Models
3. ✅ Interface de gestão de filiais
4. ✅ Sistema de autenticação

### Fase 2: Funcionalidades Avançadas
1. 🔄 Relatórios consolidados
2. 🔄 Dashboard principal
3. 🔄 Configurações globais
4. 🔄 Backup automático

### Fase 3: Otimizações
1. 📱 Interface mobile
2. 📊 Métricas avançadas
3. 🔔 Notificações
4. 🔗 Integrações

## 📝 Conclusão

O sistema de filiais do Divino Lanches oferece uma solução completa para estabelecimentos que desejam expandir, mantendo controle centralizado e flexibilidade para cada filial. A arquitetura multi-tenant existente facilita a implementação, e o sistema de autenticação robusto garante segurança e isolamento de dados.

---

**Divino Lanches v2.0** - Sistema Multi-Filial
© 2025 Todos os direitos reservados



