<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requirePermission('citas.ver');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function jsonResponse(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function requestId(): ?int {
    $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $id ?: null;
}

function validDateParam(string $value): ?string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function weekStart(string $fecha): string {
    $date = DateTime::createFromFormat('!Y-m-d', $fecha) ?: new DateTime('today');
    $dayOfWeek = (int) $date->format('N');
    return $date->modify('-' . ($dayOfWeek - 1) . ' day')->format('Y-m-d');
}

function validCalendarDateParam(?string $value): ?string {
    $text = trim((string) $value);
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})/', $text, $matches)) {
        return null;
    }

    return validDateParam($matches[1]);
}

function appointmentStatusColor(string $estado): array {
    return match ($estado) {
        'confirmada' => ['#2FAF7C', '#0F241B'],
        'pendiente' => ['#D9A247', '#2A2011'],
        'cancelada' => ['#D9615F', '#2A1517'],
        'atendida' => ['#5C87CE', '#141E2E'],
        'no_asistio' => ['#a8b3c7', '#1d2533'],
        default => ['#8B7EFF', '#1C1A2E'],
    };
}

function appointmentStatusText(string $estado): string {
    return match ($estado) {
        'atendida' => 'Finalizada',
        'no_asistio' => 'No asistio',
        default => ucfirst(str_replace('_', ' ', $estado)),
    };
}

function normalizeSqlTime(?string $value, string $fallback = '00:00:00'): string {
    $time = trim((string) $value);
    if (preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $time, $matches)) {
        return sprintf('%02d:%02d:%02d', (int) $matches[1], (int) $matches[2], (int) ($matches[3] ?? 0));
    }

    return $fallback;
}

function addMinutesToTime(string $time, int $minutes): string {
    $date = DateTime::createFromFormat('!H:i:s', $time) ?: new DateTime('00:00:00');
    return $date->modify('+' . $minutes . ' minutes')->format('H:i:s');
}

$action = trim((string) ($_GET['action'] ?? ''));

try {
    $db = getDB();

    if ($action === 'fullcalendar') {
        $startDate = validCalendarDateParam($_GET['start'] ?? null)
            ?? validCalendarDateParam($_GET['desde'] ?? null)
            ?? date('Y-m-d');
        $endDate = validCalendarDateParam($_GET['end'] ?? null)
            ?? (new DateTime($startDate))->modify('+7 day')->format('Y-m-d');

        if ($endDate <= $startDate) {
            $endDate = (new DateTime($startDate))->modify('+1 day')->format('Y-m-d');
        }

        $sql = "SELECT
                    c.id,
                    DATE_FORMAT(c.fecha, '%Y-%m-%d') AS fecha,
                    TIME_FORMAT(c.hora_inicio, '%H:%i:%s') AS hora_inicio,
                    TIME_FORMAT(c.hora_fin, '%H:%i:%s') AS hora_fin,
                    c.estado,
                    COALESCE(c.motivo, '') AS motivo,
                    COALESCE(p.nombre, '') AS paciente_nombre,
                    COALESCE(p.apellido, '') AS paciente_apellido,
                    COALESCE(p.numero_documento, '') AS numero_documento,
                    COALESCE(u.nombre, '') AS odontologo_nombre,
                    COALESCE(u.apellido, '') AS odontologo_apellido
                FROM citas c
                LEFT JOIN pacientes p ON c.paciente_id = p.id
                LEFT JOIN usuarios u ON c.odontologo_id = u.id
                WHERE DATE(c.fecha) >= :start_date
                  AND DATE(c.fecha) < :end_date
                ORDER BY c.fecha ASC, c.hora_inicio ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
        ]);

        $events = array_map(static function (array $cita): array {
            $paciente = trim(($cita['paciente_nombre'] ?? '') . ' ' . ($cita['paciente_apellido'] ?? '')) ?: 'Paciente no disponible';
            $odontologo = trim(($cita['odontologo_nombre'] ?? '') . ' ' . ($cita['odontologo_apellido'] ?? '')) ?: 'Odontologo no disponible';
            $motivo = trim((string) ($cita['motivo'] ?? '')) ?: 'Sin motivo';
            $estado = (string) ($cita['estado'] ?? 'pendiente');
            [$borderColor, $backgroundColor] = appointmentStatusColor($estado);
            $fecha = (string) $cita['fecha'];
            $horaInicio = normalizeSqlTime($cita['hora_inicio'] ?? null);
            $horaFin = normalizeSqlTime($cita['hora_fin'] ?? null, addMinutesToTime($horaInicio, 30));

            if ($horaFin <= $horaInicio) {
                $horaFin = addMinutesToTime($horaInicio, 30);
            }

            return [
                'id' => (string) $cita['id'],
                'title' => $paciente,
                'start' => $fecha . 'T' . $horaInicio,
                'end' => $fecha . 'T' . $horaFin,
                'allDay' => false,
                'url' => BASE_URL . '/modules/citas/editar.php?id=' . urlencode((string) $cita['id']) . '&volver=calendario',
                'backgroundColor' => $backgroundColor,
                'borderColor' => $borderColor,
                'textColor' => '#ffffff',
                'classNames' => ['fc-cita-event', 'status-' . $estado],
                'extendedProps' => [
                    'paciente' => $paciente,
                    'odontologo' => $odontologo,
                    'documento' => (string) ($cita['numero_documento'] ?? ''),
                    'estado' => $estado,
                    'estadoTexto' => appointmentStatusText($estado),
                    'motivo' => $motivo,
                    'horaInicio' => substr($horaInicio, 0, 5),
                    'horaFin' => substr($horaFin, 0, 5),
                    'fecha' => $fecha,
                ],
            ];
        }, $stmt->fetchAll());

        $statsStmt = $db->prepare("SELECT
                SUM(CASE WHEN DATE(fecha) = CURDATE() THEN 1 ELSE 0 END) AS hoy,
                SUM(CASE WHEN DATE(fecha) >= :pendiente_start AND DATE(fecha) < :pendiente_end AND estado = 'pendiente' THEN 1 ELSE 0 END) AS pendiente,
                SUM(CASE WHEN DATE(fecha) >= :confirmada_start AND DATE(fecha) < :confirmada_end AND estado = 'confirmada' THEN 1 ELSE 0 END) AS confirmada,
                SUM(CASE WHEN DATE(fecha) >= :cancelada_start AND DATE(fecha) < :cancelada_end AND estado = 'cancelada' THEN 1 ELSE 0 END) AS cancelada
            FROM citas");
        $statsStmt->execute([
            ':pendiente_start' => $startDate,
            ':pendiente_end' => $endDate,
            ':confirmada_start' => $startDate,
            ':confirmada_end' => $endDate,
            ':cancelada_start' => $startDate,
            ':cancelada_end' => $endDate,
        ]);
        $statsRow = $statsStmt->fetch() ?: [];

        jsonResponse([
            'success' => true,
            'events' => $events,
            'stats' => [
                'hoy' => (int) ($statsRow['hoy'] ?? 0),
                'pendiente' => (int) ($statsRow['pendiente'] ?? 0),
                'confirmada' => (int) ($statsRow['confirmada'] ?? 0),
                'cancelada' => (int) ($statsRow['cancelada'] ?? 0),
            ],
        ]);
    }

    if ($action === 'calendario') {
        $fechaBase = validDateParam(trim((string) ($_GET['desde'] ?? ''))) ?? date('Y-m-d');
        $inicioSemana = weekStart($fechaBase);
        $finSemana = (new DateTime($inicioSemana))->modify('+6 day')->format('Y-m-d');

        $sql = "SELECT
                    c.id,
                    DATE_FORMAT(c.fecha, '%Y-%m-%d') AS fecha,
                    TIME_FORMAT(c.hora_inicio, '%H:%i:%s') AS hora_inicio,
                    TIME_FORMAT(c.hora_fin, '%H:%i:%s') AS hora_fin,
                    c.estado,
                    COALESCE(c.motivo, '') AS motivo,
                    COALESCE(p.nombre, '') AS paciente_nombre,
                    COALESCE(p.apellido, '') AS paciente_apellido,
                    COALESCE(p.numero_documento, '') AS numero_documento,
                    COALESCE(u.nombre, '') AS odontologo_nombre,
                    COALESCE(u.apellido, '') AS odontologo_apellido
                FROM citas c
                LEFT JOIN pacientes p ON c.paciente_id = p.id
                LEFT JOIN usuarios u ON c.odontologo_id = u.id
                WHERE DATE(c.fecha) >= :inicio_semana
                  AND DATE(c.fecha) <= :fin_semana
                ORDER BY c.fecha ASC, c.hora_inicio ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':inicio_semana' => $inicioSemana,
            ':fin_semana' => $finSemana,
        ]);
        $citas = $stmt->fetchAll();

        $statsStmt = $db->prepare("SELECT
                SUM(CASE WHEN DATE(fecha) = CURDATE() THEN 1 ELSE 0 END) AS hoy,
                SUM(CASE WHEN DATE(fecha) BETWEEN :pendiente_inicio AND :pendiente_fin AND estado = 'pendiente' THEN 1 ELSE 0 END) AS pendiente,
                SUM(CASE WHEN DATE(fecha) BETWEEN :confirmada_inicio AND :confirmada_fin AND estado = 'confirmada' THEN 1 ELSE 0 END) AS confirmada,
                SUM(CASE WHEN DATE(fecha) BETWEEN :cancelada_inicio AND :cancelada_fin AND estado = 'cancelada' THEN 1 ELSE 0 END) AS cancelada
            FROM citas");
        $statsStmt->execute([
            ':pendiente_inicio' => $inicioSemana,
            ':pendiente_fin' => $finSemana,
            ':confirmada_inicio' => $inicioSemana,
            ':confirmada_fin' => $finSemana,
            ':cancelada_inicio' => $inicioSemana,
            ':cancelada_fin' => $finSemana,
        ]);
        $statsRow = $statsStmt->fetch() ?: [];

        jsonResponse([
            'success' => true,
            'inicioSemana' => $inicioSemana,
            'finSemana' => $finSemana,
            'citas' => $citas,
            'stats' => [
                'hoy' => (int) ($statsRow['hoy'] ?? 0),
                'pendiente' => (int) ($statsRow['pendiente'] ?? 0),
                'confirmada' => (int) ($statsRow['confirmada'] ?? 0),
                'cancelada' => (int) ($statsRow['cancelada'] ?? 0),
            ],
        ]);
    }

    if ($action === 'buscar') {
        $query = trim((string) ($_GET['query'] ?? ''));
        $estado = trim((string) ($_GET['estado'] ?? ''));
        $fechaFiltro = trim((string) ($_GET['fecha_filtro'] ?? ''));
        $params = [];
        $where = 'WHERE 1=1';

        if ($query !== '') {
            $where .= " AND (
                p.nombre LIKE :term_paciente_nombre
                OR p.apellido LIKE :term_paciente_apellido
                OR p.numero_documento LIKE :term_documento
                OR u.nombre LIKE :term_odontologo_nombre
                OR u.apellido LIKE :term_odontologo_apellido
                OR c.motivo LIKE :term_motivo
            )";
            $term = '%' . $query . '%';
            $params[':term_paciente_nombre'] = $term;
            $params[':term_paciente_apellido'] = $term;
            $params[':term_documento'] = $term;
            $params[':term_odontologo_nombre'] = $term;
            $params[':term_odontologo_apellido'] = $term;
            $params[':term_motivo'] = $term;
        }
        if ($estado !== '') {
            $where .= ' AND c.estado = :estado';
            $params[':estado'] = $estado;
        }
        if ($fechaFiltro === 'hoy') {
            $where .= ' AND c.fecha = CURDATE()';
        } elseif ($fechaFiltro === 'manana') {
            $where .= ' AND c.fecha = DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
        } elseif ($fechaFiltro === 'semana') {
            $where .= ' AND c.fecha BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 6 DAY)';
        }

        $sql = "SELECT
                    c.id,
                    c.fecha,
                    c.hora_inicio,
                    c.hora_fin,
                    c.estado,
                    COALESCE(c.motivo, '') AS motivo,
                    COALESCE(p.nombre, '') AS paciente_nombre,
                    COALESCE(p.apellido, '') AS paciente_apellido,
                    COALESCE(p.numero_documento, '') AS numero_documento,
                    COALESCE(u.nombre, '') AS odontologo_nombre,
                    COALESCE(u.apellido, '') AS odontologo_apellido
                FROM citas c
                LEFT JOIN pacientes p ON c.paciente_id = p.id
                LEFT JOIN usuarios u ON c.odontologo_id = u.id
                $where
                ORDER BY c.fecha DESC, c.hora_inicio DESC
                LIMIT 100";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonResponse(['success' => true, 'citas' => $stmt->fetchAll()]);
    }

    if ($action === 'detalle') {
        $id = requestId();
        if ($id === null) {
            jsonResponse([
                'success' => false,
                'error' => 'ID de cita invalido.',
                'code' => 'INVALID_APPOINTMENT_ID',
            ], 422);
        }

        $stmt = $db->prepare("SELECT
                c.*,
                COALESCE(p.nombre, '') AS paciente_nombre,
                COALESCE(p.apellido, '') AS paciente_apellido,
                COALESCE(p.numero_documento, '') AS numero_documento,
                COALESCE(p.tipo_documento, '') AS tipo_documento,
                COALESCE(u.nombre, '') AS odontologo_nombre,
                COALESCE(u.apellido, '') AS odontologo_apellido,
                COALESCE(pt.nombre_plan, '') AS plan_nombre,
                COALESCE(pt.descripcion, '') AS plan_descripcion,
                COALESCE(pt.estado, '') AS plan_estado,
                COALESCE(st.numero_sesion, '') AS sesion_numero,
                COALESCE(st.descripcion, '') AS sesion_descripcion,
                COALESCE(st.estado, '') AS sesion_estado,
                COALESCE(creator.nombre, '') AS creador_nombre,
                COALESCE(creator.apellido, '') AS creador_apellido
            FROM citas c
            LEFT JOIN pacientes p ON c.paciente_id = p.id
            LEFT JOIN usuarios u ON c.odontologo_id = u.id
            LEFT JOIN planes_tratamiento pt ON c.plan_id = pt.id
            LEFT JOIN sesiones_tratamiento st ON c.sesion_id = st.id
            LEFT JOIN usuarios creator ON c.created_by = creator.id
            WHERE c.id = :id
            LIMIT 1");
        $stmt->execute([':id' => $id]);
        $cita = $stmt->fetch();

        if (!$cita) {
            jsonResponse([
                'success' => false,
                'error' => 'La cita solicitada no existe o fue eliminada.',
                'code' => 'APPOINTMENT_NOT_FOUND',
            ], 404);
        }

        jsonResponse(['success' => true, 'cita' => $cita]);
    }

    jsonResponse([
        'success' => false,
        'error' => 'Accion invalida.',
        'code' => 'INVALID_ACTION',
    ], 400);
} catch (PDOException $e) {
    $traceId = bin2hex(random_bytes(4));
    error_log("citas_api error [$traceId]: " . $e->getMessage());

    jsonResponse([
        'success' => false,
        'error' => 'Error interno en el servidor. Intenta nuevamente.',
        'code' => 'SERVER_ERROR',
        'trace_id' => $traceId,
    ], 500);
} catch (Throwable $e) {
    $traceId = bin2hex(random_bytes(4));
    error_log("citas_api unexpected [$traceId]: " . $e->getMessage());

    jsonResponse([
        'success' => false,
        'error' => 'Respuesta inesperada del servidor.',
        'code' => 'UNEXPECTED_ERROR',
        'trace_id' => $traceId,
    ], 500);
}
