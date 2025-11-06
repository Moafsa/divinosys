# 🔧 Debug: Chat da IA Não Aparece

## 🔍 Possíveis Causas

### **1. Código Não Deployado**
- O chat foi adicionado mas não foi feito `git pull`

### **2. Cache do Navegador**
- Navegador está usando versão antiga da página

### **3. Erro no Include do PHP**
- Caminho do arquivo incorreto
- Permissões

### **4. CSS Escondendo o Botão**
- z-index baixo
- display: none
- Botão atrás de outro elemento

### **5. JavaScript Não Carregado**
- Erro antes do código do chat
- aiChatWidget não inicializa

---

## ✅ Checklist de Verificação

### **Passo 1: Deploy**

```bash
cd ~/divino-lanches
git pull divinosys main
```

**Verificar que mobile_menu.php tem a linha:**
```php
include __DIR__ . '/AIChatWidget.php';
```

### **Passo 2: Limpar Cache do Navegador**

1. Abra qualquer página do sistema
2. Pressione **CTRL + F5** (força reload sem cache)
3. Ou **CTRL + SHIFT + DELETE** → Limpar cache

### **Passo 3: Verificar Console (F12)**

1. Abra DevTools (F12)
2. Aba **Console**
3. Procure por erros em vermelho
4. Procure por:
   ```
   AI Chat Widget not found
   ```

### **Passo 4: Verificar se Elemento Existe no DOM**

No Console (F12), digite:

```javascript
document.querySelector('.ai-chat-toggle')
```

**Resultado esperado:** `<button class="ai-chat-toggle"...>`  
**Se null:** Elemento não foi incluído

### **Passo 5: Verificar Logs do PHP**

```bash
docker logs divino-lanches-app --tail 100 | grep -i "chat\|widget"
```

Procure por: `AI Chat Widget not found`

---

## 🔧 Soluções

### **Solução A: Botão Escondido por CSS**

No Console (F12), execute:

```javascript
const btn = document.querySelector('.ai-chat-toggle');
if (btn) {
    btn.style.position = 'fixed';
    btn.style.bottom = '20px';
    btn.style.right = '20px';
    btn.style.zIndex = '99999';
    btn.style.display = 'flex';
    btn.style.background = '#6f42c1';
    console.log('✅ Chat button forced visible!');
} else {
    console.log('❌ Chat button not found in DOM!');
}
```

**Se aparecer o botão:** Era problema de CSS/z-index  
**Se não aparecer:** Include do PHP não está funcionando

### **Solução B: Forçar Reload Completo**

1. Feche todas as abas do sistema
2. Feche o navegador completamente
3. Reabra e acesse novamente
4. Pressione CTRL + F5

### **Solução C: Verificar se JS Carregou**

No Console:

```javascript
typeof aiChatWidget
```

**Esperado:** `object`  
**Se undefined:** JavaScript não carregou

---

## 📋 Execute e Me Diga

1. **Deploy feito?** (`git pull`)
2. **Cache limpo?** (CTRL + F5)
3. **Console mostra algum erro?**
4. **`document.querySelector('.ai-chat-toggle')` retorna algo?**
5. **`typeof aiChatWidget` retorna o quê?**

Com essas respostas vou identificar o problema exato!

