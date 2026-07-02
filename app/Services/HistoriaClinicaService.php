<?php

namespace DentiSoft\App\Services;

use DentiSoft\App\Repositories\HistoriaClinicaRepository;

final class HistoriaClinicaService
{
    private HistoriaClinicaRepository $histories;

    public function __construct(?HistoriaClinicaRepository $histories = null)
    {
        $this->histories = $histories ?? new HistoriaClinicaRepository();
    }

    public function search(string $search, int $page, int $limit): array
    {
        return $this->histories->search($search, $page, $limit);
    }
}
