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

$logoBoletinBase64 = '';
$boletinPrimariaPath = __DIR__ . '/boletin_primaria.php';
if (is_file($boletinPrimariaPath)) {
    $contenidoPrimaria = @file_get_contents($boletinPrimariaPath);
    if ($contenidoPrimaria !== false && $contenidoPrimaria !== '') {
        if (preg_match('/const\s+LOGO_BASE64\s*=\s*"([^"]+)"\s*;/', $contenidoPrimaria, $coincidenciaLogo)) {
            $logoBoletinBase64 = $coincidenciaLogo[1];
        }
    }
}



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
                        <div class="mt-3">
                            <button type="button" id="btnDescargarZipInicial" class="btn btn-danger btn-sm">
                                <i class="ri-file-zip-line"></i> Descargar ZIP Boletines Inicial
                            </button>
                        </div>
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
        const LOGO_BASE64 = <?php echo json_encode($logoBoletinBase64, JSON_UNESCAPED_UNICODE); ?>;

        function mostrarLoading() {
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function ocultarLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        function generarBoletinPDF(data, options = {}) {
            mostrarLoading();

            return new Promise((resolve, reject) => {
                setTimeout(() => {
                    try {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF({
                        orientation: 'portrait',
                        unit: 'pt',
                        format: 'letter',
                        compress: true
                    });

                    // ── LAYOUT ────────────────────────────────────────────────
                    const layout = {
                        pageWidth:      612,
                        pageHeight:     792,
                        halfHeight:     396,
                        marginX:        36,
                        // encabezado
                        headerLineY:    22,   // línea decorativa superior
                        headerTitleY:   40,   // "UNIDAD EDUCATIVA..."
                        headerSubY:     56,   // "BOLETÍN INFORMATIVO"
                        headerSubtitleY:70,   // "Educación Inicial..."
                        headerLineY2:   78,   // línea decorativa inferior
                        // datos del estudiante
                        studentY:       94,
                        courseY:        110,
                        dateY:          124,
                        // tabla
                        tableTop:       122,
                        tableHeaderH1:  28,
                        tableHeaderH2:  20,
                        bodyRowH:       148,
                        // firma
                        signatureLineY: 356,
                        signatureNameY: 368,
                        signatureRoleY: 378,
                    };

                    const col = { area: 228, t1: 108, t2: 108, t3: 108 };

                    const tableX  = layout.marginX;
                    const tableW  = col.area + col.t1 + col.t2 + col.t3;
                    const xArea   = tableX;
                    const xT1     = xArea + col.area;
                    const xT2     = xT1 + col.t1;
                    const xT3     = xT2 + col.t2;
                    const yHeader1 = layout.tableTop;
                    const yHeader2 = yHeader1 + layout.tableHeaderH1;
                    const yRows    = yHeader2 + layout.tableHeaderH2;

                    // Campos de saberes: [{ titulo, descripcion }]
                    const camposData = [
                        {
                            titulo: 'COMUNIDAD Y SOCIEDAD',
                            desc:   'Desarrollo de la Comunicación, Lenguajes y Artes (Música, Artes Plásticas y Visuales, Ciencias Sociales - Recreación).'
                        },
                        {
                            titulo: 'CIENCIA TECNOLOGÍA Y PRODUCCIÓN',
                            desc:   'Desarrollo del Conocimiento y de la Producción (Matemática - Técnica Tecnológica).'
                        },
                        {
                            titulo: 'VIDA TIERRA TERRITORIO',
                            desc:   'Desarrollo Biopsicomotriz (Ciencias Naturales).'
                        },
                        {
                            titulo: 'COSMOS Y PENSAMIENTO',
                            desc:   'Desarrollo Sociocultural, Afectivo y Espiritual.'
                        }
                    ];

                    const estudiantes = (data.estudiantes && data.estudiantes.length)
                        ? data.estudiantes
                        : [{ id_estudiante: 0, nombre: 'Sin estudiantes registrados' }];

                    let totalPages = 0;

                    estudiantes.forEach((estudiante, index) => {
                        const nombreEstudiante = estudiante?.nombre || '';
                        const idEstudiante     = Number(estudiante?.id_estudiante || 0);
                        const slot    = index % 2;
                        const yOffset = slot * layout.halfHeight;

                        if (index > 0 && slot === 0) {
                            doc.addPage('letter', 'portrait');
                            totalPages++;
                        } else if (index === 0) {
                            totalPages = 1;
                        }

                        doc.setDrawColor(0, 0, 0);
                        doc.setFillColor(255, 255, 255);

                        // ── ENCABEZADO ────────────────────────────────────────
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('times', 'bold');
                        doc.setFontSize(12);
                        drawCenteredText(doc, 'UNIDAD EDUCATIVA SIMÓN BOLÍVAR', yOffset + layout.headerTitleY, 12, 'bold');

                        doc.setFontSize(15);
                        drawCenteredText(doc, 'BOLETÍN INFORMATIVO', yOffset + layout.headerSubY, 15, 'bold');

                        doc.setFont('times', 'italic');
                        doc.setFontSize(8.5);
                        doc.setTextColor(60, 60, 60);
                        drawCenteredText(doc, 'Educación Inicial en Familia Comunitaria', yOffset + layout.headerSubtitleY, 8.5, 'italic');

                        if (LOGO_BASE64) {
                            doc.addImage(LOGO_BASE64, 'PNG', layout.pageWidth - layout.marginX - 58, yOffset + 22, 58, 58);
                        }

                        // ── DATOS DEL ESTUDIANTE ──────────────────────────────
                        doc.setTextColor(0, 0, 0);
                        doc.setLineWidth(0.75);

                        // Fila: ESTUDIANTE (ancho completo)
                        drawDataRow(doc, layout, yOffset + layout.studentY,
                            [
                                { label: 'ESTUDIANTE:', value: nombreEstudiante, labelW: 72 }
                            ]
                        );

                        // Fila: CURSO + GESTIÓN
                        drawDataRow(doc, layout, yOffset + layout.courseY,
                            [
                                { label: 'CURSO:', value: data.curso || 'No especificado', labelW: 44 },
                                { label: 'GESTIÓN:', value: String(data.gestion || '2026'), labelW: 55, rightAlign: true }
                            ]
                        );

                        // ── TABLA ─────────────────────────────────────────────
                        const y1 = yOffset + yHeader1;
                        const y2 = yOffset + yHeader2;
                        const yBody = yOffset + yRows;
                        const tableHeight = layout.tableHeaderH1 + layout.tableHeaderH2 + layout.bodyRowH;

                        // Borde exterior grueso
                        doc.setDrawColor(0, 0, 0);
                        doc.setLineWidth(1.2);
                        doc.rect(tableX, y1, tableW, tableHeight);

                        // Divisor vertical principal (área | valoraciones)
                        doc.setLineWidth(1.2);
                        doc.line(xT1, y1, xT1, y1 + tableHeight);

                        // Divisores verticales de trimestres (solo desde h2 hacia abajo)
                        doc.setLineWidth(0.5);
                        doc.line(xT2, y2, xT2, y1 + tableHeight);
                        doc.line(xT3, y2, xT3, y1 + tableHeight);

                        // Línea entre h1 y h2
                        doc.setLineWidth(0.8);
                        doc.line(xT1, y2, xArea + tableW, y2);

                        // Encabezado principal (h1) — fondo gris oscuro
                        doc.setFillColor(210, 210, 210);
                        doc.rect(tableX, y1, tableW, layout.tableHeaderH1, 'F');
                        doc.setTextColor(0, 0, 0);
                        doc.setFont('times', 'bold');
                        doc.setFontSize(8);
                        drawCenteredTextInRect(doc, 'CAMPOS DE SABERES\nY CONOCIMIENTOS', xArea, y1, col.area, layout.tableHeaderH1, 8, 9.5);
                        drawCenteredTextInRect(doc, 'VALORACIÓN CUALITATIVA', xT1, y1, col.t1 + col.t2 + col.t3, layout.tableHeaderH1, 9, 10);

                        // Encabezado trimestres (h2) — fondo gris claro
                        doc.setFillColor(235, 235, 235);
                        doc.rect(xT1, y2, col.t1 + col.t2 + col.t3, layout.tableHeaderH2, 'F');
                        doc.setTextColor(0, 0, 0);
                        doc.setFontSize(8);
                        drawCenteredTextInRect(doc, '1er TRIMESTRE', xT1, y2, col.t1, layout.tableHeaderH2, 8, 9);
                        drawCenteredTextInRect(doc, '2do TRIMESTRE', xT2, y2, col.t2, layout.tableHeaderH2, 8, 9);
                        drawCenteredTextInRect(doc, '3er TRIMESTRE', xT3, y2, col.t3, layout.tableHeaderH2, 8, 9);

                        // ── CUERPO: campos de saberes ─────────────────────────
                        // Renderizar título (bold) + descripción (normal) en columna izquierda
                        const padL   = 8;
                        const colW   = col.area - padL - 4;
                        const lhBold = 9.5;
                        const lhNorm = 8.5;
                        const gap    = 4;   // espacio entre campos

                        // Pre-calcular líneas de cada campo para centrado vertical
                        const campoBlocks = camposData.map(c => {
                            doc.setFont('times', 'bold');
                            doc.setFontSize(7.5);
                            const tLines = doc.splitTextToSize(c.titulo, colW);
                            doc.setFont('times', 'normal');
                            doc.setFontSize(7);
                            const dLines = doc.splitTextToSize(c.desc, colW);
                            return { tLines, dLines };
                        });

                        const totalTextH = campoBlocks.reduce((sum, b) =>
                            sum + b.tLines.length * lhBold + b.dLines.length * lhNorm + gap, 0) - gap;

                        let cy = yBody + (layout.bodyRowH - totalTextH) / 2 + lhBold - 2;

                        campoBlocks.forEach(b => {
                            doc.setFont('times', 'bold');
                            doc.setFontSize(7.5);
                            doc.setTextColor(0, 0, 0);
                            b.tLines.forEach(line => {
                                doc.text(line, xArea + padL, cy);
                                cy += lhBold;
                            });
                            doc.setFont('times', 'normal');
                            doc.setFontSize(7);
                            doc.setTextColor(50, 50, 50);
                            b.dLines.forEach(line => {
                                doc.text(line, xArea + padL, cy);
                                cy += lhNorm;
                            });
                            doc.setTextColor(0, 0, 0);
                            cy += gap;
                        });

                        // ── CUERPO: valoraciones por trimestre ────────────────
                        const valTrim = (data.valoraciones && data.valoraciones[idEstudiante])
                            ? data.valoraciones[idEstudiante] : {};
                        const zonas = [
                            { x: xT1, w: col.t1, t: String(valTrim[1] || '').trim() },
                            { x: xT2, w: col.t2, t: String(valTrim[2] || '').trim() },
                            { x: xT3, w: col.t3, t: String(valTrim[3] || '').trim() }
                        ];

                        zonas.forEach(z => {
                            const padH = 10;
                            const innerW = z.w - padH * 2;
                            const centerX = z.x + z.w / 2;

                            if (z.t) {
                                doc.setFont('times', 'normal');
                                doc.setFontSize(7.5);
                                doc.setTextColor(0, 0, 0);
                                const lines = doc.splitTextToSize(z.t, innerW);
                                const lh    = 9;
                                const maxL  = Math.floor((layout.bodyRowH - 14) / lh);
                                const drawn = lines.slice(0, maxL);
                                const blockH = drawn.length * lh;
                                let ty = yBody + (layout.bodyRowH - blockH) / 2 + lh - 2;
                                drawn.forEach(line => {
                                    doc.text(line, centerX, ty, { align: 'center' });
                                    ty += lh;
                                });
                            } else {
                                // Sin valoración: guión largo centrado, discreto
                                doc.setFont('times', 'normal');
                                doc.setFontSize(9);
                                doc.setTextColor(180, 180, 180);
                                doc.text('\u2014', centerX, yBody + layout.bodyRowH / 2, { align: 'center' });
                                doc.setTextColor(0, 0, 0);
                            }
                        });

                        // ── FIRMAS ────────────────────────────────────────────
                        const lineLen  = 165;
                        const firmaY   = yOffset + layout.signatureLineY;
                        const nameY    = yOffset + layout.signatureNameY;
                        const roleY    = yOffset + layout.signatureRoleY;
                        const firmaLX  = layout.marginX + 30;
                        const dirLX    = layout.pageWidth - layout.marginX - 30 - lineLen;

                        doc.setDrawColor(0, 0, 0);
                        doc.setLineWidth(0.6);
                        doc.line(firmaLX, firmaY, firmaLX + lineLen, firmaY);
                        doc.line(dirLX,   firmaY, dirLX + lineLen,   firmaY);

                        doc.setFont('times', 'bold');
                        doc.setFontSize(7.5);
                        doc.setTextColor(0, 0, 0);
                        doc.text('FIRMA DEL MAESTRO/A', firmaLX + lineLen / 2, nameY, { align: 'center' });
                        doc.text('DIRECCIÓN', dirLX + lineLen / 2, nameY, { align: 'center' });

                        doc.setFont('times', 'normal');
                        doc.setFontSize(7);
                        doc.setTextColor(80, 80, 80);
                        doc.text('Docente de Nivel Inicial', firmaLX + lineLen / 2, roleY, { align: 'center' });
                        doc.text('Director/a de la Unidad Educativa', dirLX + lineLen / 2, roleY, { align: 'center' });
                        doc.setTextColor(0, 0, 0);
                    });

                    const safeCurso = (data.curso || 'curso').replace(/[^a-z0-9-_]+/gi, '_');
                    if (options && options.download === false) {
                        ocultarLoading();
                        resolve(doc.output('blob'));
                        return;
                    }

                    doc.save(`boletines_inicial_${safeCurso}.pdf`);
                    ocultarLoading();
                    resolve(null);
                } catch (error) {
                    console.error('Error al generar PDF:', error);
                    ocultarLoading();
                    alert('Ocurrió un error al generar el PDF. Por favor, intente nuevamente.');
                    reject(error);
                }
                }, 100);
            });
        }

        /** Texto centrado en la página */
        function drawCenteredText(doc, text, y, size, style = 'normal') {
            doc.setFont('times', style);
            doc.setFontSize(size);
            doc.text(text, doc.internal.pageSize.getWidth() / 2, y, { align: 'center' });
        }

        /** Texto centrado dentro de un rectángulo (soporta \n) */
        function drawCenteredTextInRect(doc, text, x, y, width, height, fontSize = 8.5, lineHeight = 9) {
            const lines = text.split('\n');
            const totalH = lines.length * lineHeight;
            const startY = y + (height - totalH) / 2 + lineHeight - 1;
            doc.setFont('times', 'bold');
            doc.setFontSize(fontSize);
            lines.forEach((line, idx) => {
                doc.text(line, x + width / 2, startY + idx * lineHeight, { align: 'center' });
            });
        }

        /**
         * Dibuja una fila de datos (label + valor) con soporte para múltiples pares en la misma línea.
         * items: [{ label, value, labelW, rightAlign? }]
         */
        function drawDataRow(doc, layout, y, items) {
            doc.setFontSize(9);
            // Si hay un ítem con rightAlign, lo posicionamos desde la derecha
            const normal = items.filter(i => !i.rightAlign);
            const right  = items.filter(i =>  i.rightAlign);

            normal.forEach(item => {
                const x = layout.marginX;
                doc.setFont('times', 'bold');
                doc.text(item.label, x, y);
                doc.setFont('times', 'normal');
                doc.text(item.value, x + item.labelW, y);
            });

            right.forEach(item => {
                const valX = layout.pageWidth - layout.marginX;
                doc.setFont('times', 'normal');
                doc.text(item.value, valX, y, { align: 'right' });
                const valW = doc.getTextWidth(item.value);
                doc.setFont('times', 'bold');
                doc.text(item.label, valX - valW - 4, y, { align: 'right' });
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

        const cursosZipInicial = <?php echo json_encode(array_map(static function ($c) {
            return [
                'id_curso' => (int)$c['id_curso'],
                'curso' => (string)($c['curso'] ?? ''),
                'paralelo' => (string)($c['paralelo'] ?? '')
            ];
        }, $cursos), JSON_UNESCAPED_UNICODE); ?>;

        function sanitizarNombreArchivo(nombre) {
            return String(nombre || 'archivo').replace(/[^a-z0-9-_ ]/gi, '_').replace(/\s+/g, '_');
        }

        async function descargarZipBoletinesInicial() {
            if (typeof JSZip === 'undefined') {
                alert('No se pudo cargar la librería para generar el ZIP.');
                return;
            }

            if (!Array.isArray(cursosZipInicial) || cursosZipInicial.length === 0) {
                alert('No hay cursos de inicial para exportar.');
                return;
            }

            const btn = document.getElementById('btnDescargarZipInicial');
            const textoOriginal = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Generando ZIP...';
            }

            try {
                const zip = new JSZip();
                for (const curso of cursosZipInicial) {
                    const idCurso = Number(curso.id_curso || 0);
                    const lista = Array.isArray(estudiantesPorCurso[idCurso]) ? estudiantesPorCurso[idCurso] : [];
                    const valoraciones = evaluacionesPorCurso[idCurso] || {};
                    const blob = await generarBoletinPDF({
                        curso: `${curso.curso} ${curso.paralelo}`.trim(),
                        gestion: gestionActual,
                        estudiantes: lista,
                        valoraciones
                    }, { download: false });
                    const nombreCurso = sanitizarNombreArchivo(`${curso.curso}_${curso.paralelo}`);
                    zip.file(`${nombreCurso}.pdf`, blob);
                }

                const zipBlob = await zip.generateAsync({ type: 'blob' });
                const enlace = document.createElement('a');
                const url = URL.createObjectURL(zipBlob);
                enlace.href = url;
                enlace.download = `Boletines_Inicial_${new Date().getFullYear()}.zip`;
                document.body.appendChild(enlace);
                enlace.click();
                enlace.remove();
                URL.revokeObjectURL(url);
            } catch (error) {
                alert(`Error al generar ZIP de inicial: ${error.message}`);
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = textoOriginal;
                }
            }
        }

        document.getElementById('btnDescargarZipInicial')?.addEventListener('click', descargarZipBoletinesInicial);

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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</body>
</html>
