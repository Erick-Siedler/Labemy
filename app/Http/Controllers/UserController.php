<?php

namespace App\Http\Controllers;

use App\Models\SubUserInvite;
use App\Models\User;
use App\Services\ActivityService;
use App\Services\InviteAcceptanceService;
use App\Services\ProfileSettingsService;
use App\Services\TenantRelationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Executa a rotina 'indexLogin' no fluxo de negocio.
     */
    public function indexLogin(Request $request, TenantRelationService $relationService)
    {
        $inviteToken = (string) $request->query('invite', '');
        if ($inviteToken !== '') {
            session(['pending_invite_token' => $inviteToken]);
        }

        if (Auth::check()) {
            return $this->redirectAuthenticatedUser(Auth::user(), $relationService);
        }

        return view('main.login_regis.index-login', [
            'pendingInviteToken' => session('pending_invite_token'),
        ]);
    }

    /**
     * Executa a rotina 'indexProf' no fluxo de negocio.
     */
    public function indexProf()
    {
        return redirect()->route('settings')->withFragment('perfil');
    }

    /**
     * Executa a rotina 'Login' no fluxo de negocio.
     */
    public function Login(Request $request, TenantRelationService $relationService)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        $remember = $request->has('remember');

        if (!Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $remember)) {
            return back()
                ->withErrors(['email' => 'Credenciais invalidas.'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();
        $request->session()->put('last_activity_at', now()->getTimestamp());
        $request->session()->forget([
            'active_tenant_id',
            'active_relation_id',
            'active_relation_role',
            'active_lab_id',
            'active_group_id',
        ]);

        $user = Auth::user();
        if (!$user) {
            return back()
                ->withErrors(['email' => 'Credenciais invalidas.'])
                ->withInput($request->only('email'));
        }

        $inviteRedirect = $this->consumePendingInvite($request, $user);
        if ($inviteRedirect) {
            return $inviteRedirect;
        }

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'login',
                'auth',
                null,
                'Login realizado.'
            );
        }

        return $this->redirectAuthenticatedUser($user, $relationService);
    }

    /**
     * Executa a rotina 'indexRegis' no fluxo de negocio.
     */
    public function indexRegis(Request $request)
    {
        $inviteToken = (string) $request->query('invite', '');
        if ($inviteToken !== '') {
            session(['pending_invite_token' => $inviteToken]);
        }

        return view('main.login_regis.index-regis', [
            'pendingInviteToken' => session('pending_invite_token'),
        ]);
    }

    /**
     * Exibe formulario de recuperacao de senha.
     */
    public function indexForgotPassword()
    {
        return view('main.login_regis.forgot-password');
    }

    /**
     * Envia link de redefinicao de senha.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Se o e-mail estiver cadastrado, enviaremos um link para redefinir a senha.');
    }

    /**
     * Exibe formulario para redefinir senha via token.
     */
    public function indexResetPassword(Request $request, string $token)
    {
        return view('main.login_regis.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * Persiste nova senha informada no fluxo de reset.
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('success', 'Senha redefinida com sucesso. Faca login para continuar.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => $this->resolvePasswordResetStatusMessage($status),
            ]);
    }

    /**
     * Carrega os dados necessarios para edicao de registro.
     */
    public function edit(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $user = app(ProfileSettingsService::class)->updateProfile($user, $request, [
            'avatar_directory' => 'avatars',
        ]);
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

    /**
     * Executa a rotina 'Regis' no fluxo de negocio.
     */
    public function Regis(Request $request)
    {
        $data = $request->validate(
            [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'password' => 'required|min:8|confirmed',
                'terms_of_use' => 'accepted',
                'privacy_policy' => 'accepted',
                'phone' => 'nullable|string|max:30',
                'institution' => 'nullable|string|max:255',
                'bio' => 'nullable|string|max:1000',
            ],
            [
                'terms_of_use.accepted' => 'Voce deve aceitar os Termos de Uso para continuar.',
                'privacy_policy.accepted' => 'Voce deve aceitar a Politica de Privacidade para continuar.',
            ]
        );

        $defaultPreferences = ProfileSettingsService::defaultPreferences();
        $defaultNotifications = ProfileSettingsService::defaultNotifications();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'owner',
            'status' => 'active',
            'phone' => $data['phone'] ?? null,
            'institution' => $data['institution'] ?? null,
            'plan' => 'none',
            'trial_used' => false,
            'bio' => $data['bio'] ?? null,
            'preferences' => $defaultPreferences,
            'notifications' => $defaultNotifications,
            'terms_accepted_at' => now(),
            'privacy_policy_accepted_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('last_activity_at', now()->getTimestamp());

        $inviteRedirect = $this->consumePendingInvite($request, $user);
        if ($inviteRedirect) {
            return $inviteRedirect;
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Cadastro realizado com sucesso!');
    }

    /**
     * Executa a rotina 'logout' no fluxo de negocio.
     */
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

    /**
     * Consome convite pendente em sessao apos autenticacao.
     */
    private function consumePendingInvite(Request $request, ?User $user): ?RedirectResponse
    {
        if (!$user) {
            return null;
        }

        return app(InviteAcceptanceService::class)->consumePendingInvite(
            $request,
            $user,
            app(TenantRelationService::class)
        );

        $token = (string) ($request->input('invite_token') ?: session('pending_invite_token', ''));
        if ($token === '') {
            return null;
        }

        $relationService = app(TenantRelationService::class);
        $inviteData = null;

        try {
            DB::transaction(function () use ($token, $user, $relationService, &$inviteData): void {
                $invite = SubUserInvite::where('token_hash', hash('sha256', $token))
                    ->lockForUpdate()
                    ->first();

                if (!$invite || !empty($invite->used_at) || $invite->expires_at->isPast()) {
                    throw new \RuntimeException('invalid_invite');
                }

                if (!empty($invite->email) && strcasecmp((string) $invite->email, (string) $user->email) !== 0) {
                    throw new \RuntimeException('invite_email_mismatch');
                }

                $relationService->attachUserToTenant(
                    $user,
                    (int) $invite->tenant_id,
                    !empty($invite->lab_id) ? (int) $invite->lab_id : null,
                    !empty($invite->group_id) ? (int) $invite->group_id : null,
                    (string) ($invite->role ?? 'student')
                );

                $invite->update([
                    'used_at' => now(),
                    'accepted_by_user_id' => (int) $user->id,
                ]);

                $inviteData = [
                    'tenant_id' => (int) $invite->tenant_id,
                ];
            });
        } catch (\RuntimeException $exception) {
            session()->forget('pending_invite_token');

            if ($exception->getMessage() === 'invite_email_mismatch') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'Esta conta nao corresponde ao e-mail convidado.');
            }

            return redirect()->route('login')
                ->with('error', 'Convite invalido ou expirado.');
        }

        if (empty($inviteData['tenant_id'])) {
            session()->forget('pending_invite_token');

            return redirect()->route('login')
                ->with('error', 'Convite invalido ou expirado.');
        }

        $relationService->activateTenant($user, (int) $inviteData['tenant_id']);
        session()->forget('pending_invite_token');

        return redirect()->route($relationService->resolveDashboardRoute($user, (int) $inviteData['tenant_id']))
            ->with('success', 'Convite aceito com sucesso.');
    }

    /**
     * Resolve rota inicial apos autenticacao.
     */
    private function redirectAuthenticatedUser(?User $user, TenantRelationService $relationService): RedirectResponse
    {
        if (!$user) {
            return redirect()->route('login');
        }

        $tenants = $relationService->listAccessibleTenants($user);
        if ($tenants->isEmpty()) {
            return redirect()->route('plans');
        }

        if ($relationService->hasOwnerAccess($user)) {
            return redirect()->route('tenant.select.index');
        }

        return redirect()->route('plans')
            ->with('status', 'Sua conta participa de tenants como membro. Escolha um plano ou acesse seus tenants.');
    }

    /**
     * Traduz status de reset de senha para mensagens amigaveis.
     */
    private function resolvePasswordResetStatusMessage(string $status): string
    {
        return match ($status) {
            Password::INVALID_TOKEN => 'Token de redefinicao invalido ou expirado. Solicite um novo link.',
            Password::INVALID_USER => 'Nao foi encontrada uma conta para o e-mail informado.',
            default => 'Nao foi possivel redefinir a senha. Tente novamente em instantes.',
        };
    }
}
