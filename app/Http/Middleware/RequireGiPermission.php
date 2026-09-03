<?php

namespace App\Http\Middleware;

use App\Services\GiPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireGiPermission
{
    public function handle(Request $request, Closure $next, string $permissao): Response
    {
        app(GiPermissionService::class)->exigir($permissao, $request);

        return $next($request);
    }
}