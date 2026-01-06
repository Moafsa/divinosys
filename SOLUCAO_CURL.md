# PROBLEMA IDENTIFICADO: cURL não está habilitado no PHP

## Erro Encontrado
```
Fatal error: Call to undefined function curl_init()
```

## Solução

### 1. Localizar o arquivo php.ini

Execute no terminal/PowerShell:
```powershell
php --ini
```

Isso mostrará o caminho do arquivo `php.ini` em uso.

### 2. Editar o php.ini

Abra o arquivo `php.ini` e procure por:
```ini
;extension=curl
```

Ou:
```ini
;extension=php_curl.dll
```

### 3. Descomentar a linha

Remova o ponto e vírgula (`;`) do início da linha:
```ini
extension=curl
```

Ou:
```ini
extension=php_curl.dll
```

### 4. Reiniciar o servidor web

- Se estiver usando XAMPP/WAMP: Reinicie o Apache
- Se estiver usando servidor integrado: Pare e inicie novamente
- Se estiver usando outro servidor: Reinicie conforme necessário

### 5. Verificar se funcionou

Execute este comando no PowerShell:
```powershell
php -m | findstr curl
```

Se retornar `curl`, está funcionando!

Ou acesse: `http://localhost:8080/test_basico.php` novamente.

## Alternativa: Habilitar via linha de comando (se usar servidor integrado)

Se estiver usando o servidor PHP integrado, você pode habilitar o cURL adicionando no início do seu script:

```php
if (!function_exists('curl_init')) {
    die('cURL não está habilitado no PHP! Habilite a extensão curl no php.ini');
}
```

Mas o correto é habilitar no php.ini como mostrado acima.








