<?php
/**
 * ====================================================================
 * Captura Manual y Dictado por Voz de Exámenes PAA - Instituto Lumière
 * calificador.lumieresocial.com / resultados.lumieresocial.com
 * ====================================================================
 */
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$esAdmin = (!empty($_SESSION['admin_token']) && $_SESSION['admin_token'] === ADMIN_PASSWORD);
$origen = trim($_GET['origen'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Captura Manual y Dictado - Exámenes PAA | Instituto Lumière</title>
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
            max-width: 680px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header-badge {
            background: rgba(56, 189, 248, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.35);
            color: var(--accent);
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

        .input-group {
            margin-bottom: 14px;
        }
        .input-group label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #cbd5e1;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .input-control {
            width: 100%;
            background: #0f172a;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            outline: none;
            transition: all 0.2s ease;
        }
        .input-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
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

        /* Navegación por Secciones */
        .section-nav {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        @media (min-width: 520px) {
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
            background: rgba(56, 189, 248, 0.15);
            border-color: var(--accent);
            color: #fff;
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.2);
        }
        .sec-tab.active .count-badge {
            color: var(--accent);
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

        /* Botones de Burbujas */
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
            background: var(--accent);
            border-color: var(--accent);
            color: #090d16;
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.6);
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

        /* Botones Principales */
        .btn {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(192, 27, 47, 0.4);
        }
        .btn:hover {
            background: var(--primary-hover);
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

        /* Resumen de Barra Inferior */
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
            color: var(--accent);
            font-size: 15px;
        }

        /* Pantalla de Carga */
        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid rgba(56, 189, 248, 0.2);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 12px auto;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Pantalla de Resultados */
        .score-hero {
            background: linear-gradient(135deg, rgba(192, 27, 47, 0.2) 0%, rgba(56, 189, 248, 0.15) 100%);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px 16px;
            text-align: center;
            margin-bottom: 20px;
        }
        .score-hero-number {
            font-size: 48px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -1px;
            line-height: 1;
            margin: 8px 0;
        }
        .score-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .score-col-num {
            font-size: 20px;
            font-weight: 800;
            color: #fff;
        }
        .score-col-lbl {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }
    </style>
</head>
<body>
<div class="container">

    <!-- ENCABEZADO -->
    <div class="header">
        <div class="header-badge">
            <span>INSTITUTO LUMIÈRE</span> • <span>MODO MANUAL</span>
        </div>
        <h1>✍️ Captura Manual y Dictado</h1>
        <p>Carga de respuestas de examen de alumno sin escaneo de imagen. Compatible con dictado por voz y captura táctil.</p>
        <div style="margin-top: 8px; font-size: 12px; color: var(--muted);">
            ¿Deseas configurar las respuestas correctas oficiales? <a href="captura_base.php" target="_blank" style="color: #fbbf24; text-decoration: none; font-weight: 700;">🎯 Ir a Cargar Hoja Base ↗</a>
        </div>
    </div>

    <!-- PANTALLA PRINCIPAL DE CAPTURA -->
    <div id="pantalla-formulario">
        
        <!-- CARD 1: DATOS DEL ALUMNO -->
        <div class="card">
            <div class="input-group">
                <label for="alumno-nombre">Nombre Completo del Alumno: <span style="color:var(--danger)">*</span></label>
                <input type="text" id="alumno-nombre" class="input-control" placeholder="Ej. Juan Carlos López Díaz" autocomplete="off" onblur="verificarAlumnoExistente()">
                <div id="msg-verificacion-alumno" style="margin-top: 6px; font-size: 12px; display: none;"></div>
            </div>

            <div class="input-group" style="margin-bottom: 0;">
                <label for="alumno-carrera">Carrera / Especialidad (Opcional):</label>
                <input type="text" id="alumno-carrera" class="input-control" placeholder="Ej. Médico Cirujano, Arquitectura...">
            </div>
        </div>

        <!-- CARD 2: BARRA DE DICTADO POR VOZ -->
        <div class="card" style="padding: 14px 16px;">
            <div style="display: flex; gap: 8px; align-items: center;">
                <button id="btn-dictado-voz" type="button" class="btn-dictado" onclick="toggleDictadoVoz()">
                    <span id="icono-mic">🎙️</span>
                    <span id="texto-btn-voz">Iniciar Dictado por Voz</span>
                </button>
                <button type="button" class="btn btn-outline" style="width: auto; padding: 12px 14px; font-size: 13px;" onclick="limpiarSeccionActiva()" title="Limpiar respuestas de esta sección">
                    🧹 Limpiar
                </button>
            </div>

            <div id="panel-voz-estado" style="display: none; margin-top: 10px; padding: 12px 16px; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.35); border-radius: 12px; text-align: center;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 6px;">
                    <span class="pulse-dot"></span>
                    <b style="color: #38bdf8; font-size: 14px;">Dictado en tiempo real activo</b>
                </div>
                <p style="margin: 0; font-size: 12px; color: var(--muted);">
                    Pronuncia letras en secuencia: <i>"A, B, D, C, bueno, omitida..."</i> — Las casillas se rellenarán automáticamente.
                </p>
                <div id="transcripcion-en-vivo" style="margin-top: 8px; font-size: 15px; color: #fff; font-weight: 800; min-height: 22px; font-family: monospace; letter-spacing: 2px;">
                    Escuchando micrófono...
                </div>
                <div style="margin-top: 6px; font-size: 12px; color: #94a3b8;">
                    Módulo: <b id="voz-seccion-nombre" style="color: #fff;">Lectura</b> | 
                    Reactivo siguiente: <b id="voz-reactivo-actual" style="color: #38bdf8;">1</b> de <span id="voz-reactivo-total">45</span>
                </div>
            </div>
        </div>

        <!-- CARD 3: NAVEGACIÓN Y REACTIVOS POR MÓDULOS -->
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
                Contestadas: <b id="lbl-total-contestadas">0</b> / 125
            </div>
            <button type="button" class="btn" style="width: auto; padding: 10px 20px; font-size: 14px;" onclick="enviarExamenDefinitivo()">
                <span>🚀 Calificar Examen</span>
            </button>
        </div>
    </div>

    <!-- PANTALLA DE CARGA -->
    <div id="pantalla-carga" class="card" style="display: none; text-align: center; padding: 40px 20px;">
        <div class="spinner"></div>
        <h2 style="font-size: 18px; color: #fff; margin: 0 0 6px 0;">Calificando Examen PAA...</h2>
        <p style="color: var(--muted); font-size: 13px; margin: 0;">Comparando respuestas contra la Hoja Base y Tabulador Oficial.</p>
    </div>

    <!-- PANTALLA DE RESULTADOS -->
    <div id="pantalla-resultados" class="card" style="display: none;">
        <div class="score-hero">
            <div style="font-size: 13px; font-weight: 700; color: #34d399; margin-bottom: 4px;">
                ✅ Examen Calificado con Éxito
            </div>
            <div style="font-size: 13px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px;">Puntaje Global Estimado</div>
            <div class="score-hero-number" id="res-paa-total">---</div>
            <div style="font-size: 12px; color: var(--muted);">Escala Oficial College Board PAA (400 a 1,600 pts)</div>

            <div class="score-row">
                <div>
                    <div class="score-col-num" id="res-paa-verbal" style="color: #60a5fa;">---</div>
                    <div class="score-col-lbl">Verbal (200-800)</div>
                </div>
                <div>
                    <div class="score-col-num" id="res-paa-mate" style="color: #f59e0b;">---</div>
                    <div class="score-col-lbl">Matemáticas (200-800)</div>
                </div>
                <div>
                    <div class="score-col-num" id="res-total-aciertos" style="color: #34d399;">--/115</div>
                    <div class="score-col-lbl">Aciertos Totales</div>
                </div>
            </div>
        </div>

        <h3 style="font-size: 15px; color: #fff; margin: 0 0 10px 0;">Desglose por Módulos:</h3>
        <div id="res-detalles-secciones" style="margin-bottom: 20px;"></div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <button type="button" class="btn" onclick="capturarSiguienteAlumno()">
                <span>✍️ Capturar a Otro Alumno</span>
            </button>
            <button type="button" class="btn btn-outline" onclick="window.print()">
                🖨️ Imprimir Resultados
            </button>
            <button type="button" class="btn btn-outline" onclick="window.close()" style="color: #94a3b8;">
                ✕ Cerrar Pestaña
            </button>
        </div>
    </div>

</div>

<script>
// Estado de respuestas del examen
let moduloActivo = 'lectura';
let respuestas = {
    lectura: {},
    redaccion: {},
    matematicas: {} // contiene 1-45 y 46-55
};

const ESPECIFICACIONES = {
    lectura: { min: 1, max: 45, tipo: 'opcion', label: 'Lectura (1-45)' },
    redaccion: { min: 1, max: 25, tipo: 'opcion', label: 'Redacción (1-25)' },
    matematicas: { min: 1, max: 45, tipo: 'opcion', label: 'Matemáticas 1 (1-45)' },
    matematicas2: { min: 46, max: 55, tipo: 'grid', label: 'Matemáticas 2 (46-55 - Abiertas)' }
};

document.addEventListener('DOMContentLoaded', () => {
    construirGrids();
    actualizarContadores();
});

// Construir todas las cuadrículas de preguntas
function construirGrids() {
    // 1. Lectura
    renderModuloOpcion('lectura', 1, 45, 'grid-lectura');
    // 2. Redacción
    renderModuloOpcion('redaccion', 1, 25, 'grid-redaccion');
    // 3. Matemáticas 1
    renderModuloOpcion('matematicas', 1, 45, 'grid-matematicas');
    // 4. Matemáticas 2 (Grid Numérico)
    renderModuloGrid('matematicas', 46, 55, 'grid-matematicas2');
}

function renderModuloOpcion(secKey, inicio, fin, contenedorId) {
    const cont = document.getElementById(contenedorId);
    cont.innerHTML = '';
    for (let i = inicio; i <= fin; i++) {
        const num = String(i);
        const card = document.createElement('div');
        card.className = 'q-card';
        card.id = `q-${secKey}-${num}`;
        card.innerHTML = `
            <div class="q-num">#${num}</div>
            <div class="bubble-options">
                ${['A', 'B', 'C', 'D'].map(opt => `
                    <button type="button" class="bubble-btn" data-sec="${secKey}" data-num="${num}" data-opt="${opt}" onclick="marcarRespuesta('${secKey}', '${num}', '${opt}')">${opt}</button>
                `).join('')}
                <button type="button" class="bubble-btn empty-btn active" data-sec="${secKey}" data-num="${num}" data-opt="" onclick="marcarRespuesta('${secKey}', '${num}', '')" title="Sin contestar">⚪</button>
            </div>
        `;
        cont.appendChild(card);
    }
}

function renderModuloGrid(secKey, inicio, fin, contenedorId) {
    const cont = document.getElementById(contenedorId);
    cont.innerHTML = '';
    for (let i = inicio; i <= fin; i++) {
        const num = String(i);
        const card = document.createElement('div');
        card.className = 'q-card';
        card.id = `q-mat2-${num}`;
        card.innerHTML = `
            <div class="q-num" style="color:#fbbf24;">#${num}</div>
            <div style="flex:1; max-width: 140px;">
                <input type="text" class="input-control" placeholder="Ej. 18 ó 1/2" 
                    style="padding: 6px 10px; font-size: 14px; text-transform: uppercase; font-weight: 800; text-align: center; border-color: rgba(245, 158, 11, 0.5);"
                    oninput="marcarRespuestaGrid('${num}', this.value)">
            </div>
        `;
        cont.appendChild(card);
    }
}

// Marcar opción múltiple
function marcarRespuesta(sec, num, opt) {
    if (!respuestas[sec]) respuestas[sec] = {};
    if (opt === '') {
        delete respuestas[sec][num];
    } else {
        respuestas[sec][num] = opt;
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

// Marcar reactivo abierto grid
function marcarRespuestaGrid(num, val) {
    const clean = val.trim().toUpperCase();
    if (!respuestas.matematicas) respuestas.matematicas = {};
    if (clean === '') {
        delete respuestas.matematicas[num];
    } else {
        respuestas.matematicas[num] = clean;
    }

    const card = document.getElementById(`q-mat2-${num}`);
    if (card) {
        card.classList.toggle('answered-grid', clean !== '');
    }

    actualizarContadores();
}

// Cambiar de módulo visual
function cambiarModulo(mod) {
    moduloActivo = mod;
    document.querySelectorAll('.sec-tab').forEach(t => t.classList.remove('active'));
    const btnTab = document.querySelector(`.sec-tab[onclick*="'${mod}'"]`);
    if (btnTab) btnTab.classList.add('active');

    document.getElementById('grid-lectura').style.display = mod === 'lectura' ? 'grid' : 'none';
    document.getElementById('grid-redaccion').style.display = mod === 'redaccion' ? 'grid' : 'none';
    document.getElementById('grid-matematicas').style.display = mod === 'matematicas' ? 'grid' : 'none';
    document.getElementById('grid-matematicas2').style.display = mod === 'matematicas2' ? 'grid' : 'none';

    // Actualizar indicador de sección en panel de voz
    const spec = ESPECIFICACIONES[mod];
    const elemSec = document.getElementById('voz-seccion-nombre');
    const elemTot = document.getElementById('voz-reactivo-total');
    const elemAct = document.getElementById('voz-reactivo-actual');
    if (elemSec) elemSec.textContent = spec.label;
    if (elemTot) elemTot.textContent = (spec.max - spec.min + 1);
    if (elemAct) elemAct.textContent = spec.min;
}

// Actualizar contadores globales y por módulo
function actualizarContadores() {
    let cLec = Object.keys(respuestas.lectura || {}).length;
    let cRed = Object.keys(respuestas.redaccion || {}).length;
    let cMat1 = 0;
    let cMat2 = 0;

    const matKeys = Object.keys(respuestas.matematicas || {});
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

// Limpiar módulo activo
function limpiarSeccionActiva() {
    const spec = ESPECIFICACIONES[moduloActivo];
    if (!confirm(`¿Borrar todas las respuestas del módulo "${spec.label}"?`)) return;

    if (moduloActivo === 'matematicas2') {
        for (let i = 46; i <= 55; i++) {
            delete respuestas.matematicas[String(i)];
            const inp = document.querySelector(`#q-mat2-${i} input`);
            if (inp) inp.value = '';
            const card = document.getElementById(`q-mat2-${i}`);
            if (card) card.classList.remove('answered-grid');
        }
    } else {
        const secKey = (moduloActivo === 'matematicas') ? 'matematicas' : moduloActivo;
        for (let i = spec.min; i <= spec.max; i++) {
            marcarRespuesta(secKey, String(i), '');
        }
    }
    actualizarContadores();
}

// ====================================================================
// MOTOR DE DICTADO POR VOZ (Web Speech API)
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
        alert('Tu navegador no soporta reconocimiento de voz nativo.\nRecomendamos abrir en Google Chrome o Safari.');
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
                        marcarRespuestaGrid(String(num), val);
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
                        marcarRespuesta(secKey, String(num), letra);
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
                alert('Permiso de micrófono denegado. Permite el acceso al micrófono en el navegador.');
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
// VERIFICAR ALUMNO Y ENVIAR EXAMEN
// ====================================================================
function verificarAlumnoExistente() {
    const nombre = document.getElementById('alumno-nombre').value.trim();
    const msgBox = document.getElementById('msg-verificacion-alumno');
    if (!nombre) {
        msgBox.style.display = 'none';
        return;
    }

    const formData = new FormData();
    formData.append('accion', 'verificar_alumno');
    formData.append('nombre', nombre);

    fetch('api_calificar.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (!d.exito) return;
            msgBox.style.display = 'block';
            if (d.existe) {
                if (d.bloqueado) {
                    msgBox.innerHTML = `<span style="color:#ef4444;">🔒 Alumno ya entregó examen con anterioridad (${d.ultima_evaluacion?.puntaje_paa_total || 0} pts).</span>`;
                } else {
                    msgBox.innerHTML = `<span style="color:#34d399;">🟢 Alumno con reintento habilitado.</span>`;
                }
            } else {
                msgBox.innerHTML = `<span style="color:#38bdf8;">✨ Nuevo aspirante registrado.</span>`;
            }
        })
        .catch(() => {});
}

function enviarExamenDefinitivo() {
    const nombre = document.getElementById('alumno-nombre').value.trim();
    const carrera = document.getElementById('alumno-carrera').value.trim();

    if (!nombre) {
        alert('Por favor, ingresa el nombre completo del alumno.');
        document.getElementById('alumno-nombre').focus();
        return;
    }

    const totalContestadas = parseInt(document.getElementById('lbl-total-contestadas').textContent, 10) || 0;
    if (totalContestadas < 115) {
        const omitidas = 125 - totalContestadas;
        if (!confirm(`El examen tiene ${omitidas} reactivos sin contestar.\n\n¿Estás seguro de calificarlo de forma definitiva?`)) {
            return;
        }
    }

    detenerDictadoVoz();

    document.getElementById('pantalla-formulario').style.display = 'none';
    document.getElementById('pantalla-carga').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });

    const formData = new FormData();
    formData.append('accion', 'confirmar_examen_alumno');
    formData.append('temp_id', 'manual_' + Date.now());
    formData.append('nombre_alumno', nombre);
    formData.append('carrera', carrera);
    formData.append('respuestas', JSON.stringify(respuestas));
    formData.append('respuestas_originales', JSON.stringify(respuestas));

    fetch('api_calificar.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('pantalla-carga').style.display = 'none';
        if (!data.exito) {
            alert('Error: ' + data.error);
            document.getElementById('pantalla-formulario').style.display = 'block';
            return;
        }

        mostrarResultados(data.evaluacion);
    })
    .catch(err => {
        document.getElementById('pantalla-carga').style.display = 'none';
        alert('Error al comunicar con el servidor calificador.');
        document.getElementById('pantalla-formulario').style.display = 'block';
    });
}

function mostrarResultados(ev) {
    document.getElementById('res-paa-total').textContent = ev.puntaje_paa_total;
    document.getElementById('res-paa-verbal').textContent = ev.puntaje_paa_verbal;
    document.getElementById('res-paa-mate').textContent = ev.puntaje_paa_mate;
    document.getElementById('res-total-aciertos').textContent = `${ev.total_aciertos}/${ev.total_preguntas}`;

    const contSec = document.getElementById('res-detalles-secciones');
    contSec.innerHTML = '';

    ['lectura', 'redaccion', 'matematicas'].forEach(k => {
        const s = ev.secciones[k];
        if (!s) return;

        if (k === 'matematicas' && s.detalles) {
            let ac1 = 0, err1 = 0, om1 = 0, tot1 = 0;
            let ac2 = 0, err2 = 0, om2 = 0, tot2 = 0;
            for (let i = 1; i <= 45; i++) {
                const d = s.detalles[String(i)];
                if (d) {
                    tot1++;
                    if (d.es_acierto) ac1++;
                    else if (d.marcada === 'OMITIDA' || !d.marcada) om1++;
                    else err1++;
                }
            }
            for (let i = 46; i <= 55; i++) {
                const d = s.detalles[String(i)];
                if (d) {
                    tot2++;
                    if (d.es_acierto) ac2++;
                    else if (d.marcada === 'OMITIDA' || !d.marcada) om2++;
                    else err2++;
                }
            }

            const p1 = tot1 > 0 ? Math.round((ac1 / tot1) * 100) : 0;
            const p2 = tot2 > 0 ? Math.round((ac2 / tot2) * 100) : 0;

            // Box Mat 1
            const box1 = document.createElement('div');
            box1.style.cssText = 'background: #1a243b; border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; border: 1px solid #263352;';
            box1.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <b style="color: #fff; font-size: 14px;">📐 Matemáticas 1 (Opción Múltiple 1-45)</b>
                    <span style="font-weight: 800; color: #34d399; font-size: 14px;">${ac1} / ${tot1 || 45} (${p1}%)</span>
                </div>
                <div style="font-size: 12px; color: #94a3b8;">Errores: ${err1} | Omitidas: ${om1}</div>
            `;
            contSec.appendChild(box1);

            // Box Mat 2
            const box2 = document.createElement('div');
            box2.style.cssText = 'background: linear-gradient(135deg, rgba(245, 158, 11, 0.12) 0%, #1a243b 100%); border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; border: 1px solid rgba(245, 158, 11, 0.35);';
            box2.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <b style="color: #fbbf24; font-size: 14px;">✏️ Matemáticas 2 (Abiertas 46-55)</b>
                    <span style="font-weight: 800; color: #fbbf24; font-size: 14px;">${ac2} / ${tot2 || 10} (${p2}%)</span>
                </div>
                <div style="font-size: 12px; color: #94a3b8;">Errores: ${err2} | Omitidas: ${om2}</div>
            `;
            contSec.appendChild(box2);
            return;
        }

        const box = document.createElement('div');
        box.style.cssText = 'background: #1a243b; border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; border: 1px solid #263352;';
        box.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                <b style="color: #fff; font-size: 14px;">${s.nombre}</b>
                <span style="font-weight: 800; color: #34d399; font-size: 14px;">${s.aciertos} / ${s.total_clave} (${s.porcentaje}%)</span>
            </div>
            <div style="font-size: 12px; color: #94a3b8;">Errores: ${s.errores} | Omitidas: ${s.omitidas}</div>
        `;
        contSec.appendChild(box);
    });

    document.getElementById('pantalla-resultados').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function capturarSiguienteAlumno() {
    respuestas = { lectura: {}, redaccion: {}, matematicas: {} };
    document.getElementById('alumno-nombre').value = '';
    document.getElementById('alumno-carrera').value = '';
    document.getElementById('msg-verificacion-alumno').style.display = 'none';

    construirGrids();
    actualizarContadores();
    cambiarModulo('lectura');

    document.getElementById('pantalla-resultados').style.display = 'none';
    document.getElementById('pantalla-formulario').style.display = 'block';
    window.scrollTo({ top: 0, behavior: 'smooth' });
    document.getElementById('alumno-nombre').focus();
}
</script>
</body>
</html>
