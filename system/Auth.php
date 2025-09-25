<?php

namespace System;

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
            "SELECT ug.* FROM usuarios_globais ug 
             JOIN usuarios_telefones ut ON ug.id = ut.usuario_global_id 
             WHERE ut.telefone = ? AND ug.ativo = true AND ut.ativo = true",
            [$telefone]
        );
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
                'financeiro', 'relatorios', 'clientes', 'configuracoes', 'usuarios'
            ],
            'cozinha' => [
                'pedidos', 'estoque'
            ],
            'garcom' => [
                'novo_pedido', 'pedidos', 'delivery'
            ],
            'entregador' => [
                'delivery'
            ],
            'caixa' => [
                'dashboard', 'novo_pedido', 'delivery', 'produtos', 'estoque'
            ],
            'cliente' => [
                'historico_pedidos'
            ]
        ];

        return $permissions[$tipoUsuario] ?? [];
    }

    /**
     * Fazer logout
     */
    public static function logout()
    {
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
