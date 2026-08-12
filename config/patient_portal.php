<?php
/**
 * Autenticacion del Portal del Paciente.
 * Mantiene credenciales de pacientes separadas de usuarios internos.
 */

function crearAccesoPortalPaciente(PDO $db, int $pacienteId, string $numeroDocumento, string $estadoPaciente = 'activo'): void {
    $documento = trim($numeroDocumento);
    if ($pacienteId <= 0 || $documento === '') {
        throw new InvalidArgumentException('Datos insuficientes para crear acceso de paciente.');
    }

    $estadoCuenta = $estadoPaciente === 'activo' ? 'activo' : 'inactivo';
    $stmt = $db->prepare("
        INSERT INTO paciente_accesos (
            paciente_id,
            usuario_documento,
            password,
            debe_cambiar_password,
            estado
        ) VALUES (
            :paciente_id,
            :usuario_documento,
            :password,
            1,
            :estado
        )
    ");
    $stmt->execute([
        ':paciente_id' => $pacienteId,
        ':usuario_documento' => $documento,
        ':password' => password_hash($documento, PASSWORD_DEFAULT),
        ':estado' => $estadoCuenta,
    ]);
}

function sincronizarAccesoPortalPaciente(PDO $db, int $pacienteId, string $numeroDocumento, string $estadoPaciente = 'activo'): void {
    $documento = trim($numeroDocumento);
    if ($pacienteId <= 0 || $documento === '') {
        throw new InvalidArgumentException('Datos insuficientes para sincronizar acceso de paciente.');
    }

    $estadoCuenta = $estadoPaciente === 'activo' ? 'activo' : 'inactivo';

    $stmt = $db->prepare("
        SELECT id, debe_cambiar_password, ultimo_acceso
        FROM paciente_accesos
        WHERE paciente_id = :paciente_id
        LIMIT 1
    ");
    $stmt->execute([':paciente_id' => $pacienteId]);
    $acceso = $stmt->fetch();

    if ($acceso) {
        $passwordSql = '';
        $params = [
            ':usuario_documento' => $documento,
            ':estado' => $estadoCuenta,
            ':id' => $acceso['id'],
        ];

        if ((int) ($acceso['debe_cambiar_password'] ?? 0) === 1 && empty($acceso['ultimo_acceso'])) {
            $passwordSql = ', password = :password';
            $params[':password'] = password_hash($documento, PASSWORD_DEFAULT);
        }

        $update = $db->prepare("
            UPDATE paciente_accesos
            SET usuario_documento = :usuario_documento,
                estado = :estado,
                updated_at = NOW()
                $passwordSql
            WHERE id = :id
        ");
        $update->execute($params);
        return;
    }

    crearAccesoPortalPaciente($db, $pacienteId, $documento, $estadoPaciente);
}

function estadoCuentaPacienteLabel(?string $estado): string {
    return match ($estado) {
        'activo' => 'Activa',
        'suspendido' => 'Suspendida',
        'inactivo' => 'Inactiva',
        default => 'Sin acceso',
    };
}

/**
 * ============================================================
 * Recuperacion de contrasena del Portal del Paciente
 * ============================================================
 */

const PORTAL_RESET_TOKEN_TTL_MINUTES = 60;

/**
 * Crea la tabla de tokens de reseteo si no existe (auto-migracion).
 */
function asegurarTablaResetsPortal(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS paciente_password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            acceso_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            ip VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_paciente_reset_token (token_hash),
            KEY idx_paciente_reset_acceso (acceso_id),
            KEY idx_paciente_reset_expira (expires_at),
            CONSTRAINT fk_paciente_reset_acceso
                FOREIGN KEY (acceso_id) REFERENCES paciente_accesos(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Genera un token de reseteo para una cuenta de acceso.
 * Invalida cualquier token previo no usado y devuelve el token EN CLARO
 * (en la base solo se guarda su hash SHA-256).
 */
function crearTokenResetPortal(PDO $db, int $accesoId): string {
    asegurarTablaResetsPortal($db);

    $del = $db->prepare("DELETE FROM paciente_password_resets WHERE acceso_id = :id AND used_at IS NULL");
    $del->execute([':id' => $accesoId]);

    $tokenPlano = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenPlano);
    $expira = (new DateTime('+' . PORTAL_RESET_TOKEN_TTL_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

    $ins = $db->prepare("
        INSERT INTO paciente_password_resets (acceso_id, token_hash, expires_at, ip)
        VALUES (:acceso_id, :token_hash, :expires_at, :ip)
    ");
    $ins->execute([
        ':acceso_id' => $accesoId,
        ':token_hash' => $tokenHash,
        ':expires_at' => $expira,
        ':ip' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
    ]);

    return $tokenPlano;
}

/**
 * Valida un token en claro. Devuelve la fila con datos de la cuenta o null
 * si el token no existe, ya fue usado o expiro.
 */
function obtenerResetPortalValido(PDO $db, string $tokenPlano): ?array {
    $tokenPlano = trim($tokenPlano);
    if ($tokenPlano === '' || strlen($tokenPlano) !== 64 || !ctype_xdigit($tokenPlano)) {
        return null;
    }

    asegurarTablaResetsPortal($db);
    $tokenHash = hash('sha256', $tokenPlano);

    $stmt = $db->prepare("
        SELECT
            r.id AS reset_id,
            r.acceso_id,
            pa.paciente_id,
            pa.usuario_documento,
            pa.estado AS cuenta_estado,
            p.estado AS paciente_estado
        FROM paciente_password_resets r
        INNER JOIN paciente_accesos pa ON pa.id = r.acceso_id
        INNER JOIN pacientes p ON p.id = pa.paciente_id
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
 * Aplica una nueva contrasena usando un token de reseteo valido,
 * marca el token como usado e invalida el resto de tokens de la cuenta.
 */
function restablecerPasswordPortal(PDO $db, int $resetId, int $accesoId, string $nuevaPassword): void {
    $update = $db->prepare("
        UPDATE paciente_accesos
        SET password = :password,
            debe_cambiar_password = 0,
            updated_at = NOW()
        WHERE id = :id
    ");
    $update->execute([
        ':password' => password_hash($nuevaPassword, PASSWORD_DEFAULT),
        ':id' => $accesoId,
    ]);

    $mark = $db->prepare("UPDATE paciente_password_resets SET used_at = NOW() WHERE id = :id");
    $mark->execute([':id' => $resetId]);

    $clean = $db->prepare("DELETE FROM paciente_password_resets WHERE acceso_id = :id AND used_at IS NULL");
    $clean->execute([':id' => $accesoId]);
}
