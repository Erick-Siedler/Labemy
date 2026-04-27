<?php

namespace App\Http\Middleware;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentTokenMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $webUser = Auth::guard('web')->user();
        $subUser = Auth::guard('subusers')->user();

        if (!$webUser && !$subUser) {
            abort(401, 'Usuário não autenticado');
        }

        if ($webUser) {
            $tenant = Tenant::where('creator_id', $webUser->id)->first();

            $payment = Payment::where('user_id', $webUser->id)
                ->where('status', 'paid')
                ->latest('created_at')
                ->first();

            if ($tenant) {
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
            }

            if (!$payment) {
                if ($webUser->plan === 'solo') {
                    if ($request->routeIs('home') || $request->routeIs('home-solo')) {
                        return $next($request);
                    }

                    return redirect()->route('home-solo');
                }

                if ((bool) ($webUser->trial_used ?? false)) {
                    return redirect()->route('plans')
                        ->with('error', 'Você já utilizou seu período de teste. Selecione um plano pago.');
                }

                if ($request->routeIs('tenant-create') || $request->routeIs('tenant-store')) {
                    $token = (string) $request->route('token');
                    if ($token === 'trial') {
                        return $next($request);
                    }
                }

                return redirect()->route('tenant-create', ['token' => 'trial']);
            }

            if (!$tenant) {
                $isSoloFlow = $payment->plan === 'solo' || $webUser->plan === 'solo';
                if ($isSoloFlow) {
                    if ($request->routeIs('home') || $request->routeIs('home-solo')) {
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

            $request->merge(['payment_token' => $payment->payment_token]);
            $request->attributes->add([
                'payment' => $payment,
                'tenant' => $tenant,
            ]);

            return $next($request);
        }

        if (empty($subUser->lab_id)) {
            abort(403, 'Subusuário sem vínculo de lab');
        }

        $tenant = Tenant::where('id', $subUser->tenant_id)->first();

        if (!$tenant) {
            abort(403, 'Tenant não encontrado para este laboratório');
        }

        if (empty($tenant->creator_id)) {
            abort(403, 'Tenant sem creator_id definido');
        }

        $payment = Payment::where('user_id', $tenant->creator_id)
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
                ->with('error', 'Seu período de teste expirou. Realize um pagamento para continuar.');
        }

        $request->merge(['payment_token' => $payment->payment_token]);
        $request->attributes->add([
            'payment' => $payment,
            'tenant' => $tenant,
        ]);

        return $next($request);
    }

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
            ->with('error', 'Período de teste expirado. Realize um pagamento para continuar.');
    }

    private function isTrialActive(Tenant $tenant): bool
    {
        if (empty($tenant->trial_ends_at)) {
            return false;
        }

        $trialEndsAt = Carbon::parse($tenant->trial_ends_at)->endOfDay();

        return now()->lt($trialEndsAt);
    }
}
