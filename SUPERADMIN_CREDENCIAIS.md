# 🔐 Credenciais do SuperAdmin - Divino Lanches SaaS

## 📍 **LOCALIZAÇÃO DO LOGIN**

### URL de Acesso
```
http://localhost:8080/index.php?view=login_admin
```

### Credenciais Padrão
```
Usuário: superadmin
Senha: password
```

⚠️ **IMPORTANTE**: Altere a senha em produção!

---

## 🚀 **COMO ACESSAR**

### 1. Via Navegador
1. Abra seu navegador
2. Acesse: `http://localhost:8080/index.php?view=login_admin`
3. Digite as credenciais:
   - **Usuário**: `superadmin`
   - **Senha**: `password`
4. Clique em "Entrar"

### 2. Via Terminal (se estiver usando Docker)
```bash
# Acessar o container
docker exec -it divino-lanches-app bash

# Ou acessar diretamente
curl -X POST http://localhost:8080/index.php?view=login_admin \
  -d "login=superadmin&senha=password"
```

---

## 🎯 **FUNCIONALIDADES DO SUPERADMIN**

### Dashboard Principal
- **Estatísticas em tempo real**
- **Gestão de estabelecimentos**
- **Gestão de planos de assinatura**
- **Gestão de pagamentos**
- **Análises e relatórios**

### Seções Disponíveis
1. **Dashboard** - Visão geral do sistema
2. **Estabelecimentos** - CRUD completo de tenants
3. **Planos** - Gestão de planos de assinatura
4. **Assinaturas** - Controle de assinaturas ativas
5. **Pagamentos** - Histórico e gestão de pagamentos
6. **Análises** - Gráficos e métricas

---

## 🔧 **CONFIGURAÇÕES INICIAIS**

### 1. Alterar Senha do SuperAdmin
```sql
-- Conectar ao PostgreSQL
psql -U postgres -d divino_lanches

-- Alterar senha (substitua 'nova_senha' pela senha desejada)
UPDATE usuarios 
SET senha = '$2y$10$NOVA_SENHA_HASH_AQUI'
WHERE login = 'superadmin';
```

### 2. Gerar Hash da Senha em PHP
```php
<?php
echo password_hash('sua_nova_senha', PASSWORD_BCRYPT);
?>
```

### 3. Configurar Gateway de Pagamento (Asaas)
1. Copie `asaas.env.example` para `asaas.env`
2. Configure suas credenciais do Asaas:
```env
ASAAS_API_KEY=sua_api_key_aqui
ASAAS_API_URL=https://sandbox.asaas.com/api/v3
ASAAS_WEBHOOK_URL=https://seu-dominio.com/webhook/asaas.php
```

---

## 📊 **PLANOS DISPONÍVEIS**

### 1. Starter - R$ 49,90/mês
- 5 mesas
- 2 usuários
- 50 produtos
- 500 pedidos/mês
- Relatórios básicos
- Suporte por email

### 2. Professional - R$ 149,90/mês
- 15 mesas
- 5 usuários
- 200 produtos
- 2.000 pedidos/mês
- Relatórios avançados
- Suporte WhatsApp
- Backup diário
- API de acesso

### 3. Business - R$ 299,90/mês
- 30 mesas
- 10 usuários
- 500 produtos
- 5.000 pedidos/mês
- Relatórios customizados
- Suporte prioritário
- Backup diário
- API de acesso

### 4. Enterprise - R$ 999,90/mês
- Recursos ilimitados
- Relatórios customizados
- Suporte dedicado
- Backup em tempo real
- White label
- Integrações customizadas

---

## 🌐 **URLS IMPORTANTES**

### Sistema Principal
- **Login Admin**: `http://localhost:8080/index.php?view=login_admin`
- **Dashboard SuperAdmin**: `http://localhost:8080/index.php?view=superadmin_dashboard`
- **Página de Planos**: `http://localhost:8080/index.php?view=planos`
- **Onboarding**: `http://localhost:8080/index.php?view=onboarding`

### APIs
- **SuperAdmin API**: `http://localhost:8080/mvc/controller/SuperAdminController.php`
- **Webhook Asaas**: `http://localhost:8080/webhook/asaas.php`

---

## 🛠️ **TROUBLESHOOTING**

### Problema: "Usuário não encontrado"
**Solução**: Execute a migration do banco de dados
```bash
psql -U postgres -d divino_lanches -f database/init/10_create_saas_tables.sql
```

### Problema: "Acesso negado"
**Solução**: Verifique se está logado como superadmin (nível 999)

### Problema: "Erro de conexão com Asaas"
**Solução**: 
1. Verifique as credenciais em `asaas.env`
2. Teste a conexão via dashboard
3. Verifique se a URL do webhook está acessível

### Problema: "Dashboard não carrega"
**Solução**:
1. Verifique se todas as tabelas foram criadas
2. Execute a migration novamente
3. Verifique os logs em `logs/`

---

## 📝 **LOGS E DEBUG**

### Logs do Sistema
```bash
# Ver logs em tempo real
tail -f logs/app.log

# Ver logs de erro
tail -f logs/error.log
```

### Debug do Banco
```sql
-- Verificar se o superadmin existe
SELECT * FROM usuarios WHERE login = 'superadmin';

-- Verificar se as tabelas SaaS existem
\dt

-- Verificar planos cadastrados
SELECT * FROM planos;
```

---

## 🔒 **SEGURANÇA**

### Recomendações
1. **Altere a senha padrão** imediatamente
2. **Configure HTTPS** em produção
3. **Monitore os logs** regularmente
4. **Faça backup** dos dados regularmente
5. **Configure firewall** adequadamente

### Níveis de Acesso
- **999**: SuperAdmin (acesso total)
- **1**: Administrador do Tenant
- **0**: Operador comum

---

## 📞 **SUPORTE**

Se encontrar problemas:
1. Verifique os logs do sistema
2. Execute a migration novamente
3. Verifique as configurações do banco
4. Consulte a documentação completa em `SISTEMA_SAAS_DOCUMENTACAO.md`
