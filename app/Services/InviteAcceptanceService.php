<?php

namespace App\Services;

use App\Models\SubUserInvite;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InviteAcceptanceService
{
    public function consumePendingInvite(
        Request $request,
        User $user,
        TenantRelationService $relationService
    ): ?RedirectResponse {
        $token = (string) ($request->input('invite_token') ?: session('pending_invite_token', ''));
        if ($token === '') {
            return null;
        }

        $tenantId = 0;

        try {
            DB::transaction(function () use ($token, $user, $relationService, &$tenantId): void {
                $invite = SubUserInvite::where('token_hash', hash('sha256', $token))
                    ->lockForUpdate()
                    ->first();

                if (!$invite || !empty($invite->used_at) || $invite->expires_at->isPast()) {
                    throw new \RuntimeException('invalid_invite');
                }

                if (!empty($invite->email) && strcasecmp((string) $invite->email, (string) $user->email) !== 0) {
                    throw new \RuntimeException('invite_email_mismatch');
                }

                $this->attachAndConsumeInvite($invite, $user, $relationService);
                $tenantId = (int) $invite->tenant_id;
            });
        } catch (\RuntimeException $exception) {
            session()->forget('pending_invite_token');

            if ($exception->getMessage() === 'invite_email_mismatch') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'Esta conta nao corresponde ao e-mail convidado.');
            }

            return redirect()->route('login')
                ->with('error', 'Convite invalido ou expirado.');
        }

        if ($tenantId <= 0) {
            session()->forget('pending_invite_token');

            return redirect()->route('login')
                ->with('error', 'Convite invalido ou expirado.');
        }

        $relationService->activateTenant($user, $tenantId);
        session()->forget('pending_invite_token');

        return redirect()->route($relationService->resolveDashboardRoute($user, $tenantId))
            ->with('success', 'Convite aceito com sucesso.');
    }

    public function acceptInviteForUser(SubUserInvite $invite, User $user, TenantRelationService $relationService): void
    {
        DB::transaction(function () use ($invite, $user, $relationService): void {
            $freshInvite = SubUserInvite::where('id', (int) $invite->id)
                ->lockForUpdate()
                ->first();

            if (!$freshInvite) {
                throw new \RuntimeException('Convite invalido.');
            }

            if (!empty($freshInvite->used_at)) {
                throw new \RuntimeException('Este convite ja foi utilizado.');
            }

            if ($freshInvite->expires_at->isPast()) {
                throw new \RuntimeException('Este convite expirou.');
            }

            if (!empty($freshInvite->email) && strcasecmp((string) $freshInvite->email, (string) $user->email) !== 0) {
                throw new \RuntimeException('Esta conta nao corresponde ao e-mail convidado.');
            }

            $this->attachAndConsumeInvite($freshInvite, $user, $relationService);
            $relationService->activateTenant($user, (int) $freshInvite->tenant_id);
        });
    }

    private function attachAndConsumeInvite(
        SubUserInvite $invite,
        User $user,
        TenantRelationService $relationService
    ): void {
        $relationService->attachUserToTenant(
            $user,
            (int) $invite->tenant_id,
            !empty($invite->lab_id) ? (int) $invite->lab_id : null,
            !empty($invite->group_id) ? (int) $invite->group_id : null,
            (string) ($invite->role ?? 'student')
        );

        $invite->update([
            'used_at' => now(),
            'accepted_by_user_id' => (int) $user->id,
        ]);
    }
}
