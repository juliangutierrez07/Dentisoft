<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/patient_portal.php';
requirePermission('pacientes.editar');

$paginaTitulo = 'Editar Paciente';
$errores = [];
$estadoOpciones = [
    'activo' => 'Activo',
    'inactivo' => 'Inactivo',
    'suspendido' => 'Suspendido',
];
$pacienteId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$pacienteId) {
    setAlerta('Paciente no válido.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM pacientes WHERE id = :id");
    $stmt->execute([':id' => $pacienteId]);
    $paciente = $stmt->fetch();
    if (!$paciente) {
        setAlerta('Paciente no encontrado.', 'danger');
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Pacientes Editar carga error: ' . $e->getMessage());
    setAlerta('Error interno al cargar el paciente.', 'danger');
    header('Location: index.php');
    exit;
}

function esPeticionAjax(): bool {
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

function responderJson(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function asegurarEstadoSuspendido(PDO $db): void {
    static $verificado = false;

    if ($verificado) {
        return;
    }

    $verificado = true;
    $stmt = $db->query("SHOW COLUMNS FROM pacientes LIKE 'estado'");
    $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    $type = strtolower((string) ($column['Type'] ?? ''));

    if ($type !== '' && str_contains($type, "'suspendido'")) {
        return;
    }

    $db->exec("ALTER TABLE pacientes MODIFY estado ENUM('activo','inactivo','suspendido') DEFAULT 'activo'");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $numeroDocumento = trim($_POST['numero_documento'] ?? '');
    $tipoDocumento = trim($_POST['tipo_documento'] ?? 'CC');
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $fechaNacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $genero = trim($_POST['genero'] ?? 'Otro');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? 'Neiva');
    $eps = trim($_POST['eps'] ?? '');
    $tipoAfiliacion = trim($_POST['tipo_afiliacion'] ?? 'particular');
    $grupoSanguineo = trim($_POST['grupo_sanguineo'] ?? '');
    $estado = trim($_POST['estado'] ?? 'activo');
    $tiposDocumento = ['CC', 'TI', 'CE', 'PAS', 'RC'];
    $generos = ['M', 'F', 'Otro'];
    $tiposAfiliacion = ['contributivo', 'subsidiado', 'particular', 'otro'];
    $gruposSanguineos = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    if ($numeroDocumento === '') {
        $errores[] = 'El número de documento es obligatorio.';
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
    if (!array_key_exists($estado, $estadoOpciones)) {
        $errores[] = 'Selecciona un estado valido.';
    }

    if (empty($errores)) {
        try {
            $db->beginTransaction();
            asegurarEstadoSuspendido($db);
            $update = $db->prepare("UPDATE pacientes SET numero_documento = :numero_documento, tipo_documento = :tipo_documento, nombre = :nombre, apellido = :apellido, fecha_nacimiento = :fecha_nacimiento, genero = :genero, telefono = :telefono, email = :email, direccion = :direccion, ciudad = :ciudad, eps = :eps, tipo_afiliacion = :tipo_afiliacion, grupo_sanguineo = :grupo_sanguineo, estado = :estado WHERE id = :id");
            $update->execute([
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
                ':grupo_sanguineo' => $grupoSanguineo,
                ':estado' => $estado,
                ':id' => $pacienteId,
            ]);

            sincronizarAccesoPortalPaciente($db, (int) $pacienteId, $numeroDocumento, $estado);
            $db->commit();

            if (esPeticionAjax()) {
                $stmt = $db->prepare("SELECT updated_at FROM pacientes WHERE id = :id");
                $stmt->execute([':id' => $pacienteId]);
                $updatedAt = $stmt->fetchColumn() ?: date('Y-m-d H:i:s');

                responderJson([
                    'success' => true,
                    'message' => 'Estado del paciente actualizado correctamente',
                    'paciente' => [
                        'id' => $pacienteId,
                        'estado' => $estado,
                        'estado_label' => $estadoOpciones[$estado],
                        'updated_at' => formatDateTime((string) $updatedAt),
                    ],
                ]);
            }

            setAlerta('Paciente actualizado correctamente.');
            header('Location: ver.php?id=' . $pacienteId);
            exit;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Pacientes Editar error: ' . $e->getMessage());
            if ($e->getCode() === '23000') {
                $errores[] = 'Ya existe un paciente o acceso al portal con ese numero de documento.';
            } else {
                $errores[] = 'No fue posible actualizar el paciente. Intenta nuevamente.';
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Pacientes Editar unexpected error: ' . $e->getMessage());
            $errores[] = 'No fue posible sincronizar el acceso del portal. Intenta nuevamente.';
        }
    }

    if (esPeticionAjax()) {
        responderJson([
            'success' => false,
            'message' => $errores[0] ?? 'No fue posible actualizar el paciente.',
            'errors' => $errores,
        ], 422);
    }
}

function campo(string $name, array $paciente, $default = ''): string {
    return htmlspecialchars(trim($_POST[$name] ?? $paciente[$name] ?? $default), ENT_QUOTES, 'UTF-8');
}

function formatDateTime(?string $date, string $default = 'Sin registro'): string {
    if (!$date) {
        return $default;
    }
    $dt = date_create($date);
    return $dt ? date_format($dt, 'd/m/Y H:i') : $default;
}

function calculateAge(?string $birthDate): string {
    if (!$birthDate) {
        return '-';
    }
    try {
        $dob = new DateTime($birthDate);
        $today = new DateTime('today');
        return $dob->diff($today)->y . ' años';
    } catch (Exception $e) {
        return '-';
    }
}

function getInitials(string $name, string $surname): string {
    $initials = '';
    if ($name !== '') {
        $initials .= strtoupper(mb_substr(trim($name), 0, 1));
    }
    if ($surname !== '') {
        $initials .= strtoupper(mb_substr(trim($surname), 0, 1));
    }
    return $initials ?: 'PD';
}

$cssAdicional = 'pacientes-premium.css';
$pacienteNombre = trim(($paciente['nombre'] ?? '') . ' ' . ($paciente['apellido'] ?? ''));
$pacienteInitials = getInitials($paciente['nombre'] ?? '', $paciente['apellido'] ?? '');
$edad = calculateAge($paciente['fecha_nacimiento'] ?? null);
$ultimaActualizacion = formatDateTime($paciente['updated_at'] ?? $paciente['created_at'] ?? null);
$estadoActual = strtolower(trim((string) ($_POST['estado'] ?? $paciente['estado'] ?? 'activo')));
if (!array_key_exists($estadoActual, $estadoOpciones)) {
    $estadoActual = 'activo';
}
$estadoPaciente = $estadoOpciones[$estadoActual];
$tipoAfiliacionLabel = ucfirst(trim($paciente['tipo_afiliacion'] ?? 'particular'));
$epsLabel = trim($paciente['eps'] ?? 'No registrado');
$grupoSanguineoLabel = trim($paciente['grupo_sanguineo'] ?? 'Sin dato');
$generoLabel = trim($paciente['genero'] ?? 'No definido');
$campoDocumento = trim($paciente['tipo_documento'] ?? '-');

?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<script>document.body.classList.add('page-loading');</script>
<div class="container-fluid py-4 edit-patient-page">
    <div class="edit-skeleton-shell">
        <div class="skeleton-hero skeleton-card mb-4">
            <div class="skeleton-hero-grid">
                <div class="skeleton-avatar skeleton-shimmer"></div>
                <div class="skeleton-hero-body">
                    <div class="skeleton-line skeleton-title skeleton-shimmer"></div>
                    <div class="skeleton-line skeleton-subtitle skeleton-shimmer"></div>
                    <div class="skeleton-badges">
                        <span class="skeleton-badge skeleton-shimmer"></span>
                        <span class="skeleton-badge skeleton-shimmer"></span>
                        <span class="skeleton-badge skeleton-shimmer"></span>
                    </div>
                </div>
                <div class="skeleton-hero-stats">
                    <div class="skeleton-stat skeleton-shimmer"></div>
                    <div class="skeleton-stat skeleton-shimmer"></div>
                    <div class="skeleton-stat skeleton-shimmer"></div>
                </div>
            </div>
        </div>
        <div class="skeleton-page-grid">
            <div class="skeleton-card skeleton-form-shell skeleton-shimmer">
                <div class="skeleton-row">
                    <div class="skeleton-field"></div>
                    <div class="skeleton-field"></div>
                    <div class="skeleton-field"></div>
                    <div class="skeleton-field"></div>
                </div>
                <div class="skeleton-row">
                    <div class="skeleton-field"></div>
                    <div class="skeleton-field"></div>
                    <div class="skeleton-field"></div>
                </div>
                <div class="skeleton-row">
                    <div class="skeleton-field"></div>
                    <div class="skeleton-field"></div>
                    <div class="skeleton-field"></div>
                </div>
            </div>
            <div class="skeleton-card skeleton-sidebar-shell skeleton-shimmer">
                <div class="skeleton-line skeleton-title skeleton-shimmer"></div>
                <div class="skeleton-field skeleton-mini"></div>
                <div class="skeleton-field skeleton-mini"></div>
                <div class="skeleton-field skeleton-mini"></div>
            </div>
        </div>
    </div>
    <div class="edit-page-content">
    <div class="page-header-panel mb-4">
        <div class="page-header-left">
            <div class="page-breadcrumbs">
                <span class="breadcrumb-item"><i class="bi bi-people"></i> Pacientes</span>
                <span class="breadcrumb-sep">/</span>
                <span class="breadcrumb-item active">Editar paciente</span>
            </div>
            <h1 class="page-title">Editar paciente</h1>
            <p class="page-description text-muted">Moderniza el perfil con una experiencia premium, clara y más funcional.</p>
        </div>
        <div class="page-header-actions">
            <a href="index.php" class="btn btn-premium-soft"><i class="bi bi-list-check"></i> Listado</a>
            <a href="ver.php?id=<?= $pacienteId ?>" class="btn btn-premium-outline"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger shadow-sm mb-4">
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="hero-edit-card mb-4">
        <div class="hero-edit-grid">
            <div class="hero-profile">
                <div class="hero-avatar"><?= htmlspecialchars($pacienteInitials, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="hero-summary">
                    <span class="hero-tag">Perfil del paciente</span>
                    <h2><?= htmlspecialchars($pacienteNombre ?: 'Paciente sin nombre', ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="hero-meta-text">Documento <strong><?= htmlspecialchars($campoDocumento, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($paciente['numero_documento'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <div class="hero-badges">
                        <span class="badge badge-soft"><i class="bi bi-gender-ambiguous"></i> <?= htmlspecialchars($generoLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge badge-soft"><i class="bi bi-droplet"></i> <?= htmlspecialchars($grupoSanguineoLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge badge-soft"><i class="bi bi-building"></i> <?= htmlspecialchars($epsLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
            <div class="hero-stats-group">
                <article class="hero-stat-card hero-status-card status-context-<?= htmlspecialchars($estadoActual, ENT_QUOTES, 'UTF-8') ?>" data-status-card>
                    <span>Edad</span>
                    <strong><?= htmlspecialchars($edad, ENT_QUOTES, 'UTF-8') ?></strong>
                </article>
                <article class="hero-stat-card">
                    <span>Estado</span>
                    <strong data-patient-status-label><?= htmlspecialchars($estadoPaciente, ENT_QUOTES, 'UTF-8') ?></strong>
                </article>
                <article class="hero-stat-card hero-stat-card-accent">
                    <span>Última actualización</span>
                    <strong data-patient-updated-at><?= htmlspecialchars($ultimaActualizacion, ENT_QUOTES, 'UTF-8') ?></strong>
                </article>
            </div>
        </div>
    </section>

    <div class="page-content-grid">
        <section class="premium-card premium-form-panel">
            <form id="editarPacienteForm" method="POST" class="needs-validation" novalidate data-prevent-double>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-section-card">
                    <div class="form-section-header">
                        <i class="bi bi-person-circle"></i>
                        <div>
                            <h3>Información personal</h3>
                            <p>Datos básicos del paciente con un diseño premium.</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-floating">
                                <input id="numero_documento" type="text" name="numero_documento" class="form-control premium-input" placeholder=" " pattern="[A-Za-z0-9\-]{5,20}" value="<?= campo('numero_documento', $paciente) ?>" required>
                                <label for="numero_documento">Documento *</label>
                                <div class="valid-feedback"><i class="bi bi-check-circle-fill"></i> Documento válido.</div>
                                <div class="invalid-feedback"><i class="bi bi-exclamation-circle-fill"></i> Documento obligatorio o inválido.</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-floating">
                                <select id="tipo_documento" class="form-select premium-input" name="tipo_documento" aria-label="Tipo documento">
                                    <?php foreach (['CC','TI','CE','PAS','RC'] as $tipo): ?>
                                        <option value="<?= $tipo ?>" <?= campo('tipo_documento', $paciente, 'CC') === $tipo ? 'selected' : '' ?>><?= $tipo ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="tipo_documento">Tipo documento</label>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-floating">
                                <input id="nombre" type="text" name="nombre" class="form-control premium-input" placeholder=" " value="<?= campo('nombre', $paciente) ?>" required>
                                <label for="nombre">Nombre *</label>
                                <div class="valid-feedback"><i class="bi bi-check-circle-fill"></i> Campo válido.</div>
                                <div class="invalid-feedback"><i class="bi bi-exclamation-circle-fill"></i> El nombre es obligatorio.</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-floating">
                                <input id="apellido" type="text" name="apellido" class="form-control premium-input" placeholder=" " value="<?= campo('apellido', $paciente) ?>" required>
                                <label for="apellido">Apellido *</label>
                                <div class="valid-feedback"><i class="bi bi-check-circle-fill"></i> Campo válido.</div>
                                <div class="invalid-feedback"><i class="bi bi-exclamation-circle-fill"></i> El apellido es obligatorio.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section-card">
                    <div class="form-section-header">
                        <i class="bi bi-telephone-forward"></i>
                        <div>
                            <h3>Contacto</h3>
                            <p>Información actualizada para el paciente y su comunicación.</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input id="fecha_nacimiento" type="date" name="fecha_nacimiento" class="form-control premium-input" placeholder=" " value="<?= campo('fecha_nacimiento', $paciente) ?>">
                                <label for="fecha_nacimiento">Fecha nacimiento</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select id="genero" class="form-select premium-input" name="genero" aria-label="Género">
                                    <?php foreach (['M' => 'M', 'F' => 'F', 'Otro' => 'Otro'] as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= campo('genero', $paciente, 'Otro') === $value ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="genero">Género</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input id="telefono" type="text" name="telefono" class="form-control premium-input" placeholder=" " pattern="[0-9+\s\-()]{7,20}" value="<?= campo('telefono', $paciente) ?>">
                                <label for="telefono">Teléfono</label>
                                <div class="valid-feedback"><i class="bi bi-check-circle-fill"></i> Teléfono válido.</div>
                                <div class="invalid-feedback"><i class="bi bi-exclamation-circle-fill"></i> Ingresa un teléfono válido.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input id="email" type="email" name="email" class="form-control premium-input" placeholder=" " value="<?= campo('email', $paciente) ?>">
                                <label for="email">Email</label>
                                <div class="valid-feedback"><i class="bi bi-check-circle-fill"></i> Email válido.</div>
                                <div class="invalid-feedback"><i class="bi bi-exclamation-circle-fill"></i> Ingresa un correo válido.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <textarea id="direccion" name="direccion" class="form-control premium-input" placeholder=" " style="height: 84px;"><?= campo('direccion', $paciente) ?></textarea>
                                <label for="direccion">Dirección</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input id="ciudad" type="text" name="ciudad" class="form-control premium-input" placeholder=" " value="<?= campo('ciudad', $paciente, 'Neiva') ?>">
                                <label for="ciudad">Ciudad</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input id="eps" type="text" name="eps" class="form-control premium-input" placeholder=" " value="<?= campo('eps', $paciente) ?>">
                                <label for="eps">EPS</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select id="tipo_afiliacion" class="form-select premium-input" name="tipo_afiliacion" aria-label="Tipo afiliación">
                                    <?php foreach (['contributivo','subsidiado','particular','otro'] as $tipo): ?>
                                        <option value="<?= $tipo ?>" <?= campo('tipo_afiliacion', $paciente, 'particular') === $tipo ? 'selected' : '' ?>><?= ucfirst($tipo) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="tipo_afiliacion">Tipo afiliación</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section-card">
                    <div class="form-section-header">
                        <i class="bi bi-heart-pulse"></i>
                        <div>
                            <h3>Información médica</h3>
                            <p>Registra los datos clínicos clave para el historial del paciente.</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select id="grupo_sanguineo" class="form-select premium-input" name="grupo_sanguineo" aria-label="Grupo sanguíneo">
                                    <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $grupo): ?>
                                        <option value="<?= $grupo ?>" <?= campo('grupo_sanguineo', $paciente) === $grupo ? 'selected' : '' ?>><?= $grupo ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="grupo_sanguineo">Grupo sanguíneo</label>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="clinical-history-callout">
                                <div class="clinical-history-content">
                                    <div class="clinical-history-copy">
                                        <h4 class="mb-1">Historial clínico</h4>
                                        <p>Las alergias, enfermedades y observaciones se administran desde la ficha clínica del paciente.</p>
                                    </div>
                                    <a href="../historia_clinica/index.php?paciente_id=<?= $pacienteId ?>" class="btn btn-premium-outline btn-sm">Ver historial</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section-card patient-status-section">
                    <div class="form-section-header">
                        <i class="bi bi-shield-check"></i>
                        <div>
                            <h3>Estado del paciente</h3>
                            <p>Controla la disponibilidad operativa del perfil sin salir de esta vista.</p>
                        </div>
                    </div>
                    <div class="row g-3 align-items-stretch">
                        <div class="col-md-6">
                            <div class="form-floating status-select-wrap" data-selected-status="<?= htmlspecialchars($estadoActual, ENT_QUOTES, 'UTF-8') ?>">
                                <select id="estado" class="form-select premium-input patient-status-select" name="estado" aria-label="Estado del paciente" required data-status-select>
                                    <?php foreach ($estadoOpciones as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= campo('estado', $paciente, 'activo') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="estado">Estado</label>
                                <div class="valid-feedback"><i class="bi bi-check-circle-fill"></i> Estado listo para guardar.</div>
                                <div class="invalid-feedback"><i class="bi bi-exclamation-circle-fill"></i> Selecciona un estado valido.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="status-preview status-preview-<?= htmlspecialchars($estadoActual, ENT_QUOTES, 'UTF-8') ?>" data-status-preview>
                                <span class="status-preview-dot"></span>
                                <div>
                                    <strong data-status-preview-label><?= htmlspecialchars($estadoPaciente, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <small data-status-preview-text><?= $estadoActual === 'activo' ? 'Perfil disponible para atencion y agenda.' : ($estadoActual === 'suspendido' ? 'Perfil pausado temporalmente para gestion interna.' : 'Perfil fuera del flujo activo de atencion.') ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions-row">
                    <a href="ver.php?id=<?= $pacienteId ?>" class="btn btn-premium-outline">Cancelar</a>
                    <button type="submit" class="btn btn-premium"><span>Actualizar paciente</span> <i class="bi bi-send-fill"></i></button>
                </div>
            </form>
        </section>

        <aside class="premium-card premium-sidebar-panel">
            <div class="sidebar-card">
                <div class="sidebar-card-header">
                    <span>Panel rápido</span>
                    <small>Estado y acciones clave</small>
                </div>
                <div class="sidebar-card-body">
                    <div class="status-pill status-pill-<?= htmlspecialchars($estadoActual, ENT_QUOTES, 'UTF-8') ?>" data-sidebar-status><?= htmlspecialchars($estadoPaciente, ENT_QUOTES, 'UTF-8') ?></div>
                    <ul class="sidebar-info-list">
                        <li><strong data-sidebar-updated-at><?= htmlspecialchars($ultimaActualizacion, ENT_QUOTES, 'UTF-8') ?></strong><span>Última actualización</span></li>
                        <li><strong><?= htmlspecialchars($epsLabel, ENT_QUOTES, 'UTF-8') ?></strong><span>EPS</span></li>
                        <li><strong><?= htmlspecialchars($tipoAfiliacionLabel, ENT_QUOTES, 'UTF-8') ?></strong><span>Afiliación</span></li>
                        <li><strong><?= htmlspecialchars($generoLabel, ENT_QUOTES, 'UTF-8') ?></strong><span>Género</span></li>
                        <li><strong><?= htmlspecialchars($grupoSanguineoLabel, ENT_QUOTES, 'UTF-8') ?></strong><span>Grupo sanguíneo</span></li>
                    </ul>
                </div>
            </div>

            <div class="sidebar-card sidebar-card-action">
                <div class="sidebar-card-header">
                    <span>Acciones rápidas</span>
                    <small>Toma acciones clave desde aquí</small>
                </div>
                <div class="sidebar-card-body sidebar-actions">
                    <a href="ver.php?id=<?= $pacienteId ?>" class="btn btn-premium-soft w-100 mb-2"><i class="bi bi-eye"></i> Ver perfil</a>
                    <a href="../historia_clinica/index.php?paciente_id=<?= $pacienteId ?>" class="btn btn-premium-outline w-100"><i class="bi bi-journal-text"></i> Historial clínico</a>
                </div>
            </div>
        </aside>
    </div>
</div>
</div>
<script>
(function() {
    const body = document.body;

    function fadeOutSkeleton() {
        const skeleton = document.querySelector('.edit-skeleton-shell');
        const content = document.querySelector('.edit-page-content');
        if (skeleton) {
            skeleton.classList.add('fade-out');
            skeleton.addEventListener('transitionend', function() {
                skeleton.remove();
            });
        }
        if (content) {
            content.classList.add('fade-in');
        }
        body.classList.remove('page-loading');
    }

    function buildRegex(pattern) {
        try {
            return new RegExp('^(?:' + pattern + ')$');
        } catch (err) {
            return null;
        }
    }

    function validateField(field) {
        if (!field || field.disabled) return;
        const value = field.value.trim();
        let valid = true;

        if (field.required && value === '') {
            valid = false;
        } else if (value !== '' && !field.checkValidity()) {
            valid = false;
        }

        if (valid && field.name === 'numero_documento' && value !== '' && value.length < 5) {
            valid = false;
        }

        if (valid && field.name === 'telefono' && value !== '' && !/^[0-9+\s\-()]{7,20}$/.test(value)) {
            valid = false;
        }

        if (valid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
            field.setCustomValidity('');
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
            field.setCustomValidity('invalid');
        }

        const form = field.form;
        if (form) {
            form.classList.add('was-validated');
        }
    }

    function updateFloatingState(field) {
        if (!field) return;
        const wrapper = field.closest('.form-floating');
        if (!wrapper) return;

        const hasValue = field.value.trim() !== '';
        wrapper.classList.toggle('is-filled', hasValue);
    }

    function attachValidation(form) {
        if (!form) return;
        const fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(function(field) {
            updateFloatingState(field);
            field.addEventListener('input', function() {
                updateFloatingState(field);
                validateField(field);
            });
            field.addEventListener('change', function() {
                updateFloatingState(field);
                validateField(field);
            });
            field.addEventListener('blur', function() {
                updateFloatingState(field);
                validateField(field);
            });
        });
    }

    const statusMeta = {
        activo: {
            label: 'Activo',
            text: 'Perfil disponible para atencion y agenda.',
            toast: 'Estado del paciente actualizado correctamente'
        },
        inactivo: {
            label: 'Inactivo',
            text: 'Perfil fuera del flujo activo de atencion.',
            toast: 'Estado del paciente actualizado correctamente'
        },
        suspendido: {
            label: 'Suspendido',
            text: 'Perfil pausado temporalmente para gestion interna.',
            toast: 'Estado del paciente actualizado correctamente'
        }
    };

    function setStatusClass(element, prefix, status) {
        if (!element) return;
        Object.keys(statusMeta).forEach(function(key) {
            element.classList.remove(prefix + key);
        });
        element.classList.add(prefix + status);
    }

    function applyStatus(status, label, updatedAt) {
        const safeStatus = statusMeta[status] ? status : 'activo';
        const meta = statusMeta[safeStatus];
        const displayLabel = label || meta.label;

        document.querySelectorAll('[data-patient-status-label]').forEach(function(el) {
            el.textContent = displayLabel;
        });
        document.querySelectorAll('[data-sidebar-status]').forEach(function(el) {
            el.textContent = displayLabel;
            setStatusClass(el, 'status-pill-', safeStatus);
        });
        document.querySelectorAll('[data-status-card]').forEach(function(el) {
            setStatusClass(el, 'status-context-', safeStatus);
        });
        document.querySelectorAll('[data-status-preview]').forEach(function(el) {
            setStatusClass(el, 'status-preview-', safeStatus);
        });

        const previewLabel = document.querySelector('[data-status-preview-label]');
        if (previewLabel) previewLabel.textContent = displayLabel;

        const previewText = document.querySelector('[data-status-preview-text]');
        if (previewText) previewText.textContent = meta.text;

        if (updatedAt) {
            document.querySelectorAll('[data-patient-updated-at], [data-sidebar-updated-at]').forEach(function(el) {
                el.textContent = updatedAt;
            });
        }
    }

    function showPatientToast(message, type) {
        if (typeof mostrarAlerta === 'function') {
            mostrarAlerta(message, type, 4200);
            return;
        }
        window.alert(message);
    }

    async function submitPatientForm(form) {
        const submitButton = form.querySelector('[type="submit"]');
        const originalHtml = submitButton ? submitButton.innerHTML : '';

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando';
        }

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: new FormData(form)
            });
            const data = await response.json().catch(function() {
                return null;
            });

            if (!response.ok || !data || !data.success) {
                throw new Error(data?.message || 'No fue posible actualizar el estado del paciente.');
            }

            applyStatus(data.paciente.estado, data.paciente.estado_label, data.paciente.updated_at);
            form.classList.remove('was-validated');
            showPatientToast(data.message || statusMeta[data.paciente.estado]?.toast || 'Estado del paciente actualizado correctamente', 'success');
        } catch (err) {
            showPatientToast(err.message || 'No fue posible actualizar el estado del paciente.', 'danger');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalHtml;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.needs-validation').forEach(function(form) {
            attachValidation(form);
        });
        document.querySelectorAll('.form-floating input, .form-floating select, .form-floating textarea').forEach(updateFloatingState);

        const statusSelect = document.querySelector('[data-status-select]');
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                applyStatus(statusSelect.value);
            });
            applyStatus(statusSelect.value);
        }

        const editForm = document.getElementById('editarPacienteForm');
        if (editForm) {
            editForm.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();

                editForm.querySelectorAll('input, select, textarea').forEach(validateField);
                editForm.classList.add('was-validated');

                if (!editForm.checkValidity()) {
                    showPatientToast('Revisa los campos antes de guardar.', 'danger');
                    return;
                }

                submitPatientForm(editForm);
            });
        }
    });

    window.addEventListener('load', function() {
        setTimeout(fadeOutSkeleton, 400);
    });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
