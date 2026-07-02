<?php

namespace DentiSoft\App\Repositories;

use PDO;

final class HistoriaClinicaRepository extends BaseRepository
{
    public function search(string $search, int $page, int $limit): array
    {
        $params = [];
        $where = $this->buildSearchWhere($search, $params);

        $countSql = "SELECT COUNT(*)
            FROM historias_clinicas hc
            JOIN pacientes p ON hc.paciente_id = p.id
            JOIN usuarios u ON hc.odontologo_id = u.id
            $where";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        $sql = "SELECT
                hc.id,
                hc.numero_historia,
                hc.fecha_apertura,
                hc.estado,
                p.numero_documento,
                p.nombre AS paciente_nombre,
                p.apellido AS paciente_apellido,
                u.nombre AS odontologo_nombre,
                u.apellido AS odontologo_apellido
            FROM historias_clinicas hc
            JOIN pacientes p ON hc.paciente_id = p.id
            JOIN usuarios u ON hc.odontologo_id = u.id
            $where
            ORDER BY hc.fecha_apertura DESC, hc.id DESC
            LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
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
            ':term_paciente_nombre' => $term,
            ':term_paciente_apellido' => $term,
            ':term_documento' => $term,
            ':term_numero' => $term,
        ];

        return "WHERE (
            p.nombre LIKE :term_paciente_nombre
            OR p.apellido LIKE :term_paciente_apellido
            OR p.numero_documento LIKE :term_documento
            OR hc.numero_historia LIKE :term_numero
        )";
    }
}
