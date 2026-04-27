<?php

namespace App\Http\Controllers;

use App\Services\HomeOwnerDataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Services\SubHomeDataService;
use App\Services\ActivityService;

class SettingController extends Controller
{
    public function index(HomeOwnerDataService $homeOwnerData)
    {   
        $user = Auth::user();
        $data = $homeOwnerData->build($user);
        $data['pageTitle'] = 'Configurações';
        $data['pageBreadcrumbHome'] = 'Início';
        $data['pageBreadcrumbCurrent'] = 'Conta';

        $preferences = json_encode($data['userPreferences'], true);

        $preferences = explode('{', $preferences)[1];
        $preferences = explode('}', $preferences)[0];

        $theme = explode(',', $preferences)[0];
        $theme = explode(':', $theme)[1];

        $lang = explode(',', $preferences)[1];
        $lang = explode(':', $lang)[1];

        return view('main.users.index-user', $data, [
            'theme' => $theme
        ]);
    }

    public function edit(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            abort(403);
        }
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

    function indexSub(SubHomeDataService $svc){
        $student = Auth::guard('subusers')->user();

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

    private function getTheme($userPreferences)
    {
        $preferences = json_encode($userPreferences, true);

        $preferences = explode('{', $preferences)[1];
        $preferences = explode('}', $preferences)[0];

        $theme = explode(',', $preferences)[0];
        $theme = explode(':', $theme)[1];

        return $theme;
    }
}
