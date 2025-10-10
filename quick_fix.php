<?php
require_once 'config/database.php';

$db = \System\Database::getInstance();

// Fix sequence
$maxId = $db->fetch($db->query("SELECT MAX(id) as max_id FROM produtos"));
$newSeq = ($maxId['max_id'] ?: 0) + 1;
$db->query("SELECT setval('produtos_id_seq', $newSeq)");
echo "Sequence fixed to: $newSeq\n";

// Check categories
$cats = $db->fetchAll("SELECT COUNT(*) as count FROM categorias WHERE tenant_id = 1 AND filial_id = 1");
$catCount = $cats[0]['count'];

if ($catCount == 0) {
    $defaultCats = ['Lanches', 'Bebidas', 'Porções', 'Sobremesas'];
    foreach ($defaultCats as $cat) {
        $db->query("INSERT INTO categorias (nome, tenant_id, filial_id, created_at, updated_at) VALUES (?, 1, 1, NOW(), NOW())", [$cat]);
    }
    echo "Created default categories\n";
} else {
    echo "Categories already exist: $catCount\n";
}

echo "Done!\n";
?>
