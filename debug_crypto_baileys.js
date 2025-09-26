/**
 * DEBUG Baileys Crypto Module Check
 * Test real environmental configuration for correct startup
 */

const crypto = require('crypto');
const { default: makeWASocket, DisconnectReason, useMultiFileAuthState } = require('@whiskeysockets/baileys');

console.log('🔐 Debug crypto module check...');
console.log('Crypto available:', !!crypto);
console.log('Crypto methods:', typeof crypto.randomBytes, typeof crypto.createHash);
console.log('Crypto Aes:', typeof crypto.AES);

try {
    // Teste básico Baileys Requirements Crypto
    const testSessionPath = './sessions/debug-test';
    const fs = require('fs');
    
    if (!fs.existsSync('./sessions')) {
        fs.mkdirSync('./sessions', { recursive: true });
    }
    if (!fs.existsSync(testSessionPath)) {
        fs.mkdirSync(testSessionPath, { recursive: true });
    }
    
    console.log('\n🔍 Testing BaileysSocket initialization...');
    
    const { state } = await useMultiFileAuthState(testSessionPath);
    console.log('✅ AuthState loaded');
    
    const sock = makeWASocket({
        auth: state,
        printQRInTerminal: false,
        logger: {
            level: 'silent',
            child: () => ({ level: 'silent' })
        },
        browser: ['DebugBaileys', 'Chrome', '1.0']
    });
    
    console.log('✅ BaileysSocket created successfully');
    sock.close(() => {
        console.log('Socket closed');
    });
    
} catch (error) {
    console.error('❌ CRYPTO/BAILEYS test failed:');
    console.error('Error type:', error.name);
    console.error('Error message:', error.message);
    console.error('Stack traces:', error.stack.split('\n').slice(0, 3));
}
