<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinatura Expirada - Divino Lanches</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .expired-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .expired-icon {
            font-size: 80px;
            color: #e53e3e;
            margin-bottom: 20px;
        }
        .expired-title {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
        }
        .expired-message {
            font-size: 18px;
            color: #718096;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .btn-renew {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 15px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }
        .btn-renew:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .btn-secondary {
            background: #e2e8f0;
            border: none;
            color: #4a5568;
            padding: 15px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }
        .btn-secondary:hover {
            background: #cbd5e0;
            color: #4a5568;
        }
        .features-list {
            text-align: left;
            margin: 30px 0;
            padding: 20px;
            background: #f7fafc;
            border-radius: 10px;
        }
        .features-list h5 {
            color: #2d3748;
            margin-bottom: 15px;
        }
        .features-list ul {
            list-style: none;
            padding: 0;
        }
        .features-list li {
            padding: 8px 0;
            color: #4a5568;
        }
        .features-list li i {
            color: #48bb78;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="expired-container">
        <div class="expired-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        
        <h1 class="expired-title">Assinatura Expirada</h1>
        
        <p class="expired-message">
            Sua assinatura expirou e o acesso ao sistema foi suspenso. 
            Para continuar usando o Divino Lanches, renove sua assinatura.
        </p>
        
        <div class="features-list">
            <h5><i class="fas fa-star"></i> Benefícios da Assinatura</h5>
            <ul>
                <li><i class="fas fa-check"></i> Acesso completo ao sistema</li>
                <li><i class="fas fa-check"></i> Gestão de pedidos e mesas</li>
                <li><i class="fas fa-check"></i> Relatórios avançados</li>
                <li><i class="fas fa-check"></i> Suporte técnico</li>
                <li><i class="fas fa-check"></i> Backup automático</li>
            </ul>
        </div>
        
        <div class="d-flex flex-column flex-md-row justify-content-center">
            <a href="index.php?view=planos" class="btn-renew">
                <i class="fas fa-credit-card"></i> Renovar Assinatura
            </a>
            <a href="index.php?view=login" class="btn-secondary">
                <i class="fas fa-home"></i> Voltar ao Login
            </a>
        </div>
        
        <div class="mt-4">
            <small class="text-muted">
                Precisa de ajuda? Entre em contato conosco: 
                <a href="mailto:suporte@divinolanches.com" class="text-decoration-none">suporte@divinolanches.com</a>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>