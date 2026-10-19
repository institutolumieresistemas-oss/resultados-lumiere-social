<?php
/**
 * ====================================================================
 * Motor Calificador de Exámenes y Cálculo de Puntaje PAA
 * (Integrado con el Tabulador Oficial Lumière / College Board)
 * ====================================================================
 */

require_once __DIR__ . '/config.php';

/**
 * Carga la Hoja Base con las respuestas correctas desde claves.json
 */
function cargar_hoja_base() {
    if (!file_exists(CLAVES_FILE)) {
        return [];
    }
    $contenido = file_get_contents(CLAVES_FILE);
    $data = json_decode($contenido, true);
    if (!is_array($data)) return [];
    if (isset($data['lectura'])) {
        return $data;
    }
    if (isset($data['1']['lectura'])) {
        return $data['1'];
    }
    return $data;
}

/**
 * Guarda la Hoja Base en claves.json
 */
function guardar_hoja_base($hojaBase) {
    return file_put_contents(CLAVES_FILE, json_encode($hojaBase, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Aliases para compatibilidad
function cargar_claves() { return cargar_hoja_base(); }
function guardar_claves($c) { return guardar_hoja_base($c); }

/**
 * Carga el tabulador oficial de conversión de aciertos a puntaje escalado
 */
function cargar_tabulador() {
    if (!file_exists(TABULADOR_FILE)) {
        return null;
    }
    $data = json_decode(file_get_contents(TABULADOR_FILE), true);
    return is_array($data) ? $data : null;
}

/**
 * Convierte aciertos verbales a puntaje PAA (200 a 800) usando el tabulador oficial
 */
function puntaje_tabulador_verbal($aciertos, $totalEnClave = 70) {
    $tab = cargar_tabulador();
    // Si la clave tiene menos de 70 preguntas, proyectar proporcionalmente a 70
    $aciertos70 = ($totalEnClave > 0 && $totalEnClave !== 70) 
        ? (int)round(($aciertos / $totalEnClave) * 70) 
        : (int)$aciertos;

    $aciertos70 = max(0, min(70, $aciertos70));

    if ($tab && isset($tab['verbal'][(string)$aciertos70])) {
        return (int)$tab['verbal'][(string)$aciertos70];
    }

    return 200 + (int)round(($aciertos70 / 70) * 600);
}

/**
 * Convierte aciertos de matemáticas a puntaje PAA (200 a 800) usando el tabulador oficial
 */
function puntaje_tabulador_matematicas($aciertos, $totalEnClave = 55) {
    $tab = cargar_tabulador();
    // Si la clave tiene menos de 55 preguntas, proyectar proporcionalmente a 55
    $aciertos55 = ($totalEnClave > 0 && $totalEnClave !== 55) 
        ? (int)round(($aciertos / $totalEnClave) * 55) 
        : (int)$aciertos;

    $aciertos55 = max(0, min(55, $aciertos55));

    if ($tab && isset($tab['matematicas'][(string)$aciertos55])) {
        return (int)$tab['matematicas'][(string)$aciertos55];
    }

    return 200 + (int)round(($aciertos55 / 55) * 600);
}

/**
 * Califica las respuestas detectadas por la IA contra la Hoja Base registrada
 * 
 * @param array $datosAlumno Respuestas detectadas por Gemini: ['lectura' => [...], 'redaccion' => [...], ...]
 * @return array Resultados detallados de la calificación
 */
function calificar_examen($datosAlumno) {
    $hojaBase = cargar_hoja_base();

    $secciones = [
        'lectura' => 'Lectura',
        'redaccion' => 'Redacción',
        'matematicas' => 'Matemáticas',
        'ingles' => 'Inglés'
    ];

    $resultados = [
        'exito' => true,
        'fecha' => date('d/m/Y H:i'),
        'secciones' => [],
        'total_aciertos' => 0,
        'total_preguntas_evaluadas' => 0,
        'total_contestadas' => 0,
        'paa_verbal_escala' => 0,
        'paa_mate_escala' => 0,
        'paa_total_escala' => 0,
        'puntaje_escala_100' => 0,
        'aciertos_verbal' => 0,
        'aciertos_mate' => 0
    ];

    $aciertosVerbal = 0;
    $preguntasVerbal = 0;

    $aciertosMate = 0;
    $preguntasMate = 0;

    foreach ($secciones as $claveSec => $nombreSec) {
        $clavePreguntas = $hojaBase[$claveSec] ?? [];
        $respAlumno = $datosAlumno[$claveSec] ?? [];

        if (empty($clavePreguntas) && empty($respAlumno)) {
            continue;
        }

        $aciertos = 0;
        $errores = 0;
        $omitidas = 0;
        $detalleReactivos = [];

        $todasLasPreguntas = array_unique(array_merge(array_keys($clavePreguntas), array_keys($respAlumno)));
        usort($todasLasPreguntas, function($a, $b) {
            return (int)$a - (int)$b;
        });

        foreach ($todasLasPreguntas as $numPregunta) {
            $correcta = isset($clavePreguntas[$numPregunta]) ? strtoupper(trim((string)$clavePreguntas[$numPregunta])) : null;
            $marcada = isset($respAlumno[$numPregunta]) ? strtoupper(trim((string)$respAlumno[$numPregunta])) : null;

            if ($correcta !== null) {
                if ($marcada === null || $marcada === '') {
                    $estado = 'omitida';
                    $omitidas++;
                } elseif ($marcada === $correcta) {
                    $estado = 'acierto';
                    $aciertos++;
                } else {
                    $estado = 'error';
                    $errores++;
                }

                $detalleReactivos[] = [
                    'num' => $numPregunta,
                    'estado' => $estado,
                    'marcada' => $marcada ?? '—',
                    'correcta' => $correcta
                ];
            } else {
                $detalleReactivos[] = [
                    'num' => $numPregunta,
                    'estado' => 'sin_clave',
                    'marcada' => $marcada ?? '—',
                    'correcta' => '?'
                ];
            }
        }

        $totalEnClave = count($clavePreguntas);
        $totalContestadasSec = count($respAlumno);

        $porcentaje = $totalEnClave > 0 ? round(($aciertos / $totalEnClave) * 100, 1) : 0;

        $resultados['secciones'][$claveSec] = [
            'nombre' => $nombreSec,
            'total_clave' => $totalEnClave,
            'contestadas' => $totalContestadasSec,
            'aciertos' => $aciertos,
            'errores' => $errores,
            'omitidas' => $omitidas,
            'porcentaje' => $porcentaje,
            'detalles' => $detalleReactivos
        ];

        $resultados['total_aciertos'] += $aciertos;
        $resultados['total_preguntas_evaluadas'] += $totalEnClave;
        $resultados['total_contestadas'] += $totalContestadasSec;

        if ($claveSec === 'lectura' || $claveSec === 'redaccion') {
            $aciertosVerbal += $aciertos;
            $preguntasVerbal += $totalEnClave;
        } elseif ($claveSec === 'matematicas') {
            $aciertosMate += $aciertos;
            $preguntasMate += $totalEnClave;
        }
    }

    if ($resultados['total_preguntas_evaluadas'] === 0) {
        return [
            'exito' => false,
            'mensaje' => 'Aún no hay una Hoja Base registrada con respuestas correctas.'
        ];
    }

    // Conversión con el Tabulador Oficial de la imagen
    $escalaVerbal = puntaje_tabulador_verbal($aciertosVerbal, $preguntasVerbal);
    $escalaMate = puntaje_tabulador_matematicas($aciertosMate, $preguntasMate);
    $escalaTotal = $escalaVerbal + $escalaMate;

    // Fórmula oficial Lumière / UdeG (Escala 0 a 100):
    // (Puntaje PAA Lectura y Redacción + Puntaje PAA Matemáticas - 400) / 12
    $puntaje100 = round(($escalaTotal - 400) / 12, 2);

    $resultados['paa_verbal_escala'] = $escalaVerbal;
    $resultados['paa_mate_escala'] = $escalaMate;
    $resultados['paa_total_escala'] = $escalaTotal;
    $resultados['puntaje_escala_100'] = $puntaje100;
    $resultados['aciertos_verbal'] = $aciertosVerbal;
    $resultados['aciertos_mate'] = $aciertosMate;

    return $resultados;
}

/**
 * Genera mensajes con formato HTML para Telegram a partir del resultado de la calificación
 * 
 * @param array $res Resultados de calificar_examen()
 * @return array Lista de strings (mensajes) listos para enviar a Telegram
 */
function formatear_reporte_telegram($res) {
    if (!$res['exito']) {
        $msg = "⚠️ <b>AÚN NO HAY UNA HOJA BASE REGISTRADA</b>\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "El sistema todavía no tiene una hoja de respuestas base guardada para comparar.\n\n";
        $msg .= "👨‍🏫 <b>Para registrar la Hoja Base:</b>\n";
        $msg .= "1️⃣ Escribe el comando: <code>A-EXAMEN</code>\n";
        $msg .= "2️⃣ Envía la foto de la hoja contestada con las respuestas correctas.\n\n";
        $msg .= "🎯 A partir de ese momento, cualquier examen de alumno se calificará automáticamente contra esa hoja base.";
        return [$msg];
    }

    $mensajes = [];

    // Mensaje 1: Resumen, Puntaje 0-100 y Calificación PAA
    $msg1 = "🎓 <b>INSTITUTO LUMIÈRE</b>\n";
    $msg1 .= "📋 <b>REPORTE DE CALIFICACIÓN OFICIAL</b>\n";
    $msg1 .= "━━━━━━━━━━━━━━━━━━━━━━━\n";
    $msg1 .= "📅 <b>Fecha:</b> " . $res['fecha'] . "\n\n";

    $msg1 .= "🎖️ <b>PUNTAJE DE EXAMEN (ESCALA 0 - 100):</b>\n";
    $msg1 .= "⭐️ <b>" . number_format($res['puntaje_escala_100'], 2) . " / 100 pts</b>\n\n";

    $msg1 .= "🏆 <b>PUNTAJE TOTAL COLLEGE BOARD (PAA):</b>\n";
    $msg1 .= "🎯 <b>" . number_format($res['paa_total_escala']) . " / 1,600 pts</b>\n";
    $msg1 .= "<i>(Calculado con tabulador oficial PAA)</i>\n\n";

    $msg1 .= "📊 <b>PUNTAJES POR TABULADOR (200 - 800):</b>\n";
    $msg1 .= "• 📖 <b>Lectura y Redacción:</b> <b>{$res['paa_verbal_escala']} pts</b> <i>({$res['aciertos_verbal']} aciertos)</i>\n";
    $msg1 .= "• 📐 <b>Matemáticas:</b> <b>{$res['paa_mate_escala']} pts</b> <i>({$res['aciertos_mate']} aciertos)</i>\n\n";

    $msg1 .= "📈 <b>DESGLOSE DE ACIERTOS POR SECCIÓN:</b>\n";
    $iconos = [
        'lectura' => '📖',
        'redaccion' => '✍️',
        'matematicas' => '📐',
        'ingles' => '🇬🇧'
    ];

    foreach ($res['secciones'] as $k => $sec) {
        $icono = $iconos[$k] ?? '📝';
        $msg1 .= "{$icono} <b>{$sec['nombre']}:</b> {$sec['aciertos']} de {$sec['total_clave']} aciertos ({$sec['porcentaje']}%)\n";
        $msg1 .= "   ↳ <i>Contestadas: {$sec['contestadas']} | Errores: {$sec['errores']} | Omitidas: {$sec['omitidas']}</i>\n";
    }

    $msg1 .= "\n━━━━━━━━━━━━━━━━━━━━━━━";
    $mensajes[] = $msg1;

    // Mensaje 2: Detalle pregunta por pregunta
    $msg2 = "🔍 <b>DETALLE DE REACTIVOS</b>\n";
    $msg2 .= "━━━━━━━━━━━━━━━━━━━━━━━\n";

    foreach ($res['secciones'] as $k => $sec) {
        $icono = $iconos[$k] ?? '📝';
        $msg2 .= "\n{$icono} <b>{$sec['nombre']}</b> ({$sec['aciertos']}/{$sec['total_clave']}):\n";

        $linea = "";
        $count = 0;

        foreach ($sec['detalles'] as $item) {
            $num = $item['num'];
            $marcada = $item['marcada'];
            $correcta = $item['correcta'];
            $estado = $item['estado'];

            if ($estado === 'acierto') {
                $bloque = "✅ <b>{$num}:</b>{$marcada}";
            } elseif ($estado === 'error') {
                $bloque = "❌ <b>{$num}:</b>{$marcada} <i>(clave:{$correcta})</i>";
            } elseif ($estado === 'omitida') {
                $bloque = "⚪ <b>{$num}:</b>— <i>({$correcta})</i>";
            } else {
                $bloque = "❓ <b>{$num}:</b>{$marcada}";
            }

            if ($estado === 'acierto') {
                $linea .= "  " . $bloque;
                $count++;
                if ($count % 3 === 0) {
                    $msg2 .= $linea . "\n";
                    $linea = "";
                }
            } else {
                if (!empty($linea)) {
                    $msg2 .= $linea . "\n";
                    $linea = "";
                    $count = 0;
                }
                $msg2 .= "  " . $bloque . "\n";
            }

            if (strlen($msg2) > 3500) {
                $mensajes[] = $msg2;
                $msg2 = "🔍 <b>DETALLE (CONTINUACIÓN)</b>\n━━━━━━━━━━━━━━━━━━━━━━━\n";
            }
        }

        if (!empty($linea)) {
            $msg2 .= $linea . "\n";
        }
    }

    $mensajes[] = $msg2;

    return $mensajes;
}

/**
 * Pinta visualmente los círculos detectados (verde para aciertos, rojo para errores)
 * directamente sobre la fotografía de la hoja de respuestas del alumno.
 * 
 * @param string $imageBytes Contenido binario de la imagen original
 * @param array $datosAlumno Respuestas detectadas por sección
 * @param array $hojaBase Claves oficiales
 * @return string|null Data URI en formato data:image/jpeg;base64,... o null
 */
function generar_hoja_con_circulos_anotados($imageBytes, $datosAlumno, $hojaBase = [], $modo = 'evaluacion') {
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }

    $img = @imagecreatefromstring($imageBytes);
    if (!$img) return null;

    // 1. Orientación EXIF
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

    if ($w > $h && function_exists('imagerotate')) {
        $img = imagerotate($img, -90, 0);
        $w = imagesx($img);
        $h = imagesy($img);
    }

    // 2. Detección dinámica de bandas magenta del examen Lumière
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
            if (!$inBand) { $inBand = true; $start = $y; }
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

    $secBoxes = [];
    if (count($bands) >= 3) {
        // Medir extensión horizontal exacta de la barra de encabezado
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

        $secX = !empty($foundX) ? min($foundX) : (int)($w * 0.40);
        $secWidth = !empty($foundX) ? (max($foundX) - $secX) : (int)($w * 0.48);

        $secBoxes['lectura'] = [
            'x' => $secX,
            'y' => $bands[0]['end'] + 2,
            'width' => $secWidth,
            'height' => max(40, $bands[1]['start'] - $bands[0]['end'] - 4),
            'cols' => 5, 'rows' => 9, 'total' => 45
        ];
        $secBoxes['redaccion'] = [
            'x' => $secX,
            'y' => $bands[1]['end'] + 2,
            'width' => $secWidth,
            'height' => max(35, $bands[2]['start'] - $bands[1]['end'] - 4),
            'cols' => 5, 'rows' => 5, 'total' => 25
        ];
        $secBoxes['matematicas'] = [
            'x' => $secX,
            'y' => $bands[2]['end'] + 2,
            'width' => (int)($secWidth * 0.60),
            'height' => isset($bands[3]) ? max(100, $bands[3]['start'] - $bands[2]['end'] - 4) : (int)($secWidth * 1.5),
            'cols' => 3, 'rows' => 15, 'total' => 45
        ];
    } else {
        $xStart = max(0, (int)($w * 0.38));
        $secWidth = min($w - $xStart, (int)($w * 0.50));
        $secBoxes['lectura'] = [
            'x' => $xStart,
            'y' => (int)($h * 0.16),
            'width' => $secWidth,
            'height' => (int)($h * 0.080),
            'cols' => 5, 'rows' => 9, 'total' => 45
        ];
        $secBoxes['redaccion'] = [
            'x' => $xStart,
            'y' => (int)($h * 0.24),
            'width' => $secWidth,
            'height' => (int)($h * 0.055),
            'cols' => 5, 'rows' => 5, 'total' => 25
        ];
        $secBoxes['matematicas'] = [
            'x' => $xStart,
            'y' => (int)($h * 0.30),
            'width' => (int)($secWidth * 0.60),
            'height' => (int)($h * 0.25),
            'cols' => 3, 'rows' => 15, 'total' => 45
        ];
    }

    // Colores de anotación
    $colGreen = imagecolorallocate($img, 16, 185, 129);      // Verde brillante
    $colRed = imagecolorallocate($img, 239, 68, 68);         // Rojo brillante
    $colYellow = imagecolorallocate($img, 245, 158, 11);     // Amarillo
    $colBlue = imagecolorallocatealpha($img, 59, 130, 246, 60);
    $colGreenFill = imagecolorallocatealpha($img, 16, 185, 129, 90);
    $colRedOverlay = imagecolorallocatealpha($img, 239, 68, 68, 60);

    imagesetthickness($img, 2);

    $optMap = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3];
    $invOpt = [0 => 'A', 1 => 'B', 2 => 'C', 3 => 'D'];

    $calcPos = function($box, $num, $optIdx) {
        $cols = $box['cols'];
        $rows = $box['rows'];
        $col = (int)(($num - 1) / $rows);
        $row = ($num - 1) % $rows;
        
        $colWidth = $box['width'] / $cols;
        $rowHeight = $box['height'] / $rows;
        
        $numWidth = $colWidth * 0.24;
        $bubblesWidth = $colWidth * 0.76;
        $optStep = $bubblesWidth / 4;
        
        $cx = $box['x'] + ($col * $colWidth) + $numWidth + ($optIdx * $optStep) + ($optStep / 2);
        $cy = $box['y'] + ($row * $rowHeight) + ($rowHeight / 2);
        return [(int)$cx, (int)$cy];
    };

    // Dibujar rectángulos sutiles de secciones
    foreach ($secBoxes as $secKey => $box) {
        imagerectangle($img, $box['x'] - 2, $box['y'] - 2, $box['x'] + $box['width'] + 2, $box['y'] + $box['height'] + 2, $colBlue);
    }

    $refSecWidth = $secBoxes['lectura']['width'] ?? ($w * 0.6);
    $bubbleRadius = max(6, (int)($refSecWidth / 38));

    if ($modo === 'escaneo' || $modo === 'omr') {
        // =========================================================================
        // MODO ESCANEO (SOLICITADO POR EL USUARIO):
        // Círculos VERDES si NO están contestados y ROJO donde venga contestación
        // =========================================================================
        foreach ($secBoxes as $secKey => $box) {
            $respSec = $datosAlumno[$secKey] ?? [];

            for ($num = 1; $num <= $box['total']; $num++) {
                $marcada = isset($respSec[$num]) ? strtoupper(trim((string)$respSec[$num])) : null;

                for ($optIdx = 0; $optIdx < 4; $optIdx++) {
                    $optLetter = $invOpt[$optIdx];
                    list($cx, $cy) = $calcPos($box, $num, $optIdx);

                    if ($marcada && $marcada === $optLetter) {
                        // CONTESTADA: Círculo ROJO relleno y borde resaltado
                        imagefilledellipse($img, $cx, $cy, $bubbleRadius * 2, $bubbleRadius * 2, $colRedOverlay);
                        imageellipse($img, $cx, $cy, $bubbleRadius * 2, $bubbleRadius * 2, $colRed);
                        imageellipse($img, $cx, $cy, ($bubbleRadius * 2) - 2, ($bubbleRadius * 2) - 2, $colRed);
                    } else {
                        // NO CONTESTADA: Círculo VERDE delimitando la burbuja vacía
                        imageellipse($img, $cx, $cy, $bubbleRadius * 2, $bubbleRadius * 2, $colGreen);
                    }
                }
            }
        }
    } else {
        // =========================================================================
        // MODO EVALUACIÓN:
        // Verde = Acierto, Rojo = Error, Amarillo = Omitida
        // =========================================================================
        foreach ($secBoxes as $secKey => $box) {
            $respSec = $datosAlumno[$secKey] ?? [];
            $claveSec = $hojaBase[$secKey] ?? [];

            for ($num = 1; $num <= $box['total']; $num++) {
                $correcta = isset($claveSec[$num]) ? strtoupper(trim((string)$claveSec[$num])) : null;
                $marcada = isset($respSec[$num]) ? strtoupper(trim((string)$respSec[$num])) : null;

                if ($marcada && isset($optMap[$marcada])) {
                    $optIdx = $optMap[$marcada];
                    list($cx, $cy) = $calcPos($box, $num, $optIdx);

                    if ($correcta && $marcada === $correcta) {
                        // ACIERTO: Círculo verde brillante
                        imagefilledellipse($img, $cx, $cy, $bubbleRadius * 2, $bubbleRadius * 2, $colGreenFill);
                        imageellipse($img, $cx, $cy, $bubbleRadius * 2, $bubbleRadius * 2, $colGreen);
                        imageellipse($img, $cx, $cy, ($bubbleRadius * 2) - 2, ($bubbleRadius * 2) - 2, $colGreen);
                    } else {
                        // ERROR: Círculo rojo en la contestada incorrecta
                        imagefilledellipse($img, $cx, $cy, $bubbleRadius * 2, $bubbleRadius * 2, $colRedOverlay);
                        imageellipse($img, $cx, $cy, $bubbleRadius * 2, $bubbleRadius * 2, $colRed);
                        imageellipse($img, $cx, $cy, ($bubbleRadius * 2) - 2, ($bubbleRadius * 2) - 2, $colRed);

                        // Contorno verde en la correcta
                        if ($correcta && isset($optMap[$correcta])) {
                            list($cxC, $cyC) = $calcPos($box, $num, $optMap[$correcta]);
                            imageellipse($img, $cxC, $cyC, $bubbleRadius * 2, $bubbleRadius * 2, $colGreen);
                        }
                    }
                } elseif ($correcta && isset($optMap[$correcta])) {
                    // OMITIDA: Amarillo en la correcta
                    list($cxC, $cyC) = $calcPos($box, $num, $optMap[$correcta]);
                    imageellipse($img, $cxC, $cyC, $bubbleRadius * 2, $bubbleRadius * 2, $colYellow);
                }
            }
        }
    }

    ob_start();
    imagejpeg($img, null, 88);
    $jpegBytes = ob_get_clean();
    imagedestroy($img);

    return 'data:image/jpeg;base64,' . base64_encode($jpegBytes);
}

