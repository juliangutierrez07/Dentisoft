<?php

namespace DentiSoft\App\Repositories;

final class UsuarioRepository extends BaseRepository
{
    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.nombre, u.apellido, u.email, u.password, u.estado,
                   r.nombre AS rol, r.id AS rol_id
            FROM usuarios u
            INNER JOIN roles r ON u.rol_id = r.id
            WHERE u.email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function registerLastAccess(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
