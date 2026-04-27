<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\SubUserInvite;
use Illuminate\Support\Facades\Hash;
use App\Models\Tenant;
use App\Models\Lab;
use App\Models\Group;
use App\Models\User;
use App\Models\UserRelation;
use Illuminate\Support\Facades\Validator;
use App\Services\TenantRelationService;
use App\Services\ActivityService;
use App\Services\InviteAcceptanceService;
use App\Services\ProfileSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;

class SubUserController extends Controller
{
    /**
     * Executa a rotina 'sendInvite' no fluxo de negocio.
     */
    public function sendInvite(Request $request)
    {
        $subUser = Auth::user();
        if (!$subUser) {
            abort(401);
        }

        $relationService = app(TenantRelationService::class);
        $role = $relationService->resolveActiveRole($subUser);
        if (!in_array($role, ['owner', 'teacher'], true)) {
            abort(403);
        }

        $tenantId = (int) session('active_tenant_id', 0);
        if ($tenantId <= 0) {
            $tenantId = (int) ($subUser->tenant_id ?? 0);
        }
        if ($tenantId <= 0 && $role === 'owner') {
            $tenantId = (int) (Tenant::where('creator_id', $subUser->id)->value('id') ?? 0);
        }

        if ($tenantId <= 0 || !$relationService->userHasAccessToTenant($subUser, $tenantId)) {
            abort(403);
        }

        $tenant = Tenant::where('id', $tenantId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email',
            'lab_id' => 'required|exists:labs,id',
            'group_id' => 'required|exists:groups,id',
            'role' => 'nullable|in:teacher,assistant,student',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados invalidos para geracao de convite.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return back()->withErrors($validator, 'invite')->withInput();
        }

        $data = $validator->validated();
        $inviteEmail = trim((string) ($data['email'] ?? ''));

        $labQuery = Lab::where('id', $data['lab_id'])
            ->where('tenant_id', $tenant->id);

        if ($role === 'teacher') {
            $labQuery->where('creator_subuser_id', $subUser->id);
        }

        $lab = $labQuery->firstOrFail();

        $group = Group::where('id', $data['group_id'])
            ->where('lab_id', $lab->id)
            ->firstOrFail();

        $existingUserId = $inviteEmail !== ''
            ? User::where('email', $inviteEmail)->value('id')
            : null;

        if (!empty($existingUserId)) {
            $alreadyRelated = UserRelation::where('user_id', (int) $existingUserId)
                ->where('tenant_id', (int) $tenant->id)
                ->where('lab_id', (int) $lab->id)
                ->where('group_id', (int) $group->id)
                ->where('status', 'active')
                ->exists();

            if ($alreadyRelated) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Esse usuario ja esta vinculado a este grupo.',
                        'errors' => [
                            'email' => ['Esse usuario ja esta vinculado a este grupo.'],
                        ],
                    ], 422);
                }

                return back()->withErrors([
                    'email' => 'Esse usuario ja esta vinculado a este grupo.',
                ], 'invite')->withInput();
            }
        }

        $token = Str::random(24);
        $invite = SubUserInvite::create([
            'tenant_id' => $tenant->id,
            'lab_id' => $lab->id,
            'group_id' => $group->id,
            'email' => $inviteEmail,
            'role' => $data['role'] ?? 'student',
            'invited_by_user_id' => Auth::guard('web')->id(),
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(24),
        ]);

        $inviteTarget = $inviteEmail !== '' ? (' para ' . $inviteEmail) : '';
        $inviteMessage = 'Link de convite gerado' . $inviteTarget . ' no laboratorio ' . $lab->name . ' / grupo ' . $group->name . '.';
        ActivityService::notifyUser($tenant->creator_id, $inviteMessage, 'alert');
        if (!empty($lab->creator_subuser_id)) {
            ActivityService::notifyUser((int) $lab->creator_subuser_id, $inviteMessage, 'alert');
        }

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'invite_send',
                'subuser_invite',
                (int) $invite->id,
                'Link de convite gerado para o grupo ' . $group->name . '.'
            );
        }

        $groupSlug = Str::slug((string) $group->name) ?: 'grupo';
        $inviteUrl = route('invite-link-short', [
            'groupSlug' => $groupSlug,
            'token' => $token,
        ]);
        $inviteLabel = 'Convite para o grupo ' . $group->name;
        $inviteExpiresAt = $invite->expires_at?->format('d/m/Y H:i') ?? '';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Link de convite gerado com sucesso.',
                'invite_id' => (int) $invite->id,
                'invite_url' => $inviteUrl,
                'invite_label' => $inviteLabel,
                'invite_expires_at' => $inviteExpiresAt,
                'invite_group_name' => (string) $group->name,
                'invite_lab_name' => (string) $lab->name,
            ], 201);
        }

        return back()
            ->with('success', 'Link de convite gerado com sucesso.')
            ->with('invite_link', $inviteUrl)
            ->with('invite_label', $inviteLabel)
            ->with('invite_expires_at', $inviteExpiresAt)
            ->with('invite_group_name', (string) $group->name)
            ->with('invite_lab_name', (string) $lab->name);
    }

    /**
     * Revoga todos os links de convite ativos e nao expirados do grupo selecionado.
     */
    public function revokeActiveInvites(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $subUser = Auth::user();
        if (!$subUser) {
            abort(401);
        }

        $relationService = app(TenantRelationService::class);
        $role = $relationService->resolveActiveRole($subUser);
        if (!in_array($role, ['owner', 'teacher'], true)) {
            abort(403);
        }

        $tenantId = (int) session('active_tenant_id', 0);
        if ($tenantId <= 0) {
            $tenantId = (int) ($subUser->tenant_id ?? 0);
        }
        if ($tenantId <= 0 && $role === 'owner') {
            $tenantId = (int) (Tenant::where('creator_id', $subUser->id)->value('id') ?? 0);
        }

        if ($tenantId <= 0 || !$relationService->userHasAccessToTenant($subUser, $tenantId)) {
            abort(403);
        }

        $tenant = Tenant::where('id', $tenantId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'lab_id' => 'required|exists:labs,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados invalidos para revogar convites ativos.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return back()->withErrors($validator, 'invite')->withInput();
        }

        $data = $validator->validated();

        $labQuery = Lab::where('id', (int) $data['lab_id'])
            ->where('tenant_id', $tenant->id);

        if ($role === 'teacher') {
            $labQuery->where('creator_subuser_id', $subUser->id);
        }

        $lab = $labQuery->firstOrFail();

        $group = Group::where('id', (int) $data['group_id'])
            ->where('lab_id', $lab->id)
            ->firstOrFail();

        $activeInvites = SubUserInvite::query()
            ->where('tenant_id', (int) $tenant->id)
            ->where('lab_id', (int) $lab->id)
            ->where('group_id', (int) $group->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now());

        $revokedCount = (clone $activeInvites)->count();

        if ($revokedCount > 0) {
            $activeInvites->update([
                'expires_at' => now()->subSecond(),
            ]);
        }

        $message = $revokedCount > 0
            ? 'Links ativos revogados com sucesso para o grupo ' . $group->name . '.'
            : 'Nao existem links ativos para revogar neste grupo.';

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id']) && $revokedCount > 0) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'invite_revoke_all',
                'subuser_invite',
                (int) $group->id,
                'Revogacao em massa de convites ativos do grupo ' . $group->name . ' (' . $revokedCount . ').'
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'revoked_count' => (int) $revokedCount,
                'group_id' => (int) $group->id,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Executa a rotina 'showRegisterWithGroup' no fluxo de negocio.
     */
    public function showRegisterWithGroup(string $groupSlug, string $token)
    {
        return $this->showRegister($token);
    }

    /**
     * Executa a rotina 'showRegister' no fluxo de negocio.
     */
    public function showRegister($token)
    {
        $invite = $this->findInvite($token);

        if (!$invite) {
            return redirect()->route('login')
                ->with('error', 'Convite invalido ou expirado.');
        }

        $webUser = Auth::guard('web')->user();
        if ($webUser) {
            $relationService = app(TenantRelationService::class);
            try {
                $this->acceptInviteForUser($invite, $webUser, $relationService);

                return redirect()->route($relationService->resolveDashboardRoute($webUser, (int) $invite->tenant_id))
                    ->with('success', 'Convite aceito com sucesso.');
            } catch (\RuntimeException $exception) {
                return redirect()->route('login')
                    ->with('error', $exception->getMessage());
            }
        }

        session([
            'pending_invite_token' => $token,
        ]);

        return redirect()->route('login', ['invite' => $token])
            ->with('status', 'Convite validado. Faca login ou crie sua conta para concluir.');
    }

    /**
     * Valida os dados recebidos e persiste um novo registro.
     */
    public function store(Request $request, $token)
    {
        $invite = $this->findInvite($token);

        if (!$invite) {
            return redirect()->route('login')
                ->with('error', 'Convite invalido ou expirado.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        if (!empty($invite->email) && strcasecmp($data['email'], (string) $invite->email) !== 0) {
            return back()->withErrors([
                'email' => 'O e-mail informado nao confere com o convite.',
            ])->withInput();
        }

        if (User::where('email', $data['email'])->exists()) {
            return back()->withErrors([
                'email' => 'Ja existe uma conta com este e-mail. Faca login para aceitar o convite.',
            ])->withInput();
        }

        $defaultPreferences = ProfileSettingsService::defaultPreferences();
        $defaultNotifications = ProfileSettingsService::defaultNotifications();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'owner',
            'status' => 'active',
            'plan' => 'free',
            'trial_used' => false,
            'preferences' => $defaultPreferences,
            'notifications' => $defaultNotifications,
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $relationService = app(TenantRelationService::class);
        $this->acceptInviteForUser($invite, $user, $relationService);

        $acceptedMessage = 'Convite aceito por ' . $invite->email . ' no laboratorio ' . ($invite->lab?->name ?? 'N/A') . ' / grupo ' . ($invite->group?->name ?? 'N/A') . '.';
        ActivityService::notifyUser($invite->tenant->creator_id, $acceptedMessage, 'alert');
        if (!empty($invite->lab?->creator_subuser_id)) {
            ActivityService::notifyUser((int) $invite->lab->creator_subuser_id, $acceptedMessage, 'alert');
        }

        ActivityService::log(
            (int) $invite->tenant_id,
            (int) $user->id,
            (string) ($invite->role ?? 'student'),
            'invite_accept',
            'subuser_invite',
            (int) $invite->id,
            'Convite aceito e cadastro concluido para ' . $invite->email . '.'
        );

        return redirect()->route($relationService->resolveDashboardRoute($user, (int) $invite->tenant_id))
            ->with('success', 'Cadastro concluido e convite aceito com sucesso.');
    }

    /**
     * Executa a rotina 'updateProfile' no fluxo de negocio.
     */
    public function updateProfile(Request $request)
    {
        $student = Auth::user();

        if (!$student) {
            abort(403);
        }

        $student = app(ProfileSettingsService::class)->updateProfile($student, $request, [
            'avatar_directory' => 'subuser-avatars',
        ]);
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'profile_update',
                'user',
                (int) $student->id,
                'Perfil atualizado.'
            );
        }

        return back()->with('status', 'Perfil atualizado.');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'institution' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($data['email'] !== $student->email) {
            $exists = User::where('email', $data['email'])
                ->where('id', '!=', $student->id)
                ->exists();

            if ($exists) {
                return back()->withErrors([
                    'email' => 'Esse e-mail ja esta cadastrado para outro usuario.',
                ])->withInput();
            }
        }

        $student->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'institution' => $data['institution'] ?? null,
            'bio' => $data['bio'] ?? null,
        ]);

        if ($request->hasFile('avatar')) {
            if ($student->profile_photo_path) {
                Storage::disk('public')->delete($student->profile_photo_path);
            }

            $student->profile_photo_path = $request->file('avatar')->store('subuser-avatars', 'public');
        }

        $student->save();
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'profile_update',
                'user',
                (int) $student->id,
                'Perfil atualizado.'
            );
        }

        return back()->with('status', 'Perfil atualizado.');
    }

    /**
     * Executa a rotina 'updateSettings' no fluxo de negocio.
     */
    public function updateSettings(Request $request)
    {
        $student = Auth::user();

        if (!$student) {
            abort(403);
        }
        $result = app(ProfileSettingsService::class)->updateSettings($student, $request, [
            'password_min_length' => 6,
        ]);
        if (!empty($result['error'])) {
            $error = $result['error'];
            return back()->withErrors([
                (string) ($error['field'] ?? 'settings') => (string) ($error['message'] ?? 'Nao foi possivel atualizar configuracoes.'),
            ]);
        }

        $changedPassword = (bool) ($result['changedPassword'] ?? false);
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                $changedPassword ? 'password_update' : 'settings_update',
                'user',
                (int) $student->id,
                $changedPassword ? 'Senha atualizada.' : 'Configuracoes atualizadas.'
            );
        }

        return back()->with('status', 'Configuracoes atualizadas.');
        $changedPassword = false;

        if ($request->filled('current_password') || $request->filled('new_password')) {
            $data = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:6|confirmed',
            ]);

            if (!Hash::check($data['current_password'], $student->password)) {
                return back()->withErrors([
                    'current_password' => 'Senha atual incorreta.',
                ]);
            }

            $student->password = Hash::make($data['new_password']);
            $changedPassword = true;
        }

        if ($request->has('settings')) {
            $request->validate([
                'settings.theme' => 'nullable|in:light,dark,auto',
                'settings.language' => 'nullable|in:pt-BR,en,es',
                'settings.timezone' => 'nullable|string|max:64',
                'settings.compact' => 'nullable|boolean',
            ]);

            $preferences = $student->preferences ?? [];
            $preferences['theme'] = $request->input('settings.theme', $preferences['theme'] ?? 'light');
            $preferences['language'] = $request->input('settings.language', $preferences['language'] ?? 'pt-BR');
            $preferences['timezone'] = $request->input('settings.timezone', $preferences['timezone'] ?? 'America/Sao_Paulo');
            $preferences['compact'] = $request->boolean('settings.compact');
            $student->preferences = $preferences;
        }

        if ($request->has('notifications')) {
            $notifications = $student->notifications ?? [];
            $notifications['email'] = $request->boolean('notifications.email');
            $notifications['calendar'] = $request->boolean('notifications.calendar');
            $notifications['projects'] = $request->boolean('notifications.projects');
            $student->notifications = $notifications;
        }

        $student->save();
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                $changedPassword ? 'password_update' : 'settings_update',
                'user',
                (int) $student->id,
                $changedPassword ? 'Senha atualizada.' : 'Configuracoes atualizadas.'
            );
        }

        return back()->with('status', 'Configuracoes atualizadas.');
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
     * Executa a rotina 'findInvite' no fluxo de negocio.
     */
    private function findInvite(string $token): ?SubUserInvite
    {
        $invite = SubUserInvite::where('token_hash', hash('sha256', $token))->first();

        if (!$invite) {
            return null;
        }

        if ($invite->used_at || $invite->expires_at->isPast()) {
            return null;
        }

        return $invite;
    }

    /**
     * Vincula usuario autenticado ao tenant do convite.
     */
    public function acceptInviteForUser(SubUserInvite $invite, User $user, TenantRelationService $relationService): void
    {
        app(InviteAcceptanceService::class)->acceptInviteForUser($invite, $user, $relationService);
        return;

        DB::transaction(function () use ($invite, $user, $relationService): void {
            $freshInvite = SubUserInvite::where('id', (int) $invite->id)
                ->lockForUpdate()
                ->first();

            if (!$freshInvite) {
                throw new \RuntimeException('Convite invalido.');
            }

            if (!empty($freshInvite->used_at)) {
                throw new \RuntimeException('Este convite ja foi utilizado.');
            }

            if ($freshInvite->expires_at->isPast()) {
                throw new \RuntimeException('Este convite expirou.');
            }

            if (!empty($freshInvite->email) && strcasecmp((string) $freshInvite->email, (string) $user->email) !== 0) {
                throw new \RuntimeException('Esta conta nao corresponde ao e-mail convidado.');
            }

            $relationService->attachUserToTenant(
                $user,
                (int) $freshInvite->tenant_id,
                !empty($freshInvite->lab_id) ? (int) $freshInvite->lab_id : null,
                !empty($freshInvite->group_id) ? (int) $freshInvite->group_id : null,
                (string) ($freshInvite->role ?? 'student')
            );

            $freshInvite->update([
                'used_at' => now(),
                'accepted_by_user_id' => (int) $user->id,
            ]);

            $relationService->activateTenant($user, (int) $freshInvite->tenant_id);
        });
    }

}


