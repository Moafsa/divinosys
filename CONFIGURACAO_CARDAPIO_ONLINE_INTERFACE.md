# ✅ Configuração do Cardápio Online via Interface

## 🎯 Problema Resolvido

Agora você **NÃO precisa mais executar SQL manualmente**! Tudo pode ser configurado diretamente na interface do sistema.

## 📍 Onde Encontrar

1. Acesse o sistema: http://localhost:8080
2. Faça login
3. Vá em **Configurações** (menu lateral)
4. Role até a seção **"Cardápio Online"**

## ⚙️ Funcionalidades da Interface

### 1. Ativar/Desativar Cardápio Online
- Switch para ativar ou desativar o cardápio
- Quando ativado, mostra o link do cardápio automaticamente

### 2. Upload de Logo
- Campo para fazer upload da logo do estabelecimento
- Preview da logo atual
- Formatos aceitos: JPG, PNG, GIF, WEBP (máx. 2MB)
- Logo é salva em `uploads/logos/`

### 3. Configurações de Delivery
- **Taxa de Entrega Fixa**: Valor em R$ quando não usa cálculo automático
- **Raio de Entrega**: Distância máxima em km
- **Cálculo Automático**: Switch para ativar cálculo via n8n
- **Webhook n8n**: Campo para URL do webhook (aparece quando cálculo está ativado)

### 4. Tempo de Preparo
- Campo numérico para tempo médio em minutos

### 5. Formas de Pagamento
- Switch para aceitar pagamento online (via Asaas)
- Switch para aceitar pagamento na hora

### 6. Visualizar Cardápio
- Botão "Visualizar Cardápio" abre o cardápio em nova aba
- Link do cardápio aparece automaticamente quando ativado

## 🎨 Integração Automática

### Cor do Sistema
- O cardápio usa **automaticamente** a cor primária configurada em **Configurações → Aparência**
- Se a filial tiver cor própria, usa a cor da filial
- Caso contrário, usa a cor do tenant
- Fallback: #007bff (azul padrão)

### Logo do Estabelecimento
- O cardápio usa **automaticamente** a logo configurada
- Se a filial tiver logo, usa a logo da filial
- Caso contrário, usa a logo do tenant
- Se não houver logo, mostra iniciais do nome

### Produtos
- O cardápio mostra **automaticamente** todos os produtos ativos da filial
- Produtos são agrupados por categoria
- Apenas produtos com `ativo = true` são exibidos

## 📝 Como Usar

### Passo 1: Ativar o Cardápio
1. Vá em **Configurações**
2. Role até **Cardápio Online**
3. Ative o switch **"Ativar Cardápio Online"**
4. Clique em **"Salvar Configurações"**

### Passo 2: Configurar Logo (Opcional)
1. Clique em **"Escolher arquivo"** no campo Logo
2. Selecione uma imagem (JPG, PNG, GIF ou WEBP)
3. Clique em **"Salvar Configurações"**

### Passo 3: Configurar Delivery (Opcional)
1. Defina a **Taxa de Entrega Fixa** (ex: 5.00)
2. Defina o **Raio de Entrega** (ex: 10 km)
3. Se quiser usar cálculo automático:
   - Ative **"Usar cálculo automático de distância via n8n"**
   - Cole a URL do webhook n8n
4. Clique em **"Salvar Configurações"**

### Passo 4: Visualizar
1. Clique em **"Visualizar Cardápio"** ou copie o link exibido
2. O cardápio abre em nova aba

## 🔄 Fluxo Completo

```
Configurações → Cardápio Online
    ↓
Ativar Cardápio Online (switch)
    ↓
Upload Logo (opcional)
    ↓
Configurar Taxa de Entrega
    ↓
Salvar Configurações
    ↓
Cardápio Online Disponível!
```

## ✨ Melhorias Implementadas

1. ✅ **Interface de Configuração Completa**
   - Não precisa mais executar SQL manualmente
   - Tudo configurável via interface

2. ✅ **Upload de Logo**
   - Upload direto na interface
   - Preview da logo atual
   - Validação de tipo e tamanho

3. ✅ **Cor Automática**
   - Usa cor primária do sistema automaticamente
   - Busca em filial_settings → filial → tenant

4. ✅ **Link Direto**
   - Link do cardápio aparece automaticamente
   - Botão para visualizar em nova aba

5. ✅ **Produtos Automáticos**
   - Produtos vêm direto da tabela produtos
   - Filtrados por tenant_id e filial_id
   - Apenas produtos ativos

## 📂 Arquivos Modificados

- `mvc/views/configuracoes.php` - Adicionada seção de configuração do cardápio online
- `mvc/ajax/configuracoes.php` - Adicionado endpoint `salvar_cardapio_online`
- `mvc/views/cardapio_online.php` - Atualizado para usar cor e logo do sistema automaticamente

## 🎉 Resultado Final

Agora você pode:
- ✅ Ativar o cardápio online diretamente nas configurações
- ✅ Fazer upload da logo do estabelecimento
- ✅ Configurar todas as opções de delivery e pagamento
- ✅ Visualizar o cardápio com um clique
- ✅ O cardápio usa automaticamente a cor e logo do sistema
- ✅ Produtos aparecem automaticamente do banco de dados

**Não é mais necessário executar SQL manualmente!** 🎊

