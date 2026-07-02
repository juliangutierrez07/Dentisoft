<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('historias.crear');

$paginaTitulo = 'Nueva Historia Clínica';
$cssAdicional = 'historia-premium.css';
$jsAdicional = 'historia_clinica.js';
$errores = [];

try {
    $db = getDB();
    $pacientes = $db->query("SELECT id, nombre, apellido, numero_documento FROM pacientes WHERE estado = 'activo' ORDER BY nombre, apellido")->fetchAll();
    $odontologosStmt = $db->prepare("SELECT u.id, u.nombre, u.apellido FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'odontologo' AND u.estado = 'activo' ORDER BY u.nombre, u.apellido");
    $odontologosStmt->execute();
    $odontologos = $odontologosStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Historia Clínica Crear carga error: ' . $e->getMessage());
    $pacientes = [];
    $odontologos = [];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCSRF();

    $pacienteId = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $odontologoId = filter_input(INPUT_POST, 'odontologo_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $fechaApertura = trim((string) ($_POST['fecha_apertura'] ?? ''));
    $motivoConsulta = trim((string) ($_POST['motivo_consulta'] ?? ''));
    $numeroHistoria = sprintf('HC-%s-%04d', date('Y'), random_int(1, 9999));

    if (!$pacienteId) $errores[] = 'Selecciona un paciente válido.';
    if (!$odontologoId) $errores[] = 'Selecciona un odontólogo válido.';
    if ($fechaApertura === '') $errores[] = 'La fecha de apertura es obligatoria.';

    $archivos = $_FILES['archivos'] ?? null;
    if ($archivos && is_array($archivos['name'])) {
        foreach ($archivos['name'] as $index => $name) {
            if ($name === '') continue;
            if (($archivos['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errores[] = 'Uno de los adjuntos no se pudo leer correctamente.';
                continue;
            }
            $tmpName = $archivos['tmp_name'][$index] ?? '';
            $mime = $tmpName ? mime_content_type($tmpName) : '';
            if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
                $errores[] = 'Solo se permiten adjuntos JPG, PNG o WEBP.';
            }
            if (($archivos['size'][$index] ?? 0) > MAX_FILE_SIZE) {
                $errores[] = 'Uno de los adjuntos supera el límite de 5 MB.';
            }
        }
    }

    if (empty($errores)) {
        try {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO historias_clinicas (
                paciente_id, odontologo_id, numero_historia, fecha_apertura,
                motivo_consulta, enfermedad_actual, antecedentes_medicos,
                antecedentes_odontologicos, medicamentos_actuales, alergias,
                habito_tabaco, habito_alcohol, habito_bruxismo, otros_habitos,
                presion_arterial, frecuencia_cardiaca, temperatura,
                examen_extraoral, examen_intraoral, diagnostico,
                plan_tratamiento_inicial, observaciones
            ) VALUES (
                :paciente_id, :odontologo_id, :numero_historia, :fecha_apertura,
                :motivo_consulta, :enfermedad_actual, :antecedentes_medicos,
                :antecedentes_odontologicos, :medicamentos_actuales, :alergias,
                :habito_tabaco, :habito_alcohol, :habito_bruxismo, :otros_habitos,
                :presion_arterial, :frecuencia_cardiaca, :temperatura,
                :examen_extraoral, :examen_intraoral, :diagnostico,
                :plan_tratamiento_inicial, :observaciones
            )");

            $stmt->execute([
                ':paciente_id' => $pacienteId,
                ':odontologo_id' => $odontologoId,
                ':numero_historia' => $numeroHistoria,
                ':fecha_apertura' => $fechaApertura,
                ':motivo_consulta' => $motivoConsulta,
                ':enfermedad_actual' => trim((string) ($_POST['enfermedad_actual'] ?? '')),
                ':antecedentes_medicos' => trim((string) ($_POST['antecedentes_medicos'] ?? '')),
                ':antecedentes_odontologicos' => trim((string) ($_POST['antecedentes_odontologicos'] ?? '')),
                ':medicamentos_actuales' => trim((string) ($_POST['medicamentos_actuales'] ?? '')),
                ':alergias' => trim((string) ($_POST['alergias'] ?? '')),
                ':habito_tabaco' => isset($_POST['habito_tabaco']) ? 1 : 0,
                ':habito_alcohol' => isset($_POST['habito_alcohol']) ? 1 : 0,
                ':habito_bruxismo' => isset($_POST['habito_bruxismo']) ? 1 : 0,
                ':otros_habitos' => trim((string) ($_POST['otros_habitos'] ?? '')),
                ':presion_arterial' => trim((string) ($_POST['presion_arterial'] ?? '')),
                ':frecuencia_cardiaca' => filter_input(INPUT_POST, 'frecuencia_cardiaca', FILTER_VALIDATE_INT) ?: null,
                ':temperatura' => filter_input(INPUT_POST, 'temperatura', FILTER_VALIDATE_FLOAT) ?: null,
                ':examen_extraoral' => trim((string) ($_POST['examen_extraoral'] ?? '')),
                ':examen_intraoral' => trim((string) ($_POST['examen_intraoral'] ?? '')),
                ':diagnostico' => trim((string) ($_POST['diagnostico'] ?? '')),
                ':plan_tratamiento_inicial' => trim((string) ($_POST['plan_tratamiento_inicial'] ?? '')),
                ':observaciones' => trim((string) ($_POST['observaciones'] ?? '')),
            ]);

            $historiaId = (int) $db->lastInsertId();

            if ($archivos && is_array($archivos['name'])) {
                if (!is_dir(FOTOS_DIR)) mkdir(FOTOS_DIR, 0755, true);
                foreach ($archivos['name'] as $index => $name) {
                    if ($name === '') continue;
                    $safeName = uniqid('hc_', true) . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($name));
                    $dest = FOTOS_DIR . '/' . $safeName;
                    if (move_uploaded_file($archivos['tmp_name'][$index], $dest)) {
                        $rutaRelativa = str_replace(ROOT_PATH, '', $dest);
                        $adj = $db->prepare("INSERT INTO imagenes_clinicas (historia_id, tipo, nombre_archivo, ruta_archivo, tamanio_bytes, descripcion, usuario_id) VALUES (:historia_id, 'foto_clinica', :nombre, :ruta, :size, :descripcion, :usuario_id)");
                        $adj->execute([
                            ':historia_id' => $historiaId,
                            ':nombre' => $name,
                            ':ruta' => $rutaRelativa,
                            ':size' => (int) $archivos['size'][$index],
                            ':descripcion' => 'Adjunto inicial de historia clínica',
                            ':usuario_id' => $_SESSION['usuario_id'] ?? null,
                        ]);
                    }
                }
            }

            $db->commit();
            setAlerta('Historia clínica registrada correctamente.');
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('Historia Clínica Crear insert error: ' . $e->getMessage());
            $errores[] = 'No fue posible guardar la historia clínica. Intenta nuevamente.';
        }
    }
}

function oldValue(string $name): string {
    return htmlspecialchars(trim((string) ($_POST[$name] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function selectedOld(string $name, mixed $value): string {
    return (string) ($_POST[$name] ?? '') === (string) $value ? 'selected' : '';
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="history-form-page">
    <header class="history-form-hero">
        <div>
            <span class="historia-kicker">Apertura de expediente</span>
            <h1>Nueva Historia Clínica</h1>
            <p>Registra la valoración inicial del paciente con una estructura clínica clara, ordenada y lista para seguimiento.</p>
        </div>
        <a href="index.php" class="historia-secondary-btn"><i class="bi bi-arrow-left"></i><span>Volver</span></a>
    </header>

    <?php if (!empty($errores)): ?>
        <div class="history-form-alert">
            <i class="bi bi-exclamation-triangle"></i>
            <div><strong>Revisa la información</strong><ul><?php foreach ($errores as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li><?php endforeach; ?></ul></div>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate class="history-form-panel needs-validation" data-prevent-double>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <section class="history-form-section">
            <div class="history-section-title"><span><i class="bi bi-person-vcard"></i></span><div><h2>Información del paciente</h2><p>Paciente, profesional responsable y fecha de apertura.</p></div></div>
            <div class="history-form-grid">
                <label class="history-field history-wide"><span>Paciente *</span><select name="paciente_id" required><option value="">Selecciona un paciente</option><?php foreach ($pacientes as $paciente): ?><option value="<?= $paciente['id'] ?>" <?= selectedOld('paciente_id', $paciente['id']) ?>><?= htmlspecialchars($paciente['numero_documento'] . ' - ' . $paciente['nombre'] . ' ' . $paciente['apellido'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><div class="invalid-feedback">El paciente es obligatorio.</div></label>
                <label class="history-field"><span>Odontólogo *</span><select name="odontologo_id" required><option value="">Selecciona un odontólogo</option><?php foreach ($odontologos as $odontologo): ?><option value="<?= $odontologo['id'] ?>" <?= selectedOld('odontologo_id', $odontologo['id']) ?>><?= htmlspecialchars($odontologo['nombre'] . ' ' . $odontologo['apellido'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><div class="invalid-feedback">El odontólogo es obligatorio.</div></label>
                <label class="history-field"><span>Fecha de apertura *</span><input type="date" name="fecha_apertura" value="<?= oldValue('fecha_apertura') ?>" required><div class="invalid-feedback">La fecha es obligatoria.</div></label>
            </div>
        </section>

        <section class="history-form-section">
            <div class="history-section-title"><span><i class="bi bi-chat-left-text"></i></span><div><h2>Motivo de consulta</h2><p>Describe la razón principal y la enfermedad actual.</p></div></div>
            <div class="history-form-grid"><label class="history-field history-half"><span>Motivo de consulta</span><textarea name="motivo_consulta" rows="4" placeholder="Dolor, control, estética, urgencia..."><?= oldValue('motivo_consulta') ?></textarea></label><label class="history-field history-half"><span>Enfermedad actual</span><textarea name="enfermedad_actual" rows="4" placeholder="Evolución, intensidad, síntomas asociados..."><?= oldValue('enfermedad_actual') ?></textarea></label></div>
        </section>

        <section class="history-form-section">
            <div class="history-section-title"><span><i class="bi bi-clipboard2-pulse"></i></span><div><h2>Evaluación clínica</h2><p>Antecedentes, hábitos y signos clínicos relevantes.</p></div></div>
            <div class="history-form-grid">
                <label class="history-field history-half"><span>Antecedentes médicos</span><textarea name="antecedentes_medicos" rows="3"><?= oldValue('antecedentes_medicos') ?></textarea></label>
                <label class="history-field history-half"><span>Antecedentes odontológicos</span><textarea name="antecedentes_odontologicos" rows="3"><?= oldValue('antecedentes_odontologicos') ?></textarea></label>
                <label class="history-field history-half"><span>Medicamentos actuales</span><textarea name="medicamentos_actuales" rows="3"><?= oldValue('medicamentos_actuales') ?></textarea></label>
                <label class="history-field history-half"><span>Alergias</span><textarea name="alergias" rows="3"><?= oldValue('alergias') ?></textarea></label>
                <div class="history-toggle-group">
                    <label><input type="checkbox" name="habito_tabaco" <?= isset($_POST['habito_tabaco']) ? 'checked' : '' ?>><span>Tabaco</span></label>
                    <label><input type="checkbox" name="habito_alcohol" <?= isset($_POST['habito_alcohol']) ? 'checked' : '' ?>><span>Alcohol</span></label>
                    <label><input type="checkbox" name="habito_bruxismo" <?= isset($_POST['habito_bruxismo']) ? 'checked' : '' ?>><span>Bruxismo</span></label>
                </div>
                <label class="history-field history-full"><span>Otros hábitos</span><textarea name="otros_habitos" rows="2"><?= oldValue('otros_habitos') ?></textarea></label>
                <label class="history-field"><span>Presión arterial</span><input type="text" name="presion_arterial" value="<?= oldValue('presion_arterial') ?>" placeholder="120/80"></label>
                <label class="history-field"><span>Frecuencia cardiaca</span><input type="number" name="frecuencia_cardiaca" min="0" value="<?= oldValue('frecuencia_cardiaca') ?>" placeholder="72"></label>
                <label class="history-field"><span>Temperatura (°C)</span><input type="number" step="0.1" name="temperatura" value="<?= oldValue('temperatura') ?>" placeholder="36.5"></label>
                <label class="history-field history-half"><span>Examen extraoral</span><textarea name="examen_extraoral" rows="3"><?= oldValue('examen_extraoral') ?></textarea></label>
                <label class="history-field history-half"><span>Examen intraoral</span><textarea name="examen_intraoral" rows="3"><?= oldValue('examen_intraoral') ?></textarea></label>
            </div>
        </section>

        <section class="history-form-section">
            <div class="history-section-title"><span><i class="bi bi-activity"></i></span><div><h2>Diagnóstico</h2><p>Conclusión clínica inicial y hallazgos principales.</p></div></div>
            <label class="history-field history-full"><span>Diagnóstico</span><textarea name="diagnostico" rows="4"><?= oldValue('diagnostico') ?></textarea></label>
        </section>

        <section class="history-form-section">
            <div class="history-section-title"><span><i class="bi bi-diagram-3"></i></span><div><h2>Tratamiento</h2><p>Plan inicial sugerido para el paciente.</p></div></div>
            <label class="history-field history-full"><span>Plan de tratamiento inicial</span><textarea name="plan_tratamiento_inicial" rows="4"><?= oldValue('plan_tratamiento_inicial') ?></textarea></label>
        </section>

        <section class="history-form-section">
            <div class="history-section-title"><span><i class="bi bi-journal-text"></i></span><div><h2>Observaciones</h2><p>Notas clínicas o administrativas complementarias.</p></div></div>
            <label class="history-field history-full"><span>Observaciones</span><textarea name="observaciones" rows="4"><?= oldValue('observaciones') ?></textarea></label>
        </section>

        <section class="history-form-section">
            <div class="history-section-title"><span><i class="bi bi-cloud-arrow-up"></i></span><div><h2>Archivos y anexos</h2><p>Adjunta imágenes iniciales. JPG, PNG o WEBP hasta 5 MB.</p></div></div>
            <label class="history-upload-zone" data-history-dropzone>
                <input id="historiaArchivos" type="file" name="archivos[]" accept="image/jpeg,image/png,image/webp" multiple>
                <i class="bi bi-cloud-arrow-up"></i>
                <strong>Arrastra archivos aquí o haz clic para seleccionar</strong>
                <span>Radiografías, fotografías clínicas o anexos visuales.</span>
            </label>
            <div id="historiaUploadPreview" class="history-upload-preview"></div>
        </section>

        <footer class="history-form-actions">
            <a href="index.php" class="historia-secondary-btn"><i class="bi bi-x-lg"></i><span>Cancelar</span></a>
            <button type="submit" class="historia-primary-btn"><span class="spinner-border spinner-border-sm d-none" role="status"></span><i class="bi bi-check2-circle"></i><span>Guardar historia clínica</span></button>
        </footer>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
