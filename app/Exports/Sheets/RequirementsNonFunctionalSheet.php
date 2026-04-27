<?php

namespace App\Exports\Sheets;

use App\Models\NonFuncReq;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RequirementsNonFunctionalSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle
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
        return NonFuncReq::query()
            ->where('tenant_id', $this->tenantId)
            ->where('project_id', $this->projectId)
            ->with([
                'functional' => function ($query): void {
                    $query->where('tenant_id', $this->tenantId)
                        ->where('project_id', $this->projectId)
                        ->orderBy('code');
                },
            ])
            ->orderBy('code')
            ->get()
            ->map(function (NonFuncReq $nonFuncReq): array {
                return [
                    'codigo' => (string) $nonFuncReq->code,
                    'titulo' => (string) $nonFuncReq->title,
                    'categoria' => (string) ($nonFuncReq->category ?? ''),
                    'prioridade' => $this->priorityLabel((string) $nonFuncReq->priority),
                    'status' => $this->statusLabel((string) $nonFuncReq->status),
                    'rfs_vinculados' => $nonFuncReq->functional->pluck('code')->implode(', '),
                    'descricao' => (string) ($nonFuncReq->description ?? ''),
                    'criterios_aceitacao' => (string) ($nonFuncReq->acceptance_criteria ?? ''),
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
            'Categoria',
            'Prioridade',
            'Status',
            'RFs vinculados',
            'Descricao',
            'Criterios de aceitacao',
        ];
    }

    /**
     * Executa a rotina 'title' no fluxo de negocio.
     */
    public function title(): string
    {
        return 'RNFs';
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

