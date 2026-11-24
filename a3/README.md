# Trabalho A3 — Gestão e Qualidade de Software

Fork do [Easy!Appointments](https://github.com/alextselegidis/easyappointments) (agendador web em PHP, CodeIgniter 3) usado como objeto de estudo de qualidade de software. O trabalho mede o estado do código, monta um pipeline com quality gate e melhora a cobertura de testes do módulo mais crítico, o cálculo de horários (`application/libraries/Availability.php`).

**Grupo:** Gustavo, Julia, Motta e Art.

## Versão fixada

| Item | Valor |
|---|---|
| Projeto original | `alextselegidis/easyappointments` |
| Versão | **1.6.1** (tag `1.6.1`, commit `d30cba6d`) |
| Branch principal do trabalho | `a3/base` (criada a partir da 1.6.1) |
| Remote de trabalho | `grupo` (este fork). O push do `origin` fica bloqueado de propósito. |

A `main` do fork fica como está, para não misturar com o código fixado. Todo o trabalho entra na `a3/base`.

## Como subir o ambiente

Pré-requisitos: Git, Docker com Compose e Python 3 (só para rodar o gate de cobertura fora do container; no Windows o comando é `python`). Os comandos abaixo foram escritos para o **PowerShell** e também funcionam no Bash, exceto onde indicado.

**1. Configure as quebras de linha antes de baixar o código.** O repositório usa LF. No Windows, a conversão automática para CRLF marca arquivos inteiros como alterados e quebra scripts do container.

```bash
git config --global core.autocrlf false
git config --global core.eol lf
```

Se já clonou antes disso, rode os mesmos comandos sem `--global` dentro da pasta do projeto e refaça o checkout dos arquivos.

**2. Clone e entre na branch do trabalho.**

```bash
git clone https://github.com/Gustavo-dev-port/easyappointments.git
cd easyappointments
git switch a3/base
```

**3. Defina as senhas locais do banco.** O `compose.a3.yaml` exige as duas variáveis. Use `password` na senha do usuário, que é o valor que o `config-sample.php` já espera.

PowerShell:
```powershell
$env:A3_DB_PASSWORD = "password"
$env:A3_ROOT_PASSWORD = "root"
```
Bash:
```bash
export A3_DB_PASSWORD=password A3_ROOT_PASSWORD=root
```

**4. Crie o `config.php`** (arquivo ignorado pelo Git) e ajuste a URL para a porta publicada pelo nginx:

```bash
copy config-sample.php config.php 
# em config.php: const BASE_URL = 'http://localhost:8088';
```
No Linux/macOS, use `cp config-sample.php config.php`.

**5. Suba os containers.**

```bash
docker compose -p a3-ea -f compose.a3.yaml up -d --build
```

Serviços: aplicação em <http://localhost:8088> e caixa de e-mails (Mailpit) em <http://localhost:8025>.

**6. Instale as dependências do PHP com o tempo limite do Composer desligado.** O container tenta rodar `composer install` sozinho na primeira subida, mas o limite padrão de 300 s pode estourar e deixar a pasta `vendor/` pela metade. Rode você mesmo, de forma repetível:

```bash
docker compose -p a3-ea -f compose.a3.yaml exec -e COMPOSER_PROCESS_TIMEOUT=0 php-fpm composer install --no-interaction
```

Se uma instalação anterior falhou, apague a pasta `vendor/` antes de repetir.

Para derrubar o ambiente: `docker compose -p a3-ea -f compose.a3.yaml down` (acrescente `-v` para apagar o banco).

## Como rodar os testes e a cobertura

Os testes e o PHPStan rodam dentro do container `php-fpm`. Cada comando abaixo está completo: copie e cole.

| O que | Comando |
|---|---|
| Testes | `docker compose -p a3-ea -f compose.a3.yaml exec php-fpm vendor/bin/phpunit --configuration phpunit.xml` |
| Cobertura (gera `coverage/clover.xml`) | `docker compose -p a3-ea -f compose.a3.yaml exec -e XDEBUG_MODE=coverage php-fpm vendor/bin/phpunit --configuration phpunit.xml --coverage-filter application/libraries --coverage-text --coverage-clover coverage/clover.xml` |
| Gate de cobertura do `Availability` (roda no seu computador, não no container) | `python a3/ci/verificar_cobertura.py coverage/clover.xml Availability.php 80` (no Linux/macOS: `python3`) |
| Análise estática | `docker compose -p a3-ea -f compose.a3.yaml exec php-fpm vendor/bin/phpstan analyse --memory-limit=1G` |

O CI (`.github/workflows/ci.yml`) roda esta mesma sequência em todo pull request para a `a3/base`. O job `qualidade` falha se um teste quebrar, se a cobertura do `Availability` ficar abaixo da meta ou se o PHPStan achar erro novo. A meta fica na variável `META_COBERTURA_AVAILABILITY` do workflow.

## Métricas

| Métrica | Baseline (`a3-baseline`) | Meta |
|---|---|---|
| Testes | 95 testes, 140 asserções | não regredir (108 no PR #7; mais com os testes de horas, limites e períodos) |
| Cobertura de linhas, `application/libraries` | 12,90% (256 de 1985) | acompanhar |
| Cobertura de linhas, `Availability.php` | 24,91% (67 de 269) | 80% (meta do plano de testes); medido: 89,59% no PR #7 |
| Cobertura de métodos, `Availability.php` | 22,22% (2 de 9) | acompanhar |
| PHPStan (nível 5, `application/libraries`) | 151 apontamentos no baseline | nenhum erro novo |

## Gestão de configuração

### Estratégia de branching

Adotamos **trunk-based development**, com `a3/base` como tronco.

- Somos 4 pessoas e temos cerca de 2 semanas. Branches longas gerariam conflito e integração tardia, e Git Flow (`develop`, `release/*`, `hotfix/*`) seria burocracia sem benefício.
- Cada tarefa vira uma branch curta (1 a 3 dias) e entra por pull request. O histórico mostra quem fez o quê.
- Ninguém faz push direto na `a3/base`. A regra de proteção exige pull request, o check `qualidade` verde e 1 aprovação de outra pessoa.
- Merge por **merge commit ou rebase**, para preservar os commits do histórico (por exemplo, os do ciclo TDD red → green → refactor). Não usamos squash nos PRs que carregam evidência de processo. A branch é apagada depois.

Nome da branch: `a3/<tipo>-<assunto-curto>`, por exemplo `a3/docs-readme` ou `a3/test-availability`.

### Convenção de commits

`<tipo>: <o que mudou e por quê>`, em português, com o motivo da mudança e não só a ação.

| Tipo | Uso |
|---|---|
| `feat` | funcionalidade ou ajuste funcional |
| `test` | testes e cobertura |
| `docs` | README, relatórios, evidências |
| `refactor` | mudança sem alterar comportamento |
| `chore` | CI, configuração, dependências |

Os commits anteriores ao PR #4 (como `prova:` e `corrige:`) são de antes da convenção e não foram reescritos, porque a `a3/base` já estava publicada.

### Pull requests

Todo PR usa o modelo em `.github/pull_request_template.md` e cita o **entregável** a que pertence. Confira sempre a base: `Gustavo-dev-port/easyappointments` na branch `a3/base` (o GitHub sugere o repositório original por padrão).

### Tags e releases

| Tag | Quando | Conteúdo |
|---|---|---|
| `1.6.1` | versão fixada do Easy!Appointments | ponto de partida do trabalho |
| `a3-baseline` | medições iniciais registradas | release com as notas e os números do baseline |
| `a3-entrega` | 02/12 | release final com as métricas finais |

## Índice dos entregáveis

| Entregável | Onde está |
|---|---|
| Pipeline com quality gate | `.github/workflows/ci.yml`, `a3/ci/verificar_cobertura.py` |
| Análise estática com baseline | `phpstan.neon`, `phpstan-baseline.neon` |
| Documento do pipeline (Entregável 4) | `a3/04-pipeline.md` |
| Evidências do pipeline (gate bloqueando e liberando, PHPStan reprovando, proteção da branch) | `a3/evidencias/pipeline/` |
| Ambiente de laboratório | `compose.a3.yaml` |
| Gestão de configuração (Entregável 5) | este arquivo e `a3/05-gestao-configuracao.md` |
| Plano de qualidade e SQA | `a3/01-plano-qualidade.md` |
| Revisão por pares e auditoria do processo | `a3/atas/` |
| Estratégia e plano de testes | `a3/02-estrategia-plano-testes.md` |
| Testes automatizados (Entregável 3) | `a3/03-testes-automatizados.md`; código em `tests/Unit/`, `application/libraries/BookingPolicyValidator.php`, `features/` |
| Testes manuais (capturas) | `a3/evidencias/testes-manuais/` |
| Versão e baseline | `a3/registros/`, `a3/evidencias/baseline-local/` |
| Execução dos testes e cobertura (PR #7) | `a3/evidencias/testes-unitarios-e2-e3/` |
| Demais entregáveis | _a preencher pelo grupo_ |
