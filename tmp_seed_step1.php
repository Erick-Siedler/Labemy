<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FuncReq;
use App\Models\NonFuncReq;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

$tenantId = 3;
$projectId = 6;
$createdByTable = 'users';
$createdById = 3;
$status = 'draft';

$project = Project::where('id', $projectId)->where('tenant_id', $tenantId)->first();
if (!$project) {
    throw new RuntimeException('Projeto nao pertence ao tenant informado.');
}

$rfRaw = <<<'TXT'
RF01|Exibir Planos e Iniciar Checkout|high|Mostrar planos disponiveis e permitir iniciar a contratacao/pagamento.|- Pagina lista planos com preco e beneficios.\n- Usuario inicia checkout a partir de um plano.
RF02|Registrar Pagamento e Habilitar Recursos|high|Registrar status de pagamento e liberar acesso conforme plano.|- Confirmacao libera rotas internas.\n- Pagamento fica persistido e consultavel.
RF03|Bloqueio de Acesso sem Pagamento Valido (Gating)|high|Impedir acesso interno quando pagamento/token nao for valido.|- Rotas protegidas bloqueiam/redirecionam.\n- Mensagem orienta regularizacao.
RF04|Cadastro/Login/Logout do Owner|high|Permitir cadastro, login e logout do owner.|- Cadastro cria usuario valido.\n- Login inicia sessao e logout invalida acesso.
RF05|Cadastro/Login/Logout do Subuser via Convite/Token|high|Subuser se registra/acessa por convite e usa area dedicada.|- Convite valido cria subuser.\n- Login/home/logout de subuser funcionam.
RF06|Criar Tenant Apos Autenticacao/Pagamento|high|Owner cria tenant apos estar apto (pagamento ok).|- Usuario apto cria tenant.\n- Usuario nao apto e redirecionado.
RF07|Aplicar Contexto de Tenant em Toda Acao (Escopo do Ator)|high|Toda pagina e CRUD operam apenas no tenant do ator.|- Listagens mostram apenas dados do tenant.\n- IDs externos sao negados.
RF08|CRUD de Labs|high|Criar, editar, listar e excluir labs no tenant.|- Owner autorizado executa CRUD.\n- Exclusao/listagem respeitam tenant.
RF09|CRUD de Grupos em Labs|high|Criar, editar, listar e excluir grupos dentro de um lab.|- CRUD funciona no lab correto.\n- Grupo pertence ao tenant correto.
RF10|Gerenciar Papeis de Membros no Grupo|medium|Ajustar role/permissoes de membros no grupo.|- Owner/teacher altera roles permitidos.\n- Permissoes refletem imediatamente.
RF11|CRUD de Projetos|high|Criar, editar, listar e excluir projetos em lab/grupo.|- CRUD respeita tenant.\n- View do projeto exibe vinculos corretos.
RF12|CRUD de Subpastas do Projeto|medium|Gerenciar subpastas que organizam versoes/arquivos.|- Criar/editar/listar/excluir subpasta funciona.
RF13|Criar Versao do Projeto (com opcional Subpasta)|high|Criar nova versao vinculada ao projeto e opcionalmente subpasta.|- Versao aparece na listagem.\n- Versao registra autor e data.
RF14|Alterar Status da Versao (Draft/Submetida/Aprovada/Rejeitada)|high|Controlar transicoes de status com revisao.|- Submissao/aprovacao/rejeicao registradas.\n- Status fica consistente.
RF15|Comentarios e Revisao em Versoes|medium|Permitir comentarios em versoes para feedback.|- Comentario e criado/listado com autor/data.\n- Permissoes e tenant sao respeitados.
RF16|Upload de Arquivos em Versoes|high|Permitir anexar arquivos a versao.|- Upload salva arquivo e metadados.\n- Arquivo aparece apos upload.
RF17|Download/Acesso a Arquivos por Path|high|Permitir download/acesso de arquivos vinculados a versao.|- Download retorna arquivo correto.\n- Acesso por path respeita escopo.
RF18|Preview/Visualizacao de Arquivos Suportados|medium|Permitir preview de arquivos suportados no navegador.|- Arquivos suportados abrem preview.\n- Nao suportados oferecem download.
RF19|Notificacoes por Eventos do Sistema|medium|Gerar notificacoes para eventos do sistema.|- Evento gera notificacao para destinatario correto.\n- Notificacao aparece na interface.
RF20|Excluir/Limpar Notificacoes|medium|Permitir apagar notificacoes especificas ou limpar todas.|- Apagar uma remove apenas do usuario.\n- Limpar remove todas do usuario.
RF21|Registrar Logs de Acoes Importantes|medium|Registrar acoes criticas para auditoria.|- Logs aparecem em listagem.\n- Logs registram ator/acao/recurso/data.
RF22|Exportar Logs em Excel|low|Exportar logs do tenant em formato Excel.|- Download do Excel funciona.\n- Exportacao contem apenas dados do tenant.
RF23|CRUD de RFs e RNFs do Projeto|low|Criar/editar/listar/excluir RFs e RNFs no projeto.|- Requisitos sao persistidos/listados por projeto.\n- Permissoes e tenant sao respeitados.
RF24|Relacionar RNFs com RFs (RNF dentro do RF)|low|Vincular RNFs como constraints de um RF.|- E possivel associar RNF a RF e visualizar no detalhe.
TXT;

$grnfRaw = <<<'TXT'
G-RNF01|Isolamento Multi-tenant (Anti Cross-Tenant / Anti IDOR)|G-RNF|high|Todo acesso/consulta/acao e restrito ao tenant do ator.|- IDs fora do tenant retornam 403/404.\n- Queries criticas filtram tenant.\n- Tentativas cross-tenant sao logadas.
G-RNF02|Autenticacao e Sessao Seguras|G-RNF|high|Autenticacao/sessao seguem praticas seguras.|- Senhas com hashing.\n- Sessao expira e logout invalida acesso.\n- Guards nao vazam permissoes.
G-RNF03|Autorizacao por Papeis (RBAC)|G-RNF|high|Acoes permitidas/negadas conforme role.|- Rotas sensiveis validam role no backend.\n- Student nao executa acoes exclusivas de teacher/owner.
G-RNF04|Validacao e Sanitizacao de Entrada|G-RNF|high|Todo input e validado/sanitizado, inclusive path.|- Inputs invalidos retornam erro controlado.\n- Path traversal e bloqueado.
G-RNF05|Auditoria e Rastreabilidade (Logs)|G-RNF|medium|Eventos criticos geram logs rastreaveis.|- CRUD/review de versao gera log.\n- Log registra ator/acao/recurso/timestamp/tenant.
G-RNF06|Integridade e Transacoes em Operacoes Criticas|G-RNF|medium|Operacoes compostas preservam consistencia.|- Falha nao deixa registro orfao.\n- Uso de transacao/rollback quando aplicavel.
G-RNF07|Performance Base (Paginacao e Consultas Eficientes)|G-RNF|medium|Listagens grandes devem ser paginadas e consultas eficientes.|- Listas de versoes/arquivos/logs paginadas.\n- Rotas principais evitam carga total em memoria.
G-RNF08|Usabilidade Minima (Responsividade e Feedback)|G-RNF|medium|Interface responsiva e com feedback claro.|- UI responsiva em desktop/mobile.\n- Acoes destrutivas pedem confirmacao.
TXT;

DB::transaction(function () use ($rfRaw, $grnfRaw, $tenantId, $projectId, $createdByTable, $createdById, $status) {
    foreach (array_filter(array_map('trim', explode("\n", $rfRaw))) as $line) {
        [$code, $title, $priority, $description, $criteria] = explode('|', $line, 5);
        FuncReq::updateOrCreate(
            ['tenant_id' => $tenantId, 'project_id' => $projectId, 'code' => $code],
            [
                'created_by_table' => $createdByTable,
                'created_by_id' => $createdById,
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'status' => $status,
                'acceptance_criteria' => str_replace('\\n', "\n", $criteria),
            ]
        );
    }

    foreach (array_filter(array_map('trim', explode("\n", $grnfRaw))) as $line) {
        [$code, $title, $category, $priority, $description, $criteria] = explode('|', $line, 6);
        $rnf = NonFuncReq::updateOrCreate(
            ['tenant_id' => $tenantId, 'project_id' => $projectId, 'code' => $code],
            [
                'created_by_table' => $createdByTable,
                'created_by_id' => $createdById,
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'priority' => $priority,
                'status' => $status,
                'acceptance_criteria' => str_replace('\\n', "\n", $criteria),
            ]
        );
        $rnf->functional()->sync([]);
    }
});

$rfCount = FuncReq::where('tenant_id', $tenantId)->where('project_id', $projectId)->count();
$grnfCount = NonFuncReq::where('tenant_id', $tenantId)->where('project_id', $projectId)->where('category', 'G-RNF')->count();

echo "OK etapa 1\n";
echo "RF total projeto: {$rfCount}\n";
echo "G-RNF total projeto: {$grnfCount}\n";