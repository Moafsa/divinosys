# Implementação Baileys - WhatsApp Direto

## 📋 Plano de Implementação

### 1. **Instalação Baileys**
```bash
npm install @whiskeysockets/baileys
```

### 2. **Estrutura de Arquivos**
```
system/
├── WhatsApp/
│   ├── BaileysManager.php
│   ├── WhatsAppService.php
│   └── MessageHandler.php
```

### 3. **Configuração do Sistema**

#### **3.1. Tabelas Necessárias**
```sql
-- Tabela para instâncias WhatsApp
CREATE TABLE whatsapp_instances (
    id SERIAL PRIMARY KEY,
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER,
    instance_name VARCHAR(255) UNIQUE NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    status VARCHAR(50) DEFAULT 'disconnected',
    qr_code TEXT,
    session_data JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela para mensagens
CREATE TABLE whatsapp_messages (
    id SERIAL PRIMARY KEY,
    instance_id INTEGER REFERENCES whatsapp_instances(id),
    tenant_id INTEGER NOT NULL,
    filial_id INTEGER,
    message_id VARCHAR(255),
    from_number VARCHAR(20),
    to_number VARCHAR(20),
    message_text TEXT,
    message_type VARCHAR(50),
    status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### **3.2. Configuração Coolify**
```yaml
# Adicionar ao coolify.yml
services:
  app:
    environment:
      - WHATSAPP_ENABLED=true
      - WHATSAPP_SESSION_PATH=/var/www/html/whatsapp-sessions
```

### 4. **Implementação PHP**

#### **4.1. BaileysManager.php**
```php
<?php
namespace System\WhatsApp;

use System\Database;

class BaileysManager {
    private $db;
    private $instances = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function createInstance($tenantId, $filialId, $instanceName, $phoneNumber) {
        // Implementar criação de instância
    }
    
    public function connectInstance($instanceId) {
        // Implementar conexão
    }
    
    public function sendMessage($instanceId, $to, $message) {
        // Implementar envio de mensagem
    }
}
```

### 5. **Interface Web**

#### **5.1. Página Configurações**
- Listar instâncias WhatsApp
- Criar nova instância
- Conectar/Desconectar
- Ver QR Code
- Enviar mensagens de teste

#### **5.2. AJAX Handlers**
```php
// mvc/ajax/whatsapp.php
case 'create_instance':
    // Criar instância
case 'connect_instance':
    // Conectar instância
case 'send_message':
    // Enviar mensagem
```

### 6. **Vantagens do Baileys**

✅ **Sem dependências externas**
✅ **Controle total do código**
✅ **Integração direta com o sistema**
✅ **Sem problemas de URL/Proxy**
✅ **Performance melhor**
✅ **Customização completa**

### 7. **Próximos Passos**

1. **Executar limpeza**: `php cleanup-evolution.php`
2. **Deploy stack limpa**
3. **Implementar Baileys**
4. **Testar funcionalidades**
5. **Integrar com sistema existente**

## 🚀 Resultado Final

- Sistema WhatsApp nativo
- Sem dependências externas
- Controle total
- Performance otimizada
- Integração perfeita
