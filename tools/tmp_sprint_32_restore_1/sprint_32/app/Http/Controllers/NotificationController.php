<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function destroy(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer|exists:notifications,id',
        ]);

        $subUser = Auth::guard('subusers')->user();
        if ($subUser) {
            $notification = Notification::where('id', $data['id'])
                ->where('user_id', $subUser->id)
                ->where('table', 'subusers')
                ->firstOrFail();
        } else {
            $notification = Notification::where('id', $data['id'])
                ->where('user_id', Auth::id())
                ->where('table', 'users')
                ->firstOrFail();
        }

        $notification->delete();

        return back()->with('success', 'Notificação removida.');
    }
}
