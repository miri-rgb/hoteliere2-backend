<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // ══════════════════════════════════════════════════════════
    // 1. LISTER TOUS LES UTILISATEURS
    // GET /api/admin/users
    // ══════════════════════════════════════════════════════════
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom',    'like', "%$search%")
                  ->orWhere('prenom', 'like', "%$search%")
                  ->orWhere('email',  'like', "%$search%");
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $users
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 2. VOIR UN UTILISATEUR
    // GET /api/admin/users/{id}
    // ══════════════════════════════════════════════════════════
    public function show(int $id): JsonResponse
    {
        $user = User::with(['preferences', 'reservations'])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $user
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 3. AJOUTER UN UTILISATEUR
    // POST /api/admin/users
    // ══════════════════════════════════════════════════════════
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nom'       => 'required|string|max:100',
            'prenom'    => 'required|string|max:100',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8',
            'role'      => 'required|in:client,admin',
            'telephone' => 'nullable|string|max:20',
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
            'role'      => $request->role,
            'telephone' => $request->telephone,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data'    => $user
        ], 201);
    }

    // ══════════════════════════════════════════════════════════
    // 4. MODIFIER UN UTILISATEUR
    // PUT /api/admin/users/{id}
    // ══════════════════════════════════════════════════════════
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nom'       => 'sometimes|string|max:100',
            'prenom'    => 'sometimes|string|max:100',
            'email'     => 'sometimes|email|unique:users,email,' . $id,
            'password'  => 'sometimes|string|min:8',
            'role'      => 'sometimes|in:client,admin',
            'telephone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $data = $request->only(['nom', 'prenom', 'email', 'role', 'telephone', 'is_active']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur modifié avec succès',
            'data'    => $user->fresh()
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 5. SUPPRIMER UN UTILISATEUR
    // DELETE /api/admin/users/{id}
    // ══════════════════════════════════════════════════════════
    public function destroy(int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable'
            ], 404);
        }

        if ($user->id === Auth::guard('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur supprimé avec succès'
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 6. ACTIVER / DÉSACTIVER UN COMPTE
    // PATCH /api/admin/users/{id}/toggle
    // ══════════════════════════════════════════════════════════
    public function toggle(int $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur introuvable'
            ], 404);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'Compte activé' : 'Compte désactivé',
            'data'    => $user
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 7. MODIFIER SON PROPRE PROFIL
    // PUT /api/auth/profile
    // ══════════════════════════════════════════════════════════
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        $validator = Validator::make($request->all(), [
            'nom'              => 'sometimes|string|max:100',
            'prenom'           => 'sometimes|string|max:100',
            'telephone'        => 'nullable|string|max:20',
            'password'         => 'sometimes|string|min:8|confirmed',
            'current_password' => 'required_with:password|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        if ($request->filled('password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe actuel incorrect'
                ], 403);
            }
        }

        $data = $request->only(['nom', 'prenom', 'telephone']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour',
            'data'    => $user->fresh()
        ]);
    }
}