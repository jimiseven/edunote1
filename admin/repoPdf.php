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

    // Función auxiliar para normalizar strings en español
    function normalizeSpanishString($str) {
        // Convertir a minúsculas para comparación
        $str = mb_strtolower($str, 'UTF-8');
        
        // Reemplazar caracteres especiales del español
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u'
        ];
        
        return strtr($str, $replacements);
    }

    // Obtener lista de estudiantes
    $stmt_estudiantes = $conn->prepare("
        SELECT id_estudiante, apellido_paterno, apellido_materno, nombres 
        FROM estudiantes 
        WHERE id_curso = ?
    ");
    $stmt_estudiantes->execute([$id_curso]);
    $estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

    // Ordenar estudiantes alfabéticamente considerando caracteres españoles
    usort($estudiantes, function($a, $b) {
        // Normalizar caracteres para comparación correcta en español
        $a_paterno = normalizeSpanishString($a['apellido_paterno']);
        $b_paterno = normalizeSpanishString($b['apellido_paterno']);
        $a_materno = normalizeSpanishString($a['apellido_materno']);
        $b_materno = normalizeSpanishString($b['apellido_materno']);
        $a_nombres = normalizeSpanishString($a['nombres']);
        $b_nombres = normalizeSpanishString($b['nombres']);
        
        // Comparar primero por apellido paterno
        $cmp_apellido = strcoll($a_paterno, $b_paterno);
        if ($cmp_apellido !== 0) {
            return $cmp_apellido;
        }
        
        // Si apellido paterno es igual, comparar por apellido materno
        $cmp_materno = strcoll($a_materno, $b_materno);
        if ($cmp_materno !== 0) {
            return $cmp_materno;
        }
        
        // Si ambos apellidos son iguales, comparar por nombres
        return strcoll($a_nombres, $b_nombres);
    });

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

    // NOTA AUTOMÁTICA para materias padre (promedio de hijas por trimestre)
    foreach ($estudiantes as $estudiante) {
        foreach ($materias_padre as $padre) {
            if (!empty($padre['hijas'])) {
                for ($t = 1; $t <= 3; $t++) {
                    $suma = 0;
                    $contador = 0;
                    foreach ($padre['hijas'] as $hija) {
                        $nota_hija = $calificaciones[$estudiante['id_estudiante']][$hija['id_materia']][$t] ?? '';
                        if ($nota_hija !== '') {
                            $suma += floatval($nota_hija);
                            $contador++;
                        }
                    }
                    if ($contador > 0) {
                        $calificaciones[$estudiante['id_estudiante']][$padre['id_materia']][$t] = number_format($suma / $contador, 2);
                    }
                }
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

    // PRIMERO: Calcular promedios para materias padre con hijas ANTES de calcular promedios generales
    foreach ($estudiantes as $estudiante) {
        foreach ($materias_padre as $padre) {
            if (!empty($padre['hijas'])) {
                $suma_hijas = 0;
                $contador_hijas = 0;
                
                foreach ($padre['hijas'] as $hija) {
                    $promedio_hija = $promedios_materias[$estudiante['id_estudiante']][$hija['id_materia']] ?? '';
                    if ($promedio_hija !== '') {
                        $suma_hijas += floatval($promedio_hija);
                        $contador_hijas++;
                    }
                }
                
                if ($contador_hijas > 0) {
                    $promedio_padre = $suma_hijas / $contador_hijas;
                    $promedios_materias[$estudiante['id_estudiante']][$padre['id_materia']] = number_format($promedio_padre, 2);
                }
            }
        }
    }

    // SEGUNDO: Calcular promedios generales para todos los estudiantes
    $promedios_generales = [];
    foreach ($estudiantes as $estudiante) {
        $suma = 0;
        $contador = 0;
        
        // Solo contar materias padre (no hijas) para el promedio general
        foreach ($todas_materias as $materia) {
            if ($materia['es_extra'] == 1 || $materia['es_submateria'] == 1) continue;
            
            $promedio = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
            if ($promedio !== '') {
                $suma += floatval($promedio);
                $contador++;
            }
        }
        
        $promedios_generales[$estudiante['id_estudiante']] = ($contador > 0) ? number_format($suma / $contador, 2) : '-';
    }

    // TERCERO: Calcular posiciones según promedio general
    $promedios_ordenados = $promedios_generales;
    arsort($promedios_ordenados);
    $posiciones = [];
    $pos_actual = 1;
    $prom_anterior = null;

    foreach ($promedios_ordenados as $id_est => $prom) {
        if ($prom_anterior !== null && $prom < $prom_anterior) {
            $pos_actual++;
        }
        $posiciones[$id_est] = $pos_actual;
        $prom_anterior = $prom;
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
    $pdf->SetMargins(8, 25, 8);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, strtoupper($nombre_curso), 0, 1, 'C');
    $pdf->Ln(3);

    // Encabezados de tabla
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetFillColor(0, 0, 0); // Negro para B&N
    $pdf->SetTextColor(255, 255, 255);

    $widths = [8, 8, 45]; // Columnas más compactas
    $header = ['#', 'Pos.', 'Estudiante'];

    foreach ($todas_materias as $materia) {
        if ($materia['es_extra'] == 1 || $materia['es_submateria'] == 1) continue;
        $widths = array_merge($widths, [10, 10, 10, 12]); // Columnas más compactas
        $header = array_merge($header, [
            $materia['nombre_materia'] . ' T1',
            $materia['nombre_materia'] . ' T2',
            $materia['nombre_materia'] . ' T3',
            $materia['nombre_materia'] . ' P'
        ]);
    }
    $widths[] = 12; // Promedio general más compacto
    $header[] = 'P. General';

    foreach ($header as $i => $col) {
        $pdf->Cell($widths[$i], 6, $col, 1, 0, 'C', true); // Altura reducida
    }
    $pdf->Ln();

    // Filas con alternancia de color
    $pdf->SetFont('helvetica', '', 7); // Fuente más pequeña
    $pdf->SetTextColor(0, 0, 0);
    $fill = false;
    $contador = 1;

    foreach ($estudiantes as $estudiante) {
        $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        $pdf->Cell($widths[0], 5, $contador, 1, 0, 'C', $fill); // Altura reducida
        $pdf->Cell($widths[1], 5, $posiciones[$estudiante['id_estudiante']], 1, 0, 'C', $fill); // Altura reducida
        $pdf->Cell($widths[2], 5, strtoupper("{$estudiante['apellido_paterno']} {$estudiante['apellido_materno']}, {$estudiante['nombres']}"), 1, 0, 'L', $fill); // Altura reducida

        $col_index = 3; // Empezar desde la columna 3 (después de #, Pos., Estudiante)
        foreach ($todas_materias as $materia) {
            if ($materia['es_extra'] == 1 || $materia['es_submateria'] == 1) continue;
            
            // Mostrar notas de cada trimestre
            for ($i = 1; $i <= 3; $i++) {
                $nota = $calificaciones[$estudiante['id_estudiante']][$materia['id_materia']][$i] ?? '';
                $pdf->Cell($widths[$col_index], 5, $nota, 1, 0, 'C', $fill); // Altura reducida
                $col_index++;
            }
            
            // Mostrar promedio de la materia
            $promedio = $promedios_materias[$estudiante['id_estudiante']][$materia['id_materia']] ?? '';
            $pdf->Cell($widths[$col_index], 5, $promedio, 1, 0, 'C', $fill); // Altura reducida
            $col_index++;
        }

        // Mostrar promedio general ya calculado
        $prom_general = $promedios_generales[$estudiante['id_estudiante']];
        $pdf->Cell($widths[$col_index], 5, $prom_general, 1, 0, 'C', $fill); // Altura reducida

        $pdf->Ln();
        $fill = !$fill;
        $contador++;
    }

    $pdf->Output("Reporte_Notas_{$nombre_curso}.pdf", 'I');

} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
