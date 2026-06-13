<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ══════════════════════════════════════════════════════════
    // 1. INSCRIPTION
    // POST /api/auth/register
    // Body : { nom, prenom, email, password, password_confirmation, telephone?, role? }
    // ══════════════════════════════════════════════════════════
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom'       => 'required|string|max:100',
            'prenom'    => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'telephone' => 'nullable|string|max:20',
            'role'      => 'nullable|in:client,admin',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'nom'       => $request->nom,
            'prenom'    => $request->prenom,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'telephone' => $request->telephone,
            'role'      => $request->role ?? 'client',
        ]);

        $token = Auth::guard('api')->login($user);

        return response()->json([
            'success'    => true,
            'message'    => 'Compte créé avec succès',
            'user'       => $user,
            'token'      => $token,
            'type'       => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ], 201);
    }

    // ══════════════════════════════════════════════════════════
    // 2. CONNEXION
    // POST /api/auth/login
    // Body : { email, password }
    // ══════════════════════════════════════════════════════════
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $token = Auth::guard('api')->attempt([
            'email'    => $request->email,
            'password' => $request->password,
        ]);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect'
            ], 401);
        }

        $user = Auth::guard('api')->user();

        if (!$user->is_active) {
            Auth::guard('api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Compte désactivé. Contactez l\'administrateur.'
            ], 403);
        }

        return $this->respondWithToken($token, $user);
    }

    // ══════════════════════════════════════════════════════════
    // 3. DÉCONNEXION
    // POST /api/auth/logout
    // Header : Authorization: Bearer {token}
    // ══════════════════════════════════════════════════════════
    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 4. RAFRAÎCHIR LE TOKEN JWT
    // POST /api/auth/refresh
    // Header : Authorization: Bearer {token}
    // ══════════════════════════════════════════════════════════
    public function refresh(): JsonResponse
    {
        try {
            $token = Auth::guard('api')->refresh();
            $user  = Auth::guard('api')->user();
            return $this->respondWithToken($token, $user);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token invalide ou expiré, veuillez vous reconnecter'
            ], 401);
        }
    }

    // ══════════════════════════════════════════════════════════
    // 5. VOIR SON PROFIL
    // GET /api/auth/me
    // Header : Authorization: Bearer {token}
    // ══════════════════════════════════════════════════════════
    public function me(): JsonResponse
    {
        $user = Auth::guard('api')->user();

        return response()->json([
            'success' => true,
            'user'    => $user->load('preferences')
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // HELPER PRIVÉ : formater la réponse token
    // ══════════════════════════════════════════════════════════
    private function respondWithToken(string $token, User $user): JsonResponse
    {
        return response()->json([
            'success'    => true,
            'user'       => $user,
            'token'      => $token,
            'type'       => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ]);
    }

// ══════════════════════════════════════════════════════════════
// AJOUTER CES 2 MÉTHODES dans AuthController.php
// ══════════════════════════════════════════════════════════════

// ── MÉTHODE 1 : Connexion avec Google (simulation interne) ────
// POST /api/auth/google
// Body : { google_id, email, nom, prenom, avatar }
// ══════════════════════════════════════════════════════════════
public function loginAvecGoogle(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'google_id' => 'required|string',
        // L'identifiant unique Google de l'utilisateur
        // En vrai OAuth ce serait fourni par Google
        // Ici le frontend l'envoie directement

        'email'     => 'required|email',
        'nom'       => 'required|string',
        'prenom'    => 'required|string',
        'avatar'    => 'nullable|string',
        // URL de la photo de profil
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors'  => $validator->errors()
        ], 422);
    }

    // Cherche si un compte existe déjà avec ce google_id OU cet email
    $user = User::where('google_id', $request->google_id)
                ->orWhere('email', $request->email)
                ->first();

    if ($user) {
        // ── Utilisateur existant → met à jour ses infos Google ──
        $user->update([
            'google_id' => $request->google_id,
            'avatar'    => $request->avatar,
        ]);

        // Génère un token JWT pour cet utilisateur
        $token = Auth::guard('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Connexion Google réussie',
            'nouveau_compte' => false,
            'user'    => $user,
            'token'   => $token,
            'type'    => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ]);

    } else {
        // ── Nouvel utilisateur → crée le compte automatiquement ──
        $user = User::create([
            'nom'       => $request->nom,
            'prenom'    => $request->prenom,
            'email'     => $request->email,
            'password'  => Hash::make(\Illuminate\Support\Str::random(24)),
            // Pas de mot de passe pour les comptes Google
            // L'utilisateur se connecte toujours via Google

            'google_id' => $request->google_id,
            'avatar'    => $request->avatar,
            'role'      => 'client',
            // Les comptes Google sont toujours des clients
            'is_active' => true,
        ]);

        // Génère un token JWT pour le nouveau compte
        $token = Auth::guard('api')->login($user);

        return response()->json([
            'success' => true,
            'message' => 'Compte créé et connecté via Google',
            'nouveau_compte' => true,
            'user'    => $user,
            'token'   => $token,
            'type'    => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ], 201);
    }
}

// ══════════════════════════════════════════════════════════════
// MÉTHODE 2 : Vérifier si un email Google existe déjà
// GET /api/auth/google/check?email=...
// ══════════════════════════════════════════════════════════════
public function verifierCompteGoogle(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors'  => $validator->errors()
        ], 422);
    }

    $existe = User::where('email', $request->email)->exists();

    return response()->json([
        'success' => true,
        'existe'  => $existe,
        'message' => $existe
            ? 'Un compte existe déjà avec cet email'
            : 'Aucun compte existant, un nouveau sera créé',
    ]);
}
} 