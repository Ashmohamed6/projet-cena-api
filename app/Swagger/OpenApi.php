<?php

namespace App\Swagger;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *   version="1.0.0",
 *   title="CENA API",
 *   description="Documentation API CENA Bénin 2026"
 * )
 *
 * @OA\Server(
 *   url="/",
 *   description="Serveur API"
 * )
 *
 * @OA\Get(
 *   path="/api/v1/ping",
 *   tags={"Health"},
 *   summary="Ping API",
 *   @OA\Response(
 *     response=200,
 *     description="OK"
 *   )
 * )
 */
class OpenApi {}
