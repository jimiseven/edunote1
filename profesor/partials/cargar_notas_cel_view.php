<?php
$urlEscritorioExtra = ['confirmar' => 1];
if ($vistaActual === 'trimestral') {
    $urlEscritorioExtra['vista'] = 'trimestral';
}
$urlEscritorio = construirUrlPeriodo($id_curso_materia, $trimestreSeleccionado, $parcialSeleccionado, $urlEscritorioExtra);
$vistaCelDisponible = !$es_inicial && in_array($vistaActual, ['parcial', 'trimestral'], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduNote - Cargar notas celular</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <style>
        :root {
            --cel-primary: #2563eb;
            --cel-primary-dark: #1d4ed8;
            --cel-bg: #eef5fb;
            --cel-card: #ffffff;
            --cel-border: #dbe7f3;
            --cel-text: #10243f;
            --cel-muted: #64748b;
            --cel-shadow: 0 12px 30px rgba(15, 23, 42, .10);
        }
        body {
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(180deg, #f8fbff 0%, var(--cel-bg) 100%);
            color: var(--cel-text);
            padding-bottom: 96px;
        }
        .cel-shell {
            width: min(100%, 760px);
            margin: 0 auto;
            padding: 12px 12px 0;
        }
        .cel-topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            margin: -12px -12px 10px;
            padding: 12px;
            background: rgba(248, 251, 255, .96);
            border-bottom: 1px solid var(--cel-border);
            backdrop-filter: blur(10px);
        }
        .cel-title-card {
            background: var(--cel-card);
            border: 1px solid var(--cel-border);
            border-left: 4px solid var(--cel-primary);
            border-radius: 16px;
            padding: 10px 12px;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .07);
        }
        .cel-title-card h1 {
            margin: 0;
            font-size: 1rem;
            font-weight: 800;
            line-height: 1.15;
        }
        .cel-title-card p {
            margin: 2px 0 0;
            font-size: .82rem;
            color: #3b6f9f;
            font-weight: 700;
        }
        .cel-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }
        .cel-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: .72rem;
            font-weight: 800;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
        }
        .cel-pill.enabled {
            border-color: #bbf7d0;
            background: #dcfce7;
            color: #166534;
        }
        .cel-pill.disabled {
            border-color: #fecaca;
            background: #fee2e2;
            color: #991b1b;
        }
        .cel-periods {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 10px 0 2px;
        }
        .cel-period-group {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 6px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid var(--cel-border);
        }
        .cel-period-label {
            min-width: 24px;
            text-align: center;
            padding: 3px 6px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #334155;
            font-size: .7rem;
            font-weight: 900;
        }
        .cel-period-btn {
            min-width: 32px;
            text-align: center;
            padding: 4px 8px;
            border-radius: 999px;
            border: 1px solid #d7e2ee;
            color: #475569;
            text-decoration: none;
            font-size: .72rem;
            font-weight: 800;
        }
        .cel-period-btn.active {
            color: #ffffff;
            border-color: var(--cel-primary);
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            box-shadow: 0 6px 14px rgba(37, 99, 235, .22);
        }
        .cel-toolbar {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            margin-top: 10px;
        }
        .cel-search {
            min-height: 42px;
            border: 1px solid #c8d7e6;
            border-radius: 14px;
            padding: 0 12px;
            font-weight: 650;
        }
        .cel-desktop-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 12px;
            border-radius: 14px;
            border: 1px solid #bfd4ef;
            background: #ffffff;
            color: #1d4ed8;
            text-decoration: none;
            font-weight: 800;
            font-size: .82rem;
            white-space: nowrap;
        }
        .student-card {
            background: var(--cel-card);
            border: 1px solid var(--cel-border);
            border-radius: 18px;
            margin-bottom: 12px;
            box-shadow: var(--cel-shadow);
            overflow: hidden;
        }
        .student-head {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 11px 12px;
            background: linear-gradient(135deg, #ffffff, #f8fbff);
            border-bottom: 1px solid var(--cel-border);
        }
        .student-num {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 900;
            font-size: .75rem;
            flex-shrink: 0;
        }
        .student-name {
            min-width: 0;
            flex: 1;
            font-size: .94rem;
            font-weight: 850;
            color: #1e293b;
        }
        .student-total {
            border-radius: 999px;
            padding: 4px 8px;
            background: #faf5ff;
            color: #6b21a8;
            font-size: .72rem;
            font-weight: 900;
            white-space: nowrap;
        }
        .area-block {
            padding: 10px 12px;
            border-bottom: 1px solid #eef2f7;
        }
        .area-block:last-child {
            border-bottom: 0;
        }
        .area-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: .78rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .area-title.ser { color: #166534; }
        .area-title.saber { color: #1d4ed8; }
        .area-title.hacer { color: #9a3412; }
        .area-prom {
            text-transform: none;
            letter-spacing: 0;
            font-size: .72rem;
            color: var(--cel-muted);
        }
        .inputs-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
        }
        .input-wrap label {
            display: block;
            margin-bottom: 3px;
            font-size: .68rem;
            font-weight: 800;
            color: #64748b;
            text-align: center;
        }
        .mobile-note-input {
            width: 100%;
            min-height: 42px;
            border: 1px solid #c8d7e6;
            border-radius: 12px;
            text-align: center;
            font-weight: 850;
            font-size: .95rem;
            color: #0f172a;
            background: #ffffff;
        }
        .mobile-note-input:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, .22);
            background: #fffdf5;
        }
        .mobile-note-input:disabled,
        .mobile-note-input[readonly] {
            background: #eef2f7;
            color: #94a3b8;
        }
        .save-bar {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 70;
            padding: 10px 12px calc(10px + env(safe-area-inset-bottom));
            background: rgba(255,255,255,.94);
            border-top: 1px solid var(--cel-border);
            box-shadow: 0 -12px 26px rgba(15, 23, 42, .12);
            backdrop-filter: blur(10px);
        }
        .save-bar-inner {
            width: min(100%, 760px);
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            align-items: center;
        }
        .save-status {
            color: var(--cel-muted);
            font-size: .76rem;
            font-weight: 750;
        }
        .save-btn {
            min-height: 44px;
            border: 0;
            border-radius: 14px;
            padding: 0 18px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #ffffff;
            font-weight: 900;
            box-shadow: 0 12px 24px rgba(37, 99, 235, .24);
        }
        .save-btn:disabled {
            background: #cbd5e1;
            box-shadow: none;
        }
        .empty-state,
        .unsupported-state {
            background: #ffffff;
            border: 1px solid var(--cel-border);
            border-radius: 18px;
            padding: 18px;
            color: var(--cel-muted);
            box-shadow: var(--cel-shadow);
        }
    </style>
</head>
<body>
<div class="cel-shell">
    <div class="cel-topbar">
        <div class="cel-title-card">
            <h1><?php echo htmlspecialchars($curso['curso_nombre']); ?></h1>
            <p><?php echo htmlspecialchars($curso['nombre_materia']); ?> (Vista celular)</p>
            <div class="cel-meta">
                <span class="cel-pill <?php echo $periodoEditable ? 'enabled' : 'disabled'; ?>">
                    <?php echo $periodoEditable ? 'Habilitado' : 'No habilitado'; ?>
                </span>
                <span class="cel-pill">
                    <?php if ($vistaActual === 'trimestral'): ?>
                        Vista trimestral - T<?php echo (int)$trimestreSeleccionado; ?>
                    <?php else: ?>
                        T<?php echo (int)$trimestreSeleccionado; ?> - P<?php echo (int)$parcialSeleccionado; ?>
                    <?php endif; ?>
                </span>
                <span class="cel-pill">Gestion <?php echo htmlspecialchars($gestionActual); ?></span>
            </div>
        </div>
        <div class="cel-periods" aria-label="Seleccionar periodo">
            <?php if ($vistaActual === 'trimestral'): ?>
                <?php foreach ($periodosPorTrimestre as $trimestre => $parciales): ?>
                    <?php
                    $primerParcialTrim = !empty($parciales) ? (int)array_key_first($parciales) : 1;
                    $esTrimActual = (int)$trimestre === (int)$trimestreSeleccionado;
                    ?>
                    <div class="cel-period-group">
                        <a class="cel-period-btn<?php echo $esTrimActual ? ' active' : ''; ?>"
                           href="<?php echo htmlspecialchars(construirUrlVistaCelular($id_curso_materia, (int)$trimestre, $primerParcialTrim, ['confirmar' => 1, 'vista' => 'trimestral'])); ?>">
                            T<?php echo (int)$trimestre; ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?php foreach ($periodosPorTrimestre as $trimestre => $parciales): ?>
                    <div class="cel-period-group">
                        <span class="cel-period-label">T<?php echo (int)$trimestre; ?></span>
                        <?php foreach ($parciales as $parcial => $periodoBoton): ?>
                            <?php
                            $esPeriodoActual = (int)$trimestre === (int)$trimestreSeleccionado && (int)$parcial === (int)$parcialSeleccionado;
                            ?>
                            <a class="cel-period-btn<?php echo $esPeriodoActual ? ' active' : ''; ?>"
                               href="<?php echo htmlspecialchars(construirUrlVistaCelular($id_curso_materia, (int)$trimestre, (int)$parcial, ['confirmar' => 1])); ?>">
                                P<?php echo (int)$parcial; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="cel-toolbar">
            <input type="search" class="cel-search" id="studentSearch" placeholder="Buscar estudiante..." autocomplete="off">
            <a class="cel-desktop-link" href="<?php echo htmlspecialchars($urlEscritorio); ?>">Escritorio</a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php elseif (isset($_GET['success'])): ?>
        <div class="alert alert-success">Notas guardadas correctamente.</div>
    <?php endif; ?>

    <?php if (!$vistaCelDisponible): ?>
        <div class="unsupported-state">
            La vista celular esta disponible para carga parcial y trimestral. Usa la vista de escritorio para modalidad inicial.
        </div>
    <?php elseif (!$periodoConfirmado): ?>
        <div class="unsupported-state">
            Selecciona un periodo en la parte superior para cargar notas desde celular.
        </div>
    <?php else: ?>
        <form method="post" class="mobile-grade-form" id="mobileGradeForm" data-vista="<?php echo htmlspecialchars($vistaActual); ?>">
            <input type="hidden" name="trimestre" value="<?php echo (int)$trimestreSeleccionado; ?>">
            <input type="hidden" name="parcial" value="<?php echo (int)$parcialSeleccionado; ?>">
            <input type="hidden" name="nav_redirect" value="">
            <?php if ($vistaActual === 'trimestral'): ?>
                <input type="hidden" name="vista" value="trimestral">
            <?php endif; ?>

            <div id="studentsList">
                <?php $contador = 1; ?>
                <?php foreach ($estudiantes as $est): ?>
                    <?php
                    $idEstudianteFila = (int)$est['id_estudiante'];
                    $detalleFila = $detalleNotas[$idEstudianteFila] ?? [];
                    $totalesFila = $totalesAreasPorEstudiante[$idEstudianteFila] ?? ['ser_total' => 0, 'saber_total' => 0, 'hacer_total' => 0, 'calificacion' => 0];
                    $trimData = $notasTrimestrales[$idEstudianteFila][$trimestreSeleccionado] ?? [];
                    $autoVal = $trimData['autoevaluacion'] ?? '';
                    $extraVal = $trimData['nota_extra'] ?? '';
                    $parciales95 = [];
                    for ($px = 1; $px <= 3; $px++) {
                        $parciales95[$px] = isset($notas[$idEstudianteFila][$trimestreSeleccionado][$px]) && is_numeric($notas[$idEstudianteFila][$trimestreSeleccionado][$px])
                            ? (float)$notas[$idEstudianteFila][$trimestreSeleccionado][$px] : null;
                    }
                    $vals95 = array_filter($parciales95, static fn($v) => $v !== null);
                    $prom95 = count($vals95) ? (array_sum($vals95) / count($vals95)) : null;
                    $autoNum = ($autoVal !== '' && $autoVal !== null) ? (float)$autoVal : 0;
                    $extraNum = ($extraVal !== '' && $extraVal !== null) ? (float)$extraVal : 0;
                    $totalFinal = ($prom95 ?? 0) + $autoNum + $extraNum;
                    ?>
                    <article class="student-card" data-student-name="<?php echo htmlspecialchars(mb_strtolower($est['nombre'])); ?>">
                        <div class="student-head">
                            <span class="student-num"><?php echo $contador++; ?></span>
                            <div class="student-name"><?php echo htmlspecialchars($est['nombre']); ?></div>
                            <div class="student-total">
                                <?php if ($vistaActual === 'trimestral'): ?>
                                    Total <span class="mobile-total-final"><?php echo number_format((float)$totalFinal, 2); ?></span>
                                <?php else: ?>
                                    Total <span class="mobile-total-95"><?php echo number_format((float)$totalesFila['calificacion'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($vistaActual === 'trimestral'): ?>
                            <section class="area-block">
                                <div class="area-title saber">
                                    <span>Resumen parcial (95)</span>
                                    <span class="area-prom">Prom: <strong class="mobile-prom-95"><?php echo $prom95 !== null ? number_format((float)$prom95, 2) : '0.00'; ?></strong></span>
                                </div>
                                <div class="inputs-grid">
                                    <?php for ($px = 1; $px <= 3; $px++): ?>
                                        <div class="input-wrap">
                                            <label>P<?php echo $px; ?></label>
                                            <input type="text" class="mobile-note-input" value="<?php echo $parciales95[$px] !== null ? number_format((float)$parciales95[$px], 2) : '--'; ?>" readonly disabled>
                                        </div>
                                    <?php endfor; ?>
                                    <div class="input-wrap">
                                        <label>Prom</label>
                                        <input type="text" class="mobile-note-input mobile-prom-95" value="<?php echo $prom95 !== null ? number_format((float)$prom95, 2) : '0.00'; ?>" readonly disabled>
                                    </div>
                                </div>
                            </section>
                            <section class="area-block" data-area-block="TRIM">
                                <div class="area-title hacer">
                                    <span>Ajustes trimestrales</span>
                                    <span class="area-prom">Final: <strong class="mobile-total-final"><?php echo number_format((float)$totalFinal, 2); ?></strong></span>
                                </div>
                                <div class="inputs-grid">
                                    <div class="input-wrap">
                                        <label for="m_auto_<?php echo $idEstudianteFila; ?>">Auto (5)</label>
                                        <input id="m_auto_<?php echo $idEstudianteFila; ?>" type="number" class="mobile-note-input" name="auto[<?php echo $idEstudianteFila; ?>]" data-area="AUTO" data-min="0" data-max="5" value="<?php echo htmlspecialchars($autoVal === null ? '' : $autoVal); ?>" step="0.01" min="0" max="5" <?php echo !$trimestreEditableParaVistaTrimestral ? 'readonly disabled' : ''; ?>>
                                    </div>
                                    <div class="input-wrap">
                                        <label for="m_extra_<?php echo $idEstudianteFila; ?>"><?php echo $es_materia_principal_complementada ? 'Bonus' : 'Extra'; ?> (5)</label>
                                        <?php if ($es_materia_principal_complementada): ?>
                                            <input id="m_extra_<?php echo $idEstudianteFila; ?>" type="text" class="mobile-note-input" value="<?php echo $extraVal !== null && $extraVal !== '' ? number_format((float)$extraVal, 2) : '0.00'; ?>" readonly disabled>
                                        <?php else: ?>
                                            <input id="m_extra_<?php echo $idEstudianteFila; ?>" type="number" class="mobile-note-input" name="extra[<?php echo $idEstudianteFila; ?>]" data-area="EXTRA" data-min="0" data-max="5" value="<?php echo htmlspecialchars($extraVal === null ? '' : $extraVal); ?>" step="0.01" min="0" max="5" <?php echo !$trimestreEditableParaVistaTrimestral ? 'readonly disabled' : ''; ?>>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </section>
                        <?php else: ?>
                            <?php
                            $areasConfig = [
                                'SER' => ['max' => 4, 'min' => 0, 'maxNota' => 10, 'class' => 'ser', 'totalKey' => 'ser_total'],
                                'SABER' => ['max' => 8, 'min' => 0, 'maxNota' => 45, 'class' => 'saber', 'totalKey' => 'saber_total'],
                                'HACER' => ['max' => 8, 'min' => 0, 'maxNota' => 40, 'class' => 'hacer', 'totalKey' => 'hacer_total'],
                            ];
                            ?>
                            <?php foreach ($areasConfig as $area => $cfg): ?>
                                <section class="area-block" data-area-block="<?php echo $area; ?>">
                                    <div class="area-title <?php echo $cfg['class']; ?>">
                                        <span><?php echo $area; ?></span>
                                        <span class="area-prom">Prom: <strong class="mobile-area-total" data-total-area="<?php echo $area; ?>"><?php echo number_format((float)$totalesFila[$cfg['totalKey']], 2); ?></strong></span>
                                    </div>
                                    <div class="inputs-grid">
                                        <?php for ($i = 1; $i <= $cfg['max']; $i++): ?>
                                            <?php $valor = $detalleFila[$area][$i] ?? ''; ?>
                                            <div class="input-wrap">
                                                <label for="m_<?php echo $area . '_' . $idEstudianteFila . '_' . $i; ?>"><?php echo $i; ?></label>
                                                <input id="m_<?php echo $area . '_' . $idEstudianteFila . '_' . $i; ?>" type="number" class="mobile-note-input mobile-area-<?php echo strtolower($area); ?>" name="notas[<?php echo $idEstudianteFila; ?>][<?php echo $area; ?>][<?php echo $i; ?>]" data-area="<?php echo $area; ?>" data-min="<?php echo $cfg['min']; ?>" data-max="<?php echo $cfg['maxNota']; ?>" value="<?php echo htmlspecialchars($valor === null ? '' : $valor); ?>" step="0.01" min="<?php echo $cfg['min']; ?>" max="<?php echo $cfg['maxNota']; ?>" <?php echo !$periodoEditable ? 'readonly disabled' : ''; ?>>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="empty-state d-none" id="emptySearchState">No se encontraron estudiantes con ese texto.</div>

            <div class="save-bar">
                <div class="save-bar-inner">
                    <div class="save-status"><span id="visibleStudentsCount"><?php echo count($estudiantes); ?></span> estudiantes visibles</div>
                    <?php
                    $saveName = $vistaActual === 'trimestral' ? 'guardar_trimestral' : 'guardar_notas';
                    $saveEnabled = $vistaActual === 'trimestral' ? $trimestreEditableParaVistaTrimestral : $periodoEditable;
                    ?>
                    <button type="submit" name="<?php echo $saveName; ?>" class="save-btn" <?php echo !$saveEnabled ? 'disabled' : ''; ?>>
                        <?php echo $saveEnabled ? ($vistaActual === 'trimestral' ? 'Guardar trimestral' : 'Guardar notas') : 'No disponible'; ?>
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="../js/bootstrap.bundle.min.js"></script>
<script>
    function parseMobileNumber(value) {
        const v = String(value ?? '').trim().replace(',', '.');
        if (v === '') return null;
        const n = Number(v);
        return Number.isFinite(n) ? n : null;
    }
    function avgMobile(inputs) {
        const nums = inputs.map(input => parseMobileNumber(input.value)).filter(v => v !== null);
        if (!nums.length) return 0;
        return nums.reduce((a, b) => a + b, 0) / nums.length;
    }
    function updateCardParcial(card) {
        let total = 0;
        ['SER', 'SABER', 'HACER'].forEach(area => {
            const inputs = Array.from(card.querySelectorAll('[data-area="' + area + '"]'));
            const prom = +avgMobile(inputs).toFixed(2);
            total += prom;
            const label = card.querySelector('[data-total-area="' + area + '"]');
            if (label) label.textContent = prom.toFixed(2);
        });
        const totalLabel = card.querySelector('.mobile-total-95');
        if (totalLabel) totalLabel.textContent = total.toFixed(2);
    }
    function updateCardTrimestral(card) {
        const prom95Input = card.querySelector('.mobile-prom-95');
        const prom95 = prom95Input ? (parseMobileNumber(prom95Input.value) || 0) : 0;
        const autoInput = card.querySelector('[data-area="AUTO"]');
        const extraInput = card.querySelector('[data-area="EXTRA"]');
        const auto = autoInput ? (parseMobileNumber(autoInput.value) || 0) : 0;
        const extra = extraInput ? (parseMobileNumber(extraInput.value) || 0) : 0;
        const total = prom95 + auto + extra;
        card.querySelectorAll('.mobile-total-final').forEach(label => {
            label.textContent = total.toFixed(2);
        });
    }
    function updateCard(card, vista) {
        if (vista === 'trimestral') {
            updateCardTrimestral(card);
            return;
        }
        updateCardParcial(card);
    }
    const form = document.getElementById('mobileGradeForm');
    const vista = form ? (form.dataset.vista || 'parcial') : 'parcial';
    document.querySelectorAll('.student-card').forEach(card => {
        updateCard(card, vista);
        card.querySelectorAll('.mobile-note-input').forEach(input => {
            input.addEventListener('input', () => updateCard(card, vista));
            input.addEventListener('blur', () => {
                const n = parseMobileNumber(input.value);
                if (n === null) return;
                const min = Number(input.dataset.min);
                const max = Number(input.dataset.max);
                if (n < min) input.value = String(min);
                if (n > max) input.value = String(max);
                updateCard(card, vista);
            });
        });
    });
    const search = document.getElementById('studentSearch');
    const emptyState = document.getElementById('emptySearchState');
    const visibleCount = document.getElementById('visibleStudentsCount');
    if (search) {
        search.addEventListener('input', () => {
            const q = search.value.trim().toLowerCase();
            let visible = 0;
            document.querySelectorAll('.student-card').forEach(card => {
                const match = !q || card.dataset.studentName.includes(q);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            if (visibleCount) visibleCount.textContent = visible;
            if (emptyState) emptyState.classList.toggle('d-none', visible !== 0);
        });
    }
</script>
</body>
</html>
