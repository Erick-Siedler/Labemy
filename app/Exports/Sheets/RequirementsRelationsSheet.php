<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RequirementsRelationsSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
{
    /**
     * Inicializa as dependencias necessarias para esta classe.
     */
    public function __construct(
        private readonly int $tenantId,
        private readonly int $projectId
    ) {
    }

    /**
     * Executa a rotina 'collection' no fluxo de negocio.
     */
    public function collection(): Collection
    {
        return DB::table('func_non_func')
            ->join('func_reqs', 'func_non_func.func_req_id', '=', 'func_reqs.id')
            ->join('non_func_reqs', 'func_non_func.non_func_req_id', '=', 'non_func_reqs.id')
            ->where('func_reqs.tenant_id', $this->tenantId)
            ->where('func_reqs.project_id', $this->projectId)
            ->where('non_func_reqs.tenant_id', $this->tenantId)
            ->where('non_func_reqs.project_id', $this->projectId)
            ->orderBy('func_reqs.code')
            ->orderBy('non_func_reqs.code')
            ->get([
                'func_reqs.code as rf_codigo',
                'func_reqs.title as rf_titulo',
                'non_func_reqs.code as rnf_codigo',
                'non_func_reqs.title as rnf_titulo',
                'non_func_reqs.category as rnf_categoria',
                'non_func_reqs.priority as rnf_prioridade',
                'non_func_reqs.status as rnf_status',
            ])
            ->map(function (object $row): array {
                return [
                    'rf_codigo' => (string) ($row->rf_codigo ?? ''),
                    'rf_titulo' => (string) ($row->rf_titulo ?? ''),
                    'rnf_codigo' => (string) ($row->rnf_codigo ?? ''),
                    'rnf_titulo' => (string) ($row->rnf_titulo ?? ''),
                    'rnf_categoria' => (string) ($row->rnf_categoria ?? ''),
                    'rnf_prioridade' => $this->priorityLabel((string) ($row->rnf_prioridade ?? '')),
                    'rnf_status' => $this->statusLabel((string) ($row->rnf_status ?? '')),
                ];
            });
    }

    /**
     * Executa a rotina 'headings' no fluxo de negocio.
     */
    public function headings(): array
    {
        return [
            'RF codigo',
            'RF titulo',
            'RNF codigo',
            'RNF titulo',
            'RNF categoria',
            'RNF prioridade',
            'RNF status',
        ];
    }

    /**
     * Executa a rotina 'title' no fluxo de negocio.
     */
    public function title(): string
    {
        return 'Relacionamentos';
    }

    /**
     * Executa a rotina 'priorityLabel' no fluxo de negocio.
     */
    private function priorityLabel(string $value): string
    {
        return match ($value) {
            'high' => 'Alta',
            'medium' => 'Media',
            'low' => 'Baixa',
            default => $value,
        };
    }

    /**
     * Executa a rotina 'statusLabel' no fluxo de negocio.
     */
    private function statusLabel(string $value): string
    {
        return match ($value) {
            'draft' => 'Rascunho',
            'in_progress' => 'Em andamento',
            'approved' => 'Aprovado',
            'rejected' => 'Rejeitado',
            default => $value,
        };
    }
}

