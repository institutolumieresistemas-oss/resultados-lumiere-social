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

        <!-- BARRA DE DICTADO POR VOZ -->
        <div class="card" style="padding: 14px 16px;">
            <div style="display: flex; gap: 8px; align-items: center;">
                <button id="btn-dictado-voz" type="button" class="btn-dictado" onclick="toggleDictadoVoz()">
                    <span id="icono-mic">🎙️</span>
                    <span id="texto-btn-voz">Iniciar Dictado por Voz</span>
                </button>
                <button type="button" class="btn btn-outline" style="width: auto; padding: 12px 14px; font-size: 13px;" onclick="limpiarSeccionActiva()" title="Limpiar respuestas de esta sección">
                    🧹 Limpiar Módulo
                </button>
            </div>

            <div id="panel-voz-estado" style="display: none; margin-top: 10px; padding: 12px 16px; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.35); border-radius: 12px; text-align: center;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 6px;">
                    <span class="pulse-dot"></span>
                    <b style="color: #38bdf8; font-size: 14px;">Dictado de claves maestras activo</b>
                </div>
                <p style="margin: 0; font-size: 12px; color: var(--muted);">
                    Pronuncia letras en secuencia: <i>"A, B, D, C, bueno, omitida..."</i> — Las claves correctas se guardarán en vivo.
                </p>
                <div id="transcripcion-en-vivo" style="margin-top: 8px; font-size: 15px; color: #fff; font-weight: 800; min-height: 22px; font-family: monospace; letter-spacing: 2px;">
                    Escuchando micrófono...
                </div>
                <div style="margin-top: 6px; font-size: 12px; color: #94a3b8;">
                    Módulo actual: <b id="voz-seccion-nombre" style="color: #fff;">Lectura</b> | 
                    Reactivo siguiente: <b id="voz-reactivo-actual" style="color: #38bdf8;">1</b> de <span id="voz-reactivo-total">45</span>
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
        card.innerHTML = `
            <div class="q-num">#${num}</div>
            <div class="bubble-options">
                ${['A', 'B', 'C', 'D'].map(opt => `
                    <button type="button" class="bubble-btn ${actual === opt ? 'active' : ''}" 
                        data-sec="${secKey}" data-num="${num}" data-opt="${opt}" 
                        onclick="marcarClave('${secKey}', '${num}', '${opt}')">${opt}</button>
                `).join('')}
                <button type="button" class="bubble-btn empty-btn ${!actual ? 'active' : ''}" 
                    data-sec="${secKey}" data-num="${num}" data-opt="" 
                    onclick="marcarClave('${secKey}', '${num}', '')" title="Sin clave">⚪</button>
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
        card.innerHTML = `
            <div class="q-num" style="color:#fbbf24;">#${num}</div>
            <div style="flex:1; max-width: 140px;">
                <input type="text" class="input-control" value="${escapeHtml(actual)}" placeholder="Ej. 18 ó 1/2" 
                    style="padding: 6px 10px; font-size: 14px; text-transform: uppercase; font-weight: 800; text-align: center; border-color: rgba(245, 158, 11, 0.5);"
                    oninput="marcarClaveGrid('${num}', this.value)">
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
// DICTADO POR VOZ (Web Speech API)
// ====================================================================
let dictandoActivo = false;
let reconocimientoVoz = null;

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

        const btnVoz = document.getElementById('btn-dictado-voz');
        const iconMic = document.getElementById('icono-mic');
        const txtVoz = document.getElementById('texto-btn-voz');
        const panelEstado = document.getElementById('panel-voz-estado');
        const transVivo = document.getElementById('transcripcion-en-vivo');

        if (btnVoz) btnVoz.classList.add('activo');
        if (iconMic) iconMic.textContent = '⏹️';
        if (txtVoz) txtVoz.textContent = 'Detener Dictado';
        if (panelEstado) panelEstado.style.display = 'block';
        if (transVivo) transVivo.textContent = '🎤 Escuchando micrófono...';

        reconocimientoVoz.onresult = function(event) {
            let textoCompleto = '';
            for (let i = 0; i < event.results.length; ++i) {
                textoCompleto += event.results[i][0].transcript + ' ';
            }

            const letras = parsearDictadoALetras(textoCompleto);
            if (letras.length === 0) return;

            const spec = ESPECIFICACIONES[moduloActivo];

            if (moduloActivo === 'matematicas2') {
                letras.forEach((val, idx) => {
                    const num = 46 + idx;
                    if (num <= 55) {
                        marcarClaveGrid(String(num), val);
                        const inp = document.querySelector(`#q-mat2-${num} input`);
                        if (inp) inp.value = val;
                    }
                });

                if (transVivo) transVivo.textContent = letras.slice(-5).join(' ') || 'Escuchando...';
                const reactivoSig = Math.min(46 + letras.length, 55);
                const elemAct = document.getElementById('voz-reactivo-actual');
                if (elemAct) elemAct.textContent = reactivoSig;

                const cardAct = document.getElementById(`q-mat2-${Math.min(46 + (letras.length ? (letras.length - 1) : 0), 55)}`);
                if (cardAct) cardAct.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                const secKey = (moduloActivo === 'matematicas') ? 'matematicas' : moduloActivo;
                const totalSec = spec.max;

                letras.forEach((letra, idx) => {
                    const num = idx + 1;
                    if (num <= totalSec) {
                        marcarClave(secKey, String(num), letra);
                    }
                });

                if (transVivo) transVivo.textContent = letras.slice(-10).join(' ') || 'Escuchando...';
                const reactivoSig = Math.min(letras.length + 1, totalSec);
                const elemAct = document.getElementById('voz-reactivo-actual');
                if (elemAct) elemAct.textContent = reactivoSig;

                const cardAct = document.getElementById(`q-${secKey}-${letras.length || 1}`);
                if (cardAct) cardAct.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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

    const btnVoz = document.getElementById('btn-dictado-voz');
    const iconMic = document.getElementById('icono-mic');
    const txtVoz = document.getElementById('texto-btn-voz');
    const panelEstado = document.getElementById('panel-voz-estado');

    if (btnVoz) btnVoz.classList.remove('activo');
    if (iconMic) iconMic.textContent = '🎙️';
    if (txtVoz) txtVoz.textContent = 'Iniciar Dictado por Voz';
    if (panelEstado) panelEstado.style.display = 'none';
}

function parsearDictadoALetras(texto) {
    if (!texto) return [];
    const t = texto.toLowerCase().replace(/[.,;:!?]/g, ' ').trim();
    const palabras = t.split(/\s+/);
    const letras = [];

    for (let p of palabras) {
        if (!p) continue;
        if (p === 'a' || p === 'ah' || p === 'ha') {
            letras.push('A');
        } else if (p === 'b' || p === 'be' || p === 've' || p === 'bé' || p === 'vé' || p === 'bueno') {
            letras.push('B');
        } else if (p === 'c' || p === 'ce' || p === 'se' || p === 'sé' || p === 'cé' || p === 'casa') {
            letras.push('C');
        } else if (p === 'd' || p === 'de' || p === 'dé' || p === 'dedo' || p === 'dado') {
            letras.push('D');
        } else if (p === 'blanco' || p === 'paso' || p === 'omitida' || p === 'vacio' || p === 'vacío' || p === 'ninguna') {
            letras.push('');
        } else if (/^[0-9\/\.]+$/.test(p)) {
            letras.push(p);
        } else {
            for (let ch of p) {
                if (ch === 'a') letras.push('A');
                else if (ch === 'b' || ch === 'v') letras.push('B');
                else if (ch === 'c' || ch === 's') letras.push('C');
                else if (ch === 'd') letras.push('D');
            }
        }
    }
    return letras;
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
