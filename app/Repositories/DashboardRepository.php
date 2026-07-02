<?php

namespace DentiSoft\App\Repositories;

final class DashboardRepository extends BaseRepository
{
    public function kpis(): array
    {
        return [
            'total_pacientes' => (int) $this->db->query("SELECT COUNT(*) FROM pacientes WHERE estado = 'activo'")->fetchColumn(),
            'citas_hoy' => (int) $this->db->query('SELECT COUNT(*) FROM citas WHERE fecha = CURDATE()')->fetchColumn(),
            'ingresos_mes' => (float) $this->db->query('SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE MONTH(fecha_pago) = MONTH(CURDATE()) AND YEAR(fecha_pago) = YEAR(CURDATE())')->fetchColumn(),
            'cartera' => (new FacturaRepository($this->db))->getOutstandingBalance(),
        ];
    }
}
