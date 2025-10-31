<?php
$config = \System\Config::getInstance();
$session = \System\Session::getInstance();
$router = \System\Router::getInstance();
$db = \System\Database::getInstance();

// Ensure tenant and filial context
$context = \System\TenantHelper::ensureTenantContext();
$tenant = $context['tenant'];
$filial = $context['filial'];
$user = $session->getUser();

// Debug: Se não tem tenant/filial, usar valores padrão
if (!$tenant) {
    $tenant = $db->fetch("SELECT * FROM tenants WHERE id = 1");
    if ($tenant) {
        $session->setTenant($tenant);
    }
}

if (!$filial) {
    $filial = $db->fetch("SELECT * FROM filiais WHERE id = 1");
    if ($filial) {
        $session->setFilial($filial);
    }
}

// Get system statistics for AI context
$stats = [
    'total_produtos' => 0,
    'total_categorias' => 0,
    'total_ingredientes' => 0,
    'pedidos_pendentes' => 0,
    'mesas_ocupadas' => 0
];

if ($tenant && $filial) {
    $stats = [
        'total_produtos' => $db->count('produtos', 'tenant_id = ? AND filial_id = ?', [$tenant['id'], $filial['id']]),
        'total_categorias' => $db->count('categorias', 'tenant_id = ? AND filial_id = ?', [$tenant['id'], $filial['id']]),
        'total_ingredientes' => $db->count('ingredientes', 'tenant_id = ? AND filial_id = ?', [$tenant['id'], $filial['id']]),
        'pedidos_pendentes' => $db->count('pedido', 'tenant_id = ? AND filial_id = ? AND status = ?', [$tenant['id'], $filial['id'], 'Pendente']),
        'mesas_ocupadas' => $db->fetch(
            "SELECT COUNT(DISTINCT p.idmesa) as count 
             FROM pedido p 
             WHERE p.tenant_id = ? AND p.filial_id = ? 
             AND p.status IN ('Pendente', 'Preparando', 'Pronto', 'Entregue')
             AND p.delivery = false",
            [$tenant['id'], $filial['id']]
        )['count'] ?? 0
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistente IA - <?php echo $config->get('app.name'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>;
            --primary-light: <?php echo $tenant['cor_primaria'] ?? '#007bff'; ?>20;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .ai-chat-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .ai-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .ai-avatar-large {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), #6c757d);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-right: 1.5rem;
        }
        
        .ai-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .ai-chat-main {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            height: 600px;
            display: flex;
            flex-direction: column;
        }
        
        .ai-chat-header {
            background: linear-gradient(135deg, var(--primary-color), #6c757d);
            color: white;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .ai-info {
            display: flex;
            align-items: center;
        }
        
        .ai-avatar {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .ai-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .ai-chat-messages {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            background: #f8f9fa;
        }
        
        .message {
            margin-bottom: 1rem;
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
            max-width: 70%;
            padding: 1rem 1.5rem;
            border-radius: 20px;
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .message.user .message-content {
            background: var(--primary-color);
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message.ai .message-content {
            background: white;
            color: #333;
            border: 1px solid #e9ecef;
            border-bottom-left-radius: 5px;
        }
        
        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 0.5rem;
        }
        
        .ai-chat-input-container {
            padding: 1.5rem;
            background: white;
            border-top: 1px solid #e9ecef;
        }
        
        .ai-chat-input-wrapper {
            margin-bottom: 1rem;
        }
        
        .ai-chat-suggestions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .suggestion-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .suggestion-btn {
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            white-space: nowrap;
            transition: all 0.3s ease;
        }
        
        .suggestion-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .typing-indicator {
            display: none;
            align-items: center;
            gap: 1rem;
            color: #6c757d;
            font-style: italic;
        }
        
        .typing-dots {
            display: flex;
            gap: 0.25rem;
        }
        
        .typing-dot {
            width: 8px;
            height: 8px;
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
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .confirmation-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
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
        
        .back-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            background: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            transform: scale(1.1);
            color: white;
            background: var(--primary-color);
        }
    </style>
</head>
<body>
    <a href="<?php echo $router->url('dashboard'); ?>" class="back-btn" title="Voltar ao Dashboard">
        <i class="fas fa-arrow-left"></i>
    </a>

    <div class="ai-chat-container">
        <!-- AI Header -->
        <div class="ai-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="ai-avatar-large">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div>
                            <h2 class="mb-2">Assistente IA Divino Lanches</h2>
                            <p class="mb-0 text-muted">Sua inteligência artificial para gerenciar produtos, pedidos e muito mais!</p>
                            <small class="text-muted">Bem-vindo, <?php echo htmlspecialchars($user['login'] ?? 'admin'); ?>!</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-end">
                        <div class="fw-bold"><?php echo htmlspecialchars($filial['nome'] ?? 'Filial Principal'); ?></div>
                        <small class="text-muted"><?php echo date('d/m/Y H:i'); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- System Stats -->
            <div class="ai-stats">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(45deg, #28a745, #20c997);">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_produtos']; ?></div>
                    <div class="stat-label">Produtos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(45deg, #007bff, #6610f2);">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_categorias']; ?></div>
                    <div class="stat-label">Categorias</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(45deg, #ffc107, #fd7e14);">
                        <i class="fas fa-leaf"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['total_ingredientes']; ?></div>
                    <div class="stat-label">Ingredientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(45deg, #dc3545, #e83e8c);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['pedidos_pendentes']; ?></div>
                    <div class="stat-label">Pedidos Pendentes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(45deg, #6f42c1, #e83e8c);">
                        <i class="fas fa-table"></i>
                    </div>
                    <div class="stat-number"><?php echo $stats['mesas_ocupadas']; ?></div>
                    <div class="stat-label">Mesas Ocupadas</div>
                </div>
            </div>
        </div>

        <!-- AI Chat Interface -->
        <div class="ai-chat-main">
            <div class="ai-chat-header">
                <div class="ai-info">
                    <div class="ai-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Assistente IA</h5>
                        <small>Divino Lanches - Sempre online</small>
                    </div>
                </div>
                <div class="ai-actions">
                    <button class="btn btn-sm btn-outline-light" id="clearChatBtn" title="Limpar conversa">
                        <i class="fas fa-trash"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-light" id="helpBtn" title="Ajuda">
                        <i class="fas fa-question"></i>
                    </button>
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
                    <small class="text-muted">Comandos rápidos:</small>
                    <div class="suggestion-buttons">
                        <button class="btn btn-outline-secondary suggestion-btn" data-suggestion="Criar produto X-Burger com hambúrguer, queijo e alface">
                            <i class="fas fa-plus"></i> Criar produto
                        </button>
                        <button class="btn btn-outline-secondary suggestion-btn" data-suggestion="Listar todos os produtos">
                            <i class="fas fa-list"></i> Listar produtos
                        </button>
                        <button class="btn btn-outline-secondary suggestion-btn" data-suggestion="Adicionar ingrediente Bacon com preço R$ 3,00">
                            <i class="fas fa-leaf"></i> Adicionar ingrediente
                        </button>
                        <button class="btn btn-outline-secondary suggestion-btn" data-suggestion="Ver pedidos pendentes">
                            <i class="fas fa-shopping-cart"></i> Ver pedidos
                        </button>
                        <button class="btn btn-outline-secondary suggestion-btn" data-suggestion="Criar categoria Bebidas">
                            <i class="fas fa-tags"></i> Criar categoria
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        class AIChat {
            constructor() {
                this.messages = [];
                this.isRecording = false;
                this.mediaRecorder = null;
                this.audioChunks = [];
                this.init();
            }

            init() {
                this.setupEventListeners();
                this.addWelcomeMessage();
            }

            setupEventListeners() {
                const messageInput = document.getElementById('messageInput');
                const sendBtn = document.getElementById('sendBtn');
                const attachFileBtn = document.getElementById('attachFileBtn');
                const fileInput = document.getElementById('fileInput');
                const voiceBtn = document.getElementById('voiceBtn');
                const clearBtn = document.getElementById('clearChatBtn');
                const helpBtn = document.getElementById('helpBtn');
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

                // Clear chat
                clearBtn.addEventListener('click', () => {
                    this.clearChat();
                });

                // Help
                helpBtn.addEventListener('click', () => {
                    this.showHelp();
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
                this.addMessage('ai', `Olá! Sou seu assistente IA para o sistema Divino Lanches. 

Posso ajudar você a:

🎯 **Gerenciar Produtos**
• Criar novos produtos com preços e descrições
• Editar produtos existentes
• Organizar por categorias

🧩 **Ingredientes & Categorias**
• Adicionar novos ingredientes
• Criar categorias para organizar produtos
• Definir preços adicionais para ingredientes

📋 **Pedidos & Mesas**
• Visualizar pedidos pendentes
• Gerenciar status de mesas
• Criar novos pedidos

📁 **Processar Arquivos**
• Analisar imagens de produtos
• Processar planilhas com dados
• Extrair informações de PDFs

Como posso ajudar você hoje? Experimente um dos comandos rápidos abaixo ou me diga o que precisa!`);
            }

            addMessage(sender, content, attachments = []) {
                const messagesContainer = document.getElementById('aiChatMessages');
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${sender}`;

                let attachmentHtml = '';
                if (attachments.length > 0) {
                    attachmentHtml = '<div class="message-attachments" style="margin-top: 10px;">';
                    attachments.forEach(attachment => {
                        attachmentHtml += `<span class="attachment-item" style="display: inline-block; padding: 5px 10px; background: rgba(0,123,255,0.1); border-radius: 15px; font-size: 12px; margin-right: 5px;">
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
                content = content.replace(/`([^`]+)`/g, '<code style="background: #f1f3f4; padding: 2px 6px; border-radius: 4px; font-size: 13px;">$1</code>');
                
                // Format bold text
                content = content.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
                
                return content;
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
                        // Refresh stats if needed
                        setTimeout(() => location.reload(), 2000);
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
                
                // Simulate audio processing
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

            clearChat() {
                Swal.fire({
                    title: 'Limpar conversa?',
                    text: 'Tem certeza que deseja apagar todo o histórico da conversa?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sim, limpar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const messagesContainer = document.getElementById('aiChatMessages');
                        messagesContainer.innerHTML = '';
                        this.messages = [];
                        this.addWelcomeMessage();
                    }
                });
            }

            showHelp() {
                Swal.fire({
                    title: 'Como usar o Assistente IA',
                    html: `
                        <div class="text-start">
                            <h6><i class="fas fa-robot text-primary"></i> Comandos de Produtos</h6>
                            <ul>
                                <li><code>Criar produto X-Burger</code> - Cria um novo produto</li>
                                <li><code>Listar produtos</code> - Mostra todos os produtos</li>
                                <li><code>Editar produto [nome]</code> - Edita um produto</li>
                                <li><code>Excluir produto [nome]</code> - Remove um produto</li>
                            </ul>
                            
                            <h6><i class="fas fa-leaf text-success"></i> Comandos de Ingredientes</h6>
                            <ul>
                                <li><code>Adicionar ingrediente Bacon</code> - Adiciona novo ingrediente</li>
                                <li><code>Listar ingredientes</code> - Mostra todos os ingredientes</li>
                            </ul>
                            
                            <h6><i class="fas fa-tags text-warning"></i> Comandos de Categorias</h6>
                            <ul>
                                <li><code>Criar categoria Bebidas</code> - Cria nova categoria</li>
                                <li><code>Listar categorias</code> - Mostra todas as categorias</li>
                            </ul>
                            
                            <h6><i class="fas fa-shopping-cart text-info"></i> Comandos de Pedidos</h6>
                            <ul>
                                <li><code>Ver pedidos pendentes</code> - Mostra pedidos em andamento</li>
                                <li><code>Ver mesas ocupadas</code> - Mostra status das mesas</li>
                            </ul>
                            
                            <h6><i class="fas fa-file text-secondary"></i> Upload de Arquivos</h6>
                            <ul>
                                <li><strong>Imagens:</strong> Analisa produtos em fotos</li>
                                <li><strong>PDFs:</strong> Extrai informações de documentos</li>
                                <li><strong>Planilhas:</strong> Processa dados de CSV/Excel</li>
                            </ul>
                        </div>
                    `,
                    width: '600px',
                    confirmButtonText: 'Entendi!'
                });
            }
        }

        // Initialize AI Chat when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            window.aiChat = new AIChat();
        });
    </script>
</body>
</html>
