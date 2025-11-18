# Correção das Instâncias WhatsApp

## 🔍 Problema Identificado

As instâncias WhatsApp criadas na filial desapareciam após serem criadas porque:
1. **Valor padrão incorreto**: `$filialId = $filialId ?: 1` estava forçando filial_id = 1
2. **Filtro por tenant**: Instâncias eram criadas com tenant_id correto mas filial_id errado
3. **Listagem incorreta**: Query de listagem não encontrava instâncias com filial_id errado

## ✅ Correção Implementada

### **Arquivo `system/WhatsApp/BaileysManager.php`**

**Problema**: Valor padrão incorreto para filial_id
```php
// Antes (incorreto)
$filialId = $filialId ?: 1;

// Depois (correto)
$filialId = $filialId ?: null;
```

**Mudanças**:
- Alterado valor padrão de `1` para `null`
- Agora usa o filial_id correto da sessão
- Mantém compatibilidade com sistema sem filiais

## 🧪 Script de Debug Criado

**`debug_instancias_whatsapp.php`** - Debug das instâncias WhatsApp:
- Verifica todas as instâncias no banco
- Verifica instâncias do tenant atual
- Testa AJAX de listagem
- Verifica estrutura da tabela

## 📋 Como Testar

### 1. Teste o Debug
Execute `debug_instancias_whatsapp.php` para verificar:
- Se há instâncias no banco
- Se instâncias do tenant aparecem
- Se AJAX funciona corretamente

### 2. Teste a Criação de Instâncias
1. Acesse `localhost:8080/index.php?view=configuracoes`
2. Vá para a seção "WhatsApp - WuzAPI"
3. Clique em "+ Nova Instância"
4. Preencha os dados e crie a instância
5. Verifique se a instância aparece na listagem

## 🎯 Resultado Esperado

Após essa correção:
- ✅ **Instâncias aparecem**: Instâncias criadas são exibidas corretamente
- ✅ **Filial correta**: Instâncias são criadas com filial_id correto
- ✅ **Isolamento funcionando**: Mostra apenas instâncias do tenant
- ✅ **Persistência**: Instâncias não desaparecem após criação

## 🔧 Arquivos Modificados

- `system/WhatsApp/BaileysManager.php` - Corrigido valor padrão de filial_id
- `debug_instancias_whatsapp.php` - Script de debug das instâncias

## 📝 Próximos Passos

1. **Execute o debug** para verificar o estado atual das instâncias
2. **Teste criar uma nova instância** e verificar se aparece
3. **Verifique isolamento** confirmando que mostra apenas instâncias da filial
4. **Monitore logs** para identificar possíveis problemas restantes

## 🚨 Notas Importantes

- A correção mantém compatibilidade com sistema sem filiais (filial_id = null)
- O valor padrão agora é `null` ao invés de `1`
- A instância é criada com o filial_id correto da sessão
- A listagem filtra corretamente por tenant_id
