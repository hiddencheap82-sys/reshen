<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

final class OwnerManagerRequired implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::hasRole('owner', 'manager')) {
            return Response::html('<h1 style="font-family:sans-serif;padding:2rem">دسترسی ندارید.</h1>', 403);
        }

        return $next($request);
    }
}
