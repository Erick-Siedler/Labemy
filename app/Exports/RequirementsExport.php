<?php

namespace App\Exports;

use App\Exports\Sheets\RequirementsFunctionalSheet;
use App\Exports\Sheets\RequirementsNonFunctionalSheet;
use App\Exports\Sheets\RequirementsRelationsSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RequirementsExport implements WithMultipleSheets
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
     * Executa a rotina 'sheets' no fluxo de negocio.
     */
    public function sheets(): array
    {
        return [
            new RequirementsFunctionalSheet($this->tenantId, $this->projectId),
            new RequirementsNonFunctionalSheet($this->tenantId, $this->projectId),
            new RequirementsRelationsSheet($this->tenantId, $this->projectId),
        ];
    }
}

