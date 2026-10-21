<?php
/**
 * ====================================================================
 * Cliente de Inteligencia Artificial Google Gemini Vision
 * (Con Sistema de Multi-Recorte de Alta Resolución para Secciones OMR)
 * ====================================================================
 */

require_once __DIR__ . '/config.php';

/**
 * 1. Detecta los 4 cuadros negros de referencia en las esquinas de la hoja con Gemini AI.
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

    $models = array_unique([
        GEMINI_PRIMARY_MODEL,
        GEMINI_FALLBACK_MODEL,
        'gemini-3.5-flash',
        'gemini-3-flash-preview',
        'gemini-3.5-flash-lite',
        'gemini-3.6-flash'
    ]);

    foreach ($models as $model) {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GEMINI_API_KEY;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            continue;
        }

        $resData = json_decode($response, true);
        $rawText = trim($resData['candidates'][0]['content']['parts'][0]['text'] ?? '');
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $rawText, $matches)) {
            $rawText = trim($matches[1]);
        }

        $detectedBoxes = json_decode($rawText, true);
        if (is_array($detectedBoxes) && isset($detectedBoxes['top_left'], $detectedBoxes['top_right'], $detectedBoxes['bottom_right'], $detectedBoxes['bottom_left'])) {
            bot_log("Gemini ({$model}) detectó los 4 cuadros negros de la hoja.");
            return $detectedBoxes;
        }
    }

    bot_log("Aviso: Gemini no detectó los 4 cuadros negros en esta imagen.");
    return false;
}

/**
 * 2. Pasa los cuadros negros detectados a OpenCV en PHP (mediante align_opencv.py)
 * para realizar la transformación de perspectiva y acomodar la imagen.
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
 * 3. Proceso completo solicitado por el usuario:
 * - Detectar los 4 cuadros negros con IA.
 * - Pasar a OpenCV en PHP para acomodar la imagen con esos cuadros.
 * - Enviar la imagen acomodada a la IA para calificar.
 * 
 * @param string $imageBytes
 * @param string $mimeType
 * @return array
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

    // Analizar con Gemini la hoja (ya rectificada y acomodada si tuvo éxito)
    $respuestas = gemini_analyze_sheet($imagenAlineada, 'image/jpeg');

    return [
        'respuestas' => $respuestas,
        'imagen_alineada' => $imagenAlineada,
        'fue_alineada' => $fueAlineada,
        'cuadros' => $cuadros
    ];
}

/**
 * Llama a la API de Google Gemini para analizar la hoja de respuestas
 * Utiliza división inteligente por secciones para maximizar la resolución de burbujas.
 * 
 * @param string $imageBytes Contenido binario de la imagen
 * @param string $mimeType Tipo MIME de la imagen (image/jpeg, image/png)
 * @return array|false Datos estructurados en array o false en error
 */
/**
 * Función central para ejecutar peticiones a la API de Gemini con rotación automática de modelos
 */
function gemini_ejecutar_peticion($payload, $timeout = 30) {
    $models = array_unique([
        GEMINI_PRIMARY_MODEL,
        'gemini-3.8-flash',
        'gemini-3.1-pro-preview',
        'gemini-pro-latest',
        GEMINI_FALLBACK_MODEL,
        'gemini-3.5-flash-lite',
        'gemini-flash-latest',
        'gemini-3-flash-preview',
        'gemini-3.5-flash'
    ]);

    $lastError = '';

    foreach ($models as $model) {
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . GEMINI_API_KEY;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $lastError = "cURL en {$model}: {$curlError}";
            bot_log($lastError);
            continue;
        }

        if ($httpCode !== 200) {
            $lastError = "HTTP {$httpCode} en {$model}: " . substr((string)$response, 0, 150);
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
                'data' => $decoded
            ];
        } else {
            $lastError = "JSON inválido de {$model}: " . json_last_error_msg();
            bot_log($lastError);
        }
    }

    return [
        'exito' => false,
        'error' => $lastError ?: 'No se obtuvo respuesta válida de Gemini'
    ];
}

/**
 * Preprocesamiento óptico de imagen para maximizar la precisión OMR:
 * Convierte a escala de grises para eliminar sombras de luz cálida, incrementa
 * drásticamente el contraste para resaltar el grafito del lápiz y blanquea el fondo.
 */
function gemini_mejorar_imagen_omr($imageBytes) {
    if (!function_exists('imagecreatefromstring')) {
        return $imageBytes;
    }
    $im = @imagecreatefromstring($imageBytes);
    if (!$im) return $imageBytes;

    // 1. Escala de grises: elimina ruido de temperatura de color y sombras de teléfono
    imagefilter($im, IMG_FILTER_GRAYSCALE);

    // 2. Alto contraste dinámico: hace que el lápiz resalte oscuro profundo sobre el papel
    imagefilter($im, IMG_FILTER_CONTRAST, -25);

    // 3. Normalizar brillo del papel para llevarlo a blanco puro
    imagefilter($im, IMG_FILTER_BRIGHTNESS, 10);

    // 4. Suavizado sutil para limpiar artefactos de compresión JPG
    imagefilter($im, IMG_FILTER_SMOOTH, 1);

    ob_start();
    imagejpeg($im, null, 92);
    $enhanced = ob_get_clean();
    imagedestroy($im);

    return $enhanced ?: $imageBytes;
}

/**
 * Analiza una sección recortada individual con instrucciones precisas para OMR PAA
 * (Garantiza que no haya mezcla entre secciones ni confusión de opciones)
 */
function gemini_analizar_seccion_individual($imageBytes, $seccion) {
    // Aplicar preprocesamiento visual de alto contraste antes de enviar a Gemini
    $imageBytesOptimizado = gemini_mejorar_imagen_omr($imageBytes);

    if ($seccion === 'lectura') {
        $prompt = <<<EOT
Eres un lector óptico OMR de máxima precisión para hojas de respuestas de examen de admisión PAA (College Board).
Esta imagen corresponde ÚNICAMENTE a la "Parte 1 Lectura" (Preguntas 1 a 45).
Estructura: 5 columnas verticales con 9 preguntas cada una:
- Columna 1: preguntas 1 a 9
- Columna 2: preguntas 10 a 18
- Columna 3: preguntas 19 a 27
- Columna 4: preguntas 28 a 36
- Columna 5: preguntas 37 a 45

REGLAS ESTRICTAS DE DETECCIÓN OMR:
1. Cada pregunta tiene exactamente 4 opciones de izquierda a derecha: A, B, C, D (1ª=A, 2ª=B, 3ª=C, 4ª=D).
2. NO EXISTE LA OPCIÓN E. No incluyas la letra E bajo ninguna circunstancia.
3. Para cada número del 1 al 45, determina la letra de la burbuja que tenga el relleno intencional con lápiz más oscuro.
4. Descarta borrones tenues o marcas borradas; selecciona únicamente la opción marcada definitivamente.
5. Si una pregunta está vacía (sin marcar), no la incluyas en el JSON.

Devuelve ÚNICAMENTE un objeto JSON:
{"1": "A", "2": "B"}
EOT;
    } elseif ($seccion === 'redaccion') {
        $prompt = <<<EOT
Eres un lector óptico OMR de máxima precisión para hojas de respuestas de examen de admisión PAA (College Board).
Esta imagen corresponde ÚNICAMENTE a la "Parte 2 Redacción" (Preguntas 1 a 25).
Estructura: 5 columnas verticales con 5 preguntas cada una:
- Columna 1: preguntas 1 a 5
- Columna 2: preguntas 6 a 10
- Columna 3: preguntas 11 a 15
- Columna 4: preguntas 16 a 20
- Columna 5: preguntas 21 a 25

REGLAS ESTRICTAS DE DETECCIÓN OMR:
1. Cada pregunta tiene exactamente 4 opciones de izquierda a derecha: A, B, C, D (1ª=A, 2ª=B, 3ª=C, 4ª=D).
2. NO EXISTE LA OPCIÓN E. No incluyas la letra E bajo ninguna circunstancia.
3. Para cada número del 1 al 25, determina la letra de la burbuja que tenga el relleno intencional con lápiz más oscuro.
4. Descarta borrones tenues o marcas borradas; selecciona únicamente la opción marcada definitivamente.
5. Si una pregunta está vacía (sin marcar), no la incluyas en el JSON.

Devuelve ÚNICAMENTE un objeto JSON:
{"1": "C", "2": "D"}
EOT;
    } else { // matematicas
        $prompt = <<<EOT
Eres un lector óptico OMR de máxima precisión para hojas de respuestas de examen de admisión PAA (College Board).
Esta imagen corresponde a la "Parte 3 Matemáticas" (Preguntas 1 a 45 de opción múltiple).
Estructura: 3 columnas verticales con 15 preguntas cada una:
- Columna 1: preguntas 1 a 15
- Columna 2: preguntas 16 a 30
- Columna 3: preguntas 31 a 45
(Ignora por completo las cuadrículas numéricas tipo Grid 46 a 55).

REGLAS ESTRICTAS DE DETECCIÓN OMR:
1. Cada pregunta tiene exactamente 4 opciones de izquierda a derecha: A, B, C, D (1ª=A, 2ª=B, 3ª=C, 4ª=D).
2. NO EXISTE LA OPCIÓN E. No incluyas la letra E bajo ninguna circunstancia.
3. Para cada número del 1 al 45, determina la letra de la burbuja que tenga el relleno intencional con lápiz más oscuro.
4. Descarta borrones tenues o marcas borradas; selecciona únicamente la opción marcada definitivamente.
5. Si una pregunta está vacía (sin marcar), no la incluyas en el JSON.

Devuelve ÚNICAMENTE un objeto JSON:
{"1": "A", "2": "B"}
EOT;
    }

    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($imageBytesOptimizado)]]
            ]
        ]],
        'generationConfig' => [
            'response_mime_type' => 'application/json',
            'temperature' => 0.0
        ]
    ];

    $res = gemini_ejecutar_peticion($payload, 25);
    if ($res['exito'] && is_array($res['data'])) {
        // Filtrar y validar opciones estrictas A, B, C, D
        $limpias = [];
        foreach ($res['data'] as $num => $val) {
            $valStr = strtoupper(trim((string)$val));
            if (in_array($valStr, ['A', 'B', 'C', 'D'])) {
                $limpias[(string)$num] = $valStr;
            }
        }
        bot_log("Gemini ({$res['modelo']}) detectó " . count($limpias) . " reactivos en {$seccion}");
        return $limpias;
    }

    return [];
}

/**
 * Llama a la API de Google Gemini para analizar la hoja de respuestas
 * Utiliza división inteligente por secciones para maximizar la resolución de burbujas.
 * 
 * @param string $imageBytes Contenido binario de la imagen
 * @param string $mimeType Tipo MIME de la imagen (image/jpeg, image/png)
 * @return array|false Datos estructurados en array o false en error
 */
function gemini_analyze_sheet($imageBytes, $mimeType = 'image/jpeg') {
    if (empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'TU_GEMINI_API_KEY_AQUI') {
        bot_log("Error: La constante GEMINI_API_KEY no esta configurada.");
        return ['error' => 'API Key de Gemini no configurada'];
    }

    // 1. Analisis directo de imagen completa (100% de la hoja en 1 peticion de 4 segundos)
    $resultadoFull = gemini_analyze_sheet_full($imageBytes, $mimeType);
    if (!isset($resultadoFull['error'])) {
        $totalFull = count($resultadoFull['lectura']) + count($resultadoFull['redaccion']) + count($resultadoFull['matematicas']);
        if ($totalFull >= 20) {
            return $resultadoFull;
        }
    }

    // 2. Si la imagen completa arrojo muy poco, intentar multi-recorte como respaldo
    $recortes = gemini_preparar_recortes_secciones($imageBytes);
    if ($recortes !== false && count($recortes) === 3) {
        $resLec = gemini_analizar_seccion_individual($recortes[0], 'lectura');
        $resRed = gemini_analizar_seccion_individual($recortes[1], 'redaccion');
        $resMat = gemini_analizar_seccion_individual($recortes[2], 'matematicas');

        $total = count($resLec) + count($resRed) + count($resMat);
        if ($total >= 20) {
            return [
                'lectura' => $resLec,
                'redaccion' => $resRed,
                'matematicas' => $resMat,
                'ingles' => []
            ];
        }
    }

    return $resultadoFull;
}

/**
 * Analiza la hoja directamente en imagen completa (125 reactivos PAA con Grid Numerico 46 a 55)
 */
function gemini_analyze_sheet_full($imageBytes, $mimeType = 'image/jpeg') {
    $prompt = <<<EOT
Eres un calificador optico OMR de maxima precision para hojas de respuestas de examen PAA / College Board del Instituto Lumiere.
Examina la imagen completa de la hoja de respuestas.

Estructura oficial del examen (125 preguntas en total):
1. PARTE 1 / LECTURA (Preguntas 1 a 45):
   - 5 columnas verticales de 9 preguntas cada una (1-9, 10-18, 19-27, 28-36, 37-45).
   - Opciones: A, B, C, D (4 burbujas por fila).

2. PARTE 2 / REDACCION (Preguntas 1 a 25):
   - 5 columnas verticales de 5 preguntas cada una (1-5, 6-10, 11-15, 16-20, 21-25).
   - Opciones: A, B, C, D (4 burbujas por fila).

3. PARTE 3 / MATEMATICAS (55 preguntas en total: 1 a 55):
   - Preguntas 1 a 45 (Seleccion multiple):
     * 3 columnas verticales de 15 preguntas cada una (1-15, 16-30, 31-45).
     * Opciones: A, B, C, D.
   - Preguntas 46 a 55 (Cuadriculas de Grid Numerico):
     * Son 10 ejercicios abiertos donde los alumnos rellenan numeros del 0 al 9, puntos decimales (.) o fracciones (/).
     * Ejemplos de respuestas: "1/2", "3.5", "18", "4/5", "25", "0.75", etc.
     * Si rellenaron letras en lugar de numeros, anota la letra; si rellenaron numeros, anota el numero o fraccion resultante.

REGLAS ESTRICTAS DE DETECCION:
1. Identifica para cada pregunta la burbuja que tenga el relleno intencional con lapiz o tinta mas oscuro y definitivo.
2. Descarta marcas borradas o tenues.
3. Si una pregunta esta vacia (sin contestar), no la incluyas en el JSON.

Devuelve UNICAMENTE un objeto JSON valido con este formato exacto:
{
  "lectura": { "1": "A", "2": "B" },
  "redaccion": { "1": "C", "2": "D" },
  "matematicas": {
    "1": "A",
    "45": "B",
    "46": "18",
    "47": "3.5",
    "55": "1/2"
  }
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

    $res = gemini_ejecutar_peticion($payload, 35);
    if ($res['exito'] && is_array($res['data'])) {
        $data = $res['data'];
        $limpio = [
            'lectura' => [],
            'redaccion' => [],
            'matematicas' => [],
            'ingles' => []
        ];

        // 1. Lectura: 1 a 45
        if (isset($data['lectura']) && is_array($data['lectura'])) {
            foreach ($data['lectura'] as $num => $val) {
                $valStr = strtoupper(trim((string)$val));
                if (in_array($valStr, ['A', 'B', 'C', 'D'])) {
                    $limpio['lectura'][(string)$num] = $valStr;
                }
            }
        }

        // 2. Redaccion: 1 a 25
        if (isset($data['redaccion']) && is_array($data['redaccion'])) {
            foreach ($data['redaccion'] as $num => $val) {
                $valStr = strtoupper(trim((string)$val));
                if (in_array($valStr, ['A', 'B', 'C', 'D'])) {
                    $limpio['redaccion'][(string)$num] = $valStr;
                }
            }
        }

        // 3. Matematicas: 1 a 45 (ABCD) y 46 a 55 (Grid Numerico)
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

        $totalResp = count($limpio['lectura']) + count($limpio['redaccion']) + count($limpio['matematicas']);
        bot_log("Gemini ({$res['modelo']}) analizo imagen completa: {$totalResp} reactivos detectados (incluyendo Grid).");
        return $limpio;
    }

    return ['error' => $res['error'] ?? 'No se pudo procesar la hoja de examen con Gemini'];
}

/**
 * Prepara los recortes($imageBytes) {
    if (!function_exists('imagecreatefromstring')) {
        return false;
    }

    $img = @imagecreatefromstring($imageBytes);
    if (!$img) return false;

    // 1. Corregir orientación EXIF (típica en fotos de smartphones)
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

    // 2. Si la imagen quedó en horizontal (w > h), rotar 90° a vertical (la hoja de examen es vertical)
    if ($w > $h && function_exists('imagerotate')) {
        $img = imagerotate($img, -90, 0);
        $w = imagesx($img);
        $h = imagesy($img);
    }

    // 3. Detección dinámica de los encabezados magenta ("Parte 1 Lectura", "Parte 2 Redacción", "Parte 3 Matemáticas")
    $sampleX = [(int)($w * 0.45), (int)($w * 0.55), (int)($w * 0.65), (int)($w * 0.75)];
    $magentaCounts = array_fill(0, $h, 0);

    for ($y = 0; $y < $h; $y += 2) {
        foreach ($sampleX as $x) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            // Magenta adaptativo de examen Lumière (funciona con diferente iluminación)
            if ($r >= 120 && $g <= 110 && ($r - $g) >= 35) {
                $magentaCounts[$y]++;
            }
        }
    }

    $bands = [];
    $inBand = false;
    $start = 0;
    $minThick = max(3, (int)($h * 0.003));
    $maxThick = max(30, (int)($h * 0.035));

    for ($y = 0; $y < $h; $y += 2) {
        if ($magentaCounts[$y] >= 2) {
            if (!$inBand) {
                $inBand = true;
                $start = $y;
            }
        } else {
            if ($inBand) {
                $inBand = false;
                $end = $y - 2;
                $thickness = $end - $start + 1;
                // Grosor adaptativo según la resolución real de la fotografía
                if ($thickness >= $minThick && $thickness <= $maxThick) {
                    $bands[] = ['start' => $start, 'end' => $end, 'mid' => (int)(($start + $end) / 2)];
                }
            }
        }
    }

    $cropP1 = false; $cropP2 = false; $cropP3 = false;

    if (count($bands) >= 3) {
        // Encontrar inicio y fin horizontal del encabezado de Lectura dentro del tercio derecho
        $midY = $bands[0]['mid'];
        $minX = (int)($w * 0.30);
        $maxX = (int)($w * 0.95);
        $foundX = [];

        for ($x = $minX; $x < $maxX; $x++) {
            $rgb = imagecolorat($img, $x, $midY);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            if ($r >= 120 && $g <= 110 && ($r - $g) >= 35) {
                $foundX[] = $x;
            }
        }

        $xSec = !empty($foundX) ? max(0, min($foundX) - 15) : (int)($w * 0.38);
        $wSec = !empty($foundX) ? min($w - $xSec, (max($foundX) - min($foundX)) + 30) : (int)($w * 0.52);

        $yLec = max(0, $bands[0]['start'] - 6);
        $hLec = min($h - $yLec, ($bands[1]['start'] - $yLec) + 6);

        $yRed = max(0, $bands[1]['start'] - 6);
        $hRed = min($h - $yRed, ($bands[2]['start'] - $yRed) + 6);

        $yMat = max(0, $bands[2]['start'] - 6);
        $hMat = isset($bands[3]) ? (($bands[3]['start'] - $yMat) + 12) : (int)($hRed * 4.2);
        $hMat = min($h - $yMat, $hMat);

        $cropP1 = @imagecrop($img, ['x' => $xSec, 'y' => $yLec, 'width' => $wSec, 'height' => $hLec]);
        $cropP2 = @imagecrop($img, ['x' => $xSec, 'y' => $yRed, 'width' => $wSec, 'height' => $hRed]);
        $cropP3 = @imagecrop($img, ['x' => $xSec, 'y' => $yMat, 'width' => $wSec, 'height' => $hMat]);
    }

    // Si la detección de color no fue suficiente, usar proporciones adaptadas verticales
    if (!$cropP1 || !$cropP2 || !$cropP3) {
        $x = max(0, (int)($w * 0.28));
        $wSec = min($w - $x, (int)($w * 0.67));
        $cropP1 = @imagecrop($img, ['x' => $x, 'y' => (int)($h * 0.09), 'width' => $wSec, 'height' => (int)($h * 0.11)]);
        $cropP2 = @imagecrop($img, ['x' => $x, 'y' => (int)($h * 0.18), 'width' => $wSec, 'height' => (int)($h * 0.08)]);
        $cropP3 = @imagecrop($img, ['x' => $x, 'y' => (int)($h * 0.25), 'width' => $wSec, 'height' => (int)($h * 0.32)]);
    }


    if ($cropP1 && $cropP2 && $cropP3) {
        ob_start(); imagejpeg($cropP1, null, 95); $b1 = ob_get_clean();
        ob_start(); imagejpeg($cropP2, null, 95); $b2 = ob_get_clean();
        ob_start(); imagejpeg($cropP3, null, 95); $b3 = ob_get_clean();
        imagedestroy($cropP1); imagedestroy($cropP2); imagedestroy($cropP3);
        imagedestroy($img);
        return [$b1, $b2, $b3];
    }

    imagedestroy($img);
    return false;
}

/**
 * Extrae el nombre y datos del alumno desde el recuadro superior izquierdo de la hoja
 * 
 * @param string $imageBytes Contenido binario de la imagen
 * @return array ['nombre' => string, 'carrera' => string]
 */
function gemini_extraer_datos_alumno($imageBytes) {
    $prompt = <<<EOT
En esta fotografía de la hoja de examen, examina cuidadosamente el recuadro superior izquierdo (debajo del logotipo Lumière):
1. Identifica el campo "Nombre:" o "Nombre del Alumno:" y extrae con máxima precisión el texto escrito a mano o impreso.
2. Si está vacío o no hay texto escrito, devuelve cadena vacía "".
3. Si existe el campo "Carrera:", extráelo también.

Devuelve ÚNICAMENTE un objeto JSON:
{
  "nombre": "Nombre y apellidos detectados",
  "carrera": "Carrera detectada"
}
EOT;

    $payload = [
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => base64_encode($imageBytes)]]
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



