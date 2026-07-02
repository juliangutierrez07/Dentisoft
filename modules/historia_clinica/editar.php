<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('historias.editar');

$paginaTitulo = 'Editar Historia Clinica';
$cssAdicional = 'historia-premium.css';
$jsAdicional = 'historia_clinica.js';
$errores = [];
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setAlerta('Historia clinica no valida.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT hc.*, p.nombre AS paciente_nombre, p.apellido AS paciente_apellido, p.numero_documento
        FROM historias_clinicas hc
        JOIN pacientes p ON p.id = hc.paciente_id
        WHERE hc.id = :id");
    $stmt->execute([':id' => $id]);
    $historia = $stmt->fetch();

    if (!$historia) {
        setAlerta('Historia clinica no encontrada.', 'danger');
        header('Location: index.php');
        exit;
    }

    $pacientesStmt = $db->query("SELECT id, numero_documento, nombre, apellido FROM pacientes WHERE estado = 'activo' ORDER BY nombre, apellido");
    $pacientes = $pacientesStmt->fetchAll();
    $odontologosStmt = $db->prepare("SELECT u.id, u.nombre, u.apellido FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'odontologo' AND u.estado = 'activo' ORDER BY u.nombre, u.apellido");
    $odontologosStmt->execute();
    $odontologos = $odontologosStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Historia Clinica Editar carga error: ' . $e->getMessage());
    setAlerta('Error interno al cargar la historia clinica.', 'danger');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);
    $odontologoId = filter_input(INPUT_POST, 'odontologo_id', FILTER_VALIDATE_INT);
    $fechaApertura = trim($_POST['fecha_apertura'] ?? '');

    if (!$pacienteId) {
        $errores[] = 'Selecciona un paciente valido.';
    }
    if (!$odontologoId) {
        $errores[] = 'Selecciona un odontologo valido.';
    }
    if ($fechaApertura === '') {
        $errores[] = 'La fecha de apertura es obligatoria.';
    }

    if (empty($errores)) {
        try {
            $stmt = $db->prepare("UPDATE historias_clinicas SET
                paciente_id = :paciente_id,
                odontologo_id = :odontologo_id,
                fecha_apertura = :fecha_apertura,
                motivo_consulta = :motivo_consulta,
                enfermedad_actual = :enfermedad_actual,
                antecedentes_medicos = :antecedentes_medicos,
                antecedentes_odontologicos = :antecedentes_odontologicos,
                medicamentos_actuales = :medicamentos_actuales,
                alergias = :alergias,
                habito_tabaco = :habito_tabaco,
                habito_alcohol = :habito_alcohol,
                habito_bruxismo = :habito_bruxismo,
                otros_habitos = :otros_habitos,
                presion_arterial = :presion_arterial,
                frecuencia_cardiaca = :frecuencia_cardiaca,
                temperatura = :temperatura,
                examen_extraoral = :examen_extraoral,
                examen_intraoral = :examen_intraoral,
                diagnostico = :diagnostico,
                plan_tratamiento_inicial = :plan_tratamiento_inicial,
                observaciones = :observaciones
                WHERE id = :id");

            $stmt->execute([
                ':paciente_id' => $pacienteId,
                ':odontologo_id' => $odontologoId,
                ':fecha_apertura' => $fechaApertura,
                ':motivo_consulta' => trim($_POST['motivo_consulta'] ?? ''),
                ':enfermedad_actual' => trim($_POST['enfermedad_actual'] ?? ''),
                ':antecedentes_medicos' => trim($_POST['antecedentes_medicos'] ?? ''),
                ':antecedentes_odontologicos' => trim($_POST['antecedentes_odontologicos'] ?? ''),
                ':medicamentos_actuales' => trim($_POST['medicamentos_actuales'] ?? ''),
                ':alergias' => trim($_POST['alergias'] ?? ''),
                ':habito_tabaco' => isset($_POST['habito_tabaco']) ? 1 : 0,
                ':habito_alcohol' => isset($_POST['habito_alcohol']) ? 1 : 0,
                ':habito_bruxismo' => isset($_POST['habito_bruxismo']) ? 1 : 0,
                ':otros_habitos' => trim($_POST['otros_habitos'] ?? ''),
                ':presion_arterial' => trim($_POST['presion_arterial'] ?? ''),
                ':frecuencia_cardiaca' => filter_input(INPUT_POST, 'frecuencia_cardiaca', FILTER_VALIDATE_INT) ?: null,
                ':temperatura' => filter_input(INPUT_POST, 'temperatura', FILTER_VALIDATE_FLOAT) ?: null,
                ':examen_extraoral' => trim($_POST['examen_extraoral'] ?? ''),
                ':examen_intraoral' => trim($_POST['examen_intraoral'] ?? ''),
                ':diagnostico' => trim($_POST['diagnostico'] ?? ''),
                ':plan_tratamiento_inicial' => trim($_POST['plan_tratamiento_inicial'] ?? ''),
                ':observaciones' => trim($_POST['observaciones'] ?? ''),
                ':id' => $id,
            ]);

            setAlerta('Historia clinica actualizada correctamente.');
            header('Location: ver.php?id=' . $id);
            exit;
        } catch (PDOException $e) {
            error_log('Historia Clinica Editar update error: ' . $e->getMessage());
            $errores[] = 'No fue posible actualizar la historia clinica. Intenta nuevamente.';
        }
    }

    foreach ($historia as $key => $value) {
        if (isset($_POST[$key])) {
            $historia[$key] = $_POST[$key];
        }
    }
}

function value(array $historia, string $name): string {
    return htmlspecialchars(trim((string) ($historia[$name] ?? '')), ENT_QUOTES, 'UTF-8');
}

function selectedLabel(array $items, $id, string $prefix = ''): string {
    foreach ($items as $item) {
        if ((string) $item['id'] === (string) $id) {
            return htmlspecialchars($prefix . trim(($item['numero_documento'] ?? '') . ' ' . $item['nombre'] . ' ' . $item['apellido']), ENT_QUOTES, 'UTF-8');
        }
    }
    return 'Sin asignar';
}

$pacienteNombre = trim(($historia['paciente_nombre'] ?? '') . ' ' . ($historia['paciente_apellido'] ?? ''));
$iniciales = strtoupper(mb_substr($historia['paciente_nombre'] ?? 'H', 0, 1) . mb_substr($historia['paciente_apellido'] ?? 'C', 0, 1));
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 history-form-page history-editor-page">
    <section class="history-form-hero hc-editor-hero">
        <div class="hc-patient-identity">
            <div class="hc-avatar hc-avatar-xl"><?= htmlspecialchars($iniciales ?: 'HC') ?></div>
            <div>
                <span class="historia-kicker">Edicion clinica</span>
                <h1>Editar Historia Clinica</h1>
                <p><?= htmlspecialchars($pacienteNombre ?: 'Paciente') ?> - Historia <?= htmlspecialchars($historia['numero_historia']) ?></p>
            </div>
        </div>
        <div class="hc-hero-actions">
            <span class="hc-save-state" data-save-state><i class="bi bi-check2-circle"></i>Sin cambios</span>
            <a href="ver.php?id=<?= $id ?>" class="historia-secondary-btn"><i class="bi bi-arrow-left"></i>Volver</a>
        </div>
    </section>

    <?php if (!empty($errores)): ?>
        <div class="history-form-alert">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>Revisa los campos marcados</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate class="needs-validation hc-editor-shell" data-prevent-double data-history-editor>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <div class="hc-editor-tabs" role="tablist" aria-label="Secciones de historia clinica">
            <button class="active" type="button" data-hc-tab="general"><i class="bi bi-person-vcard"></i>General</button>
            <button type="button" data-hc-tab="antecedentes"><i class="bi bi-heart-pulse"></i>Antecedentes</button>
            <button type="button" data-hc-tab="habitos"><i class="bi bi-sliders2"></i>Habitos</button>
            <button type="button" data-hc-tab="examen"><i class="bi bi-search-heart"></i>Examen</button>
            <button type="button" data-hc-tab="diagnostico"><i class="bi bi-clipboard2-pulse"></i>Diagnostico</button>
            <button type="button" data-hc-tab="observaciones"><i class="bi bi-journal-text"></i>Notas</button>
        </div>

        <div class="history-form-panel hc-editor-panel">
            <section class="history-form-section hc-tab-panel is-active" data-hc-panel="general">
                <div class="history-section-title">
                    <span><i class="bi bi-person-vcard"></i></span>
                    <div>
                        <h2>Informacion general</h2>
                        <p>Datos base de la historia clinica y responsable tratante.</p>
                    </div>
                </div>
                <div class="history-form-grid">
                    <label class="history-field hc-floating-field">
                        <input type="text" value="<?= htmlspecialchars($historia['numero_historia']) ?>" disabled placeholder=" ">
                        <span>Numero de historia</span>
                    </label>
                    <label class="history-field hc-floating-field history-wide">
                        <select name="paciente_id" required>
                            <option value="">Selecciona un paciente</option>
                            <?php foreach ($pacientes as $paciente): ?>
                                <option value="<?= $paciente['id'] ?>" <?= $historia['paciente_id'] == $paciente['id'] ? 'selected' : ''?>>
                                    <?= htmlspecialchars($paciente['numero_documento'] . ' - ' . $paciente['nombre'] . ' ' . $paciente['apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span>Paciente *</span>
                        <div class="invalid-feedback">El paciente es obligatorio.</div>
                    </label>
                    <label class="history-field hc-floating-field history-wide">
                        <select name="odontologo_id" required>
                            <option value="">Selecciona un odontologo</option>
                            <?php foreach ($odontologos as $odontologo): ?>
                                <option value="<?= $odontologo['id'] ?>" <?= $historia['odontologo_id'] == $odontologo['id'] ? 'selected' : ''?>>
                                    <?= htmlspecialchars($odontologo['nombre'] . ' ' . $odontologo['apellido']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span>Odontologo *</span>
                        <div class="invalid-feedback">El odontologo es obligatorio.</div>
                    </label>
                    <label class="history-field hc-floating-field">
                        <input type="date" name="fecha_apertura" value="<?= value($historia, 'fecha_apertura') ?>" required placeholder=" ">
                        <span>Fecha de apertura *</span>
                        <div class="invalid-feedback">La fecha es obligatoria.</div>
                    </label>
                </div>
            </section>

            <section class="history-form-section hc-tab-panel" data-hc-panel="antecedentes">
                <div class="history-section-title">
                    <span><i class="bi bi-heart-pulse"></i></span>
                    <div>
                        <h2>Antecedentes</h2>
                        <p>Contexto medico, odontologico y factores de riesgo.</p>
                    </div>
                </div>
                <div class="history-form-grid">
                    <label class="history-field hc-floating-field history-half">
                        <textarea name="motivo_consulta" rows="3" placeholder=" "><?= value($historia, 'motivo_consulta') ?></textarea>
                        <span>Motivo de consulta</span>
                    </label>
                    <label class="history-field hc-floating-field history-half">
                        <textarea name="enfermedad_actual" rows="3" placeholder=" "><?= value($historia, 'enfermedad_actual') ?></textarea>
                        <span>Enfermedad actual</span>
                    </label>
                    <label class="history-field hc-floating-field history-half">
                        <textarea name="antecedentes_medicos" rows="3" placeholder=" "><?= value($historia, 'antecedentes_medicos') ?></textarea>
                        <span>Antecedentes medicos</span>
                    </label>
                    <label class="history-field hc-floating-field history-half">
                        <textarea name="antecedentes_odontologicos" rows="3" placeholder=" "><?= value($historia, 'antecedentes_odontologicos') ?></textarea>
                        <span>Antecedentes odontologicos</span>
                    </label>
                    <label class="history-field hc-floating-field history-half">
                        <textarea name="medicamentos_actuales" rows="3" placeholder=" "><?= value($historia, 'medicamentos_actuales') ?></textarea>
                        <span>Medicamentos actuales</span>
                    </label>
                    <label class="history-field hc-floating-field history-half">
                        <textarea name="alergias" rows="3" placeholder=" "><?= value($historia, 'alergias') ?></textarea>
                        <span>Alergias</span>
                    </label>
                </div>
            </section>

            <section class="history-form-section hc-tab-panel" data-hc-panel="habitos">
                <div class="history-section-title">
                    <span><i class="bi bi-sliders2"></i></span>
                    <div>
                        <h2>Habitos y signos vitales</h2>
                        <p>Interruptores rapidos y mediciones clinicas clave.</p>
                    </div>
                </div>
                <div class="hc-switch-grid">
                    <label class="hc-premium-switch">
                        <input type="checkbox" name="habito_tabaco" <?= $historia['habito_tabaco'] ? 'checked' : '' ?>>
                        <span></span>
                        <strong>Tabaco</strong>
                    </label>
                    <label class="hc-premium-switch">
                        <input type="checkbox" name="habito_alcohol" <?= $historia['habito_alcohol'] ? 'checked' : '' ?>>
                        <span></span>
                        <strong>Alcohol</strong>
                    </label>
                    <label class="hc-premium-switch">
                        <input type="checkbox" name="habito_bruxismo" <?= $historia['habito_bruxismo'] ? 'checked' : '' ?>>
                        <span></span>
                        <strong>Bruxismo</strong>
                    </label>
                </div>
                <div class="history-form-grid mt-3">
                    <label class="history-field hc-floating-field history-full">
                        <textarea name="otros_habitos" rows="2" placeholder=" "><?= value($historia, 'otros_habitos') ?></textarea>
                        <span>Otros habitos</span>
                    </label>
                    <label class="history-field hc-floating-field">
                        <input type="text" name="presion_arterial" value="<?= value($historia, 'presion_arterial') ?>" placeholder=" ">
                        <span>Presion arterial</span>
                    </label>
                    <label class="history-field hc-floating-field">
                        <input type="number" name="frecuencia_cardiaca" min="0" value="<?= value($historia, 'frecuencia_cardiaca') ?>" placeholder=" ">
                        <span>Frecuencia cardiaca</span>
                    </label>
                    <label class="history-field hc-floating-field">
                        <input type="number" step="0.1" name="temperatura" value="<?= value($historia, 'temperatura') ?>" placeholder=" ">
                        <span>Temperatura (C)</span>
                    </label>
                </div>
            </section>

            <section class="history-form-section hc-tab-panel" data-hc-panel="examen">
                <div class="history-section-title">
                    <span><i class="bi bi-search-heart"></i></span>
                    <div>
                        <h2>Examen clinico</h2>
                        <p>Hallazgos extraorales e intraorales del paciente.</p>
                    </div>
                </div>
                <div class="history-form-grid">
                    <label class="history-field hc-floating-field history-half">
                        <textarea name="examen_extraoral" rows="5" placeholder=" "><?= value($historia, 'examen_extraoral') ?></textarea>
                        <span>Examen extraoral</span>
                    </label>
                    <label class="history-field hc-floating-field history-half">
                        <textarea name="examen_intraoral" rows="5" placeholder=" "><?= value($historia, 'examen_intraoral') ?></textarea>
                        <span>Examen intraoral</span>
                    </label>
                </div>
            </section>

            <section class="history-form-section hc-tab-panel" data-hc-panel="diagnostico">
                <div class="history-section-title">
                    <span><i class="bi bi-clipboard2-pulse"></i></span>
                    <div>
                        <h2>Diagnostico y tratamiento</h2>
                        <p>Conclusion clinica y plan inicial de manejo.</p>
                    </div>
                </div>
                <div class="history-form-grid">
                    <label class="history-field hc-floating-field history-half">
                        <textarea name="diagnostico" rows="5" placeholder=" "><?= value($historia, 'diagnostico') ?></textarea>
                        <span>Diagnostico</span>
                    </label>
                    <label class="history-field hc-floating-field history-half">
                        <textarea name="plan_tratamiento_inicial" rows="5" placeholder=" "><?= value($historia, 'plan_tratamiento_inicial') ?></textarea>
                        <span>Plan de tratamiento inicial</span>
                    </label>
                </div>
            </section>

            <section class="history-form-section hc-tab-panel" data-hc-panel="observaciones">
                <div class="history-section-title">
                    <span><i class="bi bi-journal-text"></i></span>
                    <div>
                        <h2>Observaciones</h2>
                        <p>Notas complementarias visibles en el detalle clinico.</p>
                    </div>
                </div>
                <div class="history-form-grid">
                    <label class="history-field hc-floating-field history-full">
                        <textarea name="observaciones" rows="7" placeholder=" "><?= value($historia, 'observaciones') ?></textarea>
                        <span>Observaciones</span>
                    </label>
                </div>
            </section>
        </div>

        <div class="hc-editor-footer">
            <div>
                <strong><?= htmlspecialchars(selectedLabel($pacientes, $historia['paciente_id'])) ?></strong>
                <span>Los cambios se guardan al enviar el formulario.</span>
            </div>
            <div class="hc-footer-actions">
                <a href="ver.php?id=<?= $id ?>" class="historia-secondary-btn">Cancelar</a>
                <button type="submit" class="historia-primary-btn">
                    <span class="spinner-border spinner-border-sm d-none" role="status"></span>
                    <i class="bi bi-check2-circle"></i>
                    <span data-submit-label>Guardar cambios</span>
                </button>
            </div>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
