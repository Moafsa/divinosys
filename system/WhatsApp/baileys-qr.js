const { default: makeWASocket, DisconnectReason, useMultiFileAuthState } = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');
const qrcode = require('qrcode');

async function generateQRCode(instanceId) {
    try {
        // Carregar sessão da instância
        const { state, saveCreds } = await useMultiFileAuthState(`./sessions/${instanceId}`);
        
        // Conectar ao WhatsApp
        const sock = makeWASocket({
            auth: state,
            printQRInTerminal: false,
            logger: { level: 'silent' }
        });
        
        // Aguardar QR Code
        return new Promise((resolve, reject) => {
            sock.ev.on('connection.update', async (update) => {
                if (update.qr) {
                    try {
                        // Gerar QR Code como string
                        const qrCodeString = await qrcode.toString(update.qr, { type: 'utf8' });
                        resolve({
                            success: true,
                            qr_code: qrCodeString
                        });
                    } catch (error) {
                        reject({
                            success: false,
                            error: error.message
                        });
                    }
                } else if (update.connection === 'open') {
                    resolve({
                        success: true,
                        qr_code: 'connected'
                    });
                } else if (update.connection === 'close') {
                    reject({
                        success: false,
                        error: 'Conexão fechada'
                    });
                }
            });
            
            // Timeout após 30 segundos
            setTimeout(() => {
                reject({
                    success: false,
                    error: 'Timeout ao gerar QR Code'
                });
            }, 30000);
        });
        
    } catch (error) {
        return {
            success: false,
            error: error.message
        };
    }
}

// Executar se chamado diretamente
if (require.main === module) {
    const [,, instanceId] = process.argv;
    generateQRCode(instanceId)
        .then(result => console.log(JSON.stringify(result)))
        .catch(error => console.log(JSON.stringify({success: false, error: error.message})));
}

module.exports = { generateQRCode };
