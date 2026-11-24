# Gestão de configuração

Entregável 5 · Responsável: Gustavo · Atualizado em 24/11/2026

A descrição completa (branching, convenção de commits, pull requests, tags) está em `a3/README.md`, seção "Gestão de configuração". Este documento resume o que está em vigor e aponta as evidências.

## 1. O que está em vigor

| Item | Decisão | Onde está |
|---|---|---|
| Estratégia de branches | Trunk-based development, com `a3/base` como tronco; branches curtas `a3/<tipo>-<assunto>` | `a3/README.md` |
| Proteção da `a3/base` | Pull request obrigatório, 1 aprovação, check `qualidade` obrigatório, branch atualizada antes do merge; sem bypass, force push ou exclusão | `a3/04-pipeline.md` (seção 4) e `a3/evidencias/pipeline/protecao-branch-*.png` |
| Convenção de commits | `<tipo>: <o que mudou e por quê>` (`feat`, `test`, `docs`, `refactor`, `chore`) | `a3/README.md` |
| Modelo de pull request | Cita o entregável e a base correta (`a3/base` do fork) | `.github/pull_request_template.md` |
| Estratégia de merge | Merge commit ou rebase nos PRs que carregam evidência de processo (preserva o ciclo TDD); sem squash | `a3/README.md` |
| Versão fixada | Easy!Appointments 1.6.1, SHA `d30cba6d90772e3a76dcba358322e55746704339` | `a3/registros/versao.txt` |

## 2. Tags e releases

| Tag | Situação |
|---|---|
| `1.6.1` | Existe: versão fixada, ponto de partida do trabalho |
| `a3-baseline` | Existe: medições iniciais (95 testes, 24,91% de cobertura do `Availability`) |
| `a3-entrega` | Prevista para 02/12/2026: release final com as métricas finais |

## 3. Histórico dos pull requests

| PR | Conteúdo | Situação em 24/11 |
|---|---|---|
| #1 | Prova do quality gate (meta 30% reprova, corrigida para 24%) | Mergeado |
| #2 | Análise estática com PHPStan e baseline | Mergeado |
| #3 | Prova do PHPStan (erro proposital reprova) | Mergeado |
| #4 e #5 | README do A3 e modelo de PR; comandos para Windows | Mergeados |
| #6 | `BookingPolicyValidator` com TDD (RN11) | Mergeado |
| #8 | BDD da RN11 com Behat e passo no CI | Mergeado (a mensagem do commit cita `#7`) |
| #7 | Plano de testes e suíte de regras do `Availability` | Mergeado |
| #9 | Testes de horas, limites e períodos; meta 80% | Substituído pelo PR de correções |
| PR de correções | Plano de qualidade, atas, registros, evidências e Entregáveis 3 a 5 | Em revisão |

## 4. Evidências

- Proteção da branch: `a3/evidencias/pipeline/protecao-branch-1.png` a `-3.png`.
- Push direto recusado: `a3/evidencias/pipeline/push-direto-recusado.txt`.
- PRs bloqueados e liberados pelo gate: `a3/evidencias/pipeline/pr1-*.png`.

## 5. Pendências

- Registro formal da solicitação de mudança que acompanha a tag `a3-entrega` (a ligação com o Entregável 9, manutenção e evolução, ainda não foi escrita).
- Conferir o número do PR nas mensagens de commit (o PR #8 saiu com `#7`).
- Criar a release `a3-entrega` em 02/12, com as métricas finais.
