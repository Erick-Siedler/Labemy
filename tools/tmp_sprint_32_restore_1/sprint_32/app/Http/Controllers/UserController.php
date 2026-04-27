<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Payment;
use App\Models\Tenant;
use Carbon\Carbon;
use App\Services\ActivityService;

class UserController extends Controller
{
    public function indexLogin()
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }
        if (Auth::guard('subusers')->check()) {
            return redirect()->route('subuser-home');
        }
        
        return view('main.login_regis.index-login');
    }

    public function indexProf()
    {
        return redirect()->route('settings')->withFragment('perfil');
    }

    public function Login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
            'login_type' => 'required|in:owner,teacher,student,assistant',
        ]);

        $loginType = $credentials['login_type'];
        if (in_array($loginType, ['asssitant', 'assitant'], true)) {
            $loginType = 'assistant';
        }

        $remember = $request->has('remember');

        if ($loginType !== 'owner') {
            $attemptRoles = [$loginType];
            if ($loginType === 'assistant') {
                $attemptRoles = array_merge($attemptRoles, ['asssitant', 'assitant']);
            }

            $authenticated = false;
            foreach ($attemptRoles as $role) {
                if (Auth::guard('subusers')->attempt([
                    'email' => $credentials['email'],
                    'password' => $credentials['password'],
                    'role' => $role,
                ], false)) {
                    $authenticated = true;
                    break;
                }
            }

            if ($authenticated) {
                $request->session()->regenerate();
                $subUser = Auth::guard('subusers')->user();
                if ($subUser) {
                    $tenantId = ActivityService::resolveTenantIdFromSubUser($subUser);
                    if (!empty($tenantId)) {
                        ActivityService::log(
                            (int) $tenantId,
                            (int) $subUser->id,
                            (string) $subUser->role,
                            'login',
                            'auth',
                            null,
                            'Login realizado.'
                        );
                    }
                }
                return redirect()->intended(route('plans'));
            }
        } else {
            if (Auth::attempt([
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ], $remember)) {
                $request->session()->regenerate();
                $tenant = Tenant::where('creator_id', Auth::id())->first();
                if ($tenant) {
                    ActivityService::log(
                        (int) $tenant->id,
                        (int) Auth::id(),
                        'owner',
                        'login',
                        'auth',
                        null,
                        'Login realizado.'
                    );
                }

                $hasPaidAccess = Payment::where('user_id', Auth::id())
                    ->where('status', 'paid')
                    ->exists();

                $trialActive = false;
                if ($tenant && !empty($tenant->trial_ends_at)) {
                    $trialActive = Carbon::now()->lt(
                        Carbon::parse($tenant->trial_ends_at)->endOfDay()
                    );
                }

                if ($hasPaidAccess || $trialActive) {
                    return redirect()->intended(route('home'));
                }

                if (!$tenant) {
                    return redirect()->route('plans');
                }

                return redirect()->route('plans')
                    ->with('error', 'Seu período de teste expirou. Realize um pagamento para continuar.');
            }
        }

        return back()
            ->withErrors(['email' => 'Credenciais inválidas.'])
            ->withInput($request->only('email', 'login_type'));
    }

    public function indexRegis()
    {
        return view('main.login_regis.index-regis');
    }

    public function edit(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => 'nullable|string|max:30',
            'institution' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'institution' => $data['institution'] ?? null,
            'bio' => $data['bio'] ?? null,
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $user->profile_photo_path = $request->file('avatar')->store('avatars', 'public');
        }

        $user->save();
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'profile_update',
                'user',
                (int) $user->id,
                'Perfil atualizado.'
            );
        }

        return back()->with('status', 'Perfil atualizado.');
    }

    public function Regis(Request $request)
    {

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'terms' => 'accepted',
            'phone' => 'nullable|string|max:30',
            'institution' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
        ]);

        $defaultPreferences = [
            'theme' => 'light',
            'language' => 'pt-BR',
            'timezone' => 'America/Sao_Paulo',
            'compact' => true,
        ];
        $defaultNotifications = [
            'email' => true,
            'calendar' => true,
            'projects' => false,
        ];

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'owner',
            'status' => 'active',
            'phone' => $data['phone'] ?? null,
            'institution' => $data['institution'] ?? null,
            'plan' => 'free',
            'trial_used' => false,
            'bio' => $data['bio'] ?? null,
            'preferences' => $defaultPreferences,
            'notifications' => $defaultNotifications,
        ]);
        
        return redirect()->route('login');
    }

    public function logout(Request $request)
    {
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'logout',
                'auth',
                null,
                'Logout realizado.'
            );
        }

        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('login');
    }
}
