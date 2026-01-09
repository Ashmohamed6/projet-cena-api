<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\{Hash, DB};
use Illuminate\Validation\ValidationException;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 * name="Auth",
 * description="Authentification et gestion du profil utilisateur"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     * path="/api/v1/login",
     * operationId="login",
     * tags={"Auth"},
     * summary="Connexion utilisateur",
     * description="Authentifie l'utilisateur et retourne un token d'accès",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"email", "password"},
     * @OA\Property(property="email", type="string", format="email", example="admin@cena.bj"),
     * @OA\Property(property="password", type="string", format="password", example="secret")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Connexion réussie",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string"),
     * @OA\Property(property="data", type="object")
     * )
     * ),
     * @OA\Response(response=401, description="Identifiants incorrects"),
     * @OA\Response(response=403, description="Compte inactif")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Récupérer l'utilisateur
        $user = User::where('email', $validated['email'])->first();

        // Vérifier credentials
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        // ✅ Vérifier que l'utilisateur est actif (champ "statut")
        if ($user->statut !== 'actif') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte est ' . $user->statut . '. Veuillez contacter l\'administrateur.',
            ], 403);
        }

        // Révoquer anciens tokens
        $user->tokens()->delete();

        // Créer nouveau token
        $token = $user->createToken('api-token')->plainTextToken;

        // Mettre à jour dernière connexion
        $user->update([
            'derniere_connexion' => now(),
            'ip_derniere_connexion' => $request->ip(),
        ]);

        // Logger l'activité
        ActivityLog::log(
            action: 'login',
            module: 'auth',
            entityType: 'User',
            entityId: $user->id,
            description: 'Connexion réussie',
            metadata: [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        // ✅ Charger relations (avec typeElection !)
        $user->load([
            'affectations' => function ($query) {
                $query->where('actif', true); // ✅ Utilise "actif"
            },
            'affectations.role',
            'affectations.election.typeElection' // ✅ Charger la relation
        ]);

        // ✅ Récupérer les élections avec types depuis la relation
        $elections = $user->affectations
            ->pluck('election')
            ->filter()
            ->unique('id')
            ->values()
            ->map(function($e) {
                // ✅ Déterminer le type depuis la relation
                $type = 'legislative'; // Défaut
                
                if ($e->typeElection) {
                    // Normaliser le nom du type
                    $typeNom = strtolower($e->typeElection->nom);
                    
                    if (str_contains($typeNom, 'législative') || str_contains($typeNom, 'legislative')) {
                        $type = 'legislative';
                    } elseif (str_contains($typeNom, 'communale')) {
                        $type = 'communale';
                    } elseif (str_contains($typeNom, 'présidentielle') || str_contains($typeNom, 'presidentielle')) {
                        $type = 'presidentielle';
                    }
                } elseif (isset($e->type)) {
                    // Si colonne type existe (OPTION A)
                    $type = $e->type;
                } else {
                    // Fallback sur type_election_id
                    $typeMap = [
                        1 => 'legislative',
                        2 => 'communale',
                        3 => 'presidentielle',
                    ];
                    $type = $typeMap[$e->type_election_id] ?? 'legislative';
                }
                
                return [
                    'id' => $e->id,
                    'code' => $e->code,
                    'nom' => $e->nom,
                    'type' => $type, // ✅ Type déterminé
                    'date_scrutin' => $e->date_scrutin,
                    'statut' => $e->statut,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'code' => $user->code,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom, // ✅ Uniformisé à "prenom"
                    'email' => $user->email,
                    'telephone' => $user->telephone,
                    'photo' => $user->photo,
                    'statut' => $user->statut,
                    'derniere_connexion' => $user->derniere_connexion,
                ],
                'affectations' => $user->affectations->map(function ($affectation) {
                    return [
                        'id' => $affectation->id,
                        'role' => [
                            'id' => $affectation->role->id,
                            'name' => $affectation->role->name,
                            'slug' => $affectation->role->slug,
                        ],
                        'election' => $affectation->election ? [
                            'id' => $affectation->election->id,
                            'code' => $affectation->election->code,
                            'nom' => $affectation->election->nom,
                        ] : null,
                        'niveau_affectation' => $affectation->niveau_affectation,
                        'niveau_affectation_id' => $affectation->niveau_affectation_id,
                        'actif' => $affectation->actif,
                    ];
                }),
                'token' => $token,
                'elections' => $elections,
            ],
        ]);
    }

    /**
     * @OA\Post(
     * path="/api/v1/logout",
     * operationId="logout",
     * tags={"Auth"},
     * summary="Déconnexion",
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=200,
     * description="Déconnexion réussie",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="Déconnexion réussie")
     * )
     * )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        ActivityLog::log(
            action: 'logout',
            module: 'auth',
            entityType: 'User',
            entityId: $request->user()->id,
            description: 'Déconnexion',
            metadata: [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie',
        ]);
    }

    /**
     * @OA\Get(
     * path="/api/v1/me",
     * operationId="me",
     * tags={"Auth"},
     * summary="Informations de l'utilisateur connecté",
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=200,
     * description="Détails utilisateur",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="object")
     * )
     * )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load([
            'affectations.role',
            'affectations.election.typeElection'
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'code' => $user->code,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                    'email' => $user->email,
                    'telephone' => $user->telephone,
                    'photo' => $user->photo,
                    'statut' => $user->statut,
                    'derniere_connexion' => $user->derniere_connexion,
                    'email_verified_at' => $user->email_verified_at,
                ],
                'affectations' => $user->affectations->map(function ($affectation) {
                    return [
                        'id' => $affectation->id,
                        'role' => [
                            'id' => $affectation->role->id,
                            'name' => $affectation->role->name,
                            'slug' => $affectation->role->slug,
                            'description' => $affectation->role->description,
                            'niveau_hierarchique' => $affectation->role->niveau_hierarchique,
                        ],
                        'election' => $affectation->election ? [
                            'id' => $affectation->election->id,
                            'code' => $affectation->election->code,
                            'nom' => $affectation->election->nom,
                        ] : null,
                        'niveau_affectation' => $affectation->niveau_affectation,
                        'niveau_affectation_id' => $affectation->niveau_affectation_id,
                        'date_debut' => $affectation->date_debut,
                        'date_fin' => $affectation->date_fin,
                        'actif' => $affectation->actif,
                    ];
                }),
            ],
        ]);
    }

    /**
     * @OA\Get(
     * path="/api/v1/elections",
     * operationId="getMyElections",
     * tags={"Auth"},
     * summary="Liste des élections accessibles",
     * security={{"bearerAuth":{}}},
     * @OA\Response(
     * response=200,
     * description="Liste des élections",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="data", type="object")
     * )
     * )
     * )
     */
    public function elections(Request $request): JsonResponse
    {
        $user = $request->user();

        $affectations = $user->affectations()
            ->with(['election', 'election.typeElection'])
            ->where('actif', true)
            ->get();

        $elections = $affectations
            ->pluck('election')
            ->filter()
            ->unique('id')
            ->values()
            ->map(function($e) {
                $type = 'legislative';
                
                if ($e->typeElection) {
                    $typeNom = strtolower($e->typeElection->nom);
                    if (str_contains($typeNom, 'législative') || str_contains($typeNom, 'legislative')) {
                        $type = 'legislative';
                    } elseif (str_contains($typeNom, 'communale')) {
                        $type = 'communale';
                    } elseif (str_contains($typeNom, 'présidentielle') || str_contains($typeNom, 'presidentielle')) {
                        $type = 'presidentielle';
                    }
                } elseif (isset($e->type)) {
                    $type = $e->type;
                }
                
                return [
                    'id' => $e->id,
                    'code' => $e->code,
                    'nom' => $e->nom,
                    'type' => $type,
                    'date_scrutin' => $e->date_scrutin,
                    'statut' => $e->statut,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Élections accessibles',
            'data' => [
                'elections' => $elections,
            ],
        ]);
    }

    /**
     * @OA\Get(
     * path="/api/v1/permissions",
     * operationId="getMyPermissions",
     * tags={"Auth"},
     * summary="Permissions de l'utilisateur",
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="Liste des permissions")
     * )
     */
    public function permissions(Request $request): JsonResponse
    {
        $user = $request->user();

        $permissions = collect();

        foreach ($user->affectations as $affectation) {
            $rolePermissions = DB::table('permissions')
                ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('role_permissions.role_id', $affectation->role_id)
                ->select('permissions.*')
                ->get();

            $permissions = $permissions->merge($rolePermissions);
        }

        $permissions = $permissions->unique('id')->values();

        $permissionsParModule = $permissions->groupBy('module')->map(function ($modulePermissions) {
            return $modulePermissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                    'description' => $permission->description,
                ];
            })->values();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => $permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'slug' => $permission->slug,
                        'module' => $permission->module,
                        'description' => $permission->description,
                    ];
                })->values(),
                'permissions_par_module' => $permissionsParModule,
                'total' => $permissions->count(),
            ],
        ]);
    }

    /**
     * @OA\Put(
     * path="/api/v1/profile",
     * operationId="updateProfile",
     * tags={"Auth"},
     * summary="Mettre à jour le profil",
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="nom", type="string"),
     * @OA\Property(property="prenom", type="string"),
     * @OA\Property(property="telephone", type="string"),
     * @OA\Property(property="email", type="string", format="email")
     * )
     * ),
     * @OA\Response(response=200, description="Profil mis à jour")
     * )
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        // ✅ Harmonisation : 'prenom' au lieu de 'prenoms'
        $validated = $request->validate([
            'nom' => 'sometimes|string|max:200',
            'prenom' => 'sometimes|string|max:200', 
            'telephone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $oldValues = $user->only(array_keys($validated));

        $user->update($validated);

        ActivityLog::log(
            action: 'update',
            module: 'auth',
            entityType: 'User',
            entityId: $user->id,
            description: 'Mise à jour du profil',
            oldValues: $oldValues,
            newValues: $validated,
            metadata: [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'code' => $user->code,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom, // ✅ "prenom"
                    'email' => $user->email,
                    'telephone' => $user->telephone,
                ],
            ],
        ]);
    }

    /**
     * @OA\Post(
     * path="/api/v1/change-password",
     * operationId="changePassword",
     * tags={"Auth"},
     * summary="Changer le mot de passe",
     * security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"current_password", "new_password", "new_password_confirmation"},
     * @OA\Property(property="current_password", type="string"),
     * @OA\Property(property="new_password", type="string"),
     * @OA\Property(property="new_password_confirmation", type="string")
     * )
     * ),
     * @OA\Response(response=200, description="Mot de passe changé")
     * )
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le mot de passe actuel est incorrect',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        ActivityLog::log(
            action: 'change_password',
            module: 'auth',
            entityType: 'User',
            entityId: $user->id,
            description: 'Changement de mot de passe',
            metadata: [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe changé avec succès',
            'data' => [
                'token' => $token,
            ],
        ]);
    }

    /**
     * @OA\Get(
     * path="/api/v1/roles",
     * operationId="getRoles",
     * tags={"Auth"},
     * summary="Liste des rôles",
     * security={{"bearerAuth":{}}},
     * @OA\Response(response=200, description="Liste des rôles")
     * )
     */
    public function getRoles(): JsonResponse
    {
        $roles = DB::table('roles')
            ->orderBy('niveau_hierarchique')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'niveau_hierarchique' => $role->niveau_hierarchique,
                    'restriction_geographique' => $role->restriction_geographique,
                ];
            }),
        ]);
    }
}