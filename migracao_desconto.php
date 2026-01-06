<?php
// Migração para adicionar coluna desconto_aplicado na tabela pagamentos
// Este arquivo pode ser acessado via navegador para executar a migração

require_once 'index.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Verificar se a coluna já existe
    $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = 'pagamentos' AND column_name = 'desconto_aplicado'");
    $stmt->execute();
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exists) {
        // Adicionar coluna desconto_aplicado na tabela pagamentos
        $sql = "ALTER TABLE pagamentos ADD COLUMN desconto_aplicado DECIMAL(10,2) DEFAULT 0";
        $pdo->exec($sql);

        // Adicionar comentário
        $sql = "COMMENT ON COLUMN pagamentos.desconto_aplicado IS 'Valor do desconto aplicado ao pagamento (seja fixo ou percentual)'";
        $pdo->exec($sql);

        echo "<div style='color: green; font-family: Arial, sans-serif; padding: 20px;'>";
        echo "<h2>✅ Migração executada com sucesso!</h2>";
        echo "<p>Coluna <strong>desconto_aplicado</strong> adicionada à tabela <strong>pagamentos</strong></p>";
        echo "<p>Esta coluna armazenará o valor do desconto aplicado a cada pagamento.</p>";
        echo "</div>";
    } else {
        echo "<div style='color: blue; font-family: Arial, sans-serif; padding: 20px;'>";
        echo "<h2>ℹ️ Migração já executada</h2>";
        echo "<p>A coluna <strong>desconto_aplicado</strong> já existe na tabela <strong>pagamentos</strong></p>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div style='color: red; font-family: Arial, sans-serif; padding: 20px;'>";
    echo "<h2>❌ Erro ao executar migração</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>