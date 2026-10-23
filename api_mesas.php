<?php
/**
 * ====================================================================
 * API de Gestión de Mesas de Calificación en Tiempo Real
 * Instituto Lumière - calificador.lumieresocial.com / resultados.lumieresocial.com
 * ====================================================================
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/gemini.php';
require_once __DIR__ . '/calificador.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function responder_mesas_json($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$accion = $_POST['accion'] ?? ($_GET['accion'] ?? '');

// --------------------------------------------------------------------
// 1. ENVIAR FOTO A MESA (Desde Celular o PC)
// --------------------------------------------------------------------
if ($accion === 'enviar_a_mesa' || $accion === 'subir_directo_pc') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['foto'])) {
        responder_mesas_json(['exito' => false, 'error' => 'No se recibió ninguna fotografía de examen.']);
    }

    $mesaNum = intval($_POST['mesa'] ?? 1);
    if ($mesaNum < 1 || $mesaNum > 5) $mesaNum = 1;

    $archivo = $_FILES['foto'];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        responder_mesas_json(['exito' => false, 'error' => 'Error al subir la imagen (Código ' . $archivo['error'] . ').']);
    }

    $imageBytes = file_get_contents($archivo['tmp_name']);
    if (!$imageBytes) {
        responder_mesas_json(['exito' => false, 'error' => 'No se pudieron leer los datos del archivo.']);
    }

    // 1. Analizar con Gemini OMR
    $mimeType = $archivo['type'] ?: 'image/jpeg';
    $t0 = microtime(true);
    $analisisCompleto = gemini_analizar_hoja_completa_paralela($imageBytes, $mimeType);
    $duracion = round(microtime(true) - $t0, 2);

    $nombreDetectado = trim($analisisCompleto['nombre'] ?? '');
    $carreraDetectada = trim($analisisCompleto['carrera'] ?? '');
    $respuestas = $analisisCompleto['respuestas'] ?? [];

    if (empty($respuestas) || (empty($respuestas['lectura']) && empty($respuestas['redaccion']) && empty($respuestas['matematicas']))) {
        responder_mesas_json([
            'exito' => false,
            'error' => 'No se pudo leer la hoja: ' . ($analisisCompleto['error'] ?? 'No se detectaron respuestas'),
            'consejo' => 'Verifica buena iluminación, encuadre completo de la hoja y que las marcas de lápiz sean nítidas.'
        ]);
    }

    // 2. Calificar en memoria de inmediato contra la Hoja Base Oficial
    $calificacion = calificar_examen($respuestas);

    // 3. Guardar imagen en uploads
    $filename = 'mesa' . $mesaNum . '_' . time() . '_' . substr(uniqid(), -4) . '.jpg';
    $destPath = UPLOADS_DIR . '/' . $filename;
    file_put_contents($destPath, $imageBytes);

    // 4. Armar paquete de la mesa
    $examId = 'exam_m' . $mesaNum . '_' . time() . '_' . substr(uniqid(), -4);
    $payload = [
        'id' => $examId,
        'mesa' => $mesaNum,
        'timestamp' => time(),
        'fecha' => date('d/m/Y H:i:s'),
        'duracion_ia' => $duracion,
        'estado' => 'pendiente_revision',
        'foto_archivo' => $filename,
        'foto_url' => 'uploads/' . $filename,
        'nombre_alumno' => $nombreDetectado ?: 'ALUMNO SIN NOMBRE',
        'carrera' => $carreraDetectada,
        'respuestas' => $respuestas,
        'respuestas_originales_ia' => $respuestas,
        'calificacion' => $calificacion
    ];

    // Guardar estado actual de la Mesa N
    $mesaFile = MESAS_DIR . '/mesa_' . $mesaNum . '.json';
    file_put_contents($mesaFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Guardar copia permanente con su ID para ligas directas (mesas.php?id=...)
    $examFile = MESAS_DIR . '/' . $examId . '.json';
    file_put_contents($examFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    bot_log("Mesa [{$mesaNum}] recibió examen de [{$nombreDetectado}]: {$calificacion['paa_total_escala']} pts ({$calificacion['total_aciertos']} aciertos) en {$duracion}s");

    responder_mesas_json([
        'exito' => true,
        'mesa' => $mesaNum,
        'id' => $examId,
        'nombre' => $payload['nombre_alumno'],
        'puntaje_paa_total' => $calificacion['paa_total_escala'],
        'total_aciertos' => $calificacion['total_aciertos'],
        'total_errores' => ($calificacion['total_preguntas_evaluadas'] - $calificacion['total_aciertos']),
        'duracion' => $duracion,
        'mensaje' => "Examen analizado y proyectado en Mesa {$mesaNum} (PC)."
    ]);
}

// --------------------------------------------------------------------
// 2. CONSULTAR ESTADO DE LA MESA (Polling desde PC)
// --------------------------------------------------------------------
if ($accion === 'estado_mesa') {
    $mesaNum = intval($_GET['mesa'] ?? 1);
    if ($mesaNum < 1 || $mesaNum > 5) $mesaNum = 1;
    $lastId = trim($_GET['last_id'] ?? '');

    $mesaFile = MESAS_DIR . '/mesa_' . $mesaNum . '.json';
    if (!file_exists($mesaFile)) {
        responder_mesas_json(['exito' => true, 'activo' => false, 'mensaje' => 'Esperando examen...']);
    }

    $raw = @file_get_contents($mesaFile);
    $data = json_decode($raw, true);

    if (empty($data) || empty($data['id'])) {
        responder_mesas_json(['exito' => true, 'activo' => false, 'mensaje' => 'Mesa libre.']);
    }

    if ($lastId && $data['id'] === $lastId) {
        responder_mesas_json(['exito' => true, 'activo' => true, 'sin_cambios' => true, 'id' => $data['id']]);
    }

    responder_mesas_json([
        'exito' => true,
        'activo' => true,
        'sin_cambios' => false,
        'examen' => $data
    ]);
}

// --------------------------------------------------------------------
// 3. CARGAR EXAMEN ESPECÍFICO POR ID (Para enlaces compartidos)
// --------------------------------------------------------------------
if ($accion === 'cargar_examen') {
    $id = trim($_GET['id'] ?? ($_POST['id'] ?? ''));
    $idLimpio = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
    if (empty($idLimpio)) {
        responder_mesas_json(['exito' => false, 'error' => 'ID de examen inválido.']);
    }

    $examFile = MESAS_DIR . '/' . $idLimpio . '.json';
    if (!file_exists($examFile)) {
        responder_mesas_json(['exito' => false, 'error' => 'No se encontró el examen especificado o ya fue archivado.']);
    }

    $data = json_decode(file_get_contents($examFile), true);
    responder_mesas_json(['exito' => true, 'examen' => $data]);
}

// --------------------------------------------------------------------
// 4. RECALCULAR CALIFICACIÓN AL CORREGIR RESPUESTAS EN VIVO (PC)
// --------------------------------------------------------------------
if ($accion === 'recalcular') {
    $respuestasRaw = $_POST['respuestas'] ?? '{}';
    $respuestas = is_array($respuestasRaw) ? $respuestasRaw : (json_decode($respuestasRaw, true) ?: []);

    $calificacion = calificar_examen($respuestas);
    responder_mesas_json([
        'exito' => true,
        'calificacion' => $calificacion
    ]);
}

// --------------------------------------------------------------------
// 5. SELLAR Y GUARDAR DEFINITIVO EN LA BASE DE DATOS (alumnos.json)
// --------------------------------------------------------------------
if ($accion === 'sellar_definitivo') {
    $mesaNum = intval($_POST['mesa'] ?? 1);
    $examId = trim($_POST['id'] ?? '');
    $nombreAlumno = trim($_POST['nombre_alumno'] ?? '');
    $carrera = trim($_POST['carrera'] ?? '');
    $fotoArchivo = trim($_POST['foto_archivo'] ?? '');
    $respuestasRaw = $_POST['respuestas'] ?? '{}';
    $respuestasOrigRaw = $_POST['respuestas_originales'] ?? '{}';

    if (empty($nombreAlumno)) {
        responder_mesas_json(['exito' => false, 'error' => 'Debes asignar un nombre al alumno antes de sellar.']);
    }

    $respuestas = is_array($respuestasRaw) ? $respuestasRaw : (json_decode($respuestasRaw, true) ?: []);
    $respuestasOrig = is_array($respuestasOrigRaw) ? $respuestasOrigRaw : (json_decode($respuestasOrigRaw, true) ?: []);

    // Evaluar modificaciones efectuadas por el profesor respecto a lo detectado por IA
    $modificaciones = [];
    foreach (['lectura', 'redaccion', 'matematicas'] as $sec) {
        $secActual = $respuestas[$sec] ?? [];
        $secOrig = $respuestasOrig[$sec] ?? [];
        foreach ($secActual as $num => $val) {
            $valOrig = $secOrig[$num] ?? '';
            if ($val !== $valOrig) {
                $modificaciones[$sec][(string)$num] = [
                    'leido_ia' => $valOrig ?: 'OMITIDA',
                    'corregido_docente' => $val
                ];
            }
        }
    }

    $resultado = calificar_examen($respuestas);
    if (!$resultado['exito']) {
        responder_mesas_json(['exito' => false, 'error' => $resultado['mensaje'] ?? 'Error al calificar con clave oficial.']);
    }

    $norm = normalizar_nombre($nombreAlumno);
    $alumnos = cargar_alumnos();
    $alumno = $alumnos[$norm] ?? null;

    $intentosPrevios = $alumno['intentos_usados'] ?? 0;
    $nuevoIntentoNum = $intentosPrevios + 1;

    $evaluacionFinal = [
        'id' => 'eval_' . uniqid(),
        'intento_numero' => $nuevoIntentoNum,
        'fecha' => date('d/m/Y H:i'),
        'carrera' => $carrera,
        'puntaje_paa_total' => $resultado['paa_total_escala'],
        'puntaje_paa_verbal' => $resultado['paa_verbal_escala'],
        'puntaje_paa_mate' => $resultado['paa_mate_escala'],
        'puntaje_escala_100' => $resultado['puntaje_escala_100'],
        'total_aciertos' => $resultado['total_aciertos'],
        'total_preguntas' => $resultado['total_preguntas_evaluadas'],
        'aciertos_verbal' => $resultado['aciertos_verbal'],
        'aciertos_mate' => $resultado['aciertos_mate'],
        'secciones' => $resultado['secciones'],
        'respuestas_alumno' => $respuestas,
        'modificaciones_alumno' => $modificaciones,
        'hubo_modificaciones' => count($modificaciones) > 0,
        'foto_archivo' => $fotoArchivo,
        'calificado_en_mesa' => $mesaNum
    ];

    $historial = $alumno['historial'] ?? [];
    if (!empty($alumno['ultima_evaluacion'])) {
        $historial[] = $alumno['ultima_evaluacion'];
    }

    $alumnos[$norm] = [
        'nombre' => $nombreAlumno,
        'nombre_normalizado' => $norm,
        'carrera' => $carrera,
        'intentos_usados' => $nuevoIntentoNum,
        'bloqueado' => true,
        'permiso_reintento' => false,
        'solicitud_reintento' => false,
        'ultima_evaluacion' => $evaluacionFinal,
        'historial' => $historial
    ];

    guardar_alumnos($alumnos);

    // Limpiar mesa
    if ($mesaNum >= 1 && $mesaNum <= 5) {
        $mesaFile = MESAS_DIR . '/mesa_' . $mesaNum . '.json';
        if (file_exists($mesaFile)) {
            @unlink($mesaFile);
        }
    }

    bot_log("Mesa [{$mesaNum}] SELLÓ DEFINITIVO examen de [{$nombreAlumno}]: {$resultado['paa_total_escala']} pts ({$resultado['total_aciertos']} aciertos)");

    responder_mesas_json([
        'exito' => true,
        'alumno_nombre' => $nombreAlumno,
        'puntaje_paa_total' => $resultado['paa_total_escala'],
        'total_aciertos' => $resultado['total_aciertos'],
        'mensaje' => "¡Examen de {$nombreAlumno} sellado y guardado en la base de datos!"
    ]);
}

// --------------------------------------------------------------------
// 6. LIMPIAR / DESCARTAR MESA SIN GUARDAR
// --------------------------------------------------------------------
if ($accion === 'limpiar_mesa') {
    $mesaNum = intval($_POST['mesa'] ?? ($_GET['mesa'] ?? 1));
    if ($mesaNum >= 1 && $mesaNum <= 5) {
        $mesaFile = MESAS_DIR . '/mesa_' . $mesaNum . '.json';
        if (file_exists($mesaFile)) {
            @unlink($mesaFile);
        }
    }
    responder_mesas_json(['exito' => true, 'mensaje' => "Mesa {$mesaNum} liberada."]);
}

responder_mesas_json(['exito' => false, 'error' => 'Acción no reconocida en API Mesas.']);
