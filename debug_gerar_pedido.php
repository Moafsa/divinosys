<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Get current user, tenant and filial
$user = $session->getUser();
$tenant = $session->getTenant();
$filial = $session->getFilial();

// Debug: Se não tem tenant/filial, usar valores padrão
if (!$tenant) {
    $tenant = $db->fetch("SELECT * FROM tenants WHERE id = 1");
    if ($tenant) {
        $session->setTenant($tenant);
    }
}

if (!$filial) {
    $filial = $db->fetch("SELECT * FROM filiais WHERE id = 1");
    if ($filial) {
        $session->setFilial($filial);
    }
}

// Get mesas
$mesas = $db->fetchAll("SELECT * FROM mesas WHERE tenant_id = ? AND filial_id = ? ORDER BY id_mesa", [$tenant['id'], $filial['id']]);

// Get produtos
$produtos = $db->fetchAll("SELECT * FROM produtos WHERE tenant_id = ? AND filial_id = ? AND ativo = 1 ORDER BY nome", [$tenant['id'], $filial['id']]);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Gerar Pedido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Debug Gerar Pedido</h1>
        
        <div class="row">
            <div class="col-md-6">
                <h3>Mesas</h3>
                <?php foreach ($mesas as $mesa): ?>
                    <div class="mesa-card border p-3 mb-2" 
                         data-mesa-id="<?php echo $mesa['id_mesa']; ?>" 
                         data-mesa-numero="<?php echo $mesa['nome']; ?>"
                         onclick="selecionarMesa(this)">
                        <strong><?php echo $mesa['nome']; ?></strong>
                        <span class="badge bg-success">Livre</span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="col-md-6">
                <h3>Produtos</h3>
                <?php foreach ($produtos as $produto): ?>
                    <div class="produto-card border p-3 mb-2" onclick="adicionarProduto(<?php echo $produto['id']; ?>)">
                        <strong><?php echo $produto['nome']; ?></strong>
                        <br>R$ <?php echo number_format($produto['preco_normal'], 2, ',', '.'); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="mt-4">
            <h3>Carrinho</h3>
            <div id="carrinhoItens">Carrinho vazio</div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let mesaSelecionada = null;
        let carrinho = [];

        function selecionarMesa(element) {
            console.log('Selecionando mesa...');
            
            // Remove previous selection
            document.querySelectorAll('.mesa-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selection to clicked card
            element.classList.add('selected');
            
            mesaSelecionada = {
                id: element.dataset.mesaId,
                numero: element.dataset.mesaNumero
            };
            
            console.log('Mesa selecionada:', mesaSelecionada);
            alert('Mesa selecionada: ' + mesaSelecionada.numero);
        }

        function adicionarProduto(produtoId) {
            console.log('Adicionando produto:', produtoId);
            
            if (!mesaSelecionada) {
                alert('Selecione uma mesa primeiro!');
                return;
            }
            
            // Simular adição ao carrinho
            carrinho.push({
                id: produtoId,
                nome: 'Produto ' + produtoId,
                preco: 10.00,
                quantidade: 1
            });
            
            atualizarCarrinho();
            alert('Produto adicionado ao carrinho!');
        }

        function atualizarCarrinho() {
            const carrinhoItens = document.getElementById('carrinhoItens');
            
            if (carrinho.length === 0) {
                carrinhoItens.innerHTML = 'Carrinho vazio';
                return;
            }
            
            let html = '<ul>';
            carrinho.forEach(item => {
                html += `<li>${item.nome} - R$ ${item.preco.toFixed(2)}</li>`;
            });
            html += '</ul>';
            
            carrinhoItens.innerHTML = html;
        }
    </script>
</body>
</html>
