<?php
/**
 * ====================================================================
 * Configuración del Bot Calificador de Exámenes Instituto Lumière
 * ====================================================================
 */

// Desactivar visualización de errores directos en producción (se guardan en log)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// 1. Token oficial de tu bot de Telegram (@CalificadorLumierebot)
define('TELEGRAM_BOT_TOKEN', '8556272800:AAFUVsgKFajQWBvY_IKAjCcV0x2DH35l5Pk');
define('TELEGRAM_BOT_USERNAME', 'CalificadorLumierebot');

// 2. Tu API Key de Google Gemini
$geminiKeyFallback = base64_decode('QVEuQWI4Uk42SXNIc0V2ejFkVGNOUk1Deld3MndFREZvRTBiMmJQNlZSWEVyRlpyUVBrR2c=');
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: $geminiKeyFallback);

// Modelos de Gemini a utilizar con alta capacidad de visión OMR
define('GEMINI_PRIMARY_MODEL', 'gemini-3.5-flash-lite');
define('GEMINI_FALLBACK_MODEL', 'gemini-3-flash-preview');

// 3. URLs oficiales de Telegram API
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/');
define('TELEGRAM_FILE_URL', 'https://api.telegram.org/file/bot' . TELEGRAM_BOT_TOKEN . '/');

// 4. Clave de Administración Docente para calificador.lumieresiga.com
define('ADMIN_PASSWORD', 'lumiere2026');

// 5. Archivos locales y directorios compartidos entre subdominios
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
define('LOG_FILE', $sharedDir . '/bot.log');
define('STATES_FILE', $sharedDir . '/chat_states.json');

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
 * Obtiene el estado temporal de conversación de un chat
 */
function get_chat_state($chatId) {
    if (!file_exists(STATES_FILE)) return null;
    $states = json_decode(file_get_contents(STATES_FILE), true);
    if (!isset($states[$chatId])) return null;
    $s = $states[$chatId];
    // Expirar si pasaron más de 30 minutos (1800 s)
    if (time() - ($s['timestamp'] ?? 0) > 1800) {
        clear_chat_state($chatId);
        return null;
    }
    return $s;
}

/**
 * Establece el estado temporal de conversación de un chat
 */
function set_chat_state($chatId, $estado, $data = []) {
    $states = [];
    if (file_exists(STATES_FILE)) {
        $states = json_decode(file_get_contents(STATES_FILE), true) ?: [];
    }
    $states[$chatId] = array_merge($data, [
        'estado' => $estado,
        'timestamp' => time()
    ]);
    file_put_contents(STATES_FILE, json_encode($states, JSON_PRETTY_PRINT));
}

/**
 * Limpia el estado de conversación de un chat
 */
function clear_chat_state($chatId) {
    if (!file_exists(STATES_FILE)) return;
    $states = json_decode(file_get_contents(STATES_FILE), true) ?: [];
    if (isset($states[$chatId])) {
        unset($states[$chatId]);
        file_put_contents(STATES_FILE, json_encode($states, JSON_PRETTY_PRINT));
    }
}

/**
 * Función para registrar mensajes de depuración en bot.log
 */
function bot_log($message) {
    $time = date('Y-m-d H:i:s');
    if (is_array($message) || is_object($message)) {
        $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    file_put_contents(LOG_FILE, "[$time] $message\n", FILE_APPEND);
}

/**
 * Envía una petición cURL a la API de Telegram
 */
function telegram_request($method, $params = []) {
    $url = TELEGRAM_API_URL . $method;
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        bot_log("Error Telegram cURL ($method): " . $error);
        return false;
    }
    
    return json_decode($response, true);
}

/**
 * Envía un mensaje de texto formateado en HTML a un chat de Telegram
 */
function telegram_send_message($chatId, $text, $replyToMessageId = null, $replyMarkup = null) {
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if ($replyToMessageId) {
        $params['reply_to_message_id'] = $replyToMessageId;
    }
    
    if ($replyMarkup) {
        $params['reply_markup'] = $replyMarkup;
    }
    
    return telegram_request('sendMessage', $params);
}

/**
 * Envía una acción de chat (ej: "typing", "upload_photo")
 */
function telegram_send_action($chatId, $action = 'typing') {
    return telegram_request('sendChatAction', [
        'chat_id' => $chatId,
        'action' => $action
    ]);
}

/**
 * Envía una fotografía a un chat de Telegram
 */
function telegram_send_photo($chatId, $imageBytes, $caption = null, $replyToMessageId = null) {
    $tempFile = tempnam(sys_get_temp_dir(), 'tg_img_') . '.jpg';
    file_put_contents($tempFile, $imageBytes);
    
    $url = TELEGRAM_API_URL . 'sendPhoto';
    $postFields = [
        'chat_id' => $chatId,
        'photo' => new CURLFile($tempFile, 'image/jpeg', 'escaneo_anotado.jpg')
    ];
    if ($caption) {
        $postFields['caption'] = $caption;
        $postFields['parse_mode'] = 'HTML';
    }
    if ($replyToMessageId) {
        $postFields['reply_to_message_id'] = $replyToMessageId;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    @unlink($tempFile);

    if ($error) {
        bot_log("Error Telegram sendPhoto: " . $error);
        return false;
    }
    
    return json_decode($response, true);
}

