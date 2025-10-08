# Correção Completa Online - Divino Lanches

## 🚨 Problemas Identificados

### **1. Colunas Faltantes**
O erro `column "ativo" of relation "categorias" does not exist` indica que faltam colunas no schema online:
- `descricao` (TEXT)
- `ativo` (BOOLEAN)
- `ordem` (INTEGER)
- `parent_id` (INTEGER)
- `imagem` (VARCHAR)

### **2. Sequences Desatualizadas**
As sequences do PostgreSQL estão fora de sincronia com os dados existentes.

## 🛠️ Solução Completa

### **Arquivos Criados:**
1. `fix_online_complete.php` - Script PHP completo
2. `fix_online_complete.sql` - Script SQL direto

## 📋 Como Aplicar no Servidor Online

### **Opção 1: Via Script PHP (Recomendado)**

1. **Faça upload do arquivo** `fix_online_complete.php` para o servidor
2. **Execute via navegador:** `https://divinosys.conext.click/fix_online_complete.php`
3. **Verifique a saída** para confirmar que todas as correções foram aplicadas
4. **⚠️ IMPORTANTE:** Delete o arquivo após a execução por segurança

### **Opção 2: Via SQL Direto**

1. **Conecte ao banco PostgreSQL** do servidor online
2. **Execute o conteúdo** do arquivo `fix_online_complete.sql`

### **Opção 3: Via Coolify/Docker**

Se você tiver acesso ao container PostgreSQL:

```bash
# Copiar script para o container
docker cp fix_online_complete.sql <container_name>:/tmp/

# Executar no container
docker exec -i <container_name> psql -U postgres -d divino_lanches < /tmp/fix_online_complete.sql
```

## 🔍 O Que o Script Faz

### **1. Verificação de Estrutura**
- Lista todas as colunas atuais das tabelas
- Identifica colunas faltantes

### **2. Adição de Colunas**
```sql
-- Tabela categorias
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS descricao TEXT;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS ordem INTEGER DEFAULT 0;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS parent_id INTEGER;
ALTER TABLE categorias ADD COLUMN IF NOT EXISTS imagem VARCHAR(255);

-- Tabela ingredientes
ALTER TABLE ingredientes ADD COLUMN IF NOT EXISTS descricao TEXT;
ALTER TABLE ingredientes ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT true;
```

### **3. Correção de Sequences**
```sql
SELECT setval('categorias_id_seq', (SELECT MAX(id) FROM categorias) + 1);
SELECT setval('ingredientes_id_seq', (SELECT MAX(id) FROM ingredientes) + 1);
```

### **4. Testes de Funcionamento**
- Cria registros de teste
- Verifica se funcionam
- Remove os registros de teste

## ✅ Verificação Pós-Execução

Após executar o script, teste:

1. **Criar uma nova categoria** no sistema
2. **Criar um novo ingrediente** no sistema
3. **Verificar se não há mais erros** de coluna faltante

## 📊 Resultado Esperado

```
✅ Conectado ao banco de dados
✅ Colunas faltantes adicionadas
✅ Sequences corrigidas
✅ Testes de funcionamento realizados
🎉 CORREÇÃO COMPLETA CONCLUÍDA COM SUCESSO!
```

## 🚨 Se Ainda Houver Problemas

Se após executar o script ainda houver problemas:

1. **Verifique os logs** do Apache/PHP para erros específicos
2. **Confirme que todas as colunas** foram adicionadas
3. **Teste manualmente** no banco se as sequences estão corretas
4. **Verifique se há outros arquivos** de schema que precisam ser executados

## 📞 Suporte

Se precisar de ajuda adicional, forneça:
- Logs completos do script
- Mensagens de erro específicas
- Estrutura atual das tabelas após execução
