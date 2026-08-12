<?php
/**
 * ============================================================
 * Recuperacion de contrasena del Equipo Clinico (tabla usuarios)
 * ============================================================
 * Espeja el flujo del portal del paciente, adaptado a `usuarios`.
 */

const USUARIO_RESET_TOKEN_TTL_MINUTES = 60;

/**
 * Crea la tabla de tokens de reseteo si no existe (auto-migracion).
 */
function asegurarTablaResetsUsuarios(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS usuarios_password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            ip VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_usuario_reset_token (token_hash),
            KEY idx_usuario_reset_usuario (usuario_id),
            KEY idx_usuario_reset_expira (expires_at),
            CONSTRAINT fk_usuario_reset_usuario
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Genera un token de reseteo para un usuario del equipo clinico.
 * Invalida tokens previos no usados y devuelve el token EN CLARO
 * (en la base solo se guarda su hash SHA-256).
 */
function crearTokenResetUsuario(PDO $db, int $usuarioId): string {
    asegurarTablaResetsUsuarios($db);

    $del = $db->prepare("DELETE FROM usuarios_password_resets WHERE usuario_id = :id AND used_at IS NULL");
    $del->execute([':id' => $usuarioId]);

    $tokenPlano = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenPlano);
    $expira = (new DateTime('+' . USUARIO_RESET_TOKEN_TTL_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

    $ins = $db->prepare("
        INSERT INTO usuarios_password_resets (usuario_id, token_hash, expires_at, ip)
        VALUES (:usuario_id, :token_hash, :expires_at, :ip)
    ");
    $ins->execute([
        ':usuario_id' => $usuarioId,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expira,
        ':ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
    ]);

    return $tokenPlano;
}

/**
 * Valida un token en claro. Devuelve la fila con datos del usuario o null
 * si el token no existe, ya fue usado o expiro.
 */
function obtenerResetUsuarioValido(PDO $db, string $tokenPlano): ?array {
    $tokenPlano = trim($tokenPlano);
    if ($tokenPlano === '' || strlen($tokenPlano) !== 64 || !ctype_xdigit($tokenPlano)) {
        return null;
    }

    asegurarTablaResetsUsuarios($db);
    $tokenHash = hash('sha256', $tokenPlano);

    $stmt = $db->prepare("
        SELECT
            r.id AS reset_id,
            r.usuario_id,
            u.email,
            u.estado
        FROM usuarios_password_resets r
        INNER JOIN usuarios u ON u.id = r.usuario_id
        WHERE r.token_hash = :hash
          AND r.used_at IS NULL
          AND r.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([':hash' => $tokenHash]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * Aplica una nueva contrasena usando un token valido, marca el token como
 * usado e invalida el resto de tokens del usuario.
 */
function restablecerPasswordUsuario(PDO $db, int $resetId, int $usuarioId, string $nuevaPassword): void {
    $update = $db->prepare("
        UPDATE usuarios
        SET password = :password,
            updated_at = NOW()
        WHERE id = :id
    ");
    $update->execute([
        ':password' => password_hash($nuevaPassword, PASSWORD_DEFAULT),
        ':id' => $usuarioId,
    ]);

    $mark = $db->prepare("UPDATE usuarios_password_resets SET used_at = NOW() WHERE id = :id");
    $mark->execute([':id' => $resetId]);

    $clean = $db->prepare("DELETE FROM usuarios_password_resets WHERE usuario_id = :id AND used_at IS NULL");
    $clean->execute([':id' => $usuarioId]);
}
