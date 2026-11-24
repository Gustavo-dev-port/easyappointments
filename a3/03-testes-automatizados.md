# Testes automatizados — TDD, BDD e suíte do Availability

Entregável 3 · Responsáveis: Motta e Gustavo · Atualizado em 24/11/2026
Fork: `Gustavo-dev-port/easyappointments` · Branch de integração: `a3/base`

## 1. Visão geral

| Camada | O que cobre | Onde está |
|---|---|---|
| TDD | Regra RN11 (antecedência mínima) em um componente novo, `BookingPolicyValidator` | `application/libraries/BookingPolicyValidator.php`, `tests/Unit/Library/BookingPolicyValidatorTest.php` |
| BDD (Behat) | Os cenários CT08a e CT08b da RN11, em Gherkin | `features/booking_policy.feature`, `features/bootstrap/FeatureContext.php` |
| Regras do `Availability` | RN01 a RN12 chamando a classe real, com modelos simulados | `tests/Unit/Library/AvailabilityRulesTest.php` (11 testes) |
| Cobertura do `Availability` | Horas, limites de antecedência e de futuro, períodos e pausas | `AvailabilityHoursTest` (20), `AvailabilityLimitsTest` (20), `AvailabilityPeriodsTest` (33), com os fakes em `AvailabilityTestCase` |

O plano, os casos de teste e a rastreabilidade RN → CT → teste estão em `a3/02-estrategia-plano-testes.md`.

## 2. TDD do `BookingPolicyValidator` (RN11)

Regra: o agendamento precisa ser feito com no mínimo 120 minutos de antecedência. Exatamente 2h00 é aceito; 1h59 é recusado (CT08 do plano v2.0).

| Fase | Commit | Mensagem |
|---|---|---|
| Red | `d2e56787` | test: RN11 recusa antecedencia de 1h59 (red) |
| Green | `88a1fc86` | feat: RN11 valida antecedencia minima de agendamento (green) |
| Borda | `585c5d4f` | test: RN11 aceita antecedencia de exatamente 2h (borda do CT08) |
| Refactor | `28a6b302` | refactor: extrai constante RULE_MIN_ADVANCE e documenta borda da RN11 |

Os quatro commits fazem parte do histórico da `a3/base` (merge `d3013d5a`, PR #6).

**Decisões de projeto**
- O "agora" entra como parâmetro (`validate(DateTimeImmutable $start, DateTimeImmutable $now)`), então o teste é determinístico e não depende do relógio.
- O retorno é a lista dos códigos de regra violados (por exemplo `['RN11']`), o que dá rastreabilidade com o plano de testes.
- A antecedência é em minutos, a mesma unidade da configuração `book_advance_timeout`.

**Mutação manual (evidência para a inovação "teste além da cobertura").** Trocar `<` por `<=` em `validate()` fez `testAcceptsBookingWithExactlyTwoHoursAdvance` falhar (esperado `[]`, recebido `['RN11']`), enquanto o teste de 1h59 continuou passando. O mutante foi morto pelo teste de borda e a troca foi revertida.

**Limitação.** O `BookingPolicyValidator` ainda não é chamado por `Booking` nem por `Availability`. Ele demonstra o ciclo TDD e a regra do plano; o sistema continua com o comportamento atual (`<=`, que recusa 2h00 exatas). Ver achado `REV-01` na ata de revisão e no plano de testes.

## 3. BDD com Behat

`features/booking_policy.feature` (Gherkin em português) descreve dois cenários:

- **CT08a:** com antecedência mínima de 120 minutos e agora às 08:00, um horário às 09:59 é recusado pela regra RN11.
- **CT08b:** no mesmo contexto, um horário às 10:00 (exatamente 2 horas) é aceito.

Os passos ficam em `features/bootstrap/FeatureContext.php` e chamam o `BookingPolicyValidator`. O Behat é dependência de desenvolvimento no `composer.json` e roda no CI, no passo "Run Behat (BDD)" do job `qualidade`.

## 4. Suíte do `Availability`

- **`AvailabilityRulesTest`** (11 testes): uma ou mais verificações por regra (RN01/RN05, RN02, RN03, RN04, RN06, RN07/RN08, RN09, RN10, RN11, RN12 e formato de data inválido), chamando `get_available_hours`, `get_available_periods`, `consider_book_advance_timeout` e `consider_future_booking_limit`.
- **`AvailabilityHoursTest`, `AvailabilityLimitsTest` e `AvailabilityPeriodsTest`**: casos de valor limite e de partição para horas, limites e períodos. Usam `AvailabilityTestCase`, que simula os modelos do banco com classes anônimas.

### Defeitos reais encontrados pelos testes

Quatro testes estão marcados `known-bug` e ficam ignorados por padrão (`RUN_KNOWN_BUGS=1` os executa e os vê falhar contra o código atual):

| Defeito | Onde |
|---|---|
| Pausas sobrepostas (10:00–10:30 e 09:00–11:00) deixam 09:00–10:00 livre | `Availability.php`, linhas 424–440 |
| Agendamento que coincide com o período deixa um período vazio 17:00–17:00 em vez de removê-lo | `Availability.php`, linhas 474–497 |
| Data com quebra de linha final (`2030-01-07\n`) passa na validação do formato | `Availability.php`, linha 350 |
| Limite de agendamento futuro interpreta a data no fuso padrão do PHP, e não no do profissional (hipótese em teste) | `Availability.php`, linha 643 |

## 5. Resultados medidos

| Execução | Testes | Cobertura de linhas do `Availability.php` |
|---|---|---|
| Baseline (`a3-baseline`) | 95 testes, 140 asserções | 24,91% (67 de 269), 2 de 9 métodos |
| GitHub Actions, PR #7 (24/11, 20:34 UTC) | 108 testes, 171 asserções, 7 depreciações | 89,59% (241 de 269), 5 de 9 métodos |
| Local, com os testes de horas, limites e períodos (24/11, 20:15 UTC) | — | 94,80% (255 de 269) |

Evidências: `a3/evidencias/testes-unitarios-e2-e3/execucao-pr7.txt`, `a3/evidencias/baseline-local/` e `a3/evidencias/pipeline/gate-local-aprova-reprova.txt`.

## 6. Como executar

```
docker compose -p a3-ea -f compose.a3.yaml exec php-fpm vendor/bin/phpunit --configuration phpunit.xml
docker compose -p a3-ea -f compose.a3.yaml exec php-fpm vendor/bin/phpunit --group RN11
docker compose -p a3-ea -f compose.a3.yaml exec -e RUN_KNOWN_BUGS=1 php-fpm vendor/bin/phpunit --group known-bug
docker compose -p a3-ea -f compose.a3.yaml exec php-fpm vendor/bin/behat --no-interaction
```

## 7. Pendências e limites

- Prints da saída do ciclo red (falha) e green (passa) do TDD, e da execução do Behat no ambiente local.
- Integrar o `BookingPolicyValidator` ao fluxo de agendamento, ou alinhar o plano a `<=` (decisão do grupo).
- Teste de modelo ou E2E para o achado `REV-04` (agendamento em data passada aceito em `Appointments_model::validate`).
- Cobertura de desvios (branches) não é medida; o PHPUnit oferece `--path-coverage` para avaliar.
- A cobertura geral de `application/libraries` é 21,91%: o esforço está concentrado no `Availability`, o módulo de maior risco.
