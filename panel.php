<?php
/**
 * ====================================================================
 * Panel de Profesores y Administrador - Calificador PAA
 * Instituto Lumière | calificador.lumieresiga.com
 * ====================================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/calificador.php';

session_start();

// Manejo de Cerrar Sesión
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_token']);
    session_destroy();
    header('Location: panel.php');
    exit;
}

// Manejo de Inicio de Sesión por POST tradicional
$loginError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
    $password = trim($_POST['admin_password'] ?? '');
    if ($password === ADMIN_PASSWORD) {
        $_SESSION['admin_token'] = ADMIN_PASSWORD;
        header('Location: panel.php');
        exit;
    } else {
        $loginError = 'Contraseña incorrecta. Por favor verifica e intenta de nuevo.';
    }
}

$isLogged = isset($_SESSION['admin_token']) && ($_SESSION['admin_token'] === ADMIN_PASSWORD);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel de Resultados - Instituto Lumière</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #c01b2f;
            --primary-hover: #9e1324;
            --primary-subtle: rgba(192, 27, 47, 0.12);
            --bg: #090d16;
            --card-bg: #131b2e;
            --card-surface: #1a243b;
            --card-hover: #22304d;
            --border: #263352;
            --border-light: #33446b;
            --text: #f8fafc;
            --muted: #94a3b8;
            --success: #10b981;
            --success-subtle: rgba(16, 185, 129, 0.15);
            --danger: #ef4444;
            --danger-subtle: rgba(239, 68, 68, 0.15);
            --warning: #f59e0b;
            --warning-subtle: rgba(245, 158, 11, 0.15);
            --info: #3b82f6;
            --info-subtle: rgba(59, 130, 246, 0.15);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Barra de Navegación */
        .navbar {
            background: rgba(19, 27, 46, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #fff;
        }
        .nav-brand-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #c01b2f, #7f1d1d);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 12px rgba(192, 27, 47, 0.35);
        }
        .nav-title {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: -0.3px;
        }
        .nav-subtitle {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 600;
        }
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            font-family: inherit;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: var(--card-surface); color: var(--text); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--card-hover); border-color: var(--border-light); }
        .btn-success { background: var(--success); color: #064e3b; font-weight: 700; }
        .btn-success:hover { background: #059669; color: #fff; }
        .btn-danger { background: var(--danger-subtle); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); }
        .btn-danger:hover { background: var(--danger); color: #fff; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }

        /* Contenedor Principal */
        .main-container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 24px 20px 80px 20px;
        }

        /* Pantalla de Login */
        .login-wrap {
            min-height: calc(100vh - 80px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px 32px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            text-align: center;
        }
        .login-icon {
            width: 68px;
            height: 68px;
            background: var(--primary-subtle);
            border: 2px solid rgba(192, 27, 47, 0.3);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin: 0 auto 20px auto;
        }
        .login-card h2 {
            margin: 0 0 6px 0;
            font-size: 22px;
            font-weight: 800;
        }
        .login-card p {
            color: var(--muted);
            font-size: 14px;
            margin: 0 0 24px 0;
        }
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 8px;
        }
        .input-text {
            width: 100%;
            padding: 13px 16px;
            background: var(--card-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        .input-text:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(192, 27, 47, 0.2);
        }

        /* KPIs / Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s, border-color 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--border-light);
        }
        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }
        .stat-icon.blue { background: var(--info-subtle); color: #60a5fa; }
        .stat-icon.green { background: var(--success-subtle); color: #34d399; }
        .stat-icon.purple { background: rgba(168, 85, 247, 0.15); color: #c084fc; }
        .stat-icon.amber { background: var(--warning-subtle); color: #fbbf24; }
        .stat-info .stat-value {
            font-size: 26px;
            font-weight: 800;
            color: #fff;
            line-height: 1.1;
        }
        .stat-info .stat-label {
            font-size: 13px;
            color: var(--muted);
            margin-top: 4px;
            font-weight: 500;
        }

        /* Pestañas */
        .tabs-header {
            display: flex;
            gap: 10px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            padding-bottom: 4px;
        }
        .tab-btn {
            background: transparent;
            border: none;
            color: var(--muted);
            padding: 12px 18px;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            border-radius: 10px 10px 0 0;
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.2s;
        }
        .tab-btn:hover { color: #fff; }
        .tab-btn.active {
            color: #fff;
        }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary);
            border-radius: 3px 3px 0 0;
        }
        .tab-badge {
            background: var(--card-surface);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            color: var(--muted);
            border: 1px solid var(--border);
        }
        .tab-badge.alert {
            background: var(--danger);
            color: #fff;
            border: none;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }

        /* Barra de Filtros y Búsqueda */
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .search-box {
            position: relative;
            flex: 1;
            min-width: 260px;
            max-width: 420px;
        }
        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: #fff;
            font-size: 14px;
            font-family: inherit;
            outline: none;
        }
        .search-box input:focus { border-color: var(--border-light); }
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 14px;
        }
        .filter-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-btn {
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            transition: all 0.2s;
        }
        .filter-btn:hover { color: #fff; border-color: var(--border-light); }
        .filter-btn.active {
            background: var(--primary-subtle);
            border-color: rgba(192, 27, 47, 0.4);
            color: #f87171;
        }

        /* Tabla de Alumnos */
        .table-wrap {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        thead {
            background: var(--card-surface);
            border-bottom: 1px solid var(--border);
        }
        th {
            padding: 14px 18px;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        tbody tr {
            border-bottom: 1px solid rgba(38, 51, 82, 0.6);
            transition: background 0.15s;
        }
        tbody tr:hover {
            background: rgba(26, 36, 59, 0.5);
        }
        tbody tr:last-child { border-bottom: none; }
        td {
            padding: 16px 18px;
            font-size: 14px;
            vertical-align: middle;
        }

        .student-name {
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .career-chip {
            display: inline-block;
            font-size: 11px;
            color: var(--muted);
            margin-top: 3px;
        }
        .score-pill {
            display: inline-flex;
            align-items: baseline;
            gap: 4px;
            padding: 6px 12px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 16px;
        }
        .score-pill.high { background: var(--success-subtle); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .score-pill.mid { background: var(--warning-subtle); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .score-pill.low { background: var(--danger-subtle); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .score-pill .pts-text { font-size: 11px; font-weight: 600; opacity: 0.8; }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-locked { background: rgba(148, 163, 184, 0.12); color: #94a3b8; border: 1px solid var(--border); }
        .badge-request { background: var(--warning-subtle); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
        .badge-unlocked { background: var(--success-subtle); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-modified { background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); }

        .actions-cell {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-container {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 60px rgba(0,0,0,0.6);
            overflow: hidden;
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--card-surface);
        }
        .modal-header h3 { margin: 0; font-size: 18px; font-weight: 800; }
        .modal-close {
            background: transparent;
            border: none;
            color: var(--muted);
            font-size: 20px;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
        }
        .modal-close:hover { color: #fff; }
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        /* Editor de Hoja Base */
        .editor-section {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .editor-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .editor-title {
            font-size: 18px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .grid-bubbles {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
            gap: 10px;
            max-height: 480px;
            overflow-y: auto;
            padding: 10px 4px;
        }
        .q-item {
            background: var(--card-surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .q-num {
            font-weight: 700;
            font-size: 13px;
            color: #cbd5e1;
            width: 24px;
        }
        .q-bubbles {
            display: flex;
            gap: 4px;
        }
        .q-btn {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 1px solid var(--border-light);
            background: transparent;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
        }
        .q-btn:hover { border-color: #fff; color: #fff; }
        .q-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
            box-shadow: 0 0 8px rgba(192, 27, 47, 0.5);
        }

        /* Dropzone para Escaneo de Hoja Base */
        .scan-box {
            border: 2px dashed var(--border);
            border-radius: 14px;
            padding: 24px;
            text-align: center;
            background: var(--card-surface);
            cursor: pointer;
            transition: border-color 0.2s;
            margin-bottom: 20px;
        }
        .scan-box:hover { border-color: var(--primary); }
        .scan-icon { font-size: 36px; margin-bottom: 8px; }

        /* Toast Notifications */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--card-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 20px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            z-index: 1000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast.success { border-color: rgba(16, 185, 129, 0.5); }
        .toast.error { border-color: rgba(239, 68, 68, 0.5); }

        /* Loading Spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.2);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Comparador de Examen en Modal */
        .score-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .score-summary-box {
            background: var(--card-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            text-align: center;
        }
        .score-summary-box .val {
            font-size: 24px;
            font-weight: 800;
            color: #fff;
        }
        .score-summary-box .lbl {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }

        .answers-grid-modal {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(75px, 1fr));
            gap: 6px;
            max-height: 280px;
            overflow-y: auto;
            padding: 10px 4px;
            background: var(--card-surface);
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        .ans-pill {
            padding: 6px 8px;
            border-radius: 8px;
            font-size: 12px;
            text-align: center;
            font-weight: 700;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .ans-pill.correct { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
        .ans-pill.wrong { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }
        .ans-pill.omitted { background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid var(--border); }
        .ans-pill.modified { box-shadow: 0 0 0 2px #3b82f6; }
        .ans-pill .q-index { font-size: 10px; opacity: 0.75; }

        @media (max-width: 768px) {
            .navbar { padding: 12px 16px; }
            .main-container { padding: 16px 12px 60px 12px; }
            .table-wrap { overflow-x: auto; }
            .stat-card { padding: 16px; }
            .stat-info .stat-value { font-size: 22px; }
        }
    </style>
</head>
<body>

    <!-- Barra de Navegación -->
    <nav class="navbar">
        <a href="panel.php" class="nav-brand">
            <div class="nav-brand-icon">🏛️</div>
            <div>
                <div class="nav-title">Instituto Lumière</div>
                <div class="nav-subtitle">Panel de Resultados • resultados.lumieresiga.com</div>
            </div>
        </a>

        <?php if ($isLogged): ?>
        <div class="nav-actions">
            <button class="btn btn-secondary btn-sm" onclick="cargarDashboard(true)" title="Refrescar datos en vivo">
                <span id="refreshIcon">🔄</span> <span style="display:none; @media(min-width:600px){display:inline;}">Actualizar</span>
            </button>
            <a href="https://calificador.lumieresiga.com" target="_blank" class="btn btn-secondary btn-sm" title="Abrir portal de subida de exámenes">
                <span>🌐 Portal Alumnos (calificador)</span>
            </a>
            <a href="panel.php?logout=1" class="btn btn-danger btn-sm" title="Cerrar sesión">
                <span>Salir</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- CUERPO PRINCIPAL -->
    <?php if (!$isLogged): ?>
    <!-- 1. VISTA DE INICIO DE SESIÓN -->
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-icon">🔒</div>
            <h2>Acceso Docente</h2>
            <p>Ingresa la contraseña maestra para administrar las calificaciones y la Hoja Base.</p>

            <?php if ($loginError): ?>
            <div style="background:var(--danger-subtle); border:1px solid rgba(239,68,68,0.4); color:#fca5a5; padding:12px; border-radius:10px; margin-bottom:18px; font-size:13px; font-weight:600;">
                ⚠️ <?php echo htmlspecialchars($loginError); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="panel.php">
                <input type="hidden" name="login_action" value="1">
                <div class="form-group">
                    <label for="admin_password">Contraseña de Profesor</label>
                    <input type="password" id="admin_password" name="admin_password" class="input-text" placeholder="••••••••••••" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; padding:14px; font-size:15px; font-weight:700;">
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <!-- 2. VISTA DE ADMINISTRACIÓN Y CALIFICACIONES -->
    <div class="main-container">

        <!-- Banner de Solicitudes Pendientes (Alerta Automática) -->
        <div id="retryAlertBanner" style="display:none; background:var(--warning-subtle); border:1px solid rgba(245,158,11,0.5); padding:16px 20px; border-radius:14px; margin-bottom:24px; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="font-size:24px;">⚠️</span>
                <div>
                    <strong style="color:#fbbf24; font-size:15px;" id="retryAlertText">Hay alumnos con solicitud de reintento</strong>
                    <div style="font-size:13px; color:var(--muted);">Revisa la lista a continuación y autoriza el nuevo intento con un clic.</div>
                </div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="filtrarPorSolicitud()">Ver Solicitudes</button>
        </div>

        <!-- KPIs y Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">👥</div>
                <div class="stat-info">
                    <div class="stat-value" id="statTotalAlumnos">0</div>
                    <div class="stat-label">Alumnos Evaluados</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">📈</div>
                <div class="stat-info">
                    <div class="stat-value" id="statPromedioPAA">0 <span style="font-size:14px; font-weight:500; color:var(--muted);">/ 1600</span></div>
                    <div class="stat-label">Promedio General PAA</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">🎯</div>
                <div class="stat-info">
                    <div class="stat-value" id="statAprobados1000">0</div>
                    <div class="stat-label">Aprobados (≥ 1000 pts)</div>
                </div>
            </div>
            <div class="stat-card" id="statCardRetry" style="cursor:pointer;" onclick="filtrarPorSolicitud()">
                <div class="stat-icon amber">⚠️</div>
                <div class="stat-info">
                    <div class="stat-value" id="statSolicitudesReintento">0</div>
                    <div class="stat-label">Solicitudes de Reintento</div>
                </div>
            </div>
        </div>

        <!-- Pestañas de Navegación -->
        <div class="tabs-header">
            <button class="tab-btn active" id="tabBtnAlumnos" onclick="cambiarPestana('alumnos')">
                <span>📋 Evaluaciones de Alumnos</span>
                <span class="tab-badge" id="tabBadgeAlumnos">0</span>
                <span class="tab-badge alert" id="tabBadgeAlert" style="display:none;">!</span>
            </button>
            <button class="tab-btn" id="tabBtnBase" onclick="cambiarPestana('base')">
                <span>🎯 Hoja Base Oficial (Claves PAA)</span>
                <span class="tab-badge" id="tabBadgePreguntas">115 Qs</span>
            </button>
        </div>

        <!-- PESTAÑA 1: LISTA DE ALUMNOS -->
        <div id="pestanaAlumnos">
            <!-- Barra de Filtros y Búsqueda -->
            <div class="toolbar">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Buscar alumno por nombre o carrera..." oninput="aplicarFiltros()">
                </div>
                <div class="filter-group">
                    <button class="filter-btn active" data-filter="todos" onclick="setFiltro('todos', this)">Todos (<span id="countFiltroTodos">0</span>)</button>
                    <button class="filter-btn" data-filter="solicitudes" onclick="setFiltro('solicitudes', this)">⚠️ Solicitudes (<span id="countFiltroSolicitudes">0</span>)</button>
                    <button class="filter-btn" data-filter="aprobados" onclick="setFiltro('aprobados', this)">🟢 ≥ 1000 pts (<span id="countFiltroAprobados">0</span>)</button>
                    <button class="filter-btn" data-filter="modificados" onclick="setFiltro('modificados', this)">✏️ Corregidos Alumno</button>
                </div>
            </div>

            <!-- Tabla de Evaluaciones -->
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Alumno / Carrera</th>
                            <th>Puntaje PAA</th>
                            <th>Verbal / Mate</th>
                            <th>Aciertos</th>
                            <th>Auditoría</th>
                            <th>Estado / Permiso</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="alumnosTableBody">
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px; color:var(--muted);">
                                <div class="spinner" style="margin-bottom:12px;"></div>
                                <div>Cargando evaluaciones de alumnos...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PESTAÑA 2: HOJA BASE OFICIAL (CLAVES PAA) -->
        <div id="pestanaBase" style="display:none;">

            <!-- Escanear Hoja Base con Foto -->
            <div class="editor-section">
                <div class="editor-header">
                    <div class="editor-title">
                        <span>📷 Escaneo Rápido de Hoja Base Maestra con IA</span>
                    </div>
                </div>
                <p style="color:var(--muted); font-size:13px; margin:0 0 16px 0;">
                    Toma o sube una fotografía nítida de la hoja con las respuestas oficiales marcadas. La Inteligencia Artificial detectará todas las burbujas y actualizará el editor inferior automáticamente.
                </p>
                <div class="scan-box" style="padding: 24px 16px;">
                    <div class="scan-icon">📸</div>
                    <strong style="color:#fff; font-size:16px; display:block; margin-bottom:8px;">Captura o Sube la Hoja Base Maestra</strong>
                    <span style="color:var(--muted); font-size:13px; display:block; margin-bottom:16px;" id="scanStatusText">
                        Usa la cámara de tu celular, visor de cámara en vivo o selecciona un archivo
                    </span>

                    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                        <!-- Botón 1: Cámara Nativa (Celulares / Dispositivos) -->
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('fileHojaBaseCamara').click()" style="padding:10px 18px; font-size:14px;">
                            <span>📸 Tomar Foto con Cámara</span>
                        </button>

                        <!-- Botón 2: Cámara Web en Vivo (Modal con retícula) -->
                        <button type="button" class="btn btn-secondary" onclick="abrirCamaraEnVivo()" style="padding:10px 18px; font-size:14px; background:#1e293b; color:#fff; border:1px solid #334155;">
                            <span>📹 Visor de Cámara en Vivo</span>
                        </button>

                        <!-- Botón 3: Galería o Archivo -->
                        <button type="button" class="btn btn-outline" onclick="document.getElementById('fileHojaBase').click()" style="padding:10px 18px; font-size:14px; border:1px solid var(--border); background:var(--card-surface); color:var(--text);">
                            <span>📁 Subir desde Archivo</span>
                        </button>
                    </div>

                    <input type="file" id="fileHojaBaseCamara" accept="image/*" capture="environment" style="display:none;" onchange="escanearHojaBaseConIA(this)">
                    <input type="file" id="fileHojaBase" accept="image/*" style="display:none;" onchange="escanearHojaBaseConIA(this)">
                </div>
            </div>

            <!-- Editor Visual de Burbujas -->
            <div class="editor-section">
                <div class="editor-header">
                    <div>
                        <div class="editor-title">
                            <span>✏️ Editor Visual de Respuestas Oficiales</span>
                        </div>
                        <div style="color:var(--muted); font-size:12px; margin-top:4px;">
                            Haz clic en las burbujas para alternar la clave correcta de cada pregunta.
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="guardarHojaBaseVisual()" id="btnGuardarBase">
                        <span>💾 Guardar Hoja Base Oficial</span>
                    </button>
                </div>

                <!-- Sub-pestañas por sección -->
                <div style="display:flex; gap:8px; margin-bottom:16px;">
                    <button class="filter-btn active" id="btnSecLectura" onclick="cambiarSecClave('lectura')">Lectura (1 - 45)</button>
                    <button class="filter-btn" id="btnSecRedaccion" onclick="cambiarSecClave('redaccion')">Redacción (1 - 25)</button>
                    <button class="filter-btn" id="btnSecMate" onclick="cambiarSecClave('matematicas')">Matemáticas (1 - 45)</button>
                </div>

                <!-- Cuadrícula de Preguntas -->
                <div id="gridClavesContainer" class="grid-bubbles">
                    <!-- Generado dinámicamente con JS -->
                </div>
            </div>

        </div>

    </div>

    <!-- MODAL DE DETALLE DEL EXAMEN -->
    <div class="modal-overlay" id="modalDetalle">
        <div class="modal-container">
            <div class="modal-header">
                <div>
                    <h3 id="modalAlumnoNombre">Detalle de Examen</h3>
                    <div style="font-size:12px; color:var(--muted); margin-top:2px;" id="modalAlumnoMeta">Evaluación</div>
                </div>
                <button class="modal-close" onclick="cerrarModalDetalle()">✕</button>
            </div>
            <div class="modal-body" id="modalDetalleBody">
                <!-- Se llena con JS -->
            </div>
        </div>
    </div>

    <!-- Modal de Cámara en Vivo para Hoja Base -->
    <div id="modalCamaraEnVivo" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:9999; justify-content:center; align-items:center; padding:16px;">
        <div style="background:var(--card-bg); border:1px solid var(--border); border-radius:16px; max-width:550px; width:100%; overflow:hidden; position:relative; box-shadow:0 20px 40px rgba(0,0,0,0.6);">
            <div style="padding:16px 20px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                <strong style="color:#fff; font-size:16px;">📷 Captura de Hoja Base con Cámara</strong>
                <button type="button" onclick="cerrarCamaraEnVivo()" style="background:none; border:none; color:var(--muted); font-size:24px; cursor:pointer; line-height:1;">&times;</button>
            </div>
            <div style="position:relative; background:#000; width:100%; max-height:420px; overflow:hidden; display:flex; justify-content:center; align-items:center;">
                <video id="videoCamaraFeed" autoplay playsinline style="width:100%; max-height:420px; object-fit:contain;"></video>
                <div style="position:absolute; border:2px dashed rgba(255,255,255,0.7); width:85%; height:88%; pointer-events:none; border-radius:12px; box-shadow:0 0 0 9999px rgba(0,0,0,0.35);">
                    <div style="position:absolute; bottom:8px; left:0; width:100%; text-align:center; color:#fff; font-size:12px; text-shadow:0 1px 3px #000;">
                        Alinea la hoja completa dentro del recuadro
                    </div>
                </div>
            </div>
            <div style="padding:16px 20px; display:flex; justify-content:space-between; gap:12px; background:var(--card-surface);">
                <button type="button" class="btn btn-outline" onclick="cerrarCamaraEnVivo()" style="padding:10px 18px; color:var(--muted); border:1px solid var(--border); background:none;">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="capturarFotoCamara()" style="padding:10px 24px; font-weight:700;">
                    <span>📸 Capturar y Escanear</span>
                </button>
            </div>
        </div>
    </div>
    <canvas id="canvasCaptura" style="display:none;"></canvas>

    <!-- Toast Notification -->
    <div class="toast" id="toastNotification">
        <span id="toastIcon">✅</span>
        <span id="toastText">Mensaje</span>
    </div>

    <?php endif; ?>

    <!-- JAVASCRIPT DEL PANEL -->
    <?php if ($isLogged): ?>
    <script>
        // Variables de Estado
        let datosDashboard = {
            stats: {},
            alumnos: [],
            hoja_base: {}
        };
        let filtroActual = 'todos';
        let seccionClaveActual = 'lectura';
        let hojaBaseTrabajo = {};

        // Inicializar al cargar
        document.addEventListener('DOMContentLoaded', () => {
            cargarDashboard(false);
            // Polling en tiempo real cada 25 segundos
            setInterval(() => {
                cargarDashboard(true, true);
            }, 25000);
        });

        // 1. Cargar Dashboard desde API
        async function cargarDashboard(manual = false, silencioso = false) {
            const refreshIcon = document.getElementById('refreshIcon');
            if (manual && refreshIcon) refreshIcon.classList.add('spinner');

            try {
                const formData = new FormData();
                formData.append('accion', 'admin_get_dashboard');

                const resp = await fetch('api_calificar.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await resp.json();

                if (!res.exito) {
                    if (!silencioso) mostrarToast('⚠️ ' + (res.error || 'Error al cargar datos'), 'error');
                    return;
                }

                datosDashboard = res;
                hojaBaseTrabajo = JSON.parse(JSON.stringify(res.hoja_base || {}));

                renderStats(res.stats);
                aplicarFiltros();
                renderEditorClaves();

                if (manual && !silencioso) {
                    mostrarToast('Datos actualizados en tiempo real', 'success');
                }
            } catch (err) {
                console.error(err);
                if (!silencioso) mostrarToast('Error de conexión con el servidor', 'error');
            } finally {
                if (refreshIcon) refreshIcon.classList.remove('spinner');
            }
        }

        // 2. Renderizar Tarjetas de Estadísticas
        function renderStats(stats) {
            document.getElementById('statTotalAlumnos').textContent = stats.total_alumnos || 0;
            document.getElementById('statPromedioPAA').childNodes[0].textContent = (stats.promedio_paa || 0) + ' ';
            document.getElementById('statAprobados1000').textContent = stats.aprobados_1000 || 0;
            
            const solCount = stats.solicitudes_reintento || 0;
            document.getElementById('statSolicitudesReintento').textContent = solCount;

            const banner = document.getElementById('retryAlertBanner');
            const alertBadge = document.getElementById('tabBadgeAlert');
            if (solCount > 0) {
                banner.style.display = 'flex';
                document.getElementById('retryAlertText').textContent = `Hay ${solCount} alumno(s) con solicitud de reintento pendiente`;
                alertBadge.style.display = 'inline-block';
            } else {
                banner.style.display = 'none';
                alertBadge.style.display = 'none';
            }

            document.getElementById('countFiltroTodos').textContent = stats.total_alumnos || 0;
            document.getElementById('countFiltroSolicitudes').textContent = solCount;
            document.getElementById('countFiltroAprobados').textContent = stats.aprobados_1000 || 0;
            document.getElementById('tabBadgeAlumnos').textContent = stats.total_alumnos || 0;
        }

        // 3. Renderizar Tabla de Alumnos
        function aplicarFiltros() {
            const search = (document.getElementById('searchInput').value || '').toLowerCase().trim();
            const tbody = document.getElementById('alumnosTableBody');

            let filtrados = datosDashboard.alumnos || [];

            // Filtrado por botón de estado
            if (filtroActual === 'solicitudes') {
                filtrados = filtrados.filter(a => !!a.solicitud_reintento);
            } else if (filtroActual === 'aprobados') {
                filtrados = filtrados.filter(a => (a.ultima_evaluacion?.puntaje_paa_total || 0) >= 1000);
            } else if (filtroActual === 'modificados') {
                filtrados = filtrados.filter(a => !!a.ultima_evaluacion?.hubo_modificaciones);
            }

            // Filtrado por buscador de texto
            if (search.length > 0) {
                filtrados = filtrados.filter(a => {
                    const nom = (a.nombre || '').toLowerCase();
                    const car = (a.carrera || a.ultima_evaluacion?.carrera || '').toLowerCase();
                    return nom.includes(search) || car.includes(search);
                });
            }

            if (filtrados.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align:center; padding:50px 20px; color:var(--muted);">
                            <div style="font-size:32px; margin-bottom:8px;">🔍</div>
                            <strong style="color:#fff; font-size:15px; display:block;">No se encontraron alumnos</strong>
                            <span style="font-size:13px;">No hay evaluaciones que coincidan con los filtros seleccionados.</span>
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = filtrados.map(a => {
                const evalData = a.ultima_evaluacion || {};
                const puntajePAA = evalData.puntaje_paa_total || 0;
                let scoreClass = 'mid';
                if (puntajePAA >= 1000) scoreClass = 'high';
                else if (puntajePAA > 0 && puntajePAA < 800) scoreClass = 'low';

                // Estado de intentos y reintento
                let estadoBadge = '';
                let botonReintento = '';

                if (a.solicitud_reintento) {
                    const motivo = a.motivo_solicitud ? `"${escapeHtml(a.motivo_solicitud)}"` : 'Problema con la foto';
                    estadoBadge = `<span class="badge badge-request" title="${motivo}">⚠️ Pide Reintento</span>`;
                    botonReintento = `
                        <button class="btn btn-success btn-sm" onclick="liberarReintento('${escapeHtml(a.nombre)}')" title="Autorizar nuevo intento a este alumno">
                            🔓 Habilitar Reintento
                        </button>
                    `;
                } else if (a.permiso_reintento) {
                    estadoBadge = `<span class="badge badge-unlocked">🟢 Habilitado</span>`;
                } else {
                    estadoBadge = `<span class="badge badge-locked">🔒 1 intento usado</span>`;
                    botonReintento = `
                        <button class="btn btn-secondary btn-sm" onclick="liberarReintento('${escapeHtml(a.nombre)}')" title="Dar otro intento manual">
                            🔓 Reintento
                        </button>
                    `;
                }

                // Auditoría de modificaciones hechas por el alumno
                let auditaBadge = '<span style="color:var(--muted); font-size:12px;">✓ 100% IA</span>';
                if (evalData.hubo_modificaciones) {
                    const countMods = contarModificaciones(evalData.modificaciones_alumno);
                    auditaBadge = `<span class="badge badge-modified" title="El alumno rectificó respuestas antes de entregar">✏️ ${countMods} editada(s)</span>`;
                }

                return `
                    <tr>
                        <td>
                            <div class="student-name">
                                <span>${escapeHtml(a.nombre)}</span>
                            </div>
                            <div class="career-chip">
                                🎓 ${escapeHtml(a.carrera || evalData.carrera || 'Aspirante Lumière')}
                                ${evalData.fecha ? `• 📅 ${evalData.fecha}` : ''}
                            </div>
                        </td>
                        <td>
                            <div class="score-pill ${scoreClass}">
                                ${puntajePAA} <span class="pts-text">PAA</span>
                            </div>
                            ${evalData.puntaje_escala_100 ? `<div style="font-size:11px; color:var(--muted); margin-top:2px;">Escala: ${evalData.puntaje_escala_100}%</div>` : ''}
                        </td>
                        <td>
                            <div style="font-size:13px; font-weight:600; color:#fff;">
                                V: <span style="color:#60a5fa;">${evalData.puntaje_paa_verbal || 0}</span> | 
                                M: <span style="color:#f59e0b;">${evalData.puntaje_paa_mate || 0}</span>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:700; color:#fff; font-size:14px;">
                                ${evalData.total_aciertos ?? 0} <span style="font-size:11px; color:var(--muted); font-weight:500;">/ ${evalData.total_preguntas ?? 115}</span>
                            </div>
                        </td>
                        <td>${auditaBadge}</td>
                        <td>
                            ${estadoBadge}
                            ${a.motivo_solicitud ? `<div style="font-size:11px; color:#fbbf24; margin-top:3px; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(a.motivo_solicitud)}">"${escapeHtml(a.motivo_solicitud)}"</div>` : ''}
                        </td>
                        <td style="text-align:right;">
                            <div class="actions-cell" style="justify-content:flex-end;">
                                ${evalData.id ? `
                                    <button class="btn btn-secondary btn-sm" onclick="abrirModalDetalle('${escapeHtml(a.nombre)}')">
                                        👁️ Detalle
                                    </button>
                                ` : ''}
                                ${botonReintento}
                                <button class="btn btn-danger btn-sm" onclick="eliminarAlumno('${escapeHtml(a.nombre)}')" title="Eliminar registro">
                                    🗑️
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function contarModificaciones(mods) {
            if (!mods) return 0;
            let c = 0;
            ['lectura', 'redaccion', 'matematicas'].forEach(sec => {
                if (mods[sec]) c += Object.keys(mods[sec]).length;
            });
            return c;
        }

        // 4. Cambiar Filtros de Pestaña y Botones
        function setFiltro(tipo, btn) {
            filtroActual = tipo;
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            if (btn) btn.classList.add('active');
            aplicarFiltros();
        }

        function filtrarPorSolicitud() {
            cambiarPestana('alumnos');
            const btn = document.querySelector('[data-filter="solicitudes"]');
            setFiltro('solicitudes', btn);
        }

        function cambiarPestana(pestana) {
            const pestAlumnos = document.getElementById('pestanaAlumnos');
            const pestBase = document.getElementById('pestanaBase');
            const btnAlumnos = document.getElementById('tabBtnAlumnos');
            const btnBase = document.getElementById('tabBtnBase');

            if (pestana === 'alumnos') {
                pestAlumnos.style.display = 'block';
                pestBase.style.display = 'none';
                btnAlumnos.classList.add('active');
                btnBase.classList.remove('active');
            } else {
                pestAlumnos.style.display = 'none';
                pestBase.style.display = 'block';
                btnAlumnos.classList.remove('active');
                btnBase.classList.add('active');
                renderEditorClaves();
            }
        }

        // 5. Liberar Reintento de Alumno
        async function liberarReintento(nombre) {
            if (!confirm(`¿Autorizar un nuevo intento de examen para "${nombre}"? Podrá volver a subir su hoja inmediatamente.`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('accion', 'admin_liberar_reintento');
                formData.append('nombre', nombre);

                const resp = await fetch('api_calificar.php', { method: 'POST', body: formData });
                const res = await resp.json();

                if (res.exito) {
                    mostrarToast(`✅ Permiso habilitado para ${nombre}`, 'success');
                    cargarDashboard(true);
                } else {
                    mostrarToast('⚠️ ' + (res.error || 'No se pudo liberar reintento'), 'error');
                }
            } catch (err) {
                mostrarToast('Error de conexión', 'error');
            }
        }

        // 6. Eliminar Alumno
        async function eliminarAlumno(nombre) {
            if (!confirm(`¿Estás seguro de eliminar el registro completo y examen de "${nombre}"? Esta acción no se puede deshacer.`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('accion', 'admin_eliminar_alumno');
                formData.append('nombre', nombre);

                const resp = await fetch('api_calificar.php', { method: 'POST', body: formData });
                const res = await resp.json();

                if (res.exito) {
                    mostrarToast(`Registro de ${nombre} eliminado`, 'success');
                    cargarDashboard(true);
                } else {
                    mostrarToast('⚠️ ' + (res.error || 'No se pudo eliminar'), 'error');
                }
            } catch (err) {
                mostrarToast('Error de conexión', 'error');
            }
        }

        // 7. Modal Detalle de Examen
        function abrirModalDetalle(nombre) {
            const alumno = (datosDashboard.alumnos || []).find(a => a.nombre === nombre);
            if (!alumno || !alumno.ultima_evaluacion) return;

            const ev = alumno.ultima_evaluacion;
            document.getElementById('modalAlumnoNombre').textContent = alumno.nombre;
            document.getElementById('modalAlumnoMeta').textContent = `Carrera: ${alumno.carrera || ev.carrera || 'No especificada'} • Fecha: ${ev.fecha} • Intento #${ev.intento_numero || 1}`;

            const base = datosDashboard.hoja_base || {};
            const respAlumno = ev.respuestas_alumno || {};
            const mods = ev.modificaciones_alumno || {};

            let modsHtml = '';
            if (ev.hubo_modificaciones) {
                let itemsMods = [];
                ['lectura', 'redaccion', 'matematicas'].forEach(sec => {
                    if (mods[sec]) {
                        for (const [q, val] of Object.entries(mods[sec])) {
                            const secNom = sec.charAt(0).toUpperCase() + sec.slice(1);
                            itemsMods.push(`
                                <tr>
                                    <td><strong>${secNom} #${q}</strong></td>
                                    <td><span style="color:#f87171;">${val.leido_ia}</span></td>
                                    <td><span style="color:#34d399; font-weight:700;">${val.corregido_alumno}</span></td>
                                    <td><strong>${base[sec]?.[q] || '-'}</strong></td>
                                </tr>
                            `);
                        }
                    }
                });

                modsHtml = `
                    <div style="background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.3); border-radius:12px; padding:16px; margin-bottom:20px;">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                            <span style="font-size:18px;">✏️</span>
                            <strong style="color:#93c5fd;">Auditoría de Modificaciones del Alumno</strong>
                        </div>
                        <div style="font-size:12px; color:var(--muted); margin-bottom:12px;">
                            El alumno rectificó las siguientes preguntas en la pantalla de verificación previa:
                        </div>
                        <table style="width:100%; font-size:12px;">
                            <thead>
                                <tr style="color:var(--muted); border-bottom:1px solid var(--border);">
                                    <th style="padding:6px;">Pregunta</th>
                                    <th style="padding:6px;">Leído por IA</th>
                                    <th style="padding:6px;">Modificado por Alumno</th>
                                    <th style="padding:6px;">Clave Base</th>
                                </tr>
                            </thead>
                            <tbody>${itemsMods.join('')}</tbody>
                        </table>
                    </div>
                `;
            }

            // Generador de cuadrículas de respuestas por sección
            let seccionesHtml = '';
            const secInfo = [
                { id: 'lectura', nombre: 'Lectura (45 Qs)', total: 45 },
                { id: 'redaccion', nombre: 'Redacción (25 Qs)', total: 25 },
                { id: 'matematicas', nombre: 'Matemáticas (45 Qs)', total: 45 }
            ];

            secInfo.forEach(s => {
                let pills = [];
                const resSec = respAlumno[s.id] || {};
                const baseSec = base[s.id] || {};
                const modSec = mods[s.id] || {};

                for (let i = 1; i <= s.total; i++) {
                    const rAl = resSec[String(i)] || resSec[i] || '';
                    const rBase = baseSec[String(i)] || baseSec[i] || '';
                    const wasMod = !!modSec[String(i)] || !!modSec[i];

                    let pillClass = 'omitted';
                    if (rAl) {
                        pillClass = (rAl === rBase) ? 'correct' : 'wrong';
                    }
                    if (wasMod) pillClass += ' modified';

                    pills.push(`
                        <div class="ans-pill ${pillClass}" title="P#${i}: Alumno=${rAl || 'OMITIDA'}, Base=${rBase}${wasMod ? ' (Modificada por alumno)' : ''}">
                            <span class="q-index">#${i}</span>
                            <span>${rAl || '-'}</span>
                            <span style="font-size:9px; opacity:0.7;">✓${rBase || '-'}</span>
                        </div>
                    `);
                }

                seccionesHtml += `
                    <div style="margin-bottom:16px;">
                        <div style="font-weight:700; font-size:14px; margin-bottom:8px; display:flex; justify-content:space-between;">
                            <span>${s.nombre}</span>
                            <span style="color:var(--muted); font-size:12px;">Puntaje: ${ev.secciones?.[s.id]?.aciertos || 0} / ${s.total} aciertos</span>
                        </div>
                        <div class="answers-grid-modal">${pills.join('')}</div>
                    </div>
                `;
            });

            // Fotografía original si existe
            let fotoHtml = '';
            if (ev.foto_archivo) {
                fotoHtml = `
                    <div style="margin-top:20px; border-top:1px solid var(--border); padding-top:16px;">
                        <div style="font-weight:700; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between;">
                            <span>📸 Hoja del Examen Subida</span>
                            <a href="uploads/${encodeURIComponent(ev.foto_archivo)}" target="_blank" class="btn btn-secondary btn-sm">Abrir en Pestaña Nueva ↗</a>
                        </div>
                        <div style="text-align:center; background:#000; border-radius:12px; overflow:hidden; border:1px solid var(--border);">
                            <img src="uploads/${encodeURIComponent(ev.foto_archivo)}" style="max-width:100%; max-height:450px; object-fit:contain;" alt="Hoja del Alumno">
                        </div>
                    </div>
                `;
            }

            document.getElementById('modalDetalleBody').innerHTML = `
                <div class="score-summary-grid">
                    <div class="score-summary-box">
                        <div class="val" style="color:#34d399;">${ev.puntaje_paa_total || 0}</div>
                        <div class="lbl">Puntaje PAA Total</div>
                    </div>
                    <div class="score-summary-box">
                        <div class="val" style="color:#60a5fa;">${ev.puntaje_paa_verbal || 0}</div>
                        <div class="lbl">PAA Verbal</div>
                    </div>
                    <div class="score-summary-box">
                        <div class="val" style="color:#f59e0b;">${ev.puntaje_paa_mate || 0}</div>
                        <div class="lbl">PAA Matemáticas</div>
                    </div>
                    <div class="score-summary-box">
                        <div class="val">${ev.total_aciertos || 0} / ${ev.total_preguntas || 115}</div>
                        <div class="lbl">Aciertos Totales</div>
                    </div>
                </div>

                ${modsHtml}
                ${seccionesHtml}
                ${fotoHtml}
            `;

            document.getElementById('modalDetalle').classList.add('open');
        }

        function cerrarModalDetalle() {
            document.getElementById('modalDetalle').classList.remove('open');
        }

        // 8. Editor Visual de Claves Oficiales
        function cambiarSecClave(sec) {
            seccionClaveActual = sec;
            document.getElementById('btnSecLectura').classList.toggle('active', sec === 'lectura');
            document.getElementById('btnSecRedaccion').classList.toggle('active', sec === 'redaccion');
            document.getElementById('btnSecMate').classList.toggle('active', sec === 'matematicas');
            renderEditorClaves();
        }

        function renderEditorClaves() {
            const container = document.getElementById('gridClavesContainer');
            if (!container) return;

            const sec = seccionClaveActual;
            const total = (sec === 'redaccion') ? 25 : 45;
            const respuestas = hojaBaseTrabajo[sec] || {};

            let items = [];
            for (let i = 1; i <= total; i++) {
                const actual = respuestas[String(i)] || respuestas[i] || '';
                const opciones = ['A', 'B', 'C', 'D'];

                let btns = opciones.map(letra => {
                    const isActive = (actual === letra) ? 'active' : '';
                    return `<button type="button" class="q-btn ${isActive}" onclick="seleccionarClave('${sec}', ${i}, '${letra}')">${letra}</button>`;
                }).join('');

                items.push(`
                    <div class="q-item">
                        <span class="q-num">#${i}</span>
                        <div class="q-bubbles">${btns}</div>
                    </div>
                `);
            }

            container.innerHTML = items.join('');
        }

        function seleccionarClave(sec, num, letra) {
            if (!hojaBaseTrabajo[sec]) hojaBaseTrabajo[sec] = {};
            const actual = hojaBaseTrabajo[sec][String(num)];
            if (actual === letra) {
                delete hojaBaseTrabajo[sec][String(num)]; // Alternar para borrar
            } else {
                hojaBaseTrabajo[sec][String(num)] = letra;
            }
            renderEditorClaves();
        }

        async function guardarHojaBaseVisual() {
            const btn = document.getElementById('btnGuardarBase');
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner"></span> Guardando...`;

            try {
                const formData = new FormData();
                formData.append('accion', 'admin_guardar_hoja_base');
                formData.append('hoja_base', JSON.stringify(hojaBaseTrabajo));

                const resp = await fetch('api_calificar.php', { method: 'POST', body: formData });
                const res = await resp.json();

                if (res.exito) {
                    mostrarToast('✅ Hoja Base guardada y actualizada con éxito', 'success');
                    datosDashboard.hoja_base = res.hoja_base;
                } else {
                    mostrarToast('⚠️ ' + (res.error || 'Error al guardar'), 'error');
                }
            } catch (err) {
                mostrarToast('Error de conexión', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<span>💾 Guardar Hoja Base Oficial</span>`;
            }
        }

        // 9. Escanear Hoja Base con IA
        async function procesarArchivoHojaBase(archivo) {
            if (!archivo) return;
            const statusText = document.getElementById('scanStatusText');

            statusText.innerHTML = `<span class="spinner"></span> Analizando Hoja Base con Inteligencia Artificial (Gemini)...`;

            try {
                const formData = new FormData();
                formData.append('accion', 'admin_escanear_hoja_base');
                formData.append('foto', archivo);

                const resp = await fetch('api_calificar.php', { method: 'POST', body: formData });
                const res = await resp.json();

                if (res.exito) {
                    hojaBaseTrabajo = res.hoja_base;
                    datosDashboard.hoja_base = res.hoja_base;
                    renderEditorClaves();
                    mostrarToast('✅ ¡Hoja Base escaneada y cargada al editor con éxito!', 'success');
                    statusText.textContent = `Último escaneo completado: ${archivo.name || 'Captura de cámara'}`;
                } else {
                    mostrarToast('⚠️ ' + (res.error || 'No se pudo escanear la hoja'), 'error');
                    statusText.textContent = 'Error en escaneo. Puedes intentarlo nuevamente.';
                }
            } catch (err) {
                mostrarToast('Error al conectar con la IA', 'error');
                statusText.textContent = 'Error de conexión.';
            }
        }

        async function escanearHojaBaseConIA(input) {
            if (!input.files || !input.files[0]) return;
            const archivo = input.files[0];
            await procesarArchivoHojaBase(archivo);
            input.value = '';
        }

        let camaraStream = null;

        async function abrirCamaraEnVivo() {
            const modal = document.getElementById('modalCamaraEnVivo');
            const video = document.getElementById('videoCamaraFeed');
            modal.style.display = 'flex';

            try {
                camaraStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1920 },
                        height: { ideal: 1080 }
                    },
                    audio: false
                });
                video.srcObject = camaraStream;
            } catch (err) {
                console.error("Error al acceder a la cámara:", err);
                cerrarCamaraEnVivo();
                mostrarToast('Iniciando cámara nativa del dispositivo...', 'info');
                document.getElementById('fileHojaBaseCamara').click();
            }
        }

        function cerrarCamaraEnVivo() {
            const modal = document.getElementById('modalCamaraEnVivo');
            const video = document.getElementById('videoCamaraFeed');
            if (camaraStream) {
                camaraStream.getTracks().forEach(track => track.stop());
                camaraStream = null;
            }
            if (video) video.srcObject = null;
            modal.style.display = 'none';
        }

        function capturarFotoCamara() {
            const video = document.getElementById('videoCamaraFeed');
            const canvas = document.getElementById('canvasCaptura');
            if (!video.videoWidth) {
                mostrarToast('Esperando señal de video...', 'error');
                return;
            }

            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            cerrarCamaraEnVivo();

            canvas.toBlob(async (blob) => {
                if (blob) {
                    const archivo = new File([blob], "captura_hoja_base.jpg", { type: "image/jpeg" });
                    await procesarArchivoHojaBase(archivo);
                }
            }, 'image/jpeg', 0.95);
        }

        // 10. Utilidades
        function mostrarToast(texto, tipo = 'info') {
            const toast = document.getElementById('toastNotification');
            const toastText = document.getElementById('toastText');
            const toastIcon = document.getElementById('toastIcon');

            toastText.textContent = texto;
            toast.className = `toast show ${tipo}`;
            toastIcon.textContent = (tipo === 'success') ? '✅' : (tipo === 'error' ? '❌' : 'ℹ️');

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3500);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
    </script>
    <?php endif; ?>

</body>
</html>
