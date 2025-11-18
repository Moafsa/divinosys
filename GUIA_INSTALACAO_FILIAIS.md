# Guia de Instalação - Sistema de Filiais

## 📋 Pré-requisitos

### 1. PHP 8.2+ Instalado
Se o PHP não estiver instalado no Windows:

1. **Baixar PHP:**
   - Acesse: https://windows.php.net/download/
   - Baixe a versão "Thread Safe" para Windows
   - Extraia para `C:\php`

2. **Configurar PATH:**
   - Adicione `C:\php` ao PATH do sistema
   - Reinicie o terminal/PowerShell

3. **Verificar instalação:**
   ```cmd
   php --version
   ```

### 2. PostgreSQL Instalado
Se o PostgreSQL não estiver instalado:

1. **Baixar PostgreSQL:**
   - Acesse: https://www.postgresql.org/download/windows/
   - Baixe e instale a versão mais recente

2. **Configurar banco de dados:**
   - Criar banco: `divino_db`
   - Usuário: `divino_user`
   - Senha: `divino_password`

## 🚀 Instalação do Sistema de Filiais

### Passo 1: Configurar Variáveis de Ambiente

1. **Copiar arquivo de configuração:**
   ```cmd
   copy env.example .env
   ```

2. **Editar arquivo .env:**
   ```env
   # Database Configuration
   DB_HOST=localhost
   DB_PORT=5432
   DB_NAME=divino_db
   DB_USER=divino_user
   DB_PASSWORD=divino_password
   
   # Application Configuration
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=http://localhost:8080
   APP_NAME="Divino Lanches"
   APP_VERSION="2.0"
   
   # Multi-tenant Configuration
   DEFAULT_TENANT_ID=1
   ENABLE_MULTI_TENANT=true
   ```

### Passo 2: Executar Migração do Banco de Dados

1. **Executar script de deploy:**
   ```cmd
   php deploy_sistema_filiais.php
   ```

2. **Verificar se as tabelas foram criadas:**
   - Acesse o PostgreSQL
   - Verifique se as tabelas `filiais`, `usuarios_globais`, `usuarios_estabelecimento` existem
   - Verifique se a coluna `filial_id` foi adicionada às tabelas principais

### Passo 3: Verificar Instalação

1. **Acessar o sistema:**
   - Abra o navegador
   - Acesse: `http://localhost:8080`
   - Faça login como administrador

2. **Verificar menu de filiais:**
   - No menu lateral, deve aparecer:
     - 🏢 Dashboard Estabelecimento
     - 🏪 Gerenciar Filiais
     - 📊 Relatórios Consolidados

## 🏪 Como Criar uma Filial

### Passo 1: Acessar Gerenciamento de Filiais

1. **No menu lateral, clique em "Gerenciar Filiais"**
2. **Você verá a tela de gerenciamento de filiais**

### Passo 2: Criar Nova Filial

1. **Clicar no botão "Nova Filial"**
2. **Preencher os dados:**
   - **Nome da Filial:** Ex: "Filial Centro"
   - **Telefone:** (11) 99999-9999
   - **Email:** contato@filial.com
   - **CNPJ:** 00.000.000/0000-00 (opcional)
   - **Endereço:** Endereço completo da filial
   - **Cor Primária:** Escolha uma cor para a filial
   - **Número de Mesas:** 15 (padrão)

3. **Clicar em "Criar Filial"**

### Passo 3: Configurar Usuários da Filial

1. **Após criar a filial, você pode:**
   - **Editar** informações da filial
   - **Acessar** o sistema da filial
   - **Configurar usuários** específicos para a filial

2. **Para acessar o sistema da filial:**
   - Clique em "Acessar" na filial desejada
   - Você será redirecionado para o sistema da filial
   - Lá você pode configurar cardápio, mesas, usuários, etc.

## 📊 Relatórios Consolidados

### Acessar Relatórios

1. **No menu lateral, clique em "Relatórios Consolidados"**
2. **Você verá:**
   - **Estatísticas gerais** de todas as filiais
   - **Gráficos comparativos** entre filiais
   - **Evolução temporal** da receita
   - **Resumo detalhado** por filial

### Funcionalidades dos Relatórios

- **Filtros por período:** Hoje, Semana, Mês, Ano
- **Gráficos interativos:** Receita por filial, Pedidos por filial
- **Evolução da receita:** Gráfico de linha temporal
- **Comparativo entre filiais:** Performance de cada filial

## 🔧 Solução de Problemas

### Problema: "PHP não é reconhecido"
**Solução:**
1. Instalar PHP 8.2+
2. Adicionar ao PATH do sistema
3. Reiniciar terminal

### Problema: "Erro de conexão com banco"
**Solução:**
1. Verificar se PostgreSQL está rodando
2. Verificar configurações no arquivo .env
3. Testar conexão manualmente

### Problema: "Menu de filiais não aparece"
**Solução:**
1. Verificar se o usuário é administrador
2. Verificar se as permissões foram atualizadas
3. Limpar cache do navegador

### Problema: "Erro ao criar filial"
**Solução:**
1. Verificar se as tabelas foram criadas
2. Verificar se o script de deploy foi executado
3. Verificar logs de erro

## 📁 Estrutura de Arquivos Criados

```
mvc/
├── controller/
│   ├── FilialController.php          # Gerenciamento de filiais
│   └── EstabelecimentoController.php # Dashboard principal
├── views/
│   ├── dashboard_estabelecimento.php    # Dashboard consolidado
│   ├── gerenciar_filiais.php            # Gerenciamento de filiais
│   └── relatorios_consolidados.php      # Relatórios consolidados
└── ajax/
    └── estabelecimento.php              # Endpoints AJAX

database/migrations/
└── create_filial_system.sql             # Migração do banco

system/
├── Router.php                           # Rotas atualizadas
└── Middleware/
    └── AccessControl.php                # Menu de navegação atualizado
```

## 🎯 Próximos Passos

### Após a Instalação

1. **Criar primeira filial**
2. **Configurar usuários para a filial**
3. **Testar funcionalidades básicas**
4. **Configurar relatórios consolidados**

### Melhorias Futuras

1. **Sincronização de dados** entre filiais
2. **Backup automático** por filial
3. **Notificações** entre filiais
4. **API REST** para integrações externas

## 📞 Suporte

Se encontrar problemas:

1. **Verificar logs de erro** no navegador (F12)
2. **Verificar logs do PHP** se disponível
3. **Verificar conexão com banco** de dados
4. **Verificar permissões** de arquivos

## ✅ Checklist de Instalação

- [ ] PHP 8.2+ instalado e configurado
- [ ] PostgreSQL instalado e configurado
- [ ] Arquivo .env configurado
- [ ] Script de deploy executado
- [ ] Tabelas criadas no banco
- [ ] Menu de filiais aparecendo
- [ ] Primeira filial criada
- [ ] Relatórios consolidados funcionando

---

**🎉 Parabéns! O sistema de filiais está instalado e funcionando!**

Agora você pode gerenciar múltiplas filiais com controle centralizado de relatórios financeiros.













