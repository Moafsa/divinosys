<?php
/**
 * SCRIPT PARA CORRIGIR FRONTEND DA WUZAPI
 * 
 * Este script diagnostica e corrige problemas com o frontend da Wuzapi
 */

echo "🔧 DIAGNÓSTICO E CORREÇÃO DO FRONTEND WUZAPI\n";
echo "==========================================\n\n";

// 1. Verificar se o container está rodando
echo "1️⃣ Verificando container da Wuzapi...\n";
$containers = shell_exec('docker ps --format "table {{.Names}}\t{{.Status}}" | findstr wuzapi');
echo $containers ? "✅ Container encontrado:\n$containers\n" : "❌ Container não encontrado\n";

// 2. Verificar logs da Wuzapi
echo "2️⃣ Verificando logs da Wuzapi...\n";
$logs = shell_exec('docker logs divino-lanches-wuzapi --tail 20 2>&1');
echo "Logs recentes:\n$logs\n";

// 3. Verificar se as portas estão abertas
echo "3️⃣ Verificando portas...\n";
$ports = shell_exec('netstat -an | findstr :8081');
echo $ports ? "✅ Porta 8081 ativa:\n$ports\n" : "❌ Porta 8081 não encontrada\n";

// 4. Testar conectividade
echo "4️⃣ Testando conectividade...\n";
$test = @file_get_contents('http://localhost:8081');
if ($test !== false) {
    echo "✅ Frontend respondendo\n";
} else {
    echo "❌ Frontend não responde\n";
}

// 5. Verificar se precisa reiniciar
echo "5️⃣ Verificando se precisa reiniciar...\n";
$restart = shell_exec('docker restart divino-lanches-wuzapi 2>&1');
echo "Reiniciando container: $restart\n";

// 6. Aguardar e testar novamente
echo "6️⃣ Aguardando reinicialização...\n";
sleep(10);

$test2 = @file_get_contents('http://localhost:8081');
if ($test2 !== false) {
    echo "✅ Frontend funcionando após reinicialização\n";
} else {
    echo "❌ Frontend ainda não funciona\n";
    echo "💡 POSSÍVEIS SOLUÇÕES:\n";
    echo "   - Verificar se o build do React foi feito corretamente\n";
    echo "   - Verificar variáveis de ambiente da Wuzapi\n";
    echo "   - Verificar se o nginx/proxy está configurado\n";
    echo "   - Rebuildar o container da Wuzapi\n";
}

echo "\n🎯 PRÓXIMOS PASSOS:\n";
echo "1. Acesse: http://localhost:8081\n";
echo "2. Se não funcionar, execute: docker-compose down && docker-compose up -d\n";
echo "3. Verifique logs: docker logs divino-lanches-wuzapi\n";
?>
