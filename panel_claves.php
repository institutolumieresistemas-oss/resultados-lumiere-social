<?php
/**
 * ====================================================================
 * Panel Web de la Hoja Base de Respuestas (Instituto Lumière)
 * ====================================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/calificador.php';

$mensaje = null;
$tipoMensaje = 'info';

$hojaBase = cargar_hoja_base();

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar_base') {
        $secciones = ['lectura', 'redaccion', 'matematicas', 'ingles'];
        $nuevaBase = [
            'nombre' => 'Hoja Base Lumière PAA',
            'fecha' => date('d/m/Y H:i')
        ];

        foreach ($secciones as $sec) {
            $nuevaBase[$sec] = [];
            $textoRapido = trim($_POST["rapido_{$sec}"] ?? '');

            if (!empty($textoRapido)) {
                $tokens = preg_split('/[\s,]+/', $textoRapido);
                $contadorAuto = 1;

                foreach ($tokens as $token) {
                    $token = trim($token);
                    if (empty($token)) continue;

                    if (strpos($token, ':') !== false) {
                        list($num, $resp) = explode(':', $token, 2);
                        $nuevaBase[$sec][trim($num)] = strtoupper(trim($resp));
                    } else {
                        $nuevaBase[$sec][(string)$contadorAuto] = strtoupper($token);
                        $contadorAuto++;
                    }
                }
            }
        }

        guardar_hoja_base($nuevaBase);
        $hojaBase = $nuevaBase;
        $mensaje = "✅ ¡Hoja Base guardada correctamente en el sistema!";
        $tipoMensaje = 'success';
    }
}

// Convertir array a texto rápido para textarea
function format_rapido($arr) {
    if (empty($arr)) return '';
    $partes = [];
    foreach ($arr as $num => $resp) {
        $partes[] = "{$num}:{$resp}";
    }
    return implode(' ', $partes);
}

$countLec = count($hojaBase['lectura'] ?? []);
$countRed = count($hojaBase['redaccion'] ?? []);
$countMat = count($hojaBase['matematicas'] ?? []);
$countIng = count($hojaBase['ingles'] ?? []);
$total = $countLec + $countRed + $countMat + $countIng;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoja Base de Respuestas - Instituto Lumière</title>
    <style>
        :root {
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --bg: #0f172a;
            --card-bg: #1e293b;
            --border: #334155;
            --text: #f8fafc;
            --muted: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 24px; }
        .container { max-width: 820px; margin: 0 auto; }
        .card { background: var(--card-bg); border-radius: 14px; padding: 24px; border: 1px solid var(--border); margin-bottom: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        h1 { margin: 0 0 8px 0; font-size: 24px; color: #fff; }
        h2 { font-size: 18px; margin: 0 0 16px 0; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: rgba(16, 185, 129, 0.2); border: 1px solid var(--success); color: #6ee7b7; }
        .alert-info { background: rgba(59, 130, 246, 0.2); border: 1px solid var(--primary); color: #93c5fd; }
        .field { margin-bottom: 18px; }
        label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #cbd5e1; }
        textarea {
            width: 100%;
            padding: 10px 14px;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #fff;
            box-sizing: border-box;
            font-size: 14px;
            font-family: monospace;
            resize: vertical;
            min-height: 85px;
        }
        textarea:focus { outline: none; border-color: var(--primary); }
        .help-text { font-size: 12px; color: var(--muted); margin-top: 4px; }
        button {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
            background: var(--primary);
            color: white;
        }
        button:hover { background: var(--primary-hover); }
        .btn-secondary { background: #334155; color: white; text-decoration: none; padding: 10px 16px; border-radius: 8px; font-weight: 600; font-size: 14px; }
        .stat-badge { background: #0f172a; padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border); font-size: 13px; display: inline-block; margin-right: 8px; margin-top: 6px; }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1>🔑 Hoja Base de Respuestas Correctas</h1>
            <p style="color: var(--muted); margin: 4px 0 0 0;">Instituto Lumière - PAA College Board</p>
        </div>
        <div>
            <a href="index.php" class="btn-secondary">🏠 Inicio</a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?php echo $tipoMensaje; ?>"><?php echo $mensaje; ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>📊 Estado Actual de la Hoja Base</h2>
        <div>
            <span class="stat-badge">📖 Lectura: <b><?php echo $countLec; ?></b> reactivos</span>
            <span class="stat-badge">✍️ Redacción: <b><?php echo $countRed; ?></b> reactivos</span>
            <span class="stat-badge">📐 Matemáticas: <b><?php echo $countMat; ?></b> reactivos</span>
            <span class="stat-badge">🇬🇧 Inglés: <b><?php echo $countIng; ?></b> reactivos</span>
            <span class="stat-badge" style="border-color: #3b82f6;">🏆 Total Claves: <b><?php echo $total; ?></b></span>
        </div>

    </div>

    <!-- Banner de Dictado y Editor Visual -->
    <div style="background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.35); border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div>
            <b style="color: #38bdf8; font-size: 14px;">🎙️ Editor Visual y Dictado por Voz</b>
            <p style="margin: 3px 0 0 0; font-size: 13px; color: var(--muted);">Captura y dicta las respuestas oficiales directamente en casillas interactivas sin escribir texto rápido.</p>
        </div>
        <a href="captura_base.php" target="_blank" style="text-decoration: none; background: #0284c7; color: #fff; font-weight: 700; font-size: 13px; padding: 10px 16px; border-radius: 8px; display: inline-flex; align-items: center; gap: 6px;">
            <span>🎙️ Abrir Editor Visual / Voz ↗</span>
        </a>
    </div>

    <!-- Formulario de Edición -->
    <div class="card">
        <h2>📝 Editar Respuestas Correctas</h2>

        <form method="POST">
            <input type="hidden" name="accion" value="guardar_base">

            <!-- Lectura -->
            <div class="field">
                <label>📖 Parte 1: Lectura (45 reactivos)</label>
                <textarea name="rapido_lectura" placeholder="Ej: 1:A 2:B 3:D 4:C ... o simplemente letras separadas por espacio: A B D C A C A B"><?php echo htmlspecialchars(format_rapido($hojaBase['lectura'] ?? [])); ?></textarea>
                <div class="help-text">Puedes pegar las letras separadas por espacio (A B C D) o formato número:letra (1:A 2:B).</div>
            </div>

            <!-- Redacción -->
            <div class="field">
                <label>✍️ Parte 2: Redacción (25 reactivos)</label>
                <textarea name="rapido_redaccion" placeholder="Ej: 1:A 2:B ... o 10:A 15:D 17:D"><?php echo htmlspecialchars(format_rapido($hojaBase['redaccion'] ?? [])); ?></textarea>
            </div>

            <!-- Matemáticas -->
            <div class="field">
                <label>📐 Parte 3: Matemáticas (55 reactivos)</label>
                <textarea name="rapido_matematicas" placeholder="Ej: 1:A 2:B ... 46:12 47:3/4 ..."><?php echo htmlspecialchars(format_rapido($hojaBase['matematicas'] ?? [])); ?></textarea>
                <div class="help-text">1 al 45 son letras (A B C D). 46 al 55 pueden ser números o fracciones (ej: 46:12).</div>
            </div>

            <!-- Inglés -->
            <div class="field">
                <label>🇬🇧 Parte 4: Inglés (50 reactivos - Opcional)</label>
                <textarea name="rapido_ingles" placeholder="Ej: 1:A 2:B 3:C ..."><?php echo htmlspecialchars(format_rapido($hojaBase['ingles'] ?? [])); ?></textarea>
            </div>

            <button type="submit">💾 Guardar Hoja Base</button>
        </form>
    </div>
</div>
</body>
</html>
