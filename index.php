<?php
/**
 * ====================================================================
 * Portal del Alumno / Router - Instituto Lumière
 * calificador.lumieresiga.com (Alumnos) | resultados.lumieresiga.com (Profesores)
 * ====================================================================
 */
require_once __DIR__ . '/config.php';

// Si el usuario entra por resultados.lumieresiga.com, mostrar directamente el Panel Docente
$hostActual = strtolower($_SERVER['HTTP_HOST'] ?? '');
if (strpos($hostActual, 'resultados.') !== false) {
    require __DIR__ . '/panel.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Calificador de Exámenes - Instituto Lumière</title>
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
            max-width: 600px;
            width: 100%;
        }

        /* Encabezado */
        .header {
            text-align: center;
            margin-bottom: 22px;
        }
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary-subtle);
            border: 1px solid rgba(192, 27, 47, 0.35);
            color: #f87171;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .brand-badge .dot {
            width: 8px; height: 8px; background: #ef4444; border-radius: 50%;
            box-shadow: 0 0 8px #ef4444;
        }
        h1 {
            margin: 4px 0 6px 0;
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
        }
        .subtitle {
            color: var(--muted);
            font-size: 14px;
            margin: 0;
        }

        /* Tarjeta principal */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 12px 30px rgba(0,0,0,0.4);
            margin-bottom: 20px;
        }

        /* Paso 1: Subida */
        .input-group {
            margin-bottom: 18px;
        }
        .input-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #cbd5e1;
        }
        .input-control {
            width: 100%;
            padding: 12px 14px;
            background: var(--card-surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            transition: 0.2s;
        }
        .input-control:focus {
            border-color: #f87171;
            box-shadow: 0 0 0 3px rgba(192,27,47,0.25);
        }

        .upload-area {
            border: 2px dashed #334155;
            border-radius: 14px;
            padding: 32px 16px;
            text-align: center;
            background: rgba(30, 41, 59, 0.4);
            cursor: pointer;
            transition: 0.2s;
            margin-bottom: 16px;
            position: relative;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color: #f87171;
            background: rgba(192, 27, 47, 0.05);
        }
        .upload-icon {
            font-size: 40px;
            margin-bottom: 8px;
        }
        .upload-title {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
        }
        .upload-desc {
            font-size: 13px;
            color: var(--muted);
        }

        .tips-box {
            background: rgba(15, 23, 42, 0.6);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.5;
            border-left: 3px solid var(--primary);
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            width: 100%;
            padding: 14px 20px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn:disabled { background: #475569; cursor: not-allowed; opacity: 0.6; }
        .btn-secondary {
            background: #334155;
            color: #f8fafc;
        }
        .btn-secondary:hover { background: #475569; }
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: #cbd5e1;
        }
        .btn-outline:hover { background: rgba(255,255,255,0.05); }

        /* Pantalla de Bloqueo */
        .blocked-card {
            text-align: center;
            padding: 30px 20px;
        }
        .blocked-icon { font-size: 48px; margin-bottom: 12px; }
        .score-pill {
            display: inline-block;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 18px;
            margin: 12px 0;
        }

        /* Pantalla de Carga */
        .loading-view {
            text-align: center;
            padding: 40px 20px;
            display: none;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(192, 27, 47, 0.2);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Paso 2: Revisión de Respuestas */
        .section-nav {
            display: flex;
            background: var(--card-surface);
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 16px;
            gap: 4px;
        }
        .sec-tab {
            flex: 1;
            padding: 8px 4px;
            font-size: 11.5px;
            font-weight: 700;
            text-align: center;
            border: none;
            background: transparent;
            color: var(--muted);
            border-radius: 8px;
            cursor: pointer;
            white-space: nowrap;
        }
        .sec-tab.active {
            background: var(--primary);
            color: #fff;
        }

        .questions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 8px;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 4px;
            margin-bottom: 20px;
        }
        .q-card {
            background: var(--card-surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .q-card.modified {
            border-color: var(--warning);
            background: rgba(245, 158, 11, 0.08);
        }
        .q-header {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
            color: #cbd5e1;
        }
        .q-tag {
            font-size: 10px;
            color: var(--warning);
            font-weight: 600;
        }
        .bubble-options {
            display: flex;
            gap: 4px;
            justify-content: space-between;
        }
        .bubble-btn {
            flex: 1;
            height: 28px;
            border-radius: 6px;
            border: 1px solid #334155;
            background: #0f172a;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.15s;
        }
        .bubble-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: #f87171;
            box-shadow: 0 0 6px rgba(192, 27, 47, 0.4);
        }
        .bubble-btn.empty-btn {
            color: #64748b;
            font-size: 10px;
        }
        .bubble-btn.empty-btn.active {
            background: #475569;
            color: #fff;
            border-color: #64748b;
        }

        /* Paso 3: Resultados */
        .score-hero {
            background: linear-gradient(135deg, rgba(192, 27, 47, 0.25) 0%, rgba(15, 23, 42, 0.8) 100%);
            border: 1px solid rgba(192, 27, 47, 0.35);
            border-radius: 14px;
            padding: 24px;
            text-align: center;
            margin-bottom: 20px;
        }
        .score-hero-title {
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 1px;
            color: #f87171;
            margin-bottom: 6px;
        }
        .score-hero-number {
            font-size: 48px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
            margin-bottom: 6px;
        }
        .score-hero-scale {
            font-size: 13px;
            color: var(--muted);
        }
        .score-row {
            display: flex;
            gap: 10px;
            margin-top: 18px;
        }
        .score-col {
            flex: 1;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 8px;
            text-align: center;
        }
        .score-col-num {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
        }
        .score-col-lbl {
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }

        /* Modal para solicitar reintento */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 16px;
        }
        .modal {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            max-width: 440px;
            width: 100%;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: var(--muted);
        }
        .footer a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 600;
        }
        .footer a:hover { text-decoration: underline; }

        .btn-siguiente {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
            color: #ffffff !important;
            font-size: 16px !important;
            font-weight: 700 !important;
            padding: 14px 20px !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4) !important;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .btn-siguiente:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.5) !important;
        }

        /* Botón y contenedor de Dictado por Voz */
        .btn-dictado {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important;
            color: #ffffff !important;
            border: none !important;
            padding: 12px 18px !important;
            border-radius: 12px !important;
            font-size: 14px !important;
            font-weight: 700 !important;
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
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%) !important;
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
    </style>
</head>
<body>

<div class="container">
    <!-- Encabezado Lumière -->
    <div class="header">
        <div class="brand-badge">
            <span class="dot"></span>
            INSTITUTO LUMIÈRE
        </div>
        <h1>Calificador de Exámenes PAA</h1>
        <p class="subtitle">Evaluación Automática College Board (400 a 1,600 pts)</p>
    </div>

    <!-- PANTALLA 1: SUBIR EXAMEN -->
    <div id="pantalla-subida" class="card">
        <div class="input-group">
            <label for="input-nombre">Nombre del Alumno <span style="font-size: 12px; color: var(--muted); font-weight: normal;">(Opcional: la IA lo leerá automáticamente de la hoja)</span>:</label>
            <input type="text" id="input-nombre" class="input-control" placeholder="La IA intentará leerlo de la hoja (o escríbelo aquí)" autocomplete="name">
        </div>

        <div class="upload-area" id="drop-zone" onclick="document.getElementById('input-foto').click()">
            <div class="upload-icon">📸</div>
            <div class="upload-title">Toma una foto o selecciona tu archivo</div>
            <div class="upload-desc">JPG, PNG o foto directa de cámara</div>
            <input type="file" id="input-foto" accept="image/*" capture="environment" style="display: none;" onchange="manejarArchivoSeleccionado(this.files[0])">
        </div>

        <div id="vista-previa-foto" style="display: none; margin-bottom: 16px; text-align: center;">
            <img id="img-preview" src="" alt="Vista Previa" style="max-height: 200px; border-radius: 10px; border: 1px solid var(--border);">
            <div style="margin-top: 6px;">
                <button type="button" class="btn btn-outline" style="width: auto; padding: 6px 14px; font-size: 12px;" onclick="limpiarFoto()">Cambiar Foto</button>
            </div>
        </div>

        <div class="tips-box">
            💡 <b>Consejos para una lectura perfecta:</b>
            <br>• Coloca la hoja sobre una mesa plana y bien iluminada.
            <br>• Procura que toda la hoja aparezca completa en el visor.
            <br>• Recuerda que solo tienes <b>1 intento oficial</b> para entregar.
        </div>

        <div style="margin-top: 20px;">
            <button id="btn-analizar" class="btn" onclick="analizarHoja()" disabled>
                <span>🔍 Analizar con Inteligencia Artificial</span>
            </button>
        </div>

        <div style="margin-top: 12px; text-align: center;">
            <a href="captura_manual.php" target="_blank" class="btn btn-outline" style="text-decoration: none; font-size: 13px; padding: 11px 16px; width: 100%; border: 1px dashed var(--border); color: #38bdf8; display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                🎙️ O capturar / dictar respuestas en otra pestaña (sin foto) ↗
            </a>
        </div>

        <div style="margin-top: 12px; text-align: center; font-size: 12px; color: var(--muted); padding-top: 10px; border-top: 1px solid var(--border);">
            ¿Eres profesor? <a href="captura_base.php" target="_blank" style="color: #fbbf24; text-decoration: none; font-weight: 700;">🎯 Cargar / Dictar Hoja Base Oficial ↗</a>
        </div>
    </div>

    <!-- PANTALLA DE BLOQUEO POR INTENTO PREVIO -->
    <div id="pantalla-bloqueo" class="card blocked-card" style="display: none;">
        <div class="blocked-icon">🔒</div>
        <h2 style="margin: 0 0 6px 0; font-size: 20px; color: #fff;">Examen Ya Entregado</h2>
        <p style="color: var(--muted); font-size: 14px; margin: 0 0 12px 0;">
            Hola <b id="bloqueo-nombre" style="color: #fff;"></b>, ya has completado y sellado tu examen oficial.
        </p>

        <div class="score-pill" id="bloqueo-puntaje">--- pts PAA</div>
        <p style="font-size: 12px; color: #94a3b8; margin: 0 0 20px 0;">Entregado el: <span id="bloqueo-fecha">---</span></p>

        <div id="bloqueo-estado-solicitud" style="margin-bottom: 16px;"></div>

        <button id="btn-solicitar-reintento" class="btn btn-secondary" onclick="abrirModalSolicitud()">
            <span>📩 Solicitar Nuevo Intento al Profesor</span>
        </button>

        <div style="margin-top: 14px;">
            <button class="btn btn-outline" onclick="reiniciarPortal()">Ingresar con otro nombre</button>
        </div>
    </div>

    <!-- PANTALLA DE CARGA / PROCESANDO -->
    <div id="pantalla-carga" class="card loading-view" style="display: none; text-align: center; padding: 28px 16px;">
        <div class="spinner"></div>
        <div style="margin: 16px 0 6px 0;">
            <span style="font-size: 36px; font-weight: 800; color: #38bdf8; font-variant-numeric: tabular-nums; letter-spacing: -0.5px;">
                ⏱️ <span id="contador-segundos">0.0</span>s
            </span>
        </div>
        <h2 style="font-size: 18px; color: #fff; margin: 0 0 6px 0;">Analizando Hoja con IA...</h2>
        <p id="loading-desc" style="color: var(--muted); font-size: 13px; margin: 0;">Extrayendo nombre y leyendo 125 reactivos en paralelo...</p>
    </div>

    <!-- PANTALLA 2: REVISIÓN Y CORRECCIÓN ÚNICA -->
    <div id="pantalla-revision" class="card" style="display: none;">
        <div style="text-align: center; margin-bottom: 16px;">
            <div style="margin-bottom: 8px;">
                <span id="badge-tiempo-total" style="background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.35); color: #38bdf8; font-size: 13px; font-weight: 700; padding: 5px 14px; border-radius: 12px; display: inline-flex; align-items: center; gap: 6px;">
                    ⚡ Tiempo de respuesta IA: <b id="tiempo-ia-segundos">0.0</b>s
                </span>
            </div>
            <span style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #fbbf24; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 12px;">
                ⚠️ Revisión Única Previa a la Calificación
            </span>
            <h2 style="font-size: 20px; color: #fff; margin: 10px 0 4px 0;">Verifica tus Respuestas</h2>
            <p style="color: var(--muted); font-size: 13px; margin: 0;">
                Si la IA malinterpretó alguna burbuja rellenada, cámbiala haciendo clic sobre la letra correspondiente.
            </p>
        </div>

        <div class="input-group">
            <label for="revision-nombre">Nombre del Alumno (Verifica o edita):</label>
            <div id="badge-nombre-detectado" style="display: none; margin-bottom: 8px; padding: 8px 12px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; font-size: 13px; color: #34d399;">
                ✨ Nombre detectado de la hoja: <b id="lbl-nombre-detectado"></b>
            </div>
            <input type="text" id="revision-nombre" class="input-control" placeholder="Nombre del Alumno">
        </div>

        <!-- Barra de Dictado por Voz encima de las secciones -->
        <div class="voice-dictation-container" style="margin-bottom: 16px;">
            <div style="display: flex; gap: 8px; align-items: center;">
                <button id="btn-dictado-voz" type="button" class="btn-dictado" onclick="toggleDictadoVoz()">
                    <span id="icono-mic">🎙️</span>
                    <span id="texto-btn-voz">Dictar Respuestas por Voz</span>
                </button>
                <button id="btn-limpiar-sec" type="button" class="btn btn-outline" style="width: auto; padding: 12px 14px; font-size: 13px;" onclick="limpiarSeccionActiva()" title="Limpiar respuestas de esta sección">
                    🧹 Limpiar Sección
                </button>
            </div>
            
            <div id="panel-voz-estado" style="display: none; margin-top: 10px; padding: 12px 16px; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.35); border-radius: 12px; text-align: center;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 6px;">
                    <span class="pulse-dot"></span>
                    <b style="color: #38bdf8; font-size: 15px;">Dicta tus resultados</b>
                </div>
                <p style="margin: 0; font-size: 12px; color: var(--muted);">
                    Habla continuo o pausado: <i>"A, B, D, C, C, D, B..."</i> — Las burbujas se rellenarán en vivo conforme hables.
                </p>
                <div id="transcripcion-en-vivo" style="margin-top: 8px; font-size: 15px; color: #fff; font-weight: 800; min-height: 22px; font-family: monospace; letter-spacing: 2px;">
                    Escuchando micrófono...
                </div>
                <div style="margin-top: 6px; font-size: 12px; color: #94a3b8;">
                    Sección: <b id="voz-seccion-nombre" style="color: #fff; text-transform: capitalize;">Lectura</b> | 
                    Reactivo actual: <b id="voz-reactivo-actual" style="color: #38bdf8;">1</b> de <span id="voz-reactivo-total">45</span>
                </div>
            </div>
        </div>

        <div class="section-nav">
            <button class="sec-tab active" onclick="cambiarSeccionRevision('lectura')">Lectura (45)</button>
            <button class="sec-tab" onclick="cambiarSeccionRevision('redaccion')">Redacción (25)</button>
            <button class="sec-tab" onclick="cambiarSeccionRevision('matematicas')">Matemáticas 1 (45)</button>
            <button class="sec-tab" onclick="cambiarSeccionRevision('matematicas2')">Matemáticas 2 (10)</button>
        </div>

        <!-- Contenedores de preguntas por sección -->
        <div id="grid-lectura" class="questions-grid"></div>
        <div id="grid-redaccion" class="questions-grid" style="display: none;"></div>
        <div id="grid-matematicas" class="questions-grid" style="display: none;"></div>
        <div id="grid-matematicas2" class="questions-grid" style="display: none;"></div>

        <div id="aviso-modificaciones" style="font-size: 12px; color: #fbbf24; margin-bottom: 16px; text-align: center; display: none;">
            ✏️ Has rectificado <span id="conteo-modificaciones">0</span> respuestas.
        </div>

        <div style="margin-top: 16px; display: flex; flex-direction: column; gap: 8px;">
            <button class="btn" onclick="confirmarEntregaDefinitiva()">
                <span>🚀 Confirmar y Guardar Calificación</span>
            </button>
            <button type="button" class="btn btn-outline" onclick="siguienteExamen(false)">
                ✕ Descartar y Calificar Otro Examen
            </button>
        </div>
    </div>

    <!-- PANTALLA 3: RESULTADOS OFICIALES -->
    <div id="pantalla-resultados" class="card" style="display: none;">
        <!-- Botón superior para pasar directo al siguiente examen -->
        <div style="margin-bottom: 20px;">
            <button class="btn-siguiente" onclick="siguienteExamen(true)">
                <span>📸 Calificar Siguiente Examen</span>
            </button>
        </div>

        <div class="score-hero">
            <div style="margin-bottom: 8px;">
                <span style="background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; font-size: 13px; font-weight: 700; padding: 4px 12px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px;">
                    ⚡ Calificado en <b id="res-duracion">0.0</b>s con Gemini AI
                </span>
            </div>
            <div class="score-hero-title">Puntaje Global Estimado</div>
            <div class="score-hero-number" id="res-paa-total">---</div>
            <div class="score-hero-scale">Escala Oficial College Board PAA (400 a 1,600 pts)</div>

            <div class="score-row">
                <div class="score-col">
                    <div class="score-col-num" id="res-paa-verbal">---</div>
                    <div class="score-col-lbl">Verbal (200-800)</div>
                </div>
                <div class="score-col">
                    <div class="score-col-num" id="res-paa-mate">---</div>
                    <div class="score-col-lbl">Matemáticas (200-800)</div>
                </div>
                <div class="score-col">
                    <div class="score-col-num" id="res-total-aciertos">--/115</div>
                    <div class="score-col-lbl">Aciertos Totales</div>
                </div>
            </div>
        </div>

        <h3 style="font-size: 16px; color: #fff; margin: 0 0 12px 0;">Desglose por Secciones:</h3>

        <div id="detalles-secciones"></div>

        <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
            <button class="btn-siguiente" onclick="siguienteExamen(true)">
                <span>📸 Calificar Siguiente Examen</span>
            </button>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ Imprimir / Guardar Resultados</button>
        </div>

        <p style="font-size: 13px; color: #10b981; text-align: center; margin-top: 14px; font-weight: 600;">
            ✅ Calificación guardada exitosamente en el sistema.
        </p>
    </div>

    <!-- Pie de página con enlace al panel docente -->
    <div class="footer">
        Calificador Lumière PAA © <?php echo date('Y'); ?> | 
        <a href="https://resultados.lumieresiga.com" target="_blank">🔐 Panel de Resultados Docente (resultados.lumieresiga.com)</a>
    </div>
</div>

<!-- MODAL SOLICITUD DE REINTENTO -->
<div id="modal-solicitud" class="modal-overlay">
    <div class="modal">
        <h3 style="color: #fff; margin: 0 0 8px 0; font-size: 18px;">Solicitar Nuevo Intento</h3>
        <p style="color: var(--muted); font-size: 13px; margin: 0 0 16px 0;">
            Explica brevemente por qué necesitas volver a subir tu examen (ej: foto movida, mala iluminación):
        </p>
        <textarea id="motivo-reintento" class="input-control" rows="3" placeholder="Escribe aquí el motivo..."></textarea>
        <div style="display: flex; gap: 8px; margin-top: 16px;">
            <button class="btn btn-secondary" style="flex: 1;" onclick="cerrarModalSolicitud()">Cancelar</button>
            <button class="btn" style="flex: 1;" onclick="enviarSolicitudReintento()">Enviar Solicitud</button>
        </div>
    </div>
</div>

<script>
let archivoSeleccionado = null;
let respuestasDetectadas = null;
let respuestasOriginales = null;
let tempIdActual = null;
let alumnoVerificado = null;

let timerInterval = null;
let timerStart = 0;

function iniciarCronometro() {
    timerStart = Date.now();
    const elem = document.getElementById('contador-segundos');
    const desc = document.getElementById('loading-desc');
    if (elem) elem.textContent = '0.0';
    if (timerInterval) clearInterval(timerInterval);
    timerInterval = setInterval(() => {
        const segNum = (Date.now() - timerStart) / 1000;
        const seg = segNum.toFixed(1);
        if (elem) elem.textContent = seg;
        if (desc) {
            if (segNum < 1.2) {
                desc.textContent = 'Enviando imagen al servidor...';
            } else if (segNum < 3.2) {
                desc.textContent = 'Leyendo 125 reactivos y nombre con 5 núcleos IA en paralelo...';
            } else {
                desc.textContent = 'Verificando cuadrículas numéricas y armando reporte...';
            }
        }
    }, 100);
}

function detenerCronometro(duracionServidor) {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    const finalSeg = duracionServidor ? Number(duracionServidor).toFixed(1) : (timerStart ? ((Date.now() - timerStart) / 1000).toFixed(1) : '0.0');
    const elemBadge = document.getElementById('tiempo-ia-segundos');
    if (elemBadge) elemBadge.textContent = finalSeg;
    const elemRes = document.getElementById('res-duracion');
    if (elemRes) elemRes.textContent = finalSeg;
    return finalSeg;
}

// Validar nombre al escribir
document.getElementById('input-nombre').addEventListener('blur', function() {
    verificarNombreAlumno(this.value.trim());
});

function verificarNombreAlumno(nombre) {
    if (!nombre) return;
    fetch('api_calificar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'accion=verificar_alumno&nombre=' + encodeURIComponent(nombre)
    })
    .then(r => r.json())
    .then(data => {
        if (!data.exito) return;
        alumnoVerificado = data;
        if (data.existe && data.bloqueado) {
            mostrarPantallaBloqueo(data);
        }
    })
    .catch(() => {});
}

function mostrarPantallaBloqueo(data) {
    document.getElementById('pantalla-subida').style.display = 'none';
    document.getElementById('pantalla-bloqueo').style.display = 'block';
    document.getElementById('bloqueo-nombre').textContent = data.nombre_oficial || document.getElementById('input-nombre').value;

    const evalData = data.ultima_evaluacion || {};
    document.getElementById('bloqueo-puntaje').textContent = (evalData.puntaje_paa_total || '---') + ' pts PAA';
    document.getElementById('bloqueo-fecha').textContent = evalData.fecha || 'Recientemente';

    const divSol = document.getElementById('bloqueo-estado-solicitud');
    const btnSol = document.getElementById('btn-solicitar-reintento');

    if (data.solicitud_reintento) {
        divSol.innerHTML = '<span style="color: #fbbf24; font-size: 13px; font-weight: 600;">⏳ Tienes una solicitud de reintento pendiente de autorización por tu profesor.</span>';
        btnSol.style.display = 'none';
    } else {
        divSol.innerHTML = '';
        btnSol.style.display = 'block';
    }
}

function reiniciarPortal() {
    document.getElementById('pantalla-bloqueo').style.display = 'none';
    document.getElementById('pantalla-subida').style.display = 'block';
    document.getElementById('input-nombre').value = '';
    alumnoVerificado = null;
    limpiarFoto();
}

function manejarArchivoSeleccionado(file) {
    if (!file) return;
    archivoSeleccionado = file;

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('img-preview').src = e.target.result;
        document.getElementById('vista-previa-foto').style.display = 'block';
        document.getElementById('drop-zone').style.display = 'none';
        document.getElementById('btn-analizar').disabled = false;
    };
    reader.readAsDataURL(file);
}

function limpiarFoto() {
    archivoSeleccionado = null;
    document.getElementById('input-foto').value = '';
    document.getElementById('vista-previa-foto').style.display = 'none';
    document.getElementById('drop-zone').style.display = 'block';
    document.getElementById('btn-analizar').disabled = true;
}

// Optimiza fotografías pesadas de celulares (reduce de 15MB a <1MB en milisegundos)
async function optimizarFoto(file) {
    if (!file) return file;
    // Si el archivo ya pesa menos de 1 MB y es imagen estándar, enviarlo directamente
    if (file.size < 1024 * 1024) return file;

    return new Promise((resolve) => {
        try {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                const maxDim = 1600;
                let w = img.width;
                let h = img.height;

                if (w > maxDim || h > maxDim) {
                    if (w > h) {
                        h = Math.round((h * maxDim) / w);
                        w = maxDim;
                    } else {
                        w = Math.round((w * maxDim) / h);
                        h = maxDim;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);

                canvas.toBlob((blob) => {
                    if (blob && blob.size < file.size) {
                        const optimizado = new File([blob], 'examen_foto.jpg', {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        });
                        console.log(`Foto optimizada: ${(file.size / (1024*1024)).toFixed(1)}MB -> ${(optimizado.size / 1024).toFixed(0)}KB (${w}x${h})`);
                        resolve(optimizado);
                    } else {
                        resolve(file);
                    }
                }, 'image/jpeg', 0.85);
            };
            img.onerror = () => {
                URL.revokeObjectURL(url);
                resolve(file);
            };
            img.src = url;
        } catch(e) {
            console.warn('Fallback imagen original:', e);
            resolve(file);
        }
    });
}

// Analizar hoja con IA
async function analizarHoja() {
    if (!archivoSeleccionado) {
        alert('Por favor selecciona o toma una fotografía de tu examen.');
        return;
    }

    const nombre = document.getElementById('input-nombre').value.trim();

    document.getElementById('pantalla-subida').style.display = 'none';
    document.getElementById('pantalla-carga').style.display = 'block';
    const descLoading = document.getElementById('loading-desc');
    if (descLoading) descLoading.textContent = 'Optimizando fotografía del examen...';
    iniciarCronometro();

    try {
        const fotoFinal = await optimizarFoto(archivoSeleccionado);
        if (descLoading) descLoading.textContent = 'Extrayendo nombre y leyendo 125 reactivos en paralelo...';

        const formData = new FormData();
        formData.append('accion', 'analizar_hoja_alumno');
        formData.append('foto', fotoFinal);
        formData.append('nombre_previo', nombre);

        const response = await fetch('api_calificar.php', {
            method: 'POST',
            body: formData
        });

        const rawText = await response.text();
        let data;
        try {
            data = JSON.parse(rawText);
        } catch (jsonErr) {
            console.error('Respuesta no JSON del servidor:', rawText);
            throw new Error(`El servidor devolvió un error (Código HTTP ${response.status}). Intenta nuevamente.`);
        }

        const seg = detenerCronometro(data.duracion);
        document.getElementById('pantalla-carga').style.display = 'none';

        if (!data.exito) {
            if (data.bloqueado) {
                mostrarPantallaBloqueo({
                    nombre_oficial: data.alumno_nombre,
                    solicitud_reintento: data.solicitud_reintento
                });
                return;
            }
            alert('Aviso: ' + (data.error || 'No se pudieron detectar respuestas') + (data.consejo ? '\n\n💡 ' + data.consejo : ''));
            document.getElementById('pantalla-subida').style.display = 'block';
            return;
        }

        respuestasDetectadas = JSON.parse(JSON.stringify(data.respuestas_detectadas));
        respuestasOriginales = JSON.parse(JSON.stringify(data.respuestas_detectadas));
        tempIdActual = data.temp_id;

        // Nombre detectado automáticamente por la IA o sugerido
        const nombreDetectado = (data.nombre_detectado || '').trim();
        const nombreFinal = nombreDetectado || nombre || '';

        document.getElementById('revision-nombre').value = nombreFinal;
        document.getElementById('input-nombre').value = nombreFinal;

        const badge = document.getElementById('badge-nombre-detectado');
        const lblBadge = document.getElementById('lbl-nombre-detectado');
        if (badge && lblBadge) {
            if (nombreDetectado) {
                lblBadge.textContent = nombreDetectado;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }

        construirGridsRevision();
        document.getElementById('pantalla-revision').style.display = 'block';

    } catch (err) {
        detenerCronometro();
        document.getElementById('pantalla-carga').style.display = 'none';
        console.error('Error al analizar la hoja:', err);
        let errorMsg = err.message || '';
        if (!errorMsg || errorMsg.includes('Failed to fetch') || errorMsg.includes('NetworkError')) {
            errorMsg = 'Error de conexión con el servidor. Revisa tu conexión a internet o intenta nuevamente.';
        }
        alert(errorMsg);
        document.getElementById('pantalla-subida').style.display = 'block';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Construcción de Grids para Revisión
function construirGridsRevision() {
    const specs = {
        lectura: { start: 1, end: 45, cont: document.getElementById('grid-lectura'), isGrid: false, dataSec: 'lectura' },
        redaccion: { start: 1, end: 25, cont: document.getElementById('grid-redaccion'), isGrid: false, dataSec: 'redaccion' },
        matematicas: { start: 1, end: 45, cont: document.getElementById('grid-matematicas'), isGrid: false, dataSec: 'matematicas' },
        matematicas2: { start: 46, end: 55, cont: document.getElementById('grid-matematicas2'), isGrid: true, dataSec: 'matematicas' }
    };

    ['lectura', 'redaccion', 'matematicas', 'matematicas2'].forEach(secId => {
        const { start, end, cont, isGrid, dataSec } = specs[secId];
        if (!cont) return;
        cont.innerHTML = '';
        const respSec = respuestasDetectadas[dataSec] || {};
        const respOrigSec = respuestasOriginales[dataSec] || {};

        for (let i = start; i <= end; i++) {
            const num = String(i);
            const val = respSec[num] || '';
            const valOrig = respOrigSec[num] || '';
            const esModificada = val !== valOrig;

            const card = document.createElement('div');
            card.className = 'q-card' + (esModificada ? ' modified' : '');
            card.id = `q-${secId}-${num}`;

            if (isGrid) {
                card.style.borderColor = 'rgba(245, 158, 11, 0.4)';
                card.style.background = 'linear-gradient(135deg, rgba(245, 158, 11, 0.08) 0%, rgba(15, 23, 42, 0.7) 100%)';
                card.innerHTML = `
                    <div class="q-header">
                        <span style="color:#f59e0b; font-weight: 700;">#${num} (Respuesta Abierta)</span>
                        ${esModificada ? '<span class="q-tag">Modificada</span>' : ''}
                    </div>
                    <div style="padding: 6px 0;">
                        <input type="text" value="${escapeHtml(val)}" 
                            placeholder="ej. 15, 1/2, 3.5, 9" 
                            style="width: 100%; padding: 8px 10px; font-size: 15px; text-transform: uppercase; font-weight: 800; text-align: center; background: #0f172a; border: 1px solid #f59e0b; color: #fff; border-radius: 8px; letter-spacing: 1px;"
                            onchange="modificarRespuestaGrid('matematicas2', '${num}', this.value)">
                    </div>
                `;
            } else {
                card.innerHTML = `
                    <div class="q-header">
                        <span>#${num}</span>
                        ${esModificada ? '<span class="q-tag">Modificada</span>' : ''}
                    </div>
                    <div class="bubble-options">
                        ${['A', 'B', 'C', 'D'].map(opt => `
                            <button type="button" class="bubble-btn ${val === opt ? 'active' : ''}" 
                                onclick="seleccionarOpcion('${dataSec}', '${num}', '${opt}')">${opt}</button>
                        `).join('')}
                        <button type="button" class="bubble-btn empty-btn ${val === '' ? 'active' : ''}" 
                            onclick="seleccionarOpcion('${dataSec}', '${num}', '')" title="Sin contestar">⚪</button>
                    </div>
                `;
            }
            cont.appendChild(card);
        }
    });

    actualizarConteoModificaciones();
}

function modificarRespuestaGrid(sec, num, val) {
    const secKey = (sec === 'matematicas2') ? 'matematicas' : sec;
    if (!respuestasDetectadas[secKey]) respuestasDetectadas[secKey] = {};
    const clean = val.trim().toUpperCase();
    if (clean === '') {
        delete respuestasDetectadas[secKey][num];
    } else {
        respuestasDetectadas[secKey][num] = clean;
    }
    const valOrig = (respuestasOriginales[secKey] && respuestasOriginales[secKey][num]) || '';
    const esModificada = clean !== valOrig;
    const card = document.getElementById(`q-matematicas2-${num}`) || document.getElementById(`q-matematicas-${num}`) || document.getElementById(`q-${sec}-${num}`);
    if (card) {
        card.className = 'q-card' + (esModificada ? ' modified' : '');
        const tagSpan = card.querySelector('.q-header');
        tagSpan.innerHTML = `<span style="color:#f59e0b; font-weight: 700;">#${num} (Respuesta Abierta)</span>${esModificada ? '<span class="q-tag">Modificada</span>' : ''}`;
    }
    actualizarConteoModificaciones();
}

// ====================================================================
// MOTOR DE DICTADO POR VOZ EN TIEMPO REAL (Web Speech API)
// ====================================================================
let seccionActiva = 'lectura';
let reconocimientoVoz = null;
let dictandoActivo = false;

function cambiarSeccionRevision(sec) {
    seccionActiva = sec;
    document.querySelectorAll('.sec-tab').forEach(t => t.classList.remove('active'));
    
    // Marcar pestaña activa
    const btnTab = document.querySelector(`.sec-tab[onclick*="'${sec}'"]`) || document.querySelector(`.sec-tab[onclick*="${sec}"]`);
    if (btnTab) {
        btnTab.classList.add('active');
    } else if (typeof event !== 'undefined' && event && event.target && event.target.classList) {
        event.target.classList.add('active');
    }

    document.getElementById('grid-lectura').style.display = sec === 'lectura' ? 'grid' : 'none';
    document.getElementById('grid-redaccion').style.display = sec === 'redaccion' ? 'grid' : 'none';
    document.getElementById('grid-matematicas').style.display = sec === 'matematicas' ? 'grid' : 'none';
    const gridMat2 = document.getElementById('grid-matematicas2');
    if (gridMat2) gridMat2.style.display = sec === 'matematicas2' ? 'grid' : 'none';

    // Actualizar indicador de sección en el panel de voz
    const nombres = {
        lectura: 'Lectura (1-45)',
        redaccion: 'Redacción (1-25)',
        matematicas: 'Matemáticas 1 (1-45)',
        matematicas2: 'Matemáticas 2 (46-55)'
    };
    const totales = { lectura: 45, redaccion: 25, matematicas: 45, matematicas2: 10 };
    const elemSec = document.getElementById('voz-seccion-nombre');
    const elemTot = document.getElementById('voz-reactivo-total');
    if (elemSec) elemSec.textContent = nombres[sec] || sec;
    if (elemTot) elemTot.textContent = totales[sec] || 45;

    // Resetear contador visual de reactivo dictado para la nueva sección
    const elemAct = document.getElementById('voz-reactivo-actual');
    if (elemAct) elemAct.textContent = (sec === 'matematicas2') ? '46' : '1';
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
        alert('Tu navegador no soporta reconocimiento de voz nativo.\n\nTe recomendamos abrir este portal en Google Chrome en Android/PC o Safari en iPhone/iPad.');
        return;
    }

    try {
        if (reconocimientoVoz) {
            reconocimientoVoz.abort();
        }

        reconocimientoVoz = new SpeechRecognition();
        reconocimientoVoz.lang = 'es-MX';
        reconocimientoVoz.continuous = true;
        reconocimientoVoz.interimResults = true;
        reconocimientoVoz.maxAlternatives = 1;

        dictandoActivo = true;

        // UI Feedback
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

            if (seccionActiva === 'matematicas2') {
                letras.forEach((val, idx) => {
                    const num = 46 + idx;
                    if (num <= 55) {
                        modificarRespuestaGrid('matematicas2', String(num), val);
                        const inp = document.querySelector(`#q-matematicas2-${num} input`);
                        if (inp) inp.value = val;
                    }
                });

                const ultimasLetras = letras.slice(-5).join(' ');
                if (transVivo) transVivo.textContent = ultimasLetras || 'Escuchando...';

                const reactivoSiguiente = Math.min(46 + letras.length, 55);
                const elemAct = document.getElementById('voz-reactivo-actual');
                if (elemAct) elemAct.textContent = reactivoSiguiente;

                const targetNum = Math.min(46 + (letras.length ? (letras.length - 1) : 0), 55);
                const cardAct = document.getElementById(`q-matematicas2-${targetNum}`);
                if (cardAct) cardAct.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                const specs = { lectura: 45, redaccion: 25, matematicas: 45 };
                const totalSec = specs[seccionActiva] || 45;

                letras.forEach((letra, idx) => {
                    const num = idx + 1;
                    if (num <= totalSec) {
                        seleccionarOpcion(seccionActiva, String(num), letra);
                    }
                });

                const ultimasLetras = letras.slice(-10).join(' ');
                if (transVivo) transVivo.textContent = ultimasLetras || 'Escuchando...';

                const reactivoSiguiente = Math.min(letras.length + 1, totalSec);
                const elemAct = document.getElementById('voz-reactivo-actual');
                if (elemAct) elemAct.textContent = reactivoSiguiente;

                const cardAct = document.getElementById(`q-${seccionActiva}-${letras.length || 1}`);
                if (cardAct) cardAct.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        };

        reconocimientoVoz.onerror = function(event) {
            console.warn('Reconocimiento de voz:', event.error);
            if (event.error === 'not-allowed') {
                alert('Permiso de micrófono denegado. Permite el acceso al micrófono en el navegador para dictar tus respuestas.');
                detenerDictadoVoz();
            }
        };

        reconocimientoVoz.onend = function() {
            // Reconectar si sigue activo (algunos navegadores detienen tras silencio)
            if (dictandoActivo) {
                try { reconocimientoVoz.start(); } catch(e) {}
            }
        };

        reconocimientoVoz.start();

    } catch(err) {
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
    if (txtVoz) txtVoz.textContent = 'Dictar Respuestas por Voz';
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
        } else if (p === 'blanco' || p === 'paso' || p === 'omitida' || p === 'vacio' || p === 'vacío') {
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

function limpiarSeccionActiva() {
    const titulo = (seccionActiva === 'matematicas2') ? 'MATEMÁTICAS 2 (46 a 55)' : ((seccionActiva === 'matematicas') ? 'MATEMÁTICAS 1 (1 a 45)' : seccionActiva.toUpperCase());
    if (!confirm(`¿Deseas borrar todas las respuestas marcadas en "${titulo}"?`)) {
        return;
    }
    if (seccionActiva === 'matematicas2') {
        for (let i = 46; i <= 55; i++) {
            modificarRespuestaGrid('matematicas2', String(i), '');
            const inp = document.querySelector(`#q-matematicas2-${i} input`);
            if (inp) inp.value = '';
        }
    } else if (seccionActiva === 'matematicas') {
        for (let i = 1; i <= 45; i++) {
            seleccionarOpcion('matematicas', String(i), '');
        }
    } else {
        const specs = { lectura: 45, redaccion: 25 };
        const total = specs[seccionActiva] || 45;
        for (let i = 1; i <= total; i++) {
            seleccionarOpcion(seccionActiva, String(i), '');
        }
    }
    const elemAct = document.getElementById('voz-reactivo-actual');
    if (elemAct) elemAct.textContent = (seccionActiva === 'matematicas2') ? '46' : '1';
    const trans = document.getElementById('transcripcion-en-vivo');
    if (trans) trans.textContent = 'Respuestas limpiadas. Listo para dictar.';
}

function iniciarRevisionManualVoz() {
    window.open('captura_manual.php', '_blank');
}

function seleccionarOpcion(sec, num, opt) {
    if (!respuestasDetectadas[sec]) respuestasDetectadas[sec] = {};

    if (opt === '') {
        delete respuestasDetectadas[sec][num];
    } else {
        respuestasDetectadas[sec][num] = opt;
    }

    const card = document.getElementById(`q-${sec}-${num}`);
    const valOrig = (respuestasOriginales[sec] && respuestasOriginales[sec][num]) || '';
    const esModificada = opt !== valOrig;

    card.className = 'q-card' + (esModificada ? ' modified' : '');

    const tagSpan = card.querySelector('.q-header');
    tagSpan.innerHTML = `<span>#${num}</span>${esModificada ? '<span class="q-tag">Modificada</span>' : ''}`;

    card.querySelectorAll('.bubble-btn').forEach(btn => {
        if (btn.classList.contains('empty-btn')) {
            btn.classList.toggle('active', opt === '');
        } else {
            btn.classList.toggle('active', btn.textContent.trim() === opt);
        }
    });

    actualizarConteoModificaciones();
}

function actualizarConteoModificaciones() {
    let modCount = 0;
    ['lectura', 'redaccion', 'matematicas'].forEach(sec => {
        const cur = respuestasDetectadas[sec] || {};
        const orig = respuestasOriginales[sec] || {};
        const maxTotal = (sec === 'redaccion') ? 25 : (sec === 'matematicas' ? 55 : 45);
        for (let i = 1; i <= maxTotal; i++) {
            const num = String(i);
            const cVal = cur[num] || '';
            const oVal = orig[num] || '';
            if (cVal !== oVal) modCount++;
        }
    });

    const divAviso = document.getElementById('aviso-modificaciones');
    if (modCount > 0) {
        divAviso.style.display = 'block';
        document.getElementById('conteo-modificaciones').textContent = modCount;
    } else {
        divAviso.style.display = 'none';
    }
}

// Confirmación Definitiva
function confirmarEntregaDefinitiva() {
    const nombre = document.getElementById('revision-nombre').value.trim();
    if (!nombre) {
        alert('Por favor escribe o verifica el nombre del alumno antes de confirmar.');
        return;
    }

    if (!confirm(`¿Confirmas la calificación del examen para "${nombre}"?`)) {
        return;
    }

    document.getElementById('pantalla-revision').style.display = 'none';
    document.getElementById('pantalla-carga').style.display = 'block';
    document.getElementById('loading-desc').textContent = 'Guardando y calificando definitivamente...';

    const formData = new FormData();
    formData.append('accion', 'confirmar_examen_alumno');
    formData.append('temp_id', tempIdActual);
    formData.append('nombre_alumno', nombre);
    formData.append('respuestas', JSON.stringify(respuestasDetectadas));
    formData.append('respuestas_originales', JSON.stringify(respuestasOriginales));

    fetch('api_calificar.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('pantalla-carga').style.display = 'none';

        if (!data.exito) {
            alert('Error: ' + data.error);
            document.getElementById('pantalla-revision').style.display = 'block';
            return;
        }

        mostrarResultadosOficiales(data.evaluacion);
    })
    .catch(() => {
        document.getElementById('pantalla-carga').style.display = 'none';
        alert('Error al confirmar el examen.');
        document.getElementById('pantalla-revision').style.display = 'block';
    });
}

// Función para reiniciar y calificar el siguiente examen de forma inmediata
function siguienteExamen(abrirCamara = true) {
    archivoSeleccionado = null;
    respuestasDetectadas = null;
    respuestasOriginales = null;
    tempIdActual = null;
    alumnoVerificado = null;
    detenerDictadoVoz();

    // Limpiar campos
    document.getElementById('input-nombre').value = '';
    document.getElementById('revision-nombre').value = '';
    document.getElementById('input-foto').value = '';
    document.getElementById('img-preview').src = '';
    document.getElementById('vista-previa-foto').style.display = 'none';
    document.getElementById('drop-zone').style.display = 'block';
    document.getElementById('btn-analizar').disabled = true;

    const badge = document.getElementById('badge-nombre-detectado');
    if (badge) badge.style.display = 'none';

    // Regresar a la pantalla de subida
    document.getElementById('pantalla-resultados').style.display = 'none';
    document.getElementById('pantalla-revision').style.display = 'none';
    document.getElementById('pantalla-carga').style.display = 'none';
    document.getElementById('pantalla-bloqueo').style.display = 'none';
    document.getElementById('pantalla-subida').style.display = 'block';

    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Activar selector / cámara automáticamente para agilizar el flujo de los profesores
    if (abrirCamara) {
        setTimeout(() => {
            const inputFoto = document.getElementById('input-foto');
            if (inputFoto) inputFoto.click();
        }, 300);
    }
}

function mostrarResultadosOficiales(ev) {
    document.getElementById('res-paa-total').textContent = ev.puntaje_paa_total;
    document.getElementById('res-paa-verbal').textContent = ev.puntaje_paa_verbal;
    document.getElementById('res-paa-mate').textContent = ev.puntaje_paa_mate;
    document.getElementById('res-total-aciertos').textContent = `${ev.total_aciertos}/${ev.total_preguntas}`;

    const contSec = document.getElementById('detalles-secciones');
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

            // Matemáticas 1
            const box1 = document.createElement('div');
            box1.style.cssText = 'background: #1a243b; border-radius: 10px; padding: 14px; margin-bottom: 12px; border: 1px solid #263352;';
            box1.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <b style="color: #fff; font-size: 15px;">📐 Matemáticas 1 (Opción Múltiple 1-45)</b>
                    <span style="font-weight: 800; color: #34d399; font-size: 15px;">${ac1} / ${tot1 || 45} aciertos (${p1}%)</span>
                </div>
                <div style="font-size: 12px; color: #94a3b8;">
                    Errores: ${err1} | Omitidas: ${om1}
                </div>
            `;
            contSec.appendChild(box1);

            // Matemáticas 2
            const box2 = document.createElement('div');
            box2.style.cssText = 'background: linear-gradient(135deg, rgba(245, 158, 11, 0.12) 0%, #1a243b 100%); border-radius: 10px; padding: 14px; margin-bottom: 12px; border: 1px solid rgba(245, 158, 11, 0.35);';
            box2.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <b style="color: #fbbf24; font-size: 15px;">✏️ Matemáticas 2 (Respuestas Abiertas 46-55)</b>
                    <span style="font-weight: 800; color: #fbbf24; font-size: 15px;">${ac2} / ${tot2 || 10} aciertos (${p2}%)</span>
                </div>
                <div style="font-size: 12px; color: #94a3b8;">
                    Errores: ${err2} | Omitidas: ${om2}
                </div>
            `;
            contSec.appendChild(box2);
            return;
        }

        const secBox = document.createElement('div');
        secBox.style.cssText = 'background: #1a243b; border-radius: 10px; padding: 14px; margin-bottom: 12px; border: 1px solid #263352;';
        secBox.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <b style="color: #fff; font-size: 15px;">${s.nombre}</b>
                <span style="font-weight: 800; color: #34d399; font-size: 15px;">${s.aciertos} / ${s.total_clave} aciertos (${s.porcentaje}%)</span>
            </div>
            <div style="font-size: 12px; color: #94a3b8;">
                Errores: ${s.errores} | Omitidas: ${s.omitidas}
            </div>
        `;
        contSec.appendChild(secBox);
    });

    document.getElementById('pantalla-resultados').style.display = 'block';
}

// Modal de Solicitud de Reintento
function abrirModalSolicitud() {
    document.getElementById('modal-solicitud').style.display = 'flex';
}
function cerrarModalSolicitud() {
    document.getElementById('modal-solicitud').style.display = 'none';
}

function enviarSolicitudReintento() {
    const nombre = document.getElementById('bloqueo-nombre').textContent.trim() || document.getElementById('input-nombre').value.trim();
    const motivo = document.getElementById('motivo-reintento').value.trim();

    fetch('api_calificar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'accion=solicitar_reintento&nombre=' + encodeURIComponent(nombre) + '&motivo=' + encodeURIComponent(motivo)
    })
    .then(r => r.json())
    .then(data => {
        cerrarModalSolicitud();
        if (data.exito) {
            alert('✅ ' + data.mensaje);
            verificarNombreAlumno(nombre);
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(() => alert('Error de conexión al enviar solicitud.'));
}
</script>

</body>
</html>
