<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../system/Config.php';
require_once __DIR__ . '/../../system/Database.php';
require_once __DIR__ . '/../../system/Session.php';

try {
    $action = $_GET['action'] ?? '';
    $verMesa = $_GET['ver_mesa'] ?? '';
    
    if ($verMesa == '1') {
        $action = 'ver_mesa';
    }
    
    switch ($action) {
        case 'ver_mesa':
            $mesaId = (int) ($_GET['mesa_id'] ?? 0);
            
            if (!$mesaId) {
                throw new \Exception('ID da mesa é obrigatório');
            }
            
            $db = \System\Database::getInstance();
            $session = \System\Session::getInstance();
            $tenantId = $session->getTenantId() ?? 1;
            $filialId = $session->getFilialId() ?? 1;
            
            // Get mesa info
            $mesa = $db->fetch(
                "SELECT * FROM mesas WHERE id_mesa = ? AND tenant_id = ? AND filial_id = ?",
                [$mesaId, $tenantId, $filialId]
            );
            
            if (!$mesa) {
                throw new \Exception('Mesa não encontrada');
            }
            
            // Get pedido ativo da mesa
            $pedido = $db->fetch(
                "SELECT * FROM pedido WHERE idmesa::varchar = ? AND tenant_id = ? AND filial_id = ? AND status NOT IN ('Finalizado', 'Cancelado') ORDER BY data DESC, hora_pedido DESC LIMIT 1",
                [$mesaId, $tenantId, $filialId]
            );
            
            // Generate HTML content with modern layout
            $html = '<div class="mesa-details">';
            
            if ($pedido) {
                // Header with actions
                $html .= '<div class="d-flex justify-content-between align-items-center mb-3">';
                $html .= '<div>';
                $html .= '<h5 class="mb-1">Pedido #' . htmlspecialchars($pedido['idpedido']) . '</h5>';
                $html .= '<small class="text-muted">' . htmlspecialchars($pedido['data']) . ' às ' . htmlspecialchars($pedido['hora_pedido']) . '</small>';
                $html .= '</div>';
                $html .= '<div class="btn-group" role="group">';
                $html .= '<button type="button" class="btn btn-outline-primary btn-sm" onclick="editarPedido(' . $pedido['idpedido'] . ')">';
                $html .= '<i class="fas fa-edit"></i> Editar';
                $html .= '</button>';
                $html .= '<button type="button" class="btn btn-outline-success btn-sm" onclick="fecharPedido(' . $pedido['idpedido'] . ')">';
                $html .= '<i class="fas fa-check"></i> Fechar';
                $html .= '</button>';
                $html .= '<button type="button" class="btn btn-outline-danger btn-sm" onclick="excluirPedido(' . $pedido['idpedido'] . ')">';
                $html .= '<i class="fas fa-trash"></i> Excluir';
                $html .= '</button>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Status and Total cards
                $html .= '<div class="row mb-3">';
                $html .= '<div class="col-md-6">';
                $html .= '<div class="status-card">';
                $html .= '<label class="form-label">Status Atual</label>';
                $html .= '<select class="form-select" id="statusSelect" onchange="atualizarStatusRapido(' . $pedido['idpedido'] . ', this.value)">';
                $html .= '<option value="Pendente"' . ($pedido['status'] === 'Pendente' ? ' selected' : '') . '>Pendente</option>';
                $html .= '<option value="Em Preparo"' . ($pedido['status'] === 'Em Preparo' ? ' selected' : '') . '>Em Preparo</option>';
                $html .= '<option value="Pronto"' . ($pedido['status'] === 'Pronto' ? ' selected' : '') . '>Pronto</option>';
                $html .= '<option value="Saiu para Entrega"' . ($pedido['status'] === 'Saiu para Entrega' ? ' selected' : '') . '>Saiu para Entrega</option>';
                $html .= '<option value="Entregue"' . ($pedido['status'] === 'Entregue' ? ' selected' : '') . '>Entregue</option>';
                $html .= '<option value="Finalizado"' . ($pedido['status'] === 'Finalizado' ? ' selected' : '') . '>Finalizado</option>';
                $html .= '</select>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '<div class="col-md-6">';
                $html .= '<div class="total-card">';
                $html .= '<label class="form-label">Valor Total</label>';
                $html .= '<div class="h4 text-success mb-0">R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</div>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Client and Table info cards
                $html .= '<div class="row mb-3">';
                $html .= '<div class="col-md-6">';
                $html .= '<div class="info-card">';
                $html .= '<h6><i class="fas fa-user me-2"></i>Cliente</h6>';
                $html .= '<p class="mb-1"><strong>Nome:</strong> ' . htmlspecialchars($pedido['cliente'] ?? 'N/A') . '</p>';
                $html .= '<p class="mb-0"><strong>Usuário:</strong> ' . htmlspecialchars($pedido['usuario_id'] ?? 'N/A') . '</p>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '<div class="col-md-6">';
                $html .= '<div class="info-card">';
                $html .= '<h6><i class="fas fa-table me-2"></i>Local</h6>';
                $html .= '<p class="mb-1"><strong>Mesa:</strong> ' . htmlspecialchars($mesa['nome']) . '</p>';
                $html .= '<button type="button" class="btn btn-sm btn-outline-primary" onclick="editarMesa(' . $pedido['idpedido'] . ', \'' . $pedido['idmesa'] . '\')">';
                $html .= '<i class="fas fa-edit"></i> Editar Mesa';
                $html .= '</button>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Observations
                $html .= '<div class="mb-3">';
                $html .= '<label class="form-label">Observações</label>';
                $html .= '<textarea class="form-control" id="observacaoPedido" rows="2" placeholder="Observações do pedido...">' . htmlspecialchars($pedido['observacao'] ?? '') . '</textarea>';
                $html .= '<button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="salvarObservacao(' . $pedido['idpedido'] . ')">';
                $html .= '<i class="fas fa-save"></i> Salvar Observação';
                $html .= '</button>';
                $html .= '</div>';
                
                // Get itens do pedido
                $itens = $db->fetchAll(
                    "SELECT pi.*, pr.nome as produto_nome 
                     FROM pedido_itens pi 
                     LEFT JOIN produtos pr ON pi.produto_id = pr.id AND pr.tenant_id = pi.tenant_id AND pr.filial_id = ?
                     WHERE pi.pedido_id = ? AND pi.tenant_id = ?",
                    [$filialId, $pedido['idpedido'], $tenantId]
                );
                
                if (!empty($itens)) {
                    $html .= '<div class="itens-section">';
                    $html .= '<h6><i class="fas fa-list me-2"></i>Itens do Pedido</h6>';
                    $html .= '<div class="table-responsive">';
                    $html .= '<table class="table table-hover">';
                    $html .= '<thead class="table-light">';
                    $html .= '<tr>';
                    $html .= '<th>Produto</th>';
                    $html .= '<th width="80">Qtd</th>';
                    $html .= '<th width="100">Valor Unit.</th>';
                    $html .= '<th width="100">Total</th>';
                    $html .= '<th width="80">Ações</th>';
                    $html .= '</tr>';
                    $html .= '</thead>';
                    $html .= '<tbody>';
                    
                    foreach ($itens as $item) {
                        $html .= '<tr>';
                        $html .= '<td>';
                        $html .= '<strong>' . htmlspecialchars($item['produto_nome'] ?? 'Produto') . '</strong>';
                        if (!empty($item['observacao'])) {
                            $html .= '<br><small class="text-muted">' . htmlspecialchars($item['observacao']) . '</small>';
                        }
                        $html .= '</td>';
                        $html .= '<td>';
                        $html .= '<div class="input-group input-group-sm">';
                        $html .= '<button class="btn btn-outline-secondary" type="button" onclick="alterarQuantidade(' . $pedido['idpedido'] . ', ' . $item['id'] . ', ' . ($item['quantidade'] - 1) . ')">-</button>';
                        $html .= '<input type="number" class="form-control text-center" value="' . $item['quantidade'] . '" min="1" onchange="alterarQuantidade(' . $pedido['idpedido'] . ', ' . $item['id'] . ', this.value)">';
                        $html .= '<button class="btn btn-outline-secondary" type="button" onclick="alterarQuantidade(' . $pedido['idpedido'] . ', ' . $item['id'] . ', ' . ($item['quantidade'] + 1) . ')">+</button>';
                        $html .= '</div>';
                        $html .= '</td>';
                        $html .= '<td>R$ ' . number_format($item['valor_unitario'], 2, ',', '.') . '</td>';
                        $html .= '<td><strong>R$ ' . number_format($item['valor_total'], 2, ',', '.') . '</strong></td>';
                        $html .= '<td>';
                        $html .= '<button class="btn btn-sm btn-outline-danger" onclick="removerItem(' . $pedido['idpedido'] . ', ' . $item['id'] . ')" title="Remover item">';
                        $html .= '<i class="fas fa-times"></i>';
                        $html .= '</button>';
                        $html .= '</td>';
                        $html .= '</tr>';
                    }
                    
                    $html .= '</tbody>';
                    $html .= '</table>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                
            } else {
                // Mesa livre
                $html .= '<div class="d-flex justify-content-between align-items-center mb-3">';
                $html .= '<div>';
                $html .= '<h5 class="mb-1">Mesa ' . htmlspecialchars($mesa['id_mesa']) . '</h5>';
                $html .= '<small class="text-muted">' . htmlspecialchars($mesa['nome']) . '</small>';
                $html .= '</div>';
                $html .= '<div class="btn-group" role="group">';
                $html .= '<button type="button" class="btn btn-outline-success btn-sm" onclick="fazerPedido(' . $mesaId . ')">';
                $html .= '<i class="fas fa-plus"></i> Novo Pedido';
                $html .= '</button>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Status card for free table
                $html .= '<div class="row mb-3">';
                $html .= '<div class="col-md-12">';
                $html .= '<div class="status-card">';
                $html .= '<label class="form-label">Status da Mesa</label>';
                $html .= '<div class="h5 mb-0">';
                $html .= '<span class="badge bg-success">Livre</span>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                // Table info
                $html .= '<div class="row mb-3">';
                $html .= '<div class="col-md-12">';
                $html .= '<div class="info-card">';
                $html .= '<h6><i class="fas fa-table me-2"></i>Informações da Mesa</h6>';
                $html .= '<p class="mb-1"><strong>Número:</strong> ' . htmlspecialchars($mesa['id_mesa']) . '</p>';
                $html .= '<p class="mb-1"><strong>Nome:</strong> ' . htmlspecialchars($mesa['nome']) . '</p>';
                $html .= '<p class="mb-0"><strong>Status:</strong> <span class="badge bg-success">Disponível</span></p>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';
                
                $html .= '<div class="alert alert-info">';
                $html .= '<i class="fas fa-info-circle me-2"></i>';
                $html .= '<strong>Mesa Livre:</strong> Esta mesa está disponível para novos pedidos.';
                $html .= '</div>';
            }
            
            $html .= '</div>';
            
            // Adicionar JavaScript inline para as funções
            $html .= '<script>
            function atualizarStatusRapido(pedidoId, novoStatus) {
                console.log("=== TESTE ATUALIZAR STATUS ===");
                console.log("Pedido ID:", pedidoId);
                console.log("Novo Status:", novoStatus);
                
                fetch("index.php?action=pedidos&t=" + Date.now(), {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: `atualizar_status=1&pedido_id=${pedidoId}&status=${novoStatus}`
                })
                .then(response => {
                    console.log("Response status:", response.status);
                    return response.json();
                })
                .then(data => {
                    console.log("Response data:", data);
                    if (data.success) {
                        Swal.fire("Sucesso", "Status atualizado com sucesso!", "success");
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        Swal.fire("Erro", data.message, "error");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    Swal.fire("Erro", "Erro ao atualizar status", "error");
                });
            }
            
            function salvarObservacao(pedidoId) {
                const observacao = document.getElementById("observacaoPedido").value;
                
                fetch("index.php?action=pedidos&t=" + Date.now(), {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: `atualizar_observacao=1&pedido_id=${pedidoId}&observacao=${encodeURIComponent(observacao)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire("Sucesso", "Observação salva com sucesso!", "success");
                    } else {
                        Swal.fire("Erro", data.message, "error");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    Swal.fire("Erro", "Erro ao salvar observação", "error");
                });
            }
            
            function alterarQuantidade(pedidoId, itemId, novaQuantidade) {
                if (novaQuantidade < 1) {
                    Swal.fire("Atenção", "Quantidade deve ser maior que zero", "warning");
                    return;
                }

                fetch("index.php?action=pedidos&t=" + Date.now(), {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: `alterar_quantidade=1&pedido_id=${pedidoId}&item_id=${itemId}&quantidade=${novaQuantidade}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire("Sucesso", "Quantidade atualizada com sucesso!", "success");
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        Swal.fire("Erro", data.message, "error");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    Swal.fire("Erro", "Erro ao atualizar quantidade", "error");
                });
            }
            
            function removerItem(pedidoId, itemId) {
                Swal.fire({
                    title: "Remover Item",
                    text: "Deseja realmente remover este item?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Sim, remover",
                    cancelButtonText: "Cancelar",
                    confirmButtonColor: "#dc3545"
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch("index.php?action=pedidos&t=" + Date.now(), {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                                "X-Requested-With": "XMLHttpRequest"
                            },
                            body: `remover_item=1&pedido_id=${pedidoId}&item_id=${itemId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire("Sucesso", "Item removido com sucesso!", "success");
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                Swal.fire("Erro", data.message, "error");
                            }
                        })
                        .catch(error => {
                            console.error("Error:", error);
                            Swal.fire("Erro", "Erro ao remover item", "error");
                        });
                    }
                });
            }
            
            function editarPedido(pedidoId) {
                window.location.href = `index.php?view=gerar_pedido&editar=${pedidoId}`;
            }
            
            function excluirPedido(pedidoId) {
                Swal.fire({
                    title: "Excluir Pedido",
                    text: "Deseja realmente excluir este pedido?",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Sim, excluir",
                    cancelButtonText: "Cancelar",
                    confirmButtonColor: "#dc3545"
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch("index.php?action=pedidos&t=" + Date.now(), {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                                "X-Requested-With": "XMLHttpRequest"
                            },
                            body: `excluir_pedido=1&pedido_id=${pedidoId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire("Sucesso", "Pedido excluído com sucesso!", "success");
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                Swal.fire("Erro", data.message, "error");
                            }
                        })
                        .catch(error => {
                            console.error("Error:", error);
                            Swal.fire("Erro", "Erro ao excluir pedido", "error");
                        });
                    }
                });
            }
            
            function fecharPedido(pedidoId) {
                let html = "<div class=\"mb-3\">";
                html += "<label class=\"form-label\">Forma de Pagamento</label>";
                html += "<select class=\"form-select\" id=\"formaPagamento\" required>";
                html += "<option value=\"\">Selecione a forma de pagamento</option>";
                html += "<option value=\"Dinheiro\">Dinheiro</option>";
                html += "<option value=\"Cartão de Débito\">Cartão de Débito</option>";
                html += "<option value=\"Cartão de Crédito\">Cartão de Crédito</option>";
                html += "<option value=\"PIX\">PIX</option>";
                html += "<option value=\"Vale Refeição\">Vale Refeição</option>";
                html += "</select>";
                html += "</div>";
                
                html += "<div class=\"mb-3\">";
                html += "<label class=\"form-label\">Troco para (se dinheiro)</label>";
                html += "<input type=\"number\" class=\"form-control\" id=\"trocoPara\" step=\"0.01\" min=\"0\" placeholder=\"0,00\">";
                html += "</div>";
                
                html += "<div class=\"mb-3\">";
                html += "<label class=\"form-label\">Observações do Fechamento</label>";
                html += "<textarea class=\"form-control\" id=\"observacaoFechamento\" rows=\"2\" placeholder=\"Observações adicionais...\"></textarea>";
                html += "</div>";

                Swal.fire({
                    title: "Fechar Pedido",
                    html: html,
                    showCancelButton: true,
                    confirmButtonText: "Fechar Pedido",
                    cancelButtonText: "Cancelar",
                    confirmButtonColor: "#28a745",
                    preConfirm: () => {
                        const formaPagamento = document.getElementById("formaPagamento").value;
                        const trocoPara = document.getElementById("trocoPara").value;
                        const observacaoFechamento = document.getElementById("observacaoFechamento").value;
                        
                        if (!formaPagamento) {
                            Swal.showValidationMessage("Selecione a forma de pagamento");
                            return false;
                        }
                        
                        return { formaPagamento, trocoPara, observacaoFechamento };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const { formaPagamento, trocoPara, observacaoFechamento } = result.value;
                        
                        fetch("index.php?action=pedidos&t=" + Date.now(), {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                                "X-Requested-With": "XMLHttpRequest"
                            },
                            body: `fechar_pedido=1&pedido_id=${pedidoId}&forma_pagamento=${encodeURIComponent(formaPagamento)}&troco_para=${trocoPara}&observacao_fechamento=${encodeURIComponent(observacaoFechamento)}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire("Sucesso", "Pedido fechado com sucesso!", "success");
                                setTimeout(() => location.reload(), 1500);
                            } else {
                                Swal.fire("Erro", data.message, "error");
                            }
                        })
                        .catch(error => {
                            console.error("Error:", error);
                            Swal.fire("Erro", "Erro ao fechar pedido", "error");
                        });
                    }
                });
            }
            </script>';
            
            echo json_encode(['success' => true, 'html' => $html]);
            break;
            
        default:
            throw new \Exception('Ação não encontrada');
    }
    
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
