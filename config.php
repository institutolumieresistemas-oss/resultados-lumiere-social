<?php
/**
 * ====================================================================
 * Configuración del Sistema Calificador Web - Instituto Lumière
 * ====================================================================
 */

// Desactivar visualización de errores directos en producción (se guardan en log)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 1. Tu API Key de Google Gemini
$geminiKeyFallback = base64_decode('QVEuQWI4Uk42SXNIc0V2ejFkVGNOUk1Deld3MndFREZvRTBiMmJQNlZSWEVyRlpyUVBrR2c=');
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: $geminiKeyFallback);

// Modelos de Gemini a utilizar con alta capacidad de visión OMR
define('GEMINI_PRIMARY_MODEL', 'gemini-3.5-flash-lite');
define('GEMINI_FALLBACK_MODEL', 'gemini-3-flash-preview');

// 2. Clave de Administración Docente para calificador.lumieresiga.com
define('ADMIN_PASSWORD', 'lumiere2026');

// 3. Archivos locales y directorios compartidos entre subdominios
// (calificador y resultados)
$sharedDir = __DIR__;
if (file_exists(__DIR__ . '/../calificador/claves.json') || file_exists(__DIR__ . '/../calificador/alumnos.json')) {
    $sharedDir = realpath(__DIR__ . '/../calificador') ?: (__DIR__ . '/../calificador');
} elseif (!file_exists($sharedDir . '/claves.json') && file_exists(__DIR__ . '/../public_html/claves.json')) {
    $sharedDir = realpath(__DIR__ . '/../public_html') ?: (__DIR__ . '/../public_html');
}

define('SHARED_STORAGE_DIR', $sharedDir);
define('CLAVES_FILE', $sharedDir . '/claves.json');
define('TABULADOR_FILE', $sharedDir . '/tabulador.json');
define('ALUMNOS_FILE', $sharedDir . '/alumnos.json');
define('UPLOADS_DIR', $sharedDir . '/uploads');
define('LOG_FILE', $sharedDir . '/calificador.log');

// Asegurar directorio de uploads
if (!is_dir(UPLOADS_DIR)) {
    @mkdir(UPLOADS_DIR, 0777, true);
}

/**
 * Normaliza nombres de alumnos para búsquedas y claves únicas consistentes
 */
function normalizar_nombre($nombre) {
    $n = trim((string)$nombre);
    $n = mb_strtoupper($n, 'UTF-8');
    // Reemplazar acentos
    $n = str_replace(['Á','É','Í','Ó','Ú','Ü','Ñ'], ['A','E','I','O','U','U','N'], $n);
    // Eliminar espacios dobles y caracteres raros
    $n = preg_replace('/[^A-Z0-9\s]/', ' ', $n);
    $n = preg_replace('/\s+/', ' ', $n);
    return trim($n);
}

/**
 * Carga la base de datos de alumnos desde alumnos.json
 */
function cargar_alumnos() {
    if (!file_exists(ALUMNOS_FILE)) {
        return [];
    }
    $contenido = file_get_contents(ALUMNOS_FILE);
    $data = json_decode($contenido, true);
    return is_array($data) ? $data : [];
}

/**
 * Guarda la base de datos de alumnos en alumnos.json de forma segura
 */
function guardar_alumnos($alumnos) {
    return file_put_contents(ALUMNOS_FILE, json_encode($alumnos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Obtiene el registro de un alumno por su nombre normalizado
 */
function obtener_alumno($nombre) {
    $norm = normalizar_nombre($nombre);
    if (empty($norm)) return null;
    $alumnos = cargar_alumnos();
    return $alumnos[$norm] ?? null;
}

/**
 * Función para registrar mensajes de depuración en calificador.log
 */
function app_log($message) {
    $time = date('Y-m-d H:i:s');
    if (is_array($message) || is_object($message)) {
        $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    file_put_contents(LOG_FILE, "[$time] $message\n", FILE_APPEND);
}

// Alias para compatibilidad con código existente
function bot_log($message) {
    app_log($message);
}
