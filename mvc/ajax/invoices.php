<?php
/**
 * AJAX Handler for Invoices
 * Processes invoice-related requests
 */

session_start();
require_once __DIR__ . '/../controller/InvoiceController.php';

try {
    $action = $_GET['action'] ?? '';
    $controller = new InvoiceController();
    
    switch ($action) {
        case 'scheduleInvoice':
            $controller->scheduleInvoice();
            break;
            
        case 'issueInvoice':
            $controller->issueInvoice();
            break;
            
        case 'cancelInvoice':
            $controller->cancelInvoice();
            break;
            
        case 'listInvoices':
            $controller->listInvoices();
            break;
            
        case 'getInvoice':
            $controller->getInvoice();
            break;
            
        case 'getInvoiceStats':
            $controller->getInvoiceStats();
            break;
            
        case 'createInvoiceFromOrder':
            $controller->createInvoiceFromOrder();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Invoice AJAX Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
