# 📋 Guía Maestra y Registro de Actualizaciones - Sistema Lumière OMR

Documento central con las rutas, dominios, credenciales, actualizaciones técnicas e instrucciones operativas del sistema calificador de exámenes PAA del **Instituto Lumière**.

---

## 🌐 1. Dominios y Accesos Oficiales en la Web

| Módulo | Enlace Web | Descripción | Acceso |
| :--- | :--- | :--- | :--- |
| **Portal del Alumno** | [https://calificador.lumieresocial.com](https://calificador.lumieresocial.com) | Donde los alumnos toman foto o suben su hoja de respuestas para calificarla con IA. | Abierto / 1 intento por alumno |
| **Panel Docente y Resultados** | [https://resultados.lumieresocial.com](https://resultados.lumieresocial.com) | Registro de hoja base, estadísticas grupales, auditoría de alumnos y exportación a Excel. | Clave: `lumiere2026` |
| **Editor Rápido de Claves** | [https://calificador.lumieresocial.com/panel_claves.php](https://calificador.lumieresocial.com/panel_claves.php) | Panel directo para consulta rápida o edición de la Hoja Base. | Clave: `lumiere2026` |

---

## 📂 2. Estructura de Rutas y Repositorios Locales

### A. Repositorios de Producción (Hostinger)
* **Calificador Alumnos (`calificador.lumieresocial.com`):**
  * Ruta local: `C:\Users\LENOVO\source\repos\scanner lumi\SUBIR_A_HOSTINGER`
  * Repositorio GitHub: `https://github.com/institutolumieresistemas-oss/calificador-Lumiere-Social.git`
  * Despliegue: Automático a Hostinger mediante GitHub Actions al hacer `git push origin main`.
* **Panel Docente (`resultados.lumieresocial.com`):**
  * Ruta local: `C:\Users\LENOVO\source\repos\resultados-lumiere-social`
  * Repositorio GitHub: `https://github.com/institutolumieresistemas-oss/resultados-lumiere-social.git`
  * Despliegue: Automático a Hostinger mediante GitHub Actions al hacer `git push origin main`.
* **Código Fuente de Desarrollo PHP:**
  * Ruta local: `C:\Users\LENOVO\source\repos\scanner lumi\bot-telegram-php`

### B. Proyecto Alternativo Local (Python FastAPI / OpenCV / PWA)
* **Scantron Móvil Local:**
  * Ruta local: `C:\Users\LENOVO\Desktop\Lumiere\Sacntron`
  * Motor: FastAPI + OpenCV tradicional (`api/paa_engine.py` y `api/omr_engine.py`).

---

## 🚀 3. Registro de Actualizaciones Recientes (Changelog)

### ✅ Optimización de Visión por Computadora con IA (Google Gemini)
1. **Migración de Modelo a Gemini Flash Lite:**
   * **Antes:** Se utilizaba `gemini-3.8-flash`, el cual presentaba saturación de demanda (`HTTP 503`) y timeouts prolongados en horas pico.
   * **Ahora:** Se configuró **`gemini-3.5-flash-lite`** como modelo principal de visión OMR (con fallback a `gemini-3-flash-preview`). En pruebas con hojas de examen reales, procesó las 3 secciones con respuesta inmediata y **115 de 115 reactivos detectados (100% de precisión)**.
2. **Eliminación del Bloqueo de Cuota (`HTTP 429`):**
   * Se incorporó un espaciado dinámico anti-ráfaga de **500 ms** entre peticiones de secciones para evitar saturar el límite de peticiones por minuto.
3. **Mecanismo de Autorrecuperación Seccional:**
   * Si por alguna fluctuación de red una sección arroja menos respuestas de las esperadas, el backend reintenta automáticamente esa sección individual antes de sellar la calificación.
4. **Sincronización en Producción:**
   * Ambas plataformas (`calificador.lumieresocial.com` y `resultados.lumieresocial.com`) fueron actualizadas y desplegadas en Hostinger.

---

## 📝 4. Instrucciones de Operación

### A. ¿Cómo registrar o cambiar la Hoja Base (Respuestas Correctas)?
*(No se requiere Telegram; se gestiona 100% desde la web).*

1. Entra a **[https://resultados.lumieresocial.com](https://resultados.lumieresocial.com)**.
2. Ingresa la contraseña de administrador: **`lumiere2026`**.
3. Haz clic en la pestaña superior: **"📋 Hoja Base Oficial (Claves PAA)"**.
4. Selecciona tu método preferido:
   * **Opción con Foto (Automático con IA):**
     * En el recuadro *"Escaneo Rápido de Hoja Base Maestra con IA"*, sube la foto de tu hoja contestada con las respuestas maestras.
     * La IA detectará los 115 reactivos y los volcará en la grilla interactiva.
   * **Opción Manual (Clic en Grilla):**
     * Puedes hacer clic directamente en las burbujas **A, B, C o D** de las secciones:
       * **Lectura:** Preguntas 1 a 45.
       * **Redacción:** Preguntas 1 a 25.
       * **Matemáticas:** Preguntas 1 a 45.
5. Haz clic en el botón verde **"💾 Guardar Hoja Base Oficial"**.
   * *A partir de ese instante, todos los exámenes de los alumnos se calificarán contra esta clave.*

---

### B. ¿Cómo califica un Alumno su Examen?
1. El alumno ingresa desde su teléfono móvil o computadora a **[https://calificador.lumieresocial.com](https://calificador.lumieresocial.com)**.
2. Escribe su nombre completo y toma/sube una foto bien iluminada de su hoja de respuestas.
3. El sistema analiza la hoja con la IA de Google en ~3 segundos y le muestra una previsualización de sus respuestas detectadas.
4. El alumno confirma la entrega y el sistema le muestra su reporte detallado:
   * **Puntaje PAA Escalado Oficial:** Escala de 200 a 800 puntos por área (Puntaje Verbal, Puntaje Matemáticas y Puntaje Total de 400 a 1600).
   * **Desglose de Aciertos:** Correctas, incorrectas y omitidas por sección.
   * **Bloqueo de seguridad:** Una vez entregado, el sistema bloquea al alumno para evitar intentos no autorizados.

---

### C. ¿Cómo consultar Resultados y autorizar Reintentos (Docente)?
1. Entra a **[https://resultados.lumieresocial.com](https://resultados.lumieresocial.com)** (`lumiere2026`).
2. En la pestaña **"📊 Calificaciones y Alumnos"** verás:
   * Promedio general del grupo y distribución de puntajes.
   * Tabla con cada alumno evaluado, fecha/hora, puntajes escalados y estado de entrega.
   * Botón **"📥 Exportar a Excel (CSV)"** para descargar la lista completa.
   * **Desbloqueo / Autorización de Reintento:** Si un alumno subió una foto borrosa y solicita reintento, el profesor puede hacer clic en *"Autorizar nuevo intento"* junto al nombre del estudiante.

---

## 💳 5. Configuración de Google AI Studio y Facturación

El sistema utiliza la API de Google Gemini a través de **Google AI Studio**:
* **Consola de administración:** [https://aistudio.google.com/](https://aistudio.google.com/)
* **Costo por Examen:** El consumo de procesar 1 hoja de respuestas con visión multi-recorte cuesta aproximadamente **$0.0005 USD**.
* **Escala:** Calificar **500 exámenes completos** tiene un costo estimado de entre **$5 y $10 pesos mexicanos**.
* **Recomendación para periodos de aplicación masiva:**
  * En Google AI Studio, activar **"Set up billing"** vinculando una tarjeta. Esto elimina de raíz cualquier límite de velocidad de peticiones concurrentes cuando cientos de alumnos suban sus fotos a la misma hora.

---

*Fecha de última actualización: 21 de Octubre de 2026.*
