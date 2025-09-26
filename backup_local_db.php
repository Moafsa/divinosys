<?php
/**
 * Script para fazer backup do banco local e substituir o online
 * Baseado no sistema que funciona localmente
 */

echo "=== BACKUP E SUBSTITUIÇÃO DO BD ===\n";
echo "Fazendo backup do BD que funciona localmente...\n\n";

// 1. Fazer backup do BD local
$localHost = 'localhost';
$localPort = '5433'; // Porta do docker local
$localDb = 'divino_db';
$localUser = 'divino_user';
$localPassword = 'divino_password';

$timestamp = date('Y_m_d_H_i_s');
$backupFile = "backup_local_$timestamp.sql";

echo "1. Fazendo backup do BD local...\n";
echo "Host: $localHost:$localPort\n";
echo "Database: $localDb\n";
echo "User: $localUser\n\n";

try {
    // Comando para fazer backup via pg_dump
    $command = "pg_dump -h $localHost -p $localPort -U $localUser -d $localDb > $backupFile";
    
    // Usar PGPASSWORD para ser compatível com Windows
    putenv("PGPASSWORD=$localPassword");
    
    $output = [];
    $return_code = 0;
    
    exec($command, $output, $return_code);
    
    if ($return_code === 0) {
        echo "✅ Backup local criado com sucesso: $backupFile\n\n";
        
        if (file_exists($backupFile)) {
            $fileSize = filesize($backupFile);
            echo "📁 Tamanho do arquivo: " . number_format($fileSize) . " bytes\n\n";
            
            // 2. Mostrar instruções para usar o backup no online
            echo "=== INSTRUÇÕES PARA SUBSTITUIR BD ONLINE ===\n";
            echo "1. O arquivo '$backupFile' contém backup completo do BD local\n";
            echo "2. Para usar no Coolify/servidor online:\n\n";
            echo "   📋 PASSOS:\n";
            echo "   ------------------------\n";
            echo "   1º - Acessar container postgres do Coolify\n";
            echo "   2º - Subir arquivo $backupFile para o servidor\n";
            echo "   3º - Executar: psql -U postgres -d divino_lanches < $backupFile\n";
            echo "   4º - Ou usar interface do Coolify para restore\n\n";
            
            // 3. Gerar script de substituição 
            $scriptContent = "#!/bin/bash\n";
            $scriptContent .= "# Script para substituir BD online com backup local\n";
            $scriptContent .= "echo 'Substituindo BD online com backup local...'\n";
            $scriptContent .= "echo 'Parando aplicação temporariamente...'\n";
            $scriptContent .= "# Adicione comandos do Coolify aqui\n\n";
            $scriptContent .= "echo 'Importando dados locais para produção...'\n";
            $scriptContent .= "psql -h postgres -U postgres divino_lanches < $backupFile\n";
            $scriptContent .= "echo 'BD substituído com dados locais!'\n";
            
            file_put_contents("restore_online_sh.txt", $scriptContent);
            echo "📝 Script gerado: restore_online_sh.txt\n\n";
            
            // Mostrar conteúdo do arquivo de backup
            echo "📊 ESTRUTURA DO BACKUP (primeiras linhas):\n";
            echo "-------------------------------------------\n";
            $lines = file($backupFile);
            for ($i = 0; $i < min(20, count($lines)); $i++) {
                echo ($i+1) . ": " . $lines[$i];
            }
            echo "-------------------------------------------\n\n";
            
        } else {
            echo "❌ Erro: arquivo de backup não foi criado\n";
        }
        
    } else {
        echo "❌ Erro ao fazer backup: código $return_code\n";
        echo "output: " . implode("\n", $output) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}

echo "=== REFERÊNCIAS ===\n";
echo "Arquivo de backup: $backupFile\n";
echo "Script para restaurar: restore_online_sh.txt\n";
echo "Com banco restaurado, instâncias funcionarão como local! ✅\n";

echo "\n=== PRÓXIMOS PASSOS ===\n";
echo "1. Subir arquivo '$backupFile' para servidor\n";
echo "2. Conectar ao PostgreSQL do Coolify\n";
echo "3. Executar: psql -U postgres -d divino_lanches < $backupFile\n";
echo "4. Reiniciar aplicação no Coolify\n\n";
?>
