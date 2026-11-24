# Pipeline com quality gate — Easy!Appointments (A3)

Entregável 4 · Responsável: Gustavo · Atualizado em 24/11/2026
Fork: `Gustavo-dev-port/easyappointments` · Branch de integração: `a3/base`

## 1. O que o pipeline faz

Todo código que entra na `a3/base` passa por um robô no GitHub Actions (`.github/workflows/ci.yml`). O robô roda em `push` e em `pull_request` na `a3/base` e tem dois jobs:

- **`qualidade`** (obrigatório para o merge): instala o PHP 8.5 com Xdebug, instala as dependências pelo `composer.lock`, roda as quatro travas abaixo e guarda o `clover.xml` como artefato.
- **`frontend-build`**: `npm ci` e `npm run build`, herdado do projeto.

## 2. As quatro travas do job `qualidade`

| Ordem | Trava | O que barra | Como funciona |
| --- | --- | --- | --- |
| 1 | **Análise estática (PHPStan)** | Erros que nenhum teste pega, como chamar função inexistente | PHPStan 2.3.6, nível 5, em `application/libraries`; os 151 erros herdados ficam em `phpstan-baseline.neon`, então só erro **novo** reprova |
| 2 | **Testes (PHPUnit)** | Quebra de funcionalidade existente | 95 testes e 140 asserções no baseline; 108 testes na execução do PR #7; cobertura Clover via Xdebug |
| 3 | **Testes de aceitação (Behat)** | Quebra de comportamento descrito em Gherkin | `vendor/bin/behat --no-interaction` sobre `features/` (cenários da RN11) |
| 4 | **Quality gate de cobertura** | Perda de teste no módulo crítico | `a3/ci/verificar_cobertura.py` lê o `clover.xml` e reprova se a cobertura de linhas do `Availability.php` ficar abaixo da meta |

**Por que o `Availability.php`:** é o módulo que decide quais horários o sistema oferece, onde mora o risco de conflito de agenda e de horário fora da jornada. O gate é por módulo, e não pela cobertura geral, porque a cobertura geral do código medido é de 12,90% e não diz onde está o risco.

## 3. Meta de cobertura em escada

Ponto de partida medido: **24,91%** de linhas do `Availability` (67 de 269) e 2 de 9 métodos, igual no Docker e no CI. A meta fica na variável `META_COBERTURA_AVAILABILITY` do `ci.yml` e só sobe no **mesmo PR** que acrescenta os testes que a sustentam.

| Marco | Meta | Situação |
| --- | --- | --- |
| Baseline (`a3-baseline`) | 24% | Regra "não regredir" (PRs #1 a #3) |
| PR #7 (testes das regras RN01 a RN12) | 69% | Medido no CI: 89,59% (241 de 269 linhas) |
| PR #9 (testes de horas, limites e períodos) | **80%** | Meta final do Plano de Testes; medido localmente: 94,80% (255 de 269) |

A escada anterior (40%, 50% e 70%) foi substituída: os testes entraram mais rápido que o previsto. A meta de 69% deixava 20 pontos de folga e não pegaria uma regressão grande, então subiu para 80%, valor do Plano de Testes `PT-EA-SQ04-2026` v2.0.

## 4. Regras da branch `a3/base`

Branch protection ativa, valendo inclusive para administradores:

- Pull request obrigatório, com **1 aprovação**.
- Check **`qualidade`** obrigatório, com a branch atualizada antes do merge.
- Sem bypass, sem force push e sem exclusão da branch.

Resultado prático: ninguém altera a `a3/base` sem passar pelo robô e por um revisor.

## 5. Evidências

Arquivos em `a3/evidencias/pipeline/` (exceto os dois `.txt` do PHPStan, que ficam em `a3/evidencias/`). Os números da coluna "O que prova" são os da época de cada captura.

| Evidência | O que prova | Arquivo |
| --- | --- | --- |
| Saídas locais do gate (meta 80 aprova, meta 96 reprova, código 1), no `clover.xml` com 94,80% | O script funciona nos dois sentidos | `gate-local-aprova-reprova.txt` |
| Execução nº 1 vermelha (commit `ed1fa95`) | O CI pegou um problema real de ambiente (fuso) | `ci-1-vermelho.png` |
| Log do "Diagnóstico de fuso" e execução nº 2 verde (commit `af1b475`) | `Asia/Calcutta` ausente no runner; corrigido instalando `tzdata-legacy`; o gate aprova com 24,91% contra meta 24% | `ci-2-diagnostico-fuso.png`, `ci-2-gate-aprovado.png`, `ci-2-verde.png` |
| Push direto recusado (GH006) | A proteção da branch funciona | `push-direto-recusado.txt` |
| PR #1 bloqueado (24,91% contra meta 30%) e liberado com meta 24, mergeado (commit `26e3449`) | O gate reprova quando deve e libera quando passa | `pr1-bloqueado.png`, `pr1-gate-log.png` e `.txt`, `pr1-verde-aguardando-aprovacao.png`, `pr1-liberado.png`, `pr1-merged.png` |
| PR #2 verde e mergeado | O PHPStan com baseline passa no CI | `pr2-verde.png`, `pr2-merged.png` |
| PR #3: erro proposital reprova, correção libera | A análise estática barra código novo com erro | `pr3-reprova-e-corrige.png`, `pr3-merged.png`, e `ci-phpstan-reprova.txt` e `phpstan-reprova-local.txt` em `a3/evidencias/` |
| Tela da regra em Settings > Branches (3 capturas) | A configuração descrita na seção 4 | `protecao-branch-1.png` a `-3.png` |

## 6. Como rodar localmente

No Docker do grupo (serviço `php-fpm`), na pasta do projeto:

```
docker compose -p a3-ea -f compose.a3.yaml exec php-fpm vendor/bin/phpstan analyse --memory-limit=1G
docker compose -p a3-ea -f compose.a3.yaml exec php-fpm vendor/bin/phpunit --configuration phpunit.xml --coverage-filter application/libraries --coverage-text --coverage-clover coverage/clover.xml
python a3/ci/verificar_cobertura.py coverage/clover.xml Availability.php 80
```

## 7. Limites conhecidos

- A cobertura medida é de **linhas**. A cobertura de desvios (branches), citada no Plano de Testes, ainda não é medida.
- O gate cobre só o `Availability.php`; o restante do código segue sem meta.
- O PHPStan está no nível 5 com baseline: os 151 erros herdados continuam lá, sem correção. A maior parte vem de propriedades dinâmicas do CodeIgniter que a ferramenta não enxerga.
- O `composer audit` aponta 1 aviso de segurança em uma dependência já existente no projeto; não foi tratado neste entregável.
- As actions do workflow geram avisos de descontinuação do Node 20.

## 8. Notas sobre as evidências

- `pr1-liberado.png` mostra duas aprovações (MusiMus1 e hzmotta) e, junto, um comentário "eu nao aprovo" de MusiMus1 na mesma revisão. O registro foi mantido como foi capturado; a aprovação formal é a que libera o merge, e o motivo do comentário deve ser explicado pelo autor ao apresentar esta evidência.
- Os prints de 18/11 a 19/11 mostram a meta de 24% e 30% da época. A meta atual é 80% (seção 3).
- O arquivo `gate-local-aprova-reprova.txt` usa as metas 80 e 96 porque o `clover.xml` local já tem a cobertura de 94,80%; a reprovação com a meta de 30% sobre 24,91% está no log do PR #1 (`pr1-gate-log.txt`).
