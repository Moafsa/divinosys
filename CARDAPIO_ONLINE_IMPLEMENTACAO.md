# Cardápio Online - Implementação Completa

## Visão Geral

Sistema de cardápio online para franquias, permitindo que cada filial tenha seu próprio cardápio público com funcionalidades similares ao iFood/Aiqfome.

## Funcionalidades Implementadas

### ✅ 1. Cardápio Online por Franquia
- Cada franquia pode gerar seu próprio cardápio online
- URL de acesso: `?view=cardapio_online&tenant=ID&filial=ID`
- Design responsivo e moderno, similar ao iFood
- Exibe logo, dados do estabelecimento e produtos

### ✅ 2. Sistema de Produtos
- Produtos são carregados diretamente da tabela `produtos`
- Agrupados por categoria
- Exibe imagem, nome, descrição e preço
- Apenas produtos ativos são exibidos

### ✅ 3. Sistema de Carrinho
- Carrinho gerenciado via localStorage
- Persiste entre sessões do navegador
- Interface lateral deslizante (sidebar)
- Controle de quantidade e remoção de itens

### ✅ 4. Tipos de Entrega
- **Retirada no Balcão**: Sem taxa de entrega
- **Delivery**: Com taxa de entrega (fixa ou calculada)

### ✅ 5. Cálculo de Taxa de Entrega
- **Taxa Fixa**: Configurável por filial (`taxa_delivery_fixa`)
- **Cálculo por Distância**: Integração com n8n webhook
  - Envia endereço do estabelecimento e do cliente
  - Recebe distância e valor calculado
  - Configurável via `n8n_webhook_distancia` na filial

### ✅ 6. Integração com Asaas
- Pagamento online via PIX
- Integração com configurações do Asaas já existentes
- Criação automática de cliente no Asaas
- Geração de link de pagamento
- Suporte a pagamento na hora (dinheiro, PIX ou cartão)

### ✅ 7. Criação de Pedidos
- Pedidos são criados na tabela `pedido`
- Itens são salvos em `pedido_itens`
- Pedidos aparecem automaticamente na tela de pedidos
- Suporte a delivery e retirada no balcão

## Estrutura de Arquivos

### Migrations
- `database/migrations/create_cardapio_online_fields.sql` - Campos de configuração do cardápio online
- `database/migrations/add_asaas_payment_fields_pedido.sql` - Campos de pagamento Asaas no pedido

### Views
- `mvc/views/cardapio_online.php` - Interface pública do cardápio online

### API Endpoints
- `mvc/ajax/pedidos_online.php` - Endpoint para criar pedidos online

### Rotas
- Adicionada rota pública `cardapio_online` em `system/Router.php`
- Mapeamento de ação `criar_pedido_online` em `index.php`

## Configuração

### 1. Executar Migrations

```sql
-- Executar no banco de dados
\i database/migrations/create_cardapio_online_fields.sql
\i database/migrations/add_asaas_payment_fields_pedido.sql
```

### 2. Configurar Filial

Atualizar a filial para ativar o cardápio online:

```sql
UPDATE filiais 
SET 
    cardapio_online_ativo = true,
    taxa_delivery_fixa = 5.00, -- Taxa fixa (se não usar cálculo)
    usar_calculo_distancia = true, -- true para usar n8n, false para taxa fixa
    n8n_webhook_distancia = 'https://seu-n8n.com/webhook/calcular-distancia',
    raio_entrega_km = 10.00,
    tempo_medio_preparo = 30,
    aceita_pagamento_online = true,
    aceita_pagamento_na_hora = true
WHERE id = FILIAL_ID;
```

### 3. Configurar n8n Webhook (Opcional)

Se usar cálculo de distância via n8n, criar um workflow que:

**Entrada (POST):**
```json
{
    "endereco_estabelecimento": "Rua Exemplo, 123, Cidade",
    "endereco_cliente": "Rua Cliente, 456, Cidade"
}
```

**Saída (JSON):**
```json
{
    "success": true,
    "distancia": 5.2,
    "valor": 8.50
}
```

## Uso

### Acesso ao Cardápio

```
http://seudominio.com/index.php?view=cardapio_online&tenant=1&filial=1
```

### Fluxo do Cliente

1. Cliente acessa o cardápio online
2. Navega pelos produtos organizados por categoria
3. Adiciona produtos ao carrinho
4. Clica em "Finalizar Pedido"
5. Escolhe tipo de entrega (retirada ou delivery)
6. Se delivery, informa endereço e calcula taxa
7. Preenche dados pessoais
8. Escolhe forma de pagamento (online ou na hora)
9. Confirma pedido
10. Se pagamento online, é redirecionado para página de pagamento Asaas
11. Pedido aparece na tela de pedidos do estabelecimento

## Campos Adicionados

### Tabela `filiais`
- `cardapio_online_ativo` (BOOLEAN) - Ativa/desativa cardápio online
- `taxa_delivery_fixa` (DECIMAL) - Taxa fixa de entrega
- `usar_calculo_distancia` (BOOLEAN) - Usar cálculo via n8n
- `n8n_webhook_distancia` (VARCHAR) - URL do webhook n8n
- `raio_entrega_km` (DECIMAL) - Raio máximo de entrega
- `tempo_medio_preparo` (INTEGER) - Tempo médio em minutos
- `horario_funcionamento` (JSONB) - Horários de funcionamento
- `aceita_pagamento_online` (BOOLEAN) - Aceita pagamento online
- `aceita_pagamento_na_hora` (BOOLEAN) - Aceita pagamento na hora

### Tabela `pedido`
- `asaas_payment_id` (VARCHAR) - ID do pagamento no Asaas
- `asaas_payment_url` (VARCHAR) - URL do pagamento
- `telefone_cliente` (VARCHAR) - Telefone do cliente
- `tipo_entrega` (VARCHAR) - Tipo: 'pickup' ou 'delivery'

## Segurança

- Validação de dados de entrada
- Verificação de filial ativa e cardápio habilitado
- Validação de produtos ativos
- Sanitização de dados do cliente
- CORS configurado para permitir requisições do cardápio

## Próximos Passos (Opcional)

1. **Sistema de Avaliações**: Permitir clientes avaliarem produtos/pedidos
2. **Cupons de Desconto**: Sistema de cupons promocionais
3. **Histórico de Pedidos**: Página para cliente ver seus pedidos anteriores
4. **Notificações**: Notificações push/email quando pedido é atualizado
5. **Rastreamento de Entrega**: Status em tempo real do pedido
6. **Múltiplos Métodos de Pagamento**: Cartão de crédito além de PIX

## Notas Técnicas

- O carrinho usa localStorage com chave específica por filial
- Pedidos online usam mesa_id '998' para retirada e '999' para delivery
- Integração com Asaas usa configurações da filial/tenant
- Pedidos aparecem automaticamente na tela de pedidos (filtro por tenant_id e filial_id)

## Suporte

Para dúvidas ou problemas, verificar:
1. Logs do servidor (`error_log`)
2. Console do navegador (F12)
3. Configurações da filial no banco de dados
4. Status do webhook n8n (se usando cálculo de distância)

