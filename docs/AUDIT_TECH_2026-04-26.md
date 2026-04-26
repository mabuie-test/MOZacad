# Auditoria Técnica MOZacad — 2026-04-26

## Fase 1 — Auditoria baseada em código

### 1) Estado geral do sistema
O repositório apresenta uma base **MVC PHP 8.2** funcional, com separação razoável entre controllers, services e repositories, suporte híbrido HTML/JSON, fluxo transacional de pedidos/pagamentos e ciclo de geração documental com fila de revisão humana. O sistema já cobre o backbone de negócio (pedido → pricing → invoice/payment → geração AI → revisão humana → download), mas ainda há lacunas de hardening e consistência em produção, especialmente em integridade de dados, segurança operacional e limites de responsabilidade na camada HTTP/admin.

### 2) O que está bem implementado
- Bootstrap mínimo e direto, com autoload Composer, carregamento de env e sessão com `strict_mode`, `httponly` e `samesite` definidos.
- CSRF centralizado no `BaseController` + helper dedicado, e formulários web com token.
- Padrão híbrido HTML/JSON consistente em vários controllers usando `wantsJson()/isHtmlRequest()`.
- Fluxo de pagamento com cuidados de idempotência/concurrency via locks e reaproveitamento de pagamento pendente.
- Webhook com validação de assinatura HMAC, checagem de configuração e sanitização de headers em logs.
- Transições de estado de pagamento com anti-regressão de status e efeitos de domínio (invoice/order/dispatch de job) em transação.
- Polling separado do webhook, ambos convergindo na mesma máquina de transição de estado.
- Uploads com validação de tamanho + MIME real (`finfo`) e paths de storage centralizados.
- Downloads protegidos por autorização, validação de status/versionamento e verificação física de ficheiro.
- AI jobs com mecanismo de reserva (`reservation_token`), recuperação de stale jobs e marcação de processamento/completude/erro.
- Pipeline documental relativamente completo (normas/regras/template/fallback → geração → refinamento → DOCX → fila humana).
- Administração já segmentada por secções (catálogo, pricing, descontos, cupões, regras, revisão humana), com `requireAdminAccess()` e CSRF em ações mutáveis.

### 3) O que está funcional mas ainda frágil
- Router próprio é simples e funcional, mas não tem middleware chain nativa nem políticas HTTP avançadas.
- Sessão usa `secure` só quando HTTPS detectado; não há estratégia explícita de expiração/idle timeout.
- API de pagamento reutiliza sessão/cookie e remove exigência de CSRF para caminhos `/api/*`; funcional, mas o contrato de autenticação API não está endurecido (token/JWT, rate limit, scopes).
- Erros internos são devolvidos no payload em alguns `catch` (exposição técnica em falhas 5xx).
- `validate_runtime.php` e `SchemaConvergenceService` melhoram robustez, mas dependem de execução operacional disciplinada (não automática por runtime web).
- Camada admin ainda tem controlador único grande (`AdminController`) e acoplamento elevado com múltiplos serviços num mesmo entrypoint.

### 4) O que está parcial
- Módulo de templates/normas está explicitamente em modo de diagnóstico/read-only; não há CRUD/publicação full in-app para ficheiros de templates.
- Middleware dedicados (`AuthMiddleware`/`RoleMiddleware`) existem mas não estão integrados no router padrão atual.
- A secção admin de templates apresenta prontidão e resolução operacional, mas não oferece ciclo completo de gestão de artefactos.
- Há cobertura de revisão humana (assign/decide), porém sem trilha avançada de SLA/escalonamento (não confirmável no código lido).

### 5) O que está incompleto
- Ausência de rate limiting, lockout e controles anti-bruteforce no login.
- Ausência de trilha explícita de rotação/expiração de sessão por tempo de inatividade.
- Ausência de mecanismo API robusto para endpoints JSON (chave de API/OAuth/JWT) — hoje depende da sessão web.
- Ausência de evidência de testes automatizados (unit/integration/e2e) no repositório lido.

### 6) Riscos de segurança (priorizados)
1. **Exposição de detalhes internos em erros 5xx** (mensagens de exceção devolvidas em respostas de controllers).
2. **Hardening incompleto de autenticação** (sem rate limiting/lockout no login).
3. **Superfície API baseada em sessão web sem camada dedicada de auth API** (pode dificultar endurecimento e observabilidade de abuso).
4. **Configuração insegura possível por default local** (`APP_DEBUG=true`, `DEBITO_ALLOW_UNSIGNED_WEBHOOK_LOCAL=true`) se replicada fora de ambiente controlado.
5. **Seed com contas previsíveis de ambiente de teste** pode ser risco operacional se usado indevidamente em produção.

### 7) Riscos de arquitetura/manutenção (priorizados)
1. **AdminController grande/concentrado** (múltiplas responsabilidades e rotas mutáveis numa única classe).
2. **Middleware não acoplados ao router** (duplicação de gatekeeping nos controllers em vez de pipeline HTTP uniforme).
3. **Muitas regras críticas em convenção operacional** (scripts de validação/convergência dependem de execução externa).
4. **Forte acoplamento a arrays associativos em domínio complexo** (tipagem parcial via DTO, mas ainda com carga alta de arrays crus em múltiplos serviços/repos).

### 8) Riscos operacionais/produção (priorizados)
1. **Dependência de jobs externos (polling/AI) sem orquestração observável no próprio app** (necessidade de scheduler/monitoring externo disciplinado).
2. **Convergência de schema baseada em scripts e migrations idempotentes variadas**: risco de drift se pipelines não forem padronizados.
3. **Módulo de templates normativos sem gestão full in-app** pode gerar dependência operacional manual para publicação/versionamento de artefactos.
4. **Sem confirmação no código lido de healthchecks estruturados e métricas de SLO** (não consegui confirmar no código lido).

### 9) Prioridades reais de correção
1. Eliminar exposição de detalhes técnicos em respostas de erro para clientes.
2. Endurecer autenticação (rate limit + lockout progressivo + telemetria de tentativas).
3. Formalizar camada de autenticação para API JSON (token/scopes) ou bloquear API session-based para uso estritamente first-party com controles adicionais.
4. Modularizar `AdminController` em controladores por domínio (comercial, governança, revisão humana, catálogo).
5. Fechar gap operacional de templates/normas (upload/validação/publicação/versionamento com autorização/auditoria).
6. Automatizar enforcement de runtime checks no pipeline de deploy (não só execução manual).

---

## Fase 2 — Prompt cirúrgico de correção

### A) CONTEXTO
O MOZacad já possui fluxo de negócio principal implementado (pedido → cobrança Débito → transição de estado → job AI → revisão humana → download) com base MVC e services/repositories. O sistema está funcional, mas precisa de hardening seletivo em segurança HTTP/API, modularidade administrativa e operação de produção.

### B) LACUNAS PRIORITÁRIAS
1. Respostas 5xx expõem detalhes internos (`exception->getMessage()` em payload de erro).
2. Login sem rate limiting/lockout.
3. Endpoints JSON de pagamento dependem de sessão web, sem estratégia de auth API explícita.
4. `AdminController` excessivamente concentrado.
5. Gestão de templates/normas ainda em modo read-only/diagnóstico.
6. Validação operacional depende de execução manual de scripts.

### C) MISSÃO
Aplicar correções cirúrgicas, preservando o fluxo funcional atual e sem reescrever o sistema:
- hardening de erro/autenticação/API;
- modularização administrativa incremental;
- completar governança operacional de templates/normas;
- reforçar automação de verificações de produção.

### D) RESTRIÇÕES
- Não destruir funcionalidades já estáveis (pagamentos/transições/jobs/revisão/download).
- Não trocar stack (manter PHP 8.2 + MySQL + arquitetura existente).
- Não criar arquitetura paralela nem redesign total.
- Não responder com plano abstrato: entregar alterações concretas em ficheiros reais.

### E) ENTREGÁVEIS
1. **Lista de ficheiros alterados** com objetivo por ficheiro.
2. **Código completo** das alterações (controllers/services/repos/helpers/views/scripts).
3. **Migrations/rotas/controllers/views** apenas quando necessário ao requisito.
4. **Validação objetiva**:
   - comandos executados;
   - resultados esperados/obtidos;
   - prova de não regressão nos fluxos já funcionais.
5. **Critérios mínimos de aceite**:
   - nenhum endpoint público retorna mensagem técnica de exceção;
   - login com rate limit/lockout ativo;
   - API JSON com política de auth clara e testável;
   - admin segmentado por domínio (mínimo comercial/governança/revisão);
   - templates/normas com operação administrável (upload + validação + publicação auditável);
   - validações operacionais integradas ao pipeline de deploy.
