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
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\Lab;
use App\Models\Group;
use Illuminate\Support\Facades\Validator;
use App\Services\SubHomeDataService;

class SubUserHomeController extends Controller
{
    public function home(SubHomeDataService $svc)
    {
        $subUser = Auth::guard('subusers')->user();
        $role = $subUser->role;
        $view = 'main.home.index-home-student';

        if ($role === 'student') {
            $data = $svc->buildStudent($subUser);
        } elseif (in_array($role, ['assistant', 'assitant'], true)) {
            $data = $svc->buildAssistant($subUser);
        } else {
            $data = $svc->buildTeacher($subUser);
            $view = 'main.home.index-home-owner';
        }

        $theme = $this->getTheme($data['userPreferences']);

        return view($view, $data, [
            'theme' => $theme,
            'user' => $subUser,
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

    private function getAssistantdata($student) :array
    {
        return [];
    }

    private function getTeacherdata($student) :array
    {
        return [];
    }
}
