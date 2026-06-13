<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {});
    }

    /**
     * ─────────────────────────────────────────────────────
     * CORRECTION PRINCIPALE :
     * Au lieu de rediriger vers "route [login]" (comportement
     * web par défaut), on retourne une réponse JSON 401.
     * ─────────────────────────────────────────────────────
     */
    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Non authentifié. Veuillez vous connecter et fournir un token JWT valide.',
        ], 401);
    }
}