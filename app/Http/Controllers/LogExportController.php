<?php

namespace App\Http\Controllers;

use App\Exports\LogsExport;
use App\Models\Tenant;
use App\Models\UserRelation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class LogExportController extends Controller
{
    /**
     * Executa a rotina '__invoke' no fluxo de negocio.
     */
    public function __invoke()
    {
        $tenant = $this->resolveTenant();
        if (!$tenant) {
            abort(404, 'Instituicao nao encontrada.');
        }

        $fileName = 'logs-' . Str::slug((string) ($tenant->name ?? 'instituicao')) . '-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new LogsExport((int) $tenant->id), $fileName);
    }

    /**
     * Executa a rotina 'resolveTenant' no fluxo de negocio.
     */
    private function resolveTenant(): ?Tenant
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            abort(401);
        }

        $tenantId = (int) session('active_tenant_id', 0);
        if ($tenantId <= 0) {
            $tenantId = (int) (Tenant::where('creator_id', $user->id)->value('id') ?? 0);
        }

        if ($tenantId <= 0) {
            $tenantId = (int) (UserRelation::where('user_id', $user->id)
                ->where('status', 'active')
                ->value('tenant_id') ?? 0);
        }

        if ($tenantId <= 0) {
            return null;
        }

        $ownsTenant = Tenant::where('id', $tenantId)
            ->where('creator_id', $user->id)
            ->exists();

        if (!$ownsTenant) {
            $role = UserRelation::where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->value('role');

            if ((string) $role !== 'teacher') {
                abort(403);
            }
        }

        $tenant = Tenant::where('id', $tenantId)->first();
        if (!$tenant) {
            abort(403);
        }

        return $tenant;
    }
}

