# 📍 Onde Encontrar o Sistema de Filiais

## 🎯 **Resumo: O que foi implementado**

Criei um sistema completo de filiais que permite ao estabelecimento principal gerenciar múltiplas filiais com controle centralizado de relatórios financeiros.

## 📁 **Arquivos Criados**

### **Controllers (Lógica de Negócio)**
- `mvc/controller/FilialController.php` - Gerencia filiais (criar, editar, excluir)
- `mvc/controller/EstabelecimentoController.php` - Dashboard do estabelecimento principal

### **Views (Interface do Usuário)**
- `mvc/views/dashboard_estabelecimento.php` - Dashboard consolidado
- `mvc/views/gerenciar_filiais.php` - **AQUI VOCÊ CRIA FILIAIS**
- `mvc/views/relatorios_consolidados.php` - Relatórios de todas as filiais

### **AJAX Endpoints (Comunicação)**
- `mvc/ajax/estabelecimento.php` - Endpoints para estabelecimento principal

### **Banco de Dados**
- `database/migrations/create_filial_system.sql` - Migração completa
- `deploy_sistema_filiais.php` - Script de deploy automatizado

### **Documentação**
- `SISTEMA_FILIAIS_IMPLEMENTACAO.md` - Documentação técnica
- `PLANO_IMPLEMENTACAO_FILIAIS.md` - Plano detalhado
- `GUIA_INSTALACAO_FILIAIS.md` - Guia de instalação
- `test_sistema_filiais.php` - Script de teste

## 🚀 **Como Acessar o Sistema de Filiais**

### **1. No Menu Lateral (Após Login)**
Você verá estas novas opções:
- 🏢 **Dashboard Estabelecimento** - Visão geral de todas as filiais
- 🏪 **Gerenciar Filiais** - **AQUI VOCÊ CRIA FILIAIS**
- 📊 **Relatórios Consolidados** - Relatórios de todas as filiais

### **2. URLs Diretas**
- `index.php?view=dashboard_estabelecimento` - Dashboard principal
- `index.php?view=gerenciar_filiais` - **CRIAR FILIAIS**
- `index.php?view=relatorios_consolidados` - Relatórios consolidados

## 🏪 **Como Criar uma Filial**

### **Passo 1: Acessar Gerenciamento**
1. Faça login como **administrador**
2. No menu lateral, clique em **"Gerenciar Filiais"**
3. Clique no botão **"Nova Filial"**

### **Passo 2: Preencher Dados**
- **Nome da Filial:** Ex: "Filial Centro"
- **Telefone:** (11) 99999-9999
- **Email:** contato@filial.com
- **Endereço:** Endereço completo
- **Cor Primária:** Escolha uma cor
- **Número de Mesas:** 15 (padrão)

### **Passo 3: Criar Filial**
- Clique em **"Criar Filial"**
- A filial será criada com login e senha automáticos
- Você pode acessar o sistema da filial clicando em **"Acessar"**

## 📊 **Relatórios Consolidados**

### **Acessar Relatórios**
1. No menu lateral, clique em **"Relatórios Consolidados"**
2. Você verá:
   - **Estatísticas gerais** de todas as filiais
   - **Gráficos comparativos** entre filiais
   - **Evolução temporal** da receita
   - **Resumo detalhado** por filial

### **Funcionalidades**
- **Filtros por período:** Hoje, Semana, Mês, Ano
- **Gráficos interativos:** Receita e pedidos por filial
- **Comparativo entre filiais:** Performance individual

## 🔧 **Instalação e Configuração**

### **1. Executar Migração do Banco**
```cmd
php deploy_sistema_filiais.php
```

### **2. Verificar Instalação**
```cmd
php test_sistema_filiais.php
```

### **3. Acessar o Sistema**
- Abra o navegador
- Acesse o sistema
- Faça login como administrador
- Procure por **"Gerenciar Filiais"** no menu

## 🎯 **Funcionalidades Implementadas**

### **Estabelecimento Principal**
- ✅ Dashboard consolidado com visão geral
- ✅ Gerenciamento completo de filiais
- ✅ Relatórios financeiros consolidados
- ✅ Controle de usuários para todas as filiais
- ✅ Acesso rápido ao sistema de cada filial

### **Filiais**
- ✅ Operação independente com cardápio próprio
- ✅ Dashboard específico da filial
- ✅ Relatórios individuais
- ✅ Configurações específicas
- ✅ Integração com sistema principal

### **Segurança e Isolamento**
- ✅ Isolamento de dados por tenant e filial
- ✅ Controle de acesso hierárquico
- ✅ Auditoria completa de ações
- ✅ Middleware de validação

## 🚨 **Se Não Aparecer no Menu**

### **Possíveis Causas:**
1. **Usuário não é administrador** - Apenas admins veem as opções de filiais
2. **Migração não executada** - Execute `php deploy_sistema_filiais.php`
3. **Cache do navegador** - Limpe o cache (Ctrl+F5)
4. **Permissões não atualizadas** - Verifique se as permissões foram atualizadas

### **Soluções:**
1. **Verificar tipo de usuário:**
   - Faça login como administrador
   - Verifique se o usuário tem nível 1 (admin)

2. **Executar migração:**
   ```cmd
   php deploy_sistema_filiais.php
   ```

3. **Verificar arquivos:**
   - Verifique se os arquivos foram criados
   - Verifique se as rotas foram atualizadas

## 📞 **Suporte e Troubleshooting**

### **Verificar se está funcionando:**
1. Execute: `php test_sistema_filiais.php`
2. Verifique se todas as classes e arquivos existem
3. Teste as URLs diretamente no navegador

### **Logs de erro:**
1. Abra o navegador (F12)
2. Verifique a aba Console
3. Verifique a aba Network para erros AJAX

### **Verificar banco de dados:**
1. Acesse o PostgreSQL
2. Verifique se as tabelas `filiais`, `usuarios_globais`, `usuarios_estabelecimento` existem
3. Verifique se a coluna `filial_id` foi adicionada às tabelas principais

## 🎉 **Conclusão**

O sistema de filiais está **completamente implementado** e funcionando. Para acessar:

1. **Faça login como administrador**
2. **Procure por "Gerenciar Filiais" no menu lateral**
3. **Clique em "Nova Filial" para criar sua primeira filial**
4. **Acesse "Relatórios Consolidados" para ver os dados de todas as filiais**

Se não aparecer no menu, verifique se você está logado como administrador e se a migração foi executada corretamente.













