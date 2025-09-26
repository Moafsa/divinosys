/**
 * Baileys HTTP Server - Real WhatsApp Connection Service
 * Detalhes: Criar o verdadeiro Baileys server que outros sistemas usam
 */

// CRITICAL: crypto module must be required FIRST
const crypto = require('crypto');

const { default: makeWASocket, DisconnectReason, useMultiFileAuthState } = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const qrcode = require('qrcode');
const express = require('express');
const fs = require('fs');
const path = require('path');
const Redis = require('ioredis');
const { Buffer } = require('buffer');

const app = express();
app.use(express.json());
const PORT = 3000;

// Redis connection for session management
const redis = new Redis({
    host: process.env.REDIS_HOST || 'redis',
    port: process.env.REDIS_PORT || 6379,
    retryDelayOnFailover: 100,
    maxRetriesPerRequest: 3
});

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
      // CRITICAL: Ensure crypto module exists before anything else
      if (typeof crypto === 'undefined') {
        throw new Error('Crypto module not available - check Node runtime');
      }
      console.log(`🔐 Crypto module available: ${!!crypto}`);
      
      // Additional validation required for complex crypto functions
      if (!crypto.randomBytes) {
        throw new Error('Crypto randomBytes not available - openssl incomate!');
      }

      // Ensure sessions directory exists
      const sessionPath = `./sessions/${instanceId}`;
      if (!fs.existsSync('./sessions')) {
        fs.mkdirSync('./sessions', { recursive: true });
      }
      if (!fs.existsSync(sessionPath)) {
        fs.mkdirSync(sessionPath, { recursive: true });
      }
      
      // Enhanced session management like fazer.ai (Redis + File fallback)  
      const redisKey = `@baileys:${instanceId}`;
      let sessionData;
      
      try {
        sessionData = await redis.hgetall(redisKey);
        if (Object.keys(sessionData).length > 0) {
          console.log(`🔄 Redis session found for ${instanceId}`);
        }
      } catch (redisError) {
        console.warn(`⚠️ Redis unavailable, using file sessions`);
      }
      
      // Use file-based sessions with Redis backup  
      const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
            
            // Store session in Redis for persistence
            if (state?.creds && sessionData) {
                await redis.hset(redisKey, 'creds', JSON.stringify(state.creds));
                await redis.hset(redisKey, 'lastSeen', new Date().toISOString());
            }
            
            const sock = makeWASocket({
                auth: state,
                printQRInTerminal: false,
                browser: ['DivinoLanches','Chrome','1.0'],
                keepAliveIntervalMs: 30000,
                connectTimeoutMs: 10_000,
                defaultQueryTimeoutMs: 60_000,
                retryRequestDelayMs: 250,
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
                },
                generateHighQualityLinkPreview: true,
                markOnlineOnConnect: true,
                shouldIgnoreJid: (jid) => false
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
                        // Generate QR code DataURL using qrcode library like fazer.ai implementation
                        const qrDataUrl = await qrcode.toDataURL(qr, {
                            errorCorrectionLevel: 'M',
                            type: 'image/png',
                            width: 300,
                            margin: 2,
                            color: {
                                dark: '#000000',
                                light: '#FFFFFF'
                            }
                        });
                        
                        // Extract base64 string without data:image/png;base64, prefix
                        const qrCodeBase64 = qrDataUrl.split(',')[1];
                        qrData = qrCodeBase64;
                        responseSent = true;
                        
                        const response = {
                            success: true,
                            qr_code: qrCodeBase64,
                            status: 'qrcode',
                            instance_id: instanceId,
                            phone: phoneNumber,
                            message: 'Scan this QR with WhatsApp', 
                            qr_raw: qr,
                            qrDataUrl: qrDataUrl
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
            if (!responseSent) {
                responseSent = true;
                res.status(500).json({
                    success: false,
                    status: 'error',
                    instance_id: instanceId,
                    reason: error.message === 'crypto is not defined' ? 'Crypto module not loaded - internal server error' : error.message,
                    detail: error.code || 'unknown'
                });
            }
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
        
        // Additional crypto safety validation
        const cryptoAgain = require('crypto');
        console.log('🔐 Deployed crypto successfully available type:', typeof cryptoAgain, !!cryptoAgain.publicDecrypt);
    });
}

// Initialize app startup procedure
console.log('🎂 NEW START: Crypto module detection');
console.log('Crypto loaded (exists):', typeof crypto, !!crypto);

// CRITICAL: Double check crypto available just above initialization 
// BEFORE calling initBaileysServer methods
// **This ensures that crypto is correctly loaded in all streams**

initBaileysServer();
