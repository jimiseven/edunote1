<?php
/**
 * Centralizador de notas → descarga automática de XLSX con encabezados verticales.
 * No depende de Composer; usa SheetJS en el navegador.
 */
session_start();
require_once '../config/database.php';

// -----------------------------------------------------------------------------
// 1. CONTROL DE ACCESO ----------------------------------------------------------
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

// -----------------------------------------------------------------------------
// 2. PARÁMETROS -----------------------------------------------------------------
$id_curso  = isset($_GET['id'])        ? (int)$_GET['id']        : 0;
$trimestre = isset($_GET['trimestre']) ? (int)$_GET['trimestre'] : 1;

if ($id_curso <= 0) {
    header('Location: priv.php');
    exit();
}

// -----------------------------------------------------------------------------
// 3. CONSULTAS A BD -------------------------------------------------------------
$db   = new Database();
$conn = $db->connect();

// 3.1 Curso
$stmt = $conn->prepare("SELECT curso, paralelo, nivel FROM cursos WHERE id_curso = ?");
$stmt->execute([$id_curso]);
$curso = $stmt->fetch(PDO::FETCH_ASSOC);
$nombre_curso = $curso['nivel'] . ' ' . $curso['curso'] . ' \"' . $curso['paralelo'] . '\"';

// 3.2 Estudiantes
$stmt = $conn->prepare("SELECT id_estudiante, nombres, apellido_paterno, apellido_materno
                         FROM estudiantes
                         WHERE id_curso = ?
                         ORDER BY apellido_paterno, apellido_materno, nombres");
$stmt->execute([$id_curso]);
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3.3 Materias
$stmt = $conn->prepare("SELECT m.id_materia, m.nombre_materia, m.es_extra
                         FROM cursos_materias cm
                         JOIN materias m ON cm.id_materia = m.id_materia
                         WHERE cm.id_curso = ?
                         ORDER BY m.es_extra, m.nombre_materia");
$stmt->execute([$id_curso]);
$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3.4 Calificaciones y promedios
$calificaciones = [];
$promedios      = [];
foreach ($estudiantes as $e) {
    $suma = 0; $cont = 0;
    foreach ($materias as $m) {
        $sel = $conn->prepare("SELECT calificacion
                                FROM calificaciones
                                WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?");
        $sel->execute([$e['id_estudiante'], $m['id_materia'], $trimestre]);
        $nota = $sel->fetchColumn();
        $calificaciones[$e['id_estudiante']][$m['id_materia']] = $nota !== false ? (float)$nota : '';
        if ($nota !== false && !$m['es_extra']) { $suma += $nota; $cont++; }
    }
    $promedios[$e['id_estudiante']] = $cont ? round($suma / $cont, 2) : '';
}

// -----------------------------------------------------------------------------
// 4. SALIDA: HTML MINIMAL + DESCARGA AUTOMÁTICA ---------------------------------
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Descargando…</title></head><body style="margin:0;">
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
// Datos PHP → JS
const estudiantes   = <?=json_encode($estudiantes,   JSON_UNESCAPED_UNICODE)?>;
const materias      = <?=json_encode($materias,      JSON_UNESCAPED_UNICODE)?>;
const calificaciones= <?=json_encode($calificaciones,JSON_UNESCAPED_UNICODE)?>;
const promedios     = <?=json_encode($promedios,     JSON_UNESCAPED_UNICODE)?>;
const nombreCurso   = <?=json_encode($nombre_curso,  JSON_UNESCAPED_UNICODE)?>;
const trimestre     = <?=$trimestre?>;

function generarXLSX(){
    const wb = XLSX.utils.book_new();
    const data = [];

    // Título + fila vacía
    data.push([`Centralizador: ${nombreCurso} - Trimestre ${trimestre}`]);
    data.push([]);

    // Encabezados
    const headers = ['Nº', 'Estudiante', ...materias.map(m => m.nombre_materia + (m.es_extra ? ' (Extra)' : '')), 'Promedio'];
    data.push(headers);

    // Filas
    let n = 1;
    estudiantes.forEach(e => {
        const row = [n++, `${e.apellido_paterno.toUpperCase()} ${e.apellido_materno.toUpperCase()}, ${e.nombres.toUpperCase()}`];
        materias.forEach(m => {
            const nota = (calificaciones[e.id_estudiante] || {})[m.id_materia] ?? '';
            row.push(nota);
        });
        row.push(promedios[e.id_estudiante] ?? '');
        data.push(row);
    });

    const ws = XLSX.utils.aoa_to_sheet(data);

    // Fusionar título
    ws['!merges'] = [{s:{r:0,c:0}, e:{r:0,c:headers.length-1}}];

    // Estilo título
    ws['A1'].s = {font:{bold:true,sz:14},alignment:{horizontal:'center'}};

    // Encabezados verticales
    headers.forEach((_, i) => {
        const cellAddr = XLSX.utils.encode_cell({r:2, c:i});
        const cell = ws[cellAddr];
        if(cell){
            cell.s = {
                font:{bold:true},
                alignment:{horizontal:'center', vertical:'center', textRotation:90},
                fill:{patternType:'solid', fgColor:{rgb:'D9D9D9'}},
                border:{top:{style:'thin'}, bottom:{style:'thin'}, left:{style:'thin'}, right:{style:'thin'}}
            };
        }
    });

    // Auto ancho (un poco mayor para primer par de columnas, estrecho para verticales)
    const cols = headers.map((h, idx) => idx < 2 ? {wch:h.length+4} : {wch:6});
    ws['!cols'] = cols;

    // Congelar encabezados
    ws['!freeze'] = {xSplit:0, ySplit:3};

    XLSX.utils.book_append_sheet(wb, ws, `Trimestre ${trimestre}`);
    const nombreArchivo = `Centralizador_${nombreCurso.replace(/\s+/g,'_')}_T${trimestre}.xlsx`;
    XLSX.writeFile(wb, nombreArchivo);

    // Cerrar ventana si está en popup (opcional)
    // window.close();
}

window.addEventListener('load', generarXLSX);
</script></body></html>