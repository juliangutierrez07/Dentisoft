<?php

namespace DentiSoft\App\Validators;

final class PacienteValidator
{
    public function validate(array $data): array
    {
        $errors = [];

        if (trim((string) ($data['numero_documento'] ?? '')) === '') {
            $errors[] = 'El numero de documento es obligatorio.';
        }

        if (trim((string) ($data['nombre'] ?? '')) === '') {
            $errors[] = 'El nombre es obligatorio.';
        }

        if (trim((string) ($data['apellido'] ?? '')) === '') {
            $errors[] = 'El apellido es obligatorio.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Ingresa un correo electronico valido.';
        }

        return $errors;
    }
}
