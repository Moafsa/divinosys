<?php
/**
 * Debug User Buttons - Simple test page
 */

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug User Buttons</title>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .user { border: 1px solid #ccc; padding: 10px; margin: 10px 0; }
        .btn { padding: 5px 10px; margin: 5px; cursor: pointer; }
        .btn-primary { background: blue; color: white; }
        .btn-warning { background: orange; color: white; }
        .btn-danger { background: red; color: white; }
    </style>
</head>
<body>";

echo "<h2>Debug User Buttons</h2>";

// Test data
$usuarios = [
    ['id' => 1, 'nome' => 'Administrador', 'ativo' => true, 'tipo_usuario' => 'admin'],
    ['id' => 4, 'nome' => 'João da Cozinha', 'ativo' => false, 'tipo_usuario' => 'cozinha'],
    ['id' => 5, 'nome' => 'Moacir', 'ativo' => true, 'tipo_usuario' => 'garcom']
];

echo "<div id='usuariosList'>";
foreach ($usuarios as $usuario) {
    $usuarioId = $usuario['id'];
    $statusText = $usuario['ativo'] ? 'Ativo' : 'Inativo';
    $actionStatusText = $usuario['ativo'] ? 'Desativar' : 'Ativar';
    
    echo "<div class='user'>";
    echo "<strong>{$usuario['nome']}</strong> - Status: $statusText<br>";
    echo "<button class='btn btn-primary' onclick='testEditUser($usuarioId)'>Editar</button>";
    $statusValue = $usuario['ativo'] ? 'true' : 'false';
    echo "<button class='btn btn-warning' onclick='testChangeStatus($usuarioId, $statusValue)'>$actionStatusText</button>";
    echo "<button class='btn btn-danger' onclick='testDeleteUser($usuarioId, \"{$usuario['nome']}\")'>Deletar</button>";
    echo "</div>";
}
echo "</div>";

echo "<script>
function testEditUser(usuarioId) {
    console.log('✏️ Edit user clicked:', usuarioId, 'Type:', typeof usuarioId);
    
    if (!usuarioId || usuarioId <= 0 || isNaN(usuarioId)) {
        console.error('❌ Invalid user ID:', usuarioId);
        Swal.fire('Erro', 'ID do usuário inválido', 'error');
        return;
    }
    
    Swal.fire('Sucesso', 'Edit user function called with ID: ' + usuarioId, 'success');
}

function testChangeStatus(usuarioId, statusAtual) {
    console.log('🔄 Change status clicked:', usuarioId, 'Current status:', statusAtual, 'Type:', typeof statusAtual);
    
    if (!usuarioId || usuarioId <= 0 || isNaN(usuarioId)) {
        console.error('❌ Invalid user ID:', usuarioId);
        Swal.fire('Erro', 'ID do usuário inválido', 'error');
        return;
    }
    
    // Convert string to boolean if needed
    let statusBoolean;
    if (typeof statusAtual === 'string') {
        statusBoolean = statusAtual === 'true';
    } else {
        statusBoolean = Boolean(statusAtual);
    }
    
    const novoStatus = !statusBoolean;
    const acao = novoStatus ? 'ativar' : 'desativar';
    
    Swal.fire({
        title: 'Test Change Status',
        text: 'Would ' + acao + ' user ' + usuarioId + '?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, ' + acao,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            console.log('✅ Status change confirmed for user:', usuarioId);
            Swal.fire('Sucesso', 'Usuário ' + acao + ' com sucesso!', 'success');
        } else {
            console.log('❌ Status change cancelled');
        }
    });
}

function testDeleteUser(usuarioId, nomeUsuario) {
    console.log('🗑️ Delete user clicked:', usuarioId, 'Name:', nomeUsuario);
    
    if (!usuarioId || usuarioId <= 0 || isNaN(usuarioId)) {
        console.error('❌ Invalid user ID:', usuarioId);
        Swal.fire('Erro', 'ID do usuário inválido', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Test Delete User',
        html: 'Would delete user <strong>' + nomeUsuario + '</strong> (ID: ' + usuarioId + ')?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, deletar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            console.log('✅ Delete confirmed for user:', usuarioId);
            Swal.fire('Sucesso', 'Usuário ' + nomeUsuario + ' deletado!', 'success');
        } else {
            console.log('❌ Delete cancelled');
        }
    });
}

console.log('🚀 Debug page loaded');
</script>";

echo "</body></html>";
?>
