<?php

namespace App\Http\Controllers;

use App\Services\SubHomeDataService;
use App\Services\TenantRelationService;
use App\Services\UserUiPreferencesService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SubUserHomeController extends Controller
{
    /**
     * Executa a rotina 'home' no fluxo de negocio.
     */
    public function home(SubHomeDataService $svc, TenantRelationService $relationService): View|RedirectResponse
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        $role = $relationService->resolveActiveRole($user);
        if ($role === 'owner') {
            return redirect()->route('home');
        }

        $view = 'main.home.index-home-student';

        if ($role === 'student') {
            $data = $svc->buildStudent($user);
        } elseif (in_array($role, ['assistant', 'assitant'], true)) {
            $data = $svc->buildAssistant($user);
        } else {
            $data = $svc->buildTeacher($user);
            $view = 'main.home.index-home-owner';
        }

        $theme = $this->getTheme($data['userPreferences']);

        return view($view, $data, [
            'theme' => $theme,
            'user' => $user,
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
