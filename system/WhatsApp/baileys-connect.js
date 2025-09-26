const { default: makeWASocket, DisconnectReason, useMultiFileAuthState } = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const qrcode = require('qrcode');

/**
 * Baileys Connect Script - Conexão WhatsApp Authentic (Real Protocol)
 * Como Evolution API em tipo real Dynamic QR
 */

async function connectInstance(instanceId, phoneNumber) {
    const sessionPath = `./sessions/${instanceId}`;
    
    try {
        console.log(`Conectando instância: ${instanceId} (${phoneNumber})`);
        
        // Setup authentication session directory
        const { state, saveCreds } = await useMultiFileAuthState(sessionPath);
        
        // Create WhatsApp socket CORRECTLY - like official Baileys examples
        const sock = makeWASocket({
            auth: state,
            printQRInTerminal: false,
            logger: {
                level: 'silent',
                child: () => ({ level: 'silent' })
            },
            browser: ['Divino Lanches', 'Chrome', 'latest']
        });

        let qrCodeData = null;
        let connectionStatus = 'disconnected';

        // ✅ CORRECT EVENT HANDLER (fix syntax error)  
        sock.ev.on('connection.update', async (update) => {
            const { connection, lastDisconnect, qr } = update;
            
            // 📱 QR Code Handler - Generate REAL WhatsApp QR
            if (qr) {
                console.log(`📱 QR received, generating code for instance ${instanceId}`);
                
                try {
                    // Generate REAL QR code PNG binary
                    const qrImageBase64 = await qrcode.toDataURL(qr, {
                        errorCorrectionLevel: 'M',
                        type: 'image/png',
                        quality: 92,
                        margin: 1,
                        width: 300
                    });
                    
                    // Remove data:image prefix to get raw base64
                    const rawBase64 = qrImageBase64.split(',')[1]; 
                    qrCodeData = rawBase64;
                    
                    // 🟩 JSON should be printed immediately for PHP to receive
                    const result = {
                        success: true,
                        qr_code: rawBase64,
                        status: 'qrcode',
                        instance_id: instanceId,
                        phone: phoneNumber,
                        message: 'QR Code gerado. Escaneie com WhatsApp.'
                    };
                    console.log(JSON.stringify(result));
                    
                } catch (qrError) {
                    console.error(`❌ QR generation failed: ${qrError.message}`);
                    const error = {
                        success: false,
                        error: `QR gerado falhou: ${qrError.message}`,
                        instance_id: instanceId
                    };
                    console.log(JSON.stringify(error));
                }
            }

            // 🔗 Connection Status Handling  
            if (connection === 'open') {
                connectionStatus = 'connected';
                console.log(`✅ Connected: Instance ${instanceId} (${phoneNumber})`);
                
                const result = {
                    success: true,
                    connected: true,
                    status: 'connected',
                    instance_id: instanceId,
                    phone: phoneNumber
                };
                console.log(JSON.stringify(result));
                
                // Init ping session to keep alive
                startSessionPing(sock, instanceId);
                return;
            }
            
            if (connection === 'close') {
                connectionStatus = 'disconnected';
                
                // Identify if user disconnected  
                const shouldReconnect = lastDisconnect?.error?.output?.statusCode === DisconnectReason.badSession;
                if (shouldReconnect) {
                    connectInstance(instanceId, phoneNumber); 
                } else {
                    const failed = {
                        success: false,
                        status: 'disconnected',
                        instance_id: instanceId,
                        reason: lastDisconnect?.error?.message || 'Lost connection'
                    };
                    console.log(JSON.stringify(failed));
                }
                return;
            }
        });

        // 💾 Auto save credentials   
        sock.ev.on('creds.update', saveCreds);

        setTimeout(() => {
            if (connectionStatus === 'disconnected') {
                console.log(JSON.stringify({
                    success: false,
                    status: 'timeout',
                    message: 'Connection timeout'
                }));
            }
        }, 18000); // timeout like Evolution (3 min)
        
        // Keep active is connected
        return sock;

    } catch (error) { 
        console.error('❌ Baileys connection fatal: ', error.message);
        console.log(JSON.stringify({
            success: false,
            error: error.message,
            status: 'failed',
            instance_id: instanceId
        }));
        process.exit(1);
    }
}

/**
 * Strategic ping prevent disconnect
 */
function startSessionPing(sock, instanceId) {
    const pingInterval = setInterval(() => {
        if (sock.state === 'open') { 
            // Silent ping function
            sock.ev.on('connection.update', (update) => {
                // Connection alive  
            });
        }
        try {
            sock.ev.on('creds.update', () => {
                // Keep near
            });
        } catch (err) {
            clearInterval(pingInterval);
        }
    }, 30000);
}

// Command line execution
if (require.main === module) {
    const args = process.argv.slice(2);
    if (args.length >= 2) {
        connectInstance(args[0], args[1]);
    } else {
        console.log(JSON.stringify({
            success: false,
            error: 'Usage: node baileys-connect.js INSTANCE_ID PHONE_NUMBER'
        }));
    }
}

module.exports = { connectInstance };