<?php

namespace App\Http\Middleware;

use App\Services\TenantContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantRole
{
    /**
     * Ordem de privilegio dos papeis.
     */
    private const ROLE_PRIORITY = [
        'student' => 1,
        'assistant' => 2,
        'teacher' => 3,
        'owner' => 4,
    ];

    /**
     * Garante role minimo no tenant ativo da sessao.
     */
    public function handle(Request $request, Closure $next, string ...$requiredRoles): Response
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            abort(401, 'Usuario nao autenticado.');
        }

        $normalizedRequiredRoles = $this->normalizeRequiredRoles($requiredRoles);
        if (empty($normalizedRequiredRoles)) {
            abort(500, 'Middleware tenant.role sem role valido configurado.');
        }

        $tenantContext = app(TenantContextService::class);
        $tenant = $tenantContext->resolveTenantFromSession($user, true);
        $activeRole = $this->normalizeRole($tenantContext->resolveRoleInTenant($user, $tenant));
        if (!$activeRole) {
            abort(403);
        }

        $activePriority = self::ROLE_PRIORITY[$activeRole] ?? 0;
        foreach ($normalizedRequiredRoles as $requiredRole) {
            $requiredPriority = self::ROLE_PRIORITY[$requiredRole];
            if ($activePriority >= $requiredPriority) {
                $request->attributes->set('active_tenant_role', $activeRole);
                return $next($request);
            }
        }

        abort(403, 'Voce nao possui permissao para acessar este recurso.');
    }

    /**
     * Normaliza papeis recebidos na rota.
     */
    private function normalizeRequiredRoles(array $requiredRoles): array
    {
        $normalized = [];
        foreach ($requiredRoles as $requiredRole) {
            $value = $this->normalizeRole($requiredRole);
            if ($value) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Normaliza variacoes de papel para os valores aceitos.
     */
    private function normalizeRole(?string $role): ?string
    {
        $value = strtolower(trim((string) $role));
        if (in_array($value, ['assitant', 'asssitant'], true)) {
            $value = 'assistant';
        }

        return array_key_exists($value, self::ROLE_PRIORITY) ? $value : null;
    }
}
