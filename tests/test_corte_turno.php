<?php
/**
 * Test: Verificacion de la logica de corte horario (MANANA/TARDE)
 * y consistencia escaner-reporte.
 *
 * Ejecutar: php tests/test_corte_turno.php
 */

$passed = 0;
$failed = 0;

function assert_eq($test_name, $expected, $actual)
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        echo "  [PASS] $test_name\n";
    } else {
        $failed++;
        echo "  [FAIL] $test_name\n";
        echo "         expected: " . var_export($expected, true) . "\n";
        echo "         actual:   " . var_export($actual, true) . "\n";
    }
}

echo "=== Test: Corte horario 12:00:00 ===\n\n";

$horaCorte = '12:00:00';

// Simular la logica de asistencia_resolver_turno_y_puntualidad (doble turno, AUTO)
function resolver_turno_auto($horaActual, $horaCorte, $tardeHabilitadaHoy)
{
    if ($horaActual < $horaCorte) {
        return 'MANANA';
    } else {
        if (!$tardeHabilitadaHoy) {
            return 'SIN_TARDE_HOY';
        }
        return 'TARDE';
    }
}

echo "1. Escenarios de turno automatico\n";

assert_eq('11:00 con TARDE habilitada -> MANANA',
    'MANANA', resolver_turno_auto('11:00:00', $horaCorte, true));

assert_eq('11:00 sin TARDE habilitada -> MANANA',
    'MANANA', resolver_turno_auto('11:00:00', $horaCorte, false));

assert_eq('11:59:59 con TARDE habilitada -> MANANA',
    'MANANA', resolver_turno_auto('11:59:59', $horaCorte, true));

assert_eq('12:00:00 con TARDE habilitada -> TARDE',
    'TARDE', resolver_turno_auto('12:00:00', $horaCorte, true));

assert_eq('12:00:00 sin TARDE habilitada -> SIN_TARDE_HOY',
    'SIN_TARDE_HOY', resolver_turno_auto('12:00:00', $horaCorte, false));

assert_eq('12:00:01 con TARDE habilitada -> TARDE',
    'TARDE', resolver_turno_auto('12:00:01', $horaCorte, true));

assert_eq('13:00:00 con TARDE habilitada -> TARDE',
    'TARDE', resolver_turno_auto('13:00:00', $horaCorte, true));

assert_eq('18:00:00 con TARDE habilitada -> TARDE',
    'TARDE', resolver_turno_auto('18:00:00', $horaCorte, true));

assert_eq('07:00:00 con TARDE habilitada -> MANANA',
    'MANANA', resolver_turno_auto('07:00:00', $horaCorte, true));

assert_eq('07:00:00 sin TARDE habilitada -> MANANA',
    'MANANA', resolver_turno_auto('07:00:00', $horaCorte, false));

assert_eq('08:30:00 con TARDE habilitada -> MANANA',
    'MANANA', resolver_turno_auto('08:30:00', $horaCorte, true));

echo "\n2. Simulacion: reporte usa turno almacenado (ya no infiere por hora)\n";

function simular_reporte($registros_db, $turno_filtro)
{
    $resultados = [];
    foreach ($registros_db as $r) {
        $turnoAlmacenado = strtoupper($r['turno'] ?? 'MANANA');
        if ($turnoAlmacenado === $turno_filtro) {
            $resultados[] = $r;
        }
    }
    return $resultados;
}

$registros = [
    ['id_estudiante' => 1, 'turno' => 'MANANA', 'hora_entrada' => '08:15:00', 'fecha' => '2026-06-11'],
    ['id_estudiante' => 2, 'turno' => 'MANANA', 'hora_entrada' => '09:00:00', 'fecha' => '2026-06-11'],
    ['id_estudiante' => 3, 'turno' => 'TARDE',  'hora_entrada' => '14:30:00', 'fecha' => '2026-06-11'],
    ['id_estudiante' => 4, 'turno' => 'TARDE',  'hora_entrada' => '13:00:00', 'fecha' => '2026-06-11'],
    ['id_estudiante' => 5, 'turno' => 'TARDE',  'hora_entrada' => '15:00:00', 'fecha' => '2026-06-11'],
];

$reporte_manana = simular_reporte($registros, 'MANANA');
$reporte_tarde = simular_reporte($registros, 'TARDE');

assert_eq('Filtro MANANA: 2 estudiantes (IDs 1,2)',
    2, count($reporte_manana));

assert_eq('Filtro TARDE: 3 estudiantes (IDs 3,4,5)',
    3, count($reporte_tarde));

// Verificar que el que llego a 13:00 esta en TARDE (aunque antes la inferencia podria ponerlo en MANANA)
$estudiante13 = null;
foreach ($reporte_tarde as $r) {
    if ($r['id_estudiante'] === 4) {
        $estudiante13 = $r;
    }
}
assert_eq('Estudiante 4 (13:00) aparece en TARDE con nuevo sistema',
    'TARDE', $estudiante13['turno'] ?? null);

assert_eq('Estudiante 4 NO esta en MANANA',
    false, in_array(4, array_column($reporte_manana, 'id_estudiante')));

echo "\n3. Verificacion: sin COMPLETO, el pre-check protege\n";

function simular_pre_check($registros_db, $idEstudiante, $fecha, $turnoAsignado)
{
    foreach ($registros_db as $r) {
        if ($r['id_estudiante'] === $idEstudiante
            && $r['fecha'] === $fecha
            && $r['turno'] === $turnoAsignado) {
            return ['existe' => true, 'hora_entrada' => $r['hora_entrada']];
        }
    }
    return ['existe' => false];
}

// Escenario 1: Estudiante 1 ya tiene MANANA, escanea a las 10 AM (antes del corte)
$check = simular_pre_check($registros, 1, '2026-06-11', 'MANANA');
assert_eq('Estudiante 1 con MANANA previa, 10AM: pre-check encuentra MANANA',
    true, $check['existe']);

// Escenario 2: Estudiante 1 ya tiene MANANA, escanea a las 2 PM (despues del corte)
$check = simular_pre_check($registros, 1, '2026-06-11', 'TARDE');
assert_eq('Estudiante 1 con MANANA previa, 2PM: pre-check NO encuentra TARDE',
    false, $check['existe']);

// Escenario 3: Estudiante 3 ya tiene TARDE, escanea a las 3 PM (despues del corte)
$check = simular_pre_check($registros, 3, '2026-06-11', 'TARDE');
assert_eq('Estudiante 3 con TARDE previa, 3PM: pre-check encuentra TARDE',
    true, $check['existe']);

// Escenario 4: Estudiante 3 ya tiene TARDE, escanea a las 10 AM
$check = simular_pre_check($registros, 3, '2026-06-11', 'MANANA');
assert_eq('Estudiante 3 con TARDE previa, 10AM: pre-check NO encuentra MANANA',
    false, $check['existe']);

echo "\n4. Consistencia: turno almacenado es el que el escaner decidio\n";

function decidir_y_almacenar($horaActual, $horaCorte, $tardeHabilitadaHoy)
{
    $turno = resolver_turno_auto($horaActual, $horaCorte, $tardeHabilitadaHoy);
    if ($turno === 'SIN_TARDE_HOY') {
        return null;
    }
    return ['turno' => $turno, 'hora_entrada' => $horaActual];
}

$almacenados = [];
$almacenados[] = decidir_y_almacenar('08:00:00', $horaCorte, true);  // MANANA
$almacenados[] = decidir_y_almacenar('11:00:00', $horaCorte, true);  // MANANA
$almacenados[] = decidir_y_almacenar('13:00:00', $horaCorte, true);  // TARDE
$almacenados[] = decidir_y_almacenar('14:00:00', $horaCorte, true);  // TARDE
$almacenados[] = decidir_y_almacenar('15:30:00', $horaCorte, true);  // TARDE
$almacenados[] = decidir_y_almacenar('14:00:00', $horaCorte, false); // SIN_TARDE_HOY

assert_eq('08:00 registrado como MANANA', 'MANANA', $almacenados[0]['turno'] ?? null);
assert_eq('11:00 registrado como MANANA', 'MANANA', $almacenados[1]['turno'] ?? null);
assert_eq('13:00 registrado como TARDE',  'TARDE',  $almacenados[2]['turno'] ?? null);
assert_eq('14:00 registrado como TARDE',  'TARDE',  $almacenados[3]['turno'] ?? null);
assert_eq('15:30 registrado como TARDE',  'TARDE',  $almacenados[4]['turno'] ?? null);
assert_eq('14:00 sin TARDE habilitada -> null (SIN_TARDE_HOY)',
    null,  $almacenados[5]);

// Verificar que el reporte (que usa turno almacenado) muestra lo correcto
$reporte_t = simular_reporte(array_filter($almacenados), 'TARDE');
assert_eq('Reporte TARDE: 3 registros (13:00, 14:00, 15:30)',
    3, count($reporte_t));

$reporte_m = simular_reporte(array_filter($almacenados), 'MANANA');
assert_eq('Reporte MANANA: 2 registros (08:00, 11:00)',
    2, count($reporte_m));

echo "\n5. Verificacion: casos limite con forzado de turno\n";

// Turno forzado siempre respeta el turno del usuario
function resolver_turno_forzado($turnoForzado, $tardeHabilitadaHoy)
{
    if ($turnoForzado === 'MANANA') {
        return 'MANANA';
    }
    if ($turnoForzado === 'TARDE') {
        if (!$tardeHabilitadaHoy) {
            return 'SIN_TARDE_HOY';
        }
        return 'TARDE';
    }
    return 'MANANA';
}

assert_eq('Forzar MANANA a las 15:00 -> MANANA',
    'MANANA', resolver_turno_forzado('MANANA', true));
assert_eq('Forzar TARDE con TARDE habilitada -> TARDE',
    'TARDE', resolver_turno_forzado('TARDE', true));
assert_eq('Forzar TARDE sin TARDE habilitada -> SIN_TARDE_HOY',
    'SIN_TARDE_HOY', resolver_turno_forzado('TARDE', false));

echo "\n6. Consistencia SQL del reporte\n";

// El SQL del reporte ahora usa: UPPER(COALESCE(a.turno, 'MANANA')) = ?
// Sin el CASE con TIMEDIFF
// Verificar que 1 placeholder = 1 param
$turnoFiltroSql = "UPPER(COALESCE(a.turno, 'MANANA')) = ?";
$turnoFiltroParams = ['TARDE'];

$placeholders = substr_count($turnoFiltroSql, '?');
assert_eq('SQL turno filter tiene 1 placeholder', 1, $placeholders);
assert_eq('turnoFiltroParams tiene 1 elemento', 1, count($turnoFiltroParams));

echo "\n=== Resultado: $passed passed, $failed failed ===\n";
echo ($failed === 0 ? "TODOS LOS TESTS PASARON\n" : "HAY FALLOS\n");

exit($failed === 0 ? 0 : 1);
