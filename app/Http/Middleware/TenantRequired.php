<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Ensures an active salon is selected in session AND that the logged-in
 * user actually has a membership row for it — the first of the three
 * tenancy-isolation layers described in the product doc (8.4): even if a
 * query forgot to scope by salon_id, no controller action runs without
 * this check passing first.
 */
final class TenantRequired implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (Auth::isPlatformAdmin() && Auth::isImpersonating()) {
            return $next($request);
        }

        $memberships = Auth::memberships();

        if (empty($memberships)) {
            return Response::redirect('/onboarding');
        }

        $salonId = Auth::salonId();
        $valid = $salonId !== null && array_filter($memberships, static fn ($m) => (int) $m['salon_id'] === $salonId);

        if (!$valid) {
            if (count($memberships) === 1) {
                Auth::setSalon((int) $memberships[0]['salon_id']);
            } else {
                return Response::redirect('/salons');
            }
        }

        return $next($request);
    }
}
