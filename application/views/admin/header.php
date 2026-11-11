<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'SXData Admin' ?></title>
    
    <!-- Boostrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- jQuery (antes de qualquer script inline) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #8fae5d;
            --secondary-color: #23345F;
            --bg-light: #f8f9fa;
            --text-muted: #6c757d;
        }
        
        body {
            background-color: var(--bg-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #1a2847 100%);
            min-height: 100vh;
            width: 250px;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar.collapsed {
            transform: translateX(-100%);
        }
        
        .sidebar .logo {
            padding-top: 1rem;
            padding-bottom: 0.6rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        
        .sidebar .logo h4 {
            color: white;
            margin: 0;
            font-weight: 600;
        }
        
        .sidebar .logo small {
            color: var(--primary-color);
            font-size: 0.75rem;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 3px solid var(--primary-color);
        }
        
        .sidebar-nav .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
        }
        
        .main-content {
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        .navbar {
            background: white !important;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: none;
        }
        
        .navbar-brand {
            color: var(--secondary-color) !important;
            font-weight: 600;
        }
        
        .content-wrapper {
            padding: 2rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .stat-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin: 0;
        }
        
        .stat-card .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin: 0;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #7a9850;
            border-color: #7a9850;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--secondary-color);
        }
        
        .badge-success {
            background-color: var(--primary-color) !important;
        }
        
        .alert {
            border: none;
            border-radius: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }        
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <img src="<?php echo img_url('logo_branco.png'); ?>" alt="Imagem" width="48%" height="auto"><br/>
            <small>Sistema de Coleta</small>
        </div>
        
        <ul class="sidebar-nav nav flex-column">
            <?php $user_role = $this->session->userdata('admin_role'); ?>
            <?php if ($user_role === 'cliente'): ?>
            <li class="nav-item">
                <a class="nav-link <?= $this->uri->segment(1) == 'client' ? 'active' : '' ?>" href="<?= base_url('client/dashboard') ?>">
                    <i class="fas fa-chart-pie"></i>
                    Painel de Análises
                </a>
            </li>
            <?php else: ?>
            <li class="nav-item">
                <a class="nav-link <?= $this->uri->segment(1) == 'dashboard' ? 'active' : '' ?>" href="<?= base_url('dashboard') ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $this->uri->segment(1) == 'questionnaires' ? 'active' : '' ?>" href="<?= base_url('projects') ?>">
                    <i class="fas fa-project-diagram"></i>
                    Projetos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $this->uri->segment(1) == 'questionnaires' ? 'active' : '' ?>" href="<?= base_url('questionnaires') ?>">
                    <i class="fas fa-clipboard-list"></i>
                    Questionários
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $this->uri->segment(1) == 'responses' ? 'active' : '' ?>" href="<?= base_url('responses') ?>">
                    <i class="fas fa-chart-bar"></i>
                    Respostas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $this->uri->segment(1) == 'users' ? 'active' : '' ?>" href="<?= base_url('users') ?>">
                    <i class="fas fa-users"></i>
                    Usuários
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $this->uri->segment(1) == 'reports' ? 'active' : '' ?>" href="<?= base_url('reports') ?>">
                    <i class="fas fa-file-alt"></i>
                    Relatórios e Análises
                </a>
            </li>
            <?php if (in_array($user_role, ['administrador', 'supervisor'], true)): ?>
            <li class="nav-item">
                <a class="nav-link <?= $this->uri->segment(1) == 'relatorios-automatizados' ? 'active' : '' ?>" href="<?= base_url('relatorios-automatizados') ?>">
                    <i class="fas fa-globe-americas"></i>
                    Relatórios Automatizados
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $this->uri->segment(1) == 'mapa-integrado' ? 'active' : '' ?>" href="<?= base_url('mapa-integrado') ?>">
                    <i class="fas fa-map-marked-alt"></i>
                    Mapa integrado
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?= $this->uri->segment(1) == 'ai' ? 'active' : '' ?>" href="<?= base_url('ai') ?>">
                    <i class="fas fa-brain"></i>
                    Inteligência Artificial
                </a>
            </li>
            <?php if ($user_role === 'administrador'): ?>
            <li class="nav-item">
                <a class="nav-link <?= $this->uri->segment(1) == 'settings' ? 'active' : '' ?>" href="<?= base_url('settings') ?>">
                    <i class="fas fa-cog"></i>
                    Configurações
                </a>
            </li>
            <?php endif; ?>
            <?php endif; ?>
            <li class="nav-item mt-auto">
                <a class="nav-link" href="<?= base_url('auth/logout') ?>">
                    <i class="fas fa-sign-out-alt"></i>
                    Sair
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid">
                <button class="btn btn-outline-secondary d-md-none" type="button" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="navbar-nav ms-auto">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i>
                            <?= $this->session->userdata('admin_name') ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li style="display:none"><a class="dropdown-item" href="<?= base_url('profile') ?>">Perfil</a></li>
                            <?php if ($this->session->userdata('admin_role') === 'administrador'): ?>
                            <li><a class="dropdown-item" href="<?= base_url('settings') ?>">Configurações</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= base_url('auth/logout') ?>">Sair</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <?php if ($this->session->flashdata('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $this->session->flashdata('success') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($this->session->flashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $this->session->flashdata('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>