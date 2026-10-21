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
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            border: none;
            background: transparent;
            color: var(--muted);
            border-radius: 8px;
            cursor: pointer;
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
            <label for="input-nombre">Nombre Completo del Alumno:</label>
            <input type="text" id="input-nombre" class="input-control" placeholder="Ej: JUAN PÉREZ LÓPEZ" autocomplete="name">
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
    <div id="pantalla-carga" class="card loading-view">
        <div class="spinner"></div>
        <h2 style="font-size: 18px; color: #fff; margin: 0 0 8px 0;">Analizando Hoja con IA...</h2>
        <p id="loading-desc" style="color: var(--muted); font-size: 14px; margin: 0;">Leyendo cuadrículas de Lectura, Redacción y Matemáticas...</p>
    </div>

    <!-- PANTALLA 2: REVISIÓN Y CORRECCIÓN ÚNICA -->
    <div id="pantalla-revision" class="card" style="display: none;">
        <div style="text-align: center; margin-bottom: 16px;">
            <span style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #fbbf24; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 12px;">
                ⚠️ Revisión Única Previa a la Calificación
            </span>
            <h2 style="font-size: 20px; color: #fff; margin: 10px 0 4px 0;">Verifica tus Respuestas</h2>
            <p style="color: var(--muted); font-size: 13px; margin: 0;">
                Si la IA malinterpretó alguna burbuja rellenada, cámbiala haciendo clic sobre la letra correspondiente.
            </p>
        </div>

        <div class="input-group">
            <label for="revision-nombre">Nombre del Alumno (Verifica que sea correcto):</label>
            <input type="text" id="revision-nombre" class="input-control" placeholder="Nombre del Alumno">
        </div>

        <div class="section-nav">
            <button class="sec-tab active" onclick="cambiarSeccionRevision('lectura')">Lectura (45)</button>
            <button class="sec-tab" onclick="cambiarSeccionRevision('redaccion')">Redacción (25)</button>
            <button class="sec-tab" onclick="cambiarSeccionRevision('matematicas')">Matemáticas (55)</button>
        </div>

        <!-- Contenedores de preguntas por sección -->
        <div id="grid-lectura" class="questions-grid"></div>
        <div id="grid-redaccion" class="questions-grid" style="display: none;"></div>
        <div id="grid-matematicas" class="questions-grid" style="display: none;"></div>

        <div id="aviso-modificaciones" style="font-size: 12px; color: #fbbf24; margin-bottom: 16px; text-align: center; display: none;">
            ✏️ Has rectificado <span id="conteo-modificaciones">0</span> respuestas.
        </div>

        <button class="btn" onclick="confirmarEntregaDefinitiva()">
            <span>🚀 Confirmar y Sellar Mi Examen</span>
        </button>
    </div>

    <!-- PANTALLA 3: RESULTADOS OFICIALES -->
    <div id="pantalla-resultados" class="card" style="display: none;">
        <div class="score-hero">
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

        <div style="margin-top: 20px;">
            <button class="btn btn-secondary" onclick="window.print()">🖨️ Imprimir / Guardar Resultados</button>
        </div>

        <p style="font-size: 12px; color: #94a3b8; text-align: center; margin-top: 14px;">
            🔒 Tu calificación ha quedado guardada oficialmente. No se permiten nuevas cargas.
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

// Analizar hoja con IA
function analizarHoja() {
    if (!archivoSeleccionado) {
        alert('Por favor selecciona o toma una fotografía de tu examen.');
        return;
    }

    const nombre = document.getElementById('input-nombre').value.trim();

    document.getElementById('pantalla-subida').style.display = 'none';
    document.getElementById('pantalla-carga').style.display = 'block';

    const formData = new FormData();
    formData.append('accion', 'analizar_hoja_alumno');
    formData.append('foto', archivoSeleccionado);
    formData.append('nombre_previo', nombre);

    fetch('api_calificar.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('pantalla-carga').style.display = 'none';

        if (!data.exito) {
            if (data.bloqueado) {
                mostrarPantallaBloqueo({
                    nombre_oficial: data.alumno_nombre,
                    solicitud_reintento: data.solicitud_reintento
                });
                return;
            }
            alert('Error: ' + data.error);
            document.getElementById('pantalla-subida').style.display = 'block';
            return;
        }

        respuestasDetectadas = JSON.parse(JSON.stringify(data.respuestas_detectadas));
        respuestasOriginales = JSON.parse(JSON.stringify(data.respuestas_detectadas));
        tempIdActual = data.temp_id;

        // Nombre sugerido
        const nombreFinal = nombre || data.nombre_detectado || '';
        document.getElementById('revision-nombre').value = nombreFinal;

        construirGridsRevision();
        document.getElementById('pantalla-revision').style.display = 'block';
    })
    .catch(err => {
        document.getElementById('pantalla-carga').style.display = 'none';
        alert('Error de conexión al analizar la hoja. Revisa tu conexión a internet.');
        document.getElementById('pantalla-subida').style.display = 'block';
    });
}

// Construcción de Grids para Revisión
function construirGridsRevision() {
    const specs = {
        lectura: { total: 45, cont: document.getElementById('grid-lectura') },
        redaccion: { total: 25, cont: document.getElementById('grid-redaccion') },
        matematicas: { total: 55, cont: document.getElementById('grid-matematicas') }
    };

    ['lectura', 'redaccion', 'matematicas'].forEach(sec => {
        const { total, cont } = specs[sec];
        cont.innerHTML = '';
        const respSec = respuestasDetectadas[sec] || {};
        const respOrigSec = respuestasOriginales[sec] || {};

        for (let i = 1; i <= total; i++) {
            const num = String(i);
            const val = respSec[num] || '';
            const valOrig = respOrigSec[num] || '';
            const esModificada = val !== valOrig;

            const card = document.createElement('div');
            card.className = 'q-card' + (esModificada ? ' modified' : '');
            card.id = `q-${sec}-${num}`;

            if (sec === 'matematicas' && i >= 46) {
                card.style.borderColor = 'rgba(245, 158, 11, 0.4)';
                card.innerHTML = `
                    <div class="q-header">
                        <span style="color:#f59e0b;">#${num} (Grid Numérico)</span>
                        ${esModificada ? '<span class="q-tag">Modificada</span>' : ''}
                    </div>
                    <div style="padding: 4px 0;">
                        <input type="text" value="${escapeHtml(val)}" 
                            placeholder="ej. 18, 3.5, 1/2" 
                            style="width: 100%; padding: 8px 10px; font-size: 14px; text-transform: uppercase; font-weight: 700; text-align: center; background: #0f172a; border: 1px solid #334155; color: #fff; border-radius: 8px;"
                            onchange="modificarRespuestaGrid('${sec}', '${num}', this.value)">
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
                                onclick="seleccionarOpcion('${sec}', '${num}', '${opt}')">${opt}</button>
                        `).join('')}
                        <button type="button" class="bubble-btn empty-btn ${val === '' ? 'active' : ''}" 
                            onclick="seleccionarOpcion('${sec}', '${num}', '')" title="Sin contestar">⚪</button>
                    </div>
                `;
            }
            cont.appendChild(card);
        }
    });

    actualizarConteoModificaciones();
}

function modificarRespuestaGrid(sec, num, val) {
    if (!respuestasDetectadas[sec]) respuestasDetectadas[sec] = {};
    const clean = val.trim().toUpperCase();
    if (clean === '') {
        delete respuestasDetectadas[sec][num];
    } else {
        respuestasDetectadas[sec][num] = clean;
    }
    const valOrig = (respuestasOriginales[sec] && respuestasOriginales[sec][num]) || '';
    const esModificada = clean !== valOrig;
    const card = document.getElementById(`q-${sec}-${num}`);
    if (card) {
        card.className = 'q-card' + (esModificada ? ' modified' : '');
        const tagSpan = card.querySelector('.q-header');
        tagSpan.innerHTML = `<span style="color:#f59e0b;">#${num} (Grid Numérico)</span>${esModificada ? '<span class="q-tag">Modificada</span>' : ''}`;
    }
    actualizarConteoModificaciones();
}

function cambiarSeccionRevision(sec) {
    document.querySelectorAll('.sec-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');

    document.getElementById('grid-lectura').style.display = sec === 'lectura' ? 'grid' : 'none';
    document.getElementById('grid-redaccion').style.display = sec === 'redaccion' ? 'grid' : 'none';
    document.getElementById('grid-matematicas').style.display = sec === 'matematicas' ? 'grid' : 'none';
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
        for (let i = 1; i <= 45; i++) {
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
        alert('Por favor escribe tu nombre completo antes de confirmar.');
        return;
    }

    if (!confirm(`¿Confirmas la entrega definitiva de tu examen a nombre de "${nombre}"?\n\nRecuerda: Esta acción sellará tu examen y se registrará tu puntaje oficial.`)) {
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
