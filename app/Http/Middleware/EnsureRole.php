<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessException;
use Closure;
use Illuminate\Http\Request;

// Se evalúa antes de buscar el recurso: un 403 no revela si existe (contrato §4).
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (! in_array($request->user()?->role, $roles, true)) {
            throw new BusinessException('FORBIDDEN_ROLE', 'No tiene permisos para esta operación.', 403);
        }
        return $next($request);
    }
}