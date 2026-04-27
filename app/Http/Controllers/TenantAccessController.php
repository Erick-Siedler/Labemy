<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\UserRelation;
use App\Services\TenantRelationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TenantAccessController extends Controller
{
    /**
     * Lista tenants disponiveis para selecao.
     */
    public function index(TenantRelationService $relationService): View|RedirectResponse
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $tenants = $relationService->listAccessibleTenants($user);
        if ($tenants->isEmpty()) {
            return redirect()->route('plans')
                ->with('error', 'Nenhum tenant vinculado para este usuario.');
        }

        if ($tenants->count() === 1) {
            $tenantId = (int) $tenants->first()->id;
            $relationService->activateTenant($user, $tenantId);

            return redirect()->route($relationService->resolveDashboardRoute($user, $tenantId));
        }

        $relationsByTenant = UserRelation::with(['lab', 'group'])
            ->where('user_id', $user->id)
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->where('status', 'active')
            ->orderByRaw("FIELD(role, 'owner', 'teacher', 'assistant', 'student')")
            ->get()
            ->groupBy('tenant_id');

        $ownedTenantIds = Tenant::where('creator_id', $user->id)
            ->pluck('id')
            ->flip();

        return view('main.tenant.select-tenant', [
            'tenants' => $tenants,
            'relationsByTenant' => $relationsByTenant,
            'ownedTenantIds' => $ownedTenantIds,
            'activeTenantId' => (int) session('active_tenant_id', 0),
        ]);
    }

    /**
     * Persiste tenant ativo na sessao.
     */
    public function select(Request $request, TenantRelationService $relationService): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $activated = $relationService->activateTenant($user, (int) $data['tenant_id']);
        if (!$activated) {
            return back()->withErrors([
                'tenant_id' => 'Voce nao possui acesso a este tenant.',
            ])->withInput();
        }

        return redirect()->route($relationService->resolveDashboardRoute($user, (int) $data['tenant_id']));
    }

    /**
     * Revoga relacao ativa do usuario com o tenant selecionado.
     */
    public function revoke(Request $request, TenantRelationService $relationService): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => 'required|integer|exists:tenants,id',
        ]);

        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $tenantId = (int) $data['tenant_id'];
        $revoked = $relationService->revokeTenantAccess($user, $tenantId);
        if (!$revoked) {
            return back()->withErrors([
                'tenant_id' => 'Nao foi possivel remover sua relacao com este tenant.',
            ]);
        }

        if ((int) session('active_tenant_id', 0) === $tenantId) {
            session()->forget([
                'active_tenant_id',
                'active_relation_id',
                'active_relation_role',
                'active_lab_id',
                'active_group_id',
            ]);
        }

        $remainingTenants = $relationService->listAccessibleTenants($user);
        if ($remainingTenants->isEmpty()) {
            return redirect()->route('plans')
                ->with('success', 'Relacao removida com sucesso.');
        }

        if ($remainingTenants->count() > 1) {
            return redirect()->route('tenant.select.index')
                ->with('success', 'Relacao removida com sucesso.');
        }

        $tenantIdToActivate = (int) $remainingTenants->first()->id;
        $relationService->activateTenant($user, $tenantIdToActivate);

        return redirect()->route($relationService->resolveDashboardRoute($user, $tenantIdToActivate))
            ->with('success', 'Relacao removida com sucesso.');
    }
}
