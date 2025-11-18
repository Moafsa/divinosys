# Sistema de Exportação e Importação de Dados

## Visão Geral

O sistema agora possui funcionalidades completas de exportação e importação de dados em formato CSV, permitindo que os usuários exportem dados para edição no Excel e importem as alterações de volta ao sistema.

## Funcionalidades Implementadas

### 1. Exportação de Dados

#### Página de Produtos (`/gerenciar_produtos`)
- **Exportar Produtos**: Exporta todos os produtos com suas categorias e ingredientes
- **Exportar Categorias**: Exporta todas as categorias de produtos
- **Exportar Ingredientes**: Exporta todos os ingredientes disponíveis

#### Página Financeira (`/financeiro`)
- **Exportar Lançamentos**: Exporta todos os lançamentos financeiros
- **Exportar Pedidos**: Exporta todos os pedidos do sistema
- **Exportar Pedidos Quitados**: Exporta apenas pedidos com status "quitado"
- **Exportar Pedidos Fiados**: Exporta apenas pedidos com status "fiado"

### 2. Importação de Dados

#### Página de Produtos
- **Importar Produtos**: Importa produtos, categorias e ingredientes
- **Importar Categorias**: Importa categorias de produtos
- **Importar Ingredientes**: Importa ingredientes

#### Página Financeira
- **Importar Lançamentos**: Importa lançamentos financeiros
- **Importar Pedidos**: Importa pedidos do sistema

## Como Usar

### Exportação

1. **Acesse a página desejada** (Produtos ou Financeiro)
2. **Clique no botão "Exportar"** no cabeçalho da página
3. **Selecione o tipo de dados** que deseja exportar
4. **O arquivo CSV será baixado automaticamente**

### Importação

1. **Acesse a página desejada** (Produtos ou Financeiro)
2. **Clique no botão "Importar"** no cabeçalho da página
3. **Selecione o tipo de dados** que deseja importar
4. **Escolha o arquivo CSV** exportado anteriormente
5. **Clique em "Importar"** para processar os dados

## Formato dos Arquivos CSV

### Produtos
```csv
ID,Código,Nome,Descrição,Preço Normal,Preço Mini,Ativo,Imagem,Categoria ID,Categoria Nome,Ingredientes,Data Criação
```

### Categorias
```csv
ID,Nome,Descrição,Ativo,Data Criação
```

### Ingredientes
```csv
ID,Nome,Preço,Ativo,Data Criação
```

### Lançamentos Financeiros
```csv
ID,Tipo,Valor,Data Vencimento,Data Pagamento,Descrição,Observações,Forma Pagamento,Status,Categoria,Conta,Usuário,Data Criação
```

### Pedidos
```csv
ID,Mesa,Cliente,Telefone,Status,Forma Pagamento,Valor Total,Valor Pago,Valor Restante,Observações,Usuário,Data
```

## Regras de Importação

### Comportamento dos Dados
- **Dados existentes**: Se um registro com o mesmo ID já existe, ele será atualizado
- **Novos registros**: Registros sem ID ou com ID inexistente serão criados como novos
- **Relacionamentos**: O sistema tenta manter relacionamentos (categorias, contas, usuários) por nome

### Validações
- **Campos obrigatórios**: Nome e preço são obrigatórios para produtos
- **Formatos**: Valores monetários devem usar ponto como separador decimal
- **Datas**: Devem estar no formato YYYY-MM-DD ou YYYY-MM-DD HH:MM:SS

### Tratamento de Erros
- **Erros de validação**: Registros com erro são ignorados e reportados
- **Transações**: Cada registro é processado em transação separada
- **Logs**: Erros são registrados e exibidos ao usuário

## Segurança

### Autenticação
- Todas as operações requerem usuário autenticado
- Dados são filtrados por tenant e filial do usuário

### Validação de Arquivos
- Apenas arquivos CSV são aceitos
- Tamanho máximo de arquivo: configurável no servidor
- Validação de estrutura do CSV

## Limitações

### Tamanho de Arquivo
- Arquivos muito grandes podem causar timeout
- Recomendado: máximo 10.000 registros por importação

### Performance
- Importações grandes podem demorar alguns minutos
- Sistema mostra progresso durante a importação

## Troubleshooting

### Problemas Comuns

1. **Erro "User not authenticated"**
   - Solução: Faça login novamente no sistema

2. **Erro "Invalid CSV format"**
   - Solução: Verifique se o arquivo foi exportado pelo sistema

3. **Erro "Category not found"**
   - Solução: Crie a categoria manualmente ou importe categorias primeiro

4. **Timeout durante importação**
   - Solução: Divida o arquivo em partes menores

### Logs
- Erros são registrados no console do navegador
- Logs do servidor em `/var/log/apache2/error.log` (Linux) ou logs do servidor web

## Exemplos de Uso

### Cenário 1: Atualização em Massa de Preços
1. Exporte os produtos
2. Abra no Excel e atualize os preços
3. Importe o arquivo modificado
4. Sistema atualizará todos os preços automaticamente

### Cenário 2: Migração de Dados
1. Exporte todos os dados do sistema antigo
2. Faça ajustes necessários no Excel
3. Importe no sistema novo
4. Verifique se todos os dados foram importados corretamente

### Cenário 3: Backup e Restauração
1. Exporte dados regularmente como backup
2. Em caso de problemas, importe o backup mais recente
3. Sistema restaurará os dados exatamente como estavam

## Suporte

Para problemas ou dúvidas sobre o sistema de exportação/importação:
1. Verifique os logs de erro
2. Teste com arquivos menores primeiro
3. Entre em contato com o suporte técnico se necessário
