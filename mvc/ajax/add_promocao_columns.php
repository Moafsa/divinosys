<?php
/**
 * Script direto para adicionar colunas de promoção
 * Execute este arquivo via navegador: http://localhost:8080/mvc/ajax/add_promocao_columns.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php';

use System\Database;

try {
    $db = Database::getInstance();
    
    $results = [];
    $errors = [];
    
    // Adicionar coluna preco_promocional
    try {
        $db->query("
            ALTER TABLE produtos 
            ADD COLUMN IF NOT EXISTS preco_promocional DECIMAL(10,2) DEFAULT NULL
        ");
        $results[] = "✅ Coluna 'preco_promocional' adicionada ou já existe";
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'already exists') !== false || 
            strpos($errorMsg, 'duplicate') !== false) {
            $results[] = "ℹ️  Coluna 'preco_promocional' já existe";
        } else {
            $errors[] = "Erro ao adicionar preco_promocional: " . $errorMsg;
            $results[] = "❌ Erro: " . $errorMsg;
        }
    }
    
    // Adicionar coluna em_promocao
    try {
        $db->query("
            ALTER TABLE produtos 
            ADD COLUMN IF NOT EXISTS em_promocao BOOLEAN DEFAULT false
        ");
        $results[] = "✅ Coluna 'em_promocao' adicionada ou já existe";
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, 'already exists') !== false || 
            strpos($errorMsg, 'duplicate') !== false) {
            $results[] = "ℹ️  Coluna 'em_promocao' já existe";
        } else {
            $errors[] = "Erro ao adicionar em_promocao: " . $errorMsg;
            $results[] = "❌ Erro: " . $errorMsg;
        }
    }
    
    // Adicionar comentários
    try {
        $db->query("
            COMMENT ON COLUMN produtos.preco_promocional IS 'Preço promocional do produto. Se definido e em_promocao = true, será usado no lugar do preco_normal'
        ");
        $results[] = "✅ Comentário adicionado em preco_promocional";
    } catch (Exception $e) {
        // Ignorar erro de comentário
    }
    
    try {
        $db->query("
            COMMENT ON COLUMN produtos.em_promocao IS 'Indica se o produto está em promoção. Se true e preco_promocional definido, exibe o preço promocional'
        ");
        $results[] = "✅ Comentário adicionado em em_promocao";
    } catch (Exception $e) {
        // Ignorar erro de comentário
    }
    
    // Verificar se as colunas existem agora
    $verification = [];
    try {
        $checkPrecoPromocional = $db->fetch("
            SELECT 1 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
              AND table_name = 'produtos' 
              AND column_name = 'preco_promocional'
            LIMIT 1
        ");
        
        $checkEmPromocao = $db->fetch("
            SELECT 1 
            FROM information_schema.columns 
            WHERE table_schema = 'public' 
              AND table_name = 'produtos' 
              AND column_name = 'em_promocao'
            LIMIT 1
        ");
        
        if ($checkPrecoPromocional && $checkEmPromocao) {
            $verification[] = "✅ Coluna 'preco_promocional' existe no banco";
            $verification[] = "✅ Coluna 'em_promocao' existe no banco";
        } else {
            if (!$checkPrecoPromocional) {
                $verification[] = "❌ Coluna 'preco_promocional' NÃO encontrada";
            }
            if (!$checkEmPromocao) {
                $verification[] = "❌ Coluna 'em_promocao' NÃO encontrada";
            }
        }
    } catch (Exception $e) {
        $verification[] = "⚠️  Erro ao verificar colunas: " . $e->getMessage();
    }
    
    echo json_encode([
        'success' => empty($errors),
        'message' => empty($errors) ? 'Colunas de promoção adicionadas com sucesso!' : 'Alguns erros ocorreram',
        'errors' => $errors,
        'results' => $results,
        'verification' => $verification
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

