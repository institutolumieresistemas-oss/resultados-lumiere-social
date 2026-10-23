<?php
/**
 * ====================================================================
 * Escáner Móvil de Exámenes - Calificación por Mesas
 * Instituto Lumière - calificador.lumieresocial.com / resultados.lumieresocial.com
 * ====================================================================
 */
require_once __DIR__ . '/config.php';

$mesaInicial = intval($_GET['mesa'] ?? 1);
if ($mesaInicial < 1 || $mesaInicial > 5) $mesaInicial = 1;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Escáner Móvil - Mesas de Calificación | Instituto Lumière</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #c01b2f;
            --primary-hover: #9e1324;
            --bg: #090d16;
            --card-bg: #131b2e;
            --card-surface: #1a243b;
            --border: #263352;
            --text: #f8fafc;
            --muted: #94a3b8;
            --success: #10b981;
            --accent: #38bdf8;
            --warning: #fbbf24;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 16px 14px 40px 14px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            max-width: 500px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 16px;
        }
        .header-badge {
            background: rgba(56, 189, 248, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.35);
            color: var(--accent);
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
        }
        .header h1 {
            margin: 0 0 4px 0;
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
        }
        .header p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        /* Selector de Mesas */
        .mesas-tabs {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
            margin-bottom: 16px;
        }
        .mesa-btn {
            background: var(--card-surface);
            border: 1px solid var(--border);
            color: var(--muted);
            padding: 10px 4px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }
        .mesa-btn.active {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border-color: #38bdf8;
            color: #fff;
            box-shadow: 0 4px 12px rgba(56, 189, 248, 0.35);
            transform: scale(1.04);
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
            text-align: center;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #34d399;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 16px;
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

        /* Botón de Captura de Cámara */
        .btn-capture {
            background: linear-gradient(135deg, #c01b2f 0%, #9e1324 100%);
            color: #fff;
            border: none;
            padding: 20px 24px;
            border-radius: 16px;
            font-size: 17px;
            font-weight: 800;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 6px 20px rgba(192, 27, 47, 0.45);
            transition: all 0.2s;
        }
        .btn-capture:hover {
            transform: translateY(-2px);
        }
        .btn-capture:active {
            transform: scale(0.98);
        }

        .preview-box {
            display: none;
            margin-top: 16px;
            text-align: center;
        }
        .preview-img {
            max-width: 100%;
            max-height: 280px;
            border-radius: 12px;
            border: 2px solid var(--border);
            object-fit: contain;
            background: #000;
        }

        .btn-enviar {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            border: none;
            padding: 16px 20px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 14px;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
        }

        /* Spinner */
        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.15);
            border-top: 3px solid var(--accent);
            border-radius: 50%;
            width: 36px;
            height: 36px;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 12px auto;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Resultado Preliminar */
        .result-box {
            display: none;
            background: linear-gradient(145deg, #0e1e38 0%, #172b50 100%);
            border: 2px solid var(--accent);
            border-radius: 16px;
            padding: 20px;
            margin-top: 16px;
            text-align: center;
            animation: popIn 0.3s ease;
        }
        @keyframes popIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .score-huge {
            font-size: 38px;
            font-weight: 900;
            color: #38bdf8;
            margin: 8px 0;
            line-height: 1;
        }

        .btn-next {
            background: #1e293b;
            border: 1px solid var(--border);
            color: #fff;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <div class="header-badge">
                <span>INSTITUTO LUMIÈRE</span>
            </div>
            <h1>📲 Escáner de Mesas</h1>
            <p>Toma la foto del examen y se proyectará en tu monitor al instante.</p>
        </div>

        <!-- SELECTOR DE MESAS 1 A 5 -->
        <div class="mesas-tabs">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="button" class="mesa-btn <?php echo ($i === $mesaInicial) ? 'active' : ''; ?>" 
                    onclick="cambiarMesa(<?php echo $i; ?>)" id="btnMesa<?php echo $i; ?>">
                    Mesa <?php echo $i; ?>
                </button>
            <?php endfor; ?>
        </div>

        <!-- TARJETA PRINCIPAL DE CAPTURA -->
        <div class="card" id="cardCaptura">
            <div class="status-pill">
                <span class="pulse-dot"></span>
                <span>Vinculado a: <b id="lblMesaActual" style="color: #fff;">Mesa <?php echo $mesaInicial; ?></b></span>
            </div>

            <p style="font-size: 13px; color: var(--muted); margin: 0 0 16px 0;">
                Asegúrate de que la hoja esté plana, con buena luz y encuadrando las 3 secciones.
            </p>

            <!-- INPUT DE CÁMARA NATIVO -->
            <input type="file" id="inputFoto" accept="image/*" capture="environment" style="display: none;" onchange="alSeleccionarFoto(this)">
            <button type="button" class="btn-capture" onclick="document.getElementById('inputFoto').click()">
                <span style="font-size: 24px;">📸</span>
                <span>Tomar Foto del Examen</span>
            </button>

            <!-- PREVIEW DE LA FOTO CAPTURADA -->
            <div id="boxPreview" class="preview-box">
                <img id="imgPreview" class="preview-img" alt="Vista previa">
                <button type="button" class="btn-enviar" id="btnEnviar" onclick="enviarFotoAMesa()">
                    <span>🚀 Analizar y Proyectar en Mesa <span id="lblMesaBtn"><?php echo $mesaInicial; ?></span></span>
                </button>
                <button type="button" style="background: transparent; border: none; color: var(--muted); font-size: 12px; margin-top: 10px; cursor: pointer; text-decoration: underline;" onclick="reiniciarCaptura()">
                    Tomar otra foto
                </button>
            </div>

            <!-- SPINNER DE CARGA -->
            <div id="boxCargando" style="display: none; padding: 24px 10px;">
                <div class="spinner"></div>
                <b style="color: #38bdf8; font-size: 16px;">Leyendo hoja con IA...</b>
                <p style="color: var(--muted); font-size: 12px; margin: 6px 0 0 0;">Contrastando contra Hoja Base en tiempo real (~2.5s)</p>
            </div>

            <!-- RESULTADO PRELIMINAR EN CELULAR -->
            <div id="boxResultado" class="result-box">
                <div style="font-size: 12px; color: #34d399; font-weight: 800; letter-spacing: 0.5px;">
                    ✅ ¡ENVIADO A MESA <span id="resMesaNum">1</span>!
                </div>
                <div id="resAlumnoNombre" style="font-size: 18px; font-weight: 800; color: #fff; margin-top: 6px;">
                    ALUMNO DETECTADO
                </div>
                <div class="score-huge" id="resPuntajePaa">1,210</div>
                <div style="font-size: 12px; color: var(--muted); margin-bottom: 12px;">Puntaje PAA Preliminar</div>

                <div style="display: flex; justify-content: space-around; background: rgba(0,0,0,0.3); padding: 8px; border-radius: 10px; font-size: 13px;">
                    <div>🟢 Aciertos: <b id="resAciertos" style="color: #34d399;">96</b></div>
                    <div>🔴 Errores: <b id="resErrores" style="color: #ef4444;">19</b></div>
                </div>

                <p style="font-size: 12px; color: #94a3b8; margin: 12px 0 6px 0;">
                    🖥️ El examen ya está en pantalla completa de tu PC para que puedas auditar y sellar.
                </p>

                <button type="button" class="btn-next" onclick="siguienteExamen()">
                    <span>📸 Escanear Siguiente Examen</span>
                </button>
            </div>
        </div>

        <!-- PIE DE PÁGINA -->
        <div style="text-align: center; font-size: 12px; color: var(--muted);">
            <a href="mesas.php?mesa=<?php echo $mesaInicial; ?>" target="_blank" style="color: #38bdf8; text-decoration: none; font-weight: 700;">
                🖥️ Ir a la pantalla de Mesa <?php echo $mesaInicial; ?> en este dispositivo
            </a>
        </div>
    </div>

    <script>
        let mesaActual = <?php echo $mesaInicial; ?>;
        let fotoArchivoActual = null;

        function cambiarMesa(num) {
            mesaActual = num;
            document.querySelectorAll('.mesa-btn').forEach((b, idx) => {
                b.classList.toggle('active', (idx + 1) === num);
            });
            document.getElementById('lblMesaActual').textContent = `Mesa ${num}`;
            document.getElementById('lblMesaBtn').textContent = num;
        }

        function alSeleccionarFoto(input) {
            if (!input.files || !input.files[0]) return;
            fotoArchivoActual = input.files[0];

            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imgPreview').src = e.target.result;
                document.getElementById('boxPreview').style.display = 'block';
                document.getElementById('boxResultado').style.display = 'none';
                document.getElementById('boxPreview').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            };
            reader.readAsDataURL(fotoArchivoActual);
        }

        function reiniciarCaptura() {
            fotoArchivoActual = null;
            document.getElementById('inputFoto').value = '';
            document.getElementById('boxPreview').style.display = 'none';
            document.getElementById('boxResultado').style.display = 'none';
            document.getElementById('boxCargando').style.display = 'none';
        }

        async function enviarFotoAMesa() {
            if (!fotoArchivoActual) {
                alert('Toma primero una fotografía del examen.');
                return;
            }

            document.getElementById('boxPreview').style.display = 'none';
            document.getElementById('boxCargando').style.display = 'block';

            const formData = new FormData();
            formData.append('accion', 'enviar_a_mesa');
            formData.append('mesa', mesaActual);
            formData.append('foto', fotoArchivoActual);

            try {
                const res = await fetch('api_mesas.php', { method: 'POST', body: formData });
                const data = await res.json();

                document.getElementById('boxCargando').style.display = 'none';

                if (data.exito) {
                    // Mostrar tarjeta de confirmación en el celular
                    document.getElementById('resMesaNum').textContent = data.mesa;
                    document.getElementById('resAlumnoNombre').textContent = data.nombre || 'ALUMNO REGISTRADO';
                    document.getElementById('resPuntajePaa').textContent = data.puntaje_paa_total || 0;
                    document.getElementById('resAciertos').textContent = data.total_aciertos || 0;
                    document.getElementById('resErrores').textContent = data.total_errores || 0;

                    document.getElementById('boxResultado').style.display = 'block';
                    document.getElementById('boxResultado').scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    alert('⚠️ Error al analizar la hoja:\n' + (data.error || 'No se pudo leer la imagen'));
                    document.getElementById('boxPreview').style.display = 'block';
                }
            } catch(e) {
                document.getElementById('boxCargando').style.display = 'none';
                document.getElementById('boxPreview').style.display = 'block';
                alert('Error de conexión con el servidor. Verifica tu señal Wi-Fi / datos móviles.');
            }
        }

        function siguienteExamen() {
            reiniciarCaptura();
            document.getElementById('inputFoto').click();
        }
    </script>
</body>
</html>
