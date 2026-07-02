<?php
/**
 * Utilidades para cargar variables de entorno - DentiSoft 1.0
 */

function cargarVariablesEntorno(): void {
    static $cargado = false;

    if ($cargado) {
        return;
    }

    $cargado = true;
    $envFile = ROOT_PATH . '/.env';

    if (!is_file($envFile) || !is_readable($envFile)) {
        return;
    }

    try {
        $lineas = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lineas === false) {
            return;
        }

        foreach ($lineas as $linea) {
            $linea = trim($linea);

            if ($linea === '' || str_starts_with($linea, '#') || strpos($linea, '=') === false) {
                continue;
            }

            [$clave, $valor] = explode('=', $linea, 2);
            $clave = trim($clave);
            $valor = trim($valor);

            if ($clave === '') {
                continue;
            }

            $primerCaracter = $valor[0] ?? '';
            $ultimoCaracter = $valor !== '' ? substr($valor, -1) : '';

            if (($primerCaracter === '"' && $ultimoCaracter === '"') || ($primerCaracter === "'" && $ultimoCaracter === "'")) {
                $valor = substr($valor, 1, -1);
            }

            $_ENV[$clave] = $valor;
            putenv($clave . '=' . $valor);
        }
    } catch (Throwable $e) {
        error_log('Error al cargar .env: ' . $e->getMessage());
    }
}

function env(string $clave, mixed $default = null): mixed {
    $valor = getenv($clave);

    if ($valor === false) {
        return $_ENV[$clave] ?? $default;
    }

    return $valor;
}

function env_bool(string $clave, bool $default = false): bool {
    $valor = env($clave, $default ? 'true' : 'false');

    if (is_bool($valor)) {
        return $valor;
    }

    return in_array(strtolower(trim((string) $valor)), ['1', 'true', 'yes', 'on'], true);
}
