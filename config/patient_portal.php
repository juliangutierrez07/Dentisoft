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
