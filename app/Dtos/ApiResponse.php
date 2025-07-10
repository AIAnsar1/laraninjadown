<?php


namespace App\Dtos;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as Status;


class ApiResponse
{
    /**
     * ================================================================
     * success()
     * ================================================================
     * Создаёт успешный JSON-ответ.
     *
     * @param mixed $Data
     * @return JsonResponse
     */
    public static function success(array $data): JsonResponse
    {
        return Response::json([
            "data" => $data,
            "success" => true,
        ], Status::HTTP_OK);
    }

    /**
     * ================================================================
     * error()
     * ================================================================
     * Создаёт JSON-ответ с ошибкой.
     *
     * @param string $message
     * @param int $status
     * @param bool $isArray
     * @return JsonResponse
     */
    public static function error(string $message, int $status = Status::HTTP_OK, bool $isArray = false): JsonResponse
    {
        if ($isArray)
        {
            $message = reset($message)[0];
        }
        return Response::json([
            "message" => $message,
            "success" => false,
        ], $status);
    }
}
