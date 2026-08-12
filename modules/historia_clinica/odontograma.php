<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('historias.ver');

$paginaTitulo = 'Odontograma';
$historiaId = filter_input(INPUT_GET, 'historia_id', FILTER_VALIDATE_INT);
if (!$historiaId) {
    setAlerta('Historia clinica no valida.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT hc.id, hc.numero_historia, hc.fecha_apertura, hc.estado,
            p.nombre AS paciente_nombre, p.apellido AS paciente_apellido, p.numero_documento,
            p.tipo_documento, p.fecha_nacimiento, p.grupo_sanguineo, p.eps
        FROM historias_clinicas hc
        JOIN pacientes p ON hc.paciente_id = p.id
        WHERE hc.id = :id");
    $stmt->execute([':id' => $historiaId]);
    $historia = $stmt->fetch();

    if (!$historia) {
        setAlerta('Historia clinica no encontrada.', 'danger');
        header('Location: index.php');
        exit;
    }

    $teethStmt = $db->prepare("SELECT pieza_dental, estado, caras_afectadas, color_estado, notas, updated_at FROM odontograma WHERE historia_id = :historia_id");
    $teethStmt->execute([':historia_id' => $historiaId]);
    $odontograma = [];
    while ($row = $teethStmt->fetch()) {
        $odontograma[$row['pieza_dental']] = [
            'estado' => $row['estado'],
            'caras_afectadas' => json_decode($row['caras_afectadas'] ?: '[]', true),
            'color_estado' => $row['color_estado'] ?: '#10b981',
            'notas' => $row['notas'],
            'updated_at' => $row['updated_at'],
        ];
    }
} catch (PDOException $e) {
    error_log('Odontograma cargar error: ' . $e->getMessage());
    setAlerta('No fue posible cargar el odontograma.', 'danger');
    header('Location: index.php');
    exit;
}

$piezaOrden = [
    '18','17','16','15','14','13','12','11',
    '21','22','23','24','25','26','27','28',
    '38','37','36','35','34','33','32','31',
    '41','42','43','44','45','46','47','48'
];

$estadoColores = [
    'sano' => '#10b981',
    'caries' => '#ef4444',
    'obturado' => '#3b82f6',
    'extraccion_indicada' => '#f59e0b',
    'ausente' => '#6b7280',
    'corona' => '#facc15',
    'protesis' => '#8b5cf6',
    'implante' => '#14b8a6',
    'fractura' => '#f97316',
    'tratamiento_conductos' => '#0ea5e9',
    'otro' => '#94a3b8',
];

$estadoIconos = [
    'sano' => 'bi-check2-circle',
    'caries' => 'bi-exclamation-octagon',
    'obturado' => 'bi-plus-circle',
    'extraccion_indicada' => 'bi-arrow-down-circle',
    'ausente' => 'bi-dash-circle',
    'corona' => 'bi-gem',
    'protesis' => 'bi-layers',
    'implante' => 'bi-nut',
    'fractura' => 'bi-lightning-charge',
    'tratamiento_conductos' => 'bi-bezier2',
    'otro' => 'bi-circle',
];

$estadoLabels = [
    'sano' => 'Sano',
    'caries' => 'Caries',
    'obturado' => 'Obturado',
    'extraccion_indicada' => 'Extraccion',
    'ausente' => 'Ausente',
    'corona' => 'Corona',
    'protesis' => 'Protesis',
    'implante' => 'Implante',
    'fractura' => 'Fractura',
    'tratamiento_conductos' => 'Endodoncia',
    'otro' => 'Otro',
];

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function estadoColor(string $estado, array $estadoColores): string {
    return $estadoColores[$estado] ?? '#10b981';
}

function obtenerEstado(array $odontograma, string $pieza): array {
    return $odontograma[$pieza] ?? ['estado' => 'sano', 'caras_afectadas' => [], 'color_estado' => '#10b981', 'notas' => '', 'updated_at' => null];
}

function formatDateSoft($value, string $fallback = 'Sin registro'): string {
    if (!$value) return $fallback;
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : $fallback;
}

function patientAge(?string $birthDate): string {
    if (!$birthDate) return 'Edad N/D';
    try {
        $birth = new DateTime($birthDate);
        return $birth->diff(new DateTime())->y . ' anos';
    } catch (Exception $e) {
        return 'Edad N/D';
    }
}

function toothTransform(string $pieza): string {
    $layout = [
        '18' => [132, 176, -26, .88], '17' => [196, 140, -22, .95], '16' => [268, 112, -16, 1.04], '15' => [346, 94, -9, .96],
        '14' => [424, 84, -4, .9], '13' => [505, 78, 0, .88], '12' => [586, 84, 5, .9], '11' => [666, 98, 10, .95],
        '21' => [746, 98, -10, .95], '22' => [826, 84, -5, .9], '23' => [907, 78, 0, .88], '24' => [988, 84, 4, .9],
        '25' => [1066, 94, 9, .96], '26' => [1144, 112, 16, 1.04], '27' => [1216, 140, 22, .95], '28' => [1280, 176, 26, .88],
        '38' => [132, 574, 26, .88], '37' => [196, 610, 22, .95], '36' => [268, 638, 16, 1.04], '35' => [346, 656, 9, .96],
        '34' => [424, 666, 4, .9], '33' => [505, 672, 0, .88], '32' => [586, 666, -5, .9], '31' => [666, 652, -10, .95],
        '41' => [746, 652, 10, .95], '42' => [826, 666, 5, .9], '43' => [907, 672, 0, .88], '44' => [988, 666, -4, .9],
        '45' => [1066, 656, -9, .96], '46' => [1144, 638, -16, 1.04], '47' => [1216, 610, -22, .95], '48' => [1280, 574, -26, .88],
    ];
    $p = $layout[$pieza] ?? [0, 0, 0, 1];
    return 'translate(' . $p[0] . ' ' . $p[1] . ') rotate(' . $p[2] . ') scale(' . $p[3] . ')';
}

$stats = array_fill_keys(array_keys($estadoColores), 0);
$ultimaActualizacion = null;
foreach ($piezaOrden as $pieza) {
    $data = obtenerEstado($odontograma, $pieza);
    $estado = $data['estado'] ?: 'sano';
    $stats[$estado] = ($stats[$estado] ?? 0) + 1;
    if (!empty($data['updated_at']) && (!$ultimaActualizacion || strtotime($data['updated_at']) > strtotime($ultimaActualizacion))) {
        $ultimaActualizacion = $data['updated_at'];
    }
}
$pacienteNombre = trim($historia['paciente_nombre'] . ' ' . $historia['paciente_apellido']);
$iniciales = strtoupper(mb_substr($historia['paciente_nombre'], 0, 1) . mb_substr($historia['paciente_apellido'], 0, 1));
$totalPiezas = count($piezaOrden);
$saludDental = (int) round(($stats['sano'] / max(1, $totalPiezas)) * 100);
?>
<?php $cssAdicional = 'odontograma.css'; ?>
<?php $jsAdicional = 'odontograma.js'; ?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 odontograma-page">
    <section class="odo-hero">
        <div class="odo-patient">
            <div class="odo-avatar"><?= h($iniciales ?: 'DS') ?></div>
            <div>
                <div class="odo-breadcrumb"><a href="ver.php?id=<?= $historiaId ?>">Historia clinica</a><i class="bi bi-chevron-right"></i><span>Odontograma</span></div>
                <h1><?= h($pacienteNombre) ?></h1>
                <div class="odo-hero-meta">
                    <span><i class="bi bi-person-vcard"></i><?= h(($historia['tipo_documento'] ?: 'Doc') . ' ' . $historia['numero_documento']) ?></span>
                    <span><i class="bi bi-calendar-heart"></i><?= h(patientAge($historia['fecha_nacimiento'])) ?></span>
                    <span><i class="bi bi-journal-medical"></i>HC <?= h($historia['numero_historia']) ?></span>
                    <span><i class="bi bi-clock-history"></i><?= h(formatDateSoft($ultimaActualizacion, 'Sin cambios')) ?></span>
                </div>
            </div>
        </div>
        <div class="odo-actions">
            <a href="ver.php?id=<?= $historiaId ?>" class="odo-btn odo-btn-ghost"><i class="bi bi-arrow-left"></i>Volver</a>
            <a href="adjuntos.php?historia_id=<?= $historiaId ?>" class="odo-btn odo-btn-ghost"><i class="bi bi-paperclip"></i>Adjuntos</a>
            <button type="button" class="odo-btn odo-btn-ghost" id="resetViewBtn"><i class="bi bi-arrow-counterclockwise"></i>Limpiar vista</button>
            <button type="button" class="odo-btn odo-btn-primary" id="viewToggleBtn" aria-pressed="false"><i class="bi bi-cube"></i>Vista 3D</button>
        </div>
    </section>

    <section class="odo-stats-grid" aria-label="Estadisticas del odontograma">
        <?php
        $statCards = [
            ['key' => 'sano', 'label' => 'Dientes sanos', 'icon' => 'bi-check2-circle'],
            ['key' => 'caries', 'label' => 'Caries', 'icon' => 'bi-exclamation-octagon'],
            ['key' => 'obturado', 'label' => 'Obturados', 'icon' => 'bi-plus-circle'],
            ['key' => 'ausente', 'label' => 'Ausentes', 'icon' => 'bi-dash-circle'],
            ['key' => 'tratamiento_conductos', 'label' => 'Endodoncias', 'icon' => 'bi-bezier2'],
        ];
        foreach ($statCards as $card):
            $count = (int) ($stats[$card['key']] ?? 0);
            $percent = (int) round(($count / max(1, $totalPiezas)) * 100);
        ?>
            <article class="odo-stat-card" data-stat="<?= h($card['key']) ?>" style="--state-color: <?= h($estadoColores[$card['key']]) ?>; --stat-percent: <?= $percent ?>%;">
                <span><i class="bi <?= h($card['icon']) ?>"></i></span>
                <div>
                    <strong data-stat-count><?= $count ?></strong>
                    <small><?= h($card['label']) ?></small>
                    <i></i>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <div class="odo-workspace">
        <aside class="odo-side-panel">
            <section class="odo-panel odo-selected-card">
                <div class="odo-panel-title">
                    <span><i class="bi bi-crosshair"></i></span>
                    <div>
                        <h2>Pieza seleccionada</h2>
                        <p>Informacion clinica en tiempo real</p>
                    </div>
                </div>
                <div class="odo-selected-tooth">
                    <div class="odo-selected-number" id="selectedToothNumber">--</div>
                    <div>
                        <span id="selectedToothState">Selecciona una pieza</span>
                        <strong id="selectedToothName">Arcada dental</strong>
                    </div>
                </div>
                <div class="odo-detail-list">
                    <div><span>Caras afectadas</span><strong id="selectedToothFaces">Sin registro</strong></div>
                    <div><span>Ultima modificacion</span><strong id="selectedToothUpdated">Sin registro</strong></div>
                    <div><span>Tratamientos</span><strong id="selectedToothTreatments">Sin procedimientos asociados</strong></div>
                </div>
                <p class="odo-selected-note" id="selectedToothNotes">Haz clic en una pieza para abrir el editor y actualizar su estado, caras afectadas y observaciones.</p>
                <?php if (can('historias.editar')): ?>
                    <button type="button" class="odo-btn odo-btn-primary w-100" id="editSelectedBtn" disabled><i class="bi bi-pencil-square"></i>Editar pieza</button>
                <?php endif; ?>
            </section>

            <section class="odo-panel">
                <div class="odo-panel-title">
                    <span><i class="bi bi-activity"></i></span>
                    <div>
                        <h2>Estado general</h2>
                        <p>Indice visual de salud dental</p>
                    </div>
                </div>
                <div class="odo-health-ring" style="--health: <?= $saludDental ?>;">
                    <strong id="healthScore"><?= $saludDental ?>%</strong>
                    <span>salud</span>
                </div>
                <div class="odo-progress-list">
                    <div><span>Sanos</span><i style="--value: <?= (int) round(($stats['sano'] / max(1, $totalPiezas)) * 100) ?>%; --bar: #10b981"></i></div>
                    <div><span>Caries</span><i style="--value: <?= (int) round(($stats['caries'] / max(1, $totalPiezas)) * 100) ?>%; --bar: #ef4444"></i></div>
                    <div><span>Restaurados</span><i style="--value: <?= (int) round(($stats['obturado'] / max(1, $totalPiezas)) * 100) ?>%; --bar: #3b82f6"></i></div>
                </div>
            </section>
        </aside>

        <main class="odo-stage-panel">
            <div class="odo-stage-toolbar">
                <div class="odo-segmented" role="group" aria-label="Modo de vista">
                    <button type="button" class="active" data-view-mode="2d"><i class="bi bi-grid-3x3-gap"></i>2D</button>
                    <button type="button" data-view-mode="3d"><i class="bi bi-cube"></i>3D</button>
                </div>
                <div class="odo-tool-buttons">
                    <button type="button" class="odo-icon-btn" id="zoomOutBtn" title="Alejar"><i class="bi bi-zoom-out"></i></button>
                    <input type="range" id="zoomRange" min="80" max="130" value="100" aria-label="Zoom del odontograma">
                    <button type="button" class="odo-icon-btn" id="zoomInBtn" title="Acercar"><i class="bi bi-zoom-in"></i></button>
                    <button type="button" class="odo-icon-btn" id="tiltLeftBtn" title="Rotar izquierda"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <button type="button" class="odo-icon-btn" id="tiltRightBtn" title="Rotar derecha"><i class="bi bi-arrow-clockwise"></i></button>
                </div>
            </div>

            <div class="odontograma-board" id="odontogramaBoard">
                <div class="odo-stage-glow"></div>
                <svg id="odontogramaSvg" viewBox="0 0 1412 760" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Odontograma interactivo premium">
                    <defs>
                        <linearGradient id="toothBase" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#ffffff"/>
                            <stop offset="48%" stop-color="#dfeaff"/>
                            <stop offset="100%" stop-color="#a9b9d8"/>
                        </linearGradient>
                        <linearGradient id="toothRoot" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0%" stop-color="#eaf2ff"/>
                            <stop offset="100%" stop-color="#8ea1c5"/>
                        </linearGradient>
                        <filter id="toothShadow" x="-40%" y="-40%" width="180%" height="180%">
                            <feDropShadow dx="0" dy="14" stdDeviation="10" flood-color="#00091f" flood-opacity=".34"/>
                        </filter>
                        <filter id="selectedGlow" x="-70%" y="-70%" width="240%" height="240%">
                            <feDropShadow dx="0" dy="0" stdDeviation="8" flood-color="#2FE0B0" flood-opacity=".9"/>
                        </filter>
                    </defs>

                    <path class="odo-arch-line" d="M112 224 C268 44 516 36 706 112 C896 36 1144 44 1300 224" />
                    <path class="odo-arch-line" d="M112 536 C268 716 516 724 706 648 C896 724 1144 716 1300 536" />
                    <text class="odo-arch-label" x="706" y="38" text-anchor="middle">Arcada superior</text>
                    <text class="odo-arch-label" x="706" y="742" text-anchor="middle">Arcada inferior</text>

                    <?php foreach ($piezaOrden as $pieza):
                        $estadoData = obtenerEstado($odontograma, $pieza);
                        $estado = $estadoData['estado'] ?: 'sano';
                        $color = $estadoData['color_estado'] ?: estadoColor($estado, $estadoColores);
                        $title = $estadoLabels[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
                        $caras = json_encode($estadoData['caras_afectadas'], JSON_UNESCAPED_UNICODE);
                        $notes = $estadoData['notas'] ?? '';
                        $updated = $estadoData['updated_at'] ?? '';
                        $isLower = in_array($pieza[0], ['3', '4'], true);
                    ?>
                        <g class="tooth tooth-state-<?= h($estado) ?>"
                           data-pieza="<?= h($pieza) ?>"
                           data-estado="<?= h($estado) ?>"
                           data-color="<?= h($color) ?>"
                           data-notas="<?= h($notes) ?>"
                           data-caras="<?= h($caras) ?>"
                           data-updated="<?= h($updated) ?>"
                           transform="<?= h(toothTransform($pieza)) ?>">
                            <ellipse class="tooth-platform" cx="0" cy="44" rx="42" ry="12"></ellipse>
                            <path class="tooth-root" d="<?= $isLower ? 'M-18,-2 C-28,26 -22,54 -4,72 C8,56 12,30 0,6 C14,30 18,56 32,72 C48,44 38,14 18,-2 Z' : 'M-18,2 C-28,-26 -22,-54 -4,-72 C8,-56 12,-30 0,-6 C14,-30 18,-56 32,-72 C48,-44 38,-14 18,2 Z' ?>"></path>
                            <path class="tooth-body" d="M-34,-28 C-22,-48 24,-48 36,-28 C48,-8 40,30 22,42 C8,52 -20,50 -34,36 C-50,20 -48,-8 -34,-28 Z"></path>
                            <path class="tooth-shine" d="M-19,-27 C-8,-35 12,-36 22,-28 C12,-20 -4,-18 -22,-20 Z"></path>
                            <path class="tooth-groove" d="M-18,6 C-6,-4 8,-4 22,8 M-10,23 C2,15 14,16 26,24"></path>
                            <circle class="tooth-state-marker" cx="31" cy="-27" r="9" fill="<?= h($color) ?>"></circle>
                            <circle class="tooth-state-halo" cx="31" cy="-27" r="15" style="--state-color: <?= h($color) ?>"></circle>
                            <text x="0" y="69" text-anchor="middle" class="tooth-label"><?= h($pieza) ?></text>
                            <title><?= h($pieza . ' - ' . $title) ?></title>
                        </g>
                    <?php endforeach; ?>
                </svg>
            </div>

            <div class="odo-legend">
                <?php foreach ($estadoLabels as $key => $label): ?>
                    <button type="button" class="odo-legend-item" data-filter-state="<?= h($key) ?>" style="--state-color: <?= h($estadoColores[$key]) ?>">
                        <i class="bi <?= h($estadoIconos[$key]) ?>"></i>
                        <span><?= h($label) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>

<div class="odo-tooltip" id="toothTooltip" role="tooltip" aria-hidden="true"></div>

<!-- Modal edicion pieza -->
<div class="modal fade odo-modal" id="modalEditarPieza" tabindex="-1" aria-labelledby="modalEditarPiezaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="odo-modal-title">
                    <span><i class="bi bi-brush"></i></span>
                    <div>
                        <h5 class="modal-title" id="modalEditarPiezaLabel">Editar pieza dental</h5>
                        <p>Actualiza el estado clinico y observaciones de la pieza.</p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarPieza" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" id="piezaDental" name="pieza_dental">
                    <input type="hidden" id="historiaId" name="historia_id" value="<?= $historiaId ?>">

                    <div class="odo-form-grid">
                        <label class="odo-field">
                            <span>Pieza dental</span>
                            <input type="text" id="piezaLabel" class="form-control" disabled>
                        </label>
                        <label class="odo-field">
                            <span>Estado</span>
                            <select id="estadoPieza" name="estado" class="form-select" required>
                                <option value="sano">Sano</option>
                                <option value="caries">Caries</option>
                                <option value="obturado">Obturado</option>
                                <option value="extraccion_indicada">Extraccion indicada</option>
                                <option value="ausente">Ausente</option>
                                <option value="corona">Corona</option>
                                <option value="protesis">Protesis</option>
                                <option value="implante">Implante</option>
                                <option value="fractura">Fractura</option>
                                <option value="tratamiento_conductos">Tratamiento de conductos</option>
                                <option value="otro">Otro</option>
                            </select>
                            <div class="invalid-feedback">Selecciona un estado.</div>
                        </label>
                    </div>

                    <div class="odo-face-section">
                        <span>Caras afectadas</span>
                        <div class="odo-face-grid">
                            <?php
                            $caras = ['mesial' => 'Mesial', 'distal' => 'Distal', 'oclusal' => 'Oclusal', 'vestibular' => 'Vestibular', 'palatino' => 'Palatino'];
                            foreach ($caras as $clave => $texto): ?>
                                <label class="odo-face-chip" for="cara_<?= h($clave) ?>">
                                    <input type="checkbox" id="cara_<?= h($clave) ?>" name="caras_afectadas[]" value="<?= h($clave) ?>">
                                    <span><?= h($texto) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <label class="odo-field">
                        <span>Notas clinicas</span>
                        <textarea id="notasPieza" name="notas" class="form-control" rows="4" placeholder="Describe hallazgos, tratamientos o recomendaciones para esta pieza."></textarea>
                    </label>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="odo-btn odo-btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="formEditarPieza" class="odo-btn odo-btn-primary">
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    <i class="bi bi-cloud-check"></i>
                    <span data-submit-label>Guardar cambio</span>
                </button>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
