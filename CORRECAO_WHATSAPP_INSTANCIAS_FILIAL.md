# Correção do Problema das Instâncias WhatsApp na Filial

## 🔍 **Problema Identificado**

### **Instâncias WhatsApp não aparecem para a filial**
- **Sintoma**: Instâncias criadas não são exibidas na lista da filial
- **Causa**: Código usando valores fixos para `tenant_id` ao invés dos valores da sessão atual

## ✅ **Correções Implementadas**

### **Arquivo `mvc/ajax/configuracoes.php`**
```php
// Antes (incorreto)
$tenantId = 1; // Usar valor fixo

// Depois (correto)
$session = \System\Session::getInstance();
$tenantId = $session->getTenantId() ?? 1;
$filialId = $session->getFilialId();
```

### **Arquivo `system/WhatsApp/BaileysManager.php`**
```php
// Antes (incorreto)
public function getInstances($tenantId) {
    $instances = $this->db->fetchAll(
        "SELECT * FROM whatsapp_instances WHERE tenant_id = ? AND ativo = true ORDER BY created_at DESC",
        [$tenantId]
    );

// Depois (correto)
public function getInstances($tenantId, $filialId = null) {
    if ($filialId !== null) {
        $instances = $this->db->fetchAll(
            "SELECT * FROM whatsapp_instances WHERE tenant_id = ? AND filial_id = ? AND ativo = true ORDER BY created_at DESC",
            [$tenantId, $filialId]
        );
    } else {
        $instances = $this->db->fetchAll(
            "SELECT * FROM whatsapp_instances WHERE tenant_id = ? AND ativo = true ORDER BY created_at DESC",
            [$tenantId]
        );
    }
```

## 🎯 **Resultado Esperado**

Após as correções:
- ✅ **Listagem de instâncias**: Agora mostra instâncias da filial atual
- ✅ **Isolamento por tenant/filial**: Funcionando corretamente
- ✅ **Criação de instâncias**: Já estava funcionando (usava valores da sessão)
- ✅ **Filtros corretos**: Instâncias filtradas por tenant e filial

## 📝 **Como Testar**

1. **Faça login na filial** (tenant 24, filial 2)
2. **Vá para Configurações > WhatsApp - WuzAPI**
3. **Verifique se as instâncias criadas aparecem na lista**
4. **Teste criando uma nova instância**

## 🚨 **Notas Importantes**

- O problema era que o código estava usando `tenantId = 1` fixo
- Agora usa os valores corretos da sessão atual (tenant 24, filial 2)
- O método `getInstances` agora suporta filtro por filial
- O isolamento por tenant/filial agora funciona corretamente para instâncias WhatsApp
