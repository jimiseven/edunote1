<?php
/**
 * Helper para repartir la nota extra entre los parciales.
 *
 * Reglas:
 *  - Bono base = nota_extra * multiplicador.
 *  - Multiplicador depende de cuantos parciales cargados tiene el estudiante:
 *      * 3 parciales -> x3
 *      * 2 parciales -> x2
 *      * 1 parcial   -> x1
 *      * 0 parciales -> no se reparte (se conserva en BD para cuando carguen uno)
 *  - El bono se suma al parcial con menor nota. Si supera 95, el excedente se reparte
 *    entre los demás parciales en el mismo orden (menor primero) y con el mismo
 *    tope de 95.
 *  - Devuelve un arreglo [parcial => nuevaNota] conservando nulos/vacíos.
 *
 * No modifica la base de datos; solo opera en memoria sobre los valores dados.
 */

/**
 * @param array<int, float|null> $parcialesNotas Mapa de numero de parcial (1,2,3) a nota (o null)
 * @param float $notaExtra Valor actual de nota_extra (0-5). Si es null/<=0 no hace nada.
 * @return array<int, float|null> Notas actualizadas
 */
function repartirNotaExtra(array $parcialesNotas, float $notaExtra): array {
    if ($notaExtra <= 0) {
        return $parcialesNotas;
    }

    // Contar parciales efectivamente cargados.
    $indices = [1, 2, 3];
    $cargados = [];
    foreach ($indices as $px) {
        $v = $parcialesNotas[$px] ?? null;
        if ($v !== null) {
            $cargados[$px] = $v;
        }
    }
    $cantidadCargados = count($cargados);

    // Si no hay parciales cargados, el bono se queda sin aplicar por ahora.
    if ($cantidadCargados === 0) {
        return $parcialesNotas;
    }

    // Multiplicador segun cantidad de parciales cargados.
    $multiplicador = match ($cantidadCargados) {
        1 => 1.0,
        2 => 2.0,
        default => 3.0,
    };
    $bono = $notaExtra * $multiplicador;

    $resultado = $parcialesNotas;

    while ($bono > 0.00001) {
        $candidatos = [];
        foreach ($indices as $px) {
            $v = $resultado[$px] ?? null;
            if ($v === null) continue;
            $candidatos[$px] = $v;
        }
        if (empty($candidatos)) break;

        asort($candidatos);
        $pxMenor = array_key_first($candidatos);
        $valorMenor = $candidatos[$pxMenor];

        $espacio = 95.0 - $valorMenor;
        if ($espacio <= 0.00001) {
            $resultado[$pxMenor] = 95.0;
            $stillSaturated = false;
            foreach ($indices as $px) {
                if (($resultado[$px] ?? null) !== null && $resultado[$px] >= 95.0) {
                    $stillSaturated = true;
                }
            }
            if ($stillSaturated && count(array_filter($resultado, fn($v) => $v !== null && $v < 95.0)) === 0) {
                break;
            }
            continue;
        }

        $aplicar = min($bono, $espacio);
        $resultado[$pxMenor] = $valorMenor + $aplicar;
        $bono -= $aplicar;
    }

    return $resultado;
}

/**
 * Versión que toma los promedios agrupando por estudiante y devuelve el mapa
 * promedios finales por parcial tras aplicar el reparto.
 *
 * @param array<int, array<int, float|null>> $notasPorEstudiante id_est => [parcial => nota]
 * @param array<int, float> $notaExtraPorEstudiante id_est => nota_extra
 * @return array<int, array<int, float|null>>
 */
function repartirNotaExtraPorEstudiante(array $notasPorEstudiante, array $notaExtraPorEstudiante): array {
    $out = [];
    foreach ($notasPorEstudiante as $idEst => $parciales) {
        $extra = $notaExtraPorEstudiante[$idEst] ?? 0.0;
        $out[$idEst] = repartirNotaExtra($parciales, (float)$extra);
    }
    return $out;
}
