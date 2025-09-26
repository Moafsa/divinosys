const { default: makeWASocket, DisconnectReason, useMultiFileAuthState } = require('@whiskeysockets/baileys');
const { Boom } = require('@hapi/boom');

async function sendMessage(instanceId, to, message, messageType = 'text') {
    try {
        // Carregar sessão da instância
        const { state, saveCreds } = await useMultiFileAuthState(`./sessions/${instanceId}`);
        
        // Conectar ao WhatsApp
        const sock = makeWASocket({
            auth: state,
            printQRInTerminal: false,
            logger: { level: 'silent' }
        });
        
        // Aguardar conexão
        sock.ev.on('connection.update', (update) => {
            if (update.connection === 'open') {
                console.log('Conectado ao WhatsApp');
            }
        });
        
        // Enviar mensagem
        let result;
        if (messageType === 'text') {
            result = await sock.sendMessage(to, { text: message });
        } else if (messageType === 'image') {
            result = await sock.sendMessage(to, { image: { url: message } });
        } else if (messageType === 'document') {
            result = await sock.sendMessage(to, { document: { url: message } });
        } else {
            result = await sock.sendMessage(to, { text: message });
        }
        
        // Salvar credenciais
        sock.ev.on('creds.update', saveCreds);
        
        return {
            success: true,
            message_id: result.key.id,
            status: 'sent'
        };
        
    } catch (error) {
        return {
            success: false,
            error: error.message
        };
    }
}

// Executar se chamado diretamente
if (require.main === module) {
    const [,, instanceId, to, message, messageType] = process.argv;
    sendMessage(instanceId, to, message, messageType)
        .then(result => console.log(JSON.stringify(result)))
        .catch(error => console.log(JSON.stringify({success: false, error: error.message})));
}

module.exports = { sendMessage };
