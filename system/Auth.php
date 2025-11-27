<?php

namespace System;

require_once __DIR__ . '/WhatsApp/WuzAPIManager.php';

class Auth
{
    private static $db;
    private static $currentUser = null;
    private static $currentSession = null;

    public static function init()
    {
        self::$db = Database::getInstance();
    }

    /**
     * Gerar token de autenticação
     */
    public static function generateToken($usuarioGlobalId, $tipo = 'login', $expiraEm = null)
    {
        if (!$expiraEm) {
            $expiraEm = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        }

        $token = bin2hex(random_bytes(32));
        
        self::$db->insert('tokens_autenticacao', [
            'usuario_global_id' => $usuarioGlobalId,
            'token' => $token,
            'tipo' => $tipo,
            'expira_em' => $expiraEm
        ]);

        return $token;
    }

    /**
     * Enviar mensagem LGPD via n8n
     */
    public static function sendLGPDMessage($usuario, $telefone, $tenantId, $filialId)
    {
        // Buscar instância Evolution ativa
        $instancia = self::$db->fetch(
            "SELECT * FROM evolution_instancias 
             WHERE tenant_id = ? AND filial_id = ? AND status = 'open' 
             ORDER BY created_at DESC LIMIT 1",
            [$tenantId, $filialId]
        );

        if (!$instancia) {
            return [
                'success' => false,
                'message' => 'Nenhuma instância Evolution ativa encontrada'
            ];
        }

        // Verificar se já tem consentimento
        $consentimento = self::checkLGPDConsent($usuario['id'], $tenantId, $filialId, 'pedidos');
        
        if ($consentimento && $consentimento['consentimento']) {
            return [
                'success' => true,
                'message' => 'Cliente já autorizou o compartilhamento de dados',
                'consentimento_existente' => true
            ];
        }

        // Gerar mensagem LGPD
        $mensagem = "Olá {$usuario['nome']}! 👋\n\n";
        $mensagem .= "Detectamos que você já é cliente em outro estabelecimento que usa nossa plataforma. ";
        $mensagem .= "Para facilitar seu pedido, podemos compartilhar seus dados entre estabelecimentos?\n\n";
        $mensagem .= "✅ Responda SIM para autorizar\n";
        $mensagem .= "❌ Responda NÃO para não compartilhar\n\n";
        $mensagem .= "Seus dados serão usados apenas para:\n";
        $mensagem .= "• Facilitar seus pedidos\n";
        $mensagem .= "• Manter seu histórico de compras\n";
        $mensagem .= "• Melhorar seu atendimento\n\n";
        $mensagem .= "Você pode revogar este consentimento a qualquer momento.";

        // Enviar via n8n
        $n8nData = [
            'nome' => $usuario['nome'],
            'telefone' => $telefone,
            'estancia' => $instancia['nome_instancia'],
            'mensagem' => $mensagem
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://whook.conext.click/webhook/divinosyslgpd');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($n8nData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Erro cURL n8n LGPD: " . $error);
            return [
                'success' => false,
                'message' => 'Erro na comunicação com n8n: ' . $error
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            // Log da mensagem enviada
            self::logAccess($usuario['id'], 'mensagem_lgpd_enviada', $tenantId, $filialId, [
                'telefone' => $telefone,
                'instancia' => $instancia['nome_instancia']
            ]);

            return [
                'success' => true,
                'message' => 'Mensagem LGPD enviada com sucesso',
                'response' => $response
            ];
        }

        return [
            'success' => false,
            'message' => 'Erro HTTP: ' . $httpCode,
            'response' => $response
        ];
    }

    /**
     * Validar token de autenticação
     */
    public static function validateToken($token)
    {
        $tokenData = self::$db->fetch(
            "SELECT t.*, ug.* FROM tokens_autenticacao t 
             JOIN usuarios_globais ug ON t.usuario_global_id = ug.id 
             WHERE t.token = ? AND t.usado = false AND t.expira_em > NOW()",
            [$token]
        );

        if (!$tokenData) {
            return false;
        }

        // Marcar token como usado
        self::$db->update('tokens_autenticacao', ['usado' => true], 'token = ?', [$token]);

        return $tokenData;
    }

    /**
     * Criar sessão ativa
     */
    public static function createSession($usuarioGlobalId, $tenantId, $filialId = null)
    {
        // Primeiro, limpar sessões antigas do mesmo usuário
        self::$db->delete('sessoes_ativas', 'usuario_global_id = ? AND tenant_id = ? AND filial_id = ?', 
            [$usuarioGlobalId, $tenantId, $filialId]);

        $tokenSessao = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', strtotime('+8 hours'));

        self::$db->insert('sessoes_ativas', [
            'usuario_global_id' => $usuarioGlobalId,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'token_sessao' => $tokenSessao,
            'expira_em' => $expiraEm
        ]);

        // Armazenar na sessão PHP
        $_SESSION['auth_token'] = $tokenSessao;
        $_SESSION['usuario_global_id'] = $usuarioGlobalId;
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['filial_id'] = $filialId;

        return $tokenSessao;
    }

    /**
     * Validar sessão ativa
     */
    public static function validateSession($tokenSessao = null)
    {
        if (!$tokenSessao) {
            $tokenSessao = $_SESSION['auth_token'] ?? null;
        }

        if (!$tokenSessao) {
            return false;
        }

        $sessionData = self::$db->fetch(
            "SELECT s.*, ug.* FROM sessoes_ativas s 
             JOIN usuarios_globais ug ON s.usuario_global_id = ug.id 
             WHERE s.token_sessao = ? AND s.expira_em > NOW()",
            [$tokenSessao]
        );

        if (!$sessionData) {
            return false;
        }

        self::$currentSession = $sessionData;
        return $sessionData;
    }

    /**
     * Buscar usuário por telefone
     */
    public static function findUserByPhone($telefone)
    {
        return self::$db->fetch(
            "SELECT * FROM usuarios_globais 
             WHERE telefone = ? AND ativo = true",
            [$telefone]
        );
    }

    /**
     * Gerar e enviar código de acesso via WhatsApp
     */
    public static function generateAndSendAccessCode($telefone, $tenantId, $filialId = null)
    {
        try {
            // Buscar ou criar usuário
            $usuario = self::findUserByPhone($telefone);
            if (!$usuario) {
                // Criar novo usuário na tabela usuarios_globais
                $usuarioId = self::$db->insert('usuarios_globais', [
                    'nome' => 'Usuário ' . $telefone,
                    'telefone' => $telefone,
                    'tipo_usuario' => 'cliente',
                    'ativo' => true
                ]);
                
                $usuario = self::findUserByPhone($telefone);
            }

            // Verificar se usuário tem acesso ao estabelecimento
            $userEstablishment = self::$db->fetch(
                "SELECT * FROM usuarios_estabelecimento 
                 WHERE usuario_global_id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
                [$usuario['id'], $tenantId, $filialId]
            );

            if (!$userEstablishment) {
                // Criar associação com estabelecimento como cliente
                self::$db->insert('usuarios_estabelecimento', [
                    'usuario_global_id' => $usuario['id'],
                    'tenant_id' => $tenantId,
                    'filial_id' => $filialId,
                    'tipo_usuario' => 'cliente',
                    'ativo' => true
                ]);
            }

            // Gerar código de acesso (6 dígitos)
            $codigo = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            
            // Definir expiração (5 minutos)
            $expiraEm = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            // Salvar código no banco
            self::$db->insert('codigos_acesso', [
                'usuario_global_id' => $usuario['id'],
                'telefone' => $telefone,
                'codigo' => $codigo,
                'expira_em' => $expiraEm,
                'tenant_id' => $tenantId,
                'filial_id' => $filialId
            ]);

            // Enviar código via WhatsApp
            $sendResult = self::sendAccessCodeViaWhatsApp($telefone, $codigo, $tenantId, $filialId);
            
            if (!$sendResult['success']) {
                return $sendResult;
            }

            return [
                'success' => true,
                'message' => 'Código de acesso enviado para seu WhatsApp',
                'usuario_id' => $usuario['id'],
                'expires_in' => 300 // 5 minutos em segundos
            ];

        } catch (\Exception $e) {
            error_log("Auth::generateAndSendAccessCode - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao gerar código de acesso: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Enviar código de acesso via WhatsApp usando WuzAPI
     */
    public static function sendAccessCodeViaWhatsApp($telefone, $codigo, $tenantId, $filialId)
    {
        try {
            error_log("Auth::sendAccessCodeViaWhatsApp - Buscando instância - Tenant: $tenantId, Filial: " . ($filialId ?? 'NULL'));
            
            // Primeiro, tentar buscar instância específica da filial (com status ativo)
            $instancia = null;
            if ($filialId !== null) {
                $instancia = self::$db->fetch(
                    "SELECT * FROM whatsapp_instances 
                     WHERE tenant_id = ? AND filial_id = ? AND ativo = true 
                     AND status IN ('open', 'connected', 'ativo', 'active') 
                     ORDER BY created_at DESC LIMIT 1",
                    [$tenantId, $filialId]
                );
                error_log("Auth::sendAccessCodeViaWhatsApp - Busca com filial específica (status ativo): " . ($instancia ? "Encontrada (ID: {$instancia['id']}, Status: {$instancia['status']})" : "Não encontrada"));
                
                // Se não encontrou com status ativo, tentar qualquer instância da filial (ativo = true)
                if (!$instancia) {
                    $instancia = self::$db->fetch(
                        "SELECT * FROM whatsapp_instances 
                         WHERE tenant_id = ? AND filial_id = ? AND ativo = true 
                         ORDER BY created_at DESC LIMIT 1",
                        [$tenantId, $filialId]
                    );
                    error_log("Auth::sendAccessCodeViaWhatsApp - Busca com filial específica (qualquer status): " . ($instancia ? "Encontrada (ID: {$instancia['id']}, Status: {$instancia['status']})" : "Não encontrada"));
                }
            }
            
            // Se não encontrou com filial específica, tentar sem filial (instância global do tenant)
            if (!$instancia) {
                $instancia = self::$db->fetch(
                    "SELECT * FROM whatsapp_instances 
                     WHERE tenant_id = ? AND (filial_id IS NULL OR filial_id = 0) AND ativo = true 
                     AND status IN ('open', 'connected', 'ativo', 'active') 
                     ORDER BY created_at DESC LIMIT 1",
                    [$tenantId]
                );
                error_log("Auth::sendAccessCodeViaWhatsApp - Busca sem filial específica (status ativo): " . ($instancia ? "Encontrada (ID: {$instancia['id']}, Status: {$instancia['status']})" : "Não encontrada"));
                
                // Se não encontrou com status ativo, tentar qualquer instância global
                if (!$instancia) {
                    $instancia = self::$db->fetch(
                        "SELECT * FROM whatsapp_instances 
                         WHERE tenant_id = ? AND (filial_id IS NULL OR filial_id = 0) AND ativo = true 
                         ORDER BY created_at DESC LIMIT 1",
                        [$tenantId]
                    );
                    error_log("Auth::sendAccessCodeViaWhatsApp - Busca sem filial específica (qualquer status): " . ($instancia ? "Encontrada (ID: {$instancia['id']}, Status: {$instancia['status']})" : "Não encontrada"));
                }
            }
            
            // Se ainda não encontrou, tentar qualquer instância ativa do tenant (qualquer status)
            // IMPORTANTE: Se ativo=true, usar mesmo que status não esteja como "connected"
            if (!$instancia) {
                $instancia = self::$db->fetch(
                    "SELECT * FROM whatsapp_instances 
                     WHERE tenant_id = ? AND ativo = true 
                     ORDER BY 
                        CASE WHEN status IN ('open', 'connected', 'ativo', 'active') THEN 1 ELSE 2 END,
                        created_at DESC 
                     LIMIT 1",
                    [$tenantId]
                );
                error_log("Auth::sendAccessCodeViaWhatsApp - Busca qualquer instância do tenant: " . ($instancia ? "Encontrada (ID: {$instancia['id']}, Status: {$instancia['status']})" : "Não encontrada"));
            }

            if (!$instancia) {
                error_log("Auth::sendAccessCodeViaWhatsApp - Nenhuma instância encontrada para Tenant: $tenantId, Filial: " . ($filialId ?? 'NULL'));
                
                // Log de debug: listar todas as instâncias do tenant
                $todasInstancias = self::$db->fetchAll(
                    "SELECT id, tenant_id, filial_id, instance_name, status, ativo 
                     FROM whatsapp_instances 
                     WHERE tenant_id = ?",
                    [$tenantId]
                );
                error_log("Auth::sendAccessCodeViaWhatsApp - Todas instâncias do tenant: " . json_encode($todasInstancias));
                
                return [
                    'success' => false,
                    'message' => 'Nenhuma instância WhatsApp ativa encontrada'
                ];
            }
            
            error_log("Auth::sendAccessCodeViaWhatsApp - Instância selecionada: ID={$instancia['id']}, Nome={$instancia['instance_name']}, Status={$instancia['status']}");
            
            // Verificar e sincronizar status da instância se necessário
            // Se o status não estiver em um dos status ativos, tentar sincronizar com WuzAPI
            if (!in_array($instancia['status'], ['open', 'connected', 'ativo', 'active'])) {
                error_log("Auth::sendAccessCodeViaWhatsApp - Status não está ativo, tentando sincronizar com WuzAPI");
                try {
                    $wuzapiManager = new \System\WhatsApp\WuzAPIManager();
                    $statusSync = $wuzapiManager->syncInstanceStatus($instancia['id']);
                    if ($statusSync['success']) {
                        // Buscar instância novamente com status atualizado
                        $instancia = self::$db->fetch(
                            "SELECT * FROM whatsapp_instances WHERE id = ?",
                            [$instancia['id']]
                        );
                        error_log("Auth::sendAccessCodeViaWhatsApp - Status sincronizado: {$instancia['status']}");
                    }
                } catch (\Exception $e) {
                    error_log("Auth::sendAccessCodeViaWhatsApp - Erro ao sincronizar status: " . $e->getMessage());
                    // Continuar mesmo se a sincronização falhar
                }
            }

            // Formatar telefone (remover caracteres especiais)
            // O telefone já vem com código do país do frontend (ex: 5511999999999)
            $telefoneFormatado = preg_replace('/[^0-9]/', '', $telefone);
            
            // Se o telefone não começar com código de país (não começa com + ou código), adicionar código do Brasil
            if (strlen($telefoneFormatado) <= 11 && !preg_match('/^[1-9][0-9]{1,2}/', $telefoneFormatado)) {
                // Telefone brasileiro sem código do país
                $telefoneFormatado = '55' . $telefoneFormatado;
            }
            
            error_log("Auth::sendAccessCodeViaWhatsApp - Telefone formatado: $telefoneFormatado");

            // Criar mensagem
            $mensagem = "🔐 *Divino Lanches - Código de Acesso*\n\n";
            $mensagem .= "Seu código de acesso é: *{$codigo}*\n\n";
            $mensagem .= "⏰ Este código expira em 5 minutos.\n";
            $mensagem .= "🚫 Não compartilhe este código com ninguém.\n\n";
            $mensagem .= "Se você não solicitou este código, ignore esta mensagem.";

            // Usar WuzAPIManager para enviar mensagem
            $wuzapiManager = new \System\WhatsApp\WuzAPIManager();
            $result = $wuzapiManager->sendMessage($instancia['id'], $telefoneFormatado, $mensagem);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Código enviado com sucesso',
                    'message_id' => $result['message_id'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Erro ao enviar código: ' . $result['message']
                ];
            }

        } catch (\Exception $e) {
            error_log("Auth::sendAccessCodeViaWhatsApp - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar código via WhatsApp: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validar código de acesso
     */
    public static function validateAccessCode($telefone, $codigo, $tenantId, $filialId = null, $accessType = 'usuario', $tipoUsuarioEspecifico = null)
    {
        try {
            error_log("Auth::validateAccessCode - Telefone: $telefone, Tenant: $tenantId, Filial: " . ($filialId ?? 'NULL') . ", AccessType: $accessType");
            
            // Buscar código válido (não depender do tenant_id do código, pois pode ter sido gerado com outro)
            // O código é válido se o telefone e código batem, independente do tenant
            $codigoData = self::$db->fetch(
                "SELECT ca.*, ug.* FROM codigos_acesso ca
                 JOIN usuarios_globais ug ON ca.usuario_global_id = ug.id
                 WHERE ca.telefone = ? AND ca.codigo = ? 
                 AND ca.usado = false AND ca.expira_em > NOW()
                 ORDER BY ca.created_at DESC LIMIT 1",
                [$telefone, $codigo]
            );

            if (!$codigoData) {
                error_log("Auth::validateAccessCode - Código não encontrado ou inválido para telefone: $telefone");
                return [
                    'success' => false,
                    'message' => 'Código inválido ou expirado'
                ];
            }
            
            error_log("Auth::validateAccessCode - Código válido encontrado. UsuarioID: {$codigoData['usuario_global_id']}, Tenant do código: {$codigoData['tenant_id']}, Tenant escolhido: $tenantId");

            // Marcar código como usado
            self::$db->update(
                'codigos_acesso',
                ['usado' => true],
                'id = ?',
                [$codigoData['id']]
            );

            // Se acesso como cliente, criar sessão como cliente
            if ($accessType === 'cliente') {
                error_log("Auth::validateAccessCode - Acesso como CLIENTE");
                
                // Verificar se já tem vínculo como cliente, se não, criar
                $userEstablishment = self::$db->fetch(
                    "SELECT * FROM usuarios_estabelecimento 
                     WHERE usuario_global_id = ? AND tenant_id = ? AND tipo_usuario = 'cliente' AND ativo = true
                     LIMIT 1",
                    [$codigoData['usuario_global_id'], $tenantId]
                );
                
                if (!$userEstablishment) {
                    // Criar vínculo como cliente
                    self::$db->insert('usuarios_estabelecimento', [
                        'usuario_global_id' => $codigoData['usuario_global_id'],
                        'tenant_id' => $tenantId,
                        'filial_id' => $filialId,
                        'tipo_usuario' => 'cliente',
                        'ativo' => true
                    ]);
                    
                    $userEstablishment = self::$db->fetch(
                        "SELECT * FROM usuarios_estabelecimento 
                         WHERE usuario_global_id = ? AND tenant_id = ? AND tipo_usuario = 'cliente' AND ativo = true
                         LIMIT 1",
                        [$codigoData['usuario_global_id'], $tenantId]
                    );
                }
                
                // Criar sessão
                $sessionToken = self::createSession($codigoData['usuario_global_id'], $tenantId, $filialId);
                
                return [
                    'success' => true,
                    'message' => 'Login realizado com sucesso',
                    'user' => $codigoData,
                    'establishment' => $userEstablishment,
                    'session_token' => $sessionToken,
                    'permissions' => self::getUserPermissions('cliente')
                ];
            }

            // Acesso como usuário do estabelecimento
            error_log("Auth::validateAccessCode - Acesso como USUÁRIO - Tipo específico: " . ($tipoUsuarioEspecifico ?? 'N/A'));
            error_log("Auth::validateAccessCode - Buscando estabelecimento - UsuarioID: {$codigoData['usuario_global_id']}, Tenant: $tenantId, Filial: " . ($filialId ?? 'NULL'));
            
            // IMPORTANTE: Usar o tenant/filial escolhido pelo usuário, não o do código
            // O código pode ter sido gerado com um tenant diferente
            
            // Buscar dados do estabelecimento do usuário
            // Se tipo_usuario foi especificado, usar ele na busca
            if ($tipoUsuarioEspecifico && $tipoUsuarioEspecifico !== 'cliente') {
                error_log("Auth::validateAccessCode - Buscando com tipo_usuario específico: $tipoUsuarioEspecifico");
                if ($filialId !== null) {
                    $userEstablishment = self::$db->fetch(
                        "SELECT * FROM usuarios_estabelecimento 
                         WHERE usuario_global_id = ? AND tenant_id = ? AND filial_id = ? AND tipo_usuario = ? AND ativo = true
                         LIMIT 1",
                        [$codigoData['usuario_global_id'], $tenantId, $filialId, $tipoUsuarioEspecifico]
                    );
                } else {
                    $userEstablishment = self::$db->fetch(
                        "SELECT * FROM usuarios_estabelecimento 
                         WHERE usuario_global_id = ? AND tenant_id = ? AND tipo_usuario = ? AND ativo = true
                         ORDER BY filial_id ASC LIMIT 1",
                        [$codigoData['usuario_global_id'], $tenantId, $tipoUsuarioEspecifico]
                    );
                }
                
                if ($userEstablishment) {
                    error_log("Auth::validateAccessCode - Estabelecimento encontrado com tipo específico: " . $userEstablishment['tipo_usuario']);
                } else {
                    error_log("Auth::validateAccessCode - Nenhum estabelecimento encontrado com tipo específico, tentando sem tipo");
                }
            }
            
            // Se não encontrou com tipo específico, buscar sem filtro de tipo (exceto cliente)
            if (!isset($userEstablishment) || !$userEstablishment) {
                error_log("Auth::validateAccessCode - Buscando estabelecimento sem tipo específico (exceto cliente)");
                // Buscar dados do estabelecimento do usuário (filtrar por filial se fornecida)
                if ($filialId !== null) {
                    $userEstablishment = self::$db->fetch(
                        "SELECT * FROM usuarios_estabelecimento 
                         WHERE usuario_global_id = ? AND tenant_id = ? AND filial_id = ? AND tipo_usuario != 'cliente' AND ativo = true
                         LIMIT 1",
                        [$codigoData['usuario_global_id'], $tenantId, $filialId]
                    );
                } else {
                    // Se não tem filial específica, buscar qualquer vínculo do tenant (exceto cliente)
                    $userEstablishment = self::$db->fetch(
                        "SELECT * FROM usuarios_estabelecimento 
                         WHERE usuario_global_id = ? AND tenant_id = ? AND tipo_usuario != 'cliente' AND ativo = true
                         ORDER BY filial_id ASC LIMIT 1",
                        [$codigoData['usuario_global_id'], $tenantId]
                    );
                }
            }
            
            // Se ainda não encontrou, listar todos os estabelecimentos do usuário para debug
            if (!$userEstablishment) {
                $todosEstabelecimentos = self::$db->fetchAll(
                    "SELECT * FROM usuarios_estabelecimento 
                     WHERE usuario_global_id = ? AND ativo = true",
                    [$codigoData['usuario_global_id']]
                );
                error_log("Auth::validateAccessCode - Todos estabelecimentos do usuário: " . json_encode($todosEstabelecimentos));
                error_log("Auth::validateAccessCode - Buscando: Tenant=$tenantId, Filial=" . ($filialId ?? 'NULL') . ", Tipo=" . ($tipoUsuarioEspecifico ?? 'N/A'));
            }

            if (!$userEstablishment) {
                error_log("Auth::validateAccessCode - Estabelecimento não encontrado para usuário");
                error_log("Auth::validateAccessCode - Parâmetros da busca: UsuarioID={$codigoData['usuario_global_id']}, Tenant=$tenantId, Filial=" . ($filialId ?? 'NULL') . ", Tipo=" . ($tipoUsuarioEspecifico ?? 'N/A'));
                
                // Tentar buscar qualquer estabelecimento ativo do usuário neste tenant (última tentativa)
                $userEstablishment = self::$db->fetch(
                    "SELECT * FROM usuarios_estabelecimento 
                     WHERE usuario_global_id = ? AND tenant_id = ? AND ativo = true
                     ORDER BY tipo_usuario != 'cliente' DESC, filial_id ASC LIMIT 1",
                    [$codigoData['usuario_global_id'], $tenantId]
                );
                
                if ($userEstablishment) {
                    error_log("Auth::validateAccessCode - Estabelecimento encontrado na busca alternativa: " . $userEstablishment['tipo_usuario']);
                } else {
                    // Listar todos os estabelecimentos do usuário para debug
                    $todosEstabelecimentos = self::$db->fetchAll(
                        "SELECT * FROM usuarios_estabelecimento 
                         WHERE usuario_global_id = ? AND ativo = true",
                        [$codigoData['usuario_global_id']]
                    );
                    error_log("Auth::validateAccessCode - Nenhum estabelecimento encontrado. Todos estabelecimentos do usuário: " . json_encode($todosEstabelecimentos));
                    
                    return [
                        'success' => false,
                        'message' => 'Usuário não tem acesso a este estabelecimento. Verifique se você selecionou o estabelecimento correto.'
                    ];
                }
            }

            error_log("Auth::validateAccessCode - Estabelecimento encontrado: Tipo=" . $userEstablishment['tipo_usuario'] . ", Tenant=" . $tenantId . ", Filial=" . ($filialId ?? 'NULL'));

            // Criar sessão
            $sessionToken = self::createSession($codigoData['usuario_global_id'], $tenantId, $filialId);

            return [
                'success' => true,
                'message' => 'Login realizado com sucesso',
                'user' => $codigoData,
                'establishment' => $userEstablishment,
                'session_token' => $sessionToken,
                'permissions' => self::getUserPermissions($userEstablishment['tipo_usuario']),
                'tipo_usuario' => $userEstablishment['tipo_usuario'] // Garantir que tipo_usuario está na resposta
            ];

        } catch (\Exception $e) {
            error_log("Auth::validateAccessCode - Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao validar código: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Buscar todos os telefones de um usuário
     */
    public static function getUserPhones($usuarioGlobalId)
    {
        return self::$db->fetchAll(
            "SELECT * FROM usuarios_telefones 
             WHERE usuario_global_id = ? AND ativo = true 
             ORDER BY tipo = 'principal' DESC, created_at ASC",
            [$usuarioGlobalId]
        );
    }

    /**
     * Buscar todos os endereços de um usuário
     */
    public static function getUserAddresses($usuarioGlobalId)
    {
        return self::$db->fetchAll(
            "SELECT * FROM usuarios_enderecos 
             WHERE usuario_global_id = ? AND ativo = true 
             ORDER BY padrao DESC, created_at ASC",
            [$usuarioGlobalId]
        );
    }

    /**
     * Adicionar telefone a um usuário
     */
    public static function addUserPhone($usuarioGlobalId, $telefone, $tipo = 'secundario')
    {
        return self::$db->insert('usuarios_telefones', [
            'usuario_global_id' => $usuarioGlobalId,
            'telefone' => $telefone,
            'tipo' => $tipo,
            'ativo' => true,
            'verificado' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Adicionar endereço a um usuário
     */
    public static function addUserAddress($usuarioGlobalId, $endereco, $latitude = null, $longitude = null, $pontoReferencia = null, $tipo = 'residencial')
    {
        return self::$db->insert('usuarios_enderecos', [
            'usuario_global_id' => $usuarioGlobalId,
            'endereco_completo' => $endereco,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'ponto_referencia' => $pontoReferencia,
            'tipo' => $tipo,
            'ativo' => true,
            'padrao' => false,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Criar novo usuário
     */
    public static function createUser($dados)
    {
        $dados['data_cadastro'] = date('Y-m-d H:i:s');
        $dados['created_at'] = date('Y-m-d H:i:s');
        $dados['updated_at'] = date('Y-m-d H:i:s');

        return self::$db->insert('usuarios_globais', $dados);
    }

    /**
     * Vincular usuário a estabelecimento
     */
    public static function linkUserToEstablishment($usuarioGlobalId, $tenantId, $filialId, $tipoUsuario, $cargo = null, $permissoes = null)
    {
        $dados = [
            'usuario_global_id' => $usuarioGlobalId,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'tipo_usuario' => $tipoUsuario,
            'cargo' => $cargo,
            'permissoes' => $permissoes ? json_encode($permissoes) : null,
            'data_vinculacao' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return self::$db->insert('usuarios_estabelecimento', $dados);
    }

    /**
     * Buscar usuários de um estabelecimento
     */
    public static function getUsersByEstablishment($tenantId, $filialId = null)
    {
        $sql = "SELECT ue.*, ug.* FROM usuarios_estabelecimento ue 
                JOIN usuarios_globais ug ON ue.usuario_global_id = ug.id 
                WHERE ue.tenant_id = ? AND ue.ativo = true";
        
        $params = [$tenantId];
        
        if ($filialId) {
            $sql .= " AND ue.filial_id = ?";
            $params[] = $filialId;
        }

        return self::$db->fetchAll($sql, $params);
    }

    /**
     * Verificar permissões do usuário
     */
    public static function hasPermission($permission, $tenantId = null, $filialId = null)
    {
        if (!self::$currentSession) {
            return false;
        }

        $tenantId = $tenantId ?: self::$currentSession['tenant_id'];
        $filialId = $filialId ?: self::$currentSession['filial_id'];

        $userEstablishment = self::$db->fetch(
            "SELECT * FROM usuarios_estabelecimento 
             WHERE usuario_global_id = ? AND tenant_id = ? AND filial_id = ? AND ativo = true",
            [self::$currentSession['usuario_global_id'], $tenantId, $filialId]
        );

        if (!$userEstablishment) {
            return false;
        }

        // Verificar permissões baseadas no tipo de usuário
        $permissions = self::getUserPermissions($userEstablishment['tipo_usuario']);
        
        return in_array($permission, $permissions);
    }

    /**
     * Obter permissões por tipo de usuário
     */
    public static function getUserPermissions($tipoUsuario)
    {
        $permissions = [
            'admin' => [
                'dashboard', 'pedidos', 'delivery', 'produtos', 'estoque', 
                'financeiro', 'relatorios', 'clientes', 'configuracoes', 'usuarios',
                'novo_pedido', 'relatorios_avancados', 'asaas_config', 'gerenciar_faturas', 'logout'
            ],
            'cozinha' => [
                'dashboard', 'pedidos', 'estoque', 'produtos', 'gerenciar_produtos', 'novo_pedido', 'logout'
            ],
            'garcom' => [
                'dashboard', 'novo_pedido', 'pedidos', 'delivery', 'estoque', 'produtos', 'gerenciar_produtos', 'logout'
            ],
            'entregador' => [
                'delivery', 'pedidos', 'novo_pedido', 'logout'
            ],
            'caixa' => [
                'dashboard', 'novo_pedido', 'delivery', 'produtos', 'estoque', 
                'pedidos', 'financeiro', 'logout'
            ],
            'cliente' => [
                'cliente_dashboard', 'historico_pedidos', 'perfil', 'novo_pedido', 'gerar_pedido', 'pedidos', 'logout'
            ]
        ];

        return $permissions[$tipoUsuario] ?? [];
    }

    /**
     * Fazer logout
     */
    public static function logout()
    {
        // Inicializar banco de dados se necessário
        if (!self::$db) {
            self::$db = Database::getInstance();
        }
        
        if (isset($_SESSION['auth_token'])) {
            // Marcar sessão como expirada
            self::$db->update(
                'sessoes_ativas', 
                ['expira_em' => date('Y-m-d H:i:s')], 
                'token_sessao = ?', 
                [$_SESSION['auth_token']]
            );
        }

        // Limpar sessão PHP
        session_destroy();
        self::$currentUser = null;
        self::$currentSession = null;
    }

    /**
     * Obter usuário atual
     */
    public static function getCurrentUser()
    {
        if (!self::$currentSession) {
            self::validateSession();
        }

        return self::$currentSession;
    }

    /**
     * Verificar se usuário está logado
     */
    public static function isLoggedIn()
    {
        return self::validateSession() !== false;
    }

    /**
     * Limpar tokens expirados
     */
    public static function cleanExpiredTokens()
    {
        self::$db->delete('tokens_autenticacao', 'expira_em < NOW()');
        self::$db->delete('sessoes_ativas', 'expira_em < NOW()');
    }

    /**
     * Verificar consentimento LGPD
     */
    public static function checkLGPDConsent($usuarioGlobalId, $tenantId, $filialId, $finalidade)
    {
        return self::$db->fetch(
            "SELECT * FROM usuarios_consentimentos_lgpd 
             WHERE usuario_global_id = ? AND tenant_id = ? AND filial_id = ? AND finalidade = ?",
            [$usuarioGlobalId, $tenantId, $filialId, $finalidade]
        );
    }

    /**
     * Registrar consentimento LGPD
     */
    public static function registerLGPDConsent($usuarioGlobalId, $tenantId, $filialId, $finalidade, $consentimento, $ip = null, $userAgent = null)
    {
        $dados = [
            'usuario_global_id' => $usuarioGlobalId,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'finalidade' => $finalidade,
            'consentimento' => $consentimento,
            'data_consentimento' => date('Y-m-d H:i:s'),
            'ip_consentimento' => $ip ?: $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $userAgent ?: $_SERVER['HTTP_USER_AGENT'] ?? null
        ];

        return self::$db->insert('usuarios_consentimentos_lgpd', $dados);
    }

    /**
     * Registrar log de acesso
     */
    public static function logAccess($usuarioGlobalId, $acao, $tenantId = null, $filialId = null, $dadosAlterados = null)
    {
        $dados = [
            'usuario_global_id' => $usuarioGlobalId,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'acao' => $acao,
            'dados_alterados' => $dadosAlterados ? json_encode($dadosAlterados) : null,
            'ip_acesso' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'data_acesso' => date('Y-m-d H:i:s')
        ];

        return self::$db->insert('usuarios_logs_acesso', $dados);
    }

    /**
     * Buscar usuário com dados completos
     */
    public static function getUserCompleteData($usuarioGlobalId)
    {
        $usuario = self::$db->fetch(
            "SELECT * FROM usuarios_globais WHERE id = ?",
            [$usuarioGlobalId]
        );

        if ($usuario) {
            $usuario['telefones'] = self::getUserPhones($usuarioGlobalId);
            $usuario['enderecos'] = self::getUserAddresses($usuarioGlobalId);
        }

        return $usuario;
    }

    /**
     * Verificar se telefone já está em uso por outro usuário
     */
    public static function isPhoneInUse($telefone, $excludeUserId = null)
    {
        $sql = "SELECT ut.*, ug.nome FROM usuarios_telefones ut 
                JOIN usuarios_globais ug ON ut.usuario_global_id = ug.id 
                WHERE ut.telefone = ? AND ut.ativo = true";
        
        $params = [$telefone];
        
        if ($excludeUserId) {
            $sql .= " AND ut.usuario_global_id != ?";
            $params[] = $excludeUserId;
        }

        return self::$db->fetch($sql, $params);
    }

    /**
     * Transferir telefone de um usuário para outro
     */
    public static function transferPhone($telefone, $fromUserId, $toUserId, $motivo = 'transferencia')
    {
        // Registrar no histórico
        self::$db->insert('usuarios_telefones_historico', [
            'usuario_global_id' => $fromUserId,
            'telefone_anterior' => $telefone,
            'telefone_novo' => $telefone,
            'motivo' => $motivo,
            'data_alteracao' => date('Y-m-d H:i:s'),
            'observacoes' => "Transferido para usuário ID: $toUserId"
        ]);

        // Atualizar o telefone
        self::$db->update(
            'usuarios_telefones',
            ['usuario_global_id' => $toUserId, 'updated_at' => date('Y-m-d H:i:s')],
            'telefone = ? AND usuario_global_id = ?',
            [$telefone, $fromUserId]
        );

        return true;
    }
}
