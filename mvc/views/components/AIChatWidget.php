<?php
/**
 * AI Chat Widget Component
 * Can be included in any page to add AI chat functionality
 */
?>

<!-- AI Chat Widget -->
<div class="ai-chat-widget">
    <!-- Chat Toggle Button -->
    <button class="ai-chat-toggle" id="aiChatToggle" title="Assistente IA">
        <i class="fas fa-robot"></i>
        <span class="notification-badge" id="aiNotificationBadge" style="display: none;">1</span>
    </button>

    <!-- Chat Container -->
    <div class="ai-chat-panel" id="aiChatPanel">
        <div class="ai-chat-header">
            <div class="ai-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="ai-info">
                <h6 class="mb-0">Assistente IA</h6>
                <small class="text-muted">Divino Lanches</small>
            </div>
            <div class="ai-actions">
                <button class="btn btn-sm btn-outline-light" id="clearChatBtn" title="Limpar conversa">
                    <i class="fas fa-trash"></i>
                </button>
                <button class="btn btn-sm btn-outline-light" id="closeChatBtn" title="Fechar chat">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="ai-chat-body">
            <div class="ai-chat-messages" id="aiChatMessages">
                <!-- Messages will be added here -->
            </div>

            <div class="ai-chat-input-container">
                <div class="ai-chat-input-wrapper">
                    <div class="input-group input-group-sm">
                        <button class="btn btn-outline-secondary" type="button" id="attachFileBtn" title="Anexar arquivo">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <input type="file" id="fileInput" accept="image/*,application/pdf,.csv,.xlsx,.xls" style="display: none;">
                        
                        <input type="text" class="form-control" id="messageInput" 
                               placeholder="Digite sua mensagem..." 
                               autocomplete="off">
                        
                        <button class="btn btn-outline-primary" type="button" id="voiceBtn" title="Gravação de voz">
                            <i class="fas fa-microphone"></i>
                        </button>
                        
                        <button class="btn btn-primary" type="button" id="sendBtn" title="Enviar">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>

                <div class="ai-chat-suggestions">
                    <div class="suggestion-buttons">
                        <button class="btn btn-xs btn-outline-secondary suggestion-btn" data-suggestion="Criar produto">
                            <i class="fas fa-plus"></i> Criar produto
                        </button>
                        <button class="btn btn-xs btn-outline-secondary suggestion-btn" data-suggestion="Listar produtos">
                            <i class="fas fa-list"></i> Listar produtos
                        </button>
                        <button class="btn btn-xs btn-outline-secondary suggestion-btn" data-suggestion="Ver pedidos">
                            <i class="fas fa-shopping-cart"></i> Ver pedidos
                        </button>
                        <button class="btn btn-xs btn-outline-secondary suggestion-btn" data-suggestion="Ajuda">
                            <i class="fas fa-question"></i> Ajuda
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.ai-chat-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1050;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.ai-chat-toggle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    position: relative;
}

.ai-chat-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.ai-chat-panel {
    position: absolute;
    bottom: 70px;
    right: 0;
    width: 350px;
    height: 500px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    display: none;
    flex-direction: column;
    overflow: hidden;
}

.ai-chat-panel.show {
    display: flex;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.ai-chat-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ai-avatar {
    width: 35px;
    height: 35px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}

.ai-info {
    flex: 1;
}

.ai-info h6 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
}

.ai-info small {
    font-size: 11px;
    opacity: 0.8;
}

.ai-actions {
    display: flex;
    gap: 5px;
}

.ai-actions .btn {
    width: 28px;
    height: 28px;
    padding: 0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ai-chat-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
}

.ai-chat-messages {
    flex: 1;
    padding: 15px;
    overflow-y: auto;
    background: #f8f9fa;
}

.message {
    margin-bottom: 12px;
    display: flex;
    align-items: flex-start;
}

.message.user {
    justify-content: flex-end;
}

.message.ai {
    justify-content: flex-start;
}

.message-content {
    max-width: 80%;
    padding: 8px 12px;
    border-radius: 12px;
    font-size: 13px;
    line-height: 1.4;
}

.message.user .message-content {
    background: #007bff;
    color: white;
    border-bottom-right-radius: 4px;
}

.message.ai .message-content {
    background: white;
    color: #333;
    border: 1px solid #e9ecef;
    border-bottom-left-radius: 4px;
}

.message-time {
    font-size: 10px;
    opacity: 0.7;
    margin-top: 4px;
}

.ai-chat-input-container {
    padding: 12px;
    background: white;
    border-top: 1px solid #e9ecef;
}

.ai-chat-input-wrapper {
    margin-bottom: 8px;
}

.ai-chat-suggestions {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.suggestion-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.suggestion-btn {
    font-size: 10px;
    padding: 4px 8px;
    border-radius: 12px;
    white-space: nowrap;
}

.typing-indicator {
    display: none;
    align-items: center;
    gap: 8px;
    color: #6c757d;
    font-style: italic;
    font-size: 12px;
}

.typing-dots {
    display: flex;
    gap: 2px;
}

.typing-dot {
    width: 4px;
    height: 4px;
    background: #6c757d;
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-dot:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% {
        transform: translateY(0);
    }
    30% {
        transform: translateY(-8px);
    }
}

.confirmation-dialog {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 10px;
    margin-top: 8px;
}

.confirmation-buttons {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.voice-recording {
    background: #dc3545 !important;
    color: white !important;
    animation: pulse 1s infinite;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .ai-chat-widget {
        bottom: 10px;
        right: 10px;
    }
    
    .ai-chat-panel {
        width: calc(100vw - 20px);
        height: calc(100vh - 100px);
        bottom: 80px;
        right: 10px;
    }
    
    .ai-chat-toggle {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
}
</style>

<script>
class AIChatWidget {
    constructor() {
        this.isOpen = false;
        this.messages = [];
        this.isRecording = false;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.storageKey = 'ai_chat_history';
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadChatHistory(); // Load from localStorage first
        
        // Only add welcome if no history
        if (this.messages.length === 0) {
            this.addWelcomeMessage();
        }
    }
    
    loadChatHistory() {
        try {
            const saved = localStorage.getItem(this.storageKey);
            if (saved) {
                const history = JSON.parse(saved);
                this.messages = history;
                
                // Render saved messages
                history.forEach(msg => {
                    this.renderMessageToDOM(msg.sender, msg.content, msg.attachments || []);
                });
                
                console.log(`✅ Loaded ${history.length} messages from localStorage - Conversa restaurada!`);
            }
        } catch (error) {
            console.error('Error loading chat history:', error);
        }
    }
    
    saveChatHistory() {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(this.messages));
        } catch (error) {
            console.error('Error saving chat history:', error);
        }
    }

    setupEventListeners() {
        const toggle = document.getElementById('aiChatToggle');
        const panel = document.getElementById('aiChatPanel');
        const closeBtn = document.getElementById('closeChatBtn');
        const clearBtn = document.getElementById('clearChatBtn');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const attachFileBtn = document.getElementById('attachFileBtn');
        const fileInput = document.getElementById('fileInput');
        const voiceBtn = document.getElementById('voiceBtn');
        const suggestionBtns = document.querySelectorAll('.suggestion-btn');

        // Toggle chat
        toggle.addEventListener('click', () => {
            this.toggleChat();
        });

        // Close chat
        closeBtn.addEventListener('click', () => {
            this.closeChat();
        });

        // Clear chat
        clearBtn.addEventListener('click', () => {
            this.clearChat();
        });

        // Send message on Enter
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Send button
        sendBtn.addEventListener('click', () => {
            this.sendMessage();
        });

        // File attachment
        attachFileBtn.addEventListener('click', () => {
            fileInput.click();
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.handleFileUpload(e.target.files[0]);
            }
        });

        // Voice recording
        voiceBtn.addEventListener('click', () => {
            if (this.isRecording) {
                this.stopRecording();
            } else {
                this.startRecording();
            }
        });

        // Suggestion buttons
        suggestionBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const suggestion = btn.dataset.suggestion;
                messageInput.value = suggestion;
                this.sendMessage();
            });
        });
    }

    toggleChat() {
        const panel = document.getElementById('aiChatPanel');
        const toggle = document.getElementById('aiChatToggle');
        
        this.isOpen = !this.isOpen;
        
        if (this.isOpen) {
            panel.classList.add('show');
            toggle.innerHTML = '<i class="fas fa-times"></i>';
        } else {
            panel.classList.remove('show');
            toggle.innerHTML = '<i class="fas fa-robot"></i>';
        }
    }

    closeChat() {
        this.isOpen = false;
        document.getElementById('aiChatPanel').classList.remove('show');
        document.getElementById('aiChatToggle').innerHTML = '<i class="fas fa-robot"></i>';
    }

    addWelcomeMessage() {
        this.addMessage('ai', 'Olá! Sou seu assistente IA para o sistema Divino Lanches. Posso ajudar você a gerenciar produtos, ingredientes, pedidos e muito mais. Como posso ajudar?');
    }
    
    renderMessageToDOM(sender, content, attachments = []) {
        const messagesContainer = document.getElementById('aiChatMessages');
        if (!messagesContainer) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;

        let attachmentHtml = '';
        if (attachments && attachments.length > 0) {
            attachmentHtml = '<div class="message-attachments" style="margin-top: 5px;">';
            attachments.forEach(attachment => {
                attachmentHtml += `<span class="attachment-item" style="display: inline-block; padding: 2px 6px; background: rgba(0,123,255,0.1); border-radius: 10px; font-size: 10px; margin-right: 3px;">
                    <i class="fas fa-file"></i> ${attachment.name}
                </span>`;
            });
            attachmentHtml += '</div>';
        }

        messageDiv.innerHTML = `
            <div class="message-content">
                ${this.formatMessage(content)}
                ${attachmentHtml}
                <div class="message-time">${new Date().toLocaleTimeString('pt-BR')}</div>
            </div>
        `;

        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    addMessage(sender, content, attachments = []) {
        // Save to messages array
        this.messages.push({
            sender,
            content,
            attachments,
            timestamp: new Date().toISOString()
        });
        
        // Save to localStorage
        this.saveChatHistory();
        
        // Render to DOM
        this.renderMessageToDOM(sender, content, attachments);

        // Show notification badge if chat is closed
        if (!this.isOpen) {
            this.showNotification();
        }
    }

    formatMessage(content) {
        content = content.replace(/\n/g, '<br>');
        content = content.replace(/`([^`]+)`/g, '<code style="background: #f1f3f4; padding: 2px 4px; border-radius: 3px; font-size: 11px;">$1</code>');
        content = content.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        return content;
    }

    showNotification() {
        const badge = document.getElementById('aiNotificationBadge');
        if (badge) {
            badge.style.display = 'flex';
        }
    }

    hideNotification() {
        try {
            const badge = document.getElementById('aiNotificationBadge');
            if (badge) {
                badge.style.display = 'none';
            }
        } catch (error) {
            console.warn('Error hiding notification:', error);
        }
    }

    async sendMessage() {
        const messageInput = document.getElementById('messageInput');
        if (!messageInput) return;
        
        const message = messageInput.value.trim();

        if (!message) return;

        this.addMessage('user', message);
        messageInput.value = '';
        
        // Safe hide notification
        try {
            this.hideNotification();
        } catch (error) {
            console.warn('Notification badge not found:', error);
        }

        this.showTypingIndicator();

        try {
            // Prepare chat history (last 10 messages to avoid payload too large)
            const chatHistory = this.messages.slice(-10).map(msg => ({
                role: msg.sender === 'user' ? 'user' : 'assistant',
                content: msg.content
            }));
            
            const response = await fetch('mvc/ajax/ai_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=send_message&message=${encodeURIComponent(message)}&history=${encodeURIComponent(JSON.stringify(chatHistory))}`
            });

            const data = await response.json();
            this.hideTypingIndicator();

            if (data.success) {
                this.handleAIResponse(data.response);
            } else {
                this.addMessage('ai', 'Desculpe, ocorreu um erro: ' + data.message);
            }
        } catch (error) {
            this.hideTypingIndicator();
            this.addMessage('ai', 'Erro de conexão. Tente novamente.');
            console.error('Error:', error);
        }
    }

    handleAIResponse(response) {
        if (response.type === 'confirmation' && response.confirm) {
            this.addConfirmationMessage(response);
        } else {
            this.addMessage('ai', response.message);
        }
    }

    addConfirmationMessage(response) {
        const messagesContainer = document.getElementById('aiChatMessages');
        if (!messagesContainer) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message ai';

        messageDiv.innerHTML = `
            <div class="message-content">
                ${this.formatMessage(response.message)}
                <div class="confirmation-dialog">
                    <p style="margin: 0 0 8px 0; font-size: 12px;"><strong>Confirmação necessária:</strong></p>
                    <div class="confirmation-buttons">
                        <button class="btn btn-success btn-sm" style="font-size: 11px; padding: 4px 8px;" onclick="aiChatWidget.confirmOperation(true, ${JSON.stringify(response).replace(/"/g, '&quot;')})">
                            <i class="fas fa-check"></i> Confirmar
                        </button>
                        <button class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 4px 8px;" onclick="aiChatWidget.confirmOperation(false, ${JSON.stringify(response).replace(/"/g, '&quot;')})">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div class="message-time">${new Date().toLocaleTimeString('pt-BR')}</div>
            </div>
        `;

        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    async confirmOperation(confirmed, responseData) {
        if (!confirmed) {
            this.addMessage('ai', 'Operação cancelada.');
            return;
        }

        this.showTypingIndicator();

        try {
            const response = await fetch('mvc/ajax/ai_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=execute_operation&operation=${encodeURIComponent(JSON.stringify(responseData))}`
            });

            const data = await response.json();
            this.hideTypingIndicator();

            if (data.success && data.result.success) {
                this.addMessage('ai', data.result.message);
                // Refresh page data if needed
                if (typeof refreshPageData === 'function') {
                    refreshPageData();
                }
            } else {
                this.addMessage('ai', 'Erro: ' + (data.result.message || data.message));
            }
        } catch (error) {
            this.hideTypingIndicator();
            this.addMessage('ai', 'Erro ao executar operação.');
            console.error('Error:', error);
        }
    }

    async handleFileUpload(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('action', 'upload_file');

        try {
            const response = await fetch('mvc/ajax/ai_chat.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.addMessage('user', `Arquivo anexado: ${file.name}`, [data.file]);
                
                this.showTypingIndicator();
                
                const aiResponse = await fetch('mvc/ajax/ai_chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_message&message=Processe este arquivo&attachments=${encodeURIComponent(JSON.stringify([data.file]))}`
                });

                const aiData = await aiResponse.json();
                this.hideTypingIndicator();

                if (aiData.success) {
                    this.handleAIResponse(aiData.response);
                } else {
                    this.addMessage('ai', 'Erro ao processar arquivo: ' + aiData.message);
                }
            } else {
                this.addMessage('ai', 'Erro no upload: ' + data.message);
            }
        } catch (error) {
            this.addMessage('ai', 'Erro no upload do arquivo.');
            console.error('Error:', error);
        }
    }

    async startRecording() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            this.mediaRecorder = new MediaRecorder(stream);
            this.audioChunks = [];

            this.mediaRecorder.ondataavailable = (event) => {
                this.audioChunks.push(event.data);
            };

            this.mediaRecorder.onstop = () => {
                const audioBlob = new Blob(this.audioChunks, { type: 'audio/wav' });
                this.processAudio(audioBlob);
                stream.getTracks().forEach(track => track.stop());
            };

            this.mediaRecorder.start();
            this.isRecording = true;
            
            const voiceBtn = document.getElementById('voiceBtn');
            voiceBtn.classList.add('voice-recording');
            voiceBtn.innerHTML = '<i class="fas fa-stop"></i>';
            
            this.addMessage('user', '🎤 Gravando...');

        } catch (error) {
            console.error('Error accessing microphone:', error);
            this.addMessage('ai', 'Erro ao acessar o microfone.');
        }
    }

    stopRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.stop();
            this.isRecording = false;
            
            const voiceBtn = document.getElementById('voiceBtn');
            voiceBtn.classList.remove('voice-recording');
            voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
        }
    }

    async processAudio(audioBlob) {
        this.addMessage('ai', '🎤 Processando áudio...');
        
        // Simulate audio processing
        setTimeout(() => {
            this.addMessage('ai', 'Áudio processado! (Funcionalidade de reconhecimento de voz em desenvolvimento)');
        }, 2000);
    }

    showTypingIndicator() {
        const messagesContainer = document.getElementById('aiChatMessages');
        if (!messagesContainer) return;
        
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message ai';
        typingDiv.id = 'typingIndicator';

        typingDiv.innerHTML = `
            <div class="message-content">
                <div class="typing-indicator" style="display: flex;">
                    <span>IA está digitando</span>
                    <div class="typing-dots">
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                    </div>
                </div>
            </div>
        `;

        messagesContainer.appendChild(typingDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    hideTypingIndicator() {
        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }

    clearChat() {
        const messagesContainer = document.getElementById('aiChatMessages');
        if (messagesContainer) {
            messagesContainer.innerHTML = '';
            this.messages = [];
            
            // Clear localStorage
            localStorage.removeItem(this.storageKey);
            console.log('🗑️ Chat history cleared from localStorage');
            
            this.addWelcomeMessage();
        }
    }
}

// Initialize AI Chat Widget when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.aiChatWidget = new AIChatWidget();
});
</script>
