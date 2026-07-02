<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('tratamientos.ver');

$paginaTitulo = 'Avance de Sesión';
$cssAdicional = 'tratamientos-premium.css';
$jsAdicional = 'tratamientos.js';
$sessionId = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);
if (!$sessionId) {
    setAlerta('Sesión no válida.', 'danger');
    header('Location: index.php');
    exit;
}

$errores = [];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT s.*, pt.nombre_plan, p.nombre AS paciente_nombre, p.apellido AS paciente_apellido FROM sesiones_tratamiento s JOIN planes_tratamiento pt ON s.plan_id = pt.id JOIN pacientes p ON pt.paciente_id = p.id WHERE s.id = :id");
    $stmt->execute([':id' => $sessionId]);
    $sesion = $stmt->fetch();
    if (!$sesion) {
        setAlerta('Sesión no encontrada.', 'danger');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Tratamientos Avance carga error: ' . $e->getMessage());
    setAlerta('Error interno al cargar la sesión.', 'danger');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();
    $progreso = filter_input(INPUT_POST, 'progreso', FILTER_VALIDATE_INT);
    $notas = trim($_POST['notas'] ?? '');
    $estado = trim($_POST['estado'] ?? $sesion['estado']);

    if ($progreso === false || $progreso < 0 || $progreso > 100) {
        $errores[] = 'El progreso debe ser un número entre 0 y 100.';
    }
    if (!in_array($estado, ['pendiente', 'realizada', 'cancelada'], true)) {
        $estado = $sesion['estado'];
    }

    if (empty($errores)) {
        try {
            $fechaRealizadaSql = $estado === 'realizada' ? 'COALESCE(fecha_realizada, CURDATE())' : 'fecha_realizada';
            $updateStmt = $db->prepare("UPDATE sesiones_tratamiento SET progreso = :progreso, notas = :notas, estado = :estado, fecha_realizada = $fechaRealizadaSql, fecha_ultimo_avance = NOW() WHERE id = :id");
            $updateStmt->execute([
                ':progreso' => $progreso,
                ':notas' => $notas,
                ':estado' => $estado,
                ':id' => $sessionId,
            ]);
            setAlerta('Avance de sesión guardado correctamente.');
            header('Location: sesiones.php?plan_id=' . $sesion['plan_id']);
            exit;
        } catch (PDOException $e) {
            error_log('Tratamientos Avance save error: ' . $e->getMessage());
            $errores[] = 'No fue posible guardar el avance. Intenta nuevamente.';
        }
    }
}

function value(array $data, string $name): string {
    return htmlspecialchars(trim((string) ($data[$name] ?? '')), ENT_QUOTES, 'UTF-8');
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 treatment-form-page">
    <!-- Session Detail Header -->
    <section class="treatment-form-hero mb-4">
        <div class="form-hero-main">
            <span class="treatment-eyebrow"><i class="bi bi-clipboard2-pulse"></i> Avance de sesión</span>
            <h1>Actualizar Progreso</h1>
            <p>Plan: <?= htmlspecialchars($sesion['nombre_plan']) ?> | Paciente: <?= htmlspecialchars($sesion['paciente_nombre'] . ' ' . $sesion['paciente_apellido']) ?></p>
        </div>
        <a href="sesiones.php?plan_id=<?= $sesion['plan_id'] ?>" class="btn treatment-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </section>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger shadow-sm mb-4">
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Session Progress Form -->
    <div class="treatment-form-container">
        <form method="POST" class="needs-validation" novalidate data-prevent-double">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <!-- Progress Section -->
            <section class="form-section">
                <div class="form-section-header">
                    <div class="form-section-icon"><i class="bi bi-graph-up"></i></div>
                    <div>
                        <span class="form-section-eyebrow">Progreso</span>
                        <h3>Estado de la sesión</h3>
                    </div>
                </div>
                <div class="form-section-body">
                    <div class="session-progress-display">
                        <div class="progress-value-display">
                            <span>Progreso actual</span>
                            <strong><?= value($_POST, 'progreso') ?: value($sesion, 'progreso') ?>%</strong>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?= value($_POST, 'progreso') ?: value($sesion, 'progreso') ?>%"></div>
                        </div>
                    </div>
                    <div class="treatment-form-grid">
                        <div class="treatment-field">
                            <label class="treatment-label">Progreso (%)</label>
                            <div class="treatment-input-wrapper">
                                <i class="bi bi-speedometer2"></i>
                                <input type="number" class="treatment-input" name="progreso" min="0" max="100" value="<?= value($_POST, 'progreso') ?: value($sesion, 'progreso') ?>" required>
                            </div>
                        </div>
                        <div class="treatment-field">
                            <label class="treatment-label">Estado</label>
                            <div class="treatment-input-wrapper">
                                <i class="bi bi-activity"></i>
                                <select class="treatment-select" name="estado">
                                    <?php foreach (['pendiente','realizada','cancelada'] as $estado): ?>
                                        <option value="<?= $estado ?>" <?= (value($_POST, 'estado') ?: $sesion['estado']) === $estado ? 'selected' : '' ?>><?= ucfirst($estado) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="treatment-field">
                            <label class="treatment-label">Último avance</label>
                            <div class="treatment-input-wrapper">
                                <i class="bi bi-clock-history"></i>
                                <input type="text" class="treatment-input" disabled value="<?= $sesion['fecha_ultimo_avance'] ? date('d/m/Y H:i', strtotime($sesion['fecha_ultimo_avance'])) : 'Sin avance registrado' ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Notes Section -->
            <section class="form-section">
                <div class="form-section-header">
                    <div class="form-section-icon"><i class="bi bi-chat-square-text"></i></div>
                    <div>
                        <span class="form-section-eyebrow">Notas</span>
                        <h3>Observaciones y comentarios</h3>
                    </div>
                </div>
                <div class="form-section-body">
                    <div class="treatment-field full-width">
                        <label class="treatment-label">Notas del avance</label>
                        <div class="treatment-input-wrapper textarea-wrapper">
                            <i class="bi bi-pencil-square"></i>
                            <textarea class="treatment-textarea" name="notas" rows="5" placeholder="Registra observaciones, cambios o notas importantes del avance..."><?= value($_POST, 'notas') ?: value($sesion, 'notas') ?></textarea>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Form Actions -->
            <div class="treatment-form-actions">
                <a href="sesiones.php?plan_id=<?= $sesion['plan_id'] ?>" class="btn treatment-secondary">
                    <i class="bi bi-x-circle"></i> Cancelar
                </a>
                <button type="submit" class="btn treatment-primary">
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    <i class="bi bi-save"></i> Guardar avance
                </button>
            </div>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
