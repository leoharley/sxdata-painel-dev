<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SXData Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #23345F 0%, #1a2847 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
        }
        
        .login-header {
            background: linear-gradient(135deg, #8fae5d, #a8c46a);
            padding: 1.2rem;
            text-align: center;
            color: white;
        }
        
        .login-header h3 {
            margin: 0;
            font-weight: 600;
        }
        
        .login-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 1.2rem;
        }
        
        .form-control {
            border-radius: 0.5rem;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #8fae5d;
            box-shadow: 0 0 0 0.2rem rgba(143, 174, 93, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, #8fae5d, #a8c46a);
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: transform 0.2s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-1px);
            color: white;
        }
        
        .input-group-text {
            background: transparent;
            border: 2px solid #e9ecef;
            border-right: none;
            color: #6c757d;
        }
        
        .form-control.with-icon {
            border-left: none;
        }
        
        .alert {
            border-radius: 0.5rem;
            border: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="<?php echo img_url('logo_azul_com_branco.png'); ?>" alt="Imagem" width="45%" height="auto">
                <p><small>Seja bem-vindo(a) ao painel de gestão do SXData.</small></p>                
            </div>
            
            <div class="login-body">
                <span style="text-align:center"><p>Por favor, faça seu login abaixo:</p></span>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($info)): ?>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <?= html_escape($info) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($etapa_2fa)): ?>
                <?= form_open('auth/dois_fatores') ?>
                    <p class="small text-muted">Abra o aplicativo autenticador do seu celular e digite o código de 6 dígitos da conta <strong>SXData</strong>.</p>
                    <div class="mb-4">
                        <label for="codigo" class="form-label">Código de verificação</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
                            <input type="text" class="form-control with-icon" id="codigo" name="codigo" inputmode="numeric"
                                   autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="000000" required autofocus>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-login"><i class="fas fa-check me-2"></i>Confirmar</button>
                    <div class="text-center mt-3"><a href="<?= site_url('auth/logout') ?>" class="small">Voltar ao login</a></div>
                <?= form_close() ?>
                <?php else: ?>
                <?= form_open('auth/login') ?>
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuário</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control with-icon" id="username" name="username" 
                                   placeholder="Digite seu usuário" required value="<?= set_value('username') ?>">
                        </div>
                        <?= form_error('username', '<small class="text-danger">', '</small>') ?>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Senha</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control with-icon" id="password" name="password" 
                                   placeholder="Digite sua senha" required>
                        </div>
                        <?= form_error('password', '<small class="text-danger">', '</small>') ?>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Entrar
                    </button>
                <?= form_close() ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>