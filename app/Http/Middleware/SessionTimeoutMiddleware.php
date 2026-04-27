<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeoutMiddleware
{
    /**
     * Expira a autenticacao apos periodo de inatividade.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::guard('web')->check()) {
            return $next($request);
        }

        $maxIdleMinutes = max(1, (int) config('session.idle_timeout', config('session.lifetime', 120)));
        $lastActivityAt = (int) $request->session()->get('last_activity_at', 0);
        $now = now()->getTimestamp();

        if ($lastActivityAt > 0) {
            $idleSeconds = $now - $lastActivityAt;
            if ($idleSeconds >= ($maxIdleMinutes * 60)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'Sua sessao expirou por inatividade. Faca login novamente.');
            }
        }

        $request->session()->put('last_activity_at', $now);

        return $next($request);
    }
}
