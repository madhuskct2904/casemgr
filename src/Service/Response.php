<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class Response
 */
class Response
{
	/**
	 * @param array $data
	 *
	 * @return JsonResponse
	 */
    public function success(array $data = []): JsonResponse
    {
        return new JsonResponse(['status' => 'success', 'data' => $data]);
    }

	/**
	 * @param string $message
	 * @param int $code
	 *
	 * @return JsonResponse
	 */
    public function error(string $message, int $code = 0): JsonResponse
    {
        return new JsonResponse(['status' => 'error', 'message' => $message ,'code' => $code]);
    }

	/**
	 * @param array $data
	 * @param int $code
	 *
	 * @return JsonResponse
	 */
    public function validation(array $data, int $code = 0): JsonResponse
    {
        return new JsonResponse(['status' => 'error', 'data' => $data ,'code' => $code]);
    }

    public function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }
}
