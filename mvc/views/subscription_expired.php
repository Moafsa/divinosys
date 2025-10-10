<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinatura Expirada - Divino Lanches</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .expired-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            padding: 60px;
            width: 100%;
            max-width: 600px;
            text-align: center;
        }
        
        .icon-expired {
            font-size: 100px;
            color: #f093fb;
            margin-bottom: 30px;
        }
        
        .btn-renew {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 15px 40px;
            font-size: 18px;
            font-weight: 600;
            color: white;
            transition: transform 0.2s;
        }
        
        .btn-renew:hover {
            transform: translateY(-3px);
            color: white;
        }
    </style>
</head>
<body>
    <div class="expired-container">
        <i class="fas fa-exclamation-circle icon-expired"></i>
        <h1 class="mb-3">Assinatura Expirada</h1>
        <p class="lead text-muted mb-4">
            Sua assinatura do Divino Lanches expirou. Para continuar usando o sistema, 
            renove sua assinatura ou entre em contato com o suporte.
        </p>
        
        <div class="alert alert-warning mb-4">
            <i class="fas fa-info-circle"></i> Seus dados estão seguros e serão mantidos por 30 dias.
        </div>
        
        <button class="btn btn-renew mb-3" onclick="renovarAssinatura()">
            <i class="fas fa-sync-alt"></i> Renovar Assinatura
        </button>
        
        <br>
        
        <a href="index.php?view=tenant_dashboard" class="btn btn-link">
            Ver Detalhes da Conta
        </a>
        
        <br>
        
        <a href="index.php?view=logout" class="btn btn-link text-muted">
            <i class="fas fa-sign-out-alt"></i> Sair
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function renovarAssinatura() {
            window.location.href = 'index.php?view=tenant_dashboard';
        }
    </script>
</body>
</html>

