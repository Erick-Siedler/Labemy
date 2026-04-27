<?php

namespace App\Http\Middleware;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRelation;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentTokenMiddleware
{
    /**
     * Executa a regra principal deste middleware.
     */
    public function handle(Request $request, Closure $next)
    {
        $webUser = Auth::guard('web')->user();
        if (!$webUser) {
            abort(401, 'Usuario nao autenticado');
        }

        $tenant = $this->resolveTenantFromSessionOrRelation((int) $webUser->id);
        if ($tenant) {
            $payment = Payment::where('user_id', (int) $tenant->creator_id)
                ->where('status', 'paid')
                ->latest('created_at')
                ->first();

            $trialResponse = $this->enforceTrial($tenant, $payment);
            if ($trialResponse) {
                return $trialResponse;
            }

            if (!$payment && $this->isTrialActive($tenant)) {
                $request->attributes->add([
                    'tenant' => $tenant,
                ]);

                return $next($request);
            }

            if (!$payment) {
                return redirect()->route('plans')
                    ->with('error', 'Periodo de teste expirado para este tenant. Realize um pagamento para continuar.');
            }

            $request->merge(['payment_token' => $payment->payment_token]);
            $request->attributes->add([
                'payment' => $payment,
                'tenant' => $tenant,
            ]);

            return $next($request);
        }

        $payment = Payment::where('user_id', (int) $webUser->id)
            ->where('status', 'paid')
            ->latest('created_at')
            ->first();

        if (!$payment) {
            if ($webUser->plan === 'solo') {
                if ($this->isSoloAccessibleRoute($request)) {
                    return $next($request);
                }

                return redirect()->route('home-solo');
            }

            if ((bool) ($webUser->trial_used ?? false)) {
                return redirect()->route('plans')
                    ->with('error', 'Voce ja utilizou seu periodo de teste. Selecione um plano pago.');
            }

            if ($request->routeIs('tenant-create') || $request->routeIs('tenant-store')) {
                $token = (string) $request->route('token');
                if ($token === 'trial') {
                    return $next($request);
                }
            }

            if ((string) ($webUser->plan ?? '') === 'pro') {
                return redirect()->route('tenant-create', ['token' => 'trial']);
            }

            return redirect()->route('plans');
        }

        $isSoloFlow = $payment->plan === 'solo' || $webUser->plan === 'solo';
        if ($isSoloFlow) {
            if ($this->isSoloAccessibleRoute($request)) {
                $request->merge(['payment_token' => $payment->payment_token]);
                $request->attributes->add(['payment' => $payment]);

                return $next($request);
            }

            return redirect()->route('home-solo');
        }

        if ($request->routeIs('tenant-create') || $request->routeIs('tenant-store')) {
            $request->merge(['payment_token' => $payment->payment_token]);
            $request->attributes->add(['payment' => $payment]);

            return $next($request);
        }

        return redirect()->route('tenant-create', ['token' => $payment->payment_token]);
    }

    /**
     * Executa a rotina 'enforceTrial' no fluxo de negocio.
     */
    private function enforceTrial(Tenant $tenant, ?Payment $payment): ?RedirectResponse
    {
        if ($payment) {
            return null;
        }

        if (empty($tenant->trial_ends_at)) {
            return $this->handleExpiredTrial($tenant, now());
        }

        $trialEndsAt = Carbon::parse($tenant->trial_ends_at)->endOfDay();
        if (now()->lt($trialEndsAt)) {
            return null;
        }

        return $this->handleExpiredTrial($tenant, $trialEndsAt);
    }

    /**
     * Executa a rotina 'handleExpiredTrial' no fluxo de negocio.
     */
    private function handleExpiredTrial(Tenant $tenant, Carbon $trialEndsAt): RedirectResponse
    {
        User::where('id', $tenant->creator_id)->update([
            'plan' => 'none',
            'trial_used' => true,
        ]);

        Payment::where('user_id', $tenant->creator_id)
            ->where('status', 'paid')
            ->delete();

        if ($tenant->status !== 'suspended') {
            $tenant->status = 'suspended';
            $tenant->save();
        }

        return redirect()->route('plans')
            ->with('error', 'Periodo de teste expirado. Realize um pagamento para continuar.');
    }

    /**
     * Executa a rotina 'isTrialActive' no fluxo de negocio.
     */
    private function isTrialActive(Tenant $tenant): bool
    {
        if (empty($tenant->trial_ends_at)) {
            return false;
        }

        $trialEndsAt = Carbon::parse($tenant->trial_ends_at)->endOfDay();

        return now()->lt($trialEndsAt);
    }

    /**
     * Resolve tenant ativo por sessao e fallback de relacionamento.
     */
    private function resolveTenantFromSessionOrRelation(int $userId): ?Tenant
    {
        $selectedTenantId = (int) session('active_tenant_id', 0);
        if ($selectedTenantId > 0) {
            $ownsTenant = Tenant::where('id', $selectedTenantId)
                ->where('creator_id', $userId)
                ->exists();

            $hasRelation = UserRelation::where('user_id', $userId)
                ->where('tenant_id', $selectedTenantId)
                ->where('status', 'active')
                ->exists();

            if ($ownsTenant || $hasRelation) {
                return Tenant::where('id', $selectedTenantId)->first();
            }
        }

        $ownedTenant = Tenant::where('creator_id', $userId)->first();
        if ($ownedTenant) {
            session(['active_tenant_id' => (int) $ownedTenant->id]);
            return $ownedTenant;
        }

        $relatedTenantId = UserRelation::where('user_id', $userId)
            ->where('status', 'active')
            ->value('tenant_id');

        if (!$relatedTenantId) {
            return null;
        }

        session(['active_tenant_id' => (int) $relatedTenantId]);
        return Tenant::where('id', (int) $relatedTenantId)->first();
    }

    /**
     * Define rotas acessiveis no fluxo solo.
     */
    private function isSoloAccessibleRoute(Request $request): bool
    {
        return $request->routeIs('home')
            || $request->routeIs('home-solo')
            || $request->routeIs('profile')
            || $request->routeIs('profile-edit')
            || $request->routeIs('settings')
            || $request->routeIs('settings-edit');
    }
}

