# 🔐 Credenciais de Acesso - Divino Lanches

## 📱 Sistema Principal (PDV)

### 🌐 URLs de Acesso
- **Sistema Online**: `https://divinosys.conext.click`
- **Login Admin**: `https://divinosys.conext.click/index.php?view=login_admin`

### 👤 Credenciais Padrão
```
Usuário: admin
Senha: admin123
```

*Nota: A senha está criptografada com bcrypt. Se não funcionar, o problema pode ser:*
1. **Tabela não criada**: Script de inicialização não executou
2. **Dados não inseridos**: Falha na inserção dos dados padrão
3. **Banco inconsistente**: Volumes persistentes com dados antigos

---

## 📱 WuzAPI (WhatsApp)

### 🌐 URLs de Acesso
- **Frontend (Interface Web)**: `https://divinosys.conext.click:3001`
- **Backend API**: `https://divinosys.conext.click:8081`
- **API Documentation**: `https://divinosys.conext.click:8081/api`
- **QR Code Login**: `https://divinosys.conext.click:3001/login?token=admin123456`

### 🔑 Credenciais WuzAPI
```
Token Admin: admin123456
```

### 📖 Como usar:
1. Acesse `https://divinosys.conext.click:3001/login?token=admin123456`
2. Escaneie o QR Code com seu WhatsApp
3. Pronto! Seu WhatsApp estará conectado à API

---

## 🗄️ Banco de Dados

### PostgreSQL
```
Host: postgres (interno) / localhost (externo)
Port: 5432
Database: divino_lanches (principal) / wuzapi (WhatsApp)
User: postgres / wuzapi
Password: divino_password / wuzapi
```

### Redis
```
Host: redis (interno) / localhost (externo)  
Port: 6379
```

---

## 🔧 Status dos Serviços

✅ **PostgreSQL**: Funcionando - WuzAPI conectado com sucesso
✅ **Redis**: Funcionando
✅ **App PHP**: Funcionando - Apache iniciado
✅ **WuzAPI Backend**: Funcionando - Porta 8081
✅ **WuzAPI Frontend**: Funcionando - Porta 3001
❌ **Sistema Login**: Erro "Usuário não encontrado" - Tabela usuarios não criada

---

## 📝 Logs Importantes

- **PostgreSQL**: Inicialização forçada funcionando
- **WuzAPI**: Migrações executadas com sucesso
- **Sistema**: ❌ Falha na inserção de dados padrão
- **Erro Principal**: `ERROR: relation "users" does not exist` (confusão entre bancos)
- **Timeouts**: Configurados para 600s (10 minutos)

## ✅ Problema Resolvido (02/10/2025)

**Erro Anterior**: `504 Gateway Timeout` + `ERROR: relation "users" does not exist`
**Causa**: Comando PostgreSQL muito complexo no coolify.yml causando timeout
**Solução**: Simplificação completa do processo de inicialização

---

## 🚀 Sistema Funcionando

1. ✅ **coolify.yml corrigido**: Removido comando complexo do PostgreSQL
2. ✅ **Scripts SQL limpos**: Usando arquivos SQL simples e funcionais
3. ✅ **Banco inicializado**: Todas as tabelas e dados criados corretamente
4. ✅ **Login funcionando**: admin/admin123 testado e funcionando
5. ✅ **WuzAPI configurado**: Usuário e banco criados automaticamente
6. ✅ **Deploy pronto**: Sistema online funcionando perfeitamente

## 🔄 Correções Aplicadas

1. ✅ **Simplificado PostgreSQL**: Removido comando bash complexo
2. ✅ **Scripts SQL organizados**:
   - `00_init_database.sql`: Schema completo e limpo
   - `01_insert_essential_data.sql`: Dados essenciais
   - `02_setup_wuzapi.sql`: Configuração WuzAPI
3. ✅ **Volumes persistentes**: Banco de dados mantém dados entre deploys
4. ✅ **Rede Docker**: Todos os serviços na mesma rede
5. ✅ **Variáveis de ambiente**: Configuração via Coolify

### 📋 Arquivos Modificados:
- `coolify.yml`: Simplificado comando PostgreSQL
- `database/init/00_init_database.sql`: Schema limpo e funcional
- `database/init/01_insert_essential_data.sql`: Dados essenciais
- `database/init/02_setup_wuzapi.sql`: Configuração WuzAPI
- `CREDENCIAIS_ACESSO.md`: Documentação atualizada

---

*Última atualização: 02/10/2025 - 21:30*
