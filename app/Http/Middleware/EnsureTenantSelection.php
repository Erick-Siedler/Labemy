<?php

namespace App\Http\Middleware;

use App\Services\TenantRelationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSelection
{
    /**
     * Garante tenant selecionado para usuarios web com multiplos vinculos.
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            return $next($request);
        }

        $relationService = app(TenantRelationService::class);
        $tenants = $relationService->listAccessibleTenants($user);

        if ($tenants->isEmpty()) {
            return $next($request);
        }

        if ($request->routeIs('tenant.select.index') || $request->routeIs('tenant.select.store')) {
            return $next($request);
        }

        $activeTenantId = (int) session('active_tenant_id', 0);
        if ($activeTenantId > 0 && $relationService->userHasAccessToTenant($user, $activeTenantId)) {
            return $next($request);
        }

        if ($tenants->count() === 1) {
            $relationService->activateTenant($user, (int) $tenants->first()->id);
            return $next($request);
        }

        return redirect()->route('tenant.select.index')
            ->with('status', 'Selecione o tenant que deseja acessar.');
    }
}

