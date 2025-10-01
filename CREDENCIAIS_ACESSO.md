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

*Nota: Se admin123 não funcionar, tente:*
```
Usuário: admin  
Senha: password
```

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

---

## 📝 Logs Importantes

- **PostgreSQL**: Inicialização forçada funcionando
- **WuzAPI**: Migrações executadas com sucesso
- **Sistema**: Dados padrão inseridos (produtos, mesas, categorias)
- **Timeouts**: Configurados para 600s (10 minutos)

---

## 🚀 Próximos Passos

1. **Testar Login**: Acesse o sistema com as credenciais acima
2. **Conectar WhatsApp**: Use o QR Code da WuzAPI
3. **Configurar Produtos**: Verificar se os produtos padrão foram criados
4. **Testar Pedidos**: Fazer um pedido de teste

---

*Última atualização: 01/10/2025 - 01:58*
