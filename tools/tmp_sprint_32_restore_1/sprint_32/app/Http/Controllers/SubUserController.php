<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\SubUsers;
use App\Models\SubUserInvite;
use Illuminate\Support\Facades\Hash;
use App\Models\Tenant;
use App\Models\Lab;
use App\Models\Group;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityService;

class SubUserController extends Controller
{
    public function sendInvite(Request $request)
    {
        $subUser = Auth::guard('subusers')->user();
        if ($subUser) {
            if ($subUser->role !== 'teacher') {
                abort(403);
            }
            $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
            $tenant = Tenant::where('id', $tenantId)->firstOrFail();
        } else {
            $user = Auth::user();
            $tenant = Tenant::where('creator_id', $user->id)->firstOrFail();
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'lab_id' => 'required|exists:labs,id',
            'group_id' => 'required|exists:groups,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'invite')->withInput();
        }

        $data = $validator->validated();

        $labQuery = Lab::where('id', $data['lab_id'])
            ->where('tenant_id', $tenant->id);

        if ($subUser) {
            $labQuery->where('creator_subuser_id', $subUser->id);
        }

        $lab = $labQuery->firstOrFail();

        $group = Group::where('id', $data['group_id'])
            ->where('lab_id', $lab->id)
            ->firstOrFail();

        if (SubUsers::where('tenant_id', $tenant->id)->where('email', $data['email'])->exists()) {
            return back()->withErrors([
                'email' => 'Esse e-mail já está cadastrado como aluno.',
            ], 'invite')->withInput();
        }

        $token = Str::random(64);
        $invite = SubUserInvite::create([
            'tenant_id' => $tenant->id,
            'lab_id' => $lab->id,
            'group_id' => $group->id,
            'email' => $data['email'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(24),
        ]);

        $inviteMessage = 'Convite enviado para ' . $data['email'] . ' no laboratório ' . $lab->name . ' / grupo ' . $group->name . '.';
        ActivityService::notifyUser($tenant->creator_id, $inviteMessage, 'alert');
        if (!empty($lab->creator_subuser_id)) {
            ActivityService::notifySubUser((int) $lab->creator_subuser_id, $inviteMessage, 'alert');
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
                'Convite enviado para ' . $data['email'] . '.'
            );
        }

        $registerUrl = route('subuser-register', ['token' => $token]);

        Mail::send('emails.subuser-invite', [
            'tenant' => $tenant,
            'lab' => $lab,
            'group' => $group,
            'invite' => $invite,
            'registerUrl' => $registerUrl,
        ], function ($message) use ($data, $tenant) {
            $message->to($data['email'])
                ->subject('Convite para entrar na instituição ' . $tenant->name);
        });

        return back()->with('success', 'Convite enviado com sucesso.');
    }

    public function showRegister($token)
    {
        $invite = $this->findInvite($token);

        if (!$invite) {
            return view('main.subuser.register', [
                'invalid' => true,
                'message' => 'Convite inválido ou expirado.',
            ]);
        }

        return view('main.subuser.register', [
            'invalid' => false,
            'invite' => $invite,
            'token' => $token,
        ]);
    }

    public function store(Request $request, $token)
    {
        $invite = $this->findInvite($token);

        if (!$invite) {
            return view('main.subuser.register', [
                'invalid' => true,
                'message' => 'Convite inválido ou expirado.',
            ]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
            'phone' => 'nullable|string|max:30',
            'institution' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
        ]);

        if ($data['email'] !== $invite->email) {
            return back()->withErrors([
                'email' => 'O e-mail informado não confere com o convite.',
            ])->withInput();
        }

        if (SubUsers::where('tenant_id', $invite->tenant_id)->where('email', $invite->email)->exists()) {
            return back()->withErrors([
                'email' => 'Esse e-mail já está cadastrado como aluno.',
            ]);
        }

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

        $newSubUser = SubUsers::create([
            'tenant_id' => $invite->tenant_id,
            'lab_id' => $invite->lab_id,
            'group_id' => $invite->group_id,
            'name' => $data['name'],
            'email' => $invite->email,
            'password' => Hash::make($data['password']),
            'role' => 'student',
            'phone' => $data['phone'] ?? null,
            'institution' => $data['institution'] ?? null,
            'bio' => $data['bio'] ?? null,
            'preferences' => $defaultPreferences,
            'notifications' => $defaultNotifications,
        ]);

        $acceptedMessage = 'Convite aceito por ' . $invite->email . ' e aluno criado no laboratório ' . ($invite->lab?->name ?? 'N/A') . ' / grupo ' . ($invite->group?->name ?? 'N/A') . '.';
        ActivityService::notifyUser($invite->tenant->creator_id, $acceptedMessage, 'alert');
        if (!empty($invite->lab?->creator_subuser_id)) {
            ActivityService::notifySubUser((int) $invite->lab->creator_subuser_id, $acceptedMessage, 'alert');
        }

        ActivityService::log(
            (int) $invite->tenant_id,
            (int) $newSubUser->id,
            'student',
            'invite_accept',
            'subuser_invite',
            (int) $invite->id,
            'Convite aceito e cadastro concluído para ' . $invite->email . '.'
        );

        $invite->update([
            'used_at' => now(),
        ]);

        return view('main.subuser.register', [
            'success' => true,
            'message' => 'Cadastro concluído. Agora você já pode entrar como aluno.',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $student = Auth::guard('subusers')->user();

        if (!$student) {
            abort(403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
            'institution' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($data['email'] !== $student->email) {
            $exists = SubUsers::where('tenant_id', $student->tenant_id)
                ->where('email', $data['email'])
                ->where('id', '!=', $student->id)
                ->exists();

            if ($exists) {
                return back()->withErrors([
                    'email' => 'Esse e-mail já está cadastrado para outro usuário.',
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
                'subuser',
                (int) $student->id,
                'Perfil atualizado.'
            );
        }

        return back()->with('status', 'Perfil atualizado.');
    }

    public function updateSettings(Request $request)
    {
        $student = Auth::guard('subusers')->user();

        if (!$student) {
            abort(403);
        }
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
                'subuser',
                (int) $student->id,
                $changedPassword ? 'Senha atualizada.' : 'Configurações atualizadas.'
            );
        }

        return back()->with('status', 'Configuracoes atualizadas.');
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
        Auth::guard('subusers')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

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
}
