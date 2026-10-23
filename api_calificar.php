<?php
/**
 * ====================================================================
 * API Backend Oficial - Calificador Web Instituto Lumière
 * calificador.lumieresiga.com
 * ====================================================================
 */

// Configuración de CORS y sesión para calificador.lumieresiga.com y resultados.lumieresiga.com
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    if (preg_match('/^https?:\/\/(.*?\.)?lumieresiga\.com(:[0-9]+)?$/i', $origin) || 
        preg_match('/^https?:\/\/localhost(:[0-9]+)?$/i', $origin) ||
        preg_match('/^https?:\/\/127\.0\.0\.1(:[0-9]+)?$/i', $origin)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Compatibilidad de sesión compartida entre subdominios
$host = strtolower($_SERVER['HTTP_HOST'] ?? '');
if (strpos($host, 'lumieresiga.com') !== false && !headers_sent()) {
    @ini_set('session.cookie_domain', '.lumieresiga.com');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/gemini.php';
require_once __DIR__ . '/calificador.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$accion = $_POST['accion'] ?? ($_GET['accion'] ?? '');

// Helper para responder JSON y salir
function responder_json($datos) {
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// Helper para verificar autenticación de profesor
function verificar_auth_admin() {
    $token = $_POST['admin_token'] ?? ($_SESSION['admin_token'] ?? '');
    if ($token !== ADMIN_PASSWORD) {
        responder_json(['exito' => false, 'error' => 'No autorizado. Clave administrativa incorrecta.']);
    }
}

// ====================================================================
// 1. ENDPOINTS PÚBLICOS PARA EL ALUMNO
// ====================================================================

// 1.1 Verificar estado y permisos del alumno por nombre
if ($accion === 'verificar_alumno') {
    $nombre = trim($_POST['nombre'] ?? '');
    if (empty($nombre)) {
        responder_json(['exito' => false, 'error' => 'Nombre no proporcionado.']);
    }

    $norm = normalizar_nombre($nombre);
    $alumnos = cargar_alumnos();
    $alumno = $alumnos[$norm] ?? null;

    if (!$alumno) {
        responder_json([
            'exito' => true,
            'existe' => false,
            'nombre_normalizado' => $norm,
            'puede_subir' => true,
            'intentos_usados' => 0
        ]);
    }

    $bloqueado = $alumno['bloqueado'] ?? true;
    $permisoReintento = $alumno['permiso_reintento'] ?? false;
    $puedeSubir = !$bloqueado || $permisoReintento;

    responder_json([
        'exito' => true,
        'existe' => true,
        'nombre_normalizado' => $norm,
        'nombre_oficial' => $alumno['nombre'],
        'puede_subir' => $puedeSubir,
        'bloqueado' => $bloqueado && !$permisoReintento,
        'intentos_usados' => $alumno['intentos_usados'] ?? 1,
        'solicitud_reintento' => $alumno['solicitud_reintento'] ?? false,
        'fecha_solicitud' => $alumno['fecha_solicitud'] ?? null,
        'ultima_evaluacion' => $alumno['ultima_evaluacion'] ?? null
    ]);
}

// 1.2 Solicitar reintento de examen al profesor
if ($accion === 'solicitar_reintento') {
    $nombre = trim($_POST['nombre'] ?? '');
    $motivo = trim($_POST['motivo'] ?? 'Foto borrosa o problema en captura');

    if (empty($nombre)) {
        responder_json(['exito' => false, 'error' => 'Nombre de alumno no proporcionado.']);
    }

    $norm = normalizar_nombre($nombre);
    $alumnos = cargar_alumnos();

    if (!isset($alumnos[$norm])) {
        // Registrar alumno básico si aún no existía en el registro
        $alumnos[$norm] = [
            'nombre' => $nombre,
            'nombre_normalizado' => $norm,
            'intentos_usados' => 0,
            'bloqueado' => true,
            'permiso_reintento' => false,
            'solicitud_reintento' => true,
            'fecha_solicitud' => date('d/m/Y H:i'),
            'motivo_solicitud' => $motivo,
            'ultima_evaluacion' => null,
            'historial' => []
        ];
    } else {
        $alumnos[$norm]['solicitud_reintento'] = true;
        $alumnos[$norm]['fecha_solicitud'] = date('d/m/Y H:i');
        $alumnos[$norm]['motivo_solicitud'] = $motivo;
    }

    guardar_alumnos($alumnos);
    bot_log("Alumno [{$nombre}] solicitó nuevo intento de examen. Motivo: {$motivo}");

    responder_json([
        'exito' => true,
        'mensaje' => 'Solicitud enviada a tu profesor. En cuanto la autorice en el panel, podrás volver a subir tu hoja.'
    ]);
}

// 1.3 Analizar fotografía del examen con Inteligencia Artificial
if ($accion === 'analizar_hoja_alumno') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['foto'])) {
        responder_json(['exito' => false, 'error' => 'No se recibió ninguna imagen para analizar.']);
    }

    $archivo = $_FILES['foto'];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        responder_json(['exito' => false, 'error' => 'Error al subir la imagen (Código ' . $archivo['error'] . ').']);
    }

    $imageBytes = file_get_contents($archivo['tmp_name']);
    if (!$imageBytes) {
        responder_json(['exito' => false, 'error' => 'No se pudieron leer los datos del archivo.']);
    }

    // 1. Analizar hoja completa y extraer nombre en PARALELO con los núcleos de Gemini
    $mimeType = $archivo['type'] ?: 'image/jpeg';
    $t0 = microtime(true);
    $analisisCompleto = gemini_analizar_hoja_completa_paralela($imageBytes, $mimeType);
    $duracion = round(microtime(true) - $t0, 2);

    $nombreDetectado = $analisisCompleto['nombre'] ?? '';
    $carreraDetectada = $analisisCompleto['carrera'] ?? '';
    $datosRespuestas = $analisisCompleto['respuestas'] ?? [];

    // Si ya proporcionó un nombre o se detectó uno, verificar que no esté bloqueado
    $nombreVerificar = trim($_POST['nombre_previo'] ?? $nombreDetectado);
    if (!empty($nombreVerificar)) {
        $norm = normalizar_nombre($nombreVerificar);
        $alumno = obtener_alumno($norm);
        if ($alumno && ($alumno['bloqueado'] ?? true) && !($alumno['permiso_reintento'] ?? false)) {
            responder_json([
                'exito' => false,
                'bloqueado' => true,
                'alumno_nombre' => $alumno['nombre'],
                'intentos_usados' => $alumno['intentos_usados'] ?? 1,
                'error' => "El alumno '{$alumno['nombre']}' ya ha utilizado su oportunidad de examen.",
                'solicitud_reintento' => $alumno['solicitud_reintento'] ?? false
            ]);
        }
    }

    if (empty($datosRespuestas) || (empty($datosRespuestas['lectura']) && empty($datosRespuestas['redaccion']) && empty($datosRespuestas['matematicas']))) {
        responder_json([
            'exito' => false,
            'error' => 'No se pudo leer la hoja: ' . ($analisisCompleto['error'] ?? 'No se detectaron respuestas'),
            'consejo' => 'Asegúrate de que la hoja esté bien iluminada y las burbujas estén bien marcadas.'
        ]);
    }

    // Guardar temporalmente la imagen para su posterior confirmación definitiva
    $tempId = 'temp_' . uniqid() . '.jpg';
    $tempPath = UPLOADS_DIR . '/' . $tempId;
    file_put_contents($tempPath, $imageBytes);

    $totalDetectadas = count($datosRespuestas['lectura'] ?? []) + 
                       count($datosRespuestas['redaccion'] ?? []) + 
                       count($datosRespuestas['matematicas'] ?? []);

    bot_log("⚡ Hoja analizada en {$duracion}s ({$totalDetectadas} respuestas detectadas, Nombre: '{$nombreDetectado}')");

    responder_json([
        'exito' => true,
        'temp_id' => $tempId,
        'duracion' => $duracion,
        'nombre_detectado' => $nombreDetectado,
        'carrera_detectada' => $carreraDetectada,
        'total_detectadas' => $totalDetectadas,
        'respuestas_detectadas' => $datosRespuestas
    ]);
}

// 1.4 Confirmar Calificación Definitiva (con opción de corrección única por el alumno)
if ($accion === 'confirmar_examen_alumno') {
    $tempId = trim($_POST['temp_id'] ?? '');
    $nombreAlumno = trim($_POST['nombre_alumno'] ?? '');
    $carrera = trim($_POST['carrera'] ?? '');
    $respuestasRaw = $_POST['respuestas'] ?? '{}';
    $respuestasOrigRaw = $_POST['respuestas_originales'] ?? '{}';

    if (empty($nombreAlumno)) {
        responder_json(['exito' => false, 'error' => 'Debes especificar tu nombre completo para sellar la entrega.']);
    }

    $norm = normalizar_nombre($nombreAlumno);
    $alumnos = cargar_alumnos();
    $alumno = $alumnos[$norm] ?? null;

    // Verificar candado de intentos (los administradores docentes autenticados pueden registrar sin bloqueo)
    $esAdmin = (!empty($_SESSION['admin_token']) && $_SESSION['admin_token'] === ADMIN_PASSWORD);
    if (!$esAdmin && $alumno && ($alumno['bloqueado'] ?? true) && !($alumno['permiso_reintento'] ?? false)) {
        responder_json([
            'exito' => false,
            'error' => 'Ya has entregado tu examen con anterioridad. Solicita autorización a tu profesor para volver a subirlo.'
        ]);
    }

    $respuestas = is_array($respuestasRaw) ? $respuestasRaw : (json_decode($respuestasRaw, true) ?: []);
    $respuestasOrig = is_array($respuestasOrigRaw) ? $respuestasOrigRaw : (json_decode($respuestasOrigRaw, true) ?: []);

    // Evaluar qué respuestas modificó el alumno (para auditoría docente)
    $modificaciones = [];
    foreach (['lectura', 'redaccion', 'matematicas'] as $sec) {
        $secActual = $respuestas[$sec] ?? [];
        $secOrig = $respuestasOrig[$sec] ?? [];
        foreach ($secActual as $num => $val) {
            $valOrig = $secOrig[$num] ?? '';
            if ($val !== $valOrig) {
                $modificaciones[$sec][(string)$num] = [
                    'leido_ia' => $valOrig ?: 'OMITIDA',
                    'corregido_alumno' => $val
                ];
            }
        }
    }

    // Calificar con el tabulador oficial PAA
    $resultado = calificar_examen($respuestas);
    if (!$resultado['exito']) {
        responder_json(['exito' => false, 'error' => $resultado['mensaje'] ?? 'Error al calificar contra la clave oficial.']);
    }

    // Mover archivo temporal a almacenamiento permanente del alumno
    $tempPath = UPLOADS_DIR . '/' . basename($tempId);
    $fotoFilename = '';
    $dataUriAnotada = null;

    if (!empty($tempId) && file_exists($tempPath)) {
        $fotoFilename = 'eval_' . $norm . '_' . time() . '.jpg';
        $fotoFilename = preg_replace('/\s+/', '_', $fotoFilename);
        $finalPath = UPLOADS_DIR . '/' . $fotoFilename;
        rename($tempPath, $finalPath);

        // Generar imagen con círculos anotados
        $imgBytes = file_get_contents($finalPath);
        $hojaBase = cargar_hoja_base();
        $dataUriAnotada = generar_hoja_con_circulos_anotados($imgBytes, $respuestas, $hojaBase, 'evaluacion');
    }

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
        'foto_archivo' => $fotoFilename
    ];

    // Actualizar registro en base de datos
    $historial = $alumno['historial'] ?? [];
    if (!empty($alumno['ultima_evaluacion'])) {
        $historial[] = $alumno['ultima_evaluacion'];
    }

    $alumnos[$norm] = [
        'nombre' => $nombreAlumno,
        'nombre_normalizado' => $norm,
        'carrera' => $carrera,
        'intentos_usados' => $nuevoIntentoNum,
        'bloqueado' => true, // Bloqueado tras completar
        'permiso_reintento' => false, // Consumir permiso
        'solicitud_reintento' => false,
        'fecha_solicitud' => null,
        'motivo_solicitud' => null,
        'ultima_evaluacion' => $evaluacionFinal,
        'historial' => $historial
    ];

    guardar_alumnos($alumnos);
    bot_log("Alumno [{$nombreAlumno}] completó examen definitivamente: {$resultado['paa_total_escala']} pts (Aciertos: {$resultado['total_aciertos']})");

    responder_json([
        'exito' => true,
        'alumno_nombre' => $nombreAlumno,
        'intento_numero' => $nuevoIntentoNum,
        'evaluacion' => $evaluacionFinal,
        'imagen_anotada' => $dataUriAnotada
    ]);
}

// ====================================================================
// 2. ENDPOINTS PARA EL PANEL DOCENTE / ADMINISTRADOR
// ====================================================================

// 2.1 Login de Profesor
if ($accion === 'admin_login') {
    $password = trim($_POST['password'] ?? '');
    if ($password === ADMIN_PASSWORD) {
        $_SESSION['admin_token'] = ADMIN_PASSWORD;
        responder_json(['exito' => true, 'mensaje' => 'Bienvenido al Panel de Control Docente']);
    } else {
        responder_json(['exito' => false, 'error' => 'Contraseña incorrecta.']);
    }
}

// 2.2 Obtener Dashboard Completo (Lista de alumnos, estadísticas y hoja base)
if ($accion === 'admin_get_dashboard') {
    verificar_auth_admin();

    $alumnos = cargar_alumnos();
    $hojaBase = cargar_hoja_base();

    $lista = array_values($alumnos);
    // Ordenar: primero los que tienen solicitud de reintento pendiente, luego por fecha más reciente
    usort($lista, function($a, $b) {
        $solA = !empty($a['solicitud_reintento']) ? 1 : 0;
        $solB = !empty($b['solicitud_reintento']) ? 1 : 0;
        if ($solA !== $solB) return $solB - $solA;

        $fA = $a['ultima_evaluacion']['fecha'] ?? '';
        $fB = $b['ultima_evaluacion']['fecha'] ?? '';
        return strcmp($fB, $fA);
    });

    // Calcular estadísticas
    $totalAlumnos = count($lista);
    $sumaPuntajes = 0;
    $conPuntaje = 0;
    $solicitudesPendientes = 0;
    $aprobados1000 = 0;

    foreach ($lista as $al) {
        if (!empty($al['solicitud_reintento'])) {
            $solicitudesPendientes++;
        }
        if (!empty($al['ultima_evaluacion']['puntaje_paa_total'])) {
            $pts = (int)$al['ultima_evaluacion']['puntaje_paa_total'];
            $sumaPuntajes += $pts;
            $conPuntaje++;
            if ($pts >= 1000) $aprobados1000++;
        }
    }

    $promedioPAA = $conPuntaje > 0 ? round($sumaPuntajes / $conPuntaje) : 0;

    responder_json([
        'exito' => true,
        'stats' => [
            'total_alumnos' => $totalAlumnos,
            'promedio_paa' => $promedioPAA,
            'aprobados_1000' => $aprobados1000,
            'solicitudes_reintento' => $solicitudesPendientes
        ],
        'alumnos' => $lista,
        'hoja_base' => $hojaBase
    ]);
}

// 2.3 Liberar Permiso de Reintento para un alumno
if ($accion === 'admin_liberar_reintento') {
    verificar_auth_admin();

    $nombre = trim($_POST['nombre'] ?? '');
    $norm = normalizar_nombre($nombre);
    $alumnos = cargar_alumnos();

    if (!isset($alumnos[$norm])) {
        responder_json(['exito' => false, 'error' => 'Alumno no encontrado en el sistema.']);
    }

    $alumnos[$norm]['bloqueado'] = false;
    $alumnos[$norm]['permiso_reintento'] = true;
    $alumnos[$norm]['solicitud_reintento'] = false;
    guardar_alumnos($alumnos);

    bot_log("Profesor liberó permiso de reintento para [{$nombre}].");

    responder_json([
        'exito' => true,
        'mensaje' => "Permiso liberado para {$nombre}. El alumno ya puede volver a subir su examen."
    ]);
}

// 2.4 Eliminar registro de alumno
if ($accion === 'admin_eliminar_alumno') {
    verificar_auth_admin();

    $nombre = trim($_POST['nombre'] ?? '');
    $norm = normalizar_nombre($nombre);
    $alumnos = cargar_alumnos();

    if (!isset($alumnos[$norm])) {
        responder_json(['exito' => false, 'error' => 'Alumno no encontrado.']);
    }

    // Borrar foto si existe
    $foto = $alumnos[$norm]['ultima_evaluacion']['foto_archivo'] ?? '';
    if (!empty($foto) && file_exists(UPLOADS_DIR . '/' . $foto)) {
        @unlink(UPLOADS_DIR . '/' . $foto);
    }

    unset($alumnos[$norm]);
    guardar_alumnos($alumnos);

    bot_log("Profesor eliminó al alumno [{$nombre}].");

    responder_json(['exito' => true, 'mensaje' => "Registro de {$nombre} eliminado correctamente."]);
}

// 2.5 Guardar cambios en la Hoja Base desde el Editor Visual
if ($accion === 'admin_guardar_hoja_base') {
    verificar_auth_admin();

    $baseRaw = $_POST['hoja_base'] ?? '{}';
    $nuevaBase = is_array($baseRaw) ? $baseRaw : json_decode($baseRaw, true);

    if (!is_array($nuevaBase)) {
        responder_json(['exito' => false, 'error' => 'Formato de Hoja Base inválido.']);
    }

    $nuevaBase['nombre'] = $nuevaBase['nombre'] ?? 'Hoja Base Lumière PAA';
    $nuevaBase['fecha'] = date('d/m/Y H:i');

    guardar_hoja_base($nuevaBase);
    bot_log("Profesor actualizó la Hoja Base oficial.");

    responder_json([
        'exito' => true,
        'mensaje' => 'Hoja Base actualizada exitosamente.',
        'hoja_base' => $nuevaBase
    ]);
}

// 2.6 Escanear Hoja Base oficial con IA
if ($accion === 'admin_escanear_hoja_base') {
    verificar_auth_admin();

    if (empty($_FILES['foto'])) {
        responder_json(['exito' => false, 'error' => 'No se recibió imagen de la Hoja Base.']);
    }

    $imageBytes = file_get_contents($_FILES['foto']['tmp_name']);
    $mime = $_FILES['foto']['type'] ?: 'image/jpeg';
    $detectado = gemini_analyze_sheet($imageBytes, $mime);

    if (isset($detectado['error'])) {
        responder_json(['exito' => false, 'error' => 'Error al escanear Hoja Base: ' . $detectado['error']]);
    }

    $hojaBase = [
        'nombre' => 'Hoja Base Lumière PAA',
        'fecha' => date('d/m/Y H:i'),
        'lectura' => $detectado['lectura'] ?? [],
        'redaccion' => $detectado['redaccion'] ?? [],
        'matematicas' => $detectado['matematicas'] ?? [],
        'ingles' => $detectado['ingles'] ?? []
    ];

    guardar_hoja_base($hojaBase);
    bot_log("Hoja Base escaneada y registrada mediante foto por el Administrador.");

    responder_json([
        'exito' => true,
        'mensaje' => 'Hoja Base escaneada y guardada exitosamente.',
        'hoja_base' => $hojaBase
    ]);
}

// Si ninguna acción coincidió
responder_json(['exito' => false, 'error' => 'Acción no válida o no especificada.']);
