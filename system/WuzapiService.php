<?php

namespace System;

use Exception;

/**
 * Wuzapi Service
 * 
 * Service for sending WhatsApp messages via Wuzapi API
 */
class WuzapiService
{
    private $config;
    private $apiUrl;
    private $token;
    private $instanceId;
    private $timeout;
    
    public function __construct()
    {
        $this->config = Config::getInstance();
        
        // Get Wuzapi configuration from environment
        $this->apiUrl = $this->config->getEnv('WUZAPI_URL');
        $this->token = $this->config->getEnv('WUZAPI_TOKEN');
        $this->instanceId = $this->config->getEnv('WUZAPI_INSTANCE_ID');
        $this->timeout = (int) ($this->config->getEnv('WUZAPI_TIMEOUT') ?: 30);
        
        if (empty($this->apiUrl)) {
            throw new Exception('WUZAPI_URL not configured in environment');
        }
        
        if (empty($this->token)) {
            throw new Exception('WUZAPI_TOKEN not configured in environment');
        }
        
        if (empty($this->instanceId)) {
            throw new Exception('WUZAPI_INSTANCE_ID not configured in environment');
        }
    }
    
    /**
     * Send text message via WhatsApp
     * 
     * @param string $phone Phone number (with or without country code)
     * @param string $message Message text
     * @return array Response from Wuzapi
     */
    public function sendMessage($phone, $message)
    {
        try {
            $formattedPhone = $this->formatPhone($phone);
            
            $url = rtrim($this->apiUrl, '/') . '/api/send';
            
            $data = [
                'instanceId' => $this->instanceId,
                'phone' => $formattedPhone,
                'message' => $message
            ];
            
            error_log("WuzapiService::sendMessage - Sending to {$formattedPhone}: " . substr($message, 0, 50) . "...");
            
            $response = $this->makeRequest('POST', $url, $data);
            
            error_log("WuzapiService::sendMessage - Response: " . json_encode($response));
            
            return [
                'success' => true,
                'data' => $response,
                'phone' => $formattedPhone
            ];
            
        } catch (Exception $e) {
            error_log("WuzapiService::sendMessage - Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send media message (image, document, etc)
     * 
     * @param string $phone Phone number
     * @param string $mediaUrl URL of media file
     * @param string $caption Optional caption
     * @param string $type Media type (image, document, video, audio)
     * @return array Response
     */
    public function sendMedia($phone, $mediaUrl, $caption = '', $type = 'image')
    {
        try {
            $formattedPhone = $this->formatPhone($phone);
            
            $url = rtrim($this->apiUrl, '/') . '/api/send-media';
            
            $data = [
                'instanceId' => $this->instanceId,
                'phone' => $formattedPhone,
                'mediaUrl' => $mediaUrl,
                'caption' => $caption,
                'type' => $type
            ];
            
            $response = $this->makeRequest('POST', $url, $data);
            
            return [
                'success' => true,
                'data' => $response
            ];
            
        } catch (Exception $e) {
            error_log("WuzapiService::sendMedia - Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get instance status
     * 
     * @return array Instance info
     */
    public function getStatus()
    {
        try {
            $url = rtrim($this->apiUrl, '/') . '/api/instance/status';
            
            $data = [
                'instanceId' => $this->instanceId
            ];
            
            $response = $this->makeRequest('GET', $url, $data);
            
            return [
                'success' => true,
                'data' => $response
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Format phone number to WhatsApp format
     * 
     * @param string $phone Raw phone number
     * @return string Formatted phone (with country code)
     */
    private function formatPhone($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add Brazil country code if not present
        if (strlen($phone) === 11) {
            $phone = '55' . $phone; // Brazil
        } elseif (strlen($phone) === 10) {
            $phone = '55' . $phone; // Brazil (old format)
        }
        
        // Add @c.us suffix for WhatsApp
        return $phone . '@c.us';
    }
    
    /**
     * Make HTTP request to Wuzapi API
     * 
     * @param string $method HTTP method
     * @param string $url API endpoint
     * @param array $data Request data
     * @return array Response data
     */
    private function makeRequest($method, $url, $data = [])
    {
        $ch = curl_init();
        
        // Set URL
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        // Set options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        // Set headers
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            "Authorization: Bearer {$this->token}"
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Set method and data
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        // Handle errors
        if ($error) {
            throw new Exception("cURL error: {$error}");
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("HTTP {$httpCode}: {$response}");
        }
        
        // Parse response
        $responseData = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: {$response}");
        }
        
        return $responseData;
    }
    
    /**
     * Send bulk messages (with rate limiting)
     * 
     * @param array $messages Array of ['phone' => '', 'message' => '']
     * @param int $delaySeconds Delay between messages (default 2s)
     * @return array Results
     */
    public function sendBulk($messages, $delaySeconds = 2)
    {
        $results = [];
        
        foreach ($messages as $index => $msg) {
            $result = $this->sendMessage($msg['phone'], $msg['message']);
            $results[] = $result;
            
            // Delay between messages (rate limiting)
            if ($index < count($messages) - 1) {
                sleep($delaySeconds);
            }
        }
        
        return $results;
    }
}

