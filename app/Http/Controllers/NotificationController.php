<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationTenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Remove um registro e trata os vinculos relacionados.
     */
    public function destroy(Request $request)
    {
        $user = Auth::user();
        abort_if(!$user, 403);

        $data = $request->validate([
            'id' => 'required|integer|exists:notifications,id',
        ]);

        $service = app(NotificationTenantService::class);
        $visibleIds = $service->visibleIdsForUser($user, [(int) $data['id']]);
        abort_if(empty($visibleIds), 404);

        Notification::where('id', (int) $data['id'])
            ->where('user_id', (int) $user->id)
            ->where('table', 'users')
            ->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
            ]);
        }

        return back()->with('success', 'Notificacao removida.');
    }

    /**
     * Executa a rotina 'destroyAll' no fluxo de negocio.
     */
    public function destroyAll(Request $request)
    {
        $user = Auth::user();
        abort_if(!$user, 403);

        $candidateIds = $this->parseIds($request->input('ids'));
        $service = app(NotificationTenantService::class);
        $deleted = $service->deleteVisible($user, $candidateIds);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'deleted' => (int) $deleted,
            ]);
        }

        if ($deleted > 0) {
            return back()->with('success', 'Todas as notificacoes foram removidas.');
        }

        return back()->with('success', 'Nao havia notificacoes para remover.');
    }

    /**
     * Normaliza ids vindos do frontend (csv ou array).
     */
    private function parseIds($raw): ?array
    {
        if (empty($raw)) {
            return null;
        }

        $values = is_array($raw) ? $raw : explode(',', (string) $raw);
        $ids = collect($values)
            ->map(function ($value) {
                if (is_numeric($value)) {
                    return (int) $value;
                }
                return null;
            })
            ->filter(fn ($id) => !is_null($id) && $id > 0)
            ->unique()
            ->values()
            ->all();

        return empty($ids) ? null : $ids;
    }
}

