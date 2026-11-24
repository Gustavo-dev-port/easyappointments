# Entregável 2 — Complemento Operacional ao Plano de Testes (`PT-EA-SQ04-2026` v2.0)

## 1. Alinhamento ao Plano Base e Escopo
Este documento operacionaliza a estratégia de testes unitários automatizados do módulo `Availability` (`application/libraries/Availability.php`), mantendo total aderência à numeração, metas e diretrizes definidas no Plano de Testes **`PT-EA-SQ04-2026` (v2.0)**:

* **Ambiente Alvo:** PHP 8.5 (container `php-fpm` via `compose.a3.yaml`), PHPUnit (`phpunit.xml`) e análise estática via PHPStan (`phpstan.neon`).
* **Massa Base Padrão (Fixtures):** Profissional com jornada `08:00–18:00`, pausa `12:00–13:00` e serviço de **30 minutos** (`duration = 30`, `slot_interval = 30`).
* **Metas de Cobertura (v2.0):** **80% de cobertura de linhas / 75% de cobertura de ramos (*branch coverage*)** no escopo priorizado.
* **Atributos ISO 25010 Priorizados:** Adequação Funcional, Confiabilidade e Integridade Operacional.

---

## 2. Pirâmide de Testes Aplicada ao Módulo
* **Base — Testes Unitários (`tests/Unit/Library/`):** Execução isolada dos métodos da classe `Availability` (`get_available_hours()`, `get_available_periods()`, `remove_breaks()`, `remove_unavailability_events()`, `generate_available_hours()`, `consider_book_advance_timeout()` e `consider_future_booking_limit()`), utilizando *stubs* para os modelos do CodeIgniter (`blocked_periods_model`, `working_plan_exceptions_model`, `appointments_model`, `unavailabilities_model`).
* **Topo — Testes de Aceitação (Behat):** Cenários comportamentais executados na pasta `features/` na branch dedicada do projeto.

---

## 3. Rastreabilidade de Casos de Teste Automatizados (`PT-EA-SQ04-2026` v2.0)

Os casos implementados em `tests/Unit/Library/AvailabilityRulesTest.php` exercitam diretamente os métodos reais de `Availability.php`:

| Caso | Regra / Risco | Técnica | Método Real Exercitado | Condição e Resultado Esperado |
| :--- | :--- | :--- | :--- | :--- |
| **CT01** | RN01, RN05 / RK01, RK04 | Valor Limite (Fim do expediente) | `get_available_hours()` | Jornada `08:00–18:00`, serviço `30 min` (`slot_interval = 30`). Primeiro slot `08:00`, último slot válido `17:30`; `18:00` omitido. |
| **CT02** | RN02 / RK02 | Partição de Equivalência (Dia inativo) | `get_available_hours()` | Dia configurado como `null` no JSON `working_plan` (domingo). Retorna lista vazia `[]`. |
| **CT03** | RN03 / RK05 | Partição de Equivalência (Exceção) | `get_available_hours()` | Data registrada em `working_plan_exceptions` (`09:00–11:00`) sobrescreve a jornada padrão, ofertando `['09:00', '09:30', '10:00', '10:30']`. |
| **CT04** | RN04 / RK02 | Valor Limite (Pausa `breaks`) | `get_available_hours()` | Pausa `12:00–13:00` com serviço de `30 min`: `11:30` e `13:00` disponíveis; `12:00` e `12:30` bloqueados. |
| **CT05** | RN06 / RK01 | Partição de Equivalência (`slot_interval`) | `get_available_hours()` | Configuração `slot_interval = 60` gera slots de hora em hora (`08:00`, `09:00`), omitindo `08:30`. |
| **CT06** | RN07, RN08 / RK03, RK05 | Partição de Equivalência (Conflito / Bloqueio) | `get_available_hours()` | Agendamento existente (`14:00–15:00`) bloqueia `14:00` e `14:30`; data bloqueada via `blocked_periods_model` (ou evento com `is_unavailability = true`) retorna `[]`. |
| **CT07** | RN11 / RK06 | Partição de Equivalência (Antecedência) | `consider_book_advance_timeout()` | Horário anterior ao limiar atual na data de hoje é removido; horários em data futura são preservados. *(Ver achado `REV-01` abaixo)*. |
| **CT08** | RN12 / RK06 | Valor Limite (Limite futuro) | `consider_future_booking_limit()` | Limite padrão de `90 dias`: data a `+95 dias` retorna `[]`; data a `+10 dias` mantém os slots disponíveis. |
| **CT10** | Segurança / Validação | Partição Inválida (Formato de data) | `get_available_periods()` | Data fora do padrão `Y-m-d` (`26/08/2026`) lança `InvalidArgumentException`. |

### Registro de Achado Técnico — `REV-01` (Borda da RN11)
* **Descrição:** Na linha 606 de `application/libraries/Availability.php` (`consider_book_advance_timeout`), a comparação utiliza operador menor ou igual (`$available_hour->getTimestamp() <= $threshold->getTimestamp()`), rejeitando agendamentos no minuto exato do limite de antecedência (ex: exatas 2 horas).
* **Tratamento:** Os testes unitários desta suíte validam o comportamento vigente da biblioteca sem mascarar a divergência. A validação de política de borda exata da RN11 é delegada ao componente `BookingPolicyValidator` (branch `a3/feat-booking-policy`).

---

## 4. Roteiro de Execução e Verificação de Cobertura

1. **Executar a suíte unitária:**
   ```bash
   docker compose -f compose.a3.yaml exec php-fpm ./vendor/bin/phpunit --testsuite Unit