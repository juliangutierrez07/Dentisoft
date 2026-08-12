<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';

$usuarioId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
requirePermissionOrOwn('usuarios.editar', $usuarioId);

$paginaTitulo = 'Editar Usuario';
$cssAdicional = 'usuarios-premium.css';
$errores = [];
$usuarioId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$usuarioId) {
    setAlerta('Usuario no valido.', 'danger');
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();
    $userStmt = $db->prepare("
        SELECT 
            u.id, u.rol_id, u.nombre, u.apellido, u.email, u.password, u.telefono, u.estado, u.ultimo_acceso, u.created_at, u.updated_at,
            r.nombre AS rol
        FROM usuarios u
        LEFT JOIN roles r ON u.rol_id = r.id
        WHERE u.id = :id
    ");
    $userStmt->execute([':id' => $usuarioId]);
    $usuarioEditable = $userStmt->fetch();
    if (!$usuarioEditable) {
        setAlerta('Usuario no encontrado.', 'danger');
        header('Location: index.php');
        exit;
    }
    $rolesStmt = $db->prepare("SELECT id, nombre FROM roles WHERE nombre IN ('administrador','odontologo','asistente') ORDER BY nombre");
    $rolesStmt->execute();
    $roles = $rolesStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Usuarios Editar carga error: ' . $e->getMessage());
    setAlerta('Error interno al cargar el usuario.', 'danger');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $apellido = trim((string) ($_POST['apellido'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));
    $rolId = filter_input(INPUT_POST, 'rol_id', FILTER_VALIDATE_INT);
    $estado = in_array(($_POST['estado'] ?? 'activo'), ['activo', 'inactivo', 'suspendido'], true) ? $_POST['estado'] : 'activo';

    if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
    if ($apellido === '') $errores[] = 'El apellido es obligatorio.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo electronico es obligatorio y debe ser valido.';
    if (!$rolId) $errores[] = 'Selecciona un rol valido.';

    // Security check: Only Admin can edit emails
    $currentUser = currentUser();
    $currentUserRol = strtolower(trim((string) ($currentUser['rol'] ?? '')));
    $emailAnterior = $usuarioEditable['email'] ?? '';
    $emailCambiado = $email !== $emailAnterior;
    
    // Debug logs
    error_log('=== VALIDACIÓN EMAIL ===');
    error_log('ROL SESIÓN: ' . $currentUser['rol'] ?? 'NULL');
    error_log('ROL NORMALIZADO: ' . $currentUserRol);
    error_log('EMAIL ORIGINAL: ' . $emailAnterior);
    error_log('EMAIL NUEVO: ' . $email);
    error_log('EMAIL CAMBIADO: ' . ($emailCambiado ? 'SÍ' : 'NO'));
    error_log('ES ADMINISTRADOR: ' . ($currentUserRol === 'administrador' ? 'SÍ' : 'NO'));
    error_log('=====================');
    
    if ($emailCambiado && $currentUserRol !== 'administrador') {
        $errores[] = 'Solo los administradores pueden modificar el correo electronico de los usuarios.';
    }

    if (empty($errores)) {
        try {
            $sql = "UPDATE usuarios SET rol_id = :rol_id, nombre = :nombre, apellido = :apellido, email = :email, telefono = :telefono, estado = :estado";
            $params = [
                ':rol_id' => $rolId,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':email' => $email,
                ':telefono' => $telefono ?: null,
                ':estado' => $estado,
                ':id' => $usuarioId,
            ];
            if ($password !== '') {
                $sql .= ", password = :password";
                $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            // Audit logging for email changes
            if ($emailCambiado && $currentUserRol === 'administrador') {
                registrarCambioEmail($db, $usuarioId, $emailAnterior, $email, $currentUser['id']);
            }
            
            setAlerta('Usuario actualizado correctamente.');
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                $errores[] = 'El correo electronico ya esta registrado por otro usuario.';
            } else {
                error_log('Usuarios Editar error: ' . $e->getMessage());
                $errores[] = 'No fue posible actualizar el usuario. Intenta nuevamente.';
            }
        }
    }
}

function registrarCambioEmail(PDO $db, int $usuarioId, string $emailAnterior, string $emailNuevo, int $adminId): void {
    try {
        $stmt = $db->prepare("
            INSERT INTO auditoria_cambios_email 
            (usuario_id, email_anterior, email_nuevo, cambiado_por, fecha_cambio)
            VALUES (:usuario_id, :email_anterior, :email_nuevo, :cambiado_por, NOW())
        ");
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':email_anterior' => $emailAnterior,
            ':email_nuevo' => $emailNuevo,
            ':cambiado_por' => $adminId,
        ]);
    } catch (PDOException $e) {
        error_log('Error al registrar cambio de email: ' . $e->getMessage());
    }
}

function h(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatDateTime(mixed $dateTime, string $default = 'Nunca ha iniciado sesión'): string {
    $dateTime = trim((string) ($dateTime ?? ''));
    if ($dateTime === '') {
        return $default;
    }
    $timestamp = strtotime($dateTime);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : $default;
}

function field(string $name, array $usuario, mixed $default = ''): string {
    return h(trim((string) ($_POST[$name] ?? $usuario[$name] ?? $default)));
}

$initials = mb_strtoupper(
    mb_substr(trim((string) ($usuarioEditable['nombre'] ?? '')), 0, 1) .
    mb_substr(trim((string) ($usuarioEditable['apellido'] ?? '')), 0, 1)
);
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 users-page">
    <section class="users-hero">
        <div>
            <span class="users-kicker"><i class="bi bi-person-gear"></i> Gestion de acceso</span>
            <h1>Editar usuario</h1>
            <p>Actualiza datos personales, seguridad, estado y permisos sin perder trazabilidad.</p>
        </div>
        <a href="index.php" class="users-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </section>

    <?php if (!empty($errores)): ?>
        <div class="alert alert-danger shadow-sm">
            <ul class="mb-0">
                <?php foreach ($errores as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="user-form-shell needs-validation" novalidate data-prevent-double>
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
        <aside class="user-profile-preview">
            <div class="user-avatar-xl"><?= h($initials ?: 'US') ?></div>
            <h2><?= h(trim((string) (($usuarioEditable['nombre'] ?? '') . ' ' . ($usuarioEditable['apellido'] ?? '')))) ?></h2>
            <p><?= h($usuarioEditable['email'] ?? '') ?></p>
            <span class="profile-chip"><i class="bi bi-clock-history"></i> <?= h(formatDateTime($usuarioEditable['ultimo_acceso'] ?? null)) ?></span>
        </aside>
        <main class="user-form-card">
            <section class="user-form-section">
                <div class="user-section-title"><span><i class="bi bi-person-vcard"></i></span><div><h2>Informacion personal</h2><p>Datos visibles dentro del sistema.</p></div></div>
                <div class="user-fields">
                    <label><span>Nombre *</span><input type="text" name="nombre" value="<?= field('nombre', $usuarioEditable) ?>" required></label>
                    <label><span>Apellido *</span><input type="text" name="apellido" value="<?= field('apellido', $usuarioEditable) ?>" required></label>
                    <label class="email-field-label">
                        <span>Email *</span>
                        <div class="email-input-wrapper">
                            <i class="bi bi-envelope email-icon"></i>
                            <input type="email" name="email" value="<?= field('email', $usuarioEditable) ?>" required id="emailInput" data-original-email="<?= h($usuarioEditable['email'] ?? '') ?>">
                            <span class="email-validation-indicator" id="emailIndicator"></span>
                        </div>
                        <span class="email-validation-message" id="emailMessage"></span>
                    </label>
                    <label><span>Telefono</span><input type="text" name="telefono" value="<?= field('telefono', $usuarioEditable) ?>"></label>
                </div>
            </section>
            <section class="user-form-section">
                <div class="user-section-title"><span><i class="bi bi-lock"></i></span><div><h2>Seguridad</h2><p>Deja la contraseña vacia para mantener la actual.</p></div></div>
                <div class="user-fields">
                    <label><span>Nueva contraseña</span><input type="password" name="password" placeholder="Sin cambios"></label>
                    <label><span>Estado</span><select name="estado"><option value="activo" <?= field('estado', $usuarioEditable, 'activo') === 'activo' ? 'selected' : '' ?>>Activo</option><option value="inactivo" <?= field('estado', $usuarioEditable) === 'inactivo' ? 'selected' : '' ?>>Inactivo</option><option value="suspendido" <?= field('estado', $usuarioEditable) === 'suspendido' ? 'selected' : '' ?>>Suspendido</option></select></label>
                </div>
            </section>
            <section class="user-form-section">
                <div class="user-section-title"><span><i class="bi bi-diagram-3"></i></span><div><h2>Roles y permisos</h2><p>Ajusta el alcance operativo del usuario.</p></div></div>
                <div class="user-fields">
                    <label><span>Rol *</span><select name="rol_id" required><?php foreach ($roles as $rol): ?><option value="<?= h($rol['id']) ?>" <?= field('rol_id', $usuarioEditable, $usuarioEditable['rol_id'] ?? '') == $rol['id'] ? 'selected' : '' ?>><?= h(ucfirst($rol['nombre'])) ?></option><?php endforeach; ?></select></label>
                    <label><span>Especialidad</span><input type="text" value="<?= h(($usuarioEditable['rol_id'] ?? '') ? 'Odontologia general' : 'Administracion') ?>" disabled></label>
                </div>
            </section>
            <div class="user-form-actions">
                <a href="index.php" class="users-secondary">Cancelar</a>
                <button type="submit" class="users-primary"><i class="bi bi-check2-circle"></i> Actualizar usuario</button>
            </div>
        </main>
    </form>
</div>

<style>
.email-field-label {
    position: relative;
}

.email-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.email-icon {
    position: absolute;
    left: 12px;
    color: #64748b;
    font-size: 1rem;
    z-index: 1;
}

.email-input-wrapper input {
    padding-left: 40px;
    padding-right: 40px;
    transition: all 0.3s ease;
}

.email-validation-indicator {
    position: absolute;
    right: 12px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.email-validation-indicator.valid {
    opacity: 1;
    background: rgba(47, 175, 124, 0.16);
    color: #2FAF7C;
    border: 1px solid rgba(47, 175, 124, 0.3);
}

.email-validation-indicator.invalid {
    opacity: 1;
    background: rgba(217, 97, 95, 0.16);
    color: #D9615F;
    border: 1px solid rgba(217, 97, 95, 0.3);
}

.email-validation-indicator.changed {
    opacity: 1;
    background: rgba(139, 126, 255, 0.16);
    color: #8B7EFF;
    border: 1px solid rgba(139, 126, 255, 0.3);
}

.email-validation-message {
    display: block;
    margin-top: 6px;
    font-size: 0.85rem;
    min-height: 20px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.email-validation-message.show {
    opacity: 1;
}

.email-validation-message.success {
    color: #2FAF7C;
}

.email-validation-message.error {
    color: #D9615F;
}

.email-validation-message.warning {
    color: #D9A247;
}

.email-input-wrapper input:focus {
    border-color: rgba(47, 224, 176, 0.5);
    box-shadow: 0 0 0 3px rgba(47, 224, 176, 0.1);
}

.email-input-wrapper input.valid {
    border-color: rgba(16, 185, 129, 0.5);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

.email-input-wrapper input.invalid {
    border-color: rgba(239, 68, 68, 0.5);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const emailInput = document.getElementById('emailInput');
    const emailIndicator = document.getElementById('emailIndicator');
    const emailMessage = document.getElementById('emailMessage');
    const originalEmail = emailInput.dataset.originalEmail || '';
    
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    function updateEmailValidation() {
        const email = emailInput.value.trim().toLowerCase();
        
        if (email === '') {
            emailIndicator.className = 'email-validation-indicator';
            emailIndicator.innerHTML = '';
            emailMessage.className = 'email-validation-message';
            emailMessage.textContent = '';
            emailInput.classList.remove('valid', 'invalid');
            return;
        }
        
        const isValid = validateEmail(email);
        const isChanged = email !== originalEmail.toLowerCase();
        
        if (isValid) {
            if (isChanged) {
                emailIndicator.className = 'email-validation-indicator changed';
                emailIndicator.innerHTML = '<i class="bi bi-pencil"></i>';
                emailMessage.className = 'email-validation-message show warning';
                emailMessage.textContent = 'Correo modificado (requiere permisos de administrador)';
                emailInput.classList.add('valid');
                emailInput.classList.remove('invalid');
            } else {
                emailIndicator.className = 'email-validation-indicator valid';
                emailIndicator.innerHTML = '<i class="bi bi-check"></i>';
                emailMessage.className = 'email-validation-message show success';
                emailMessage.textContent = 'Correo válido';
                emailInput.classList.add('valid');
                emailInput.classList.remove('invalid');
            }
        } else {
            emailIndicator.className = 'email-validation-indicator invalid';
            emailIndicator.innerHTML = '<i class="bi bi-x"></i>';
            emailMessage.className = 'email-validation-message show error';
            emailMessage.textContent = 'Correo inválido';
            emailInput.classList.add('invalid');
            emailInput.classList.remove('valid');
        }
    }
    
    emailInput.addEventListener('input', updateEmailValidation);
    emailInput.addEventListener('blur', updateEmailValidation);
    
    // Initial validation
    updateEmailValidation();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
