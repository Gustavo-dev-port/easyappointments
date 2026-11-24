# Ata de Auditoria do Processo – Easy!Appointments

Data da auditoria: 24/11/2026. Base auditada: branch `a3/base` no commit `7403ec65` (merge do PR #7) e o histórico de pull requests #1 a #9.
Auditor: [a preencher pelo grupo]. Os itens classificados "Não verificado" dependem de consulta que esta auditoria não fez (aprovações no GitHub, varredura das imagens, linter).

## 1. Objetivo

Verificar se o grupo está seguindo as práticas de desenvolvimento e garantia da qualidade estabelecidas no Plano de Qualidade e SQA (`a3/01-plano-qualidade.md`).

A auditoria considera o controle de versões, as revisões de código, a execução dos testes automatizados, o armazenamento de evidências e a segurança das informações registradas no repositório.

## 2. Checklist de auditoria

| Nº | Item auditado | Resultado | Evidência |
|---|---|---|---|
| 1 | As alterações do projeto estão registradas por meio de commits no Git? | Conforme | Histórico da `a3/base` (`git log`), PRs #1 a #9; tag `a3-baseline`. |
| 2 | As pull requests recebem revisão de outro integrante antes da integração? | Não verificado | A regra de proteção da `a3/base` exige 1 aprovação, mas as aprovações de cada PR não foram consultadas. Merges dos PRs #1, #2 e #4 foram feitos por outro integrante (MusiMus1); o PR #7 foi mergeado por hzmotta. |
| 3 | Os testes automatizados são executados após alterações relevantes? | Conforme | `.github/workflows/ci.yml` (job `qualidade`: PHPStan, PHPUnit com cobertura, Behat e gate); execução registrada em `a3/evidencias/testes-unitarios-e2-e3/execucao-pr7.txt` (108 testes, OK). |
| 4 | Os resultados das execuções dos testes são registrados? | Conforme | `a3/registros/phpunit_baseline.txt`, `a3/registros/cobertura_baseline.txt`, `a3/evidencias/baseline-local/` e `a3/evidencias/testes-unitarios-e2-e3/`. |
| 5 | As evidências de desenvolvimento e testes estão armazenadas no repositório? | Não conforme (corrigida no PR que adiciona esta ata) | Antes deste PR a `a3/` tinha só o plano de testes, o README, o script de cobertura e 2 arquivos de PHPStan; os caminhos citados no plano de qualidade não existiam. Ver NC-01. |
| 6 | As evidências estão livres de senhas, tokens e dados pessoais? | Não verificado | Os arquivos de texto do PR foram varridos por padrões de credencial (sem ocorrências). As imagens (`*.png`) precisam de conferência visual antes do merge. |
| 7 | Os arquivos e alterações respeitam os padrões de formatação definidos pelo projeto? | Não verificado | O CI não roda verificação de `.editorconfig` ou Prettier para PHP. |
| 8 | A versão do software, o SHA e a branch de trabalho estão identificados e registrados? | Conforme | `a3/registros/versao.txt` (1.6.1, SHA `d30cba6d`), tag `1.6.1`, branch `a3/base`. |

## 3. Registro de não conformidades

| Identificação | Não conformidade | Ação corretiva | Responsável | Prazo |
|---|---|---|---|---|
| NC-01 | O plano de qualidade cita arquivos que não estavam no repositório (plano, atas, `registros/`, evidências do baseline). | Commitar os arquivos na `a3/`, no mesmo PR desta ata. | Gustavo (autor do PR) | Neste PR |
| NC-02 | A meta do gate (`META_COBERTURA_AVAILABILITY`) ficou em 69.0 com cobertura medida de 89,59%: folga de ~20 pontos, o gate não pegaria uma regressão grande. | Subir a meta para 80, valor do plano de testes, medindo com os testes do PR #9 incluídos. | Gustavo (autor do PR) | Resolvida no PR #9 (mergeado, `fd86c5d0`) |
| NC-03 | O README diz "merge por squash", mas o histórico mostra merge commits (PR #7) e squashes cujas mensagens citam números de PR trocados. | README ajustado neste PR (merge commit ou rebase nos PRs com evidência de processo). Falta conferir o número do PR nas mensagens de commit dos próximos PRs. | Gustavo (autor do PR) | Neste PR |

## 4. Pontos não verificados

- Aprovações de cada PR (item 2): consultar a aba Reviews de cada PR e anexar prints.
- Imagens de evidência (item 6): conferir que não mostram credenciais ou dados pessoais.
- Padrões de formatação (item 7): não há verificação automática; decidir se entra no CI.

**Integração contínua:** o arquivo `.github/workflows/ci.yml` existe e há execuções registradas (PRs #1 a #3 com check vermelho e verde, e a execução do PR #7). O funcionamento do pipeline está comprovado por essas execuções, não só pela existência do arquivo.

## 5. Resultado da auditoria

**Resultado geral:** Satisfatório com ressalvas.

**Quantidade de itens conformes:** 4 (itens 1, 3, 4 e 8).

**Quantidade de itens não conformes:** 1 (item 5, corrigido no PR que registra esta ata).

**Quantidade de itens não verificados:** 3 (itens 2, 6 e 7).

**Resumo:** o controle de versões, o pipeline e o registro de execuções estão em ordem. A principal falha foi a falta dos documentos e evidências no repositório (NC-01). O gate de cobertura estava frouxo (NC-02). A revisão por pares das pull requests e a verificação de formatação ainda precisam de evidência.

**Evidências consultadas:** `git log` da `a3/base`, `.github/workflows/ci.yml`, `a3/ci/verificar_cobertura.py`, `a3/registros/`, `a3/evidencias/`, PRs #1 a #9.

**Data prevista para acompanhamento das ações corretivas:** 26/11/2026 (checkpoint de orientação).
