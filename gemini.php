<?php
/**
 * ====================================================================
 * Cliente de Inteligencia Artificial Google Gemini Vision
 * (Sistema de Multi-Recorte Seccional OMR de Alta Resolución)
 * Instituto Lumière - Exámenes de Admisión PAA
 * ====================================================================
 */

require_once __DIR__ . '/config.php';

/**
 * 1. Detecta los 4 cuadros negros de calibración/alineación en las esquinas de la hoja.
 * 
 * @param string $imageBytes Contenido binario de la imagen
 * @param string $mimeType Tipo MIME
 * @return array|false Coordenadas de los 4 cuadros o false
 */
function gemini_detectar_cuadros_negros($imageBytes, $mimeType = 'image/jpeg') {
    if (empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'TU_GEMINI_API_KEY_AQUI') {
        bot_log("Error: GEMINI_API_KEY no configurada para detectar cuadros.");
        return false;
    }

    $prompt = <<<EOT
Eres un sistema de visión artificial experto en OMR.
En esta fotografía de la hoja de respuestas de examen, localiza con máxima precisión los 4 cuadros negros sólidos de calibración/alineación ubicados en las 4 esquinas de la hoja:
1. "top_left": Cuadro negro en la esquina superior izquierda.
2. "top_right": Cuadro negro en la esquina superior derecha.
3. "bottom_right": Cuadro negro en la esquina inferior derecha.
4. "bottom_left": Cuadro negro en la esquina inferior izquierda.

Devuelve ÚNICAMENTE un objeto JSON con las coordenadas [ymin, xmin, ymax, xmax] normalizadas en escala de 0 a 1000 para cada uno:
{
  "top_left": [ymin, xmin, ymax, xmax],
  "top_right": [ymin, xmin, ymax, xmax],
  "bottom_right": [ymin, xmin, ymax, xmax],
  "bottom_left": [ymin, xmin, ymax, xmax]
}
EOT;

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => base64_encode($imageBytes)]]
                ]
            ]
        ],
        'generationConfig' => [
            'response_mime_type' => 'application/json',
            'temperature' => 0.0
        ]
    ];

    $res = gemini_ejecutar_peticion($payload, 25);
    if ($res['exito'] && is_array($res['data'])) {
        $boxes = $res['data'];
        if (isset($boxes['top_left'], $boxes['top_right'], $boxes['bottom_right'], $boxes['bottom_left'])) {
            bot_log("Gemini ({$res['modelo']}) detectó los 4 cuadros negros de la hoja.");
            return $boxes;
        }
    }

    bot_log("Aviso: Gemini no detectó los 4 cuadros negros en esta imagen.");
    return false;
}

/**
 * 2. Pasa los cuadros negros detectados a OpenCV en Python (mediante align_opencv.py)
 * para realizar la transformación de perspectiva y acomodar la imagen plana.
 * 
 * @param string $imageBytes Contenido binario de la imagen
 * @param array $cuadrosNegros Coordenadas de los 4 cuadros
 * @return string Imagen rectificada en formato JPEG binario
 */
function opencv_alinear_hoja($imageBytes, $cuadrosNegros) {
    if (!$cuadrosNegros || !is_array($cuadrosNegros)) {
        return $imageBytes;
    }

    $tmpDir = sys_get_temp_dir();
    $prefix = 'omr_align_' . uniqid();
    $inputPath = $tmpDir . DIRECTORY_SEPARATOR . $prefix . '_in.jpg';
    $outputPath = $tmpDir . DIRECTORY_SEPARATOR . $prefix . '_out.jpg';
    $ptsPath = $tmpDir . DIRECTORY_SEPARATOR . $prefix . '_pts.json';

    file_put_contents($inputPath, $imageBytes);
    file_put_contents($ptsPath, json_encode($cuadrosNegros));

    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'align_opencv.py';
    $cmd = 'python ' . escapeshellarg($scriptPath) .
           ' --input ' . escapeshellarg($inputPath) .
           ' --output ' . escapeshellarg($outputPath) .
           ' --points-file ' . escapeshellarg($ptsPath) .
           ' --width 1000 --height 1350 2>&1';

    $salida = @shell_exec($cmd);
    bot_log("OpenCV warp output: " . substr((string)$salida, 0, 200));

    if (file_exists($outputPath) && filesize($outputPath) > 1000) {
        $alignedBytes = file_get_contents($outputPath);
        @unlink($inputPath);
        @unlink($outputPath);
        @unlink($ptsPath);
        return $alignedBytes;
    }

    @unlink($inputPath);
    @unlink($outputPath);
    @unlink($ptsPath);

    return $imageBytes;
}

/**
 * 3. Proceso de alineación si se requiere
 */
function gemini_procesar_hoja_con_alineacion($imageBytes, $mimeType = 'image/jpeg') {
    $cuadros = gemini_detectar_cuadros_negros($imageBytes, $mimeType);
    $imagenAlineada = $imageBytes;
    $fueAlineada = false;

    if ($cuadros !== false) {
        $resultadoAlineacion = opencv_alinear_hoja($imageBytes, $cuadros);
        if ($resultadoAlineacion !== $imageBytes && strlen($resultadoAlineacion) > 1000) {
            $imagenAlineada = $resultadoAlineacion;
            $fueAlineada = true;
            bot_log("Hoja acomodada exitosamente con OpenCV mediante los 4 cuadros.");
        }
    }

    $respuestas = gemini_analyze_sheet($imagenAlineada, 'image/jpeg');

    return [
        'respuestas' => $respuestas,
        'imagen_alineada' => $imagenAlineada,
        'fue_alineada' => $fueAlineada,
        'cuadros' => $cuadros
    ];
}

/**
 * 4. Función central para ejecutar peticiones a la API de Gemini con rotación automática de claves y modelos
 */
function gemini_ejecutar_peticion($payload, $timeout = 30) {
    $keys = defined('GEMINI_API_KEYS') && is_array(GEMINI_API_KEYS) && count(GEMINI_API_KEYS) > 0 
        ? GEMINI_API_KEYS 
        : [GEMINI_API_KEY];

    // Limpiar claves vacías o de placeholder
    $keys = array_values(array_filter($keys, function($k) {
        return !empty($k) && $k !== 'TU_GEMINI_API_KEY_AQUI';
    }));

    if (empty($keys)) {
        return ['exito' => false, 'error' => 'API Key de Gemini no configurada'];
    }

    $models = array_unique([
        'gemini-3.5-flash-lite',
        'gemini-3-flash-preview',
        'gemini-3.5-flash'
    ]);

    // Ordenar las claves con rotación Round-Robin para balancear la carga equitativamente
    static $rotIndex = 0;
    $totalKeys = count($keys);
    $startIdx = $rotIndex % $totalKeys;
    $rotIndex++;

    $orderedKeys = [];
    for ($i = 0; $i < $totalKeys; $i++) {
        $orderedKeys[] = $keys[($startIdx + $i) % $totalKeys];
    }

    $lastError = '';

    foreach ($orderedKeys as $apiKey) {
        $keySuffix = substr($apiKey, -6);

        // Probar solo el modelo principal y el secundario por clave para evitar retrasos
        $modelosProbar = array_slice($models, 0, 2);
        foreach ($modelosProbar as $model) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, min((int)$timeout, 8));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                $lastError = "cURL en {$model} (...{$keySuffix}): {$curlError}";
                bot_log($lastError);
                continue;
            }

            if ($httpCode === 429) {
                // Tasa o cuota excedida en esta clave, pasar de inmediato a la siguiente clave del pool
                $lastError = "HTTP 429 en {$model} con key ...{$keySuffix}. Conmutando a siguiente clave del pool...";
                bot_log($lastError);
                break; // Salta de inmediato a la siguiente API Key
            }

            if ($httpCode !== 200) {
                $lastError = "HTTP {$httpCode} en {$model} (...{$keySuffix}): " . substr((string)$response, 0, 150);
                bot_log($lastError);
                continue;
            }

            $resData = json_decode($response, true);
            $rawText = trim($resData['candidates'][0]['content']['parts'][0]['text'] ?? '');
            if (empty($rawText)) {
                $lastError = "Sin texto en respuesta de {$model}";
                bot_log($lastError);
                continue;
            }

            if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $rawText, $matches)) {
                $rawText = trim($matches[1]);
            }

            $decoded = json_decode($rawText, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return [
                    'exito' => true,
                    'modelo' => $model,
                    'key_utilizada' => '...' . $keySuffix,
                    'data' => $decoded
                ];
            } else {
                $lastError = "JSON inválido de {$model}: " . json_last_error_msg();
                bot_log($lastError);
            }
        }
    }

    return [
        'exito' => false,
        'error' => $lastError ?: 'No se obtuvo respuesta válida de Gemini con ninguna clave'
    ];
}

/**
 * 4.1 Ejecuta un conjunto de peticiones a la API de Gemini en PARALELO mediante curl_multi
 * Distribuye las peticiones automáticamente entre las 5 claves del pool
 *
 * @param array $tasks Matriz asociativa ['id' => $payload, ...]
 * @param int $timeout Tiempo límite por petición
 * @return array Matriz ['id' => ['exito' => bool, 'data' => array|null, 'error' => string], ...]
 */
function gemini_ejecutar_peticiones_paralelas($tasks, $timeout = 10) {
    if (empty($tasks)) return [];

    $keys = defined('GEMINI_API_KEYS') && is_array(GEMINI_API_KEYS) && count(GEMINI_API_KEYS) > 0 
        ? GEMINI_API_KEYS 
        : [GEMINI_API_KEY];

    $keys = array_values(array_filter($keys, function($k) {
        return !empty($k) && $k !== 'TU_GEMINI_API_KEY_AQUI';
    }));

    if (empty($keys)) {
        return [];
    }

    $model = 'gemini-3.5-flash-lite';

    $mh = curl_multi_init();
    $handles = [];
    $totalKeys = count($keys);

    static $keyRotator = 0;

    foreach ($tasks as $id => $payload) {
        $apiKey = $keys[$keyRotator % $totalKeys];
        $keyRotator++;

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        curl_multi_add_handle($mh, $ch);
        $handles[$id] = [
            'ch' => $ch,
            'key' => $apiKey,
            'payload' => $payload
        ];
    }

    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 0.5);
        }
    } while ($running > 0 && $status === CURLM_OK);

    $results = [];
    $retriesNeeded = [];

    foreach ($handles as $id => $info) {
        $ch = $info['ch'];
        $content = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $parsedData = null;
        $success = false;
        $errorMsg = '';

        if ($curlError) {
            $errorMsg = "cURL error: {$curlError}";
        } elseif ($httpCode !== 200) {
            $errorMsg = "HTTP {$httpCode}: " . substr((string)$content, 0, 100);
            if ($httpCode === 429) {
                bot_log("Petición paralela '{$id}' recibió 429. Programando reintento...");
            }
        } else {
            $resData = json_decode($content, true);
            $rawText = trim($resData['candidates'][0]['content']['parts'][0]['text'] ?? '');
            if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $rawText, $matches)) {
                $rawText = trim($matches[1]);
            }
            $decoded = json_decode($rawText, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $parsedData = $decoded;
                $success = true;
            } else {
                $errorMsg = "JSON inválido de {$model}";
            }
        }

        if (!$success) {
            $retriesNeeded[$id] = $info['payload'];
        } else {
            $results[$id] = [
                'exito' => true,
                'data' => $parsedData
            ];
        }
    }
    curl_multi_close($mh);

    // Reintentar secuencialmente con timeout corto cualquier sección que haya fallado o recibido 429
    if (!empty($retriesNeeded)) {
        bot_log("Reintentando " . count($retriesNeeded) . " tareas con failover de claves...");
        $retryTimeout = 6;
        foreach ($retriesNeeded as $id => $payload) {
            $res = gemini_ejecutar_peticion($payload, $retryTimeout);
            if ($res['exito'] && is_array($res['data'])) {
                $results[$id] = [
                    'exito' => true,
                    'data' => $res['data']
                ];
            } else {
                $results[$id] = [
                    'exito' => false,
                    'data' => null,
                    'error' => $res['error'] ?? 'Error en reintento'
                ];
            }
        }
    }

    return $results;
}

/**
 * 5. Preprocesamiento óptico de imagen para maximizar la precisión OMR:
 * Escala de grises, aumento de contraste para resaltar el grafito del lápiz y normalización.
 */
function gemini_mejorar_imagen_omr($imageBytes) {
    if (!function_exists('imagecreatefromstring')) {
        return $imageBytes;
    }
    $im = @imagecreatefromstring($imageBytes);
    if (!$im) return $imageBytes;

    imagefilter($im, IMG_FILTER_GRAYSCALE);
    imagefilter($im, IMG_FILTER_CONTRAST, -25);
    imagefilter($im, IMG_FILTER_BRIGHTNESS, 10);
    imagefilter($im, IMG_FILTER_SMOOTH, 1);

    ob_start();
    imagejpeg($im, null, 92);
    $enhanced = ob_get_clean();
    imagedestroy($im);

    return $enhanced ?: $imageBytes;
}

/**
 * 6. Genera los recortes de alta resolución de cada sección de la hoja de respuestas
 */
function gemini_preparar_recortes_completos($imageBytes) {
    if (!function_exists('imagecreatefromstring')) {
        return false;
    }

    $img = @imagecreatefromstring($imageBytes);
    if (!$img) return false;

    // 1. Corregir orientación EXIF
    if (function_exists('exif_read_data') && function_exists('imagerotate')) {
        $stream = fopen('php://memory', 'r+');
        if ($stream) {
            fwrite($stream, $imageBytes);
            rewind($stream);
            $exif = @exif_read_data($stream);
            fclose($stream);

            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3: $img = imagerotate($img, 180, 0); break;
                    case 6: $img = imagerotate($img, -90, 0); break;
                    case 8: $img = imagerotate($img, 90, 0); break;
                }
            }
        }
    }

    $w = imagesx($img);
    $h = imagesy($img);

    // 2. Si la imagen quedó en horizontal (w > h), rotar a vertical
    if ($w > $h && function_exists('imagerotate')) {
        $img = imagerotate($img, -90, 0);
        $w = imagesx($img);
        $h = imagesy($img);
    }

    $helperCrop = function($rect) use ($img) {
        $c = @imagecrop($img, $rect);
        if (!$c) return false;
        ob_start();
        imagejpeg($c, null, 95);
        $bytes = ob_get_clean();
        imagedestroy($c);
        return $bytes;
    };

    // Coordenadas proporcionales optimizadas con márgenes de seguridad para encuadres de celular
    $crops = [
        'lectura' => $helperCrop([
            'x' => (int)($w * 0.22),
            'y' => (int)($h * 0.10),
            'width' => (int)($w * 0.76),
            'height' => (int)($h * 0.18)
        ]),
        'redaccion' => $helperCrop([
            'x' => (int)($w * 0.22),
            'y' => (int)($h * 0.22),
            'width' => (int)($w * 0.76),
            'height' => (int)($h * 0.14)
        ]),
        'matematicas_mc' => $helperCrop([
            'x' => (int)($w * 0.22),
            'y' => (int)($h * 0.30),
            'width' => (int)($w * 0.50),
            'height' => (int)($h * 0.23)
        ]),
        'matematicas_46_47' => $helperCrop([
            'x' => (int)($w * 0.64),
            'y' => (int)($h * 0.30),
            'width' => (int)($w * 0.34),
            'height' => (int)($h * 0.23)
        ]),
        'matematicas_48_51' => $helperCrop([
            'x' => 0,
            'y' => (int)($h * 0.47),
            'width' => (int)($w * 0.52),
            'height' => (int)($h * 0.23)
        ]),
        'matematicas_52_55' => $helperCrop([
            'x' => (int)($w * 0.47),
            'y' => (int)($h * 0.47),
            'width' => (int)($w * 0.52),
            'height' => (int)($h * 0.23)
        ]),
        'datos_alumno' => $helperCrop([
            'x' => 0,
            'y' => 0,
            'width' => (int)($w * 0.75),
            'height' => (int)($h * 0.22)
        ])
    ];

    imagedestroy($img);

    if ($crops['lectura'] && $crops['redaccion'] && $crops['matematicas_mc']) {
        return $crops;
    }

    return false;
}

/**
 * 7. Analiza la Parte 1 (Lectura - 45 preguntas de opción múltiple A, B, C, D)
 */
function gemini_analizar_lectura($imageBytes) {
    $opt = gemini_mejorar_imagen_omr($imageBytes);
    $prompt = <<<EOT
Eres un lector óptico OMR de máxima precisión para hojas de respuestas de examen PAA.
Esta imagen corresponde a "Parte 1 Lectura" (Preguntas 1 a 45).
Estructura: 5 columnas verticales:
- Columna 1: reactivos 1 a 9
- Columna 2: reactivos 10 a 18
- Columna 3: reactivos 19 a 27
- Columna 4: reactivos 28 a 36
- Columna 5: reactivos 37 a 45

Reglas estrictas OMR:
1. Cada pregunta tiene exactamente 4 opciones: A, B, C, D. No existe la opción E.
2. Identifica para cada número la letra de la burbuja que tenga el relleno intencional con lápiz más oscuro y nítido.
3. Si una pregunta está vacía (sin contestar), no la incluyas en el JSON.
4. Descarta borrones o marcas tenues.

Devuelve ÚNICAMENTE un JSON con claves del "1" al "45":
{"1": "A", "2": "B"}
EOT;

    $payload = [
        'contents' => [['parts' => [
            ['text' => $prompt],
            ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($opt)]]
        ]]],
        'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
    ];

    $res = gemini_ejecutar_peticion($payload, 30);
    $limpias = [];
    if ($res['exito'] && is_array($res['data'])) {
        foreach ($res['data'] as $num => $val) {
            $v = strtoupper(trim((string)$val));
            if (in_array($v, ['A', 'B', 'C', 'D'])) {
                $limpias[(string)$num] = $v;
            }
        }
    }
    bot_log("Gemini detectó " . count($limpias) . " reactivos en Lectura");
    return $limpias;
}

/**
 * 8. Analiza la Parte 2 (Redacción - 25 preguntas de opción múltiple A, B, C, D)
 */
function gemini_analizar_redaccion($imageBytes) {
    $opt = gemini_mejorar_imagen_omr($imageBytes);
    $prompt = <<<EOT
Eres un lector óptico OMR de máxima precisión para hojas de respuestas de examen PAA.
Esta imagen corresponde a "Parte 2 Redacción" (Preguntas 1 a 25).
Estructura: 5 columnas verticales con 5 reactivos cada una:
- Columna 1: reactivos 1 a 5
- Columna 2: reactivos 6 a 10
- Columna 3: reactivos 11 a 15
- Columna 4: reactivos 16 a 20
- Columna 5: reactivos 21 a 25

Reglas estrictas OMR:
1. Cada pregunta tiene 4 opciones: A, B, C, D. No existe la opción E.
2. Identifica la letra de la burbuja con el relleno más oscuro y definitivo.
3. Si una pregunta está vacía, no la incluyas en el JSON.
4. Descarta marcas borradas.

Devuelve ÚNICAMENTE un JSON con claves del "1" al "25":
{"1": "C", "2": "D"}
EOT;

    $payload = [
        'contents' => [['parts' => [
            ['text' => $prompt],
            ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($opt)]]
        ]]],
        'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
    ];

    $res = gemini_ejecutar_peticion($payload, 30);
    $limpias = [];
    if ($res['exito'] && is_array($res['data'])) {
        foreach ($res['data'] as $num => $val) {
            $v = strtoupper(trim((string)$val));
            if (in_array($v, ['A', 'B', 'C', 'D'])) {
                $limpias[(string)$num] = $v;
            }
        }
    }
    bot_log("Gemini detectó " . count($limpias) . " reactivos en Redacción");
    return $limpias;
}

/**
 * 9. Analiza la Parte 3 (Matemáticas - 45 preguntas de opción múltiple 1 a 45)
 */
function gemini_analizar_matematicas_mc($imageBytes) {
    $opt = gemini_mejorar_imagen_omr($imageBytes);
    $prompt = <<<EOT
Eres un lector óptico OMR de máxima precisión para hojas de respuestas de examen PAA.
Esta imagen corresponde a "Parte 3 Matemáticas" (Preguntas 1 a 45 de opción múltiple).
Estructura: 3 columnas verticales de 15 preguntas cada una:
- Columna 1: reactivos 1 a 15
- Columna 2: reactivos 16 a 30
- Columna 3: reactivos 31 a 45

Reglas estrictas OMR:
1. Opciones posibles: A, B, C, D. No existe la opción E.
2. Identifica la letra de la burbuja más oscura en cada reactivo.
3. Si una pregunta está vacía, omítela.

Devuelve ÚNICAMENTE un JSON con claves del "1" al "45":
{"1": "A", "2": "B"}
EOT;

    $payload = [
        'contents' => [['parts' => [
            ['text' => $prompt],
            ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($opt)]]
        ]]],
        'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
    ];

    $res = gemini_ejecutar_peticion($payload, 30);
    $limpias = [];
    if ($res['exito'] && is_array($res['data'])) {
        foreach ($res['data'] as $num => $val) {
            $v = strtoupper(trim((string)$val));
            if (in_array($v, ['A', 'B', 'C', 'D'])) {
                $limpias[(string)$num] = $v;
            }
        }
    }
    bot_log("Gemini detectó " . count($limpias) . " reactivos en Matemáticas Opción Múltiple");
    return $limpias;
}

/**
 * 10. Analiza las Cuadrículas Numéricas Abiertas de Matemáticas (Reactivos 46 a 55)
 */
function gemini_analizar_matematicas_grid($bytes46_47, $bytes48_51, $bytes52_55 = null) {
    $gridAnswers = [];

    $promptRules = <<<EOT
Eres un lector óptico OMR de máxima precisión para cuadrículas numéricas del examen PAA (College Board).
Cada recuadro tiene 4 columnas verticales:
- Fila 1 (arriba de la línea divisoria horizontal): barra de fracción '/' (en columnas 2 y 3).
- Fila 2 (arriba de la línea divisoria horizontal): punto decimal '.' (en columnas 1, 2, 3).
- Fila 0 (debajo de la línea divisoria horizontal): dígito 0 (en columnas 2, 3, 4).
- Filas 1 al 9: dígitos del 1 al 9 en las 4 columnas.

Reglas matemáticas indispensables:
1. Una respuesta NUNCA puede comenzar con la barra '/'. Si una marca previa a un número está en la fila 2 de puntos, es un punto decimal '.' (ejemplo: '.5' o '.33').
2. Cuenta con precisión la fila del dígito rellenado (0 al 9) debajo de la línea divisoria.
3. Concatena los caracteres rellenados con lápiz oscuro de izquierda a derecha. Si una columna está vacía (sin rellenar), no aporta carácter.
EOT;

    // Helper para procesar respuesta de grid
    $sanitizarGrid = function($val) {
        $clean = preg_replace('/[^0-9\/\.\-]/', '', trim((string)$val));
        if ($clean === '/' || $clean === '.' || $clean === '') return '';
        // Corregir barra inicial si es decimal (.33 o .5)
        if (strpos($clean, '/') === 0 && strlen($clean) > 1 && is_numeric(substr($clean, 1))) {
            $clean = '.' . substr($clean, 1);
        }
        return $clean;
    };

    // 10.1 Cuadrículas 46 y 47
    if ($bytes46_47) {
        $opt = gemini_mejorar_imagen_omr($bytes46_47);
        $payload46 = [
            'contents' => [['parts' => [
                ['text' => $promptRules . "\nEn esta imagen están los ejercicios 46 y 47. Devuelve ÚNICAMENTE un JSON con formato: {\"46\": \"...\", \"47\": \"...\"}"],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($opt)]]
            ]]],
            'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
        ];

        $res = gemini_ejecutar_peticion($payload46, 25);
        if ($res['exito'] && is_array($res['data'])) {
            foreach ($res['data'] as $k => $v) {
                $c = $sanitizarGrid($v);
                if (!empty($c)) $gridAnswers[(string)$k] = $c;
            }
        }
    }

    // 10.2 Cuadrículas 48 a 51
    if ($bytes48_51) {
        $opt = gemini_mejorar_imagen_omr($bytes48_51);
        $payload48 = [
            'contents' => [['parts' => [
                ['text' => $promptRules . "\nEn esta imagen están los 4 ejercicios 48, 49, 50 y 51 de izquierda a derecha. Devuelve ÚNICAMENTE un JSON con claves '48', '49', '50', '51': {\"48\": \"...\", \"49\": \"...\", \"50\": \"...\", \"51\": \"...\"}"],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($opt)]]
            ]]],
            'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
        ];

        $res = gemini_ejecutar_peticion($payload48, 25);
        if ($res['exito'] && is_array($res['data'])) {
            foreach ($res['data'] as $k => $v) {
                $c = $sanitizarGrid($v);
                if (!empty($c)) $gridAnswers[(string)$k] = $c;
            }
        }
    }

    // 10.3 Cuadrículas 52 a 55
    if ($bytes52_55) {
        $opt = gemini_mejorar_imagen_omr($bytes52_55);
        $payload52 = [
            'contents' => [['parts' => [
                ['text' => $promptRules . "\nEn esta imagen están los 4 ejercicios 52, 53, 54 y 55 de izquierda a derecha. Devuelve ÚNICAMENTE un JSON con claves '52', '53', '54', '55': {\"52\": \"...\", \"53\": \"...\", \"54\": \"...\", \"55\": \"...\"}"],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($opt)]]
            ]]],
            'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
        ];

        $res = gemini_ejecutar_peticion($payload52, 25);
        if ($res['exito'] && is_array($res['data'])) {
            foreach ($res['data'] as $k => $v) {
                $c = $sanitizarGrid($v);
                if (!empty($c)) $gridAnswers[(string)$k] = $c;
            }
        }
    }

    bot_log("Gemini detectó " . count($gridAnswers) . " ejercicios en Matemáticas Abiertas (Grid 46-55)");
    return $gridAnswers;
}

/**
 * 11. Analiza una sección recortada individual (mantiene compatibilidad)
 */
function gemini_analizar_seccion_individual($imageBytes, $seccion) {
    if ($seccion === 'lectura') {
        return gemini_analizar_lectura($imageBytes);
    } elseif ($seccion === 'redaccion') {
        return gemini_analizar_redaccion($imageBytes);
    } else {
        return gemini_analizar_matematicas_mc($imageBytes);
    }
}

/**
 * 12. Función principal que analiza la hoja de respuestas:
 * Utiliza el pipeline seccional de alta resolución y respaldo en imagen completa si se requiere.
 * 
 * @param string $imageBytes Contenido binario de la imagen
 * @param string $mimeType Tipo MIME de la imagen
 * @return array Datos estructurados por sección
 */
/**
 * 12. Motor Principal Ultrarrápido de Alta Resolución en Paralelo
 * Analiza el nombre y todas las secciones de la hoja simultáneamente con curl_multi
 *
 * @param string $imageBytes Contenido binario de la imagen
 * @param string $mimeType Tipo MIME de la imagen
 * @return array ['exito' => bool, 'nombre' => string, 'carrera' => string, 'duracion' => float, 'respuestas' => array]
 */
function gemini_analizar_hoja_completa_paralela($imageBytes, $mimeType = 'image/jpeg') {
    $t0 = microtime(true);
    $recortes = gemini_preparar_recortes_completos($imageBytes);

    if ($recortes === false) {
        bot_log("Recortes no disponibles, analizando hoja completa en paralelo...");
        $promptFull = <<<EOT
Eres un calificador óptico OMR de máxima precisión para hojas de examen PAA.
Examina la imagen completa de la hoja y devuelve ÚNICAMENTE un JSON con respuestas detectadas:
{
  "lectura": { "1": "A", "2": "B" },
  "redaccion": { "1": "C", "2": "D" },
  "matematicas": { "1": "A", "45": "B", "46": "15" }
}
EOT;
        $promptNom = <<<EOT
Extrae el nombre completo en MAYÚSCULAS y carrera del estudiante en el encabezado superior:
{"nombre": "NOMBRE COMPLETO", "carrera": "CARRERA"}
EOT;
        $fbTasks = [
            'respuestas' => [
                'contents' => [['parts' => [
                    ['text' => $promptFull],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => base64_encode($imageBytes)]]
                ]]],
                'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
            ],
            'nombre' => [
                'contents' => [['parts' => [
                    ['text' => $promptNom],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => base64_encode($imageBytes)]]
                ]]],
                'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
            ]
        ];
        $fbRes = gemini_ejecutar_peticiones_paralelas($fbTasks, 10);
        $dResp = $fbRes['respuestas']['data'] ?? [];
        $dNom = $fbRes['nombre']['data'] ?? [];

        return [
            'exito' => !empty($dResp),
            'nombre' => trim((string)($dNom['nombre'] ?? '')),
            'carrera' => trim((string)($dNom['carrera'] ?? '')),
            'duracion' => round(microtime(true) - $t0, 2),
            'respuestas' => [
                'lectura' => $dResp['lectura'] ?? [],
                'redaccion' => $dResp['redaccion'] ?? [],
                'matematicas' => $dResp['matematicas'] ?? [],
                'ingles' => []
            ]
        ];
    }

    $tasks = [];

    // 1. Tarea Nombre del Alumno
    $promptNombre = <<<EOT
Eres un extractor de datos de examen de alta precisión para el Instituto Lumière.
Examina el encabezado superior de la hoja de respuestas:
1. Localiza el campo "Nombre:", "Nombre del Alumno:" o la línea manuscrita donde el estudiante escribió su nombre.
2. Extrae con exactitud el nombre y apellidos completos (ej: "JUAN PÉREZ LÓPEZ"). Conviértelo a MAYÚSCULAS limpias.
3. Si está vacío o no hay texto manuscrito legible, devuelve cadena vacía "".
4. Si existe el campo "Carrera:" o "Grupo:", extráelo también.

Devuelve ÚNICAMENTE un objeto JSON:
{
  "nombre": "NOMBRE COMPLETO EN MAYÚSCULAS",
  "carrera": "CARRERA O GRUPO"
}
EOT;
    $bytesNombre = !empty($recortes['datos_alumno']) ? $recortes['datos_alumno'] : $imageBytes;
    $tasks['nombre'] = [
        'contents' => [['parts' => [
            ['text' => $promptNombre],
            ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($bytesNombre)]]
        ]]],
        'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
    ];

    // 2. Tarea Lectura (1 a 45)
    $promptLec = <<<EOT
Eres un lector óptico OMR de máxima precisión para hojas de respuestas de examen PAA.
Esta imagen corresponde a "Parte 1 Lectura" (Preguntas 1 a 45).
Estructura: 5 columnas verticales:
- Columna 1: reactivos 1 a 9
- Columna 2: reactivos 10 a 18
- Columna 3: reactivos 19 a 27
- Columna 4: reactivos 28 a 36
- Columna 5: reactivos 37 a 45

Reglas estrictas OMR:
1. Cada pregunta tiene exactamente 4 opciones: A, B, C, D. No existe la opción E.
2. Identifica la burbuja que el alumno rellenó intencionalmente con lápiz (la más oscura).
3. Si una pregunta está vacía (sin contestar), NO la incluyas en el JSON.
4. Descarta marcas borradas o manchas tenues.

Devuelve ÚNICAMENTE un objeto JSON con este formato exacto:
{
  "1": "A",
  "2": "C"
}
EOT;
    $tasks['lectura'] = [
        'contents' => [['parts' => [
            ['text' => $promptLec],
            ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($recortes['lectura'])]]
        ]]],
        'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
    ];

    // 3. Tarea Redacción (1 a 25)
    $promptRed = <<<EOT
Eres un lector óptico OMR de máxima precisión para hojas de respuestas de examen PAA.
Esta imagen corresponde a "Parte 2 Redacción" (Preguntas 1 a 25).
Estructura: 5 columnas verticales:
- Columna 1: reactivos 1 a 5
- Columna 2: reactivos 6 a 10
- Columna 3: reactivos 11 a 15
- Columna 4: reactivos 16 a 20
- Columna 5: reactivos 21 a 25

Reglas estrictas OMR:
1. Cada pregunta tiene exactamente 4 opciones: A, B, C, D. No existe la opción E.
2. Identifica la burbuja rellenada intencionalmente con lápiz (la más oscura).
3. Si una pregunta está vacía (sin contestar), NO la incluyas en el JSON.
4. Descarta marcas borradas o manchas tenues.

Devuelve ÚNICAMENTE un objeto JSON:
{
  "1": "B",
  "2": "D"
}
EOT;
    $tasks['redaccion'] = [
        'contents' => [['parts' => [
            ['text' => $promptRed],
            ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($recortes['redaccion'])]]
        ]]],
        'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
    ];

    // 4. Tarea Matemáticas Opción Múltiple (1 a 45)
    $promptMat = <<<EOT
Eres un lector óptico OMR de máxima precisión para hojas de respuestas de examen PAA.
Esta imagen corresponde a "Parte 3 Matemáticas - Opción Múltiple" (Preguntas 1 a 45).
Estructura: 5 columnas verticales:
- Columna 1: reactivos 1 a 9
- Columna 2: reactivos 10 a 18
- Columna 3: reactivos 19 a 27
- Columna 4: reactivos 28 a 36
- Columna 5: reactivos 37 a 45

Reglas estrictas OMR:
1. Cada pregunta tiene exactamente 4 opciones: A, B, C, D. No existe la opción E.
2. Identifica la burbuja que el alumno rellenó con lápiz (la más oscura).
3. Si una pregunta está vacía (sin contestar), NO la incluyas en el JSON.
4. Descarta marcas borradas o manchas tenues.

Devuelve ÚNICAMENTE un objeto JSON:
{
  "1": "C",
  "2": "A"
}
EOT;
    $tasks['matematicas_mc'] = [
        'contents' => [['parts' => [
            ['text' => $promptMat],
            ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($recortes['matematicas_mc'])]]
        ]]],
        'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
    ];

    // 5. Cuadrículas numéricas Matemáticas (46 a 55)
    $promptRules = <<<EOT
Eres un lector óptico OMR de máxima precisión para hojas de respuestas PAA.
Analiza las cuadrículas numéricas abiertas de Matemáticas (Reactivos de respuesta abierta).
Cada ejercicio consta de casillas superiores con números escritos y una matriz de burbujas (dígitos 0-9, barra de fracción '/' y punto decimal '.').

INSTRUCCIONES CLAVE:
1. Determina el número o fracción que el alumno rellenó en las burbujas o escribió en las casillas superiores.
2. Las respuestas posibles pueden ser:
   - Enteros (ej: "15", "9", "22", "0")
   - Fracciones (ej: "1/2", "3/4", "7/5")
   - Decimales (ej: "0.5", "3.14", ".75")
3. Si un ejercicio está totalmente en blanco, NO lo incluyas en el JSON.
EOT;

    if (!empty($recortes['matematicas_46_47'])) {
        $tasks['grid_46_47'] = [
            'contents' => [['parts' => [
                ['text' => $promptRules . "\nEn esta imagen están los ejercicios 46 y 47. Devuelve ÚNICAMENTE un JSON con formato: {\"46\": \"...\", \"47\": \"...\"}"],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($recortes['matematicas_46_47'])]]
            ]]],
            'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
        ];
    }

    if (!empty($recortes['matematicas_48_51'])) {
        $tasks['grid_48_51'] = [
            'contents' => [['parts' => [
                ['text' => $promptRules . "\nEn esta imagen están los 4 ejercicios 48, 49, 50 y 51 de izquierda a derecha. Devuelve ÚNICAMENTE un JSON con claves '48', '49', '50', '51': {\"48\": \"...\", \"49\": \"...\", \"50\": \"...\", \"51\": \"...\"}"],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($recortes['matematicas_48_51'])]]
            ]]],
            'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
        ];
    }

    if (!empty($recortes['matematicas_52_55'])) {
        $tasks['grid_52_55'] = [
            'contents' => [['parts' => [
                ['text' => $promptRules . "\nEn esta imagen están los 4 ejercicios 52, 53, 54 y 55 de izquierda a derecha. Devuelve ÚNICAMENTE un JSON con claves '52', '53', '54', '55': {\"52\": \"...\", \"53\": \"...\", \"54\": \"...\", \"55\": \"...\"}"],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($recortes['matematicas_52_55'])]]
            ]]],
            'generationConfig' => ['response_mime_type' => 'application/json', 'temperature' => 0.0]
        ];
    }

    // Ejecutar TODAS las peticiones en paralelo (timeout estricto de 10s)
    $resultadosParalelos = gemini_ejecutar_peticiones_paralelas($tasks, 10);

    $nombreDetectado = '';
    $carreraDetectada = '';
    if (!empty($resultadosParalelos['nombre']['data'])) {
        $nombreDetectado = trim((string)($resultadosParalelos['nombre']['data']['nombre'] ?? ''));
        $carreraDetectada = trim((string)($resultadosParalelos['nombre']['data']['carrera'] ?? ''));
    }

    $sanitizarMC = function($rawArray, $maxReactivos) {
        $clean = [];
        if (!is_array($rawArray)) return $clean;
        for ($i = 1; $i <= $maxReactivos; $i++) {
            $num = (string)$i;
            if (isset($rawArray[$num])) {
                $letra = strtoupper(trim((string)$rawArray[$num]));
                if (in_array($letra, ['A', 'B', 'C', 'D'])) {
                    $clean[$num] = $letra;
                }
            }
        }
        return $clean;
    };

    $sanitizarGrid = function($val) {
        $clean = trim((string)$val);
        $clean = preg_replace('/[^0-9\/\.]/', '', $clean);
        return substr($clean, 0, 7);
    };

    $resLec = $sanitizarMC($resultadosParalelos['lectura']['data'] ?? [], 45);
    $resRed = $sanitizarMC($resultadosParalelos['redaccion']['data'] ?? [], 25);
    $resMatMC = $sanitizarMC($resultadosParalelos['matematicas_mc']['data'] ?? [], 45);

    $gridAnswers = [];
    foreach (['grid_46_47', 'grid_48_51', 'grid_52_55'] as $gridTask) {
        $gridData = $resultadosParalelos[$gridTask]['data'] ?? [];
        if (is_array($gridData)) {
            foreach ($gridData as $k => $v) {
                $cleanVal = $sanitizarGrid($v);
                if (!empty($cleanVal)) {
                    $gridAnswers[(string)$k] = $cleanVal;
                }
            }
        }
    }

    $resMat = array_merge($resMatMC, $gridAnswers);
    ksort($resMat, SORT_NUMERIC);

    $duracion = round(microtime(true) - $t0, 2);
    $totalDetectadas = count($resLec) + count($resRed) + count($resMat);
    bot_log("⚡ Pipeline Paralelo OMR completado en {$duracion}s: {$totalDetectadas} respuestas detectadas (Lec: " . count($resLec) . ", Red: " . count($resRed) . ", Mat: " . count($resMat) . "). Nombre: '{$nombreDetectado}'");

    // Si los recortes no capturaron suficientes respuestas (foto desalineada o con márgenes amplios), activar respaldo de hoja completa
    if ($totalDetectadas < 25) {
        bot_log("Aviso: Pocas respuestas detectadas por recorte ({$totalDetectadas}). Activando respaldo de imagen completa...");
        $respFull = gemini_analyze_sheet_full($imageBytes, $mimeType);
        $totFull = count($respFull['lectura'] ?? []) + count($respFull['redaccion'] ?? []) + count($respFull['matematicas'] ?? []);
        if ($totFull > $totalDetectadas) {
            bot_log("Respaldo de hoja completa exitoso con {$totFull} respuestas detectadas.");
            return [
                'exito' => true,
                'nombre' => $nombreDetectado,
                'carrera' => $carreraDetectada,
                'duracion' => round(microtime(true) - $t0, 2),
                'respuestas' => $respFull
            ];
        }
    }

    return [
        'exito' => true,
        'nombre' => $nombreDetectado,
        'carrera' => $carreraDetectada,
        'duracion' => $duracion,
        'respuestas' => [
            'lectura' => $resLec,
            'redaccion' => $resRed,
            'matematicas' => $resMat,
            'ingles' => []
        ]
    ];
}

function gemini_analyze_sheet($imageBytes, $mimeType = 'image/jpeg') {
    $res = gemini_analizar_hoja_completa_paralela($imageBytes, $mimeType);
    return $res['respuestas'] ?? [];
}

/**
 * 13. Respaldo en imagen completa (125 reactivos PAA)
 */
function gemini_analyze_sheet_full($imageBytes, $mimeType = 'image/jpeg') {
    $prompt = <<<EOT
Eres un calificador optico OMR de maxima precision para hojas de respuestas de examen PAA / College Board del Instituto Lumiere.
Examina la imagen completa de la hoja de respuestas.

Estructura oficial del examen (125 preguntas en total):
1. PARTE 1 / LECTURA (Preguntas 1 a 45): Opciones A, B, C, D.
2. PARTE 2 / REDACCION (Preguntas 1 a 25): Opciones A, B, C, D.
3. PARTE 3 / MATEMATICAS (55 preguntas en total: 1 a 55):
   - Preguntas 1 a 45: Opciones A, B, C, D.
   - Preguntas 46 a 55: Cuadriculas numericas abiertas (ej. "15", "1/2", "22", "0.33", "9", "2000", "1429", "12", "0.5", "0").

REGLAS ESTRICTAS DE DETECCION:
1. Identifica para cada pregunta la burbuja que tenga el relleno intencional con lapiz o tinta mas oscuro y definitivo.
2. Descarta marcas borradas o tenues.
3. Si una pregunta esta vacia (sin contestar), no la incluyas en el JSON.

Devuelve UNICAMENTE un objeto JSON valido con este formato exacto:
{
  "lectura": { "1": "A", "2": "B" },
  "redaccion": { "1": "C", "2": "D" },
  "matematicas": { "1": "A", "45": "B", "46": "15", "47": "1/2", "55": "0" }
}
EOT;

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => base64_encode($imageBytes)]]
                ]
            ]
        ],
        'generationConfig' => [
            'response_mime_type' => 'application/json',
            'temperature' => 0.0
        ]
    ];

    $res = gemini_ejecutar_peticion($payload, 10);
    if ($res['exito'] && is_array($res['data'])) {
        $data = $res['data'];
        $limpio = [
            'lectura' => [],
            'redaccion' => [],
            'matematicas' => [],
            'ingles' => []
        ];

        if (isset($data['lectura']) && is_array($data['lectura'])) {
            foreach ($data['lectura'] as $num => $val) {
                $valStr = strtoupper(trim((string)$val));
                if (in_array($valStr, ['A', 'B', 'C', 'D'])) {
                    $limpio['lectura'][(string)$num] = $valStr;
                }
            }
        }

        if (isset($data['redaccion']) && is_array($data['redaccion'])) {
            foreach ($data['redaccion'] as $num => $val) {
                $valStr = strtoupper(trim((string)$val));
                if (in_array($valStr, ['A', 'B', 'C', 'D'])) {
                    $limpio['redaccion'][(string)$num] = $valStr;
                }
            }
        }

        if (isset($data['matematicas']) && is_array($data['matematicas'])) {
            foreach ($data['matematicas'] as $num => $val) {
                $numInt = intval($num);
                $valStr = strtoupper(trim((string)$val));
                if ($numInt >= 46 && $numInt <= 55) {
                    $cleanGrid = preg_replace('/[^A-Z0-9\/\.\-]/', '', $valStr);
                    if (!empty($cleanGrid)) {
                        $limpio['matematicas'][(string)$num] = $cleanGrid;
                    }
                } else {
                    if (in_array($valStr, ['A', 'B', 'C', 'D'])) {
                        $limpio['matematicas'][(string)$num] = $valStr;
                    }
                }
            }
        }

        return $limpio;
    }

    return ['error' => $res['error'] ?? 'No se pudo procesar la hoja de examen con Gemini'];
}

/**
 * 14. Extrae el nombre y datos del alumno desde el recuadro superior izquierdo de la hoja
 * 
 * @param string $imageBytes Contenido binario de la imagen
 * @return array ['nombre' => string, 'carrera' => string]
 */
function gemini_extraer_datos_alumno($imageBytes) {
    // Si podemos recortar la esquina superior izquierda, se procesa mucho más rápido y nítido
    $recortes = gemini_preparar_recortes_completos($imageBytes);
    $bytesNombre = ($recortes && !empty($recortes['datos_alumno'])) ? $recortes['datos_alumno'] : $imageBytes;

    $prompt = <<<EOT
Eres un extractor de datos de examen de alta precisión para el Instituto Lumière.
Examina el encabezado superior de la hoja de respuestas:
1. Localiza el campo "Nombre:", "Nombre del Alumno:" o la línea manuscrita donde el estudiante escribió su nombre.
2. Extrae con exactitud el nombre y apellidos completos (ej: "JUAN PÉREZ LÓPEZ"). Conviértelo a MAYÚSCULAS limpias.
3. Si está vacío o no hay texto manuscrito legible, devuelve cadena vacía "".
4. Si existe el campo "Carrera:" o "Grupo:", extráelo también.

Devuelve ÚNICAMENTE un objeto JSON:
{
  "nombre": "NOMBRE COMPLETO EN MAYÚSCULAS",
  "carrera": "CARRERA O GRUPO"
}
EOT;

    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($bytesNombre)]]
            ]
        ]],
        'generationConfig' => [
            'response_mime_type' => 'application/json',
            'temperature' => 0.0
        ]
    ];

    $res = gemini_ejecutar_peticion($payload, 18);
    if ($res['exito'] && is_array($res['data'])) {
        return [
            'nombre' => trim((string)($res['data']['nombre'] ?? '')),
            'carrera' => trim((string)($res['data']['carrera'] ?? ''))
        ];
    }

    return ['nombre' => '', 'carrera' => ''];
}
