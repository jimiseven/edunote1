<?php
// Habilitar reporte de errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';

// Verificar si TCPDF está instalado
if (!file_exists('../vendor/tecnickcom/tcpdf/tcpdf.php')) {
    die('Error: TCPDF no está instalado. Ejecuta "composer update" primero.');
}
require_once '../vendor/autoload.php';

// Verificar que TCPDF se cargó correctamente
if (!class_exists('TCPDF')) {
    die('Error: La clase TCPDF no existe después de cargar el autoloader');
}

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], [1, 2])) {
    header('Location: ../index.php');
    exit();
}

// Validar parámetro id_curso
if (!isset($_GET['id_curso']) || !is_numeric($_GET['id_curso'])) {
    die('Error: ID de curso no especificado o inválido');
}

$id_curso = intval($_GET['id_curso']);

try {
    $database = new Database();
    $conn = $database->connect();

    // Obtener información del curso
    $stmt_curso = $conn->prepare("SELECT nivel, curso, paralelo FROM cursos WHERE id_curso = ?");
    $stmt_curso->execute([$id_curso]);

    if ($stmt_curso->rowCount() == 0) {
        die('Error: Curso no encontrado');
    }

    $curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);
    $nombre_curso = "{$curso_info['nivel']} {$curso_info['curso']} \"{$curso_info['paralelo']}\"";

    // Obtener lista de estudiantes
    $stmt_estudiantes = $conn->prepare("
        SELECT id_estudiante, apellido_paterno, apellido_materno, nombres 
        FROM estudiantes 
        WHERE id_curso = ?
        ORDER BY apellido_paterno, apellido_materno, nombres
    ");
    $stmt_estudiantes->execute([$id_curso]);
    $estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

    // Obtener materias
    $stmt_materias = $conn->prepare("
        SELECT m.id_materia, m.nombre_materia, m.es_extra, m.es_submateria, m.materia_padre_id
        FROM cursos_materias cm 
        JOIN materias m ON cm.id_materia = m.id_materia 
        WHERE cm.id_curso = ? 
        ORDER BY m.nombre_materia
    ");
    $stmt_materias->execute([$id_curso]);
    $todas_materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);

    // Clasificar materias
    $materias_padre = [];
    $materias_hijas = [];
    foreach ($todas_materias as $materia) {
        if ($materia['es_extra'] == 0 && $materia['es_submateria'] == 0) {
            $materia['hijas'] = [];
            $materias_padre[$materia['id_materia']] = $materia;
        } elseif ($materia['es_submateria'] == 1) {
            $materias_hijas[] = $materia;
        }
    }
    foreach ($materias_hijas as $hija) {
        if (isset($materias_padre[$hija['materia_padre_id']])) {
            $materias_padre[$hija['materia_padre_id']]['hijas'][] = $hija;
        }
    }

    // Calificaciones
    $calificaciones = [];
    foreach ($estudiantes as $estudiante) {
        foreach ($todas_materias as $materia) {
            for ($i = 1; $i <= 3; $i++) {
                $stmt = $conn->prepare("
                    SELECT calificacion 
                    FROM calificaciones 
                    WHERE id_estudiante = ? AND id_materia = ? AND bimestre = ?
                ");
                $stmt->execute([$estudiante['id_estudiante'], $materia['id_materia'], $i]);
                $nota = $stmt->fetchColumn();
                $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$i] = $nota ?: '';
            }
        }
    }

    // Promedios
    $promedios_materias = [];
    foreach ($estudiantes as $estudiante) {
        foreach ($todas_materias as $materia) {
            $notas = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']] ?? [];
            $notas_validas = array_filter($notas, fn($v) => $v !== '' && $v !== null);
            $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] =
                count($notas_validas) > 0 ? number_format(array_sum($notas_validas) / count($notas_validas), 2) : '';
        }
    }

    // Crear clase personalizada para header/footer
    class CustomPDF extends TCPDF {
        public function Header() {
            $this->Image('../public/logo.png', 10, 5, 20); // Logo
            $this->SetFont('helvetica', 'B', 14);
            $this->Cell(0, 10, 'U.E. SIMÓN BOLÍVAR', 0, 1, 'C');
            $this->SetFont('helvetica', '', 10);
            $this->Cell(0, 5, 'Reporte de Notas', 0, 1, 'C');
            $this->Ln(5);
        }
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Generado el ' . date('d/m/Y') . ' - Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
        }
    }

    // Crear PDF
    $pdf = new CustomPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetMargins(10, 30, 10);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, strtoupper($nombre_curso), 0, 1, 'C');
    $pdf->Ln(5);

    // Encabezados de tabla
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(41, 128, 185);
    $pdf->SetTextColor(255, 255, 255);

    $widths = [10, 50];
    $header = ['#', 'Estudiante'];

    foreach ($todas_materias as $materia) {
        if ($materia['es_extra'] == 1 || $materia['es_submateria'] == 1) continue;
        $widths = array_merge($widths, [12, 12, 12, 15]);
        $header = array_merge($header, [
            $materia['nombre_materia'] . ' T1',
            $materia['nombre_materia'] . ' T2',
            $materia['nombre_materia'] . ' T3',
            $materia['nombre_materia'] . ' P'
        ]);
    }
    $widths[] = 15;
    $header[] = 'P. General';

    foreach ($header as $i => $col) {
        $pdf->Cell($widths[$i], 7, $col, 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Filas con alternancia de color
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $contador = 1;

    foreach ($estudiantes as $estudiante) {
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        $pdf->Cell($widths[0], 6, $contador, 1, 0, 'C', $fill);
        $pdf->Cell($widths[1], 6, strtoupper("{$estudiante['apellido_paterno']} {$estudiante['apellido_materno']}, {$estudiante['nombres']}"), 1, 0, 'L', $fill);

        $suma_total = 0;
        $contador_materias = 0;
        foreach ($todas_materias as $materia) {
            if ($materia['es_extra'] == 1 || $materia['es_submateria'] == 1) continue;
            for ($i = 1; $i <= 3; $i++) {
                $nota = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$i] ?? '';
                $pdf->Cell(12, 6, $nota, 1, 0, 'C', $fill);
            }
            $promedio = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
            $pdf->Cell(15, 6, $promedio, 1, 0, 'C', $fill);
            if ($promedio !== '') {
                $suma_total += $promedio;
                $contador_materias++;
            }
        }

        $prom_general = $contador_materias > 0 ? number_format($suma_total / $contador_materias, 2) : '';
        $pdf->Cell(15, 6, $prom_general, 1, 0, 'C', $fill);

        $pdf->Ln();
        $fill = !$fill;
        $contador++;
    }

    $pdf->Output("Reporte_Notas_{$nombre_curso}.pdf", 'I');

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
