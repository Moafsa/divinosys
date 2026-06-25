<?php
/**
 * Script para popular cardápio do Divino Lanches
 * Tenant: delorenzzi1977@gmail.com
 * Filial: Praia
 */

require_once __DIR__ . '/vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    // Buscar tenant
    $tenant = $db->fetch("SELECT id, nome FROM tenants WHERE email = ?", ['delorenzi1977@gmail.com']);
    if (!$tenant) {
        throw new Exception("Tenant não encontrado");
    }
    
    echo "Tenant encontrado: {$tenant['nome']} (ID: {$tenant['id']})\n";
    
    // Buscar filial Praia
    $filial = $db->fetch("SELECT id, nome FROM filiais WHERE tenant_id = ? AND LOWER(nome) LIKE ?", [$tenant['id'], '%praia%']);
    if (!$filial) {
        throw new Exception("Filial Praia não encontrada");
    }
    
    echo "Filial encontrada: {$filial['nome']} (ID: {$filial['id']})\n\n";
    
    $tenantId = $tenant['id'];
    $filialId = $filial['id'];
    
    // Definir categorias
    $categorias = [
        'Xis' => 'Lanches tipo Xis',
        'Cachorro Quente' => 'Hot Dogs',
        'Prato Executivo' => 'Pratos executivos',
        'Torrada' => 'Torradas',
        'Tábua de Frios' => 'Tábuas de frios',
        'Porções' => 'Porções',
        'Bebidas' => 'Bebidas'
    ];
    
    // Criar categorias (buscar por tenant_id e nome, não por filial_id devido à constraint)
    $categoriaIds = [];
    foreach ($categorias as $nome => $descricao) {
        $categoriaExistente = $db->fetch(
            "SELECT id FROM categorias WHERE nome = ? AND tenant_id = ?",
            [$nome, $tenantId]
        );
        
        if ($categoriaExistente) {
            $categoriaIds[$nome] = $categoriaExistente['id'];
            echo "Categoria '{$nome}' já existe (ID: {$categoriaExistente['id']})\n";
        } else {
            try {
                $categoriaId = $db->insert('categorias', [
                    'nome' => $nome,
                    'descricao' => $descricao,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId,
                    'ativo' => true,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $categoriaIds[$nome] = $categoriaId;
                echo "Categoria '{$nome}' criada (ID: {$categoriaId})\n";
            } catch (Exception $e) {
                // Se der erro de constraint, buscar novamente
                $categoriaExistente = $db->fetch(
                    "SELECT id FROM categorias WHERE nome = ? AND tenant_id = ?",
                    [$nome, $tenantId]
                );
                if ($categoriaExistente) {
                    $categoriaIds[$nome] = $categoriaExistente['id'];
                    echo "Categoria '{$nome}' encontrada após tentativa (ID: {$categoriaExistente['id']})\n";
                } else {
                    throw $e;
                }
            }
        }
    }
    
    echo "\n";
    
    // Definir ingredientes
    $ingredientes = [
        'Pão',
        'Hambúrguer',
        'Ovo',
        'Presunto',
        'Queijo',
        'Milho',
        'Ervilha',
        'Alface',
        'Tomate',
        'Maionese',
        'Ketchup',
        'Mostarda',
        'Bacon',
        'Filé',
        'Hambúrguer de Frango',
        'Coração de Frango',
        'Calabresa',
        'Palmito',
        'Pepino',
        'Cebola',
        'Rúcula',
        'Tomate Seco',
        'Carne',
        'Frango',
        'Salsicha',
        'Molho',
        'Queijo Ralado',
        'Batata Palha',
        'Salsicha Vegetariana',
        'Patinho',
        'Arroz',
        'Feijão',
        'Batata Frita',
        'Ovos',
        'Salada Mista',
        'Salame',
        'Ovo de Codorna',
        'Azeitona',
        'Anel de Cebola',
        'Polenta',
        'Batata',
        'Queijo (porção)',
        'Água Mineral',
        'H2OH',
        'Refrigerante Lata',
        'Refrigerante 600ml',
        'Refrigerante 2L',
        'Cerveja Long Neck',
        'Cerveja 600ml'
    ];
    
    // Criar ingredientes (buscar por tenant_id e nome, não por filial_id devido à constraint)
    $ingredienteIds = [];
    foreach ($ingredientes as $nome) {
        $ingredienteExistente = $db->fetch(
            "SELECT id FROM ingredientes WHERE nome = ? AND tenant_id = ?",
            [$nome, $tenantId]
        );
        
        if ($ingredienteExistente) {
            $ingredienteIds[$nome] = $ingredienteExistente['id'];
        } else {
            try {
                $ingredienteId = $db->insert('ingredientes', [
                    'nome' => $nome,
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId,
                    'ativo' => true,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                $ingredienteIds[$nome] = $ingredienteId;
                echo "Ingrediente '{$nome}' criado (ID: {$ingredienteId})\n";
            } catch (Exception $e) {
                // Se der erro de constraint, buscar novamente
                $ingredienteExistente = $db->fetch(
                    "SELECT id FROM ingredientes WHERE nome = ? AND tenant_id = ?",
                    [$nome, $tenantId]
                );
                if ($ingredienteExistente) {
                    $ingredienteIds[$nome] = $ingredienteExistente['id'];
                } else {
                    throw $e;
                }
            }
        }
    }
    
    echo "\n";
    
    // Definir produtos
    $produtos = [
        // Xis
        [
            'nome' => 'Xis da Casa',
            'descricao' => 'Pão, hambúrguer, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese, ketchup e mostarda',
            'categoria' => 'Xis',
            'preco_normal' => 33.00,
            'preco_mini' => 29.00,
            'ingredientes' => ['Pão', 'Hambúrguer', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese', 'Ketchup', 'Mostarda']
        ],
        [
            'nome' => 'Xis Duplo',
            'descricao' => 'Pão, 2 hambúrgueres, 2 ovos, 2 presuntos, 2 queijos, milho, ervilha, alface, tomate, maionese, ketchup e mostarda',
            'categoria' => 'Xis',
            'preco_normal' => 40.00,
            'preco_mini' => 35.00,
            'ingredientes' => ['Pão', 'Hambúrguer', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese', 'Ketchup', 'Mostarda']
        ],
        [
            'nome' => 'Xis Bacon',
            'descricao' => 'Pão, hambúrguer, bacon, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese, ketchup e mostarda',
            'categoria' => 'Xis',
            'preco_normal' => 38.00,
            'preco_mini' => 35.00,
            'ingredientes' => ['Pão', 'Hambúrguer', 'Bacon', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese', 'Ketchup', 'Mostarda']
        ],
        [
            'nome' => 'Xis Filé',
            'descricao' => 'Pão, filé, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese, ketchup e mostarda',
            'categoria' => 'Xis',
            'preco_normal' => 50.00,
            'preco_mini' => 45.00,
            'ingredientes' => ['Pão', 'Filé', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese', 'Ketchup', 'Mostarda']
        ],
        [
            'nome' => 'Xis Frango',
            'descricao' => 'Pão, hambúrguer de frango, ovo, presunto, queijo, milho, alface, tomate, maionese, ketchup e mostarda',
            'categoria' => 'Xis',
            'preco_normal' => 38.00,
            'preco_mini' => 35.00,
            'ingredientes' => ['Pão', 'Hambúrguer de Frango', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Alface', 'Tomate', 'Maionese', 'Ketchup', 'Mostarda']
        ],
        [
            'nome' => 'Xis Coração',
            'descricao' => 'Pão, coração de frango, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese, ketchup e mostarda',
            'categoria' => 'Xis',
            'preco_normal' => 38.00,
            'preco_mini' => 35.00,
            'ingredientes' => ['Pão', 'Coração de Frango', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese', 'Ketchup', 'Mostarda']
        ],
        [
            'nome' => 'Xis Calabresa',
            'descricao' => 'Pão, calabresa, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese, ketchup e mostarda',
            'categoria' => 'Xis',
            'preco_normal' => 38.00,
            'preco_mini' => 35.00,
            'ingredientes' => ['Pão', 'Calabresa', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Alface', 'Tomate', 'Maionese', 'Ketchup', 'Mostarda']
        ],
        [
            'nome' => 'Xis Vegetariano',
            'descricao' => 'Pão, alface, tomate, queijo, palmito, pepino, milho, ervilha, maionese, ketchup e mostarda',
            'categoria' => 'Xis',
            'preco_normal' => 35.00,
            'preco_mini' => 32.00,
            'ingredientes' => ['Pão', 'Alface', 'Tomate', 'Queijo', 'Palmito', 'Pepino', 'Milho', 'Ervilha', 'Maionese', 'Ketchup', 'Mostarda']
        ],
        [
            'nome' => 'Xis Cebola',
            'descricao' => 'Pão, hambúrguer, cebola, ovo, presunto, queijo, milho, ervilha, maionese, tomate, ketchup e mostarda',
            'categoria' => 'Xis',
            'preco_normal' => 38.00,
            'preco_mini' => 35.00,
            'ingredientes' => ['Pão', 'Hambúrguer', 'Cebola', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Maionese', 'Tomate', 'Ketchup', 'Mostarda']
        ],
        [
            'nome' => 'Xis Tomate Seco com Rúcula',
            'descricao' => 'Pão, filé, rúcula, tomate seco, ovo, presunto, queijo, milho, ervilha, maionese, ketchup e mostarda',
            'categoria' => 'Xis',
            'preco_normal' => 52.00,
            'preco_mini' => 48.00,
            'ingredientes' => ['Pão', 'Filé', 'Rúcula', 'Tomate Seco', 'Ovo', 'Presunto', 'Queijo', 'Milho', 'Ervilha', 'Maionese', 'Ketchup', 'Mostarda']
        ],
        [
            'nome' => 'Xis Entrevero',
            'descricao' => 'Pão, calabresa, coração, carne, frango, bacon, cebola, ovo, queijo, presunto, alface, tomate, milho, ervilha e maionese',
            'categoria' => 'Xis',
            'preco_normal' => 50.00,
            'preco_mini' => 45.00,
            'ingredientes' => ['Pão', 'Calabresa', 'Coração de Frango', 'Carne', 'Frango', 'Bacon', 'Cebola', 'Ovo', 'Queijo', 'Presunto', 'Alface', 'Tomate', 'Milho', 'Ervilha', 'Maionese']
        ],
        
        // Cachorro Quente
        [
            'nome' => 'Cachorro Quente Simples',
            'descricao' => 'Pão, 1 salsicha, molho, milho, ervilha, queijo ralado, maionese e batata palha',
            'categoria' => 'Cachorro Quente',
            'preco_normal' => 27.00,
            'preco_mini' => null,
            'ingredientes' => ['Pão', 'Salsicha', 'Molho', 'Milho', 'Ervilha', 'Queijo Ralado', 'Maionese', 'Batata Palha']
        ],
        [
            'nome' => 'Cachorro Quente Duplo',
            'descricao' => 'Pão, 2 salsichas, molho, milho, ervilha, queijo ralado, maionese e batata palha',
            'categoria' => 'Cachorro Quente',
            'preco_normal' => 30.00,
            'preco_mini' => null,
            'ingredientes' => ['Pão', 'Salsicha', 'Molho', 'Milho', 'Ervilha', 'Queijo Ralado', 'Maionese', 'Batata Palha']
        ],
        [
            'nome' => 'Cachorro Quente Vegetariano',
            'descricao' => 'Pão, 1 salsicha vegetariana, molho, milho, ervilha, queijo ralado, maionese e batata palha',
            'categoria' => 'Cachorro Quente',
            'preco_normal' => 28.00,
            'preco_mini' => null,
            'ingredientes' => ['Pão', 'Salsicha Vegetariana', 'Molho', 'Milho', 'Ervilha', 'Queijo Ralado', 'Maionese', 'Batata Palha']
        ],
        
        // Prato Executivo
        [
            'nome' => 'Prato Feito da Casa',
            'descricao' => 'Patinho, arroz, feijão, batata frita, ovos, salada mista e pão',
            'categoria' => 'Prato Executivo',
            'preco_normal' => 38.00,
            'preco_mini' => null,
            'ingredientes' => ['Patinho', 'Arroz', 'Feijão', 'Batata Frita', 'Ovos', 'Salada Mista', 'Pão']
        ],
        
        // Torrada
        [
            'nome' => 'Torrada Americana',
            'descricao' => 'Tomate, alface e maionese. Pão de xis, 2 presuntos, 2 queijos, ovo',
            'categoria' => 'Torrada',
            'preco_normal' => 28.00,
            'preco_mini' => null,
            'ingredientes' => ['Pão', 'Tomate', 'Alface', 'Maionese', 'Presunto', 'Queijo', 'Ovo']
        ],
        [
            'nome' => 'Torrada com Bacon',
            'descricao' => '3 pães, 2 presuntos, 4 queijos, alface, tomate e maionese',
            'categoria' => 'Torrada',
            'preco_normal' => 32.00,
            'preco_mini' => null,
            'ingredientes' => ['Pão', 'Presunto', 'Queijo', 'Alface', 'Tomate', 'Maionese', 'Bacon']
        ],
        
        // Tábua de Frios
        [
            'nome' => 'Tábua de Frios',
            'descricao' => 'Porções de 200 gr. cada: Queijo, salame, ovo de codorna, azeitona e pepino',
            'categoria' => 'Tábua de Frios',
            'preco_normal' => 100.00,
            'preco_mini' => null,
            'ingredientes' => ['Queijo', 'Salame', 'Ovo de Codorna', 'Azeitona', 'Pepino']
        ],
        
        // Porções
        [
            'nome' => 'Anel de Cebola 300gr',
            'descricao' => 'Anel de cebola 300gr',
            'categoria' => 'Porções',
            'preco_normal' => 30.00,
            'preco_mini' => null,
            'ingredientes' => ['Anel de Cebola']
        ],
        [
            'nome' => 'Polenta 300gr',
            'descricao' => 'Polenta 300gr',
            'categoria' => 'Porções',
            'preco_normal' => 25.00,
            'preco_mini' => null,
            'ingredientes' => ['Polenta']
        ],
        [
            'nome' => 'Batata 200gr',
            'descricao' => 'Batata frita 200gr',
            'categoria' => 'Porções',
            'preco_normal' => 20.00,
            'preco_mini' => null,
            'ingredientes' => ['Batata']
        ],
        [
            'nome' => 'Batata 400gr',
            'descricao' => 'Batata frita 400gr',
            'categoria' => 'Porções',
            'preco_normal' => 32.00,
            'preco_mini' => null,
            'ingredientes' => ['Batata']
        ],
        [
            'nome' => 'Queijo 300gr',
            'descricao' => 'Queijo frito 300gr',
            'categoria' => 'Porções',
            'preco_normal' => 35.00,
            'preco_mini' => null,
            'ingredientes' => ['Queijo (porção)']
        ],
        
        // Bebidas
        [
            'nome' => 'Água Mineral',
            'descricao' => 'Água mineral',
            'categoria' => 'Bebidas',
            'preco_normal' => 5.00,
            'preco_mini' => null,
            'ingredientes' => ['Água Mineral']
        ],
        [
            'nome' => 'H2OH 600ml',
            'descricao' => 'H2OH 600ml',
            'categoria' => 'Bebidas',
            'preco_normal' => 10.00,
            'preco_mini' => null,
            'ingredientes' => ['H2OH']
        ],
        [
            'nome' => 'Refrigerante Lata',
            'descricao' => 'Refrigerante em lata',
            'categoria' => 'Bebidas',
            'preco_normal' => 9.00,
            'preco_mini' => null,
            'ingredientes' => ['Refrigerante Lata']
        ],
        [
            'nome' => 'Refrigerante 600ml',
            'descricao' => 'Refrigerante 600ml',
            'categoria' => 'Bebidas',
            'preco_normal' => 10.00,
            'preco_mini' => null,
            'ingredientes' => ['Refrigerante 600ml']
        ],
        [
            'nome' => 'Refrigerante 2L',
            'descricao' => 'Refrigerante 2 litros',
            'categoria' => 'Bebidas',
            'preco_normal' => 16.00,
            'preco_mini' => null,
            'ingredientes' => ['Refrigerante 2L']
        ],
        [
            'nome' => 'Cerveja Long Neck',
            'descricao' => 'Cerveja long neck',
            'categoria' => 'Bebidas',
            'preco_normal' => 14.00,
            'preco_mini' => null,
            'ingredientes' => ['Cerveja Long Neck']
        ],
        [
            'nome' => 'Cerveja 600ml',
            'descricao' => 'Cerveja 600ml',
            'categoria' => 'Bebidas',
            'preco_normal' => 18.00,
            'preco_mini' => null,
            'ingredientes' => ['Cerveja 600ml']
        ]
    ];
    
    // Criar produtos e vincular ingredientes
    $produtosCriados = 0;
    foreach ($produtos as $produto) {
        // Verificar se produto já existe
        $produtoExistente = $db->fetch(
            "SELECT id FROM produtos WHERE nome = ? AND tenant_id = ? AND filial_id = ?",
            [$produto['nome'], $tenantId, $filialId]
        );
        
        if ($produtoExistente) {
            $produtoId = $produtoExistente['id'];
            echo "Produto '{$produto['nome']}' já existe (ID: {$produtoId})\n";
        } else {
            $produtoId = $db->insert('produtos', [
                'nome' => $produto['nome'],
                'descricao' => $produto['descricao'],
                'categoria_id' => $categoriaIds[$produto['categoria']],
                'preco_normal' => $produto['preco_normal'],
                'preco_mini' => $produto['preco_mini'],
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'ativo' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $produtosCriados++;
            echo "Produto '{$produto['nome']}' criado (ID: {$produtoId})\n";
        }
        
        // Vincular ingredientes
        foreach ($produto['ingredientes'] as $ingredienteNome) {
            if (isset($ingredienteIds[$ingredienteNome])) {
                $ingredienteId = $ingredienteIds[$ingredienteNome];
                
                // Verificar se já está vinculado
                $vinculoExistente = $db->fetch(
                    "SELECT id FROM produto_ingredientes WHERE produto_id = ? AND ingrediente_id = ? AND tenant_id = ? AND filial_id = ?",
                    [$produtoId, $ingredienteId, $tenantId, $filialId]
                );
                
                if (!$vinculoExistente) {
                    $db->insert('produto_ingredientes', [
                        'produto_id' => $produtoId,
                        'ingrediente_id' => $ingredienteId,
                        'tenant_id' => $tenantId,
                        'filial_id' => $filialId,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }
    }
    
    echo "\n=== Resumo ===\n";
    echo "Categorias: " . count($categorias) . "\n";
    echo "Ingredientes: " . count($ingredientes) . "\n";
    echo "Produtos criados: {$produtosCriados}\n";
    echo "Produtos totais: " . count($produtos) . "\n";
    echo "\nCardápio populado com sucesso!\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

