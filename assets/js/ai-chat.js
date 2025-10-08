class AIChat {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.messages = [];
        this.isRecording = false;
        this.mediaRecorder = null;
        this.audioChunks = [];
        this.init();
    }

    init() {
        this.createChatInterface();
        this.setupEventListeners();
        this.addWelcomeMessage();
    }

    createChatInterface() {
        this.container.innerHTML = `
            <div class="ai-chat-container">
                <div class="ai-chat-header">
                    <div class="ai-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="ai-info">
                        <h6 class="mb-0">Assistente IA</h6>
                        <small class="text-muted">Divino Lanches</small>
                    </div>
                    <div class="ai-status">
                        <span class="status-dot online"></span>
                        <small>Online</small>
                    </div>
                </div>
                
                <div class="ai-chat-messages" id="aiChatMessages">
                    <!-- Messages will be added here -->
                </div>
                
                <div class="ai-chat-input-container">
                    <div class="ai-chat-input-wrapper">
                        <div class="input-group">
                            <button class="btn btn-outline-secondary" type="button" id="attachFileBtn" title="Anexar arquivo">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <input type="file" id="fileInput" accept="image/*,application/pdf,.csv,.xlsx,.xls" style="display: none;">
                            
                            <input type="text" class="form-control" id="messageInput" 
                                   placeholder="Digite sua mensagem ou comando..." 
                                   autocomplete="off">
                            
                            <button class="btn btn-outline-primary" type="button" id="voiceBtn" title="Gravação de voz">
                                <i class="fas fa-microphone"></i>
                            </button>
                            
                            <button class="btn btn-primary" type="button" id="sendBtn" title="Enviar mensagem">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="ai-chat-suggestions">
                        <small class="text-muted">Sugestões:</small>
                        <div class="suggestion-buttons">
                            <button class="btn btn-sm btn-outline-secondary suggestion-btn" data-suggestion="Criar produto X-Burger">
                                Criar produto
                            </button>
                            <button class="btn btn-sm btn-outline-secondary suggestion-btn" data-suggestion="Listar produtos">
                                Listar produtos
                            </button>
                            <button class="btn btn-sm btn-outline-secondary suggestion-btn" data-suggestion="Adicionar ingrediente Bacon">
                                Adicionar ingrediente
                            </button>
                            <button class="btn btn-sm btn-outline-secondary suggestion-btn" data-suggestion="Ver pedidos pendentes">
                                Ver pedidos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add CSS styles
        this.addStyles();
    }

    addStyles() {
        if (document.getElementById('ai-chat-styles')) return;

        const styles = `
            <style id="ai-chat-styles">
                .ai-chat-container {
                    display: flex;
                    flex-direction: column;
                    height: 500px;
                    border: 1px solid #dee2e6;
                    border-radius: 10px;
                    background: white;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }

                .ai-chat-header {
                    display: flex;
                    align-items: center;
                    padding: 15px;
                    border-bottom: 1px solid #dee2e6;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border-radius: 10px 10px 0 0;
                }

                .ai-avatar {
                    width: 40px;
                    height: 40px;
                    background: rgba(255,255,255,0.2);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 12px;
                    font-size: 18px;
                }

                .ai-info {
                    flex: 1;
                }

                .ai-info h6 {
                    color: white;
                    font-weight: 600;
                }

                .ai-info small {
                    color: rgba(255,255,255,0.8);
                }

                .ai-status {
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }

                .status-dot {
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    background: #28a745;
                }

                .ai-chat-messages {
                    flex: 1;
                    padding: 15px;
                    overflow-y: auto;
                    max-height: 300px;
                }

                .message {
                    margin-bottom: 15px;
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
                    padding: 10px 15px;
                    border-radius: 15px;
                    position: relative;
                }

                .message.user .message-content {
                    background: #007bff;
                    color: white;
                    border-bottom-right-radius: 5px;
                }

                .message.ai .message-content {
                    background: #f8f9fa;
                    color: #333;
                    border: 1px solid #dee2e6;
                    border-bottom-left-radius: 5px;
                }

                .message-time {
                    font-size: 11px;
                    opacity: 0.7;
                    margin-top: 5px;
                }

                .message-attachments {
                    margin-top: 10px;
                }

                .attachment-item {
                    display: inline-block;
                    padding: 5px 10px;
                    background: rgba(0,123,255,0.1);
                    border-radius: 15px;
                    font-size: 12px;
                    margin-right: 5px;
                    margin-bottom: 5px;
                }

                .ai-chat-input-container {
                    padding: 15px;
                    border-top: 1px solid #dee2e6;
                    background: #f8f9fa;
                    border-radius: 0 0 10px 10px;
                }

                .ai-chat-input-wrapper {
                    margin-bottom: 10px;
                }

                .ai-chat-suggestions {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    flex-wrap: wrap;
                }

                .suggestion-buttons {
                    display: flex;
                    gap: 5px;
                    flex-wrap: wrap;
                }

                .suggestion-btn {
                    font-size: 11px;
                    padding: 2px 8px;
                }

                .typing-indicator {
                    display: none;
                    align-items: center;
                    gap: 10px;
                    color: #6c757d;
                    font-style: italic;
                }

                .typing-dots {
                    display: flex;
                    gap: 3px;
                }

                .typing-dot {
                    width: 6px;
                    height: 6px;
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
                        transform: translateY(-10px);
                    }
                }

                .confirmation-dialog {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    border-radius: 10px;
                    padding: 15px;
                    margin-top: 10px;
                }

                .confirmation-buttons {
                    display: flex;
                    gap: 10px;
                    margin-top: 10px;
                }

                .voice-recording {
                    background: #dc3545 !important;
                    color: white !important;
                    animation: pulse 1s infinite;
                }

                @keyframes pulse {
                    0% { opacity: 1; }
                    50% { opacity: 0.7; }
                    100% { opacity: 1; }
                }
            </style>
        `;

        document.head.insertAdjacentHTML('beforeend', styles);
    }

    setupEventListeners() {
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const attachFileBtn = document.getElementById('attachFileBtn');
        const fileInput = document.getElementById('fileInput');
        const voiceBtn = document.getElementById('voiceBtn');
        const suggestionBtns = document.querySelectorAll('.suggestion-btn');

        // Send message on Enter key
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Send button click
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

    addWelcomeMessage() {
        this.addMessage('ai', 'Olá! Sou seu assistente IA para o sistema Divino Lanches. Posso ajudar você a:\n\n• Criar e gerenciar produtos\n• Adicionar ingredientes e categorias\n• Visualizar pedidos e mesas\n• Processar arquivos e imagens\n\nComo posso ajudar hoje?');
    }

    addMessage(sender, content, attachments = []) {
        const messagesContainer = document.getElementById('aiChatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}`;

        let attachmentHtml = '';
        if (attachments.length > 0) {
            attachmentHtml = '<div class="message-attachments">';
            attachments.forEach(attachment => {
                attachmentHtml += `<span class="attachment-item">
                    <i class="fas fa-${this.getFileIcon(attachment.type)}"></i> 
                    ${attachment.name}
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

        this.messages.push({
            sender,
            content,
            attachments,
            timestamp: new Date()
        });
    }

    formatMessage(content) {
        // Convert line breaks to HTML
        content = content.replace(/\n/g, '<br>');
        
        // Format code blocks
        content = content.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Format bold text
        content = content.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        
        return content;
    }

    getFileIcon(type) {
        const icons = {
            'image': 'image',
            'pdf': 'file-pdf',
            'spreadsheet': 'file-excel',
            'csv': 'file-csv',
            'unknown': 'file'
        };
        return icons[type] || 'file';
    }

    async sendMessage() {
        const messageInput = document.getElementById('messageInput');
        const message = messageInput.value.trim();

        if (!message) return;

        // Add user message
        this.addMessage('user', message);
        messageInput.value = '';

        // Show typing indicator
        this.showTypingIndicator();

        try {
            const response = await fetch('mvc/ajax/ai_chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=send_message&message=${encodeURIComponent(message)}`
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
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message ai';

        messageDiv.innerHTML = `
            <div class="message-content">
                ${this.formatMessage(response.message)}
                <div class="confirmation-dialog">
                    <p><strong>Confirmação necessária:</strong></p>
                    <p>${response.message}</p>
                    <div class="confirmation-buttons">
                        <button class="btn btn-success btn-sm" onclick="aiChat.confirmOperation(true, ${JSON.stringify(response).replace(/"/g, '&quot;')})">
                            <i class="fas fa-check"></i> Confirmar
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="aiChat.confirmOperation(false, ${JSON.stringify(response).replace(/"/g, '&quot;')})">
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
                
                // Process file with AI
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
            
            this.addMessage('user', '🎤 Gravando... Clique novamente para parar.');

        } catch (error) {
            console.error('Error accessing microphone:', error);
            this.addMessage('ai', 'Erro ao acessar o microfone. Verifique as permissões.');
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
        
        // For demo purposes, we'll simulate audio processing
        // In a real implementation, you would send the audio to a speech-to-text service
        setTimeout(() => {
            this.addMessage('ai', 'Áudio processado! (Funcionalidade de reconhecimento de voz em desenvolvimento)');
        }, 2000);
    }

    showTypingIndicator() {
        const messagesContainer = document.getElementById('aiChatMessages');
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

    // Public method to add messages programmatically
    addSystemMessage(content) {
        this.addMessage('ai', content);
    }

    // Method to clear chat
    clearChat() {
        const messagesContainer = document.getElementById('aiChatMessages');
        messagesContainer.innerHTML = '';
        this.messages = [];
        this.addWelcomeMessage();
    }
}

// Initialize AI Chat when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if container exists
    if (document.getElementById('aiChatContainer')) {
        window.aiChat = new AIChat('aiChatContainer');
    }
});
