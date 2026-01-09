<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="CENA API Documentation",
 *     version="1.0.0",
 *     description="API de gestion électorale - Commission Électorale Nationale Autonome du Bénin
 *
 *     Cette API permet de gérer :
 *     - Les procès-verbaux (PV) de compilation électorale
 *     - Les données géographiques (départements, communes, arrondissements, villages/quartiers)
 *     - La validation et le suivi des PV
 *     - Les résultats électoraux
 *
 *     Types d'élections supportés :
 *     - Législatives (2026)
 *     - Communales
 *     - Présidentielles
 *
 *     Niveaux de compilation des PV :
 *     - PV Arrondissement : compilation par village/quartier
 *     - PV Village/Quartier : compilation par poste de vote
 *     - PV Commune : compilation par arrondissement",
 *
 *     @OA\Contact(
 *         email="support@cena.bj",
 *         name="Support CENA"
 *     ),
 *     @OA\License(
 *         name="Propriétaire",
 *         url="https://www.cena.bj"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:9000",
 *     description="Serveur de développement local"
 * )
 *
 * @OA\Server(
 *     url="https://api.cena.bj",
 *     description="Serveur de production"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token",
 *     description="Authentification via token Sanctum. Format : Bearer {votre-token}",
 *     name="Authorization",
 *     in="header"
 * )
 *
 * @OA\Tag(
 *     name="Géographie",
 *     description="Endpoints pour récupérer les données géographiques (départements, communes, etc.)"
 * )
 *
 * @OA\Tag(
 *     name="Procès-Verbaux",
 *     description="Gestion complète des procès-verbaux de compilation électorale"
 * )
 *
 * @OA\Tag(
 *     name="Validation PV",
 *     description="Endpoints de validation et vérification des PV"
 * )
 *
 * ✅ IMPORTANT : il faut au moins 1 endpoint annoté (PathItem) sinon swagger-php lève:
 * Required @OA\PathItem() not found
 *
 * @OA\Get(
 *     path="/api/v1/ping",
 *     tags={"Health"},
 *     summary="Ping API",
 *     description="Vérifie que l'API répond.",
 *     @OA\Response(
 *         response=200,
 *         description="OK",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="message", type="string", example="API CENA Bénin 2026"),
 *             @OA\Property(property="version", type="string", example="1.0.0"),
 *             @OA\Property(property="status", type="string", example="ok")
 *         )
 *     )
 * )
 */
class SwaggerController extends Controller
{
    /**
     * Ce controller sert uniquement pour les annotations Swagger de base.
     * Il ne contient aucune logique métier.
     *
     * La documentation complète est accessible à :
     * http://localhost:9000/api/documentation
     */
}
