<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Services\HomeOwnerDataService;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function index(HomeOwnerDataService $homeOwnerData)
    {
        $user = Auth::user();
        $data = $homeOwnerData->build($user);

        $preferences = json_encode($data['userPreferences'], true);

        $preferences = explode('{', $preferences)[1];
        $preferences = explode('}', $preferences)[0];

        $theme = explode(',', $preferences)[0];
        $theme = explode(':', $theme)[1];

        $lang = explode(',', $preferences)[1];
        $lang = explode(':', $lang)[1];

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

    public function indexSolo(HomeOwnerDataService $homeOwnerData)
    {
        $user = Auth::user();
        $data = $homeOwnerData->buildSolo($user);

        $data['pageTitle'] = 'Projetos';
        $data['pageBreadcrumbHome'] = 'Início';
        $data['pageBreadcrumbCurrent'] = 'Projetos';

        $preferences = json_encode($data['userPreferences'], true);

        $preferences = explode('{', $preferences)[1];
        $preferences = explode('}', $preferences)[0];

        $theme = explode(',', $preferences)[0];
        $theme = explode(':', $theme)[1];

        $lang = explode(',', $preferences)[1];
        $lang = explode(':', $lang)[1];

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

