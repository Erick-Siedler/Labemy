<?php

namespace App\Http\Controllers;

use App\Services\HomeOwnerDataService;
use App\Services\TenantRelationService;
use App\Services\UserUiPreferencesService;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Lista e prepara os dados exibidos na tela.
     */
    public function index(HomeOwnerDataService $homeOwnerData, TenantRelationService $relationService)
    {
        $user = Auth::user();
        $role = $relationService->resolveActiveRole($user);

        if (in_array($role, ['teacher', 'assistant', 'asssitant', 'assitant', 'student'], true)) {
            return redirect()->route('subuser-home');
        }

        $data = $homeOwnerData->build($user);

        $theme = app(UserUiPreferencesService::class)->resolveTheme($data['userPreferences']);

        if ($user->plan === 'solo'){
            return redirect()->route('home-solo');
        }

        if ($user->plan === 'none'){
            return redirect()->route('plans');
        }

        return view('main.home.index-home-owner', $data, [
            'theme' => $theme
        ]);
            
    }

    /**
     * Executa a rotina 'indexSolo' no fluxo de negocio.
     */
    public function indexSolo(HomeOwnerDataService $homeOwnerData)
    {
        $user = Auth::user();
        $data = $homeOwnerData->buildSolo($user);

        $data['pageTitle'] = 'Projetos';
        $data['pageBreadcrumbHome'] = 'Início';
        $data['pageBreadcrumbCurrent'] = 'Projetos';

        $theme = app(UserUiPreferencesService::class)->resolveTheme($data['userPreferences']);

        if ($user->plan === 'solo'){
            return view('main.home.index-solo', $data, [
                'theme' => $theme
            ]);
        }

        if ($user->plan === 'none'){
            return redirect()->route('plans');
        }

        return redirect()->route('home');
    }
}

