<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/session.php';
requirePermission('usuarios.crear');

$paginaTitulo = 'Crear Usuario';
$cssAdicional = 'usuarios-premium.css';
$errores = [];

try {
    $db = getDB();
    $rolesStmt = $db->prepare("SELECT id, nombre FROM roles WHERE nombre IN ('administrador','odontologo','asistente') ORDER BY nombre");
    $rolesStmt->execute();
    $roles = $rolesStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Usuarios Crear carga error: ' . $e->getMessage());
    $roles = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $apellido = trim((string) ($_POST['apellido'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $telefono = trim((string) ($_POST['telefono'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));
    $rolId = filter_input(INPUT_POST, 'rol_id', FILTER_VALIDATE_INT);
    $estado = in_array(($_POST['estado'] ?? 'activo'), ['activo', 'inactivo'], true) ? $_POST['estado'] : 'activo';

    if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
    if ($apellido === '') $errores[] = 'El apellido es obligatorio.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo electronico es obligatorio y debe ser valido.';
    if ($password === '' || strlen($password) < 8) $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    if (!$rolId) $errores[] = 'Selecciona un rol valido.';

    if (empty($errores)) {
        try {
            $stmt = $db->prepare("INSERT INTO usuarios (rol_id, nombre, apellido, email, password, telefono, estado) VALUES (:rol_id, :nombre, :apellido, :email, :password, :telefono, :estado)");
            $stmt->execute([
                ':rol_id' => $rolId,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':email' => $email,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':telefono' => $telefono ?: null,
                ':estado' => $estado,
            ]);
            setAlerta('Usuario creado correctamente.');
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                $errores[] = 'El correo ya esta registrado.';
            } else {
                error_log('Usuarios Crear insert error: ' . $e->getMessage());
                $errores[] = 'No fue posible crear el usuario. Intenta nuevamente.';
            }
        }
    }
}

function h(mixed $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function old(string $name, mixed $default = ''): string {
    return h(trim((string) ($_POST[$name] ?? $default)));
}
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<div class="container-fluid py-4 users-page">
    <section class="users-hero">
        <div>
            <span class="users-kicker"><i class="bi bi-person-plus"></i> Nuevo acceso</span>
            <h1>Crear usuario</h1>
            <p>Configura identidad, seguridad, rol y permisos iniciales para el equipo de DentiSoft.</p>
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
            <div class="user-avatar-xl"><i class="bi bi-person"></i></div>
            <h2>Perfil de equipo</h2>
            <p>El usuario recibira acceso segun su rol operativo y podra gestionar solo las areas autorizadas.</p>
            <span class="profile-chip"><i class="bi bi-shield-check"></i> Acceso seguro</span>
        </aside>
        <main class="user-form-card">
            <section class="user-form-section">
                <div class="user-section-title"><span><i class="bi bi-person-vcard"></i></span><div><h2>Informacion personal</h2><p>Datos visibles dentro del sistema.</p></div></div>
                <div class="user-fields">
                    <label><span>Nombre *</span><input type="text" name="nombre" value="<?= old('nombre') ?>" required></label>
                    <label><span>Apellido *</span><input type="text" name="apellido" value="<?= old('apellido') ?>" required></label>
                    <label><span>Email *</span><input type="email" name="email" value="<?= old('email') ?>" required></label>
                    <label><span>Telefono</span><input type="text" name="telefono" value="<?= old('telefono') ?>"></label>
                </div>
            </section>
            <section class="user-form-section">
                <div class="user-section-title"><span><i class="bi bi-lock"></i></span><div><h2>Seguridad</h2><p>Credenciales iniciales y estado de acceso.</p></div></div>
                <div class="user-fields">
                    <label><span>Contraseña *</span><input type="password" name="password" required minlength="8"></label>
                    <label><span>Estado</span><select name="estado"><option value="activo" <?= old('estado', 'activo') === 'activo' ? 'selected' : '' ?>>Activo</option><option value="inactivo" <?= old('estado') === 'inactivo' ? 'selected' : '' ?>>Inactivo</option></select></label>
                </div>
            </section>
            <section class="user-form-section">
                <div class="user-section-title"><span><i class="bi bi-diagram-3"></i></span><div><h2>Roles y permisos</h2><p>Define el alcance operativo del usuario.</p></div></div>
                <div class="user-fields">
                    <label><span>Rol *</span><select name="rol_id" required><option value="">Selecciona un rol</option><?php foreach ($roles as $rol): ?><option value="<?= h($rol['id']) ?>" <?= old('rol_id') == $rol['id'] ? 'selected' : '' ?>><?= h(ucfirst($rol['nombre'])) ?></option><?php endforeach; ?></select></label>
                    <label><span>Especialidad</span><input type="text" value="Odontologia general" disabled></label>
                </div>
            </section>
            <div class="user-form-actions">
                <a href="index.php" class="users-secondary">Cancelar</a>
                <button type="submit" class="users-primary"><i class="bi bi-check2-circle"></i> Crear usuario</button>
            </div>
        </main>
    </form>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
