<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Executa a rotina 'checkout' no fluxo de negocio.
     */
    public function checkout(string $plan)
    {
        if (!Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Faça login para continuar.');
        }

        $plans = [
            'solo' => 99.99,
            'pro' => 599.99,
            'enterprise' => 999.99,
        ];

        if (!array_key_exists($plan, $plans)) {
            abort(404);
        }

        if ($plan === 'enterprise') {
            return redirect()->route('plans')
                ->with('error', 'Plano Enterprise em desenvolvimento.');
        }

        $user = Auth::user();
        $tenant = Tenant::where('creator_id', $user->id)->first();

        $hasPaidAccess = Payment::where('user_id', $user->id)
            ->where('status', 'paid')
            ->exists();

        $canStartTrial =
            !$tenant
            && !$hasPaidAccess
            && !(bool) ($user->trial_used ?? false);

        if ($canStartTrial) {
            if ($plan === 'solo') {
                $user->plan = 'solo';
                $user->trial_used = true;
                $user->save();

                return redirect()->route('home-solo');
            }

            if ($plan === 'pro'){
                if ((string) $user->plan !== 'pro') {
                    $user->plan = 'pro';
                    $user->save();
                }

                return redirect()->route('tenant-create', ['token' => 'trial']);
            }

            return redirect()->route('tenant-create', ['token' => 'trial']);
        }

        return view('main/payment/checkout', [
            'plan' => $plan,
            'amount' => $plans[$plan],
        ]);
    }

    /**
     * Executa a rotina 'pay' no fluxo de negocio.
     */
    public function pay(Request $request, string $plan)
    {
        if (!Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Faça login para continuar.');
        }

        $plans = [
            'solo' => 99.99,
            'pro' => 599.99,
            'enterprise' => 999.99,
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
            'cpf' => 'required|string|min:11|max:18',
        ]);

        $cpf = $this->normalizeCpf((string) $request->input('cpf'));
        if (strlen($cpf) !== 11) {
            return $this->redirectCheckoutWithCpfError(
                $plan,
                $request,
                'CPF invalido para pagamento. Informe um CPF valido.'
            );
        }

        if ($this->cpfAlreadyUsed($cpf)) {
            return $this->redirectCheckoutWithCpfError(
                $plan,
                $request,
                'Este CPF ja esta vinculado a outro pagamento.'
            );
        }

        try {
            $payment = Payment::create([
                'user_id' => Auth::id(),
                'email' => (string) $request->input('email'),
                'CPF/CNPJ' => $cpf,
                'plan' => $plan,
                'amount' => $plans[$plan],
                'status' => 'paid',
                'payment_token' => Str::uuid(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isDuplicateCpfException($exception)) {
                return $this->redirectCheckoutWithCpfError(
                    $plan,
                    $request,
                    'Este CPF ja esta vinculado a outro pagamento.'
                );
            }

            throw $exception;
        }

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

    /**
     * Normaliza CPF para manter somente digitos.
     */
    private function normalizeCpf(string $rawCpf): string
    {
        return preg_replace('/\D+/', '', $rawCpf) ?? '';
    }

    /**
     * Verifica se CPF ja foi usado, desconsiderando pontuacao.
     */
    private function cpfAlreadyUsed(string $cpf): bool
    {
        try {
            return Payment::query()
                ->whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`CPF/CNPJ`, '.', ''), '-', ''), '/', ''), ' ', ''), ',', '') = ?",
                    [$cpf]
                )
                ->exists();
        } catch (QueryException) {
            return Payment::where('CPF/CNPJ', $cpf)->exists();
        }
    }

    /**
     * Redireciona para checkout com mensagem amigavel para CPF invalido/duplicado.
     */
    private function redirectCheckoutWithCpfError(string $plan, Request $request, string $message)
    {
        return redirect()->route('payment.checkout', ['plan' => $plan])
            ->withInput($request->except('cpf'))
            ->withErrors([
                'cpf' => $message,
            ]);
    }

    /**
     * Detecta erro de violacao de unicidade no CPF/CNPJ do pagamento.
     */
    private function isDuplicateCpfException(QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        if (!in_array($sqlState, ['23000', '23505'], true)) {
            return false;
        }

        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'cpf/cnpj')
            || (str_contains($message, 'payments') && str_contains($message, 'unique'));
    }

    /**
     * Executa a rotina 'cancelTrial' no fluxo de negocio.
     */
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
