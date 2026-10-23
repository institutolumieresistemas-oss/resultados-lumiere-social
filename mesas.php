<?php
/**
 * ====================================================================
 * Estaciones de Calificación y Auditoría Rápida por Mesas (PC)
 * Instituto Lumière - calificador.lumieresocial.com / resultados.lumieresocial.com
 * ====================================================================
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/calificador.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mesaInicial = intval($_GET['mesa'] ?? 1);
if ($mesaInicial < 1 || $mesaInicial > 5) $mesaInicial = 1;
$idDirecto = trim($_GET['id'] ?? '');

$esAdmin = (!empty($_SESSION['admin_token']) && $_SESSION['admin_token'] === ADMIN_PASSWORD);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesas de Calificación y Auditoría - Instituto Lumière</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #c01b2f;
            --primary-hover: #9e1324;
            --bg: #090d16;
            --card-bg: #111827;
            --card-surface: #1a243b;
            --border: #263352;
            --text: #f8fafc;
            --muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #fbbf24;
            --accent: #38bdf8;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 12px 16px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* BARRA SUPERIOR */
        .top-navbar {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 12px;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .brand-badge {
            background: linear-gradient(135deg, #c01b2f 0%, #9e1324 100%);
            color: #fff;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }
        .brand-title {
            font-size: 16px;
            font-weight: 800;
            color: #fff;
            margin: 0;
        }

        .mesas-nav {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .mesa-tab {
            background: var(--card-surface);
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .mesa-tab:hover {
            color: #fff;
            border-color: #475569;
        }
        .mesa-tab.active {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border-color: #38bdf8;
            color: #fff;
            box-shadow: 0 2px 8px rgba(56, 189, 248, 0.4);
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #34d399;
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.35);
            padding: 5px 12px;
            border-radius: 20px;
        }
        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            display: inline-block;
            box-shadow: 0 0 8px #10b981;
            animation: pulse-green 1.5s infinite;
        }
        @keyframes pulse-green {
            0% { transform: scale(0.9); opacity: 0.7; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.7; }
        }

        /* CONTENEDOR PRINCIPAL PANTALLA DIVIDIDA */
        .workspace-split {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
            flex: 1;
            min-height: calc(100vh - 100px);
        }
        @media (min-width: 900px) {
            .workspace-split {
                grid-template-columns: 48% 52%;
            }
        }

        /* COLUMNA IZQUIERDA: VISOR DE FOTO EN ALTA RESOLUCIÓN */
        .photo-viewer-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }
        .viewer-toolbar {
            background: rgba(15, 23, 42, 0.9);
            border-bottom: 1px solid var(--border);
            padding: 8px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
        }
        .tool-btn {
            background: var(--card-surface);
            border: 1px solid var(--border);
            color: #cbd5e1;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }
        .tool-btn:hover {
            color: #fff;
            border-color: var(--accent);
        }
        .photo-stage {
            flex: 1;
            background: #050811;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            user-select: none;
            cursor: grab;
            min-height: 480px;
        }
        .photo-stage:active {
            cursor: grabbing;
        }
        .photo-img {
            max-width: 95%;
            max-height: 95%;
            transition: transform 0.15s ease-out;
            transform-origin: center center;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7);
        }

        /* COLUMNA DERECHA: AUDITORÍA Y CORRECCIÓN */
        .audit-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 18px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            max-height: calc(100vh - 100px);
        }

        .student-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 12px;
        }
        .student-name-box {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .student-name-input {
            background: #0f172a;
            border: 1px solid #38bdf8;
            color: #fff;
            font-size: 18px;
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 8px;
            font-family: inherit;
        }

        /* TARJETA DE RESULTADOS PRELIMINARES */
        .score-preview-box {
            background: linear-gradient(135deg, #131f38 0%, #1c2b4d 100%);
            border: 2px solid var(--accent);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 12px;
            box-shadow: 0 4px 18px rgba(56, 189, 248, 0.2);
        }
        .score-preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .score-total-val {
            font-size: 32px;
            font-weight: 900;
            color: var(--accent);
            line-height: 1;
        }
        .score-subbars {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            background: rgba(0, 0, 0, 0.25);
            padding: 8px 12px;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 12px;
        }

        /* FILTROS DE REACTIVOS */
        .filters-row {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }
        .filter-chip {
            background: var(--card-surface);
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s;
        }
        .filter-chip:hover {
            color: #fff;
        }
        .filter-chip.active {
            background: rgba(56, 189, 248, 0.15);
            border-color: var(--accent);
            color: var(--accent);
        }
        .filter-chip.danger-chip.active {
            background: rgba(239, 68, 68, 0.18);
            border-color: #ef4444;
            color: #f87171;
        }

        /* LISTA DE REACTIVOS INTERACTIVA */
        .questions-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
            max-height: 440px;
            padding-right: 4px;
            margin-bottom: 14px;
        }
        .q-row {
            background: var(--card-surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            transition: all 0.15s;
        }
        .q-row.row-acierto {
            border-color: rgba(16, 185, 129, 0.3);
            background: rgba(16, 185, 129, 0.04);
        }
        .q-row.row-error {
            border-color: rgba(239, 68, 68, 0.5);
            background: rgba(239, 68, 68, 0.1);
        }
        .q-row.row-omitida {
            border-color: rgba(148, 163, 184, 0.3);
            background: rgba(148, 163, 184, 0.04);
        }

        .q-left-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 140px;
        }
        .q-badge-num {
            font-size: 13px;
            font-weight: 800;
            color: #94a3b8;
            min-width: 38px;
        }
        .q-status-tag {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
        }
        .q-status-tag.tag-acierto { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .q-status-tag.tag-error { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .q-status-tag.tag-omitida { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; }

        .q-bubbles-box {
            display: flex;
            gap: 4px;
            align-items: center;
        }
        .bubble-btn-audit {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: #0f172a;
            color: #cbd5e1;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }
        .bubble-btn-audit:hover {
            border-color: #94a3b8;
            color: #fff;
        }
        .bubble-btn-audit.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #090d16;
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.5);
        }
        .bubble-btn-audit.is-correct-key {
            border-color: #10b981;
            box-shadow: inset 0 0 0 1px #10b981;
        }

        /* BARRA INFERIOR DE ACCIONES */
        .actions-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding-top: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: auto;
        }
        .btn-sellar {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-sellar:hover {
            transform: translateY(-1px);
        }
        .btn-descartar {
            background: #1e293b;
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-descartar:hover {
            color: #ef4444;
            border-color: #ef4444;
        }

        /* PANTALLA EN ESPERA (STANDBY) */
        .standby-card {
            background: var(--card-bg);
            border: 2px dashed var(--border);
            border-radius: 16px;
            padding: 50px 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 480px;
        }
        .qr-box-img {
            background: #fff;
            padding: 10px;
            border-radius: 12px;
            margin: 16px 0;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5);
            width: 170px;
            height: 170px;
        }
    </style>
</head>
<body>

    <!-- BARRA SUPERIOR -->
    <div class="top-navbar">
        <div class="brand-section">
            <span class="brand-badge">ESTACIONES PAA</span>
            <h1 class="brand-title">Mesas de Calificación en Vivo</h1>
        </div>

        <!-- SELECTOR DE MESAS 1 A 5 -->
        <div class="mesas-nav">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <a href="mesas.php?mesa=<?php echo $i; ?>" class="mesa-tab <?php echo ($i === $mesaInicial) ? 'active' : ''; ?>">
                    <span>Mesa <?php echo $i; ?></span>
                </a>
            <?php endfor; ?>
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
            <div class="status-indicator">
                <span class="pulse-dot"></span>
                <span id="lblMesaStatus">Mesa <?php echo $mesaInicial; ?> lista</span>
            </div>
            <a href="panel.php" class="tool-btn" style="text-decoration: none;" title="Regresar al Panel Docente">
                📊 Panel
            </a>
        </div>
    </div>

    <!-- PANTALLA EN ESPERA (STANDBY) -->
    <div id="pantallaStandby" class="standby-card" style="display: none;">
        <div class="status-indicator" style="margin-bottom: 12px;">
            <span class="pulse-dot"></span>
            <span>Mesa <?php echo $mesaInicial; ?> Conectada</span>
        </div>
        <h2 style="font-size: 24px; margin: 0 0 8px 0; color: #fff;">Esperando fotografía desde el celular...</h2>
        <p style="color: var(--muted); font-size: 14px; max-width: 480px; margin: 0 0 10px 0;">
            Abre el escáner en tu teléfono móvil apuntando a esta Mesa. Cada foto tomada se proyectará aquí automáticamente para su auditoría y corrección.
        </p>

        <!-- CÓDIGO QR PARA CELULAR -->
        <div id="qrCodeContainer">
            <img class="qr-box-img" id="qrCodeImg" alt="QR Escáner Celular"
                src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo urlencode('https://' . ($_SERVER['HTTP_HOST'] ?? 'calificador.lumieresocial.com') . '/escaner_mesa.php?mesa=' . $mesaInicial); ?>">
        </div>

        <div style="font-size: 13px; color: #38bdf8; font-weight: 700; margin-bottom: 20px;">
            O abre en el celular: <u>https://<?php echo $_SERVER['HTTP_HOST'] ?? 'calificador.lumieresocial.com'; ?>/escaner_mesa.php?mesa=<?php echo $mesaInicial; ?></u>
        </div>

        <!-- UPLOAD DIRECTO DESDE PC -->
        <div style="border-top: 1px solid var(--border); padding-top: 18px; width: 100%; max-width: 400px;">
            <input type="file" id="inputFotoDirectaPC" accept="image/*" style="display: none;" onchange="subirFotoDirectaPC(this)">
            <button type="button" class="tool-btn" style="padding: 10px 18px; font-size: 13px;" onclick="document.getElementById('inputFotoDirectaPC').click()">
                📂 O subir fotografía desde esta computadora
            </button>
        </div>
    </div>

    <!-- ESPACIO DE TRABAJO A PANTALLA DIVIDIDA (CUANDO HAY EXAMEN) -->
    <div id="workspaceExamen" class="workspace-split" style="display: none;">

        <!-- COLUMNA IZQUIERDA: VISOR DE FOTOGRAFÍA -->
        <div class="photo-viewer-card">
            <div class="viewer-toolbar">
                <span style="color: #38bdf8;">📸 Hoja Original del Examen</span>
                <div style="display: flex; gap: 6px;">
                    <button type="button" class="tool-btn" onclick="zoomFoto(0.2)" title="Acercar">🔍 +</button>
                    <button type="button" class="tool-btn" onclick="zoomFoto(-0.2)" title="Alejar">🔍 -</button>
                    <button type="button" class="tool-btn" onclick="rotarFoto()" title="Rotar 90°">🔄 Rotar</button>
                    <button type="button" class="tool-btn" onclick="resetFoto()" title="Reiniciar vista">↕️ Centrar</button>
                </div>
            </div>
            <div class="photo-stage" id="photoStage">
                <img id="imgExamen" class="photo-img" src="" alt="Examen del alumno">
            </div>
        </div>

        <!-- COLUMNA DERECHA: AUDITORÍA Y CORRECCIÓN RÁPIDA -->
        <div class="audit-card">
            <!-- ENCABEZADO DEL ALUMNO -->
            <div class="student-header">
                <div>
                    <div style="font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase;">
                        Mesa <?php echo $mesaInicial; ?> &bull; Auditoría Rápida de Examen
                    </div>
                    <div class="student-name-box" style="margin-top: 4px;">
                        <input type="text" id="inputNombreAlumno" class="student-name-input" value="ALUMNO DETECTADO" placeholder="Nombre completo">
                    </div>
                </div>
                <div style="text-align: right; font-size: 12px; color: var(--muted);">
                    <div>IA OMR: <b id="lblTiempoIa" style="color: #38bdf8;">2.4s</b></div>
                    <div id="lblHoraRecepcion" style="font-size: 11px;">14:30:10</div>
                </div>
            </div>

            <!-- TARJETA DE RESULTADOS PRELIMINARES -->
            <div class="score-preview-box">
                <div class="score-preview-header">
                    <div>
                        <div style="font-size: 11px; color: #cbd5e1; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                            Puntaje PAA Preliminar
                        </div>
                        <div class="score-total-val" id="lblPuntajePaaTotal">1,210</div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <div style="text-align: right;">
                            <span style="color: #34d399; font-weight: 800; font-size: 18px;" id="lblTotalAciertos">96</span>
                            <div style="font-size: 11px; color: var(--muted);">Aciertos</div>
                        </div>
                        <div style="text-align: right;">
                            <span style="color: #ef4444; font-weight: 800; font-size: 18px;" id="lblTotalErrores">19</span>
                            <div style="font-size: 11px; color: var(--muted);">Errores</div>
                        </div>
                        <div style="text-align: right;">
                            <span style="color: #94a3b8; font-weight: 800; font-size: 18px;" id="lblTotalOmitidas">10</span>
                            <div style="font-size: 11px; color: var(--muted);">Omitidas</div>
                        </div>
                    </div>
                </div>

                <div class="score-subbars">
                    <div>Verbal (Lectura + Redacción): <b id="lblPaaVerbal" style="color: #38bdf8;">610 pts</b> (<span id="lblAciertosVerbal">52</span> aciertos)</div>
                    <div>Matemáticas 1 y 2: <b id="lblPaaMate" style="color: #fbbf24;">600 pts</b> (<span id="lblAciertosMate">44</span> aciertos)</div>
                </div>
            </div>

            <!-- FILTROS DE REACTIVOS (ERRORES PRIMERO) -->
            <div class="filters-row">
                <button type="button" class="filter-chip danger-chip active" id="chipErrores" onclick="filtrarReactivos('errores')">
                    🔴 Solo Errores (<span id="countErrores">0</span>)
                </button>
                <button type="button" class="filter-chip" id="chipOmitidas" onclick="filtrarReactivos('omitidas')">
                    ⚪ Omitidas (<span id="countOmitidas">0</span>)
                </button>
                <button type="button" class="filter-chip" id="chipAciertos" onclick="filtrarReactivos('aciertos')">
                    🟢 Aciertos (<span id="countAciertos">0</span>)
                </button>
                <button type="button" class="filter-chip" id="chipTodos" onclick="filtrarReactivos('todos')">
                    📋 Todos (125)
                </button>
            </div>

            <!-- LISTA INTERACTIVA DE REACTIVOS -->
            <div id="listaReactivos" class="questions-list">
                <!-- Generado dinámicamente -->
            </div>

            <!-- BARRA INFERIOR DE ACCIONES -->
            <div class="actions-footer">
                <button type="button" class="btn-descartar" onclick="descartarMesa()">
                    🗑️ Descartar sin Guardar
                </button>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="tool-btn" onclick="copiarLigaExamen()" title="Copiar enlace directo de este examen">
                        🔗 Copiar Enlace
                    </button>
                    <button type="button" class="btn-sellar" id="btnSellar" onclick="sellarExamenDefinitivo()">
                        💾 Sellar y Guardar Definitivo
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
        const MESA_NUM = <?php echo $mesaInicial; ?>;
        const ID_DIRECTO = "<?php echo addslashes($idDirecto); ?>";

        let examenActual = null;
        let respuestasTrabajo = {};
        let filtroActivo = 'errores';
        let zoomNivel = 1.0;
        let rotacionGrados = 0;
        let lastExamId = '';
        let pollingTimer = null;

        document.addEventListener('DOMContentLoaded', () => {
            if (ID_DIRECTO) {
                cargarExamenPorId(ID_DIRECTO);
            } else {
                iniciarPollingMesa();
            }
        });

        // 1. POLLING DE LA MESA EN TIEMPO REAL
        function iniciarPollingMesa() {
            verificarEstadoMesa();
            pollingTimer = setInterval(verificarEstadoMesa, 1500);
        }

        async function verificarEstadoMesa() {
            try {
                const res = await fetch(`api_mesas.php?accion=estado_mesa&mesa=${MESA_NUM}&last_id=${encodeURIComponent(lastExamId)}`);
                const data = await res.json();

                if (!data.exito) return;

                if (data.activo && !data.sin_cambios && data.examen) {
                    renderExamenEnMesa(data.examen);
                } else if (!data.activo && !examenActual) {
                    mostrarStandby();
                }
            } catch(e) {
                console.warn('Error polling mesa:', e);
            }
        }

        async function cargarExamenPorId(id) {
            try {
                const res = await fetch(`api_mesas.php?accion=cargar_examen&id=${encodeURIComponent(id)}`);
                const data = await res.json();
                if (data.exito && data.examen) {
                    renderExamenEnMesa(data.examen);
                } else {
                    alert('No se pudo cargar el examen especificado.');
                    mostrarStandby();
                }
            } catch(e) {
                mostrarStandby();
            }
        }

        function mostrarStandby() {
            document.getElementById('pantallaStandby').style.display = 'flex';
            document.getElementById('workspaceExamen').style.display = 'none';
            document.getElementById('lblMesaStatus').textContent = `Mesa ${MESA_NUM} lista (Esperando foto)`;
        }

        function renderExamenEnMesa(examen) {
            examenActual = examen;
            lastExamId = examen.id;
            respuestasTrabajo = JSON.parse(JSON.stringify(examen.respuestas || {}));

            document.getElementById('pantallaStandby').style.display = 'none';
            document.getElementById('workspaceExamen').style.display = 'grid';
            document.getElementById('lblMesaStatus').textContent = `Mesa ${MESA_NUM}: Examen cargado`;

            // Cargar imagen
            const img = document.getElementById('imgExamen');
            img.src = examen.foto_url || `uploads/${examen.foto_archivo}`;
            resetFoto();

            // Metadatos
            document.getElementById('inputNombreAlumno').value = examen.nombre_alumno || 'ALUMNO REGISTRADO';
            document.getElementById('lblTiempoIa').textContent = (examen.duracion_ia || '2.4') + 's';
            document.getElementById('lblHoraRecepcion').textContent = examen.fecha || '';

            // Calificación
            actualizarIndicadoresCalificacion(examen.calificacion);

            // Renderizar lista interactiva con el filtro actual (errores primero)
            renderListaReactivos();
        }

        function actualizarIndicadoresCalificacion(cal) {
            if (!cal) return;
            document.getElementById('lblPuntajePaaTotal').textContent = cal.paa_total_escala || 0;
            document.getElementById('lblTotalAciertos').textContent = cal.total_aciertos || 0;
            const errores = (cal.total_preguntas_evaluadas || 125) - (cal.total_aciertos || 0);
            document.getElementById('lblTotalErrores').textContent = Math.max(0, errores);

            let omitidas = 0;
            let aciertos = 0;
            let errs = 0;
            (cal.secciones || []).forEach(s => {
                omitidas += s.omitidas || 0;
                aciertos += s.aciertos || 0;
                errs += s.errores || 0;
            });
            document.getElementById('lblTotalOmitidas').textContent = omitidas;
            document.getElementById('countErrores').textContent = errs;
            document.getElementById('countOmitidas').textContent = omitidas;
            document.getElementById('countAciertos').textContent = aciertos;

            document.getElementById('lblPaaVerbal').textContent = (cal.paa_verbal_escala || 0) + ' pts';
            document.getElementById('lblAciertosVerbal').textContent = cal.aciertos_verbal || 0;
            document.getElementById('lblPaaMate').textContent = (cal.paa_mate_escala || 0) + ' pts';
            document.getElementById('lblAciertosMate').textContent = cal.aciertos_mate || 0;
        }

        function filtrarReactivos(tipo) {
            filtroActivo = tipo;
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            if (tipo === 'errores') document.getElementById('chipErrores').classList.add('active');
            else if (tipo === 'omitidas') document.getElementById('chipOmitidas').classList.add('active');
            else if (tipo === 'aciertos') document.getElementById('chipAciertos').classList.add('active');
            else document.getElementById('chipTodos').classList.add('active');

            renderListaReactivos();
        }

        function renderListaReactivos() {
            const container = document.getElementById('listaReactivos');
            if (!container || !examenActual || !examenActual.calificacion) return;

            const secciones = examenActual.calificacion.secciones || [];
            let html = '';

            secciones.forEach(sec => {
                const secClave = sec.clave;
                const detalle = sec.detalle || [];

                detalle.forEach(item => {
                    const num = item.num;
                    const estado = item.estado; // 'acierto', 'error', 'omitida'
                    const marcada = respuestasTrabajo[secClave]?.[num] || '';
                    const correcta = item.correcta;

                    // Aplicar filtro
                    if (filtroActivo === 'errores' && estado !== 'error') return;
                    if (filtroActivo === 'omitidas' && estado !== 'omitida') return;
                    if (filtroActivo === 'aciertos' && estado !== 'acierto') return;

                    const rowClass = (estado === 'acierto') ? 'row-acierto' : ((estado === 'error') ? 'row-error' : 'row-omitida');
                    const tagClass = (estado === 'acierto') ? 'tag-acierto' : ((estado === 'error') ? 'tag-error' : 'tag-omitida');
                    const tagTxt = (estado === 'acierto') ? '🟢 Correcto' : ((estado === 'error') ? `🔴 Fallada (Era ${correcta})` : '⚪ Omitida');

                    const esGridMate = (secClave === 'matematicas' && parseInt(num, 10) >= 46 && parseInt(num, 10) <= 55);

                    if (esGridMate) {
                        html += `
                            <div class="q-row ${rowClass}" id="qRow_${secClave}_${num}">
                                <div class="q-left-info">
                                    <span class="q-badge-num">#${num}</span>
                                    <span class="q-status-tag ${tagClass}">${tagTxt}</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 11px; color: #fbbf24;">Clave: <b>${correcta}</b></span>
                                    <input type="text" value="${escapeHtml(marcada)}" placeholder="—" maxlength="8"
                                        style="width: 80px; padding: 4px 6px; font-size: 13px; font-weight: 800; text-align: center; background: #0f172a; border: 1px solid #fbbf24; color: #fff; border-radius: 6px;"
                                        onchange="corregirRespuestaDirecta('${secClave}', '${num}', this.value)">
                                </div>
                            </div>
                        `;
                    } else {
                        const opts = ['A', 'B', 'C', 'D'];
                        const btns = opts.map(opt => {
                            const isSelected = (marcada === opt);
                            const isKey = (correcta === opt);
                            let cls = 'bubble-btn-audit';
                            if (isSelected) cls += ' active';
                            if (isKey) cls += ' is-correct-key';
                            return `<button type="button" class="${cls}" onclick="corregirRespuestaDirecta('${secClave}', '${num}', '${opt}')" title="${isKey ? 'Clave correcta oficial' : ''}">${opt}</button>`;
                        }).join('');

                        html += `
                            <div class="q-row ${rowClass}" id="qRow_${secClave}_${num}">
                                <div class="q-left-info">
                                    <span class="q-badge-num">#${num}</span>
                                    <span class="q-status-tag ${tagClass}">${tagTxt}</span>
                                </div>
                                <div class="q-bubbles-box">
                                    ${btns}
                                    <button type="button" class="bubble-btn-audit ${!marcada ? 'active' : ''}" style="width:24px; height:24px; font-size:10px;" onclick="corregirRespuestaDirecta('${secClave}', '${num}', '')" title="Omitir">⚪</button>
                                </div>
                            </div>
                        `;
                    }
                });
            });

            if (!html) {
                html = `<div style="text-align: center; padding: 30px; color: var(--muted); font-size: 13px;">No hay reactivos en esta categoría (${filtroActivo}).</div>`;
            }

            container.innerHTML = html;
        }

        async function corregirRespuestaDirecta(sec, num, val) {
            const clean = val.trim().toUpperCase();
            if (!respuestasTrabajo[sec]) respuestasTrabajo[sec] = {};
            if (clean === '') {
                delete respuestasTrabajo[sec][num];
            } else {
                respuestasTrabajo[sec][num] = clean;
            }

            // Recalcular en backend en tiempo real
            const formData = new FormData();
            formData.append('accion', 'recalcular');
            formData.append('respuestas', JSON.stringify(respuestasTrabajo));

            try {
                const res = await fetch('api_mesas.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.exito && data.calificacion) {
                    examenActual.calificacion = data.calificacion;
                    actualizarIndicadoresCalificacion(data.calificacion);
                    renderListaReactivos();
                }
            } catch(e) {
                console.warn('Error recalculando:', e);
            }
        }

        // 2. HERRAMIENTAS DE VISOR DE FOTO (Zoom / Pan / Rotar)
        function zoomFoto(delta) {
            zoomNivel = Math.max(0.5, Math.min(3.5, zoomNivel + delta));
            aplicarTransformFoto();
        }

        function rotarFoto() {
            rotacionGrados = (rotacionGrados + 90) % 360;
            aplicarTransformFoto();
        }

        function resetFoto() {
            zoomNivel = 1.0;
            rotacionGrados = 0;
            aplicarTransformFoto();
        }

        function aplicarTransformFoto() {
            const img = document.getElementById('imgExamen');
            if (img) {
                img.style.transform = `scale(${zoomNivel}) rotate(${rotacionGrados}deg)`;
            }
        }

        // 3. SELLAR Y GUARDAR DEFINITIVO
        async function sellarExamenDefinitivo() {
            if (!examenActual) return;
            const nombre = document.getElementById('inputNombreAlumno').value.trim();
            if (!nombre) {
                alert('Debes ingresar el nombre completo del alumno.');
                return;
            }

            const btn = document.getElementById('btnSellar');
            const txtOrig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span>⏳ Guardando...</span>';

            const formData = new FormData();
            formData.append('accion', 'sellar_definitivo');
            formData.append('mesa', MESA_NUM);
            formData.append('id', examenActual.id);
            formData.append('nombre_alumno', nombre);
            formData.append('carrera', examenActual.carrera || '');
            formData.append('foto_archivo', examenActual.foto_archivo || '');
            formData.append('respuestas', JSON.stringify(respuestasTrabajo));
            formData.append('respuestas_originales', JSON.stringify(examenActual.respuestas_originales_ia || {}));

            try {
                const res = await fetch('api_mesas.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.exito) {
                    alert(`✅ ¡Examen de ${nombre} sellado y guardado con éxito!\nPuntaje PAA: ${data.puntaje_paa_total} pts (${data.total_aciertos} aciertos).`);
                    examenActual = null;
                    lastExamId = '';
                    mostrarStandby();
                } else {
                    alert('Error al sellar: ' + (data.error || 'Error desconocido'));
                }
            } catch(e) {
                alert('Error de conexión al sellar el examen.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = txtOrig;
            }
        }

        async function descartarMesa() {
            if (!confirm(`¿Descartar este examen de la Mesa ${MESA_NUM} sin guardar nada en la base de datos?`)) return;

            const formData = new FormData();
            formData.append('accion', 'limpiar_mesa');
            formData.append('mesa', MESA_NUM);
            await fetch('api_mesas.php', { method: 'POST', body: formData });

            examenActual = null;
            lastExamId = '';
            mostrarStandby();
        }

        function copiarLigaExamen() {
            if (!examenActual) return;
            const url = window.location.origin + window.location.pathname + '?id=' + encodeURIComponent(examenActual.id);
            navigator.clipboard.writeText(url).then(() => {
                alert('🔗 Enlace copiado al portapapeles:\n' + url);
            }).catch(() => {
                prompt('Copia este enlace directo:', url);
            });
        }

        async function subirFotoDirectaPC(input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];

            document.getElementById('pantallaStandby').innerHTML = `
                <div class="status-indicator"><span class="pulse-dot"></span><span>Analizando con IA...</span></div>
                <h3 style="color:#38bdf8; margin:16px 0 6px 0;">Leyendo hoja y contrastando con Hoja Base...</h3>
                <p style="color:var(--muted); font-size:13px;">Esto toma aproximadamente 2.5 segundos.</p>
            `;

            const formData = new FormData();
            formData.append('accion', 'subir_directo_pc');
            formData.append('mesa', MESA_NUM);
            formData.append('foto', file);

            try {
                const res = await fetch('api_mesas.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.exito) {
                    verificarEstadoMesa();
                } else {
                    alert('Error al analizar la imagen: ' + (data.error || 'No se pudo leer la hoja'));
                    window.location.reload();
                }
            } catch(e) {
                alert('Error al subir imagen.');
                window.location.reload();
            }
        }

        function escapeHtml(t) {
            return String(t || '').replace(/[&<>"']/g, m => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[m]);
        }
    </script>
</body>
</html>
