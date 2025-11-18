# Sistema de Exportação Excel (.xlsx)

## Visão Geral

O sistema agora possui funcionalidades completas de exportação para arquivos Excel nativos (.xlsx), permitindo que os usuários exportem dados em formato Excel para edição e análise.

## Arquivos Implementados

### 1. API de Exportação Excel
- **`api/export_excel.php`** - Endpoint principal para exportação Excel
- **`api/export.php`** - Versão CSV (mantida para compatibilidade)
- **`api/import.php`** - Importação de dados

### 2. Arquivos de Teste
- **`test_excel_export.php`** - Teste completo do sistema
- **`test_headers.php`** - Teste de headers de download
- **`install_excel_support.php`** - Instalador de bibliotecas (opcional)

## Funcionalidades

### ✅ Exportação Excel Nativa
- **Formato**: Arquivos .xlsx verdadeiros
- **Compatibilidade**: Excel 2007+ e LibreOffice
- **Codificação**: UTF-8 com suporte a acentos
- **Formatação**: Headers destacados e tipos de dados corretos

### 📊 Tipos de Exportação Disponíveis

#### Página de Produtos
- **Produtos**: Lista completa com ingredientes
- **Categorias**: Todas as categorias
- **Ingredientes**: Lista de ingredientes disponíveis

#### Página Financeira
- **Lançamentos**: Dados financeiros completos
- **Pedidos**: Todos os pedidos
- **Pedidos Quitados**: Apenas pedidos pagos
- **Pedidos Fiados**: Apenas pedidos em aberto

## Como Usar

### 1. Teste Inicial
```bash
# Acesse o arquivo de teste
http://localhost/test_excel_export.php
```

### 2. Exportação via Interface
1. Acesse a página de produtos ou financeiro
2. Clique no botão "Exportar"
3. Selecione o tipo de dados desejado
4. O arquivo Excel será baixado automaticamente

### 3. Teste Direto da API
```bash
# Exportar produtos
http://localhost/api/export_excel.php?action=export_products

# Exportar categorias
http://localhost/api/export_excel.php?action=export_categories

# Exportar pedidos
http://localhost/api/export_excel.php?action=export_orders
```

## Estrutura dos Arquivos Excel

### Produtos
| Coluna | Descrição | Tipo |
|--------|-----------|------|
| ID | Identificador único | Número |
| Código | Código do produto | Texto |
| Nome | Nome do produto | Texto |
| Descrição | Descrição detalhada | Texto |
| Preço Normal | Preço padrão | Número |
| Preço Mini | Preço promocional | Número |
| Ativo | Status ativo/inativo | Texto |
| Imagem | Caminho da imagem | Texto |
| Categoria ID | ID da categoria | Número |
| Categoria Nome | Nome da categoria | Texto |
| Ingredientes | Lista de ingredientes | Texto |
| Data Criação | Data de criação | Data |

### Pedidos
| Coluna | Descrição | Tipo |
|--------|-----------|------|
| ID | ID do pedido | Número |
| Mesa | Número da mesa | Texto |
| Cliente | Nome do cliente | Texto |
| Telefone | Telefone do cliente | Texto |
| Status | Status do pedido | Texto |
| Forma Pagamento | Método de pagamento | Texto |
| Valor Total | Valor total | Número |
| Valor Pago | Valor já pago | Número |
| Valor Restante | Valor em aberto | Número |
| Observações | Observações | Texto |
| Usuário | Nome do usuário | Texto |
| Data | Data do pedido | Data |

## Vantagens do Formato Excel

### ✅ Compatibilidade
- **Excel**: Abre nativamente no Microsoft Excel
- **LibreOffice**: Compatível com LibreOffice Calc
- **Google Sheets**: Pode ser importado no Google Sheets
- **Numbers**: Funciona no Apple Numbers

### ✅ Formatação
- **Tipos de Dados**: Números, datas e texto corretamente identificados
- **Headers**: Cabeçalhos destacados e formatados
- **Codificação**: Suporte completo a acentos e caracteres especiais
- **Estrutura**: Planilha organizada e profissional

### ✅ Funcionalidades
- **Filtros**: Excel pode aplicar filtros automaticamente
- **Gráficos**: Dados prontos para criação de gráficos
- **Fórmulas**: Pode usar fórmulas do Excel
- **Formatação**: Aplicar formatação condicional

## Troubleshooting

### Problemas Comuns

1. **Arquivo não abre no Excel**
   - Verifique se o arquivo tem extensão .xlsx
   - Tente abrir com LibreOffice primeiro
   - Verifique se o arquivo não está corrompido

2. **Caracteres especiais não aparecem**
   - Verifique se o Excel está configurado para UTF-8
   - Tente abrir com LibreOffice
   - Verifique a codificação do arquivo

3. **Download não funciona**
   - Verifique se o JavaScript está habilitado
   - Teste os links diretos da API
   - Verifique os logs do servidor

### Logs e Debug
```bash
# Verificar logs do Apache
tail -f /var/log/apache2/error.log

# Testar endpoint diretamente
curl -I "http://localhost/api/export_excel.php?action=export_products"

# Verificar headers
curl -v "http://localhost/api/export_excel.php?action=export_products"
```

## Performance

### Otimizações Implementadas
- **Streaming**: Dados são enviados conforme processados
- **Memória**: Uso eficiente de memória para grandes volumes
- **Compressão**: Headers otimizados para download rápido
- **Cache**: Headers de cache para melhor performance

### Limitações
- **Tamanho**: Arquivos muito grandes podem demorar
- **Memória**: Limitação de memória do servidor
- **Timeout**: Timeout de execução do PHP

## Próximos Passos

### Melhorias Futuras
1. **PhpSpreadsheet**: Implementar biblioteca completa
2. **Formatação Avançada**: Cores, bordas, estilos
3. **Múltiplas Abas**: Várias planilhas em um arquivo
4. **Gráficos**: Incluir gráficos automáticos
5. **Templates**: Modelos personalizados

### Integrações
1. **Email**: Envio automático por email
2. **Cloud**: Upload para Google Drive/Dropbox
3. **API**: Endpoints REST completos
4. **Webhook**: Notificações de exportação

## Suporte

Para problemas ou dúvidas:
1. Execute `test_excel_export.php` para diagnóstico
2. Verifique os logs do servidor
3. Teste com arquivos menores primeiro
4. Entre em contato com o suporte técnico

---

**Sistema de Exportação Excel - Divino Lanches**  
*Versão 1.0 - Outubro 2025*
