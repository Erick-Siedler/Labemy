<?php

namespace App\Exports;

use App\Models\Log;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LogsExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    /**
     * Inicializa as dependencias necessarias para esta classe.
     */
    public function __construct(private readonly int $tenantId)
    {
    }

    /**
     * Executa a rotina 'collection' no fluxo de negocio.
     */
    public function collection(): Collection
    {
        return Log::query()
            ->join('tenants', 'tenants.id', '=', 'logs.tenant_id')
            ->where('logs.tenant_id', $this->tenantId)
            ->orderByDesc('logs.created_at')
            ->get([
                'tenants.name as institution_name',
                'logs.action',
                'logs.user_role',
                'logs.entity_type',
                'logs.entity_id',
                'logs.description',
                'logs.created_at',
            ])
            ->map(function (Log $log): array {
                return [
                    'institution_name' => (string) ($log->institution_name ?? 'N/A'),
                    'action_translated' => $this->translateAction((string) $log->action),
                    'user_role' => (string) $log->user_role,
                    'entity_type' => $this->translateEntityType((string) $log->entity_type),
                    'entity_id' => $log->entity_id,
                    'description' => (string) $log->description,
                    'created_at' => $this->formatDate($log->created_at),
                ];
            });
    }

    /**
     * Executa a rotina 'headings' no fluxo de negocio.
     */
    public function headings(): array
    {
        return [
            'Instituicao',
            'Acao',
            'User Role',
            'Entidade afetada',
            'ID da entidade',
            'Descricao',
            'Criado em',
        ];
    }

    /**
     * Executa a rotina 'translateAction' no fluxo de negocio.
     */
    private function translateAction(string $action): string
    {
        $labels = [
            'access_denied' => 'Acesso negado',
            'comment_create' => 'Criacao de comentario',
            'event_create' => 'Criacao de evento',
            'event_delete' => 'Exclusao de evento',
            'file_download' => 'Download de arquivo',
            'group_create' => 'Criacao de grupo',
            'group_update' => 'Atualizacao de grupo',
            'invite_accept' => 'Aceite de convite',
            'invite_send' => 'Envio de convite',
            'invite_revoke_all' => 'Revogacao em massa de convites',
            'lab_create' => 'Criacao de laboratorio',
            'lab_update' => 'Atualizacao de laboratorio',
            'login' => 'Login',
            'logout' => 'Logout',
            'password_update' => 'Atualizacao de senha',
            'profile_update' => 'Atualizacao de perfil',
            'project_create' => 'Criacao de projeto',
            'project_status_update' => 'Atualizacao de status do projeto',
            'project_update' => 'Atualizacao de projeto',
            'role_update' => 'Atualizacao de papel',
            'settings_update' => 'Atualizacao de configuracoes',
            'tenant_limits_update' => 'Atualizacao de limites da instituicao',
            'version_create' => 'Criacao de versao',
            'version_delete' => 'Exclusao de versao',
            'version_status_update' => 'Atualizacao de status da versao',
            'version_update' => 'Atualizacao de versao',
        ];

        return $labels[$action] ?? Str::of($action)->replace('_', ' ')->title()->toString();
    }

    /**
     * Executa a rotina 'translateEntityType' no fluxo de negocio.
     */
    private function translateEntityType(string $entityType): string
    {
        $labels = [
            'access' => 'Acesso',
            'auth' => 'Autenticacao',
            'event' => 'Evento',
            'group' => 'Grupo',
            'lab' => 'Laboratorio',
            'project' => 'Projeto',
            'project_comment' => 'Comentario de projeto',
            'project_file' => 'Arquivo de projeto',
            'project_version' => 'Versao de projeto',
            'subuser' => 'Subusuario',
            'subuser_invite' => 'Convite de subusuario',
            'tenant' => 'Instituicao',
            'user' => 'Usuario',
        ];

        return $labels[$entityType] ?? Str::of($entityType)->replace('_', ' ')->title()->toString();
    }

    /**
     * Executa a rotina 'formatDate' no fluxo de negocio.
     */
    private function formatDate(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('d/m/Y H:i:s');
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->format('d/m/Y H:i:s');
            } catch (\Throwable) {
                return $value;
            }
        }

        return '';
    }
}
