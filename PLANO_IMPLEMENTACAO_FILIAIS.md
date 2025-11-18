# Plano de Implementação - Sistema de Filiais

## Visão Geral

Este documento apresenta o plano completo para implementar o sistema de filiais no Divino Lanches, permitindo que um estabelecimento principal gerencie múltiplas filiais com controle centralizado de relatórios financeiros.

## Arquitetura do Sistema

### Hierarquia
```
SuperAdmin (Sistema)
    ↓
Tenant (Estabelecimento Principal)
    ↓
Filiais (Sub-estabelecimentos)
    ↓
Usuários (Operadores de cada filial)
```

### Funcionalidades por Nível

#### Estabelecimento Principal
- **Dashboard consolidado** com visão geral de todas as filiais
- **Gerenciamento de filiais** (criar, editar, excluir, ativar/desativar)
- **Relatórios consolidados** com dados de todas as filiais
- **Controle de usuários** para todas as filiais
- **Configurações globais** do estabelecimento

#### Filiais
- **Operação independente** com seu próprio cardápio, mesas e usuários
- **Dashboard próprio** com dados específicos da filial
- **Relatórios individuais** da filial
- **Configurações específicas** da filial
- **Integração com sistema principal** para relatórios consolidados

## Estrutura de Banco de Dados

### Tabelas Principais

#### `filiais`
```sql
CREATE TABLE filiais (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id),
    nome VARCHAR(255) NOT NULL,
    telefone VARCHAR(20),
    email VARCHAR(255),
    endereco TEXT,
    cnpj VARCHAR(18),
    cor_primaria VARCHAR(7) DEFAULT '#007bff',
    numero_mesas INTEGER DEFAULT 15,
    status VARCHAR(20) DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `usuarios_globais`
```sql
CREATE TABLE usuarios_globais (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id),
    filial_id INTEGER REFERENCES filiais(id),
    nome VARCHAR(255) NOT NULL,
    telefone VARCHAR(20) UNIQUE,
    email VARCHAR(255),
    tipo_usuario VARCHAR(50) DEFAULT 'cliente',
    status VARCHAR(20) DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

#### `usuarios_estabelecimento`
```sql
CREATE TABLE usuarios_estabelecimento (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL REFERENCES tenants(id),
    filial_id INTEGER REFERENCES filiais(id),
    usuario_global_id INTEGER REFERENCES usuarios_globais(id),
    login VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    nivel INTEGER DEFAULT 1,
    permissoes JSONB,
    status VARCHAR(20) DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
```

### Modificações em Tabelas Existentes

Todas as tabelas principais receberam a coluna `filial_id`:
- `produtos`
- `categorias`
- `mesas`
- `pedido`
- `pedido_itens`
- `ingredientes`
- `clientes`
- `categorias_financeiras`
- `contas_financeiras`
- `lancamentos_financeiros`
- `anexos_lancamentos`
- `historico_pedidos_financeiros`
- `relatorios_financeiros`
- `metas_financeiras`
- `enderecos`
- `preferencias_cliente`
- `cliente_historico`
- `cliente_estabelecimentos`
- `pagamentos`

## Estrutura de Arquivos

### Controllers
- `mvc/controller/FilialController.php` - Gerenciamento de filiais
- `mvc/controller/EstabelecimentoController.php` - Dashboard do estabelecimento principal

### Models
- `mvc/model/Filial.php` - Modelo para filiais
- `mvc/model/Estabelecimento.php` - Modelo para estabelecimento principal

### Views
- `mvc/views/dashboard_estabelecimento.php` - Dashboard principal
- `mvc/views/gerenciar_filiais.php` - Gerenciamento de filiais
- `mvc/views/relatorios_consolidados.php` - Relatórios consolidados

### AJAX Endpoints
- `mvc/ajax/estabelecimento.php` - Endpoints para estabelecimento principal
- `mvc/ajax/filiais.php` - Endpoints para gerenciamento de filiais

### Migrações
- `database/migrations/create_filial_system.sql` - Criação do sistema de filiais
- `deploy_sistema_filiais.php` - Script de deploy completo

## Fluxo de Implementação

### Fase 1: Preparação do Banco de Dados
1. **Executar migração do sistema de filiais**
   ```bash
   php deploy_sistema_filiais.php
   ```

2. **Verificar estrutura das tabelas**
   - Confirmar criação das tabelas `filiais`, `usuarios_globais`, `usuarios_estabelecimento`
   - Verificar adição da coluna `filial_id` em todas as tabelas principais
   - Confirmar criação da view `vw_filiais_resumo`

3. **Criar filial padrão**
   - Filial principal com ID 1
   - Atualizar dados existentes com `filial_id = 1`

### Fase 2: Implementação dos Controllers
1. **FilialController**
   - CRUD completo de filiais
   - Geração de credenciais de acesso
   - Controle de status (ativo/inativo/suspenso)

2. **EstabelecimentoController**
   - Dashboard consolidado
   - Relatórios financeiros consolidados
   - Gerenciamento de usuários globais

### Fase 3: Implementação das Views
1. **Dashboard Estabelecimento**
   - Visão geral das filiais
   - Estatísticas consolidadas
   - Acesso rápido às funcionalidades

2. **Gerenciar Filiais**
   - Listagem de filiais
   - Criação/edição de filiais
   - Controle de status

3. **Relatórios Consolidados**
   - Gráficos de receita por filial
   - Evolução financeira
   - Comparativo entre filiais

### Fase 4: Integração com Sistema Existente
1. **Atualizar rotas**
   - Adicionar rotas para sistema de filiais
   - Configurar middleware de autenticação

2. **Atualizar autenticação**
   - Suporte a contexto de filial
   - Controle de acesso por filial

3. **Atualizar relatórios financeiros**
   - Filtros por filial
   - Relatórios consolidados

## Funcionalidades Implementadas

### Dashboard Estabelecimento
- **Visão geral** das filiais ativas
- **Estatísticas consolidadas** (receita, pedidos, usuários)
- **Acesso rápido** para gerenciar filiais
- **Status das filiais** em tempo real

### Gerenciamento de Filiais
- **Criar nova filial** com dados completos
- **Editar informações** da filial
- **Ativar/desativar** filiais
- **Excluir filiais** (com confirmação)
- **Acessar sistema** da filial

### Relatórios Consolidados
- **Receita total** de todas as filiais
- **Gráficos comparativos** entre filiais
- **Evolução temporal** da receita
- **Filtros por período** (hoje, semana, mês, ano)
- **Resumo detalhado** por filial

### Sistema de Usuários
- **Usuários globais** com acesso a múltiplas filiais
- **Usuários específicos** de cada filial
- **Controle de permissões** por filial
- **Autenticação unificada**

## Segurança e Isolamento

### Isolamento de Dados
- **Tenant ID** para isolamento entre estabelecimentos
- **Filial ID** para isolamento entre filiais
- **Filtros automáticos** em todas as consultas
- **Middleware de contexto** para validação

### Controle de Acesso
- **Níveis de usuário** (SuperAdmin, Tenant Admin, Filial Admin, Operador)
- **Permissões específicas** por filial
- **Auditoria de ações** com logs detalhados

## Testes e Validação

### Testes de Funcionalidade
1. **Criar filial** e verificar dados
2. **Acessar sistema** da filial
3. **Gerar relatórios** consolidados
4. **Gerenciar usuários** por filial
5. **Testar isolamento** de dados

### Testes de Performance
1. **Consultas consolidadas** com múltiplas filiais
2. **Gráficos** com grandes volumes de dados
3. **Filtros** por período e filial
4. **Relatórios** em tempo real

## Próximos Passos

### Implementação Imediata
1. **Executar script de deploy**
2. **Testar funcionalidades básicas**
3. **Criar primeira filial**
4. **Configurar usuários**

### Melhorias Futuras
1. **Sincronização** de dados entre filiais
2. **Backup automático** por filial
3. **Notificações** entre filiais
4. **API REST** para integrações externas

## Considerações Técnicas

### Escalabilidade
- **Arquitetura multi-tenant** suporta múltiplos estabelecimentos
- **Sistema de filiais** permite crescimento orgânico
- **Isolamento de dados** garante segurança e performance

### Manutenibilidade
- **Código modular** com separação clara de responsabilidades
- **Documentação completa** de todas as funcionalidades
- **Testes automatizados** para validação contínua

### Segurança
- **Isolamento completo** entre estabelecimentos e filiais
- **Controle de acesso** granular
- **Auditoria completa** de todas as ações

## Conclusão

O sistema de filiais foi projetado para ser uma extensão natural do sistema existente, mantendo a compatibilidade com todas as funcionalidades atuais enquanto adiciona a capacidade de gerenciar múltiplas filiais com controle centralizado.

A implementação segue as melhores práticas de desenvolvimento, com foco em segurança, escalabilidade e manutenibilidade, garantindo que o sistema possa crescer junto com o negócio.













