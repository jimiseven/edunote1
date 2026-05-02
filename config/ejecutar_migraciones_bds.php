<?php
/**
 * Ejecuta los scripts en /bds al conectar (CREATE detalle, CHECK por área).
 * Idempotente: errores esperados se ignoran.
 */

function edunote_sql_sentencias_desde_archivo($ruta) {
    if (!is_readable($ruta)) {
        return [];
    }
    $sql = file_get_contents($ruta);
    $sql = preg_replace('/\/\*[\s\S]*?\*\//', '', $sql);
    $lineas = preg_split('/\R/', $sql);
    $buf = '';
    foreach ($lineas as $linea) {
        $p = strpos($linea, '--');
        if ($p !== false) {
            $linea = substr($linea, 0, $p);
        }
        $buf .= $linea . "\n";
    }
    $partes = preg_split('/;\s*\R/u', trim($buf));
    $out = [];
    foreach ($partes as $p) {
        $p = trim($p);
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
}

function edunote_ejecutar_sentencias(PDO $conn, array $sentencias) {
    foreach ($sentencias as $s) {
        try {
            $conn->exec($s);
        } catch (PDOException $e) {
            // Ya aplicado, motor distinto, permisos, etc.
        }
    }
}

function edunote_check_nota_detalle_sql() {
    return "ALTER TABLE calificaciones_parciales_detalle
        ADD CONSTRAINT chk_cpd_nota CHECK (
            (nota IS NULL) OR
            (area = 'SER' AND nota >= 0 AND nota <= 10) OR
            (area = 'SABER' AND nota >= 0 AND nota <= 45) OR
            (area = 'HACER' AND nota >= 0 AND nota <= 40)
        )";
}

function edunote_aplicar_migraciones_bds(PDO $conn) {
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    $dirBds = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'bds' . DIRECTORY_SEPARATOR;

    edunote_ejecutar_sentencias($conn, edunote_sql_sentencias_desde_archivo($dirBds . 'calificaciones_parciales_detalle.sql'));
    edunote_ejecutar_sentencias($conn, edunote_sql_sentencias_desde_archivo($dirBds . 'asistencia.sql'));
    edunote_ejecutar_sentencias($conn, edunote_sql_sentencias_desde_archivo($dirBds . 'asistencia_lectores.sql'));
    edunote_ejecutar_sentencias($conn, edunote_sql_sentencias_desde_archivo($dirBds . 'asistencia_horarios.sql'));
    edunote_ejecutar_sentencias($conn, edunote_sql_sentencias_desde_archivo($dirBds . 'usuarios_activos.sql'));
    edunote_ejecutar_sentencias($conn, edunote_sql_sentencias_desde_archivo($dirBds . 'usuarios_ingresos.sql'));

    try {
        $conn->query('SELECT 1 FROM calificaciones_parciales_detalle LIMIT 0');
    } catch (PDOException $e) {
        $sinFk = "CREATE TABLE IF NOT EXISTS calificaciones_parciales_detalle (
            id_detalle int(11) NOT NULL AUTO_INCREMENT,
            id_calificacion_parcial int(11) NOT NULL,
            area varchar(10) NOT NULL,
            indice tinyint(4) NOT NULL,
            nota decimal(8,2) DEFAULT NULL,
            creado_por int(11) DEFAULT NULL,
            PRIMARY KEY (id_detalle),
            UNIQUE KEY uk_calif_area_idx (id_calificacion_parcial, area, indice),
            KEY idx_calificacion (id_calificacion_parcial)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        try {
            $conn->exec($sinFk);
        } catch (PDOException $e2) {
        }
    }

    edunote_ejecutar_sentencias($conn, edunote_sql_sentencias_desde_archivo($dirBds . 'fix_chk_cpd_nota.sql'));

    foreach ([
        'ALTER TABLE calificaciones_parciales_detalle DROP CHECK chk_cpd_nota',
        'ALTER TABLE calificaciones_parciales_detalle DROP CONSTRAINT chk_cpd_nota',
    ] as $drop) {
        try {
            $conn->exec($drop);
        } catch (PDOException $e) {
        }
    }
    try {
        $conn->exec(edunote_check_nota_detalle_sql());
    } catch (PDOException $e) {
    }
}
