# Divino Lanches 2.0 - Sistema de Gestão

Sistema completo de gestão para restaurantes e lanchonetes, desenvolvido com arquitetura multi-tenant SaaS.

## 🚀 Características

- **Multi-tenant**: Suporte a múltiplos estabelecimentos
- **Responsivo**: Interface adaptável para desktop e mobile
- **Seguro**: Autenticação robusta e validação de dados
- **Escalável**: Arquitetura preparada para crescimento
- **Moderno**: Interface intuitiva com Bootstrap 5

## 🛠️ Tecnologias

### Backend
- **PHP 8.2+**: Linguagem principal
- **PostgreSQL 15**: Banco de dados
- **Redis**: Cache e sessões
- **Apache**: Servidor web

### Frontend
- **Bootstrap 5**: Framework CSS
- **jQuery 3.6**: Manipulação DOM
- **Font Awesome 6**: Ícones
- **SweetAlert2**: Alertas
- **Chart.js**: Gráficos

## 📋 Funcionalidades

### Gestão de Pedidos
- ✅ Sistema de pedidos em mesa e delivery
- ✅ Pipeline visual de status
- ✅ Customização de ingredientes
- ✅ Controle de tempo de preparo

### Gestão de Mesas
- ✅ Visualização em tempo real
- ✅ Status livre/ocupada
- ✅ Informações de pedidos ativos

### Gestão de Produtos
- ✅ CRUD completo de produtos
- ✅ Categorização
- ✅ Controle de ingredientes
- ✅ Upload de imagens

### Controle de Estoque
- ✅ Monitoramento de produtos
- ✅ Alertas de baixo estoque
- ✅ Controle de validade

### Gestão Financeira
- ✅ Controle de receitas e despesas
- ✅ Relatórios financeiros
- ✅ Categorização automática

### Relatórios
- ✅ Análises de vendas
- ✅ Produtos mais vendidos
- ✅ Performance por período
- ✅ Exportação PDF/Excel

## 🐳 Instalação com Docker

### Desenvolvimento Local

1. **Clone o repositório**
```bash
git clone https://github.com/Moafsa/div1.0.git
cd div1.0
```

2. **Configure as variáveis de ambiente**
```bash
cp env.example .env
# Edite o arquivo .env com suas configurações
```

3. **Inicie os containers**
```bash
docker-compose up -d
```

4. **Acesse o sistema**
```
http://localhost:8080
```

### Deploy no Coolify

1. **Configure as variáveis de ambiente no Coolify:**
```
DB_HOST=postgres
DB_PORT=5432
DB_NAME=divinosys
DB_USER=divino_user
DB_PASSWORD=sua_senha_segura
APP_URL=https://seu-dominio.com
APP_KEY=base64:sua_chave_secreta
```

2. **Deploy automático**
O Coolify irá fazer o build e deploy automaticamente usando o `coolify.yml`.

## 🔧 Configuração

### Variáveis de Ambiente

| Variável | Descrição | Padrão |
|----------|-----------|---------|
| `DB_HOST` | Host do PostgreSQL | `postgres` |
| `DB_PORT` | Porta do PostgreSQL | `5432` |
| `DB_NAME` | Nome do banco | `divinosys` |
| `DB_USER` | Usuário do banco | `divino_user` |
| `DB_PASSWORD` | Senha do banco | `divino_password` |
| `APP_URL` | URL da aplicação | `http://localhost:8080` |
| `APP_KEY` | Chave de criptografia | `base64:your-secret-key` |
| `ENABLE_MULTI_TENANT` | Habilitar multi-tenant | `true` |

### Estrutura do Banco

O sistema utiliza PostgreSQL com suporte a multi-tenancy. Todas as tabelas principais incluem `tenant_id` e `filial_id` para isolamento de dados.

## 👥 Usuários Padrão

Após a instalação, use as seguintes credenciais:

- **Usuário**: `admin`
- **Senha**: `admin`
- **Estabelecimento**: `divino`

## 🔒 Segurança

- Autenticação com hash de senha
- Validação de CSRF
- Sanitização de inputs
- Headers de segurança
- Rate limiting
- Logs de auditoria

## 📱 Multi-tenant

O sistema suporta múltiplos estabelecimentos com:

- Isolamento completo de dados
- Subdomínios personalizados
- Planos diferenciados
- Configurações independentes

## 🚀 Deploy

### Coolify (Recomendado)

1. Conecte o repositório no Coolify
2. Configure as variáveis de ambiente
3. Deploy automático

### Docker Compose

```bash
docker-compose -f coolify.yml up -d
```

### Manual

1. Configure o servidor web (Apache/Nginx)
2. Configure o PostgreSQL
3. Configure o Redis
4. Execute as migrações do banco
5. Configure as permissões de arquivo

## 📊 Monitoramento

O sistema inclui:

- Logs de aplicação
- Logs de segurança
- Health checks
- Métricas de performance

## 🤝 Contribuição

1. Fork o projeto
2. Crie uma branch para sua feature
3. Commit suas mudanças
4. Push para a branch
5. Abra um Pull Request

## 📄 Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

## 🆘 Suporte

Para suporte técnico:

- **Email**: contato@divinolanches.com
- **Issues**: [GitHub Issues](https://github.com/Moafsa/div1.0/issues)

## 🔄 Atualizações

### v2.0.0
- ✅ Migração para PostgreSQL
- ✅ Arquitetura multi-tenant
- ✅ Interface moderna
- ✅ Sistema de autenticação robusto
- ✅ Preparado para Coolify

---

**Desenvolvido com ❤️ para o setor de alimentação**
