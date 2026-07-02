<?php

namespace DentiSoft\App\Services;

use DentiSoft\App\Repositories\PacienteRepository;
use DentiSoft\App\Validators\PacienteValidator;

final class PacienteService
{
    private PacienteRepository $patients;
    private PacienteValidator $validator;

    public function __construct(?PacienteRepository $patients = null, ?PacienteValidator $validator = null)
    {
        $this->patients = $patients ?? new PacienteRepository();
        $this->validator = $validator ?? new PacienteValidator();
    }

    public function search(string $search, int $page, int $limit): array
    {
        return $this->patients->search($search, $page, $limit);
    }

    public function validatePayload(array $payload): array
    {
        return $this->validator->validate($payload);
    }
}
