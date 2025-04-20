<?php
session_start();
require_once '../config/database.php';

// Verificar que sea administrador
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 1) {
    header('Location: ../index.php');
    exit();
}

$conn = (new Database())->connect();

// Obtener todos los cursos de primaria
$stmt = $conn->query("
    SELECT c.id_curso, c.curso, c.paralelo 
    FROM cursos c
    WHERE c.nivel = 'Primaria'
    ORDER BY c.curso, c.paralelo
");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Cursos de Primaria</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link id="bootstrap-css" rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        body {
            background-color: #181a1b;
            color: #eaeaea;
        }

        .content-wrapper {
            background: var(--content-bg, #1f1f1f);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            margin-top: 25px;
        }

        .table-cursos {
            background: var(--table-bg, #1a1a1a);
        }

        .table-cursos th {
            background: var(--th-bg, #232323);
            color: #4682B4;
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

        .title-box {
            border-left: 6px solid #4682B4;
            padding-left: 1rem;
            margin-bottom: 2rem;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 7px;
            position: absolute;
            right: 32px;
            top: 32px;
        }

        .toggle-switch label {
            font-size: .95rem;
            font-weight: 600;
            color: #4682B4;
            cursor: pointer;
        }

        .toggle-switch input[type="checkbox"] {
            width: 28px;
            height: 16px;
            position: relative;
            appearance: none;
            background: #aaa;
            outline: none;
            border-radius: 20px;
            transition: background 0.2s;
        }

        .toggle-switch input[type="checkbox"]:checked {
            background: #4682B4;
        }

        .toggle-switch input[type="checkbox"]::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 12px;
            height: 12px;
            background: #fff;
            border-radius: 50%;
            transition: left 0.2s;
        }

        .toggle-switch input[type="checkbox"]:checked::after {
            left: 14px;
        }

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

        body:not(.dark-mode) .toggle-switch label {
            color: #1877c9;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row position-relative">
            <?php include '../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 position-relative">

                <!-- Toggle Modo Claro/Oscuro -->
                <div class="toggle-switch">

                    <label for="toggleMode">☀️/🌙</label>
                    <input type="checkbox" id="toggleMode" <?php if (isset($_COOKIE['darkmode']) && $_COOKIE['darkmode'] == 'on') echo "checked"; ?>>
                </div>
                <button onclick="generateAllCentralizadores()" class="btn btn-primary">
                    <i class="bi bi-file-earmark-pdf"></i> Exportar Centralizadores
                </button>
                <div class="content-wrapper">
                    <!-- Título Principal -->
                    <div class="title-box mb-4">
                        <h2 class="mb-0" style="color:#4682B4;">Cursos de Primaria</h2>
                        <small class="text-secondary">Seleccione el curso que desea visualizar:</small>
                    </div>

                    <!-- Tabla de Cursos -->
                    <div class="table-responsive">
                        <table class="table table-cursos table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">#</th>
                                    <th>Curso</th>
                                    <th>Centralizador</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cursos)): ?>
                                    <tr>
                                        <td colspan="3">
                                            <div class="alert alert-warning mb-0">
                                                No hay cursos de primaria registrados.
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $n = 1;
                                    foreach ($cursos as $curso): ?>
                                        <tr>
                                            <td><?php echo $n++; ?></td>
                                            <td><?php echo htmlspecialchars("{$curso['curso']} {$curso['paralelo']}"); ?></td>
                                            <td>
                                                <a href="ver_curso.php?id=<?php echo $curso['id_curso']; ?>" class="btn btn-centralizador">
                                                    Ver Centralizador
                                                </a>
                                                <a href="boletin_primaria.php?id_curso=<?= $curso['id_curso'] ?>"
                                                    class="btn btn-success btn-action">
                                                    <i class="ri-printer-line"></i> Boletín
                                                </a>
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
        // Modo claro/oscuro con persistencia en cookie
        const toggle = document.getElementById('toggleMode');

        function setMode(dark) {
            if (dark) {
                document.body.classList.add('dark-mode');
                document.cookie = "darkmode=on;path=/;max-age=31536000";
            } else {
                document.body.classList.remove('dark-mode');
                document.cookie = "darkmode=off;path=/;max-age=31536000";
            }
        }
        toggle.addEventListener('change', function() {
            setMode(this.checked);
        });
        // Estado inicial al cargar
        window.onload = function() {
            if (document.cookie.indexOf('darkmode=on') !== -1) {
                document.body.classList.add('dark-mode');
                toggle.checked = true;
            }
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        async function generateAllCentralizadores() {
            const processingMsg = document.createElement('div');
            processingMsg.innerHTML = '<div class="alert alert-info position-fixed top-50 start-50 translate-middle p-4" style="z-index:9999"><div class="spinner-border me-2" role="status"></div> Generando centralizadores...</div>';
            document.body.appendChild(processingMsg);

            try {
                // CAMBIO 1: formato y ancho de página legal landscape
                const pdf = new jspdf.jsPDF({
                    orientation: 'landscape',
                    unit: 'mm',
                    format: 'legal'
                });

                // Tamaño legal landscape: 355.6mm x 215.9mm
                const legalWidth = pdf.internal.pageSize.getWidth(); // ≈355.6
                const legalHeight = pdf.internal.pageSize.getHeight(); // ≈215.9

                const cursos = <?= json_encode($cursos) ?>;
                let isFirstPage = true;

                for (let i = 0; i < cursos.length; i++) {
                    processingMsg.innerHTML = `<div class="alert alert-info position-fixed top-50 start-50 translate-middle p-4" style="z-index:9999">
                <div class="spinner-border me-2" role="status"></div> Procesando centralizador ${i + 1} de ${cursos.length}</div>`;

                    const curso = cursos[i];

                    // Iframe oculto
                    const iframe = document.createElement('iframe');
                    iframe.style.position = 'fixed';
                    iframe.style.left = '-1800px';
                    iframe.style.top = '0';
                    iframe.style.width = '1540px'; // más ancho para legal
                    iframe.style.height = '1200px';
                    iframe.src = `ver_curso.php?id=${curso.id_curso}`;
                    document.body.appendChild(iframe);

                    await new Promise(resolve => iframe.onload = resolve);
                    await new Promise(resolve => setTimeout(resolve, 900));

                    const tableContainer = iframe.contentDocument.querySelector('.table-responsive');
                    const courseTitle = `Curso: ${curso.curso} | Paralelo: "${curso.paralelo}"`;

                    // Contenedor de PDF, más ancho
                    const pdfContent = document.createElement('div');
                    pdfContent.style.background = '#fff';
                    pdfContent.style.padding = '5px 8px 4px 8px';
                    pdfContent.style.width = '1500px';
                    pdfContent.style.boxSizing = 'border-box';

                    pdfContent.innerHTML = `
                <div style="text-align:center;margin-bottom:4px;font-family:Arial,sans-serif;">
                    <div style="font-size:12pt; font-weight:bold; color:#113366;">U.E. SIMÓN BOLÍVAR</div>
                    <div style="font-size:10pt; font-weight:600; color:#003366;">CENTRALIZADOR DE NOTAS</div>
                    <div style="font-size:10pt; margin-bottom:2px; color:#000; font-weight:bold;">
                        ${courseTitle}
                    </div>
                    <div style="font-size:8pt;color:#555;">Año Escolar ${new Date().getFullYear()}</div>
                </div>
            `;

                    if (tableContainer) {
                        let clone = tableContainer.cloneNode(true);
                        clone.style.fontSize = "7pt";
                        clone.style.margin = "0 auto";
                        clone.querySelectorAll('th,td').forEach(td => {
                            td.style.fontSize = "7pt";
                            td.style.whiteSpace = "nowrap";
                            td.style.padding = "1.2px 2.4px";
                            td.style.border = "1px solid #ccc";
                            td.style.lineHeight = "1.09";
                            if (!isNaN(td.textContent) && td.textContent.trim() !== '' && parseFloat(td.textContent) < 50) {
                                td.style.color = "#b1001e";
                                td.style.fontWeight = "bold";
                                td.style.background = "#ffeaea";
                            }
                        });
                        // Alternar fondo de columnas (materias)
                        clone.querySelectorAll('tbody tr').forEach(tr => {
                            let tdidx = 0;
                            tr.querySelectorAll('td').forEach(td => {
                                if (tdidx >= 2) {
                                    let bgc = (Math.floor((tdidx - 2) / 4) % 2 === 0) ? "#f7f8fa" : "#f0f2f4";
                                    td.style.background = bgc;
                                }
                                tdidx++;
                            });
                        });
                        clone.querySelectorAll('tbody tr').forEach(tr => {
                            let tds = tr.querySelectorAll('td');
                            if (tds.length) {
                                let last = tds[tds.length - 1];
                                last.style.background = "#ffe6b7";
                                last.style.fontWeight = "bold";
                                last.style.color = "#865805";
                            }
                        });
                        pdfContent.appendChild(clone);
                    }

                    document.body.appendChild(pdfContent);

                    // MÁXIMA RESOLUCIÓN
                    const canvas = await html2canvas(pdfContent, {
                        scale: 3.5, // resolución más alta posible
                        useCORS: true,
                        backgroundColor: "#fff"
                    });

                    if (!isFirstPage) pdf.addPage();
                    isFirstPage = false;

                    // Ajuste a tamaño legal horizontal
                    const marginX = 12,
                        marginY = 10;
                    const pageWidth = legalWidth - marginX * 2;
                    const pageHeight = legalHeight - marginY * 2;
                    let imgWidth = pageWidth,
                        imgHeight = (canvas.height * imgWidth) / canvas.width;
                    if (imgHeight > pageHeight) {
                        imgHeight = pageHeight;
                        imgWidth = (canvas.width * imgHeight) / canvas.height;
                    }

                    pdf.addImage(canvas, 'PNG', marginX, marginY, imgWidth, imgHeight);

                    document.body.removeChild(pdfContent);
                    document.body.removeChild(iframe);
                }
                pdf.save('Centralizadores_Primaria_Legal.pdf');
            } catch (error) {
                alert("Error generando PDF: " + error.message);
            } finally {
                document.body.removeChild(processingMsg);
            }
        }
    </script>


</body>

</html>