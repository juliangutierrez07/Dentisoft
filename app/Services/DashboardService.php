<?php

namespace DentiSoft\App\Services;

use DentiSoft\App\Repositories\DashboardRepository;

final class DashboardService
{
    private DashboardRepository $dashboard;

    public function __construct(?DashboardRepository $dashboard = null)
    {
        $this->dashboard = $dashboard ?? new DashboardRepository();
    }

    public function kpis(): array
    {
        return $this->dashboard->kpis();
    }
}
