# 📝 Sistema Calificador de Exámenes OMR - Instituto Lumière

Sistema web en PHP para la calificación y auditoría automática de hojas de respuestas OMR de pruebas de admisión estilo **College Board PAA** (Prueba de Aptitud Académica), impulsado por Visión e Inteligencia Artificial (**Google Gemini Vision**).

---

## 📁 Estructura del Proyecto

| Archivo / Carpeta | Descripción |
|---|---|
| `index.php` | **Portal del Alumno / Router principal**: Interfaz web móvil donde los estudiantes toman o suben foto de su hoja y reciben su puntaje. Rutea a `panel.php` si se accede por el subdominio de resultados. |
| `captura_manual.php` | **Captura Manual y Dictado por Voz (Alumnos)**: Permite ingresar y calificar exámenes en pestaña dedicada sin requerir escaneo de fotos, pidiendo nombre y cargando los módulos oficiales PAA. |
| `captura_base.php` | **Captura y Dictado de Hoja Base (Docentes)**: Editor visual interactivo con dictado por voz para configurar las respuestas oficiales PAA sin escaneo de fotografías. |
| `panel.php` | **Panel Docente y Administrativo**: Estadísticas grupales, auditoría de hojas, reactivos más fallados, escaneo de Hoja Base y exportación a Excel. |
| `panel_claves.php` | **Editor rápido de claves**: Panel web para consulta o edición manual de la Hoja Base Oficial. |
| `api_calificar.php` | **API REST / Backend**: Procesa las solicitudes AJAX de calificación, auditoría, subida de imágenes y registro de alumnos. |
| `calificador.php` | **Motor de Calificación**: Evalúa aciertos, reactivos grid numéricos con equivalencias (ej. `1/2` = `0.5`) y calcula el puntaje escalado oficial (400 a 1600 pts). |
| `gemini.php` | **Cliente de Inteligencia Artificial**: Extrae y digitaliza las respuestas marcadas en la hoja mediante la API de Google Gemini. |
| `config.php` | Configuración de credenciales de Gemini, contraseñas docentes, rutas compartidas y parámetros de ejecución. |
| `claves.json` | Base de datos JSON con las respuestas correctas oficiales (Hoja Base). |
| `tabulador.json` | Tabulador de conversión de aciertos a la escala College Board PAA. |
| `uploads/` | Directorio donde se almacenan temporalmente las fotografías de las hojas escaneadas. |
| `.github/workflows/deploy.yml` | Integración continua para despliegue automatizado por SSH a Hostinger en cada push a `main`. |
| `chek.md` | Guía maestra detallada y bitácora técnica de actualizaciones. |

---

## 🚀 Despliegue en Producción

El proyecto cuenta con integración continua mediante GitHub Actions:
- Todo cambio enviado a la rama `main` se despliega automáticamente en el servidor Hostinger.
- Requiere PHP 8.1+ con extensiones `curl`, `json` y `gd`.
