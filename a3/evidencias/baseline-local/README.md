# Baseline local do Availability — evidências da execução inicial

Entregável 1 (Plano de Qualidade, §6) e Entregável 6 (métricas: EV-M-001, 003, 004 e 005) · Easy!Appointments 1.6.1 (SHA `d30cba6d`) · branch `a3/base`

**Ambiente:** Docker (`compose.a3.yaml`, serviço `php-fpm`), PHP 8.5.13, PHPUnit 12.5.13, Xdebug 3.5.3, Python 3.15.2 (gate), Node 26.13.1 / npm 11.20.0.
**Capturas feitas em 24/11/2026**, entre 00:55 e 01:33 (horário de Brasília). Os números reproduzem o baseline registrado em 18/11/2026 (`a3/registros/cobertura_baseline.txt`).

## Resultado

| Verificação | Resultado | Figura |
|---|---|---|
| PHPUnit | 95 testes, 140 asserções, 0 falhas, 7 depreciações | 1 |
| Xdebug (necessário para cobertura) | instalado, versão 3.5.3 | 2 |
| Cobertura geral (`application/libraries`) | 12,90% das linhas (256 de 1985) | 3 |
| Cobertura do `Availability.php` | 24,91% das linhas (67 de 269); 22,22% dos métodos (2 de 9) | 3 |
| Quality gate de cobertura (meta 24%) | Aprovado | 4 |
| PHPStan (nível 5) | Nenhum erro novo; os 151 apontamentos herdados ficam em `phpstan-baseline.neon` | 5 |
| Dependências npm (`npm ci`) | 3 vulnerabilidades (1 moderada, 2 altas) | 6 |

## Figuras

**Figura 1 — Execução dos testes automatizados** (`01-phpunit-95-testes.png`)
`phpunit --configuration phpunit.xml`: 95 testes, 140 asserções e 7 avisos de depreciação. Os avisos não impedem a aprovação. Tempo da suíte sem cobertura: 1,295 s (na execução de 18/11 foram 0,962 s).

**Figura 2 — Xdebug instalado** (`02-xdebug-instalado.png`)
Saída de `php -m` no container, com `Xdebug` listado em "Zend Modules". É ele que permite medir cobertura.

**Figura 3 — Cobertura e arquivo Clover** (`03-cobertura-phpunit-clover.png`)
Execução com `XDEBUG_MODE=coverage`, `--coverage-filter application/libraries` e `--coverage-clover coverage/clover.xml`. Gerou o relatório Clover e os percentuais da tabela acima. Tempo com cobertura: 1,638 s.

**Figura 4 — Quality gate de cobertura** (`04-gate-cobertura-local.png`)
`python a3/ci/verificar_cobertura.py coverage/clover.xml Availability.php 24`: 67/269 linhas = 24,91%, meta 24%, "Quality gate aprovado".

**Figura 5 — Análise estática** (`05-phpstan-local.png`)
`phpstan analyse --memory-limit=1G` (configuração `phpstan.neon`): "[OK] No errors". Isso significa nenhum erro **novo**; o baseline de 151 apontamentos continua ativo.

**Figura 6 — Instalação das dependências do front-end** (`06-npm-ci.png`)
`npm ci` instalou 457 pacotes e relatou 3 vulnerabilidades (1 moderada, 2 altas). É a origem do valor da métrica "Vulnerabilidades npm".

## Build do front-end
O `npm run build` é executado pelo job `frontend-build` do CI, que ficou verde nas execuções registradas (ver `a3/evidencias/ci-2-verde.png`).

## Limites desta evidência
- O detalhamento por pacote das vulnerabilidades (`npm audit`) ainda não foi salvo.
- A cobertura medida é de linhas. O PHPUnit oferece `--path-coverage`, a avaliar para a meta de branches do Plano de Testes.
- O empacotamento de release do projeto original (`build.sh`, com `composer install` em `build/` e `cleanup-vendor`) não foi executado; só será necessário se a release `a3-entrega` levar um pacote `.zip`.
