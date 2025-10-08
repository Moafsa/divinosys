# Guia de Integração IA - Divino Lanches

## Visão Geral

O sistema Divino Lanches agora inclui um assistente IA integrado que permite gerenciar produtos, ingredientes, categorias e pedidos através de comandos em linguagem natural. A IA pode processar texto, voz, imagens, PDFs e planilhas.

## Funcionalidades Principais

### 🤖 Assistente IA Conversacional
- **Chat em tempo real** com processamento de linguagem natural
- **Comandos em português** para todas as operações
- **Confirmação antes de executar** operações críticas
- **Histórico de conversas** com timestamps

### 📁 Processamento de Arquivos
- **Imagens**: Análise automática de produtos em fotos
- **PDFs**: Extração de informações de documentos
- **Planilhas**: Processamento de dados CSV/Excel
- **Upload drag-and-drop** com validação de tipos

### 🎤 Reconhecimento de Voz
- **Gravação de áudio** para comandos de voz
- **Processamento automático** de comandos falados
- **Feedback visual** durante gravação

### 🗄️ Operações de Banco de Dados
- **CRUD completo** para produtos, ingredientes, categorias
- **Gestão de pedidos** e status de mesas
- **Operações seguras** com confirmação obrigatória
- **Contexto automático** com dados atuais do sistema

## Como Usar

### 1. Configuração Inicial

#### Configurar Chave da OpenAI
1. Obtenha uma chave API da OpenAI em: https://platform.openai.com/api-keys
2. Adicione no arquivo `.env`:
```env
OPENAI_API_KEY=sua-chave-aqui
```

#### Verificar Permissões de Upload
Certifique-se que o diretório `uploads/ai_chat/` existe e tem permissões de escrita:
```bash
mkdir -p uploads/ai_chat
chmod 755 uploads/ai_chat
```

### 2. Acessando o Assistente IA

#### Widget Flutuante (Dashboard)
- **Botão flutuante** no canto inferior direito do dashboard
- **Acesso rápido** sem sair da página atual
- **Notificações** quando há mensagens não lidas

#### Página Dedicada
- **Menu lateral**: Assistente IA
- **Interface completa** com estatísticas do sistema
- **Comandos rápidos** pré-definidos

### 3. Comandos Disponíveis

#### 🛍️ Gestão de Produtos
```
Criar produto X-Burger com hambúrguer, queijo e alface - R$ 25,00
Listar todos os produtos
Editar produto X-Burger - mudar preço para R$ 28,00
Excluir produto X-Burger
Buscar produtos com "burger"
```

#### 🧩 Gestão de Ingredientes
```
Adicionar ingrediente Bacon com preço R$ 3,00
Listar ingredientes
Editar ingrediente Bacon - mudar preço para R$ 4,00
Excluir ingrediente Bacon
Buscar ingredientes tipo "proteina"
```

#### 🏷️ Gestão de Categorias
```
Criar categoria Bebidas
Listar categorias
Editar categoria Bebidas para "Bebidas e Refrigerantes"
Excluir categoria Bebidas
```

#### 📋 Gestão de Pedidos
```
Ver pedidos pendentes
Ver pedidos da mesa 5
Criar pedido para mesa 3 com 2 X-Burger
Alterar status do pedido #123 para "Pronto"
Ver mesas ocupadas
```

### 4. Processamento de Arquivos

#### Upload de Imagens
1. Clique no botão de anexo (📎)
2. Selecione uma imagem de produto
3. A IA analisará automaticamente:
   - Nome do produto
   - Ingredientes visíveis
   - Categoria estimada
   - Faixa de preço sugerida

#### Upload de Planilhas
1. Anexe um arquivo CSV/Excel
2. A IA processará os dados:
   - Identificação de colunas (nome, preço, categoria)
   - Sugestão de criação em lote
   - Validação de dados

#### Upload de PDFs
1. Anexe documentos de cardápio ou lista de preços
2. A IA extrairá informações:
   - Produtos e preços
   - Categorias
   - Descrições

### 5. Comandos de Voz

#### Ativação
1. Clique no botão do microfone (🎤)
2. Fale seu comando claramente
3. Clique novamente para parar a gravação
4. A IA processará automaticamente

#### Exemplos de Comandos de Voz
- "Criar produto X-Burger"
- "Listar produtos pendentes"
- "Ver mesas ocupadas"
- "Adicionar ingrediente Bacon"

## Arquitetura Técnica

### Componentes Principais

#### 1. OpenAIService (`system/OpenAIService.php`)
- **Classe principal** para integração com OpenAI
- **Processamento de mensagens** e determinação de ações
- **Operações CRUD** automatizadas
- **Contexto do sistema** em tempo real

#### 2. AI Chat Handler (`mvc/ajax/ai_chat.php`)
- **Endpoint AJAX** para comunicação com IA
- **Upload de arquivos** com validação
- **Execução de operações** no banco de dados
- **Busca e filtros** inteligentes

#### 3. Chat Components
- **AIChatWidget** (`mvc/views/components/AIChatWidget.php`): Widget flutuante
- **AIChat** (`mvc/views/AIChat.php`): Página dedicada
- **JavaScript** (`assets/js/ai-chat.js`): Interface interativa

### Fluxo de Dados

```
Usuário → Interface → AJAX → OpenAIService → OpenAI API
                ↓
            Resposta ← Processamento ← Análise ← OpenAI API
                ↓
        Confirmação → Execução → Banco de Dados
```

### Segurança

#### Validações Implementadas
- **Autenticação obrigatória** para todas as operações
- **Confirmação dupla** para operações destrutivas
- **Validação de arquivos** (tipo, tamanho, conteúdo)
- **Sanitização de dados** antes do processamento
- **Logs de auditoria** para todas as operações

#### Permissões
- **Usuário logado** requerido
- **Contexto de tenant/filial** obrigatório
- **Validação de sessão** em cada requisição

## Configurações Avançadas

### Personalização do Sistema Prompt
Edite o método `getSystemPrompt()` em `OpenAIService.php` para:
- Adicionar regras específicas do negócio
- Personalizar respostas da IA
- Definir formatos de dados específicos

### Limites e Quotas
- **Tamanho máximo de arquivo**: 10MB
- **Tipos permitidos**: Imagens, PDF, CSV, Excel
- **Timeout de API**: 30 segundos
- **Tokens máximos**: 2000 por resposta

### Monitoramento
- **Logs de erro** em `logs/` directory
- **Rastreamento de operações** no banco de dados
- **Métricas de uso** da API OpenAI

## Solução de Problemas

### Problemas Comuns

#### 1. "OpenAI API key not configured"
- Verifique se `OPENAI_API_KEY` está definida no `.env`
- Reinicie o servidor após adicionar a chave
- Teste a chave em: https://platform.openai.com/api-keys

#### 2. "Erro no upload de arquivo"
- Verifique permissões do diretório `uploads/ai_chat/`
- Confirme que o arquivo não excede 10MB
- Verifique se o tipo de arquivo é suportado

#### 3. "Erro de conexão com IA"
- Verifique conectividade com internet
- Confirme se a chave da API é válida
- Verifique logs de erro para detalhes

#### 4. "Operação não executada"
- Confirme se clicou em "Confirmar" na dialog
- Verifique se o usuário tem permissões adequadas
- Confirme se os dados estão válidos

### Logs e Debugging
- **Logs da aplicação**: `logs/app.log`
- **Logs de erro**: `logs/error.log`
- **Debug mode**: Ative `APP_DEBUG=true` no `.env`

## Próximos Passos

### Melhorias Planejadas
1. **Reconhecimento de voz** completo (Speech-to-Text)
2. **Análise de imagens** mais avançada
3. **Integração com WhatsApp** para comandos via chat
4. **Relatórios inteligentes** gerados por IA
5. **Sugestões automáticas** de produtos e preços

### Integrações Futuras
- **Sistemas de pagamento** via comandos de voz
- **Gestão de estoque** inteligente
- **Análise de vendas** com insights da IA
- **Automação de marketing** baseada em dados

## Suporte

Para dúvidas ou problemas:
1. Consulte este guia primeiro
2. Verifique os logs de erro
3. Teste com comandos simples
4. Entre em contato com o suporte técnico

---

**Versão**: 1.0  
**Última atualização**: Janeiro 2025  
**Compatibilidade**: PHP 8.0+, OpenAI API v1
