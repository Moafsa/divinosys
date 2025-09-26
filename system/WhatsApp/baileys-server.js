/**
 * Baileys HTTP Server - Real WhatsApp Connection Service
 * Detalhes: Criar o verdadeiro Baileys server que outros sistemas usam
 */

const { default: makeWASocket, DisconnectReason, useMultiFileAuthState } = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const qrcode = require('qrcode');
const express = require('express');
const fs = require('fs');
const path = require('path');
const { Buffer } = require('buffer');

const app = express();
app.use(express.json());
const PORT = 3000;

// Store active sessions by instanceId
let activeSessions = {};

// Initialize Baileys server real
async function initBaileysServer() {
    
    app.post('/connect', async (req, res) => {
        const { instanceId, phoneNumber } = req.body;
        
        console.log(`\n=== CONNECT REQUEST ==`);
        console.log(`Instance ID: ${instanceId}`);
        console.log(`Phone: ${phoneNumber}`);
        
        try {
            // Ensure sessions directory exists
            const sessionPath = `./sessions/${instanceId}`;
            if (!fs.existsSync('./sessions')) {
                fs.mkdirSync('./sessions', { recursive: true });
            }
            if (!fs.existsSync(sessionPath)) {
                fs.mkdirSync(sessionPath, { recursive: true });
            }
            
            // Create Baileys instance with proper state management
            const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
            
            const sock = makeWASocket({
                auth: state,
                printQRInTerminal: false,
                logger: {
                    level: 'silent',
                    child: () => ({ 
                        level: 'silent',
                        trace: () => {},
                        debug: () => {},
                        info: () => {},
                        warn: () => {},
                        error: () => {}
                    }),
                    trace: () => {},
                    debug: () => {},
                    info: () => {},
                    warn: () => {},
                    error: () => {}
                }
            });
            
            let qrData = null;
            let connectionStatus = 'disconnected';
            let responseSent = false;
            
            // Handle genuine WhatsApp Web QR generation  
            sock.ev.on('connection.update', async (update) => {
                const { connection, lastDisconnect, qr } = update;
                
                console.log(`🔍 Connection update: ${connection}, QR: ${!!qr}`);
                
                if (qr && !responseSent) {
                    console.log(`✅ QR Code generated for instance ${instanceId}`);
                    console.log('🔗 QR Token length:', qr.length);
                    
                    try {
                        // Generate QR code PNG image for WhatsApp authentication
                        const qrImagePng = await qrcode.toBuffer(qr, {
                            type: 'png',
                            width: 300,
                            margin: 2,
                            color: {
                                dark: '#000000',
                                light: '#FFFFFF'
                            }
                        });
                        
                        const qrCodeBase64 = qrImagePng.toString('base64');
                        qrData = qrCodeBase64;
                        responseSent = true;
                        
                        const response = {
                            success: true,
                            qr_code: qrCodeBase64,
                            status: 'qrcode',
                            instance_id: instanceId,
                            phone: phoneNumber,
                            message: 'Scan this QR with WhatsApp',
                            qr_raw: qr
                        };
                        
                        res.json(response);
                        return;
                    } catch (qrError) {
                        console.error('❌ QR generation error:', qrError);
                        if (!responseSent) {
                            responseSent = true;
                            res.status(500).json({
                                success: false,
                                error: 'Failed to generate QR code: ' + qrError.message,
                                instance_id: instanceId
                            });
                        }
                        return;
                    }
                }
                
                if (connection === 'open') {
                    console.log(`🔗 Connected successfully for instance ${instanceId}`);
                    connectionStatus = 'connected';
                    activeSessions[instanceId] = { sock, state };
                    
                    sock.ev.on('creds.update', saveCreds);
                    
                    if (!responseSent) {
                        responseSent = true;
                        res.json({
                            success: true,
                            status: 'connected',
                            instance_id: instanceId,
                            phone: phoneNumber,
                            message: 'WhatsApp connected successfully'
                        });
                    }
                    return;
                }
                
                if (connection === 'close') {
                    console.log(`❌ Connection closed for instance ${instanceId}`);
                    connectionStatus = 'disconnected';
                    
                    // Clean up session reference
                    if (activeSessions[instanceId]) {
                        delete activeSessions[instanceId];
                    }
                    
                    const needsReconnect = lastDisconnect?.error?.output?.statusCode !== DisconnectReason.loggedOut;
                    if (needsReconnect) {
                        console.log(`🔄 Scheduling reconnect for instance ${instanceId}`);
                        setTimeout(() => {
                            delete activeSessions[instanceId];
                        }, 3000);
                    }
                    
                    if (!responseSent) {
                        responseSent = true;
                        res.json({
                            success: false,
                            status: 'disconnected',
                            instance_id: instanceId,
                            reason: lastDisconnect?.error?.message || 'Connection lost'
                        });
                    }
                    return;
                }
            });
            
            // Preserve credentials 
            sock.ev.on('creds.update', saveCreds);
            
            // Timeout handling (3 minutes like Evolution API)
            setTimeout(() => {
                if (connectionStatus === 'disconnected' && !responseSent) {
                    console.log(`⏱️ Connection timeout for instance ${instanceId}`);
                    responseSent = true;
                    res.json({
                        success: false,
                        status: 'timeout',
                        instance_id: instanceId,
                        message: 'Connection timeout. Please try again.'
                    });
                }
            }, 180000);
            
        } catch (error) {
            console.error('❌ Failed session:', error.message);
            res.status(500).json({
                success: false,
                error: error.message,
                instance_id: instanceId
            });
        }
    });
    
    app.get('/status', (req, res) => {
        res.json({
            success: true,
            active_sessions: Object.keys(activeSessions).length,
            container: 'baileys',
            status: 'running',
            uptime: process.uptime()
        });
    });
    
    app.get('/instances', (req, res) => {
        const instances = Object.keys(activeSessions).map(id => ({
            instanceId: id,
            status: 'connected',
            active: true
        }));
        res.json({
            success: true,
            instances: instances,
            total: instances.length
        });
    });
    
    app.post('/send-message', async (req, res) => {
        const { instanceId, to, message, messageType = 'text' } = req.body;
        
        console.log(`📤 Sending message for instance ${instanceId} to ${to}`);
        
        try {
            if (!activeSessions[instanceId]) {
                res.status(400).json({
                    success: false,
                    error: 'Instance not connected',
                    instanceId: instanceId
                });
                return;
            }
            
            const { sock } = activeSessions[instanceId];
            
            // Prepare message based on type
            let messageObj;
            if (messageType === 'text') {
                messageObj = { text: message };
            } else if (messageType === 'image') {
                messageObj = { image: { url: message } };
            } else if (messageType === 'document') {
                messageObj = { document: { url: message } };
            } else {
                messageObj = { text: message };
            }
            
            // Send message
            const result = await sock.sendMessage(to, messageObj);
            
            res.json({
                success: true,
                message_id: result.key.id,
                status: 'sent',
                instance_id: instanceId
            });
            
        } catch (error) {
            console.error('❌ Send message failed:', error.message);
            res.status(500).json({
                success: false,
                error: error.message,
                instance_id: instanceId
            });
        }
    });
    
    app.listen(PORT, '0.0.0.0', () => {
        console.log('🚀 BAILEYS SERVER RUNNING on port:' + PORT);
        console.log('🔗 WhatsApp Baileys integration ready!');
        console.log(`📱 Connect endpoint: http://localhost:${PORT}/connect`);
    });
}   

initBaileysServer();
