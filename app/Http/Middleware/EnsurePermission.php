<?php

namespace App\Http\Middleware;

use App\Support\Permissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $module, ?string $ability = null): Response
    {
        $ability ??= $request->isMethod('GET') || $request->isMethod('HEAD') ? 'view' : 'manage';

        if (! Permissions::current($module, $ability)) {
            if ($request->expectsJson()) {
                abort(403, 'Anda tidak memiliki hak akses untuk aksi ini.');
            }

            abort(403, 'Anda tidak memiliki hak akses ke halaman atau aksi ini.');
        }

        return $next($request);
    }
}
