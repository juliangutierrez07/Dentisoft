<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/mail.php';
require_once __DIR__ . '/../../config/MailService.php';
require_once __DIR__ . '/../../config/patient_portal.php';

requirePermission('pacientes.crear');

$paginaTitulo = 'Crear Paciente';
$cssAdicional = 'pacientes-form.css';
$errores = [];
$emailEnviado = false;
$emailWarning = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validarCSRF();

    $numeroDocumento = trim((string) ($_POST['numero_documento'] ?? ''));
    $tipoDocumento = trim((string) ($_POST['tipo_documento'] ?? 'CC'));
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $apellido = trim((string) ($_POST['apellido'] ?? ''));
    $fechaNacimiento = trim((string) ($_POST['fecha_nacimiento'] ?? ''));
    $genero = trim((string) ($_POST['genero'] ?? 'Otro'));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $direccion = trim((string) ($_POST['direccion'] ?? ''));
    $ciudad = trim((string) ($_POST['ciudad'] ?? 'Neiva'));
    $eps = trim((string) ($_POST['eps'] ?? ''));
    $tipoAfiliacion = trim((string) ($_POST['tipo_afiliacion'] ?? 'particular'));
    $grupoSanguineo = trim((string) ($_POST['grupo_sanguineo'] ?? ''));
    $estado = trim((string) ($_POST['estado'] ?? 'activo'));

    $tiposDocumento = ['CC', 'TI', 'CE', 'PAS', 'RC'];
    $generos = ['M', 'F', 'Otro'];
    $tiposAfiliacion = ['contributivo', 'subsidiado', 'particular', 'otro'];
    $gruposSanguineos = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    $estados = ['activo', 'inactivo'];

    if ($numeroDocumento === '') {
        $errores[] = 'El numero de documento es obligatorio.';
    }
    if ($nombre === '') {
        $errores[] = 'El nombre es obligatorio.';
    }
    if ($apellido === '') {
        $errores[] = 'El apellido es obligatorio.';
    }
    if (!in_array($tipoDocumento, $tiposDocumento, true)) {
        $errores[] = 'Selecciona un tipo de documento valido.';
    }
    if (!in_array($genero, $generos, true)) {
        $errores[] = 'Selecciona un genero valido.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'Ingresa un correo electronico valido.';
    }
    if ($fechaNacimiento !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNacimiento)) {
        $errores[] = 'Ingresa una fecha de nacimiento valida.';
    }
    if (!in_array($tipoAfiliacion, $tiposAfiliacion, true)) {
        $errores[] = 'Selecciona un tipo de afiliacion valido.';
    }
    if (!in_array($grupoSanguineo, $gruposSanguineos, true)) {
        $errores[] = 'Selecciona un grupo sanguineo valido.';
    }
    if (!in_array($estado, $estados, true)) {
        $errores[] = 'Selecciona un estado valido.';
    }

    if (empty($errores)) {
        try {
            $db = getDB();
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO pacientes (numero_documento, tipo_documento, nombre, apellido, fecha_nacimiento, genero, telefono, email, direccion, ciudad, eps, tipo_afiliacion, grupo_sanguineo, estado) VALUES (:numero_documento, :tipo_documento, :nombre, :apellido, :fecha_nacimiento, :genero, :telefono, :email, :direccion, :ciudad, :eps, :tipo_afiliacion, :grupo_sanguineo, :estado)");
            $stmt->execute([
                ':numero_documento' => $numeroDocumento,
                ':tipo_documento' => $tipoDocumento,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':fecha_nacimiento' => $fechaNacimiento ?: null,
                ':genero' => $genero,
                ':telefono' => $telefono,
                ':email' => $email ?: null,
                ':direccion' => $direccion,
                ':ciudad' => $ciudad,
                ':eps' => $eps,
                ':tipo_afiliacion' => $tipoAfiliacion,
                ':grupo_sanguineo' => $grupoSanguineo ?: null,
                ':estado' => $estado,
            ]);

            $pacienteId = $db->lastInsertId();
            crearAccesoPortalPaciente($db, (int) $pacienteId, $numeroDocumento, $estado);
            $db->commit();

            $pacienteDatos = [
                'id' => $pacienteId,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'numero_documento' => $numeroDocumento,
                'tipo_documento' => $tipoDocumento,
                'email' => $email,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            // El envio de correo nunca debe bloquear el registro del paciente.
            if (!empty($email)) {
                try {
                    $mailService = new MailService();
                    if ($mailService->enviarBienvenidaPaciente($pacienteDatos)) {
                        $emailEnviado = true;
                        error_log('Email de bienvenida enviado a ' . $email);
                    } else {
                        $emailWarning = 'El paciente se registro correctamente, pero no fue posible enviar el correo de bienvenida.';
                        error_log('Email warning: ' . ($mailService->obtenerError() ?? 'sin detalle'));
                    }
                } catch (Throwable $e) {
                    $emailWarning = 'El paciente se registro correctamente, pero no fue posible enviar el correo de bienvenida.';
                    error_log('Email error: ' . $e->getMessage());
                }
            }

            if ($emailEnviado) {
                setAlerta('Paciente registrado, acceso al portal creado y correo de bienvenida enviado exitosamente.', 'success');
            } else {
                setAlerta('Paciente creado correctamente. Acceso al portal creado con el documento como usuario y contraseña temporal.');
            }

            if ($emailWarning) {
                $_SESSION['email_warning'] = $emailWarning;
            }

            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Pacientes Crear error: ' . $e->getMessage());
            if ($e->getCode() === '23000') {
                $errores[] = 'Ya existe un paciente con ese numero de documento.';
            } else {
                $errores[] = 'No fue posible crear el paciente. Verifica los datos e intenta nuevamente.';
            }
        } catch (Throwable $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Pacientes Crear unexpected error: ' . $e->getMessage());
            $errores[] = 'No fue posible crear el acceso del paciente. Intenta nuevamente.';
        }
    }
}

function old(string $name, mixed $default = ''): string {
    return htmlspecialchars(trim((string) ($_POST[$name] ?? $default)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function isSelected(string $name, string $value, string $default = ''): string {
    return old($name, $default) === $value ? 'selected' : '';
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="patient-form-page">
    <header class="patient-form-hero">
        <div>
            <span class="patient-form-kicker">Registro clínico</span>
            <h1>Nuevo paciente</h1>
            <p>Captura los datos esenciales del paciente con una estructura clara, validaciones visibles y una experiencia consistente con DentiSoft.</p>
        </div>
        <a href="index.php" class="patient-back-btn">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <span>Volver</span>
        </a>
    </header>

    <?php if (!empty($errores)): ?>
        <div class="patient-form-alert" role="alert">
            <div class="patient-form-alert-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div>
            <div>
                <strong>Revisa la información</strong>
                <ul>
                    <?php foreach ($errores as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" class="patient-form-card needs-validation" novalidate data-prevent-double>
        <input type="hidden" name="csrf_token" value="<?= old('csrf_token', $_SESSION['csrf_token'] ?? '') ?>">

        <section class="patient-form-section">
            <div class="patient-section-heading">
                <span><i class="bi bi-person-badge" aria-hidden="true"></i></span>
                <div>
                    <h2>Información personal</h2>
                    <p>Identificación y datos base para la historia clínica.</p>
                </div>
            </div>

            <div class="patient-form-grid">
                <label class="patient-field">
                    <span>Documento <b>*</b></span>
                    <input type="text" name="numero_documento" value="<?= old('numero_documento') ?>" placeholder="Ej. 1075312456" required>
                    <small>Usa solo el número registrado en el documento.</small>
                    <div class="invalid-feedback">El documento es obligatorio.</div>
                </label>

                <label class="patient-field">
                    <span>Tipo documento</span>
                    <select name="tipo_documento">
                        <?php foreach (['CC', 'TI', 'CE', 'PAS', 'RC'] as $tipo): ?>
                            <option value="<?= $tipo ?>" <?= isSelected('tipo_documento', $tipo, 'CC') ?>><?= $tipo ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="patient-field">
                    <span>Nombre <b>*</b></span>
                    <input type="text" name="nombre" value="<?= old('nombre') ?>" placeholder="Ej. Ana María" required>
                    <div class="invalid-feedback">El nombre es obligatorio.</div>
                </label>

                <label class="patient-field">
                    <span>Apellido <b>*</b></span>
                    <input type="text" name="apellido" value="<?= old('apellido') ?>" placeholder="Ej. Rodríguez Pérez" required>
                    <div class="invalid-feedback">El apellido es obligatorio.</div>
                </label>

                <label class="patient-field">
                    <span>Fecha nacimiento</span>
                    <input type="date" name="fecha_nacimiento" value="<?= old('fecha_nacimiento') ?>">
                </label>

                <label class="patient-field">
                    <span>Género</span>
                    <select name="genero">
                        <?php foreach (['M' => 'Masculino', 'F' => 'Femenino', 'Otro' => 'Otro'] as $value => $label): ?>
                            <option value="<?= $value ?>" <?= isSelected('genero', $value, 'Otro') ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </section>

        <section class="patient-form-section">
            <div class="patient-section-heading">
                <span><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
                <div>
                    <h2>Contacto</h2>
                    <p>Canales para recordatorios, seguimiento y comunicación administrativa.</p>
                </div>
            </div>

            <div class="patient-form-grid">
                <label class="patient-field">
                    <span>Teléfono</span>
                    <input type="text" name="telefono" value="<?= old('telefono') ?>" placeholder="Ej. 3201234567">
                </label>

                <label class="patient-field patient-field-wide">
                    <span>Email</span>
                    <input type="email" name="email" value="<?= old('email') ?>" placeholder="paciente@email.com">
                    <div class="invalid-feedback">Ingresa un correo válido.</div>
                </label>

                <label class="patient-field patient-field-wide">
                    <span>Dirección</span>
                    <textarea name="direccion" rows="3" placeholder="Dirección de residencia"><?= old('direccion') ?></textarea>
                </label>

                <label class="patient-field">
                    <span>Ciudad</span>
                    <input type="text" name="ciudad" value="<?= old('ciudad', 'Neiva') ?>" placeholder="Neiva">
                </label>
            </div>
        </section>

        <section class="patient-form-section">
            <div class="patient-section-heading">
                <span><i class="bi bi-heart-pulse" aria-hidden="true"></i></span>
                <div>
                    <h2>Información médica</h2>
                    <p>Datos clínicos rápidos para priorización y contexto odontológico.</p>
                </div>
            </div>

            <div class="patient-form-grid">
                <label class="patient-field">
                    <span>Grupo sanguíneo</span>
                    <select name="grupo_sanguineo">
                        <option value="">Sin especificar</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $grupo): ?>
                            <option value="<?= $grupo ?>" <?= isSelected('grupo_sanguineo', $grupo) ?>><?= $grupo ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="patient-field patient-field-wide">
                    <span>EPS</span>
                    <input type="text" name="eps" value="<?= old('eps') ?>" placeholder="Ej. Nueva EPS, Sanitas, Sura">
                    <small>Déjalo vacío si el paciente es particular o no reporta EPS.</small>
                </label>
            </div>
        </section>

        <section class="patient-form-section">
            <div class="patient-section-heading">
                <span><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                <div>
                    <h2>Estado y afiliación</h2>
                    <p>Clasifica el paciente dentro del flujo operativo de la clínica.</p>
                </div>
            </div>

            <div class="patient-form-grid">
                <label class="patient-field">
                    <span>Tipo afiliación</span>
                    <select name="tipo_afiliacion">
                        <?php foreach (['contributivo', 'subsidiado', 'particular', 'otro'] as $tipo): ?>
                            <option value="<?= $tipo ?>" <?= isSelected('tipo_afiliacion', $tipo, 'particular') ?>><?= ucfirst($tipo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="patient-field">
                    <span>Estado</span>
                    <select name="estado">
                        <option value="activo" <?= isSelected('estado', 'activo', 'activo') ?>>Activo</option>
                        <option value="inactivo" <?= isSelected('estado', 'inactivo') ?>>Inactivo</option>
                    </select>
                </label>
            </div>
        </section>

        <footer class="patient-form-actions">
            <a href="index.php" class="patient-secondary-btn">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                <span>Cancelar</span>
            </a>
            <button type="submit" class="patient-submit-btn">
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                <span>Guardar paciente</span>
            </button>
        </footer>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
