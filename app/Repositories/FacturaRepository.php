<?php

namespace DentiSoft\App\Repositories;

final class FacturaRepository extends BaseRepository
{
    public function getOutstandingBalance(): float
    {
        return (float) $this->db
            ->query("SELECT COALESCE(SUM(saldo_pendiente), 0) FROM facturas WHERE estado IN ('pendiente','parcial','vencida')")
            ->fetchColumn();
    }
}
