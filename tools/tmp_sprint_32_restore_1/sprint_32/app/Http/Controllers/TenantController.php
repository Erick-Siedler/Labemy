<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantController extends Controller
{
    public function index()
    {
        if (Auth::guard('subusers')->check()) {
            return redirect()->route('subuser-home');
        }

        if (!Auth::check()) {
            return view('main.index-plans');
        }

        $user = Auth::user();
        $tenant = Tenant::where('creator_id', $user->id)->first();
        $payment = Payment::where('user_id', $user->id)
            ->where('status', 'paid')
            ->first();

        if ($payment && !$tenant) {
            return redirect()->route('tenant-create', ['token' => $payment->payment_token]);
        }

        $trialActive = false;
        $trialEndsAt = null;

        if ($tenant && !$payment && !empty($tenant->trial_ends_at)) {
            $trialEndsAtCarbon = Carbon::parse($tenant->trial_ends_at)->endOfDay();
            $trialActive = now()->lt($trialEndsAtCarbon);
            if ($trialActive) {
                $trialEndsAt = $trialEndsAtCarbon->format('d/m/Y');
            }
        }

        return view('main.index-plans', [
            'currentPlan' => $user->plan,
            'trialUsed' => (bool) ($user->trial_used ?? false),
            'trialActive' => $trialActive,
            'trialEndsAt' => $trialEndsAt,
            'hasPaidAccess' => (bool) $payment,
        ]);
    }

    public function create(string $token)
    {
        if ($token === 'trial') {
            $payment = Payment::where('user_id', Auth::id())
                ->where('status', 'paid')
                ->latest('created_at')
                ->first();

            if ($payment) {
                return redirect()->route('tenant-create', ['token' => $payment->payment_token]);
            }

            if ((bool) (Auth::user()->trial_used ?? false)) {
                return redirect()->route('plans')
                    ->with('error', 'Você já utilizou seu período de teste.');
            }

            $trialPlan = (object) ['plan' => 'free'];

            return view('main.tenant.tenant-form', [
                'plan' => $trialPlan,
                'token' => 'trial',
            ]);
        }

        $payment = Payment::where('payment_token', $token)
            ->where('user_id', Auth::id())
            ->where('status', 'paid')
            ->firstOrFail();

        return view('main.tenant.tenant-form', [
            'plan' => $payment,
            'token' => $payment->payment_token,
        ]);
    }

    public function store(Request $request, string $token)
    {
        $data = $request->validate([
            'name' => 'required|min:3|max:400',
            'slug' => 'required|min:3|unique:tenants,slug',
            'type' => 'required',
            'settings' => 'required|array',
            'settings.max_storage_mb' => 'required|integer',
            'settings.max_labs' => 'required|integer',
            'settings.max_users' => 'required|integer',
            'settings.max_projects' => 'required|integer',
            'settings.max_groups' => 'required|integer',
        ]);

        $user = Auth::user();

        $userPayment = null;
        $planName = 'free';
        $trialEndsAt = Carbon::now()->addDays(7);
        $isTrialWithoutPayment = false;

        if ($token !== 'trial') {
            $userPayment = Payment::where('payment_token', $token)
                ->where('user_id', $user->id)
                ->where('status', 'paid')
                ->firstOrFail();

            $planName = $userPayment->plan;
            $trialEndsAt = null;
        }

        if ($token === 'trial' && !$userPayment) {
            $userPayment = Payment::where('user_id', $user->id)
                ->where('status', 'paid')
                ->latest('created_at')
                ->first();

            if ($userPayment) {
                $planName = $userPayment->plan;
                $trialEndsAt = null;
            } else {
                if ((bool) ($user->trial_used ?? false)) {
                    return redirect()->route('plans')
                        ->with('error', 'Você já utilizou seu período de teste.');
                }

                $isTrialWithoutPayment = true;
            }
        }

        Tenant::create([
            'creator_id' => $user->id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'type' => $data['type'],
            'status' => 'active',
            'plan' => $planName,
            'trial_ends_at' => $trialEndsAt,
            'settings' => $data['settings'],
            'storage_used_mb' => 0,
        ]);

        if ($isTrialWithoutPayment) {
            $user->trial_used = true;
            $user->save();
        }

        return redirect()->route('home')
            ->with('success', 'Instituição criada com sucesso!');
    }
}
