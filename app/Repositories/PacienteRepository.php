<?php

namespace DentiSoft\App\Repositories;

use PDO;

final class PacienteRepository extends BaseRepository
{
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM pacientes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function search(string $search, int $page, int $limit): array
    {
        $params = [];
        $where = $this->buildSearchWhere($search, $params);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM pacientes p $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        $stmt = $this->db->prepare("SELECT
                p.id,
                p.numero_documento,
                p.tipo_documento,
                p.nombre,
                p.apellido,
                p.telefono,
                p.email,
                p.ciudad,
                p.eps,
                p.estado,
                pa.id AS portal_acceso_id,
                pa.estado AS portal_estado,
                pa.ultimo_acceso AS portal_ultimo_acceso,
                pa.debe_cambiar_password AS portal_debe_cambiar_password
            FROM pacientes p
            LEFT JOIN paciente_accesos pa ON pa.paciente_id = p.id
            $where
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT :limit OFFSET :offset");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'meta' => [
                'query' => $search,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    private function buildSearchWhere(string $search, array &$params): string
    {
        if ($search === '') {
            return 'WHERE 1=1';
        }

        $term = '%' . $search . '%';
        $params = [
            ':term_documento' => $term,
            ':term_nombre' => $term,
            ':term_apellido' => $term,
            ':term_email' => $term,
            ':term_telefono' => $term,
            ':term_ciudad' => $term,
        ];

        return "WHERE (
            p.numero_documento LIKE :term_documento
            OR p.nombre LIKE :term_nombre
            OR p.apellido LIKE :term_apellido
            OR p.email LIKE :term_email
            OR p.telefono LIKE :term_telefono
            OR p.ciudad LIKE :term_ciudad
        )";
    }
}
