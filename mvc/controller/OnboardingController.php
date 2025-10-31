<?php
/**
 * Controller de Onboarding
 * Gerencia o processo de cadastro de novos estabelecimentos
 */

require_once __DIR__ . '/../model/Tenant.php';
require_once __DIR__ . '/../model/Plan.php';
require_once __DIR__ . '/../model/Subscription.php';
require_once __DIR__ . '/../model/AsaasPayment.php';
require_once __DIR__ . '/../../system/WhatsApp/WuzAPIManager.php';

class OnboardingController {
    private $tenantModel;
    private $planModel;
    private $subscriptionModel;
    private $asaasPayment;
    private $conn;
    
    public function __construct() {
        $this->tenantModel = new Tenant();
        $this->planModel = new Plan();
        $this->subscriptionModel = new Subscription();
        $this->asaasPayment = new AsaasPayment();
        $this->conn = \System\Database::getInstance();
    }
    
    /**
     * Criar novo estabelecimento completo
     */
    public function createEstablishment() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validar dados obrigatórios
        if (empty($data['nome']) || empty($data['subdomain']) || empty($data['email']) || 
            empty($data['telefone']) || empty($data['plano_id']) || 
            empty($data['admin_login']) || empty($data['admin_senha'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados obrigatórios não fornecidos']);
            return;
        }
        
        // Verificar se subdomain está disponível
        if (!$this->tenantModel->isSubdomainAvailable($data['subdomain'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Subdomain já está em uso']);
            return;
        }
        
        // Iniciar transação
        $this->conn->getConnection()->beginTransaction();
        
        try {
            // 1. Criar tenant
            $tenant_data = [
                'nome' => $data['nome'],
                'subdomain' => $data['subdomain'],
                'cnpj' => $data['cnpj'] ?? null,
                'telefone' => $data['telefone'],
                'email' => $data['email'],
                'endereco' => $data['endereco'] ?? null,
                'cor_primaria' => $data['cor_primaria'] ?? '#667eea',
                'plano_id' => $data['plano_id'],
                'status' => 'ativo'
            ];
            
            $tenant_id = $this->tenantModel->create($tenant_data);
            
            if (!$tenant_id) {
                throw new Exception('Erro ao criar tenant');
            }
            
            // 2. Criar usuário administrador
            $senha_hash = password_hash($data['admin_senha'], PASSWORD_BCRYPT);
            
            $admin_id = $this->conn->insert('usuarios', [
                'login' => $data['admin_login'],
                'senha' => $senha_hash,
                'nivel' => 1,
                'pergunta' => 'Sistema',
                'resposta' => 'Sistema',
                'tenant_id' => $tenant_id,
            ]);
            
            if (!$admin_id) {
                throw new Exception('Erro ao criar usuário administrador');
            }
            
            // 3. Criar assinatura com trial
            $plano = $this->planModel->getById($data['plano_id']);
            
            $subscription_data = [
                'tenant_id' => $tenant_id,
                'plano_id' => $data['plano_id'],
                'status' => 'trial',
                'valor' => $plano['preco_mensal'],
                'periodicidade' => 'mensal',
                'data_inicio' => date('Y-m-d'),
                'data_proxima_cobranca' => date('Y-m-d', strtotime('+14 days')),
                'trial_ate' => date('Y-m-d', strtotime('+14 days'))
            ];
            
            $subscription_id = $this->subscriptionModel->create($subscription_data);
            
            if (!$subscription_id) {
                throw new Exception('Erro ao criar assinatura');
            }
            
            // 4. Criar categorias padrão
            $this->createDefaultCategories($tenant_id);
            
            // 5. Criar mesas se configurado
            if (!empty($data['num_mesas']) && $data['tem_mesas']) {
                $this->createDefaultMesas($tenant_id, intval($data['num_mesas']));
            }
            
            // 6. Configurar opções do tenant
            $this->setupTenantConfig($tenant_id, $data);
            
            // 7. Enviar email de boas-vindas (opcional)
            // $this->sendWelcomeEmail($data['email'], $data['nome'], $data['subdomain']);
            
            // Commit da transação
            pg_query($this->conn, 'COMMIT');
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'tenant_id' => $tenant_id,
                'message' => 'Estabelecimento criado com sucesso!'
            ]);
            
        } catch (Exception $e) {
            // Rollback em caso de erro
            pg_query($this->conn, 'ROLLBACK');
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Criar categorias padrão para novo tenant
     */
    private function createDefaultCategories($tenant_id) {
        $categorias = [
            ['nome' => 'Lanches', 'imagem' => null],
            ['nome' => 'Bebidas', 'imagem' => null],
            ['nome' => 'Porções', 'imagem' => null],
            ['nome' => 'Sobremesas', 'imagem' => null]
        ];
        
        // Buscar primeira filial do tenant
        $filial = $this->conn->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenant_id]);
        $filial_id = $filial['id'] ?? null;
        
        foreach ($categorias as $categoria) {
            $this->conn->insert('categorias', [
                'nome' => $categoria['nome'],
                'tenant_id' => $tenant_id,
                'filial_id' => $filial_id,
                'imagem' => $categoria['imagem']
            ]);
        }
    }
    
    /**
     * Criar mesas padrão para novo tenant
     */
    private function createDefaultMesas($tenant_id, $quantidade) {
        // Buscar primeira filial do tenant
        $filial = $this->conn->fetch("SELECT id FROM filiais WHERE tenant_id = ? ORDER BY id LIMIT 1", [$tenant_id]);
        $filial_id = $filial['id'] ?? null;
        
        for ($i = 1; $i <= $quantidade; $i++) {
            $id_mesa = 'M' . str_pad($i, 3, '0', STR_PAD_LEFT); // M001, M002, etc.
            
            $this->conn->insert('mesas', [
                'id_mesa' => $id_mesa,
                'numero' => $i,
                'tenant_id' => $tenant_id,
                'filial_id' => $filial_id,
                'capacidade' => 4,
                'status' => '1'
            ]);
        }
    }
    
    /**
     * Configurar opções do tenant
     */
    private function setupTenantConfig($tenant_id, $data) {
        $configs = [
            ['chave' => 'tem_delivery', 'valor' => $data['tem_delivery'] ?? true, 'tipo' => 'boolean'],
            ['chave' => 'tem_mesas', 'valor' => $data['tem_mesas'] ?? true, 'tipo' => 'boolean'],
            ['chave' => 'tem_balcao', 'valor' => $data['tem_balcao'] ?? true, 'tipo' => 'boolean'],
            ['chave' => 'num_mesas', 'valor' => $data['num_mesas'] ?? 10, 'tipo' => 'integer']
        ];
        
        foreach ($configs as $config) {
            $valor = is_bool($config['valor']) ? ($config['valor'] ? 'true' : 'false') : $config['valor'];
            
            $this->conn->insert('tenant_config', [
                'tenant_id' => $tenant_id,
                'chave' => $config['chave'],
                'valor' => $valor,
                'tipo' => $config['tipo']
            ]);
        }
    }
    
    /**
     * Criar estabelecimento com integração de pagamento Asaas
     */
    public function createEstablishmentWithPayment($data) {
        try {
            // Validar dados obrigatórios
            if (empty($data['nome']) || empty($data['subdomain']) || empty($data['email']) || 
                empty($data['telefone']) || empty($data['plano_id']) || 
                empty($data['admin_login']) || empty($data['admin_senha'])) {
                return ['success' => false, 'error' => 'Dados obrigatórios não fornecidos'];
            }
            
            // Verificar se subdomain está disponível
            if (!$this->tenantModel->isSubdomainAvailable($data['subdomain'])) {
                return ['success' => false, 'error' => 'Subdomain já está em uso'];
            }
            
            // Iniciar transação
            $this->conn->getConnection()->beginTransaction();
            
            // 1. Criar tenant
            $tenant_data = [
                'nome' => $data['nome'],
                'subdomain' => $data['subdomain'],
                'cnpj' => $data['cnpj'] ?? null,
                'telefone' => $data['telefone'],
                'email' => $data['email'],
                'endereco' => $data['endereco'] ?? null,
                'cidade' => $data['cidade'] ?? null,
                'estado' => $data['estado'] ?? null,
                'cep' => $data['cep'] ?? null,
                'cor_primaria' => $data['cor_primaria'] ?? '#667eea',
                'plano_id' => $data['plano_id'],
                'status' => 'ativo'
            ];
            
            $tenant_id = $this->tenantModel->create($tenant_data);
            
            if (!$tenant_id) {
                throw new Exception('Erro ao criar tenant');
            }
            
            // 1.1. Criar filial padrão (obrigatório)
            $filial_id = $this->conn->insert('filiais', [
                'tenant_id' => $tenant_id,
                'nome' => $data['nome'] . ' - Matriz',
                'email' => $data['email'],
                'telefone' => $data['telefone'],
                'endereco' => $data['endereco'] ?? null,
                'cidade' => $data['cidade'] ?? null,
                'estado' => $data['estado'] ?? null,
                'cep' => $data['cep'] ?? null,
                'status' => 'ativo',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            if (!$filial_id) {
                throw new Exception('Erro ao criar filial padrão');
            }
            
            // 2. Criar usuário administrador
            $senha_hash = password_hash($data['admin_senha'], PASSWORD_BCRYPT);
            
            $admin_id = $this->conn->insert('usuarios', [
                'login' => $data['admin_login'],
                'senha' => $senha_hash,
                'nivel' => 1,
                'pergunta' => 'Sistema',
                'resposta' => 'Sistema',
                'tenant_id' => $tenant_id,
                'filial_id' => $filial_id,
            ]);
            
            if (!$admin_id) {
                throw new Exception('Erro ao criar usuário administrador');
            }
            
            // 3. Criar assinatura com trial
            $plano = $this->planModel->getById($data['plano_id']);
            
            $subscription_data = [
                'tenant_id' => $tenant_id,
                'plano_id' => $data['plano_id'],
                'status' => 'trial',
                'valor' => $plano['preco_mensal'],
                'periodicidade' => $data['periodicidade'] ?? 'mensal',
                'data_inicio' => date('Y-m-d'),
                'data_proxima_cobranca' => date('Y-m-d', strtotime('+14 days')),
                'trial_ate' => date('Y-m-d', strtotime('+14 days'))
            ];
            
            $subscription_id = $this->subscriptionModel->create($subscription_data);
            
            if (!$subscription_id) {
                throw new Exception('Erro ao criar assinatura');
            }
            
            // 4. Criar categorias padrão
            $this->createDefaultCategories($tenant_id);
            
            // 5. Criar mesas se configurado
            if (!empty($data['num_mesas']) && $data['tem_mesas']) {
                $this->createDefaultMesas($tenant_id, intval($data['num_mesas']));
            }
            
            // 6. Configurar opções do tenant
            $this->setupTenantConfig($tenant_id, $data);
            
            // 7. Integração com Asaas - Criar cliente (OPCIONAL)
            $asaas_customer = null;
            $asaas_charge = null;
            $payment_id = null;
            
            $asaasEnabled = !empty($_ENV['ASAAS_API_KEY']) && $_ENV['ASAAS_API_KEY'] !== 'sua_api_key_aqui';
            
            if ($asaasEnabled) {
                $tenant_info = [
                    'id' => $tenant_id,
                    'nome' => $data['nome'],
                    'email' => $data['email'],
                    'telefone' => $data['telefone'],
                    'cnpj' => $data['cnpj'] ?? null
                ];
                
                $asaas_customer = $this->asaasPayment->createCustomer($tenant_info);
                
                if (!$asaas_customer['success']) {
                    error_log("OnboardingController - Asaas error (não crítico): " . json_encode($asaas_customer));
                    // Não bloquear criação do tenant se Asaas falhar
                    $asaas_customer = null;
                }
            } else {
                error_log("OnboardingController - Asaas desabilitado (sem API key válida)");
            }
            
            // 8. Criar assinatura recorrente no Asaas (se habilitado e cliente criado)
            if ($asaasEnabled && $asaas_customer) {
                // Mapear periodicidade para o Asaas
                $cycleMap = [
                    'mensal' => 'MONTHLY',
                    'semestral' => 'SEMIANNUALLY',
                    'anual' => 'YEARLY'
                ];
                
                $subscription_data_asaas = [
                    'asaas_customer_id' => $asaas_customer['data']['id'],
                    'valor' => $data['valor_final'] ?? $plano['preco_mensal'],
                    'descricao' => 'Assinatura ' . $plano['nome'] . ' - ' . $data['nome'],
                    'subscription_id' => $subscription_id,
                    'cycle' => $cycleMap[$data['periodicidade'] ?? 'mensal'] ?? 'MONTHLY',
                    'next_due_date' => date('Y-m-d', strtotime('+7 days'))
                ];
                
                $asaas_subscription = $this->asaasPayment->createSubscription($subscription_data_asaas);
                
                if ($asaas_subscription['success']) {
                    // Buscar a primeira cobrança da assinatura (que contém o PIX)
                    $asaas_subscription_id = $asaas_subscription['data']['id'];
                    error_log("OnboardingController - Assinatura criada no Asaas: $asaas_subscription_id");
                    
                    // Atualizar assinatura local com o ID do Asaas
                    $this->subscriptionModel->update($subscription_id, [
                        'asaas_subscription_id' => $asaas_subscription_id
                    ]);
                    error_log("OnboardingController - Assinatura local atualizada com asaas_subscription_id: $asaas_subscription_id");
                    
                    // Tentar buscar a primeira cobrança com múltiplas tentativas
                    $asaas_charge = null;
                    $maxTentativas = 5;
                    
                    for ($i = 0; $i < $maxTentativas; $i++) {
                        // Aguardar antes de cada tentativa (exceto a primeira)
                        if ($i > 0) {
                            sleep(2);
                        }
                        
                        // Buscar cobranças da assinatura
                        $paymentsResult = $this->asaasPayment->getSubscriptionPayments($asaas_subscription_id);
                        
                        if ($paymentsResult['success'] && !empty($paymentsResult['data'])) {
                            // Verificar estrutura da resposta
                            $paymentsData = $paymentsResult['data'];
                            if (isset($paymentsData['data']) && is_array($paymentsData['data']) && !empty($paymentsData['data'])) {
                                // Formato: { "data": [cobranças...] }
                                $firstPayment = $paymentsData['data'][0];
                            } elseif (is_array($paymentsData) && isset($paymentsData[0])) {
                                // Formato: [cobranças...]
                                $firstPayment = $paymentsData[0];
                            } else {
                                // Formato: cobrança única
                                $firstPayment = $paymentsData;
                            }
                            
                            $asaas_charge = [
                                'success' => true,
                                'data' => $firstPayment
                            ];
                            error_log("OnboardingController - Primeira cobrança encontrada na tentativa " . ($i + 1) . ": " . ($firstPayment['id'] ?? 'N/A'));
                            break;
                        } else {
                            error_log("OnboardingController - Tentativa " . ($i + 1) . "/$maxTentativas: cobrança ainda não disponível");
                        }
                    }
                    
                    // Se não encontrou após todas as tentativas, usar os dados da assinatura
                    if (!$asaas_charge || !isset($asaas_charge['data']['id'])) {
                        $asaas_charge = $asaas_subscription;
                        error_log("OnboardingController - Cobrança não gerada após $maxTentativas tentativas, usando dados da assinatura");
                    }
                    
                    // 9. Salvar dados do pagamento no banco
                    $valorPagamento = $data['valor_final'] ?? $plano['preco_mensal'];
                    
                    $payment_record = [
                        'tenant_id' => $tenant_id,
                        'filial_id' => $filial_id,
                        'assinatura_id' => $subscription_id,
                        'valor' => $valorPagamento,
                        'valor_pago' => $valorPagamento, // Valor pago = valor da assinatura para registro inicial
                        'forma_pagamento' => 'pix',
                        'status' => 'pendente',
                        'data_vencimento' => date('Y-m-d', strtotime('+7 days')),
                        'metodo_pagamento' => 'pix',
                        'gateway_payment_id' => $asaas_charge['data']['id'] ?? $asaas_subscription_id,
                        'gateway_customer_id' => $asaas_customer['data']['id'],
                        'gateway_response' => json_encode($asaas_charge['data']),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $db = \System\Database::getInstance();
                    $payment_id = $db->insert('pagamentos', $payment_record);
                } else {
                    error_log("OnboardingController - Erro ao criar cobrança Asaas (não crítico): " . json_encode($asaas_charge));
                }
            } else {
                error_log("OnboardingController - Pagamento Asaas pulado (Asaas desabilitado ou cliente não criado)");
            }
            
            // Commit da transação
            $this->conn->getConnection()->commit();
            
            // Preparar dados do retorno
            $response = [
                'success' => true,
                'tenant_id' => $tenant_id,
                'payment_id' => $payment_id,
                'message' => 'Estabelecimento criado com sucesso!'
            ];
            
            // Adicionar dados do Asaas se disponível
            if ($asaasEnabled && isset($asaas_subscription) && $asaas_subscription['success']) {
                $response['asaas_subscription_id'] = $asaas_subscription_id;
                
                // Se conseguiu pegar a primeira cobrança, usar a URL dela
                if (isset($asaas_charge['data']['id']) && isset($asaas_charge['data']['invoiceUrl'])) {
                    $response['asaas_payment_id'] = $asaas_charge['data']['id'];
                    $response['invoice_url'] = $asaas_charge['data']['invoiceUrl'];
                    $response['pix_code'] = $asaas_charge['data']['pixCopyAndPaste'] ?? null;
                    $response['pix_qr_code'] = $asaas_charge['data']['encodedImage'] ?? null;
                    error_log("OnboardingController - Cobrança encontrada com fatura: " . $response['invoice_url']);
                    
                    // Enviar fatura por WhatsApp
                    $this->sendInvoiceViaWhatsApp($tenant_id, $filial_id, $data, $response['invoice_url']);
                } else {
                    // Se não encontrou a cobrança, avisar que será enviada por email
                    $response['invoice_url'] = null;
                    $response['asaas_info'] = 'A primeira cobrança será enviada por email em alguns minutos.';
                    error_log("OnboardingController - Assinatura criada, mas primeira cobrança ainda não disponível");
                }
            } else {
                error_log("OnboardingController - Asaas não habilitado ou assinatura falhou");
            }
            
            return $response;
            
        } catch (Exception $e) {
            // Rollback em caso de erro
            $this->conn->getConnection()->rollBack();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar disponibilidade de subdomain
     */
    public function checkSubdomain() {
        $subdomain = $_GET['subdomain'] ?? '';
        
        if (empty($subdomain)) {
            http_response_code(400);
            echo json_encode(['error' => 'Subdomain não fornecido']);
            return;
        }
        
        $available = $this->tenantModel->isSubdomainAvailable($subdomain);
        
        header('Content-Type: application/json');
        echo json_encode(['available' => $available]);
    }
    
    /**
     * Enviar fatura por WhatsApp usando WuzAPI
     */
    private function sendInvoiceViaWhatsApp($tenant_id, $filial_id, $data, $invoice_url) {
        try {
            // Buscar instância WhatsApp do SuperAdmin (global)
            $db = \System\Database::getInstance();
            $instance = $db->fetch(
                "SELECT id, wuzapi_token, phone_number FROM whatsapp_instances WHERE is_superadmin = true AND status = 'ativo' LIMIT 1"
            );
            
            if (!$instance) {
                error_log("OnboardingController::sendInvoiceViaWhatsApp - Nenhuma instância WhatsApp do SuperAdmin encontrada");
                return;
            }
            
            // Formatar mensagem
            $plano = $this->planModel->getById($data['plano_id']);
            $valor = $data['valor_final'] ?? $plano['preco_mensal'];
            
            $message = "🎉 *Estabelecimento Criado com Sucesso!*\n\n";
            $message .= "📋 *Dados do Cadastro:*\n";
            $message .= "• Nome: {$data['nome']}\n";
            $message .= "• Subdomain: {$data['subdomain']}\n\n";
            $message .= "💰 *Pagamento:*\n";
            $message .= "• Plano: {$plano['nome']}\n";
            $message .= "• Valor: R$ " . number_format($valor, 2, ',', '.') . "\n";
            $message .= "• Período: " . ucfirst($data['periodicidade'] ?? 'mensal') . "\n\n";
            $message .= "🔗 *Acesse sua fatura para gerar o código PIX:*\n";
            $message .= "$invoice_url\n\n";
            $message .= "⚠️ *IMPORTANTE:*\n";
            $message .= "Esta é uma assinatura recorrente. As próximas cobranças serão geradas automaticamente.\n\n";
            $message .= "📧 Suas credenciais foram enviadas por email.";
            
            // Enviar via WuzAPI
            $wuzapi = new \System\WhatsApp\WuzAPIManager();
            $result = $wuzapi->sendMessage(
                $instance['id'],
                $data['telefone'],
                $message
            );
            
            if ($result['success']) {
                error_log("OnboardingController::sendInvoiceViaWhatsApp - Fatura enviada com sucesso via WhatsApp");
            } else {
                error_log("OnboardingController::sendInvoiceViaWhatsApp - Erro ao enviar: " . $result['message']);
            }
            
        } catch (Exception $e) {
            error_log("OnboardingController::sendInvoiceViaWhatsApp - Exception: " . $e->getMessage());
        }
    }
}

// Roteamento
if (basename($_SERVER['PHP_SELF']) == 'OnboardingController.php') {
    $controller = new OnboardingController();
    $action = $_GET['action'] ?? 'createEstablishment';
    
    if (method_exists($controller, $action)) {
        $controller->$action();
    } else {
        // Default: create establishment
        $controller->createEstablishment();
    }
}

