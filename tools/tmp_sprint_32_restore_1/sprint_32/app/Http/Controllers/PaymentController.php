<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function checkout(string $plan)
    {
        if (!Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Faça login para continuar.');
        }

        $plans = [
            'solo' => 99.99,
            'pro' => 159.99,
            'enterprise' => 599.99,
        ];

        if (!array_key_exists($plan, $plans)) {
            abort(404);
        }

        if ($plan === 'enterprise') {
            return redirect()->route('plans')
                ->with('error', 'Plano Enterprise em desenvolvimento.');
        }

        $user = Auth::user();

        $hasPaidAccess = Payment::where('user_id', $user->id)
            ->where('status', 'paid')
            ->exists();

        $canStartTrial =
            $user->plan === 'free'
            && !$hasPaidAccess
            && !(bool) ($user->trial_used ?? false);

        if ($canStartTrial) {
            if ($plan === 'solo') {
                $user->plan = 'solo';
                $user->trial_used = true;
                $user->save();

                return redirect()->route('home-solo');
            }

            return redirect()->route('tenant-create', ['token' => 'trial']);
        }

        return view('main/payment/checkout', [
            'plan' => $plan,
            'amount' => $plans[$plan],
        ]);
    }

    public function pay(Request $request, string $plan)
    {
        if (!Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Faça login para continuar.');
        }

        $plans = [
            'solo' => 99.99,
            'pro' => 159.99,
            'enterprise' => 599.99,
        ];

        if (!array_key_exists($plan, $plans)) {
            abort(404);
        }

        if ($plan === 'enterprise') {
            return redirect()->route('plans')
                ->with('error', 'Plano Enterprise em desenvolvimento.');
        }

        $request->validate([
            'email' => 'required|email',
            'cpf' => 'required',
        ]);

        $payment = Payment::create([
            'user_id' => Auth::id(),
            'email' => $request->email,
            'CPF/CNPJ' => $request->cpf,
            'plan' => $plan,
            'amount' => $plans[$plan],
            'status' => 'paid',
            'payment_token' => Str::uuid(),
        ]);

        $user = Auth::user();
        $tenant = Tenant::where('creator_id', $user->id)->first();

        $user->plan = $payment->plan;
        $user->save();

        if ($tenant) {
            $tenant->plan = $payment->plan;
            $tenant->trial_ends_at = null;
            $tenant->status = 'active';
            $tenant->save();

            return redirect()->route($plan === 'solo' ? 'home-solo' : 'home');
        }

        if ($plan === 'solo') {
            return redirect()->route('home-solo');
        }

        return redirect()->route('tenant-create', [
            'token' => $payment->payment_token,
            'plan' => $plan,
        ]);
    }

    public function cancelTrial()
    {
        if (!Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Faça login para continuar.');
        }

        $user = Auth::user();
        $tenant = Tenant::where('creator_id', $user->id)->first();

        $hasPaidAccess = Payment::where('user_id', $user->id)
            ->where('status', 'paid')
            ->exists();

        if ($hasPaidAccess) {
            return redirect()->route('plans')
                ->with('error', 'Seu plano atual já é pago.');
        }

        $trialActive = false;
        if ($tenant && !empty($tenant->trial_ends_at)) {
            $trialEndsAt = Carbon::parse($tenant->trial_ends_at)->endOfDay();
            $trialActive = now()->lt($trialEndsAt);
        }

        $soloTrialActive = $user->plan === 'solo';

        if (!$trialActive && !$soloTrialActive) {
            return redirect()->route('plans')
                ->with('error', 'Você não possui trial ativo para cancelar.');
        }

        $user->plan = 'none';
        $user->trial_used = true;
        $user->save();

        if ($tenant) {
            $tenant->status = 'suspended';
            $tenant->trial_ends_at = now()->subDay()->toDateString();
            $tenant->save();
        }

        return redirect()->route('plans')
            ->with('success', 'Trial cancelado. Para continuar, selecione um plano pago.');
    }
}
