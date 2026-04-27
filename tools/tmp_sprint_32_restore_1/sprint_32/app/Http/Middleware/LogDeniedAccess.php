<?php

namespace App\Http\Middleware;

use App\Services\ActivityService;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LogDeniedAccess
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (AuthorizationException $e) {
            $this->logDenied($request);
            throw $e;
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() === 403) {
                $this->logDenied($request);
            }
            throw $e;
        }
    }

    private function logDenied(Request $request): void
    {
        $actor = ActivityService::resolveActor();
        if (empty($actor['tenant_id'])) {
            return;
        }

        $routeName = $request->route()?->getName();
        $description = 'Acesso negado: ' . $request->method() . ' ' . $request->path();
        if (!empty($routeName)) {
            $description .= ' (rota: ' . $routeName . ')';
        }

        ActivityService::log(
            (int) $actor['tenant_id'],
            (int) $actor['actor_id'],
            (string) $actor['actor_role'],
            'access_denied',
            'access',
            null,
            $description
        );
    }
}
