<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\HomeOwnerDataService;
use App\Services\ActivityService;
use App\Services\ProfileSettingsService;
use App\Services\SubHomeDataService;
use App\Services\UserUiPreferencesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SettingController extends Controller
{
    /**
     * Lista e prepara os dados exibidos na tela.
     */
    public function index(HomeOwnerDataService $homeOwnerData)
    {   
        $user = Auth::user();
        $isSoloUser = (string) ($user->plan ?? '') === 'solo';
        $data = $isSoloUser
            ? $homeOwnerData->buildSolo($user)
            : $homeOwnerData->build($user);
        $data['pageTitle'] = 'Configurações';
        $data['pageBreadcrumbHome'] = 'Início';
        $data['pageBreadcrumbCurrent'] = 'Conta';

        $theme = $this->getTheme($data['userPreferences'] ?? null);

        $payment = Payment::where('user_id', $user->id)
            ->where('status', 'paid')
            ->first();

        $trialActive = false;
        $trialEndsAt = null;
        $tenant = $data['tenant'] ?? null;

        if (!$payment) {
            if (($user->plan ?? null) === 'solo') {
                $trialActive = true;
            } elseif ($tenant && !empty($tenant->trial_ends_at)) {
                $trialEndsAtCarbon = Carbon::parse($tenant->trial_ends_at)->endOfDay();
                $trialActive = now()->lt($trialEndsAtCarbon);
                if ($trialActive) {
                    $trialEndsAt = $trialEndsAtCarbon->format('d/m/Y');
                }
            }
        }

        return view('main.users.index-user', $data, [
            'theme' => $theme,
            'currentPlan' => $user->plan,
            'trialUsed' => (bool) ($user->trial_used ?? false),
            'trialActive' => $trialActive,
            'trialEndsAt' => $trialEndsAt,
            'hasPaidAccess' => (bool) $payment,
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
        $result = app(ProfileSettingsService::class)->updateSettings($user, $request, [
            'password_min_length' => 8,
            'allow_logout_other_sessions' => true,
            'allow_tenant_limits' => true,
        ]);
        if (!empty($result['error'])) {
            $error = $result['error'];
            return back()->withErrors([
                (string) ($error['field'] ?? 'settings') => (string) ($error['message'] ?? 'Nao foi possivel atualizar configuracoes.'),
            ]);
        }

        $changedPassword = (bool) ($result['changedPassword'] ?? false);
        $tenantUpdated = (bool) ($result['tenantUpdated'] ?? false);
        $tenant = $result['tenant'] ?? null;

        if ($tenantUpdated && $tenant) {
            $actor = ActivityService::resolveActor();
            if (!empty($actor['tenant_id'])) {
                ActivityService::log(
                    (int) $actor['tenant_id'],
                    (int) $actor['actor_id'],
                    (string) $actor['actor_role'],
                    'tenant_limits_update',
                    'tenant',
                    (int) $tenant->id,
                    'Limites do tenant atualizados.'
                );
            }
        }

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                $changedPassword ? 'password_update' : 'settings_update',
                'user',
                (int) $user->id,
                $changedPassword ? 'Senha atualizada.' : 'Configuracoes atualizadas.'
            );
        }

        return back()->with('status', 'Configuracoes atualizadas.');
        $changedPassword = false;

        if ($request->filled('current_password') || $request->filled('new_password')) {
            $data = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            if (!Hash::check($data['current_password'], $user->password)) {
                return back()->withErrors([
                    'current_password' => 'Senha atual incorreta.',
                ]);
            }

            $user->password = Hash::make($data['new_password']);
            $changedPassword = true;

            if ($request->boolean('logout_other_sessions')) {
                Auth::logoutOtherDevices($data['current_password']);
            }
        }

        if ($request->has('settings')) {
            $request->validate([
                'settings.theme' => 'nullable|in:light,dark,auto',
                'settings.language' => 'nullable|in:pt-BR,en,es',
                'settings.timezone' => 'nullable|string|max:64',
                'settings.compact' => 'nullable|boolean',
            ]);

            $preferences = $user->preferences ?? [];
            $preferences['theme'] = $request->input('settings.theme', $preferences['theme'] ?? 'light');
            $preferences['language'] = $request->input('settings.language', $preferences['language'] ?? 'pt-BR');
            $preferences['timezone'] = $request->input('settings.timezone', $preferences['timezone'] ?? 'America/Sao_Paulo');
            $preferences['compact'] = $request->boolean('settings.compact');
            $user->preferences = $preferences;
        }

        if ($request->has('notifications')) {
            $notifications = $user->notifications ?? [];
            $notifications['email'] = $request->boolean('notifications.email');
            $notifications['calendar'] = $request->boolean('notifications.calendar');
            $notifications['projects'] = $request->boolean('notifications.projects');
            $user->notifications = $notifications;
        }

        if ($request->has('tenant_limits')) {
            if ($user->role !== 'owner' || ($user->plan ?? null) === 'solo') {
                abort(403);
            }

            $data = $request->validate([
                'tenant_limits.max_labs' => 'nullable|integer|min:0',
                'tenant_limits.max_groups' => 'nullable|integer|min:0',
                'tenant_limits.max_projects' => 'nullable|integer|min:0',
                'tenant_limits.max_users' => 'nullable|integer|min:0',
                'tenant_limits.max_storage_mb' => 'nullable|integer|min:0',
            ]);

            $tenant = Tenant::where('creator_id', $user->id)->first();
            if (!$tenant) {
                return back()->withErrors(['tenant' => 'Tenant nao encontrado.']);
            }

            $settings = $tenant->settings;
            if (is_string($settings)) {
                $decoded = json_decode($settings, true);
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
                $settings = $decoded;
            }
            if (!is_array($settings)) {
                $settings = [];
            }

            $limits = $data['tenant_limits'] ?? [];

            $setSetting = function (string $key, $value) use (&$settings) {
                if ($value === null || $value === '') {
                    unset($settings[$key]);
                    return null;
                }
                $settings[$key] = (int) $value;
                return (int) $value;
            };

            $setSetting('max_labs', $limits['max_labs'] ?? null);
            $setSetting('max_groups', $limits['max_groups'] ?? null);
            $setSetting('max_projects', $limits['max_projects'] ?? null);
            $setSetting('max_users', $limits['max_users'] ?? null);
            $setSetting('max_storage_mb', $limits['max_storage_mb'] ?? null);

            $tenant->settings = $settings;
            $tenant->save();

            $actor = ActivityService::resolveActor();
            if (!empty($actor['tenant_id'])) {
                ActivityService::log(
                    (int) $actor['tenant_id'],
                    (int) $actor['actor_id'],
                    (string) $actor['actor_role'],
                    'tenant_limits_update',
                    'tenant',
                    (int) $tenant->id,
                    'Limites do tenant atualizados.'
                );
            }
        }

        $user->save();
        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                $changedPassword ? 'password_update' : 'settings_update',
                'user',
                (int) $user->id,
                $changedPassword ? 'Senha atualizada.' : 'Configurações atualizadas.'
            );
        }

        return back()->with('status', 'Configurações atualizadas.');
    }

    /**
     * Executa a rotina 'indexSub' no fluxo de negocio.
     */
    function indexSub(SubHomeDataService $svc){
        $student = Auth::user();

        $stud_role = $student->role;

        if($stud_role === 'student'){
            $data = $svc->buildStudent($student);
        }elseif(in_array($stud_role, ['assistant', 'assitant'], true)){
            $data = $svc->buildAssistant($student);
        }else{
            $data = $svc->buildTeacher($student);
        }

        $theme = $this->getTheme($data['userPreferences']);

        return view('main.users.index-user', $data, [
            'theme' => $theme,
            'user' => $student
        ]);
    }

    /**
     * Executa a rotina 'getTheme' no fluxo de negocio.
     */
    private function getTheme($userPreferences)
    {
        return app(UserUiPreferencesService::class)->resolveTheme($userPreferences);
    }
}
