# 🍔 Divino Lanches - Sistema de Gestão de Lanchonete

Sistema completo de gestão para lanchonetes com funcionalidades de pedidos, mesas, delivery, estoque e relatórios.

## 🚀 Funcionalidades

### 📋 Gestão de Pedidos
- ✅ Pipeline Kanban para acompanhamento de pedidos
- ✅ Criação e edição de pedidos
- ✅ Controle de status (Pendente, Em Preparo, Pronto, etc.)
- ✅ Gestão de itens e quantidades
- ✅ Observações personalizadas

### 🪑 Gestão de Mesas
- ✅ Dashboard com grid de mesas
- ✅ Status das mesas (Livre, Ocupada)
- ✅ Popup detalhado para cada mesa
- ✅ Edição de mesa e múltiplas mesas
- ✅ Fechamento de mesa

### 🚚 Delivery
- ✅ Pedidos de delivery
- ✅ Controle de entregadores
- ✅ Status de entrega

### 📦 Gestão de Produtos
- ✅ Cadastro de produtos
- ✅ Categorias de produtos
- ✅ Controle de estoque
- ✅ Preços e variações

### 📊 Relatórios e Financeiro
- ✅ Relatórios de vendas
- ✅ Controle financeiro
- ✅ Estatísticas de pedidos

## 🛠️ Tecnologias

- **Backend**: PHP 8.2+ com arquitetura MVC customizada
- **Frontend**: Bootstrap 5, jQuery, SweetAlert2
- **Database**: PostgreSQL
- **Containerização**: Docker & Docker Compose
- **Deploy**: Coolify ready

## 🐳 Deploy com Coolify

### 1. Configuração no Coolify

1. **Conecte o repositório**: `https://github.com/Moafsa/div1.0`
2. **Selecione o branch**: `main`
3. **Configure as variáveis de ambiente**:

```env
# Database
DB_HOST=postgres
DB_PORT=5432
DB_NAME=divino_lanches
DB_USER=postgres
DB_PASSWORD=sua_senha_aqui

# App
APP_NAME="Divino Lanches"
APP_URL=https://seu-dominio.com
APP_DEBUG=false

# Multi-tenant
MULTI_TENANT_ENABLED=true
```

### 2. Arquivos de Configuração

O projeto inclui:
- ✅ `Dockerfile` - Container PHP/Apache
- ✅ `docker-compose.yml` - Stack completa
- ✅ `coolify.yml` - Configuração para Coolify
- ✅ `coolify.json` - Metadados do projeto

### 3. Deploy Automático

O Coolify irá:
1. **Buildar** a imagem Docker
2. **Configurar** o PostgreSQL
3. **Executar** as migrações do banco
4. **Deployar** a aplicação

## 🗄️ Estrutura do Banco

### Tabelas Principais
- `usuarios` - Usuários do sistema
- `tenants` - Multi-tenancy
- `filiais` - Filiais da empresa
- `mesas` - Mesas do estabelecimento
- `produtos` - Catálogo de produtos
- `pedido` - Pedidos
- `pedido_itens` - Itens dos pedidos

## 🔧 Desenvolvimento Local

### Pré-requisitos
- Docker & Docker Compose
- Git

### Instalação
```bash
git clone https://github.com/Moafsa/div1.0.git
cd div1.0
docker-compose up -d
```

### Acesso
- **Aplicação**: http://localhost:8080
- **Banco**: localhost:5432

## 📱 Interface

### Dashboard Principal
- Grid de mesas com status em tempo real
- Estatísticas de pedidos
- Ações rápidas

### Gestão de Pedidos
- Pipeline visual com drag & drop
- Popup detalhado para cada pedido
- Edição inline de status e observações

### Sistema de Mesas
- Visualização em tempo real
- Popup com detalhes completos
- Controles de quantidade e remoção de itens

## 🔐 Segurança

- ✅ Autenticação de usuários
- ✅ Multi-tenancy isolado
- ✅ Validação de dados
- ✅ Sanitização de inputs
- ✅ Proteção CSRF

## 📈 Performance

- ✅ Queries otimizadas
- ✅ Cache de sessão
- ✅ Lazy loading
- ✅ Compressão de assets

## 🐛 Debug e Logs

- ✅ Logs detalhados em `logs/`
- ✅ Modo debug configurável
- ✅ Tratamento de erros
- ✅ Console logs no frontend

## 📞 Suporte

Para suporte ou dúvidas:
- **Issues**: [GitHub Issues](https://github.com/Moafsa/div1.0/issues)
- **Documentação**: Veja os arquivos `.md` no projeto

## 📄 Licença

Este projeto é proprietário. Todos os direitos reservados.

---

**Divino Lanches v1.0** - Sistema completo de gestão para lanchonetes 🍔