<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('historias.ver');

$paginaTitulo = 'Detalle Historia Clinica';
$cssAdicional = 'historia-premium.css';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setAlerta('Historia clinica no valida.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT hc.*,
            p.numero_documento, p.tipo_documento, p.nombre AS paciente_nombre, p.apellido AS paciente_apellido,
            p.fecha_nacimiento, p.genero, p.telefono, p.email, p.eps, p.tipo_afiliacion,
            p.grupo_sanguineo, p.estado AS paciente_estado,
            u.nombre AS odontologo_nombre, u.apellido AS odontologo_apellido
        FROM historias_clinicas hc
        JOIN pacientes p ON hc.paciente_id = p.id
        JOIN usuarios u ON hc.odontologo_id = u.id
        WHERE hc.id = :id");
    $stmt->execute([':id' => $id]);
    $historia = $stmt->fetch();

    if (!$historia) {
        setAlerta('Historia clinica no encontrada.', 'danger');
        header('Location: index.php');
        exit;
    }

    $citasStmt = $db->prepare("SELECT fecha, hora_inicio, estado, motivo
        FROM citas
        WHERE paciente_id = :paciente_id
        ORDER BY fecha DESC, hora_inicio DESC
        LIMIT 4");
    $citasStmt->execute([':paciente_id' => $historia['paciente_id']]);
    $citas = $citasStmt->fetchAll();

    $statsStmt = $db->prepare("SELECT
            (SELECT COUNT(*) FROM citas WHERE paciente_id = :paciente_id) AS total_citas,
            (SELECT COUNT(*) FROM planes_tratamiento WHERE historia_id = :historia_id) AS total_planes,
            (SELECT COUNT(*) FROM odontograma WHERE historia_id = :historia_id_odo) AS piezas_registradas,
            (SELECT MAX(updated_at) FROM odontograma WHERE historia_id = :historia_id_odo_max) AS odontograma_actualizado");
    $statsStmt->execute([
        ':paciente_id' => $historia['paciente_id'],
        ':historia_id' => $id,
        ':historia_id_odo' => $id,
        ':historia_id_odo_max' => $id,
    ]);
    $stats = $statsStmt->fetch() ?: [];
} catch (PDOException $e) {
    error_log('Historia Clinica Ver error: ' . $e->getMessage());
    setAlerta('Error interno al cargar la historia clinica.', 'danger');
    header('Location: index.php');
    exit;
}

function h($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function emptyText($value): string {
    return $value === null || trim((string) $value) === '' ? 'No registrado' : h($value);
}

function richText($value): string {
    return $value === null || trim((string) $value) === ''
        ? '<span class="hc-empty-text">No registrado</span>'
        : nl2br(h($value));
}

function formatDateSoft($value, string $fallback = 'Sin fecha'): string {
    if (!$value) return $fallback;
    $timestamp = strtotime($value);
    return $timestamp ? date('d/m/Y', $timestamp) : $fallback;
}

function patientAge(?string $birthDate): string {
    if (!$birthDate) return 'Edad no registrada';
    try {
        $birth = new DateTime($birthDate);
        return $birth->diff(new DateTime())->y . ' anos';
    } catch (Exception $e) {
        return 'Edad no registrada';
    }
}

$pacienteNombre = trim($historia['paciente_nombre'] . ' ' . $historia['paciente_apellido']);
$iniciales = strtoupper(mb_substr($historia['paciente_nombre'], 0, 1) . mb_substr($historia['paciente_apellido'], 0, 1));
$estadoHistoria = $historia['estado'] === 'activa' ? 'activa' : 'archivada';
$habitosActivos = (int) $historia['habito_tabaco'] + (int) $historia['habito_alcohol'] + (int) $historia['habito_bruxismo'];
$ultimaCita = $citas[0] ?? null;

$clinicalSections = [
    ['icon' => 'bi-chat-left-text', 'title' => 'Motivo de consulta', 'text' => $historia['motivo_consulta']],
    ['icon' => 'bi-activity', 'title' => 'Enfermedad actual', 'text' => $historia['enfermedad_actual']],
    ['icon' => 'bi-heart-pulse', 'title' => 'Antecedentes medicos', 'text' => $historia['antecedentes_medicos']],
    ['icon' => 'bi-shield-check', 'title' => 'Antecedentes odontologicos', 'text' => $historia['antecedentes_odontologicos']],
    ['icon' => 'bi-capsule', 'title' => 'Medicamentos', 'text' => $historia['medicamentos_actuales']],
    ['icon' => 'bi-exclamation-triangle', 'title' => 'Alergias', 'text' => $historia['alergias'], 'tone' => trim((string) $historia['alergias']) !== '' ? 'danger' : ''],
    ['icon' => 'bi-person-lines-fill', 'title' => 'Examen extraoral', 'text' => $historia['examen_extraoral']],
    ['icon' => 'bi-emoji-smile', 'title' => 'Examen intraoral', 'text' => $historia['examen_intraoral']],
    ['icon' => 'bi-clipboard2-pulse', 'title' => 'Diagnostico', 'text' => $historia['diagnostico'], 'featured' => true],
    ['icon' => 'bi-diagram-3', 'title' => 'Plan de tratamiento', 'text' => $historia['plan_tratamiento_inicial'], 'featured' => true],
    ['icon' => 'bi-journal-text', 'title' => 'Observaciones', 'text' => $historia['observaciones'], 'wide' => true],
];
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 historia-page historia-detail-page">
    <section class="hc-detail-hero">
        <div class="hc-patient-identity">
            <div class="hc-avatar hc-avatar-xl"><?= h($iniciales ?: 'HC') ?></div>
            <div class="min-width-0">
                <span class="historia-kicker">Historia <?= h($historia['numero_historia']) ?></span>
                <h1><?= h($pacienteNombre) ?></h1>
                <div class="hc-hero-meta">
                    <span><i class="bi bi-person-vcard"></i><?= h($historia['tipo_documento'] . ' ' . $historia['numero_documento']) ?></span>
                    <span><i class="bi bi-calendar-heart"></i><?= h(patientAge($historia['fecha_nacimiento'])) ?></span>
                    <span><i class="bi bi-clock-history"></i>Apertura <?= h(formatDateSoft($historia['fecha_apertura'])) ?></span>
                </div>
            </div>
        </div>

        <div class="hc-hero-actions">
            <span class="history-status history-status-<?= h($estadoHistoria) ?>"><i></i><?= h(ucfirst($estadoHistoria)) ?></span>
            <a href="index.php" class="historia-secondary-btn"><i class="bi bi-arrow-left"></i>Volver</a>
            <?php if (can('historias.editar')): ?>
                <a href="editar.php?id=<?= $id ?>" class="historia-secondary-btn historia-btn-warm"><i class="bi bi-pencil-square"></i>Editar</a>
            <?php endif; ?>
            <?php if (can('historias.adjuntar')): ?>
                <a href="adjuntos.php?historia_id=<?= $id ?>" class="historia-secondary-btn"><i class="bi bi-paperclip"></i>Adjuntos</a>
            <?php endif; ?>
            <?php if (can('historias.ver')): ?>
                <a href="odontograma.php?historia_id=<?= $id ?>" class="historia-primary-btn"><i class="bi bi-grid-3x3-gap"></i>Odontograma</a>
            <?php endif; ?>
        </div>
    </section>

    <div class="hc-detail-layout">
        <aside class="hc-summary-column">
            <section class="hc-panel hc-patient-card">
                <div class="hc-profile-top">
                    <div class="hc-avatar"><?= h($iniciales ?: 'HC') ?></div>
                    <div>
                        <strong><?= h($pacienteNombre) ?></strong>
                        <span><?= h($historia['eps'] ?: 'EPS no registrada') ?></span>
                    </div>
                </div>
                <div class="hc-badge-row">
                    <span class="hc-soft-badge"><i class="bi bi-droplet-half"></i><?= h($historia['grupo_sanguineo'] ?: 'RH N/D') ?></span>
                    <span class="hc-soft-badge hc-soft-badge-success"><i class="bi bi-person-check"></i><?= h(ucfirst($historia['paciente_estado'] ?: 'activo')) ?></span>
                </div>
                <div class="hc-info-list">
                    <div><span>Telefono</span><strong><?= emptyText($historia['telefono']) ?></strong></div>
                    <div><span>Correo</span><strong><?= emptyText($historia['email']) ?></strong></div>
                    <div><span>Afiliacion</span><strong><?= emptyText(ucfirst((string) $historia['tipo_afiliacion'])) ?></strong></div>
                    <div><span>Ultima cita</span><strong><?= $ultimaCita ? h(formatDateSoft($ultimaCita['fecha'])) : 'Sin citas' ?></strong></div>
                </div>
            </section>

            <section class="hc-panel">
                <div class="hc-panel-title">
                    <span><i class="bi bi-bar-chart-line"></i></span>
                    <div>
                        <h2>Indicadores clinicos</h2>
                        <p>Resumen operacional del paciente</p>
                    </div>
                </div>
                <div class="hc-mini-stats">
                    <div><strong><?= (int) ($stats['total_citas'] ?? 0) ?></strong><span>Citas</span></div>
                    <div><strong><?= (int) ($stats['total_planes'] ?? 0) ?></strong><span>Planes</span></div>
                    <div><strong><?= (int) ($stats['piezas_registradas'] ?? 0) ?></strong><span>Piezas</span></div>
                    <div><strong><?= $habitosActivos ?></strong><span>Habitos</span></div>
                </div>
            </section>

            <section class="hc-panel hc-odontogram-card">
                <div class="hc-panel-title">
                    <span><i class="bi bi-grid-3x3-gap"></i></span>
                    <div>
                        <h2>Odontograma</h2>
                        <p><?= $stats['odontograma_actualizado'] ? 'Actualizado ' . h(formatDateSoft($stats['odontograma_actualizado'])) : 'Modulo clinico principal' ?></p>
                    </div>
                </div>
                <div class="hc-tooth-preview" aria-hidden="true">
                    <?php for ($i = 0; $i < 16; $i++): ?>
                        <span class="<?= $i % 5 === 0 ? 'is-active' : '' ?>"></span>
                    <?php endfor; ?>
                </div>
                <a href="odontograma.php?historia_id=<?= $id ?>" class="historia-primary-btn w-100"><i class="bi bi-arrow-up-right-circle"></i>Abrir odontograma</a>
            </section>
        </aside>

        <main class="hc-main-column">
            <section class="hc-panel">
                <div class="hc-panel-title">
                    <span><i class="bi bi-heart-pulse"></i></span>
                    <div>
                        <h2>Habitos y signos vitales</h2>
                        <p>Lectura rapida de factores clinicos relevantes</p>
                    </div>
                </div>
                <div class="hc-vitals-grid">
                    <div class="hc-habit-chip <?= $historia['habito_tabaco'] ? 'is-on' : '' ?>"><i class="bi bi-wind"></i><span>Tabaco</span><strong><?= $historia['habito_tabaco'] ? 'Si' : 'No' ?></strong></div>
                    <div class="hc-habit-chip <?= $historia['habito_alcohol'] ? 'is-on' : '' ?>"><i class="bi bi-cup-straw"></i><span>Alcohol</span><strong><?= $historia['habito_alcohol'] ? 'Si' : 'No' ?></strong></div>
                    <div class="hc-habit-chip <?= $historia['habito_bruxismo'] ? 'is-on' : '' ?>"><i class="bi bi-brightness-high"></i><span>Bruxismo</span><strong><?= $historia['habito_bruxismo'] ? 'Si' : 'No' ?></strong></div>
                    <div class="hc-vital-card"><span>PA</span><strong><?= emptyText($historia['presion_arterial']) ?></strong><small>Presion arterial</small></div>
                    <div class="hc-vital-card"><span>FC</span><strong><?= emptyText($historia['frecuencia_cardiaca']) ?></strong><small>Latidos por minuto</small></div>
                    <div class="hc-vital-card"><span>Temp.</span><strong><?= emptyText($historia['temperatura']) ?></strong><small>Grados centigrados</small></div>
                </div>
                <?php if (trim((string) $historia['otros_habitos']) !== ''): ?>
                    <div class="hc-note-strip"><i class="bi bi-info-circle"></i><?= richText($historia['otros_habitos']) ?></div>
                <?php endif; ?>
            </section>

            <section class="hc-clinical-grid">
                <?php foreach ($clinicalSections as $section): ?>
                    <article class="hc-clinical-card <?= !empty($section['wide']) ? 'hc-card-wide' : '' ?> <?= !empty($section['featured']) ? 'is-featured' : '' ?> <?= !empty($section['tone']) ? 'is-' . h($section['tone']) : '' ?>">
                        <div class="hc-card-head">
                            <span><i class="bi <?= h($section['icon']) ?>"></i></span>
                            <h2><?= h($section['title']) ?></h2>
                        </div>
                        <p><?= richText($section['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="hc-panel">
                <div class="hc-panel-title">
                    <span><i class="bi bi-clock-history"></i></span>
                    <div>
                        <h2>Timeline medico</h2>
                        <p>Evolucion, tratamientos y actividad reciente</p>
                    </div>
                </div>
                <div class="hc-timeline">
                    <div class="hc-timeline-item">
                        <span class="hc-timeline-dot"></span>
                        <div>
                            <strong>Apertura de historia clinica</strong>
                            <p>Registro creado el <?= h(formatDateSoft($historia['fecha_apertura'])) ?> por Dr(a). <?= h($historia['odontologo_nombre'] . ' ' . $historia['odontologo_apellido']) ?>.</p>
                        </div>
                    </div>
                    <?php foreach ($citas as $cita): ?>
                        <div class="hc-timeline-item">
                            <span class="hc-timeline-dot"></span>
                            <div>
                                <strong>Cita <?= h(str_replace('_', ' ', $cita['estado'])) ?></strong>
                                <p><?= h(formatDateSoft($cita['fecha']) . ' ' . substr((string) $cita['hora_inicio'], 0, 5)) ?> - <?= emptyText($cita['motivo']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="hc-timeline-item">
                        <span class="hc-timeline-dot"></span>
                        <div>
                            <strong>Ultima modificacion</strong>
                            <p><?= h(formatDateSoft($historia['updated_at'] ?? null, 'Sin modificaciones registradas')) ?></p>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
