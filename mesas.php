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

$hostActual = $_SERVER['HTTP_HOST'] ?? 'calificador.lumieresocial.com';
$esAdmin = (!empty($_SESSION['admin_token']) && $_SESSION['admin_token'] === ADMIN_PASSWORD);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesa <?php echo $mesaInicial; ?> &bull; Auditoría y Calificación en Vivo | Instituto Lumière</title>
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
            --card-surface-hover: #22304e;
            --border: #263352;
            --border-light: #334155;
            --text: #f8fafc;
            --muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #fbbf24;
            --accent: #38bdf8;
            --purple: #c084fc;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 10px 14px 24px 14px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* BARRA SUPERIOR */
        .top-navbar {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 8px 16px;
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
            gap: 10px;
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
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* SELECTOR DE MESAS 1 A 5 */
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
            box-shadow: 0 2px 10px rgba(56, 189, 248, 0.4);
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

        .tool-btn {
            background: var(--card-surface);
            border: 1px solid var(--border);
            color: #cbd5e1;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .tool-btn:hover {
            color: #fff;
            border-color: var(--accent);
            background: var(--card-surface-hover);
        }

        /* PANTALLA EN ESPERA (STANDBY) - VISIBLE POR DEFECTO */
        .standby-card {
            background: var(--card-bg);
            border: 2px dashed var(--border);
            border-radius: 18px;
            padding: 40px 24px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1;
            min-height: calc(100vh - 120px);
            margin: auto;
            max-width: 860px;
            width: 100%;
        }
        .qr-box-img {
            background: #fff;
            padding: 10px;
            border-radius: 14px;
            margin: 16px 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            width: 170px;
            height: 170px;
        }

        .btn-demo-test {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border: 1px solid #38bdf8;
            color: #fff;
            font-size: 14px;
            font-weight: 800;
            padding: 12px 22px;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(56, 189, 248, 0.35);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-demo-test:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(56, 189, 248, 0.5);
        }

        /* ESPACIO DE TRABAJO A PANTALLA DIVIDIDA */
        .workspace-split {
            display: none;
            grid-template-columns: 1fr;
            gap: 14px;
            flex: 1;
            min-height: calc(100vh - 90px);
        }
        @media (min-width: 960px) {
            .workspace-split {
                grid-template-columns: 44% 56%;
            }
        }

        /* COLUMNA IZQUIERDA: VISOR DE FOTO */
        .photo-viewer-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
            min-height: 520px;
        }
        .viewer-toolbar {
            background: rgba(15, 23, 42, 0.95);
            border-bottom: 1px solid var(--border);
            padding: 8px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
            z-index: 10;
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
            max-width: 94%;
            max-height: 94%;
            transition: transform 0.15s ease-out;
            transform-origin: center center;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7);
        }

        .demo-sheet-placeholder {
            background: #0b1324;
            border: 2px dashed #263352;
            border-radius: 12px;
            padding: 30px 24px;
            text-align: center;
            max-width: 380px;
            width: 90%;
        }

        /* COLUMNA DERECHA: AUDITORÍA Y CORRECCIÓN */
        .audit-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            max-height: calc(100vh - 90px);
        }

        .student-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 10px;
        }
        .student-name-input {
            background: #0b1324;
            border: 1px solid #38bdf8;
            color: #fff;
            font-size: 17px;
            font-weight: 800;
            padding: 5px 12px;
            border-radius: 8px;
            font-family: inherit;
            width: 100%;
            max-width: 380px;
        }

        /* ==========================================================
           LAS 4 TARJETAS DE PUNTAJES CLAVE (LECTURA/REDACCIÓN, MATE, TOTAL, PROMEDIO)
           ========================================================== */
        .scores-quad-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }
        @media (min-width: 600px) {
            .scores-quad-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        .score-quad-card {
            background: linear-gradient(145deg, #131b2e 0%, #1a243b 100%);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: all 0.2s;
        }
        .score-quad-card:hover {
            border-color: #475569;
            transform: translateY(-1px);
        }
        .score-quad-card.card-verbal {
            border-color: rgba(56, 189, 248, 0.4);
            box-shadow: 0 4px 14px rgba(56, 189, 248, 0.12);
        }
        .score-quad-card.card-mate {
            border-color: rgba(251, 191, 36, 0.4);
            box-shadow: 0 4px 14px rgba(251, 191, 36, 0.12);
        }
        .score-quad-card.card-total {
            border-color: rgba(16, 185, 129, 0.4);
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.12);
        }
        .score-quad-card.card-promedio {
            border-color: rgba(192, 132, 252, 0.4);
            box-shadow: 0 4px 14px rgba(192, 132, 252, 0.12);
        }

        .score-quad-tag {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .score-quad-val {
            font-size: 24px;
            font-weight: 900;
            line-height: 1.1;
            margin: 2px 0;
            display: flex;
            align-items: baseline;
            gap: 4px;
        }
        .score-quad-sub {
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
        }
        .pts-unit {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
        }

        /* BARRA DE RESUMEN DE ACIERTOS / ERRORES / OMITIDAS */
        .counters-bar {
            background: #0b1324;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        /* RECALCULANDO BADGE ANIMADO */
        .recalc-pill {
            background: rgba(56, 189, 248, 0.18);
            border: 1px solid #38bdf8;
            color: #38bdf8;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 800;
            animation: pulse-recalc 1s infinite;
        }
        @keyframes pulse-recalc {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }

        /* ==========================================================
           LAS 4 PESTAÑAS MODULARES DE SECCIONES (Lectura, Redacción, Mate 1, Mate 2)
           ========================================================== */
        .sections-tabs-row {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .sec-tab-btn {
            background: var(--card-surface);
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 7px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .sec-tab-btn:hover {
            color: #fff;
            border-color: #475569;
        }
        .sec-tab-btn.active {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border-color: #38bdf8;
            color: #fff;
            box-shadow: 0 2px 8px rgba(56, 189, 248, 0.35);
        }
        .sec-tab-pill {
            background: rgba(0, 0, 0, 0.35);
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 6px;
            font-weight: 800;
        }

        /* FILTROS RÁPIDOS */
        .filters-row {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        .filter-chip {
            background: var(--card-surface);
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 5px 10px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.15s;
        }
        .filter-chip:hover {
            color: #fff;
        }
        .filter-chip.active {
            background: rgba(56, 189, 248, 0.18);
            border-color: var(--accent);
            color: var(--accent);
        }
        .filter-chip.danger-chip.active {
            background: rgba(239, 68, 68, 0.2);
            border-color: #ef4444;
            color: #f87171;
        }
        .filter-chip.success-chip.active {
            background: rgba(16, 185, 129, 0.2);
            border-color: #10b981;
            color: #34d399;
        }

        /* LISTA INTERACTIVA DE REACTIVOS */
        .questions-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            overflow-y: auto;
            max-height: 480px;
            padding-right: 4px;
            margin-bottom: 12px;
        }

        .section-separator-title {
            background: linear-gradient(90deg, #131f38 0%, #0d1527 100%);
            border: 1px solid #263352;
            border-left: 4px solid var(--accent);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 800;
            color: #38bdf8;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 6px 0 2px 0;
        }

        .q-row {
            background: var(--card-surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 7px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            transition: all 0.15s;
        }
        .q-row.row-acierto {
            border-color: rgba(16, 185, 129, 0.35);
            background: rgba(16, 185, 129, 0.05);
        }
        .q-row.row-error {
            border-color: rgba(239, 68, 68, 0.5);
            background: rgba(239, 68, 68, 0.12);
        }
        .q-row.row-omitida {
            border-color: rgba(148, 163, 184, 0.3);
            background: rgba(148, 163, 184, 0.04);
        }

        .q-left-info {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 150px;
        }
        .q-badge-num {
            font-size: 13px;
            font-weight: 800;
            color: #94a3b8;
            min-width: 42px;
        }
        .q-status-tag {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .q-status-tag.tag-acierto { background: rgba(16, 185, 129, 0.2); color: #34d399; }
        .q-status-tag.tag-error { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .q-status-tag.tag-omitida { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; }

        /* BURBUJAS DE OPCIÓN MÚLTIPLE INTERACTIVAS */
        .q-bubbles-box {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .bubble-btn-audit {
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
            transition: all 0.15s;
        }
        .bubble-btn-audit:hover {
            border-color: #94a3b8;
            color: #fff;
            background: #1e293b;
        }
        .bubble-btn-audit.active {
            background: var(--accent);
            border-color: var(--accent);
            color: #090d16;
            font-weight: 900;
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.5);
        }
        .bubble-btn-audit.is-correct-key {
            border-color: #10b981;
            box-shadow: inset 0 0 0 1.5px #10b981;
        }
        .bubble-btn-audit.active.is-correct-key {
            background: #10b981;
            border-color: #10b981;
            color: #090d16;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
        }

        /* GRID / CUADRÍCULA EN MATEMÁTICAS 2 */
        .grid-input-audit {
            width: 90px;
            padding: 5px 8px;
            font-size: 13px;
            font-weight: 800;
            text-align: center;
            background: #0f172a;
            border: 1px solid #fbbf24;
            color: #fff;
            border-radius: 6px;
            font-family: inherit;
        }
        .grid-input-audit:focus {
            outline: none;
            border-color: #38bdf8;
            box-shadow: 0 0 8px rgba(56, 189, 248, 0.4);
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
            padding: 11px 20px;
            border-radius: 10px;
            font-size: 13px;
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
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.5);
        }
        .btn-descartar {
            background: #1e293b;
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 11px 16px;
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
    </style>
</head>
<body>

    <!-- BARRA SUPERIOR -->
    <div class="top-navbar">
        <div class="brand-section">
            <span class="brand-badge">ESTACIONES PAA</span>
            <h1 class="brand-title">
                Mesas de Calificación en Vivo
            </h1>
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
                <span id="lblMesaStatus">Mesa <?php echo $mesaInicial; ?> Conectada</span>
            </div>
            <a href="escaner_mesa.php?mesa=<?php echo $mesaInicial; ?>" target="_blank" class="tool-btn" title="Abrir Escáner Móvil">
                📱 Escáner
            </a>
            <a href="panel.php" class="tool-btn" title="Ir al Panel Docente">
                📊 Panel
            </a>
        </div>
    </div>

    <!-- PANTALLA EN ESPERA (STANDBY) - VISIBLE POR DEFECTO -->
    <div id="pantallaStandby" class="standby-card">
        <div class="status-indicator" style="margin-bottom: 14px;">
            <span class="pulse-dot"></span>
            <span>Mesa <?php echo $mesaInicial; ?> Lista &bull; Esperando Examen</span>
        </div>

        <h2 style="font-size: 24px; margin: 0 0 8px 0; color: #fff;">
            Esperando fotografía desde el escáner del celular...
        </h2>
        <p style="color: var(--muted); font-size: 14px; max-width: 520px; margin: 0 0 16px 0;">
            Apunta la cámara del celular a la hoja del alumno. Se analizará en <b>2.5 segundos</b> y se proyectará automáticamente en esta Mesa para su auditoría y corrección.
        </p>

        <!-- BOTÓN DEMO DE PRUEBA RÁPIDA (REQUERIDO) -->
        <div style="margin-bottom: 20px;">
            <button type="button" class="btn-demo-test" onclick="cargarExamenDemo()">
                🧪 Cargar Examen de Demostración (Para Probar la Mesa)
            </button>
            <div style="font-size: 11px; color: var(--muted); margin-top: 6px;">
                Carga un examen con respuestas reales para probar las 4 secciones y recalificación en vivo sin celular.
            </div>
        </div>

        <!-- CÓDIGO QR PARA CELULAR -->
        <div id="qrCodeContainer">
            <img class="qr-box-img" id="qrCodeImg" alt="QR Escáner Celular"
                src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo urlencode('https://' . $hostActual . '/escaner_mesa.php?mesa=' . $mesaInicial); ?>">
        </div>

        <div style="font-size: 13px; color: #38bdf8; font-weight: 700; margin-bottom: 22px;">
            O abre en el navegador del celular:<br>
            <a href="https://<?php echo $hostActual; ?>/escaner_mesa.php?mesa=<?php echo $mesaInicial; ?>" target="_blank" style="color: #38bdf8; text-decoration: underline;">
                https://<?php echo $hostActual; ?>/escaner_mesa.php?mesa=<?php echo $mesaInicial; ?>
            </a>
        </div>

        <!-- UPLOAD DIRECTO DESDE PC -->
        <div style="border-top: 1px solid var(--border); padding-top: 18px; width: 100%; max-width: 440px;">
            <input type="file" id="inputFotoDirectaPC" accept="image/*" style="display: none;" onchange="subirFotoDirectaPC(this)">
            <button type="button" class="tool-btn" style="padding: 10px 18px; font-size: 13px;" onclick="document.getElementById('inputFotoDirectaPC').click()">
                📂 O subir fotografía de examen desde esta computadora
            </button>
        </div>
    </div>

    <!-- ESPACIO DE TRABAJO A PANTALLA DIVIDIDA (CUANDO HAY EXAMEN) -->
    <div id="workspaceExamen" class="workspace-split">

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
                <img id="imgExamen" class="photo-img" src="" alt="Examen del alumno" style="display:none;">
                <div id="demoSheetPlaceholder" class="demo-sheet-placeholder" style="display:none;">
                    <div style="font-size: 32px; margin-bottom: 8px;">📝</div>
                    <div style="font-size: 15px; font-weight: 800; color: #fff; margin-bottom: 4px;">Examen de Demostración</div>
                    <div style="font-size: 12px; color: var(--muted); margin-bottom: 12px;">Simulando lectura digital de 125 reactivos PAA</div>
                    <div style="background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 8px; padding: 10px; font-size: 11px; text-align: left; color: #cbd5e1;">
                        &bull; <b>Lectura</b>: 45 preguntas<br>
                        &bull; <b>Redacción</b>: 25 preguntas<br>
                        &bull; <b>Matemáticas 1</b>: 45 preguntas múltiple<br>
                        &bull; <b>Matemáticas 2</b>: 10 cuadrículas (Grid)
                    </div>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: AUDITORÍA Y CORRECCIÓN RÁPIDA -->
        <div class="audit-card">
            <!-- ENCABEZADO DEL ALUMNO -->
            <div class="student-header">
                <div style="flex: 1; min-width: 250px;">
                    <div style="font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase;">
                        Mesa <?php echo $mesaInicial; ?> &bull; Auditoría Rápida PAA
                    </div>
                    <div style="margin-top: 4px;">
                        <input type="text" id="inputNombreAlumno" class="student-name-input" value="ALUMNO DETECTADO" placeholder="Nombre completo del alumno">
                    </div>
                </div>
                <div style="text-align: right; font-size: 12px; color: var(--muted);">
                    <div>IA OMR: <b id="lblTiempoIa" style="color: #38bdf8;">2.1s</b></div>
                    <div id="lblHoraRecepcion" style="font-size: 11px; color: #64748b;">—</div>
                </div>
            </div>

            <!-- =======================================================
                 LAS 4 TARJETAS PRINCIPALES DE RESULTADOS PAA
                 (Lectura y Redacción 710, Matemáticas 800, Total PAA, Promedio)
                 ======================================================= -->
            <div class="scores-quad-grid">
                <!-- 1. LECTURA Y REDACCIÓN -->
                <div class="score-quad-card card-verbal">
                    <div class="score-quad-tag">
                        <span>Lectura + Redacción</span>
                        <span style="color:#38bdf8;">Verbal</span>
                    </div>
                    <div class="score-quad-val" style="color: #38bdf8;">
                        <span id="lblPaaVerbal">710</span>
                        <span class="pts-unit">/ 800 pts</span>
                    </div>
                    <div class="score-quad-sub">
                        <b id="lblAciertosVerbal" style="color:#fff;">61</b> / 70 aciertos
                    </div>
                </div>

                <!-- 2. MATEMÁTICAS (1 Y 2) -->
                <div class="score-quad-card card-mate">
                    <div class="score-quad-tag">
                        <span>Matemáticas 1 y 2</span>
                        <span style="color:#fbbf24;">Mate</span>
                    </div>
                    <div class="score-quad-val" style="color: #fbbf24;">
                        <span id="lblPaaMate">800</span>
                        <span class="pts-unit">/ 800 pts</span>
                    </div>
                    <div class="score-quad-sub">
                        <b id="lblAciertosMate" style="color:#fff;">55</b> / 55 aciertos
                    </div>
                </div>

                <!-- 3. PUNTAJE TOTAL PAA -->
                <div class="score-quad-card card-total">
                    <div class="score-quad-tag">
                        <span>Puntaje Total PAA</span>
                        <span style="color:#10b981;">Escala</span>
                    </div>
                    <div class="score-quad-val" style="color: #10b981;">
                        <span id="lblPuntajePaaTotal">1,510</span>
                        <span class="pts-unit">/ 1600 pts</span>
                    </div>
                    <div class="score-quad-sub">
                        <b id="lblTotalAciertos" style="color:#fff;">116</b> / 125 reactivos
                    </div>
                </div>

                <!-- 4. PROMEDIO DEL EXAMEN (OFICIAL 0-100) -->
                <div class="score-quad-card card-promedio">
                    <div class="score-quad-tag">
                        <span>Promedio Examen</span>
                        <span style="color:#c084fc;">UdeG/Lumière</span>
                    </div>
                    <div class="score-quad-val" style="color: #c084fc;">
                        <span id="lblPuntajePromedio100">92.5</span>
                        <span class="pts-unit">/ 100</span>
                    </div>
                    <div class="score-quad-sub">
                        Oficial (Escala 100)
                    </div>
                </div>
            </div>

            <!-- BARRA DE RESUMEN DE CONTEOS Y ESTADO DE RECALCULACIÓN -->
            <div class="counters-bar">
                <div style="display: flex; gap: 14px; align-items: center;">
                    <span style="color: #34d399;">🟢 <b id="countAciertosTotal">116</b> Aciertos</span>
                    <span style="color: #f87171;">🔴 <b id="countErroresTotal">7</b> Errores</span>
                    <span style="color: #94a3b8;">⚪ <b id="countOmitidasTotal">2</b> Omitidas</span>
                </div>
                <div id="lblRecalcStatus" style="display: none;" class="recalc-pill">
                    ⚡ Recalculando...
                </div>
            </div>

            <!-- =======================================================
                 LAS 4 PESTAÑAS DE SECCIONES + VER TODAS
                 1. Lectura (1-45)
                 2. Redacción (1-25)
                 3. Matemáticas 1 (1-45 Opción múltiple)
                 4. Matemáticas 2 (46-55 Grid / Cuadrícula)
                 ======================================================= -->
            <div class="sections-tabs-row" id="sectionsTabsRow">
                <button type="button" class="sec-tab-btn active" id="tabSec_lectura" onclick="cambiarSeccion('lectura')">
                    <span>📖 1. Lectura (1-45)</span>
                    <span class="sec-tab-pill" id="pillAciertos_lectura">0/45</span>
                </button>
                <button type="button" class="sec-tab-btn" id="tabSec_redaccion" onclick="cambiarSeccion('redaccion')">
                    <span>✍️ 2. Redacción (1-25)</span>
                    <span class="sec-tab-pill" id="pillAciertos_redaccion">0/25</span>
                </button>
                <button type="button" class="sec-tab-btn" id="tabSec_matematicas_1" onclick="cambiarSeccion('matematicas_1')">
                    <span>📐 3. Matemáticas 1 (1-45)</span>
                    <span class="sec-tab-pill" id="pillAciertos_matematicas_1">0/45</span>
                </button>
                <button type="button" class="sec-tab-btn" id="tabSec_matematicas_2" onclick="cambiarSeccion('matematicas_2')">
                    <span>🔢 4. Matemáticas 2 (46-55 Grid)</span>
                    <span class="sec-tab-pill" id="pillAciertos_matematicas_2">0/10</span>
                </button>
                <button type="button" class="sec-tab-btn" id="tabSec_todas" onclick="cambiarSeccion('todas')">
                    <span>📋 Ver Todo (125)</span>
                </button>
            </div>

            <!-- FILTROS RÁPIDOS DE REACTIVOS -->
            <div class="filters-row">
                <button type="button" class="filter-chip danger-chip active" id="chipErrores" onclick="filtrarReactivos('errores')">
                    🔴 Solo Errores (<span id="countErroresChip">0</span>)
                </button>
                <button type="button" class="filter-chip" id="chipOmitidas" onclick="filtrarReactivos('omitidas')">
                    ⚪ Omitidas (<span id="countOmitidasChip">0</span>)
                </button>
                <button type="button" class="filter-chip success-chip" id="chipAciertos" onclick="filtrarReactivos('aciertos')">
                    🟢 Aciertos (<span id="countAciertosChip">0</span>)
                </button>
                <button type="button" class="filter-chip" id="chipTodos" onclick="filtrarReactivos('todos')">
                    Mostrar Todos
                </button>
            </div>

            <!-- LISTA INTERACTIVA DE REACTIVOS DE LA SECCIÓN ACTIVA -->
            <div id="listaReactivos" class="questions-list">
                <!-- Se llena dinámicamente con JavaScript -->
            </div>

            <!-- BARRA INFERIOR DE ACCIONES -->
            <div class="actions-footer">
                <button type="button" class="btn-descartar" onclick="descartarMesa()">
                    🗑️ Descartar
                </button>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="tool-btn" onclick="copiarLigaExamen()" title="Copiar enlace directo de este examen">
                        🔗 Copiar Liga
                    </button>
                    <button type="button" class="btn-sellar" id="btnSellar" onclick="sellarExamenDefinitivo()">
                        💾 Sellar Definitivo
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- SCRIPT PRINCIPAL DE LA MESA -->
    <script>
        const MESA_NUM = <?php echo $mesaInicial; ?>;
        const ID_DIRECTO = "<?php echo addslashes($idDirecto); ?>";

        let examenActual = null;
        let respuestasTrabajo = {
            lectura: {},
            redaccion: {},
            matematicas: {}
        };

        let seccionActiva = 'lectura'; // 'lectura', 'redaccion', 'matematicas_1', 'matematicas_2', 'todas'
        let filtroActivo = 'errores'; // 'errores', 'omitidas', 'aciertos', 'todos'

        let zoomNivel = 1.0;
        let rotacionGrados = 0;
        let lastExamId = '';
        let pollingTimer = null;
        let recalcularTimeout = null;

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
            if (pollingTimer) clearInterval(pollingTimer);
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
                console.warn('Error en polling de mesa:', e);
            }
        }

        async function cargarExamenPorId(id) {
            try {
                const res = await fetch(`api_mesas.php?accion=cargar_examen&id=${encodeURIComponent(id)}`);
                const data = await res.json();
                if (data.exito && data.examen) {
                    renderExamenEnMesa(data.examen);
                } else {
                    alert('No se encontró el examen especificado o ya fue archivado.');
                    mostrarStandby();
                }
            } catch(e) {
                mostrarStandby();
            }
        }

        // CARGAR EXAMEN DE DEMOSTRACIÓN (Uno de los requerimientos clave)
        async function cargarExamenDemo() {
            try {
                const res = await fetch(`api_mesas.php?accion=cargar_ejemplo&mesa=${MESA_NUM}`);
                const data = await res.json();
                if (data.exito && data.examen) {
                    renderExamenEnMesa(data.examen);
                } else {
                    alert('No se pudo cargar el examen de demostración.');
                }
            } catch(e) {
                alert('Error al conectar con la API de Mesas.');
            }
        }

        function mostrarStandby() {
            document.getElementById('pantallaStandby').style.display = 'flex';
            document.getElementById('workspaceExamen').style.display = 'none';
            document.getElementById('lblMesaStatus').textContent = `Mesa ${MESA_NUM} Conectada`;
        }

        // 2. RENDERIZAR EXAMEN EN LA MESA
        function renderExamenEnMesa(examen) {
            examenActual = examen;
            lastExamId = examen.id || '';
            respuestasTrabajo = JSON.parse(JSON.stringify(examen.respuestas || {}));
            if (!respuestasTrabajo.lectura) respuestasTrabajo.lectura = {};
            if (!respuestasTrabajo.redaccion) respuestasTrabajo.redaccion = {};
            if (!respuestasTrabajo.matematicas) respuestasTrabajo.matematicas = {};

            document.getElementById('pantallaStandby').style.display = 'none';
            document.getElementById('workspaceExamen').style.display = 'grid';
            document.getElementById('lblMesaStatus').textContent = `Mesa ${MESA_NUM}: Examen Activo`;

            // Visor de foto
            const img = document.getElementById('imgExamen');
            const placeholder = document.getElementById('demoSheetPlaceholder');

            if (examen.foto_archivo || examen.foto_url) {
                img.src = examen.foto_url || `uploads/${examen.foto_archivo}`;
                img.style.display = 'block';
                placeholder.style.display = 'none';
                resetFoto();
            } else {
                img.style.display = 'none';
                placeholder.style.display = 'block';
            }

            // Metadatos del Alumno
            document.getElementById('inputNombreAlumno').value = examen.nombre_alumno || 'ALUMNO DETECTADO';
            document.getElementById('lblTiempoIa').textContent = (examen.duracion_ia || '2.1') + 's';
            document.getElementById('lblHoraRecepcion').textContent = examen.fecha || '';

            // Actualizar indicadores numéricos oficiales
            actualizarIndicadoresCalificacion(examen.calificacion);

            // Renderizar la lista de reactivos de la sección actual
            renderListaReactivos();
        }

        // 3. ACTUALIZAR LAS 4 TARJETAS DE PUNTAJE Y CONTADORES
        function actualizarIndicadoresCalificacion(cal) {
            if (!cal) return;

            // 1. Lectura y Redacción (Verbal PAA)
            const verbal = cal.paa_verbal_escala || 200;
            const aciertosVerbal = cal.aciertos_verbal || 0;
            document.getElementById('lblPaaVerbal').textContent = verbal;
            document.getElementById('lblAciertosVerbal').textContent = aciertosVerbal;

            // 2. Matemáticas (1 y 2)
            const mate = cal.paa_mate_escala || 200;
            const aciertosMate = cal.aciertos_mate || 0;
            document.getElementById('lblPaaMate').textContent = mate;
            document.getElementById('lblAciertosMate').textContent = aciertosMate;

            // 3. Puntaje Total PAA
            const total = cal.paa_total_escala || (verbal + mate);
            const aciertosTotal = cal.total_aciertos || 0;
            document.getElementById('lblPuntajePaaTotal').textContent = Number(total).toLocaleString();
            document.getElementById('lblTotalAciertos').textContent = aciertosTotal;

            // 4. Promedio del Examen (Escala 100 UdeG/Lumière)
            const promedio100 = (cal.puntaje_escala_100 !== undefined)
                ? Number(cal.puntaje_escala_100).toFixed(1)
                : ((total - 400) / 12).toFixed(1);
            document.getElementById('lblPuntajePromedio100').textContent = promedio100;

            // Conteo global de Errores y Omitidas
            const totalPreguntas = cal.total_preguntas_evaluadas || 125;
            let totalOmitidas = 0;
            let totalErrores = 0;

            // Leer secciones del objeto cal.secciones de forma segura
            const secObj = cal.secciones || {};
            const secArray = Array.isArray(secObj) ? secObj : Object.values(secObj);

            secArray.forEach(s => {
                totalOmitidas += (s.omitidas || 0);
                totalErrores += (s.errores || 0);
            });

            // Si por alguna razón errores no viene explícito, deducirlo
            if (totalErrores === 0 && (totalPreguntas - aciertosTotal - totalOmitidas) > 0) {
                totalErrores = Math.max(0, totalPreguntas - aciertosTotal - totalOmitidas);
            }

            document.getElementById('countAciertosTotal').textContent = aciertosTotal;
            document.getElementById('countErroresTotal').textContent = totalErrores;
            document.getElementById('countOmitidasTotal').textContent = totalOmitidas;

            document.getElementById('countErroresChip').textContent = totalErrores;
            document.getElementById('countOmitidasChip').textContent = totalOmitidas;
            document.getElementById('countAciertosChip').textContent = aciertosTotal;

            // Actualizar pills de las 4 secciones
            actualizarPillsSecciones(cal);
        }

        function actualizarPillsSecciones(cal) {
            if (!cal || !cal.secciones) return;
            const secs = cal.secciones;

            // Lectura
            if (secs.lectura) {
                const el = document.getElementById('pillAciertos_lectura');
                if (el) el.textContent = `${secs.lectura.aciertos || 0}/${secs.lectura.total_clave || 45}`;
            }

            // Redacción
            if (secs.redaccion) {
                const el = document.getElementById('pillAciertos_redaccion');
                if (el) el.textContent = `${secs.redaccion.aciertos || 0}/${secs.redaccion.total_clave || 25}`;
            }

            // Matemáticas 1 y 2
            if (secs.matematicas && secs.matematicas.detalles) {
                const detalles = secs.matematicas.detalles;
                const m1 = detalles.filter(d => parseInt(d.num, 10) <= 45);
                const m2 = detalles.filter(d => parseInt(d.num, 10) >= 46);

                const acM1 = m1.filter(d => d.estado === 'acierto').length;
                const acM2 = m2.filter(d => d.estado === 'acierto').length;

                const el1 = document.getElementById('pillAciertos_matematicas_1');
                if (el1) el1.textContent = `${acM1}/${m1.length || 45}`;

                const el2 = document.getElementById('pillAciertos_matematicas_2');
                if (el2) el2.textContent = `${acM2}/${m2.length || 10}`;
            }
        }

        // 4. MANEJO DE PESTAÑAS DE SECCIONES (Lectura, Redacción, Mate 1, Mate 2, Todas)
        function cambiarSeccion(sec) {
            seccionActiva = sec;
            document.querySelectorAll('.sec-tab-btn').forEach(btn => btn.classList.remove('active'));
            const activeBtn = document.getElementById('tabSec_' + sec);
            if (activeBtn) activeBtn.classList.add('active');

            renderListaReactivos();
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

        // 5. RENDERIZADO MODULAR DE LAS 4 SECCIONES Y SUS RESPUESTAS
        function renderListaReactivos() {
            const container = document.getElementById('listaReactivos');
            if (!container || !examenActual || !examenActual.calificacion) return;

            const cal = examenActual.calificacion;
            const secs = cal.secciones || {};

            // Construcción del paquete de las 4 secciones
            const seccionesDef = [
                {
                    id: 'lectura',
                    nombre: '📖 Sección 1: Lectura (Reactivos 1 al 45)',
                    secKey: 'lectura',
                    items: secs.lectura?.detalles || []
                },
                {
                    id: 'redaccion',
                    nombre: '✍️ Sección 2: Redacción (Reactivos 1 al 25)',
                    secKey: 'redaccion',
                    items: secs.redaccion?.detalles || []
                },
                {
                    id: 'matematicas_1',
                    nombre: '📐 Sección 3: Matemáticas 1 - Opción Múltiple (Reactivos 1 al 45)',
                    secKey: 'matematicas',
                    items: (secs.matematicas?.detalles || []).filter(item => parseInt(item.num, 10) <= 45)
                },
                {
                    id: 'matematicas_2',
                    nombre: '🔢 Sección 4: Matemáticas 2 - Cuadrícula / Grid (Reactivos 46 al 55)',
                    secKey: 'matematicas',
                    items: (secs.matematicas?.detalles || []).filter(item => parseInt(item.num, 10) >= 46)
                }
            ];

            let html = '';
            let totalRenderizados = 0;

            seccionesDef.forEach(secDef => {
                // Si estamos en una pestaña individual y no coincide, saltarla
                if (seccionActiva !== 'todas' && seccionActiva !== secDef.id) {
                    return;
                }

                // Filtrar reactivos de esta sección
                const itemsFiltrados = secDef.items.filter(item => {
                    const estado = item.estado; // 'acierto', 'error', 'omitida'
                    if (filtroActivo === 'errores' && estado !== 'error') return false;
                    if (filtroActivo === 'omitidas' && estado !== 'omitida') return false;
                    if (filtroActivo === 'aciertos' && estado !== 'acierto') return false;
                    return true;
                });

                if (itemsFiltrados.length === 0 && seccionActiva === 'todas') {
                    return; // No imprimir encabezado si en "todas" no hay nada con este filtro
                }

                // Si estamos viendo "todas" o hay elementos, agregar encabezado de sección
                if (seccionActiva === 'todas' || itemsFiltrados.length > 0) {
                    html += `
                        <div class="section-separator-title">
                            <span>${secDef.nombre}</span>
                            <span style="font-size: 11px; color: #cbd5e1;">${itemsFiltrados.length} reactivos mostrados</span>
                        </div>
                    `;
                }

                itemsFiltrados.forEach(item => {
                    totalRenderizados++;
                    const num = item.num;
                    const estado = item.estado; // 'acierto', 'error', 'omitida'
                    const secKey = secDef.secKey;
                    const marcada = respuestasTrabajo[secKey]?.[num] || '';
                    const correcta = item.correcta || '';

                    const rowClass = (estado === 'acierto') ? 'row-acierto' : ((estado === 'error') ? 'row-error' : 'row-omitida');
                    const tagClass = (estado === 'acierto') ? 'tag-acierto' : ((estado === 'error') ? 'tag-error' : 'tag-omitida');
                    const tagTxt = (estado === 'acierto') ? '🟢 Correcto' : ((estado === 'error') ? `🔴 Incorrecto (Era ${correcta})` : `⚪ En Blanco (Era ${correcta})`);

                    const esGrid = (secDef.id === 'matematicas_2' || (secKey === 'matematicas' && parseInt(num, 10) >= 46));

                    if (esGrid) {
                        // REACTIVO ABIERTO / CUADRÍCULA
                        html += `
                            <div class="q-row ${rowClass}" id="qRow_${secKey}_${num}">
                                <div class="q-left-info">
                                    <span class="q-badge-num">#${num}</span>
                                    <span class="q-status-tag ${tagClass}">${tagTxt}</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 11px; color: #fbbf24; font-weight: 700;">
                                        Clave: <b>${escapeHtml(correcta)}</b>
                                    </span>
                                    <input type="text" class="grid-input-audit" value="${escapeHtml(marcada)}"
                                        placeholder="—" maxlength="8"
                                        onchange="corregirRespuestaDirecta('${secKey}', '${num}', this.value)"
                                        title="Escribe el número o fracción corregido">
                                    <button type="button" class="tool-btn" style="padding: 4px 8px; font-size: 11px;"
                                        onclick="corregirRespuestaDirecta('${secKey}', '${num}', '')" title="Borrar y dejar en blanco">
                                        ⚪
                                    </button>
                                </div>
                            </div>
                        `;
                    } else {
                        // REACTIVO DE OPCIÓN MÚLTIPLE (A, B, C, D)
                        const opts = ['A', 'B', 'C', 'D'];
                        const btns = opts.map(opt => {
                            const isSelected = (marcada === opt);
                            const isKey = (correcta === opt);
                            let cls = 'bubble-btn-audit';
                            if (isSelected) cls += ' active';
                            if (isKey) cls += ' is-correct-key';
                            return `<button type="button" class="${cls}"
                                onclick="corregirRespuestaDirecta('${secKey}', '${num}', '${opt}')"
                                title="${isKey ? 'Clave oficial' : ''}">${opt}</button>`;
                        }).join('');

                        html += `
                            <div class="q-row ${rowClass}" id="qRow_${secKey}_${num}">
                                <div class="q-left-info">
                                    <span class="q-badge-num">#${num}</span>
                                    <span class="q-status-tag ${tagClass}">${tagTxt}</span>
                                </div>
                                <div class="q-bubbles-box">
                                    ${btns}
                                    <button type="button" class="bubble-btn-audit ${!marcada ? 'active' : ''}"
                                        style="width: 26px; height: 26px; font-size: 10px;"
                                        onclick="corregirRespuestaDirecta('${secKey}', '${num}', '')"
                                        title="Omitir (dejar en blanco)">⚪</button>
                                </div>
                            </div>
                        `;
                    }
                });
            });

            if (totalRenderizados === 0) {
                html = `
                    <div style="text-align: center; padding: 40px 16px; color: var(--muted); font-size: 13px;">
                        <div style="font-size: 24px; margin-bottom: 6px;">🔍</div>
                        No hay reactivos que coincidan con la categoría <b>"${filtroActivo}"</b> en esta sección.
                        <div style="margin-top: 10px;">
                            <button type="button" class="tool-btn" onclick="filtrarReactivos('todos')">
                                Mostrar Todos los Reactivos
                            </button>
                        </div>
                    </div>
                `;
            }

            container.innerHTML = html;
        }

        // 6. RECALCULAR EN VIVO AL CORREGIR CUALQUIER RESPUESTA
        async function corregirRespuestaDirecta(sec, num, val) {
            const clean = String(val || '').trim().toUpperCase();
            if (!respuestasTrabajo[sec]) respuestasTrabajo[sec] = {};

            if (clean === '') {
                delete respuestasTrabajo[sec][num];
            } else {
                respuestasTrabajo[sec][num] = clean;
            }

            // Indicador visual de recálculo
            mostrarRecalculando(true);

            // Cancelar llamadas acumuladas para evitar spam de peticiones
            if (recalcularTimeout) clearTimeout(recalcularTimeout);

            recalcularTimeout = setTimeout(async () => {
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
                        mostrarRecalculando(false, true);
                    } else {
                        mostrarRecalculando(false, false);
                    }
                } catch(e) {
                    console.warn('Error recalculando en vivo:', e);
                    mostrarRecalculando(false, false);
                }
            }, 120);
        }

        function mostrarRecalculando(cargando, exito = true) {
            const el = document.getElementById('lblRecalcStatus');
            if (!el) return;

            if (cargando) {
                el.style.display = 'inline-block';
                el.className = 'recalc-pill';
                el.textContent = '⚡ Recalculando...';
            } else {
                if (exito) {
                    el.style.display = 'inline-block';
                    el.className = 'status-indicator';
                    el.style.padding = '2px 8px';
                    el.textContent = '✅ Actualizado';
                    setTimeout(() => {
                        el.style.display = 'none';
                    }, 1200);
                } else {
                    el.style.display = 'none';
                }
            }
        }

        // 7. HERRAMIENTAS DE VISOR DE FOTO (Zoom / Pan / Rotar)
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

        // 8. SELLAR Y GUARDAR DEFINITIVO EN BASE DE DATOS
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
            formData.append('id', examenActual.id || '');
            formData.append('nombre_alumno', nombre);
            formData.append('carrera', examenActual.carrera || '');
            formData.append('foto_archivo', examenActual.foto_archivo || '');
            formData.append('respuestas', JSON.stringify(respuestasTrabajo));
            formData.append('respuestas_originales', JSON.stringify(examenActual.respuestas_originales_ia || {}));

            try {
                const res = await fetch('api_mesas.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.exito) {
                    alert(`✅ ¡Examen de ${nombre} sellado con éxito!\nPuntaje PAA Total: ${data.puntaje_paa_total} pts (${data.total_aciertos} aciertos).`);
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

        // 9. DESCARTAR MESA SIN GUARDAR
        async function descartarMesa() {
            if (!confirm(`¿Descartar este examen de la Mesa ${MESA_NUM} sin guardar en la base de datos?`)) return;

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
