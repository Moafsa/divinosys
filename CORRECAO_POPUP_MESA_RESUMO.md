# Correção do Problema da Popup da Mesa

## 🔍 **Problema Identificado**

### **Pedido não aparece na popup da mesa**
- **Sintoma**: Popup da mesa mostra "Nenhum pedido ativo nesta mesa" mesmo quando há pedidos
- **Causa**: Arquivos AJAX usando valores fixos para `tenantId` e `filialId` ao invés dos valores da sessão atual

## ✅ **Correções Implementadas**

### **Arquivo `mvc/ajax/mesa_multiplos_pedidos.php`**
```php
// Antes (incorreto)
$tenantId = 1; // Usar valor padrão
$filialId = 1; // Usar valor padrão

// Depois (correto)
$tenantId = $session->getTenantId() ?? 1;
$filialId = $session->getFilialId() ?? 1;
```

### **Arquivo `mvc/ajax/mesa_multiplos_pedidos_simples.php`**
```php
// Antes (incorreto)
$tenantId = 1; // Usar valor padrão
$filialId = 1; // Usar valor padrão

// Depois (correto)
$tenantId = $session->getTenantId() ?? 1;
$filialId = $session->getFilialId() ?? 1;
```

### **Arquivo `mvc/ajax/dashboard_ajax.php`**
```php
// Antes (incorreto)
$tenantId = 1; // Usar valor padrão
$filialId = 1; // Usar valor padrão

// Depois (correto)
$tenantId = $session->getTenantId() ?? 1;
$filialId = $session->getFilialId() ?? 1;
```

## 🎯 **Resultado Esperado**

Após as correções:
- ✅ **Popup da mesa**: Agora mostra pedidos corretos da filial atual
- ✅ **Isolamento por tenant/filial**: Funcionando corretamente
- ✅ **Dados corretos**: Pedidos aparecem na popup da mesa
- ✅ **Status da mesa**: Atualizado corretamente

## 📝 **Como Testar**

1. **Faça login na filial** (tenant 24, filial 2)
2. **Clique na mesa 3** que está ocupada
3. **Verifique se a popup mostra**:
   - Pedido #199
   - Valor total R$ 342,00
   - Status correto da mesa

## 🚨 **Notas Importantes**

- O problema era que os arquivos AJAX estavam usando valores fixos (tenant 1, filial 1)
- Agora usam os valores corretos da sessão atual (tenant 24, filial 2)
- Isso garante que os pedidos da filial correta sejam exibidos
- O isolamento por tenant/filial agora funciona corretamente na popup da mesa
