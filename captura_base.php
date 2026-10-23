<?php
/**
 * ====================================================================
 * Captura Manual y Dictado de Hoja Base (Claves PAA) - Instituto Lumière
 * calificador.lumieresocial.com / resultados.lumieresocial.com
 * ====================================================================
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/calificador.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autenticación de Administrador Docente
$errorLogin = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['login_password'])) {
    if (trim($_POST['login_password']) === ADMIN_PASSWORD) {
        $_SESSION['admin_token'] = ADMIN_PASSWORD;
    } else {
        $errorLogin = 'Contraseña docente incorrecta.';
    }
}

// Token por URL (ej: si se abre desde el panel autenticado)
if (!empty($_GET['token']) && $_GET['token'] === ADMIN_PASSWORD) {
    $_SESSION['admin_token'] = ADMIN_PASSWORD;
}

$isLogged = isset($_SESSION['admin_token']) && ($_SESSION['admin_token'] === ADMIN_PASSWORD);
$hojaBaseActual = cargar_hoja_base();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Captura y Dictado de Hoja Base - Instituto Lumière</title>
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
            --border: #263352;
            --text: #f8fafc;
            --muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --accent: #38bdf8;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 16px 12px 60px 12px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            max-width: 720px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header-badge {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.35);
            color: #fbbf24;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0 0 6px 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #ffffff;
        }
        .header p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px 18px;
            margin-bottom: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.35);
        }

        /* Dictado por Voz */
        .btn-dictado {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            border: none;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.35);
            transition: all 0.2s ease;
        }
        .btn-dictado:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(2, 132, 199, 0.45);
        }
        .btn-dictado.activo {
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            animation: pulse-red 1.5s infinite;
        }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
        .pulse-dot {
            width: 10px;
            height: 10px;
            background: #ef4444;
            border-radius: 50%;
            display: inline-block;
            animation: blink-dot 1s infinite alternate;
        }
        @keyframes blink-dot {
            from { opacity: 0.3; transform: scale(0.85); }
            to { opacity: 1; transform: scale(1.15); }
        }

        /* Navegación por Módulos */
        .section-nav {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        @media (min-width: 540px) {
            .section-nav {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        .sec-tab {
            background: var(--card-surface);
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 10px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            transition: all 0.2s;
        }
        .sec-tab .count-badge {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 600;
        }
        .sec-tab:hover {
            color: #fff;
            border-color: #475569;
        }
        .sec-tab.active {
            background: rgba(245, 158, 11, 0.15);
            border-color: #f59e0b;
            color: #fff;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
        }
        .sec-tab.active .count-badge {
            color: #fbbf24;
            font-weight: 800;
        }

        /* Cuadrícula de Preguntas */
        .questions-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
            max-height: 520px;
            overflow-y: auto;
            padding-right: 4px;
        }
        @media (min-width: 480px) {
            .questions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .q-card {
            background: var(--card-surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            transition: border-color 0.2s;
        }
        .q-card.answered {
            border-color: rgba(56, 189, 248, 0.5);
            background: rgba(56, 189, 248, 0.05);
        }
        .q-card.answered-grid {
            border-color: rgba(245, 158, 11, 0.5);
            background: rgba(245, 158, 11, 0.08);
        }
        .q-num {
            font-size: 13px;
            font-weight: 800;
            color: #94a3b8;
            min-width: 38px;
        }
        .q-card.answered .q-num {
            color: var(--accent);
        }
        .q-card.answered-grid .q-num {
            color: #fbbf24;
        }

        /* Botones de Burbuja */
        .bubble-options {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .bubble-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: #0f172a;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }
        .bubble-btn:hover {
            border-color: #64748b;
            color: #fff;
            transform: scale(1.06);
        }
        .bubble-btn.active {
            background: #3b82f6;
            border-color: #3b82f6;
            color: #fff;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.6);
            transform: scale(1.08);
        }
        .bubble-btn.empty-btn {
            font-size: 10px;
            width: 26px;
            height: 26px;
            opacity: 0.6;
        }
        .bubble-btn.empty-btn.active {
            background: #334155;
            border-color: #64748b;
            color: #fff;
            box-shadow: none;
        }

        /* Inputs y Botones */
        .input-control {
            width: 100%;
            background: #0f172a;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            outline: none;
            transition: all 0.2s ease;
        }
        .input-control:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }

        .btn {
            background: #10b981;
            color: #fff;
            border: none;
            padding: 13px 20px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
        }
        .btn:hover {
            background: #059669;
            transform: translateY(-1px);
        }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
            box-shadow: none;
        }
        .btn-outline:hover {
            background: var(--card-surface);
            border-color: #475569;
        }

        /* Prompter Guiado de Dictado Pregunta por Pregunta (Hoja Base) */
        .guided-prompter-box {
            background: linear-gradient(135deg, #18181b 0%, #291e0a 100%);
            border: 2px solid #fbbf24;
            border-radius: 14px;
            padding: 16px 18px;
            margin-top: 14px;
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.25);
            animation: fadeInPrompter 0.3s ease;
        }
        @keyframes fadeInPrompter {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .guided-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .guided-badge {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.35);
            color: #fbbf24;
            padding: 4px 10px;
            border-radius: 14px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-tts {
            background: #1e293b;
            border: 1px solid #475569;
            color: #94a3b8;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s;
        }
        .btn-tts.active {
            background: rgba(16, 185, 129, 0.2);
            border-color: #10b981;
            color: #34d399;
        }
        .guided-meta {
            font-size: 12px;
            color: var(--muted);
            text-align: center;
            margin-bottom: 4px;
        }
        .guided-question-title {
            font-size: 18px;
            font-weight: 800;
            color: #ffffff;
            text-align: center;
            margin: 6px 0 14px 0;
            letter-spacing: -0.3px;
        }
        .guided-question-title .highlight-q {
            color: #fbbf24;
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        .guided-options-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .btn-guided-option {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            border: 2px solid var(--border);
            background: #0f172a;
            color: #f8fafc;
            font-size: 18px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-guided-option:hover {
            border-color: #fbbf24;
            color: #fff;
            transform: translateY(-2px);
        }
        .btn-guided-option.active {
            background: #fbbf24;
            border-color: #fbbf24;
            color: #000;
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.6);
            transform: scale(1.08);
        }
        .btn-guided-omitir {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px dashed var(--border);
            background: transparent;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-guided-omitir:hover {
            border-color: #94a3b8;
            color: #fff;
        }
        .guided-grid-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .guided-grid-input {
            width: 140px;
            padding: 10px 12px;
            background: #0f172a;
            border: 2px solid #fbbf24;
            border-radius: 10px;
            color: #fff;
            font-size: 18px;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
        }
        .guided-nav-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 10px;
        }
        .btn-nav-step {
            background: #1e293b;
            border: 1px solid var(--border);
            color: #cbd5e1;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-nav-step:hover {
            border-color: #fbbf24;
            color: #fff;
        }
        .guided-feedback-msg {
            color: #94a3b8;
            font-size: 12px;
            text-align: center;
            flex: 1;
            min-height: 18px;
        }
        .q-card.pregunta-enfocada {
            border: 2px solid #fbbf24 !important;
            background: rgba(245, 158, 11, 0.18) !important;
            box-shadow: 0 0 16px rgba(245, 158, 11, 0.5) !important;
            transform: scale(1.02);
            position: relative;
            z-index: 2;
        }
        .q-card.pregunta-enfocada .q-num {
            color: #fbbf24 !important;
            font-weight: 900 !important;
        }
        @keyframes flash-green {
            0% { background: rgba(16, 185, 129, 0.45); transform: scale(1.04); }
            100% { transform: scale(1.0); }
        }
        .q-card.guardado-exito {
            animation: flash-green 0.5s ease-out;
        }

        /* Barra Inferior Fija */
        .bottom-bar {
            position: sticky;
            bottom: 12px;
            background: rgba(19, 27, 46, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 16px;
        }
        .stats-summary {
            font-size: 13px;
            color: #cbd5e1;
            font-weight: 600;
        }
        .stats-summary b {
            color: #fbbf24;
            font-size: 15px;
        }
    </style>
</head>
<body>
<div class="container">

    <!-- ENCABEZADO -->
    <div class="header">
        <div class="header-badge">
            <span>INSTITUTO LUMIÈRE</span> • <span>CLAVES MAESTRAS</span>
        </div>
        <h1>🎯 Hoja Base Oficial (PAA)</h1>
        <p>Captura, edición y dictado por voz de las respuestas correctas oficiales sin escanear fotografías.</p>
    </div>

    <?php if (!$isLogged): ?>
        <!-- PANTALLA DE ACCESO POR CONTRASEÑA -->
        <div class="card" style="max-width: 440px; margin: 30px auto; text-align: center;">
            <div style="font-size: 40px; margin-bottom: 12px;">🔐</div>
            <h2 style="margin: 0 0 8px 0; font-size: 20px; color: #fff;">Acceso Docente Requerido</h2>
            <p style="color: var(--muted); font-size: 13px; margin: 0 0 20px 0;">
                Ingresa la contraseña de profesor para consultar o modificar la Hoja Base Oficial de respuestas.
            </p>

            <?php if ($errorLogin): ?>
                <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid var(--danger); color: #fca5a5; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                    <?php echo htmlspecialchars($errorLogin); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div style="margin-bottom: 16px;">
                    <input type="password" name="login_password" class="input-control" placeholder="Contraseña de Profesor" autofocus required>
                </div>
                <button type="submit" class="btn" style="width: 100%;">
                    <span>🔓 Acceder a la Hoja Base</span>
                </button>
            </form>
        </div>
    <?php else: ?>

        <!-- META-INFORMACIÓN DE LA HOJA BASE -->
        <div class="card" style="padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div>
                <b style="color: #fff; font-size: 14px;">📋 <?php echo htmlspecialchars($hojaBaseActual['nombre'] ?? 'Hoja Base Lumière PAA'); ?></b>
                <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">
                    Última sincronización: <span id="lbl-fecha-base"><?php echo htmlspecialchars($hojaBaseActual['fecha'] ?? 'Desconocida'); ?></span>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <a href="panel.php" target="_blank" class="btn btn-outline" style="padding: 8px 12px; font-size: 12px; text-decoration: none;">
                    📊 Ver Panel de Resultados
                </a>
            </div>
        </div>

        <!-- BARRA DE DICTADO POR VOZ GUIADO -->
        <div class="card" style="padding: 14px 16px;">
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <button id="btn-dictado-voz" type="button" class="btn-dictado" onclick="toggleDictadoVoz()">
                    <span id="icono-mic">🎙️</span>
                    <span id="texto-btn-voz">Iniciar Dictado Guiado de Claves</span>
                </button>
                <button id="btn-toggle-prompter" type="button" class="btn btn-outline" style="width: auto; padding: 12px 14px; font-size: 13px;" onclick="togglePrompterManual()" title="Mostrar u ocultar tarjeta de pregunta actual">
                    🎯 Tarjeta Guía
                </button>
                <button type="button" class="btn btn-outline" style="width: auto; padding: 12px 14px; font-size: 13px;" onclick="limpiarSeccionActiva()" title="Limpiar respuestas de esta sección">
                    🧹 Limpiar Módulo
                </button>
            </div>

            <!-- PROMPTER CARD GUIADO (PREGUNTA POR PREGUNTA - HOJA BASE) -->
            <div id="prompter-guiado" class="guided-prompter-box" style="display: none;">
                <div class="guided-header">
                    <div class="guided-badge">
                        <span class="pulse-dot"></span>
                        <span id="guided-badge-txt">DICTADO DE CLAVES EN VIVO</span>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="button" id="btn-toggle-tts" class="btn-tts active" onclick="toggleTTS()" title="Activar/Desactivar lectura en voz alta de las preguntas">
                            <span id="icono-tts">🔊</span> <span id="texto-tts">Audio Preguntas: SÍ</span>
                        </button>
                    </div>
                </div>

                <div class="guided-body">
                    <div class="guided-meta">
                        Módulo actual: <b id="guided-mod-lbl" style="color: #fff;">Lectura</b> &bull; Clave oficial <span id="guided-reactivo-num" style="color: #fbbf24; font-weight: 800;">1</span> de <span id="guided-reactivo-tot">45</span>
                    </div>

                    <div class="guided-question-title">
                        ¿Cuál es la clave correcta para la <span class="highlight-q">Pregunta #<span id="guided-q-num">1</span></span>?
                    </div>

                    <!-- Opciones A, B, C, D (Opción múltiple) -->
                    <div id="guided-row-multi" class="guided-options-row">
                        <button type="button" class="btn-guided-option" data-opt="A" onclick="seleccionarOpcionGuiada('A')">A</button>
                        <button type="button" class="btn-guided-option" data-opt="B" onclick="seleccionarOpcionGuiada('B')">B</button>
                        <button type="button" class="btn-guided-option" data-opt="C" onclick="seleccionarOpcionGuiada('C')">C</button>
                        <button type="button" class="btn-guided-option" data-opt="D" onclick="seleccionarOpcionGuiada('D')">D</button>
                        <button type="button" class="btn-guided-omitir" onclick="omitirPreguntaGuiada()" title="Dejar sin clave oficial">
                            ⚪ Sin clave
                        </button>
                    </div>

                    <!-- Input para Matemáticas 2 (Grid Numérico) -->
                    <div id="guided-row-grid" class="guided-grid-row" style="display: none;">
                        <input type="text" id="guided-grid-val" class="guided-grid-input" placeholder="Ej: 15 ó 1/2" maxlength="8" onkeydown="if(event.key==='Enter') guardarGridGuiado()">
                        <button type="button" class="btn" style="width: auto; padding: 10px 16px; font-size: 13px; background: #fbbf24; color: #000; font-weight: 800;" onclick="guardarGridGuiado()">
                            Guardar Clave y Siguiente ▶
                        </button>
                        <button type="button" class="btn-guided-omitir" onclick="omitirPreguntaGuiada()">
                            ⚪ Omitir
                        </button>
                    </div>

                    <!-- Navegación y Feedback en vivo -->
                    <div class="guided-nav-footer">
                        <button type="button" class="btn-nav-step" onclick="navegarPreguntaGuiada(-1)">
                            ◀ Anterior
                        </button>
                        <div id="guided-feedback-msg" class="guided-feedback-msg">
                            Di <i>"1 A"</i>, <i>"A"</i>, o pulsa una opción
                        </div>
                        <button type="button" class="btn-nav-step" onclick="navegarPreguntaGuiada(1)">
                            Siguiente ▶
                        </button>
                    </div>
                </div>
            </div>

            <!-- Panel de estado de dictado en tiempo real -->
            <div id="panel-voz-estado" style="display: none; margin-top: 10px; padding: 10px 14px; background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 12px; text-align: center;">
                <div style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">
                    Dictado de claves maestras activo &bull; Di <i>"1 A"</i>, <i>"2 B"</i> o simplemente <i>"A"</i>, <i>"B"</i>...
                </div>
                <div id="transcripcion-en-vivo" style="font-size: 14px; color: #fbbf24; font-weight: 700; min-height: 20px; font-family: monospace;">
                    🎤 Escuchando micrófono...
                </div>
            </div>
        </div>

        <!-- MÓDULOS DE REACTIVOS -->
        <div class="card">
            <div class="section-nav">
                <button type="button" class="sec-tab active" onclick="cambiarModulo('lectura')">
                    <span>Lectura</span>
                    <span class="count-badge" id="badge-count-lectura">0 / 45</span>
                </button>
                <button type="button" class="sec-tab" onclick="cambiarModulo('redaccion')">
                    <span>Redacción</span>
                    <span class="count-badge" id="badge-count-redaccion">0 / 25</span>
                </button>
                <button type="button" class="sec-tab" onclick="cambiarModulo('matematicas')">
                    <span>Matemáticas 1</span>
                    <span class="count-badge" id="badge-count-matematicas">0 / 45</span>
                </button>
                <button type="button" class="sec-tab" onclick="cambiarModulo('matematicas2')">
                    <span>Matemáticas 2</span>
                    <span class="count-badge" id="badge-count-matematicas2">0 / 10</span>
                </button>
            </div>

            <!-- Grids de Reactivos -->
            <div id="grid-lectura" class="questions-grid"></div>
            <div id="grid-redaccion" class="questions-grid" style="display: none;"></div>
            <div id="grid-matematicas" class="questions-grid" style="display: none;"></div>
            <div id="grid-matematicas2" class="questions-grid" style="display: none;"></div>
        </div>

        <!-- BARRA INFERIOR DE ACCIÓN -->
        <div class="bottom-bar">
            <div class="stats-summary">
                Claves configuradas: <b id="lbl-total-contestadas">0</b> / 125
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="button" class="btn" id="btn-guardar-base" onclick="guardarHojaBaseOficial()">
                    <span>💾 Guardar Hoja Base</span>
                </button>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php if ($isLogged): ?>
<script>
// Estado de la Hoja Base pre-cargada desde PHP
let moduloActivo = 'lectura';
let hojaBase = <?php echo json_encode([
    'lectura' => $hojaBaseActual['lectura'] ?? [],
    'redaccion' => $hojaBaseActual['redaccion'] ?? [],
    'matematicas' => $hojaBaseActual['matematicas'] ?? []
], JSON_UNESCAPED_UNICODE); ?>;

const ESPECIFICACIONES = {
    lectura: { min: 1, max: 45, label: 'Lectura (1-45)' },
    redaccion: { min: 1, max: 25, label: 'Redacción (1-25)' },
    matematicas: { min: 1, max: 45, label: 'Matemáticas 1 (1-45)' },
    matematicas2: { min: 46, max: 55, label: 'Matemáticas 2 (46-55 - Abiertas)' }
};

document.addEventListener('DOMContentLoaded', () => {
    construirGrids();
    actualizarContadores();
});

function construirGrids() {
    renderModuloOpcion('lectura', 1, 45, 'grid-lectura');
    renderModuloOpcion('redaccion', 1, 25, 'grid-redaccion');
    renderModuloOpcion('matematicas', 1, 45, 'grid-matematicas');
    renderModuloGrid('matematicas', 46, 55, 'grid-matematicas2');
}

function renderModuloOpcion(secKey, inicio, fin, contenedorId) {
    const cont = document.getElementById(contenedorId);
    cont.innerHTML = '';
    const respuestas = hojaBase[secKey] || {};

    for (let i = inicio; i <= fin; i++) {
        const num = String(i);
        const actual = respuestas[num] || '';
        const card = document.createElement('div');
        card.className = 'q-card' + (actual ? ' answered' : '');
        card.id = `q-${secKey}-${num}`;
        card.style.cursor = 'pointer';
        card.onclick = (e) => {
            if (!e.target.closest('.bubble-btn')) {
                enfocarPreguntaGuiada(i, dictandoActivo);
            }
        };
        card.innerHTML = `
            <div class="q-num">#${num}</div>
            <div class="bubble-options">
                ${['A', 'B', 'C', 'D'].map(opt => `
                    <button type="button" class="bubble-btn ${actual === opt ? 'active' : ''}" 
                        data-sec="${secKey}" data-num="${num}" data-opt="${opt}" 
                        onclick="marcarClave('${secKey}', '${num}', '${opt}'); enfocarPreguntaGuiada(${num}, false);">${opt}</button>
                `).join('')}
                <button type="button" class="bubble-btn empty-btn ${!actual ? 'active' : ''}" 
                    data-sec="${secKey}" data-num="${num}" data-opt="" 
                    onclick="marcarClave('${secKey}', '${num}', ''); enfocarPreguntaGuiada(${num}, false);" title="Sin clave">⚪</button>
            </div>
        `;
        cont.appendChild(card);
    }
}

function renderModuloGrid(secKey, inicio, fin, contenedorId) {
    const cont = document.getElementById(contenedorId);
    cont.innerHTML = '';
    const respuestas = hojaBase[secKey] || {};

    for (let i = inicio; i <= fin; i++) {
        const num = String(i);
        const actual = respuestas[num] || '';
        const card = document.createElement('div');
        card.className = 'q-card' + (actual ? ' answered-grid' : '');
        card.id = `q-mat2-${num}`;
        card.style.cursor = 'pointer';
        card.onclick = (e) => {
            if (!e.target.closest('input')) {
                enfocarPreguntaGuiada(i, dictandoActivo);
            }
        };
        card.innerHTML = `
            <div class="q-num" style="color:#fbbf24;">#${num}</div>
            <div style="flex:1; max-width: 140px;">
                <input type="text" class="input-control" value="${escapeHtml(actual)}" placeholder="Ej. 18 ó 1/2" 
                    style="padding: 6px 10px; font-size: 14px; text-transform: uppercase; font-weight: 800; text-align: center; border-color: rgba(245, 158, 11, 0.5);"
                    onfocus="enfocarPreguntaGuiada(${num}, false);"
                    oninput="marcarClaveGrid('${num}', this.value); enfocarPreguntaGuiada(${num}, false);">
            </div>
        `;
        cont.appendChild(card);
    }
}

function escapeHtml(t) {
    return String(t || '').replace(/[&<>"']/g, m => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[m]);
}

function marcarClave(sec, num, opt) {
    if (!hojaBase[sec]) hojaBase[sec] = {};
    if (opt === '') {
        delete hojaBase[sec][num];
    } else {
        hojaBase[sec][num] = opt;
    }

    const card = document.getElementById(`q-${sec}-${num}`);
    if (card) {
        card.classList.toggle('answered', opt !== '');
        card.querySelectorAll('.bubble-btn').forEach(btn => {
            const btnOpt = btn.getAttribute('data-opt');
            btn.classList.toggle('active', btnOpt === opt);
        });
    }

    actualizarContadores();
}

function marcarClaveGrid(num, val) {
    const clean = val.trim().toUpperCase();
    if (!hojaBase.matematicas) hojaBase.matematicas = {};
    if (clean === '') {
        delete hojaBase.matematicas[num];
    } else {
        hojaBase.matematicas[num] = clean;
    }

    const card = document.getElementById(`q-mat2-${num}`);
    if (card) {
        card.classList.toggle('answered-grid', clean !== '');
    }

    actualizarContadores();
}

function cambiarModulo(mod) {
    moduloActivo = mod;
    document.querySelectorAll('.sec-tab').forEach(t => t.classList.remove('active'));
    const btnTab = document.querySelector(`.sec-tab[onclick*="'${mod}'"]`);
    if (btnTab) btnTab.classList.add('active');

    document.getElementById('grid-lectura').style.display = mod === 'lectura' ? 'grid' : 'none';
    document.getElementById('grid-redaccion').style.display = mod === 'redaccion' ? 'grid' : 'none';
    document.getElementById('grid-matematicas').style.display = mod === 'matematicas' ? 'grid' : 'none';
    document.getElementById('grid-matematicas2').style.display = mod === 'matematicas2' ? 'grid' : 'none';

    const spec = ESPECIFICACIONES[mod];
    const elemSec = document.getElementById('voz-seccion-nombre');
    const elemTot = document.getElementById('voz-reactivo-total');
    const elemAct = document.getElementById('voz-reactivo-actual');
    if (elemSec) elemSec.textContent = spec.label;
    if (elemTot) elemTot.textContent = (spec.max - spec.min + 1);
    if (elemAct) elemAct.textContent = spec.min;

    enfocarPreguntaGuiada(spec.min, dictandoActivo);
}

function actualizarContadores() {
    let cLec = Object.keys(hojaBase.lectura || {}).length;
    let cRed = Object.keys(hojaBase.redaccion || {}).length;
    let cMat1 = 0;
    let cMat2 = 0;

    const matKeys = Object.keys(hojaBase.matematicas || {});
    matKeys.forEach(k => {
        const n = parseInt(k, 10);
        if (n >= 1 && n <= 45) cMat1++;
        else if (n >= 46 && n <= 55) cMat2++;
    });

    const tot = cLec + cRed + cMat1 + cMat2;

    document.getElementById('badge-count-lectura').textContent = `${cLec} / 45`;
    document.getElementById('badge-count-redaccion').textContent = `${cRed} / 25`;
    document.getElementById('badge-count-matematicas').textContent = `${cMat1} / 45`;
    document.getElementById('badge-count-matematicas2').textContent = `${cMat2} / 10`;
    document.getElementById('lbl-total-contestadas').textContent = tot;
}

function limpiarSeccionActiva() {
    const spec = ESPECIFICACIONES[moduloActivo];
    if (!confirm(`¿Borrar todas las respuestas del módulo "${spec.label}"?`)) return;

    if (moduloActivo === 'matematicas2') {
        for (let i = 46; i <= 55; i++) {
            delete hojaBase.matematicas[String(i)];
            const inp = document.querySelector(`#q-mat2-${i} input`);
            if (inp) inp.value = '';
            const card = document.getElementById(`q-mat2-${i}`);
            if (card) card.classList.remove('answered-grid');
        }
    } else {
        const secKey = (moduloActivo === 'matematicas') ? 'matematicas' : moduloActivo;
        for (let i = spec.min; i <= spec.max; i++) {
            marcarClave(secKey, String(i), '');
        }
    }
    actualizarContadores();
}

// ====================================================================
// MOTOR DE DICTADO POR VOZ GUIADO DE HOJA BASE (PREGUNTA POR PREGUNTA)
// ====================================================================
let dictandoActivo = false;
let reconocimientoVoz = null;
let prompterManualVisible = false;
let preguntaGuiadaActual = 1;
let ttsHabilitado = true;
let ignorarReconocimientoPorTTS = false;
let ultimoTextoProcesado = '';
let ultimoTiempoProcesado = 0;
let debounceVozTimer = null;

const MAPA_NUMEROS_TEXTO = {
    'uno': 1, 'una': 1, 'primer': 1, 'primera': 1, 'primero': 1, 'un': 1,
    'dos': 2, 'segunda': 2, 'segundo': 2,
    'tres': 3, 'tercer': 3, 'tercera': 3, 'tercero': 3,
    'cuatro': 4, 'cuarta': 4, 'cuarto': 4,
    'cinco': 5, 'quinta': 5, 'quinto': 5,
    'seis': 6, 'sexta': 6, 'sexto': 6,
    'siete': 7, 'séptima': 7, 'septima': 7, 'séptimo': 7, 'septimo': 7,
    'ocho': 8, 'octava': 8, 'octavo': 8,
    'nueve': 9, 'novena': 9, 'noveno': 9,
    'diez': 10, 'décima': 10, 'decima': 10, 'décimo': 10, 'decimo': 10,
    'once': 11, 'doce': 12, 'trece': 13, 'catorce': 14, 'quince': 15,
    'dieciséis': 16, 'dieciseis': 16, 'diecisiete': 17, 'dieciocho': 18, 'diecinueve': 19,
    'veinte': 20, 'veintiuno': 21, 'veintiún': 21, 'veintiun': 21, 'veintidós': 22, 'veintidos': 22,
    'veintitrés': 23, 'veintitres': 23, 'veinticuatro': 24, 'veinticinco': 25,
    'veintiséis': 26, 'veintiseis': 26, 'veintisiete': 27, 'veintiocho': 28, 'veintinueve': 29,
    'treinta': 30, 'cuarenta': 40, 'cincuenta': 50
};

function togglePrompterManual() {
    prompterManualVisible = !prompterManualVisible;
    const prompter = document.getElementById('prompter-guiado');
    if (prompter) {
        prompter.style.display = prompterManualVisible ? 'block' : 'none';
    }
    if (prompterManualVisible) {
        enfocarPreguntaGuiada(preguntaGuiadaActual, false);
    }
}

function toggleTTS() {
    ttsHabilitado = !ttsHabilitado;
    const btn = document.getElementById('btn-toggle-tts');
    const icon = document.getElementById('icono-tts');
    const txt = document.getElementById('texto-tts');
    if (btn) btn.classList.toggle('active', ttsHabilitado);
    if (icon) icon.textContent = ttsHabilitado ? '🔊' : '🔇';
    if (txt) txt.textContent = ttsHabilitado ? 'Audio Preguntas: SÍ' : 'Audio Preguntas: NO';

    if (ttsHabilitado && dictandoActivo) {
        hablarPregunta(preguntaGuiadaActual);
    }
}

function hablarPregunta(num) {
    if (!ttsHabilitado || !window.speechSynthesis) return;
    try {
        window.speechSynthesis.cancel();
        const ut = new SpeechSynthesisUtterance("Pregunta " + num);
        ut.lang = 'es-MX';
        ut.rate = 1.35;
        ut.pitch = 1.0;
        ignorarReconocimientoPorTTS = true;
        ut.onend = function() {
            setTimeout(() => { ignorarReconocimientoPorTTS = false; }, 250);
        };
        ut.onerror = function() {
            ignorarReconocimientoPorTTS = false;
        };
        window.speechSynthesis.speak(ut);
    } catch(e) {
        ignorarReconocimientoPorTTS = false;
    }
}

function enfocarPreguntaGuiada(num, hablar = false) {
    const spec = ESPECIFICACIONES[moduloActivo];
    if (num < spec.min) num = spec.min;
    if (num > spec.max) num = spec.max;
    preguntaGuiadaActual = num;

    const elemQNum = document.getElementById('guided-q-num');
    const elemReactivoNum = document.getElementById('guided-reactivo-num');
    const elemReactivoTot = document.getElementById('guided-reactivo-tot');
    const elemModLbl = document.getElementById('guided-mod-lbl');

    if (elemQNum) elemQNum.textContent = num;
    if (elemReactivoNum) elemReactivoNum.textContent = num;
    if (elemReactivoTot) elemReactivoTot.textContent = spec.max;
    if (elemModLbl) elemModLbl.textContent = spec.label;

    const elemAct = document.getElementById('voz-reactivo-actual');
    if (elemAct) elemAct.textContent = num;

    document.querySelectorAll('.q-card.pregunta-enfocada').forEach(c => c.classList.remove('pregunta-enfocada'));

    const cardId = (moduloActivo === 'matematicas2') ? `q-mat2-${num}` : `q-${moduloActivo === 'matematicas' ? 'matematicas' : moduloActivo}-${num}`;
    const card = document.getElementById(cardId);
    if (card) {
        card.classList.add('pregunta-enfocada');
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (moduloActivo === 'matematicas2') {
        const rowMulti = document.getElementById('guided-row-multi');
        const rowGrid = document.getElementById('guided-row-grid');
        if (rowMulti) rowMulti.style.display = 'none';
        if (rowGrid) rowGrid.style.display = 'flex';
        const inp = document.getElementById('guided-grid-val');
        const valActual = (hojaBase.matematicas && hojaBase.matematicas[String(num)]) || '';
        if (inp) inp.value = valActual;
    } else {
        const rowMulti = document.getElementById('guided-row-multi');
        const rowGrid = document.getElementById('guided-row-grid');
        if (rowMulti) rowMulti.style.display = 'flex';
        if (rowGrid) rowGrid.style.display = 'none';
        const secKey = (moduloActivo === 'matematicas') ? 'matematicas' : moduloActivo;
        const respActual = (hojaBase[secKey] && hojaBase[secKey][String(num)]) || '';
        document.querySelectorAll('.btn-guided-option').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-opt') === respActual);
        });
    }

    if (hablar && dictandoActivo) {
        hablarPregunta(num);
    }
}

function seleccionarOpcionGuiada(opt) {
    const spec = ESPECIFICACIONES[moduloActivo];
    const secKey = (moduloActivo === 'matematicas') ? 'matematicas' : moduloActivo;
    const num = preguntaGuiadaActual;

    marcarClave(secKey, String(num), opt);

    const cardId = `q-${secKey}-${num}`;
    const card = document.getElementById(cardId);
    if (card) {
        card.classList.remove('guardado-exito');
        void card.offsetWidth;
        card.classList.add('guardado-exito');
    }

    const msgBox = document.getElementById('guided-feedback-msg');
    if (msgBox) {
        msgBox.innerHTML = `<span style="color:#10b981; font-weight:700;">✅ Clave #${num}: <b>${opt}</b> guardada</span>`;
    }

    const siguiente = num + 1;
    if (siguiente <= spec.max) {
        enfocarPreguntaGuiada(siguiente, true);
    } else {
        if (msgBox) msgBox.innerHTML = `<span style="color:#fbbf24; font-weight:800;">🎉 ¡Módulo ${spec.label} de Hoja Base completado!</span>`;
        if (ttsHabilitado && window.speechSynthesis) {
            try {
                window.speechSynthesis.cancel();
                const ut = new SpeechSynthesisUtterance("Módulo completado");
                ut.lang = 'es-MX';
                ut.rate = 1.3;
                window.speechSynthesis.speak(ut);
            } catch(e) {}
        }
    }
}

function guardarGridGuiado() {
    const inp = document.getElementById('guided-grid-val');
    const val = inp ? inp.value.trim().toUpperCase() : '';
    const num = preguntaGuiadaActual;
    marcarClaveGrid(String(num), val);

    const cardId = `q-mat2-${num}`;
    const card = document.getElementById(cardId);
    if (card) {
        card.classList.remove('guardado-exito');
        void card.offsetWidth;
        card.classList.add('guardado-exito');
    }

    const inpCard = document.querySelector(`#q-mat2-${num} input`);
    if (inpCard) inpCard.value = val;

    const msgBox = document.getElementById('guided-feedback-msg');
    if (msgBox) {
        msgBox.innerHTML = `<span style="color:#fbbf24; font-weight:700;">✅ Clave #${num}: <b>${val || 'Sin clave'}</b> guardada</span>`;
    }

    const siguiente = num + 1;
    if (siguiente <= 55) {
        enfocarPreguntaGuiada(siguiente, true);
    } else {
        if (msgBox) msgBox.innerHTML = `<span style="color:#fbbf24; font-weight:800;">🎉 ¡Módulo Matemáticas 2 completado!</span>`;
    }
}

function omitirPreguntaGuiada() {
    const spec = ESPECIFICACIONES[moduloActivo];
    const num = preguntaGuiadaActual;
    const msgBox = document.getElementById('guided-feedback-msg');
    if (msgBox) {
        msgBox.innerHTML = `<span style="color:#94a3b8;">⚪ Pregunta ${num} sin clave</span>`;
    }
    const siguiente = num + 1;
    if (siguiente <= spec.max) {
        enfocarPreguntaGuiada(siguiente, true);
    }
}

function navegarPreguntaGuiada(delta) {
    const spec = ESPECIFICACIONES[moduloActivo];
    let dest = preguntaGuiadaActual + delta;
    if (dest < spec.min) dest = spec.min;
    if (dest > spec.max) dest = spec.max;
    enfocarPreguntaGuiada(dest, true);
}

function interpretarDictadoGuiado(texto, preguntaActual = 1) {
    if (!texto) return [];
    let t = texto.toLowerCase().trim();

    if (/\b(anterior|atras|atrás|regresar)\b/.test(t)) {
        return [{ tipo: 'nav', delta: -1 }];
    }
    if (/\b(siguiente|adelante|omitir|paso|blanco|vacío|vacio|omitida)\b/.test(t)) {
        return [{ tipo: 'nav', delta: 1 }];
    }
    if (/\b(repetir|repite)\b/.test(t)) {
        return [{ tipo: 'repetir' }];
    }

    const decenas = [
        ['treinta y nueve', 39], ['treinta y ocho', 38], ['treinta y siete', 37], ['treinta y seis', 36],
        ['treinta y cinco', 35], ['treinta y cuatro', 34], ['treinta y tres', 33], ['treinta y dos', 32], ['treinta y uno', 31],
        ['cuarenta y nueve', 49], ['cuarenta y ocho', 48], ['cuarenta y siete', 47], ['cuarenta y seis', 46],
        ['cuarenta y cinco', 45], ['cuarenta y cuatro', 44], ['cuarenta y tres', 43], ['cuarenta y dos', 42], ['cuarenta y uno', 41],
        ['cincuenta y cinco', 55], ['cincuenta y cuatro', 54], ['cincuenta y tres', 53], ['cincuenta y dos', 52], ['cincuenta y uno', 51]
    ];
    decenas.forEach(([txt, num]) => {
        t = t.replace(new RegExp('\\b' + txt + '\\b', 'g'), String(num));
    });

    const fracciones = [
        ['un medio', '1/2'], ['medio', '1/2'], ['un tercio', '1/3'], ['dos tercios', '2/3'],
        ['un cuarto', '1/4'], ['tres cuartos', '3/4'], ['un quinto', '1/5'], ['dos quintos', '2/5'],
        ['tres quintos', '3/5'], ['cuatro quintos', '4/5'], ['un sexto', '1/6'], ['cinco sextos', '5/6'],
        ['cero punto cinco', '0.5'], ['cero punto veinticinco', '0.25'], ['cero punto setenta y cinco', '0.75']
    ];
    fracciones.forEach(([txt, val]) => {
        t = t.replace(new RegExp('\\b' + txt + '\\b', 'g'), val);
    });

    Object.keys(MAPA_NUMEROS_TEXTO).forEach(pal => {
        t = t.replace(new RegExp('\\b' + pal + '\\b', 'g'), String(MAPA_NUMEROS_TEXTO[pal]));
    });

    t = t.replace(/\b(bueno|vaca|be|ve|vé|bé)\b/g, ' b ');
    t = t.replace(/\b(casa|ce|sé|se|cé)\b/g, ' c ');
    t = t.replace(/\b(dedo|dado|de|dé)\b/g, ' d ');
    t = t.replace(/\b(ah|ha)\b/g, ' a ');

    t = t.replace(/\b(pregunta|reactivo|número|numero|la|el|es|opción|opcion|letra|clave)\b/g, ' ');
    t = t.replace(/[.,;:!?]/g, ' ').replace(/\s+/g, ' ').trim();

    const acciones = [];

    const regexPar = /(\d{1,2})\s*([abcdABCD])\b/g;
    let match;
    let tienePares = false;
    while ((match = regexPar.exec(t)) !== null) {
        tienePares = true;
        acciones.push({
            tipo: 'respuesta',
            numero: parseInt(match[1], 10),
            opcion: match[2].toUpperCase()
        });
    }

    if (!tienePares) {
        const matchNumVal = t.match(/^(\d{1,2})\s+([0-9\.\/]+)$/);
        if (matchNumVal) {
            acciones.push({
                tipo: 'grid',
                numero: parseInt(matchNumVal[1], 10),
                valor: matchNumVal[2]
            });
            return acciones;
        }

        const matchSoloNum = t.match(/^(\d{1,2})$/);
        if (matchSoloNum) {
            acciones.push({
                tipo: 'ir_a',
                numero: parseInt(matchSoloNum[1], 10)
            });
            return acciones;
        }

        const tokens = t.split(/\s+/);
        let currNum = preguntaActual;
        for (let tok of tokens) {
            if (/^[abcdABCD]$/.test(tok)) {
                acciones.push({
                    tipo: 'respuesta',
                    numero: currNum,
                    opcion: tok.toUpperCase()
                });
                currNum++;
            } else if (/^[0-9\.\/]+$/.test(tok) && currNum >= 46 && currNum <= 55) {
                acciones.push({
                    tipo: 'grid',
                    numero: currNum,
                    valor: tok
                });
                currNum++;
            }
        }
    }

    return acciones;
}

function procesarFraseVoz(frase) {
    if (ignorarReconocimientoPorTTS) return;
    if (!frase || !frase.trim()) return;

    const textoLimpio = frase.trim();
    const ahora = Date.now();
    if (ultimoTextoProcesado === textoLimpio && (ahora - ultimoTiempoProcesado < 800)) {
        return;
    }
    ultimoTextoProcesado = textoLimpio;
    ultimoTiempoProcesado = ahora;

    const liveBox = document.getElementById('transcripcion-en-vivo');
    if (liveBox) {
        liveBox.textContent = `🎤 "${textoLimpio}"`;
    }

    const acciones = interpretarDictadoGuiado(textoLimpio, preguntaGuiadaActual);
    if (!acciones || acciones.length === 0) return;

    const spec = ESPECIFICACIONES[moduloActivo];

    for (let acc of acciones) {
        if (acc.tipo === 'nav') {
            navegarPreguntaGuiada(acc.delta);
        } else if (acc.tipo === 'repetir') {
            hablarPregunta(preguntaGuiadaActual);
        } else if (acc.tipo === 'ir_a') {
            enfocarPreguntaGuiada(acc.numero, true);
        } else if (acc.tipo === 'respuesta') {
            const num = acc.numero;
            if (num >= spec.min && num <= spec.max && moduloActivo !== 'matematicas2') {
                preguntaGuiadaActual = num;
                seleccionarOpcionGuiada(acc.opcion);
            }
        } else if (acc.tipo === 'grid') {
            const num = acc.numero;
            if (num >= 46 && num <= 55 && moduloActivo === 'matematicas2') {
                preguntaGuiadaActual = num;
                const inp = document.getElementById('guided-grid-val');
                if (inp) inp.value = acc.valor;
                guardarGridGuiado();
            }
        }
    }
}

function toggleDictadoVoz() {
    if (dictandoActivo) {
        detenerDictadoVoz();
    } else {
        iniciarDictadoVoz();
    }
}

function iniciarDictadoVoz() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        alert('Tu navegador no soporta reconocimiento de voz nativo.\nRecomendamos Google Chrome o Safari.');
        return;
    }

    try {
        if (reconocimientoVoz) reconocimientoVoz.abort();

        reconocimientoVoz = new SpeechRecognition();
        reconocimientoVoz.lang = 'es-MX';
        reconocimientoVoz.continuous = true;
        reconocimientoVoz.interimResults = true;
        reconocimientoVoz.maxAlternatives = 1;

        dictandoActivo = true;
        prompterManualVisible = true;

        const btnVoz = document.getElementById('btn-dictado-voz');
        const iconMic = document.getElementById('icono-mic');
        const txtVoz = document.getElementById('texto-btn-voz');
        const prompter = document.getElementById('prompter-guiado');
        const panelEstado = document.getElementById('panel-voz-estado');
        const transVivo = document.getElementById('transcripcion-en-vivo');

        if (btnVoz) btnVoz.classList.add('activo');
        if (iconMic) iconMic.textContent = '⏹️';
        if (txtVoz) txtVoz.textContent = 'Detener Dictado';
        if (prompter) prompter.style.display = 'block';
        if (panelEstado) panelEstado.style.display = 'block';
        if (transVivo) transVivo.textContent = '🎤 Escuchando micrófono...';

        enfocarPreguntaGuiada(preguntaGuiadaActual, true);

        reconocimientoVoz.onresult = function(event) {
            if (ignorarReconocimientoPorTTS) return;

            let finalFrase = '';
            let interinFrase = '';

            for (let i = event.resultIndex; i < event.results.length; ++i) {
                const item = event.results[i];
                const transc = item[0].transcript;
                if (item.isFinal) {
                    finalFrase += transc + ' ';
                } else {
                    interinFrase += transc + ' ';
                }
            }

            if (interinFrase.trim() !== '') {
                const live = document.getElementById('transcripcion-en-vivo');
                if (live) live.textContent = '🎤 ' + interinFrase.trim();
            }

            if (finalFrase.trim() !== '') {
                procesarFraseVoz(finalFrase.trim());
            } else if (interinFrase.trim() !== '') {
                clearTimeout(debounceVozTimer);
                debounceVozTimer = setTimeout(() => {
                    if (!ignorarReconocimientoPorTTS && interinFrase.trim() !== '') {
                        procesarFraseVoz(interinFrase.trim());
                    }
                }, 750);
            }
        };

        reconocimientoVoz.onerror = function(event) {
            console.warn('Reconocimiento de voz:', event.error);
            if (event.error === 'not-allowed') {
                alert('Permiso de micrófono denegado en el navegador.');
                detenerDictadoVoz();
            }
        };

        reconocimientoVoz.onend = function() {
            if (dictandoActivo) {
                try { reconocimientoVoz.start(); } catch(e) {}
            }
        };

        reconocimientoVoz.start();

    } catch (err) {
        console.error('Error al iniciar reconocimiento:', err);
        detenerDictadoVoz();
    }
}

function detenerDictadoVoz() {
    dictandoActivo = false;
    if (reconocimientoVoz) {
        try { reconocimientoVoz.stop(); } catch(e) {}
        reconocimientoVoz = null;
    }

    if (window.speechSynthesis) {
        try { window.speechSynthesis.cancel(); } catch(e) {}
    }

    const btnVoz = document.getElementById('btn-dictado-voz');
    const iconMic = document.getElementById('icono-mic');
    const txtVoz = document.getElementById('texto-btn-voz');
    const panelEstado = document.getElementById('panel-voz-estado');

    if (btnVoz) btnVoz.classList.remove('activo');
    if (iconMic) iconMic.textContent = '🎙️';
    if (txtVoz) txtVoz.textContent = 'Iniciar Dictado Guiado de Claves';
    if (panelEstado) panelEstado.style.display = 'none';
}

// ====================================================================
// GUARDAR HOJA BASE OFICIAL
// ====================================================================
async function guardarHojaBaseOficial() {
    detenerDictadoVoz();

    const btn = document.getElementById('btn-guardar-base');
    const txtOrig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span>⏳ Guardando...</span>`;

    try {
        const formData = new FormData();
        formData.append('accion', 'admin_guardar_hoja_base');
        formData.append('admin_token', '<?php echo ADMIN_PASSWORD; ?>');
        formData.append('hoja_base', JSON.stringify(hojaBase));

        const res = await fetch('api_calificar.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.exito) {
            alert('✅ ¡Hoja Base Oficial guardada exitosamente!\n\nTodos los exámenes que se califiquen a partir de ahora usarán estas claves.');
            if (data.hoja_base?.fecha) {
                const lbl = document.getElementById('lbl-fecha-base');
                if (lbl) lbl.textContent = data.hoja_base.fecha;
            }
        } else {
            alert('⚠️ Error al guardar: ' + (data.error || 'Error desconocido'));
        }
    } catch (err) {
        alert('Error de conexión al guardar la Hoja Base.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = txtOrig;
    }
}
</script>
<?php endif; ?>
</body>
</html>
