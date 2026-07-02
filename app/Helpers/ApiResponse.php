<?php

namespace DentiSoft\App\Helpers;

final class ApiResponse
{
    public static function success(array $data = [], string $message = '', int $statusCode = 200): void
    {
        self::send(array_merge([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $data), $statusCode);
    }

    public static function error(string $message, array $errors = [], int $statusCode = 400): void
    {
        self::send([
            'success' => false,
            'message' => $message,
            'error' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    public static function send(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
