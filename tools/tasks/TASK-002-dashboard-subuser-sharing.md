# TASK-002: Dashboard compartilhado para subusers nao-teacher

## Quando executar
- Executar apos a entrega e validacao do suporte a grafico de colunas no dashboard.

## Objetivo
- Manter dashboard individual apenas para subusers com `role=teacher`.
- Fazer `assistant` e `student` usarem dashboard compartilhado por grupo.
- Permitir que `assistant` visualize o dashboard do grupo no contexto de grupo/projeto.

## Escopo tecnico sugerido
- Ajustar `DashboardService::ensureDashboard` para resolver chave de dashboard por contexto:
  - `teacher`: chave por `subuser_id` (individual).
  - `assistant|student`: chave por `group_id` (compartilhado).
- Adaptar pontos de entrada que carregam dashboard em `SubHomeDataService`, `GroupController` e `ProjectController` para passar/usar contexto de grupo quando necessario.
- Criar migracao de dados (ou command) para consolidar dashboards existentes de `assistant|student` por grupo.
- Revisar regras de autorizacao para garantir que `assistant` veja dashboard dos grupos permitidos.

## Criterios de aceite
- Teacher continua com dashboard proprio.
- Dois students do mesmo grupo enxergam o mesmo dashboard (mesmos cards/graficos/paginas).
- Assistant visualiza o dashboard do grupo ao abrir paginas de grupo/projeto.
- Nenhum acesso cruzado entre grupos de labs nao permitidos.
