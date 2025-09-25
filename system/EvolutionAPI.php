<?php

namespace System;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Config.php';

class EvolutionAPI
{
    private static $db;
    private static $config;

    public static function init()
    {
        self::$db = Database::getInstance();
        self::$config = require_once __DIR__ . '/../config/evolution.php';
    }

    /**
     * Criar nova instância Evolution
     */
    public static function createInstance($tenantId, $filialId, $nomeInstancia, $numeroTelefone)
    {
        $data = [
            'instanceName' => $nomeInstancia,
            'qrcode' => true,
            'number' => $numeroTelefone,
            'webhook' => self::getWebhookUrl($tenantId, $filialId)
        ];

        $response = self::makeRequest('POST', '/instance/create', $data);

        if ($response && isset($response['instance'])) {
            // Salvar no banco
            $instanceId = self::$db->insert('evolution_instancias', [
                'tenant_id' => $tenantId,
                'filial_id' => $filialId,
                'nome_instancia' => $nomeInstancia,
                'numero_telefone' => $numeroTelefone,
                'status' => 'criada',
                'webhook_url' => $data['webhook'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return [
                'success' => true,
                'instance_id' => $instanceId,
                'qr_code' => $response['instance']['qrcode'] ?? null,
                'instance_name' => $nomeInstancia
            ];
        }

        return [
            'success' => false,
            'message' => 'Erro ao criar instância'
        ];
    }

    /**
     * Obter QR Code da instância
     */
    public static function getQRCode($instanceName)
    {
        $response = self::makeRequest('GET', "/instance/connect/{$instanceName}");

        if ($response && isset($response['qrcode'])) {
            // Atualizar QR Code no banco
            self::$db->update(
                'evolution_instancias',
                ['qr_code' => $response['qrcode'], 'updated_at' => date('Y-m-d H:i:s')],
                'nome_instancia = ?',
                [$instanceName]
            );

            return [
                'success' => true,
                'qr_code' => $response['qrcode']
            ];
        }

        return [
            'success' => false,
            'message' => 'Erro ao obter QR Code'
        ];
    }

    /**
     * Verificar status da instância
     */
    public static function getInstanceStatus($instanceName)
    {
        $response = self::makeRequest('GET', "/instance/connectionState/{$instanceName}");

        if ($response) {
            $status = $response['instance']['state'] ?? 'desconectado';
            
            // Atualizar status no banco
            self::$db->update(
                'evolution_instancias',
                ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')],
                'nome_instancia = ?',
                [$instanceName]
            );

            return [
                'success' => true,
                'status' => $status
            ];
        }

        return [
            'success' => false,
            'message' => 'Erro ao verificar status'
        ];
    }

    /**
     * Enviar mensagem via Evolution
     */
    public static function sendMessage($instanceName, $number, $message)
    {
        $data = [
            'number' => $number,
            'text' => $message
        ];

        $response = self::makeRequest('POST', "/message/sendText/{$instanceName}", $data);

        return [
            'success' => $response !== false,
            'response' => $response
        ];
    }

    /**
     * Enviar mensagem LGPD via n8n
     */
    public static function sendLGPDMessage($nome, $telefone, $estancia, $mensagem)
    {
        $webhookUrl = self::$config['n8n_webhook_url'];
        
        $data = [
            'nome' => $nome,
            'telefone' => $telefone,
            'estancia' => $estancia,
            'mensagem' => $mensagem
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
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
            error_log("Erro cURL n8n: " . $error);
            return [
                'success' => false,
                'message' => 'Erro na comunicação com n8n: ' . $error
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'Mensagem enviada com sucesso',
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
     * Obter instâncias de um estabelecimento
     */
    public static function getInstances($tenantId, $filialId = null)
    {
        // Verificar se a tabela evolution_instancias existe
        $tableExists = self::$db->fetch("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'evolution_instancias')");
        
        if ($tableExists && $tableExists['exists']) {
            $sql = "SELECT * FROM evolution_instancias WHERE tenant_id = ?";
            $params = [$tenantId];

            if ($filialId) {
                $sql .= " AND filial_id = ?";
                $params[] = $filialId;
            }

            $sql .= " ORDER BY created_at DESC";

            return self::$db->fetchAll($sql, $params);
        } else {
            // Retornar array vazio se tabela não existir
            return [];
        }
    }

    /**
     * Deletar instância
     */
    public static function deleteInstance($instanceName)
    {
        $response = self::makeRequest('DELETE', "/instance/delete/{$instanceName}");

        if ($response) {
            // Remover do banco
            self::$db->delete('evolution_instancias', 'nome_instancia = ?', [$instanceName]);
            
            return [
                'success' => true,
                'message' => 'Instância deletada com sucesso'
            ];
        }

        return [
            'success' => false,
            'message' => 'Erro ao deletar instância'
        ];
    }

    /**
     * Fazer requisição para Evolution API
     */
    private static function makeRequest($method, $endpoint, $data = null)
    {
        $url = self::$config['base_url'] . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'apikey: ' . self::$config['api_key']
        ]);

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Erro cURL Evolution: " . $error);
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }

        error_log("Erro HTTP Evolution: " . $httpCode . " - " . $response);
        return false;
    }

    /**
     * Gerar URL do webhook
     */
    private static function getWebhookUrl($tenantId, $filialId)
    {
        $baseUrl = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        
        return $protocol . '://' . $baseUrl . '/webhook/evolution.php?tenant_id=' . $tenantId . '&filial_id=' . $filialId;
    }

    /**
     * Processar webhook do Evolution
     */
    public static function processWebhook($data)
    {
        $tenantId = $_GET['tenant_id'] ?? null;
        $filialId = $_GET['filial_id'] ?? null;

        if (!$tenantId) {
            return false;
        }

        // Log do webhook
        error_log("Evolution Webhook: " . json_encode($data));

        // Atualizar status da instância se necessário
        if (isset($data['event']) && $data['event'] === 'connection.update') {
            $instanceName = $data['instance'] ?? null;
            $status = $data['state'] ?? 'desconectado';

            if ($instanceName) {
                self::$db->update(
                    'evolution_instancias',
                    ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')],
                    'nome_instancia = ? AND tenant_id = ?',
                    [$instanceName, $tenantId]
                );
            }
        }

        return true;
    }
}
