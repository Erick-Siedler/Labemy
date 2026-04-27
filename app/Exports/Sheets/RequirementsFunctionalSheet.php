<?php

namespace App\Exports\Sheets;

use App\Models\FuncReq;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RequirementsFunctionalSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
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
        return FuncReq::query()
            ->where('tenant_id', $this->tenantId)
            ->where('project_id', $this->projectId)
            ->with([
                'nonFunctional' => function ($query): void {
                    $query->where('tenant_id', $this->tenantId)
                        ->where('project_id', $this->projectId)
                        ->orderBy('code');
                },
            ])
            ->orderBy('code')
            ->get()
            ->map(function (FuncReq $funcReq): array {
                return [
                    'codigo' => (string) $funcReq->code,
                    'titulo' => (string) $funcReq->title,
                    'prioridade' => $this->priorityLabel((string) $funcReq->priority),
                    'status' => $this->statusLabel((string) $funcReq->status),
                    'rnfs_vinculados' => $funcReq->nonFunctional->pluck('code')->implode(', '),
                    'descricao' => (string) ($funcReq->description ?? ''),
                    'criterios_aceitacao' => (string) ($funcReq->acceptance_criteria ?? ''),
                ];
            });
    }

    /**
     * Executa a rotina 'headings' no fluxo de negocio.
     */
    public function headings(): array
    {
        return [
            'Codigo',
            'Titulo',
            'Prioridade',
            'Status',
            'RNFs vinculados',
            'Descricao',
            'Criterios de aceitacao',
        ];
    }

    /**
     * Executa a rotina 'title' no fluxo de negocio.
     */
    public function title(): string
    {
        return 'RFs';
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

