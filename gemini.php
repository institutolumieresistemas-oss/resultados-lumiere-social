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
 * 4. Función central para ejecutar peticiones a la API de Gemini con rotación automática de modelos
 */
function gemini_ejecutar_peticion($payload, $timeout = 30) {
    if (empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'TU_GEMINI_API_KEY_AQUI') {
        return ['exito' => false, 'error' => 'API Key de Gemini no configurada'];
    }

    $models = array_unique([
        'gemini-3.5-flash-lite',
        'gemini-3-flash-preview',
        'gemini-3.5-flash',
        GEMINI_PRIMARY_MODEL,
        GEMINI_FALLBACK_MODEL,
        'gemini-flash-latest',
        'gemini-3.8-flash'
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

    // Coordenadas proporcionales óptimas para la hoja oficial Lumière
    $crops = [
        'lectura' => $helperCrop([
            'x' => (int)($w * 0.26),
            'y' => (int)($h * 0.12),
            'width' => (int)($w * 0.70),
            'height' => (int)($h * 0.16)
        ]),
        'redaccion' => $helperCrop([
            'x' => (int)($w * 0.26),
            'y' => (int)($h * 0.24),
            'width' => (int)($w * 0.70),
            'height' => (int)($h * 0.12)
        ]),
        'matematicas_mc' => $helperCrop([
            'x' => (int)($w * 0.27),
            'y' => (int)($h * 0.32),
            'width' => (int)($w * 0.44),
            'height' => (int)($h * 0.21)
        ]),
        'matematicas_46_47' => $helperCrop([
            'x' => (int)($w * 0.66),
            'y' => (int)($h * 0.32),
            'width' => (int)($w * 0.30),
            'height' => (int)($h * 0.21)
        ]),
        'matematicas_48_51' => $helperCrop([
            'x' => (int)($w * 0.02),
            'y' => (int)($h * 0.49),
            'width' => (int)($w * 0.49),
            'height' => (int)($h * 0.20)
        ]),
        'matematicas_52_55' => $helperCrop([
            'x' => (int)($w * 0.49),
            'y' => (int)($h * 0.49),
            'width' => (int)($w * 0.49),
            'height' => (int)($h * 0.20)
        ]),
        'datos_alumno' => $helperCrop([
            'x' => 0,
            'y' => (int)($h * 0.10),
            'width' => (int)($w * 0.35),
            'height' => (int)($h * 0.25)
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
function gemini_analyze_sheet($imageBytes, $mimeType = 'image/jpeg') {
    if (empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'TU_GEMINI_API_KEY_AQUI') {
        bot_log("Error: La constante GEMINI_API_KEY no esta configurada.");
        return ['error' => 'API Key de Gemini no configurada'];
    }

    // 1. Pipeline de Multi-Recorte de Alta Resolución
    $recortes = gemini_preparar_recortes_completos($imageBytes);
    if ($recortes !== false) {
        $resLec = gemini_analizar_lectura($recortes['lectura']);
        $resRed = gemini_analizar_redaccion($recortes['redaccion']);
        $resMatMC = gemini_analizar_matematicas_mc($recortes['matematicas_mc']);
        $resMatGrid = gemini_analizar_matematicas_grid(
            $recortes['matematicas_46_47'] ?? null,
            $recortes['matematicas_48_51'] ?? null,
            $recortes['matematicas_52_55'] ?? null
        );

        $resMat = array_merge($resMatMC, $resMatGrid);
        ksort($resMat, SORT_NUMERIC);

        $totalDetectadas = count($resLec) + count($resRed) + count($resMat);
        bot_log("Pipeline Seccional OMR completado: {$totalDetectadas} respuestas detectadas (Lec: " . count($resLec) . ", Red: " . count($resRed) . ", Mat: " . count($resMat) . ").");

        if ($totalDetectadas >= 15) {
            return [
                'lectura' => $resLec,
                'redaccion' => $resRed,
                'matematicas' => $resMat,
                'ingles' => []
            ];
        }
    }

    // 2. Respaldo: Análisis directo de imagen completa si los recortes fallaron
    bot_log("Iniciando respaldo de imagen completa...");
    return gemini_analyze_sheet_full($imageBytes, $mimeType);
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

    $res = gemini_ejecutar_peticion($payload, 35);
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
En esta sección de la hoja de examen (debajo del logotipo Lumière):
1. Identifica el campo "Nombre:" o "Nombre del Alumno:" y extrae el texto escrito a mano o impreso.
2. Si está vacío o no hay texto escrito, devuelve cadena vacía "".
3. Si existe el campo "Carrera:", extráelo también.

Devuelve ÚNICAMENTE un objeto JSON:
{
  "nombre": "Nombre detectado",
  "carrera": "Carrera detectada"
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
