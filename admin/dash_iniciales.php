<?php
session_start();
require_once '../config/database.php';

// Verificar administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

// Obtener cursos iniciales
$stmt = $conn->query("
    SELECT c.id_curso, c.curso, c.paralelo
    FROM cursos c
    WHERE c.nivel = 'Inicial'
    ORDER BY c.curso, c.paralelo
");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmtGestion = $conn->query("SELECT anio_escolar FROM configuracion_sistema ORDER BY id DESC LIMIT 1");
$gestionActual = trim((string)($stmtGestion->fetchColumn() ?: date('Y')));

$stmtEstudiantes = $conn->query("SELECT id_estudiante, id_curso, apellido_paterno, apellido_materno, nombres
                                 FROM estudiantes
                                 ORDER BY id_curso, apellido_paterno, apellido_materno, nombres");
$estudiantesPorCurso = [];
$estudiantesIdsPorCurso = [];
foreach ($stmtEstudiantes->fetchAll(PDO::FETCH_ASSOC) as $est) {
    $idCurso = (int)$est['id_curso'];
    $idEstudiante = (int)($est['id_estudiante'] ?? 0);
    $nombreCompleto = trim(implode(' ', array_filter([
        $est['apellido_paterno'] ?? '',
        $est['apellido_materno'] ?? '',
        $est['nombres'] ?? ''
    ], static fn($v) => $v !== null && trim((string)$v) !== '')));
    if ($nombreCompleto !== '') {
        $estudiantesPorCurso[$idCurso][] = [
            'id_estudiante' => $idEstudiante,
            'nombre' => $nombreCompleto
        ];
        $estudiantesIdsPorCurso[$idCurso][] = $idEstudiante;
    }
}

$evaluacionesPorCurso = [];
if (!empty($cursos)) {
    $idsCursos = array_map(static fn($c) => (int)$c['id_curso'], $cursos);
    $phCursos = implode(',', array_fill(0, count($idsCursos), '?'));

    $stmtComentarios = $conn->prepare("SELECT e.id_curso, cp.id_estudiante, pe.trimestre, cp.id_materia, cp.comentario
                                       FROM calificaciones_parciales cp
                                       INNER JOIN estudiantes e ON e.id_estudiante = cp.id_estudiante
                                       INNER JOIN periodos_evaluacion pe ON pe.id_periodo_evaluacion = cp.id_periodo_evaluacion
                                       INNER JOIN cursos_materias cm ON cm.id_materia = cp.id_materia AND cm.id_curso = e.id_curso
                                       WHERE e.id_curso IN ($phCursos)
                                         AND pe.parcial = 1
                                         AND cp.comentario IS NOT NULL
                                         AND cp.comentario <> ''
                                       ORDER BY e.id_curso, cp.id_estudiante, pe.trimestre, cp.id_materia");
    $stmtComentarios->execute($idsCursos);

    foreach ($stmtComentarios->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $idCurso = (int)$row['id_curso'];
        $idEst = (int)$row['id_estudiante'];
        $trim = (int)$row['trimestre'];
        $texto = trim((string)$row['comentario']);
        if ($texto === '' || $trim < 1 || $trim > 3) {
            continue;
        }
        if (!isset($evaluacionesPorCurso[$idCurso][$idEst][$trim])) {
            $evaluacionesPorCurso[$idCurso][$idEst][$trim] = $texto;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cursos de Inicial</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link id="bootstrap-css" rel="stylesheet" href="../css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <style>
        /* ---- DARK MODE ---- */
        body { background-color: #181a1b; color: #eaeaea; }
        .container-fluid { min-height: 100dvh; }
        main {
            display: flex;
            flex-direction: column;
            min-height: 100dvh;
            width: 100%;
        }
        .content-wrapper {
            background: var(--content-bg, #1f1f1f);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            margin-top: 25px;
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }
        .table-responsive.js-dashboard-table {
            flex: 1;
            min-height: 0;
            overflow: auto;
        }
        .table-cursos {
            background: var(--table-bg, #1a1a1a);
        }
        .table-cursos th {
            background: var(--th-bg, #232323);
            color: #c0d3f7;
            text-align: center;
            font-size: 1rem;
        }
        .table-cursos td {
            text-align: center;
            vertical-align: middle;
        }
        .table-cursos tr:hover {
            background: var(--tr-hover, #e3f2fd1a);
        }
        .btn-centralizador {
            background: #4682B4;
            color: #fff;
            border: none;
            font-weight: 600;
            border-radius: 5px;
            transition: background 0.2s, transform 0.2s;
        }
        .btn-centralizador:hover {
            background: #0099e6;
            color: #fff;
            transform: scale(1.05);
        }
        .btn-boletin {
            background: #198754;
            color: #fff;
            border: none;
            font-weight: 600;
            border-radius: 5px;
            transition: background 0.2s, transform 0.2s;
        }
        .btn-boletin:hover {
            background: #157347;
            color: #fff;
            transform: scale(1.05);
        }
        .title-box {
            border-left: 6px solid #4682B4;
            padding-left: 1rem;
            margin-bottom: 2rem;
        }
        .toggle-switch {
            display: flex; align-items: center; gap:7px;
            position: absolute; right: 32px; top: 32px;
        }
        .toggle-switch label {
            font-size: .95rem; font-weight: 600; color: #4682B4; cursor:pointer;
        }
        .toggle-switch input[type="checkbox"] {
            width: 28px; height: 16px; position: relative; appearance: none;
            background: #aaa; outline: none; border-radius: 20px; transition: background 0.2s;
        }
        .toggle-switch input[type="checkbox"]:checked { background: #4682B4; }
        .toggle-switch input[type="checkbox"]::after {
            content: '';
            position: absolute; top: 2px; left: 2px; width: 12px; height: 12px;
            background: #fff; border-radius: 50%; transition: left 0.2s;
        }
        .toggle-switch input[type="checkbox"]:checked::after { left: 14px; }

        /* ---- LIGHT MODE ---- */
        body:not(.dark-mode) {
            --content-bg: #fff;
            --table-bg: #f7fbff;
            --th-bg: #eaf6fb;
            --tr-hover: #e3f2fd;
        }
        body:not(.dark-mode) .table-cursos th {
            color: #4682B4;
            background: var(--th-bg);
            border-bottom: 2px solid #b9d6f2;
        }
        body:not(.dark-mode) .btn-centralizador {
            background: #1877c9;
            color: #fff;
        }
        body:not(.dark-mode) .btn-centralizador:hover {
            background: #0056b3;
            color: #e3f2fd;
        }
        body:not(.dark-mode) .title-box {
            border-left: 6px solid #1877c9;
        }
        body:not(.dark-mode) .toggle-switch label { color: #1877c9; }
        
        /* Spinner de carga */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
        }
        .loading-overlay.active {
            display: flex;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #4682B4;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .loading-text {
            color: white;
            margin-top: 15px;
            font-size: 14px;
        }
        
        @media print {
            .btn-boletin, .btn-centralizador, .toggle-switch, .sidebar {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
        <div class="loading-text">Generando boletines, por favor espere...</div>
    </div>
    
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="w-100 px-md-4 position-relative">
                <!-- Toggle Modo Claro/Oscuro -->
                <div class="toggle-switch">
                    <label for="toggleMode">☀️/🌙</label>
                    <input type="checkbox" id="toggleMode" <?php if(isset($_COOKIE['darkmode']) && $_COOKIE['darkmode']=='on') echo "checked"; ?>>
                </div>
                <div class="content-wrapper">
                    <!-- Título Principal -->
                    <div class="title-box mb-4">
                        <h2 class="mb-0" style="color:#4682B4;">Cursos de Nivel Inicial</h2>
                        <small class="text-secondary">Seleccione el curso que desea visualizar:</small>
                    </div>

                    <!-- Tabla de Cursos -->
                    <div class="table-responsive js-dashboard-table">
                        <table class="table table-cursos table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">#</th>
                                    <th>Curso</th>
                                    <th style="width: 380px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cursos)): ?>
                                    <tr>
                                        <td colspan="3">
                                            <div class="alert alert-warning mb-0">
                                                No hay cursos de nivel inicial registrados.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $n = 1; foreach ($cursos as $curso): ?>
                                    <tr>
                                        <td><?php echo $n++; ?></td>
                                        <td><?php echo htmlspecialchars("{$curso['curso']} {$curso['paralelo']}"); ?></td>
                                        <td>
                                            <div class="d-flex gap-2 justify-content-center flex-wrap">
                                                <a href="ver_c_inicial.php?id=<?php echo $curso['id_curso']; ?>" class="btn btn-centralizador">
                                                    Ver Centralizador
                                                </a>
                                                <button type="button"
                                                        class="btn btn-boletin"
                                                        data-id-curso="<?php echo (int)$curso['id_curso']; ?>"
                                                        data-curso="<?php echo htmlspecialchars("{$curso['curso']} {$curso['paralelo']}", ENT_QUOTES, 'UTF-8'); ?>">
                                                    Generar Boletines PDF
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        const estudiantesPorCurso = <?php echo json_encode($estudiantesPorCurso, JSON_UNESCAPED_UNICODE); ?>;
        const evaluacionesPorCurso = <?php echo json_encode($evaluacionesPorCurso, JSON_UNESCAPED_UNICODE); ?>;
        const gestionActual = <?php echo json_encode($gestionActual, JSON_UNESCAPED_UNICODE); ?>;

        function mostrarLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function ocultarLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        function generarBoletinPDF(data) {
            mostrarLoading();
            
            setTimeout(() => {
                try {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF({ 
                        orientation: 'portrait', 
                        unit: 'pt', 
                        format: 'letter',
                        compress: true
                    });

                    const layout = {
                        pageWidth: 612,
                        pageHeight: 792,
                        halfHeight: 396,
                        marginX: 45,
                        headerTop: 30,
                        studentY: 90,
                        courseY: 112,
                        tableTop: 128,
                        tableHeaderH1: 22,
                        tableHeaderH2: 19,
                        bodyRowH: 154,
                        signatureLineY: 360,
                        signatureTextY: 374,
                        footerY: 388
                    };

                    const col = {
                        area: 220,
                        t1: 110,
                        t2: 110,
                        t3: 110
                    };

                    const tableX = layout.marginX;
                    const tableW = col.area + col.t1 + col.t2 + col.t3;
                    const xArea = tableX;
                    const xT1 = xArea + col.area;
                    const xT2 = xT1 + col.t1;
                    const xT3 = xT2 + col.t2;
                    const yHeader1 = layout.tableTop;
                    const yHeader2 = yHeader1 + layout.tableHeaderH1;
                    const yRows = yHeader2 + layout.tableHeaderH2;

                    // Es una sola area de evaluacion anual; no se divide en filas internas.
                    const camposSaberes = 'COMUNIDAD Y SOCIEDAD\nDESARROLLO DE LA COMUNICACIÓN, LENGUAJES Y ARTES (MÚSICA, ARTES PLÁSTICAS Y VISUALES, CIENCIAS SOCIALES-RECREACIÓN).\n\nCIENCIA TECNOLOGÍA Y PRODUCCIÓN\nDESARROLLO DEL CONOCIMIENTO Y DE LA PRODUCCIÓN (MATEMATICA- TÉCNICA TECNOLÓGICA).\n\nVIDA TIERRA TERRITORIO\nDESARROLLOBIOSICOMOTRIZ (CIENCIAS NATURALES).\n\nCOSMOS Y PENSAMIENTO\nDESARROLLOSOCIOCULTURAL, AFECTIVO Y ESPIRITUAL.';

                    const estudiantes = (data.estudiantes && data.estudiantes.length)
                        ? data.estudiantes
                        : [{ id_estudiante: 0, nombre: 'Sin estudiantes registrados' }];

                    estudiantes.forEach((estudiante, index) => {
                        const nombreEstudiante = estudiante?.nombre || '';
                        const idEstudiante = Number(estudiante?.id_estudiante || 0);
                        const slot = index % 2;
                        const yOffset = slot * layout.halfHeight;
                        
                        if (index > 0 && slot === 0) {
                            doc.addPage('letter', 'portrait');
                        }

                        // Configuración blanco y negro para impresión
                        doc.setDrawColor(0, 0, 0);
                        doc.setFillColor(255, 255, 255);
                        doc.setLineWidth(0.75);

                        if (slot === 0 && index > 0) {
                            doc.setLineWidth(0.3);
                            doc.line(layout.marginX, layout.halfHeight, layout.pageWidth - layout.marginX, layout.halfHeight);
                            doc.setLineWidth(0.5);
                        }

                        // Encabezado del boletín sin recuadro, solo texto centrado.
                        doc.setFont('times', 'bold');
                        doc.setFontSize(13);
                        drawCenteredText(doc, 'UNIDAD EDUCATIVA SIMÓN BOLÍVAR', yOffset + layout.headerTop + 5, 13, 'bold');
                        doc.setFontSize(15);
                        drawCenteredText(doc, 'BOLETÍN INFORMATIVO', yOffset + layout.headerTop + 22, 15, 'bold');
                        doc.setFontSize(8);
                        drawCenteredText(doc, 'Educación Inicial en Familia Comunitaria', yOffset + layout.headerTop + 35, 8, 'normal');

                        // Información del estudiante
                        doc.setFont('times', 'bold');
                        doc.setFontSize(9);
                        doc.text('ESTUDIANTE:', layout.marginX, yOffset + layout.studentY);
                        doc.setFont('times', 'normal');
                        doc.text(nombreEstudiante || '', layout.marginX + 80, yOffset + layout.studentY);
                        doc.line(layout.marginX + 78, yOffset + layout.studentY + 2, layout.pageWidth - layout.marginX, yOffset + layout.studentY + 2);

                        doc.setFont('times', 'bold');
                        doc.text('CURSO:', layout.marginX, yOffset + layout.courseY);
                        doc.setFont('times', 'normal');
                        doc.text(data.curso || 'No especificado', layout.marginX + 50, yOffset + layout.courseY);
                        
                        doc.setFont('times', 'bold');
                        doc.text('GESTIÓN:', layout.pageWidth - layout.marginX - 85, yOffset + layout.courseY);
                        doc.setFont('times', 'normal');
                        doc.text(data.gestion || '2026', layout.pageWidth - layout.marginX - 25, yOffset + layout.courseY, { align: 'right' });

                        // Tabla principal
                        const y1 = yOffset + yHeader1;
                        const y2 = yOffset + yHeader2;
                        const yBody = yOffset + yRows;
                        const tableHeight = layout.tableHeaderH1 + layout.tableHeaderH2 + layout.bodyRowH;
                        
                        // Bordes de la tabla con jerarquia visual clara.
                        doc.setLineWidth(0.75);
                        doc.rect(tableX, y1, tableW, tableHeight);
                        doc.line(xT1, y1, xT1, y1 + tableHeight);
                        doc.line(tableX, y2, tableX + tableW, y2);
                        doc.line(tableX, yBody, tableX + tableW, yBody);
                        doc.setLineWidth(0.45);
                        doc.line(xT2, y2, xT2, y1 + tableHeight);
                        doc.line(xT3, y2, xT3, y1 + tableHeight);
                        doc.setLineWidth(0.75);

                        // Encabezados principales
                        doc.setFillColor(230, 230, 230);
                        doc.rect(tableX, y1, tableW, layout.tableHeaderH1, 'F');
                        
                        doc.setFont('times', 'bold');
                        doc.setFontSize(8.2);
                        drawCenteredTextInRect(doc, 'CAMPOS DE SABERES\nY CONOCIMIENTOS', xArea, y1, col.area, layout.tableHeaderH1, 8.2, 8.2);
                        drawCenteredTextInRect(doc, 'VALORACIÓN CUALITATIVA', xT1, y1, col.t1 + col.t2 + col.t3, layout.tableHeaderH1, 9, 9);

                        // Encabezados de trimestres
                        doc.setFillColor(245, 245, 245);
                        doc.rect(xT1, y2, col.t1 + col.t2 + col.t3, layout.tableHeaderH2, 'F');
                        
                        doc.setFontSize(8.3);
                        drawCenteredTextInRect(doc, '1er TRIMESTRE', xT1, y2, col.t1, layout.tableHeaderH2, 8.3, 8.3);
                        drawCenteredTextInRect(doc, '2do TRIMESTRE', xT2, y2, col.t2, layout.tableHeaderH2, 8.3, 8.3);
                        drawCenteredTextInRect(doc, '3er TRIMESTRE', xT3, y2, col.t3, layout.tableHeaderH2, 8.3, 8.3);

                        // Contenido de los campos de saberes
                        const yContenido = yBody;
                        const yContenidoMax = yContenido + layout.bodyRowH - 8;
                        doc.setFont('times', 'bold');
                        doc.setFontSize(8);
                        const campoLines = camposSaberes.split('\n').flatMap(line => doc.splitTextToSize(line, col.area - 18));
                        const campoLineHeight = 10;
                        const campoBlockHeight = campoLines.length * campoLineHeight;
                        const campoStartY = yContenido + ((layout.bodyRowH - campoBlockHeight) / 2) + campoLineHeight - 2;
                        doc.text(campoLines, xArea + (col.area / 2), campoStartY, { align: 'center' });

                        // Valoraciones por trimestre
                        const valTrim = (data.valoraciones && data.valoraciones[idEstudiante]) ? data.valoraciones[idEstudiante] : {};
                        const zonas = [
                            { x: xT1 + 8, w: col.t1 - 16, t: String(valTrim[1] || '').trim(), columna: 1 },
                            { x: xT2 + 8, w: col.t2 - 16, t: String(valTrim[2] || '').trim(), columna: 2 },
                            { x: xT3 + 8, w: col.t3 - 16, t: String(valTrim[3] || '').trim(), columna: 3 }
                        ];
                        
                        zonas.forEach(z => {
                            const yContenidoStart = yBody;
                            const contenidoHeight = layout.bodyRowH;
                            
                            if (z.t) {
                                doc.setFont('times', 'normal');
                                doc.setFontSize(7.3);
                                const lines = doc.splitTextToSize(z.t, z.w);
                                const lineHeight = 8.2;
                                const maxLines = Math.min(lines.length, Math.floor((contenidoHeight - 18) / lineHeight));
                                const linesToDraw = lines.slice(0, maxLines);
                                const textHeight = linesToDraw.length * lineHeight;
                                const startY = yContenidoStart + ((contenidoHeight - textHeight) / 2) + lineHeight - 2;
                                
                                linesToDraw.forEach((line, idx) => {
                                    const lineY = startY + (idx * lineHeight);
                                    if (lineY < yContenidoStart + contenidoHeight - 10) {
                                        doc.text(line, z.x + (z.w / 2), lineY, { align: 'center' });
                                    }
                                });
                            }
                        });

                        // Líneas de firma
                        const lineLen = 170;
                        const firmaY = yOffset + layout.signatureLineY;
                        const direccionY = yOffset + layout.signatureLineY;
                        
                        doc.setLineWidth(0.5);
                        doc.line(layout.marginX + 25, firmaY, layout.marginX + 25 + lineLen, firmaY);
                        doc.line(layout.pageWidth - layout.marginX - 25 - lineLen, direccionY, layout.pageWidth - layout.marginX - 25, direccionY);
                        
                        doc.setFontSize(7.5);
                        doc.setFont('times', 'normal');
                        doc.text('FIRMA DEL MAESTRO/A', layout.marginX + 25 + (lineLen / 2), yOffset + layout.signatureTextY, { align: 'center' });
                        doc.text('DIRECCIÓN', layout.pageWidth - layout.marginX - 25 - (lineLen / 2), yOffset + layout.signatureTextY, { align: 'center' });
                        
                    });

                    const safeCurso = (data.curso || 'curso').replace(/[^a-z0-9-_]+/gi, '_');
                    doc.save(`boletines_inicial_${safeCurso}.pdf`);
                    ocultarLoading();
                } catch (error) {
                    console.error('Error al generar PDF:', error);
                    ocultarLoading();
                    alert('Ocurrió un error al generar el PDF. Por favor, intente nuevamente.');
                }
            }, 100);
        }

        function drawCenteredText(doc, text, y, size, style = 'normal') {
            doc.setFont('times', style);
            doc.setFontSize(size);
            doc.text(text, doc.internal.pageSize.getWidth() / 2, y, { align: 'center' });
        }

        function drawCenteredTextInRect(doc, text, x, y, width, height, fontSize = 8.5, lineHeight = 9) {
            const lines = text.split('\n');
            const totalHeight = lines.length * lineHeight;
            const startY = y + (height - totalHeight) / 2 + lineHeight - 1;
            
            doc.setFont('times', 'bold');
            doc.setFontSize(fontSize);
            lines.forEach((line, idx) => {
                doc.text(line, x + (width / 2), startY + (idx * lineHeight), { align: 'center' });
            });
        }

        // Modo claro/oscuro con persistencia en cookie
        const toggle = document.getElementById('toggleMode');
        const tableViewport = document.querySelector('.table-responsive.js-dashboard-table');

        document.querySelectorAll('.btn-boletin').forEach(btn => {
            btn.addEventListener('click', function() {
                const idCurso = Number(this.dataset.idCurso || 0);
                const cursoNombre = this.dataset.curso || '';
                const lista = Array.isArray(estudiantesPorCurso[idCurso]) ? estudiantesPorCurso[idCurso] : [];
                const valoraciones = evaluacionesPorCurso[idCurso] || {};
                generarBoletinPDF({
                    curso: cursoNombre,
                    gestion: gestionActual,
                    estudiantes: lista,
                    valoraciones
                });
            });
        });

        function ajustarAltoTabla() {
            if (!tableViewport) return;
            const top = tableViewport.getBoundingClientRect().top;
            const alto = Math.max(260, Math.floor(window.innerHeight - top - 12));
            tableViewport.style.height = alto + 'px';
            tableViewport.style.maxHeight = alto + 'px';
        }
        
        function setMode(dark) {
            if(dark) {
                document.body.classList.add('dark-mode');
                document.cookie = "darkmode=on;path=/;max-age=31536000";
            } else {
                document.body.classList.remove('dark-mode');
                document.cookie = "darkmode=off;path=/;max-age=31536000";
            }
        }
        
        toggle.addEventListener('change', function() {
            setMode(this.checked);
            requestAnimationFrame(ajustarAltoTabla);
        });
        
        // Estado inicial al cargar
        window.onload = function() {
            if(document.cookie.indexOf('darkmode=on')!==-1) {
                document.body.classList.add('dark-mode');
                toggle.checked = true;
            }
            ajustarAltoTabla();
        }
        
        window.addEventListener('resize', ajustarAltoTabla);
        window.addEventListener('orientationchange', ajustarAltoTabla);
    </script>
</body>
</html>
