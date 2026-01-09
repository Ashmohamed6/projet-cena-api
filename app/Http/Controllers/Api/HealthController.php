<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

class HealthController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/v1/ping",
     *   tags={"Health"},
     *   summary="Ping API",
     *   description="Vérifie que l'API répond.",
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="API CENA Bénin 2026"),
     *       @OA\Property(property="version", type="string", example="1.0.0"),
     *       @OA\Property(property="status", type="string", example="ok")
     *     )
     *   )
     * )
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'message' => 'API CENA Bénin 2026',
            'version' => '1.0.0',
            'status'  => 'ok',
        ]);
    }
}
