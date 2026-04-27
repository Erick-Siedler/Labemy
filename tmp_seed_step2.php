<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FuncReq;
use App\Models\NonFuncReq;
use Illuminate\Support\Facades\DB;

$tenantId = 3;
$projectId = 6;
$createdByTable = 'users';
$createdById = 3;
$status = 'draft';

$scopeRaw = <<<'TXT'
S-RNF01.1|RF01|Protecao de Dados do Provedor de Pagamento|high|O front nao deve expor credenciais/secrets do provedor.|Nenhuma chave secreta no cliente; fluxo sensivel via backend.
S-RNF01.2|RF01|Clareza dos Limites do Plano (UX)|medium|Exibir limites e restricoes do plano com clareza.|Limites de armazenamento/features visiveis no comparativo.
S-RNF02.1|RF02|Idempotencia de Confirmacao de Pagamento|high|Confirmacao nao deve duplicar registros.|Repeticao do evento nao cria pagamento duplicado.
S-RNF02.2|RF02|Log do Evento de Pagamento|medium|Pagamento confirmado gera auditoria.|Evento de confirmacao gera log com ator/tenant/data.
S-RNF03.1|RF03|Gating no Backend (Nao so UI)|high|Bloqueio garantido por middleware/backend.|Request manual nao bypassa gating.
S-RNF03.2|RF03|UX de Bloqueio (Mensagem e Caminho)|medium|Ao bloquear, fornecer motivo e acao sugerida.|Tela informa status e caminho para regularizacao.
S-RNF04.1|RF04|Rate Limit em Login/Cadastro|high|Limitar tentativas para reduzir abuso.|Muitas tentativas consecutivas bloqueiam temporariamente.
S-RNF04.2|RF04|Privacidade no Erro de Login|medium|Nao revelar se email existe.|Erro de autenticacao permanece generico.
S-RNF05.1|RF05|Token com Expiracao e Controle de Uso|high|Convites devem expirar e evitar uso indevido.|Token invalido/expirado e rejeitado.
S-RNF05.2|RF05|Vinculo Automatico ao Tenant/Lab Correto|high|Subuser e vinculado via convite ao escopo correto.|Nao e possivel migrar por manipulacao de request.
S-RNF06.1|RF06|Evitar Tenant Duplicado|medium|Impedir tenants duplicados para o mesmo owner.|Tentativa duplicada retorna erro controlado/reuso.
S-RNF06.2|RF06|Log do Onboarding do Tenant|medium|Registrar criacao do tenant e origem do fluxo.|Onboarding gera log com owner/tenant/timestamp.
S-RNF07.1|RF07|Filtro de Tenant em Queries Criticas|high|Queries criticas devem filtrar tenant obrigatoriamente.|Nao ha consulta critica sem escopo de tenant.
S-RNF07.2|RF07|Cobertura de Cenarios Cross-Tenant|low|Cobrir tentativas cross-tenant em validacao/teste.|Existe ao menos um caso de bloqueio cross-tenant.
S-RNF08.1|RF08|Exclusao com Integridade (Dependencias)|medium|Excluir lab tratando dependencias.|Nao ficam registros orfaos apos exclusao.
S-RNF08.2|RF08|Confirmacao de Exclusao (UX)|medium|Confirmacao explicita antes de excluir.|Modal/confirm obrigatorio para exclusoes.
S-RNF09.1|RF09|Permissoes por Role para CRUD de Grupos|high|Somente roles permitidos executam acoes.|Student/assistant nao executa acao proibida.
S-RNF09.2|RF09|Consistencia de Vinculo (Grupo-Lab-Tenant)|high|Grupo deve manter coerencia com lab e tenant.|Nao e possivel apontar grupo para lab de outro tenant.
S-RNF10.1|RF10|Anti Escalonamento de Privilegio|high|Bloquear elevacao indevida de privilegios.|Backend valida matriz de permissao.
S-RNF10.2|RF10|Auditoria de Mudanca de Role|medium|Registrar antes/depois de role.|Log guarda ator, alvo, role anterior e nova.
S-RNF11.1|RF11|Paginacao/Filtros em Listagens de Projetos|medium|Listagens de projetos devem ser eficientes.|Listagem paginada com filtro basico.
S-RNF11.2|RF11|Regra de Migracao/Movimentacao Controlada|low|Impedir movimentacao indevida entre escopos.|Nao existe reassociacao indevida entre tenants.
S-RNF12.1|RF12|Vinculo Obrigatorio (Subpasta-Projeto-Tenant)|high|Subpasta sempre vinculada ao projeto/tenant correto.|Nao associar subpasta a projeto de outro tenant.
S-RNF12.2|RF12|Clareza de Hierarquia no Front|medium|UI deve exibir hierarquia de forma clara.|Usuario identifica subpasta atual e projeto pai.
S-RNF13.1|RF13|Metadados Obrigatorios da Versao|high|Versao deve guardar autor e timestamps.|Versao possui autor, created_at e project_id validos.
S-RNF13.2|RF13|Resposta Rapida na Criacao (Performance)|medium|Criacao deve responder rapidamente.|Backend retorna ID/status sem carga desnecessaria.
S-RNF14.1|RF14|Regras de Transicao de Status|high|Validar transicoes permitidas sem pular etapa.|Nao aprova draft sem submissao valida.
S-RNF14.2|RF14|Restricoes de Permissao na Aprovacao/Rejeicao|high|Somente teacher/owner podem aprovar/rejeitar.|Student/assistant nao aprovam via request manual.
S-RNF14.3|RF14|Auditoria Completa do Review|medium|Registrar quem revisou e por qual motivo.|Review registra reviewer, timestamp e feedback.
S-RNF15.1|RF15|Sanitizacao Anti-XSS em Comentarios|high|Comentarios nao podem executar script.|Conteudo potencialmente perigoso e escapado/sanitizado.
S-RNF15.2|RF15|Ordenacao e Identificacao de Autor|medium|Thread de comentarios consistente.|Comentarios ordenados por data com autor/role.
S-RNF16.1|RF16|Validacao de Tipo/Tamanho e Bloqueio de Upload Perigoso|high|Validar extensao/tamanho e bloquear uploads perigosos.|Uploads invalidos sao rejeitados com mensagem.
S-RNF16.2|RF16|Limites por Plano (Armazenamento e Upload)|high|Aplicar limite de upload/armazenamento por plano.|Ao exceder limite, operacao e bloqueada.
S-RNF16.3|RF16|Upload Atomico (Integridade)|medium|Evitar registro sem arquivo ou arquivo sem registro.|Falha faz rollback/limpeza de artefatos.
S-RNF17.1|RF17|Protecao Contra Path Traversal e Acesso Indevido|high|path nao pode sair do escopo permitido.|Bloqueio de ../ e de acesso fora do tenant.
S-RNF17.2|RF17|Streaming de Arquivos Grandes (Performance)|medium|Downloads grandes devem evitar pico de memoria.|Processo usa streaming/chunk quando necessario.
S-RNF17.3|RF17|Auditoria de Download (Opcional)|low|Downloads criticos podem ser rastreados.|Log de download registra ator/arquivo/versao/data.
S-RNF18.1|RF18|Fallback de Compatibilidade|medium|Quando preview falhar, oferecer alternativa.|Sempre existe opcao de download.
S-RNF18.2|RF18|Render Seguro no Preview|high|Preview nao deve executar conteudo inseguro.|Conteudo perigoso e sanitizado ou forca download.
S-RNF19.1|RF19|Notificacoes no Escopo do Tenant e do Usuario|high|Notificacao deve chegar ao tenant/usuario corretos.|Notificacao nao vaza para outro tenant.
S-RNF19.2|RF19|Baixo Impacto de Performance|low|Notificacao nao deve atrasar acao principal.|Acao principal responde mesmo com falha secundaria.
S-RNF20.1|RF20|Restricao de Exclusao ao Dono da Notificacao|high|Usuario apaga apenas as proprias notificacoes.|Excluir notificacao alheia retorna 403/404.
S-RNF21.1|RF21|Privacidade no Log|high|Nao persistir secrets/tokens em log.|Payload sensivel nao e salvo.
S-RNF21.2|RF21|Logging sem Degradar Rotas Criticas|medium|Logging nao pode degradar rotas principais.|Rotas criticas mantem responsividade com logging.
S-RNF22.1|RF22|Exportacao com Consumo Controlado|medium|Exportar sem estourar memoria/tempo.|Volume grande usa estrategia segura de processamento.
S-RNF22.2|RF22|Escopo do Tenant na Exportacao|high|Exportar apenas logs do tenant solicitante.|Arquivo exportado nao contem dados cross-tenant.
S-RNF23.1|RF23|Vinculo Obrigatorio (Requisito-Projeto-Tenant)|high|Requisito deve pertencer ao projeto/tenant corretos.|Nao associar requisito a projeto de outro tenant.
S-RNF23.2|RF23|UX de Status/Tags para Requisitos|low|Permitir organizacao por status/tags.|Requisito pode ter status e filtro correspondente.
S-RNF24.1|RF24|Consistencia de Relacao (Sem Cruzar Projetos/Tenants)|high|Bloquear relacionamentos entre projetos/tenants distintos.|Relacao indevida e negada pelo backend.
S-RNF24.2|RF24|Visualizacao de RNFs Acoplados ao RF|low|Exibir RNFs vinculados no detalhe do RF.|Tela do RF lista RNFs associados claramente.
TXT;

DB::transaction(function () use ($scopeRaw, $tenantId, $projectId, $createdByTable, $createdById, $status) {
    $rfMap = FuncReq::where('tenant_id', $tenantId)
        ->where('project_id', $projectId)
        ->pluck('id', 'code')
        ->toArray();

    foreach (array_filter(array_map('trim', explode("\n", $scopeRaw))) as $line) {
        [$code, $rfCode, $title, $priority, $description, $criteria] = explode('|', $line, 6);

        $rfId = $rfMap[$rfCode] ?? null;
        if (!$rfId) {
            throw new RuntimeException("RF nao encontrado para vinculo: {$rfCode} ({$code})");
        }

        $rnf = NonFuncReq::updateOrCreate(
            ['tenant_id' => $tenantId, 'project_id' => $projectId, 'code' => $code],
            [
                'created_by_table' => $createdByTable,
                'created_by_id' => $createdById,
                'title' => $title,
                'description' => $description,
                'category' => 'S-RNF',
                'priority' => $priority,
                'status' => $status,
                'acceptance_criteria' => $criteria,
            ]
        );

        $rnf->functional()->sync([$rfId]);
    }
});

$sCount = NonFuncReq::where('tenant_id', $tenantId)->where('project_id', $projectId)->where('category', 'S-RNF')->count();
$pivotCount = DB::table('func_non_func')
    ->join('func_reqs', 'func_non_func.func_req_id', '=', 'func_reqs.id')
    ->join('non_func_reqs', 'func_non_func.non_func_req_id', '=', 'non_func_reqs.id')
    ->where('func_reqs.tenant_id', $tenantId)
    ->where('func_reqs.project_id', $projectId)
    ->where('non_func_reqs.tenant_id', $tenantId)
    ->where('non_func_reqs.project_id', $projectId)
    ->count();

echo "OK etapa 2\n";
echo "S-RNF total projeto: {$sCount}\n";
echo "Vinculos func_non_func no projeto: {$pivotCount}\n";