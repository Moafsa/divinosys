# Implementação MinIO - Sistema de Armazenamento de Arquivos

## ✅ Configuração Completa

Este documento descreve a implementação do MinIO para armazenamento de todas as imagens e arquivos do sistema.

## 📋 O que foi implementado

### 1. Classe MinIO Helper (`system/Storage/MinIO.php`)
- Classe singleton para gerenciar todas as operações com MinIO
- Suporte para upload, download, exclusão e verificação de arquivos
- Integração com AWS SDK para compatibilidade com MinIO

### 2. Configurações
- ✅ Credenciais adicionadas ao `docker-compose.yml`
- ✅ Variáveis de ambiente adicionadas ao `env.example`
- ✅ Classe Config atualizada para carregar variáveis do MinIO
- ✅ Dependência AWS SDK adicionada ao `composer.json`

### 3. Uploads Migrados para MinIO

#### ✅ Produtos (`mvc/ajax/produtos_fix.php`)
- Upload de imagens de produtos
- Exclusão automática de imagens antigas ao atualizar

#### ✅ Logos (`mvc/ajax/configuracoes.php`)
- Upload de logos de filiais
- Armazenamento no bucket MinIO

#### ✅ Anexos Financeiros (`mvc/ajax/lancamentos_simple.php`)
- Upload de anexos de lançamentos financeiros
- Suporte para múltiplos arquivos
- Validação de tipo e tamanho

#### ✅ AI Chat (`mvc/ajax/ai_chat.php`)
- Upload de arquivos para processamento por IA
- Suporte para imagens, PDFs e planilhas

## 🔧 Configuração das Credenciais

### Variáveis de Ambiente

As seguintes variáveis devem estar configuradas:

```env
MINIO_ENDPOINT=https://ws3.conext.click
MINIO_ACCESS_KEY=vwTkiHo6pVhNqZp6e4QF
MINIO_SECRET_KEY=1NdkBupLjTCND5OyrKaTJvFRx7aAGHAKR5D7Pgfn
MINIO_BUCKET=divinosys
MINIO_PUBLIC_URL=https://winio.conext.click
```

### Docker Compose

As variáveis já foram adicionadas ao `docker-compose.yml` no serviço `app`.

## 📦 Estrutura de Pastas no MinIO

Os arquivos são organizados no bucket da seguinte forma:

```
divinosys/
├── produtos/
│   └── {nome_arquivo_unico}.{ext}
├── logos/
│   └── logo_{tenant_id}_{filial_id}_{timestamp}.{ext}
├── financeiro/
│   └── anexos/
│       └── {nome_arquivo_unico}.{ext}
└── ai_chat/
    └── {nome_arquivo_unico}_{timestamp}.{ext}
```

## 🚀 Próximos Passos

### 1. Instalar Dependências

```bash
composer install
```

Isso instalará o pacote `aws/aws-sdk-php` necessário para o MinIO.

### 2. Configurar Variáveis de Ambiente

- **Produção**: Configure as variáveis no ambiente (Coolify, Docker, etc.)
- **Desenvolvimento**: Copie `env.example` para `.env` e configure as credenciais

### 3. Verificar Conectividade

Certifique-se de que o servidor consegue acessar:
- Endpoint: `https://ws3.conext.click`
- Domínio público: `https://winio.conext.click`

### 4. Testar Uploads

Teste os seguintes cenários:
1. ✅ Upload de imagem de produto
2. ✅ Upload de logo de filial
3. ✅ Upload de anexo financeiro
4. ✅ Upload de arquivo no AI Chat

## 🔍 Como Usar a Classe MinIO

### Exemplo de Upload de Arquivo

```php
require_once __DIR__ . '/system/Storage/MinIO.php';

$minio = \System\Storage\MinIO::getInstance();

// Upload de arquivo do $_FILES
$url = $minio->uploadFile($_FILES['imagem'], 'produtos');

// Upload de conteúdo direto
$url = $minio->uploadContent($fileContent, 'imagem.jpg', 'produtos', 'image/jpeg');

// Exclusão de arquivo
$minio->deleteByUrl($url);
```

### Métodos Disponíveis

- `upload($filePath, $objectKey, $contentType, $isContent)` - Upload genérico
- `uploadFile($fileArray, $prefix)` - Upload de arquivo do $_FILES
- `uploadContent($content, $fileName, $prefix, $contentType)` - Upload de conteúdo direto
- `delete($objectKey)` - Deletar por object key
- `deleteByUrl($url)` - Deletar por URL pública
- `getUrl($objectKey)` - Obter URL pública
- `exists($objectKey)` - Verificar se arquivo existe
- `getContent($objectKey)` - Obter conteúdo do arquivo

## ⚠️ Notas Importantes

1. **SSL**: A verificação SSL está desabilitada para desenvolvimento. Em produção, considere configurar certificados adequados.

2. **Permissões**: O bucket deve ter permissões de leitura pública para que as URLs funcionem corretamente.

3. **Backward Compatibility**: O código mantém compatibilidade com URLs antigas que podem estar armazenadas no banco de dados.

4. **Validação**: Todos os uploads validam tipo e tamanho antes de fazer upload.

## 🔄 Migração de Arquivos Existentes

Se você já tem arquivos armazenados localmente, será necessário:

1. Migrar os arquivos existentes para o MinIO
2. Atualizar as URLs no banco de dados
3. Remover os arquivos locais após confirmação

## 📝 Logs e Debug

Os erros de MinIO são logados automaticamente. Para debug, verifique:
- `error_log` do PHP
- Logs do servidor MinIO
- Respostas de erro da API

## ✅ Checklist de Verificação

- [x] Classe MinIO criada
- [x] Credenciais configuradas
- [x] Upload de produtos migrado
- [x] Upload de logos migrado
- [x] Upload de anexos financeiros migrado
- [x] Upload de AI Chat migrado
- [x] Dependências adicionadas
- [ ] Testes realizados
- [ ] Arquivos antigos migrados (se necessário)

## 🐛 Troubleshooting

### Erro: "MinIO credentials not configured"
- Verifique se as variáveis de ambiente estão configuradas
- Certifique-se de que o `.env` está sendo carregado

### Erro: "Failed to upload file to MinIO"
- Verifique conectividade com o endpoint
- Verifique credenciais
- Verifique permissões do bucket

### URLs não funcionando
- Verifique se o bucket tem permissões públicas
- Verifique se o `MINIO_PUBLIC_URL` está correto
- Verifique CORS do MinIO

