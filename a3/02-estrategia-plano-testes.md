# Complemento Operacional ao Plano de Testes (`PT-EA-SQ04-2026` v2.0) — Entregável 2

**Projeto:** Easy!Appointments 1.6.1  
**Módulo Alvo:** `application/libraries/Availability.php`  
**Stack de Execução:** PHP 8.5 + PHPUnit 12.5 + Xdebug 3.5 + PHPStan (`compose.a3.yaml`)

---

## 1. Escopo e Estratégia de Testes

Este documento complementa o Plano de Testes principal (`PT-EA-SQ04-2026` v2.0) com a rastreabilidade técnica da suíte unitária automatizada em `tests/Unit/Library/AvailabilityRulesTest.php` e a integração com o Quality Gate de CI (`.github/workflows/ci.yml`).

- **Testes Unitários (PHPUnit):** Exercitam diretamente os métodos reais de `Availability.php` (`get_available_hours`, `get_available_periods`, `consider_book_advance_timeout` e `consider_future_booking_limit`) isolados de banco de dados via *stubs* de `CI_Controller` e modelos CI (`appointments_model`, `unavailabilities_model`, `blocked_periods_model`, `working_plan_exceptions_model`).
- **Testes de Aceitação (BDD / Behat):** Mantidos na branch `a3/test-behat-booking-policy` para validação dos fluxos de política de agendamento sem duplicação em `tests/features/`.
- **Metas de Cobertura (`PT-EA-SQ04-2026` v2.0):** Meta global do módulo alvo de **80% de linhas** e **75% de branches**, com piso progressivo travado no Quality Gate do CI (`META_COBERTURA_AVAILABILITY`).

---

## 2. Matriz de Rastreabilidade (`RN01–RN12` ↔ `CT01–CT10`)

A numeração abaixo segue exatamente a tabela do Plano de Testes `PT-EA-SQ04-2026` v2.0:

| Regra | Descrição da Regra de Negócio | Caso de Teste | Método de Teste (`AvailabilityRulesTest.php`) | Status |
| :--- | :--- | :--- | :--- | :--- |
| **RN01 / RN05** | Grade de atendimento dentro do `working_plan` e respeito à `duration` do serviço (padrão 30 min) | **CT01** | `testGetAvailableHoursRespectsWorkingPlanAndServiceDuration` | Coberto |
| **RN02** | Dias sem expediente configurado (`null` no `working_plan`) retornam lista vazia | **CT02** | `testGetAvailableHoursReturnsEmptyArrayOnNonWorkingDay` | Coberto |
| **RN03** | Exceções pontuais de calendário (`working_plan_exceptions`) sobrescrevem o plano semanal | **CT03** | `testGetAvailableHoursAppliesWorkingPlanExceptionForSpecificDate` | Coberto |
| **RN04** | Pausas e intervalos cadastrados (`breaks`) são excluídos dos horários disponíveis | **CT04** | `testGetAvailableHoursExcludesConfiguredBreakPeriod` | Coberto |
| **RN06** | Espaçamento entre horários respeita o campo `slot_interval` configurado no serviço | — | `testGetAvailableHoursRespectsCustomSlotInterval` | Coberto |
| **RN07** | Horários ocupados por agendamentos existentes são bloqueados | **CT05** | `testGetAvailableHoursBlocksExistingAppointmentsUnavailabilitiesAndBlockedDates` | Coberto |
| **RN08** | Períodos de indisponibilidade (`is_unavailability = true`) e datas inteiras bloqueadas (`blocked_periods`) impedem oferta | **CT06** | `testGetAvailableHoursBlocksExistingAppointmentsUnavailabilitiesAndBlockedDates` | Coberto |
| **RN09** | Edição/reagendamento ignora o próprio `exclude_appointment_id` sem gerar falso conflito | **CT07** | `testGetAvailableHoursExcludesCurrentAppointmentIdWhenRescheduling` | Coberto |
| **RN11** | Antecedência mínima (`book_advance_timeout`) bloqueia horários passados ou dentro da janela mínima | **CT08** | `testConsiderBookAdvanceTimeoutFiltersPastAndKeepsFutureSlots` | Coberto |
| **RN10** | Serviços com múltiplos atendentes simultâneos (`attendants_number > 1`) mantêm vaga enquanto houver capacidade | **CT09** | `testGetAvailableHoursRespectsMultipleAttendantsCapacity` | Coberto |
| **RN12** | Limite máximo de agendamento futuro (`future_booking_limit`) bloqueia datas além da janela permitida | **CT10** | `testConsiderFutureBookingLimitBlocksDatesBeyondLimit` | Coberto |

---

## 3. Achados Técnicos Registrados na Revisão

* **`REV-01` (Borda `<=` na RN11 — `Availability::consider_book_advance_timeout`):** Em `application/libraries/Availability.php` (linha ~606) o slot é removido quando `$available_hour->getTimestamp() <= $threshold->getTimestamp()`, ou seja, o horário exatamente no limite de antecedência (ex.: 2h00) é **recusado**. O CT08 do plano v2.0 determina que 2h00 exatas sejam **aceitas** (`<`). A divergência é documentada pelo comportamento atual no teste `AvailabilityLimitsTest::testTimeoutSlotExactlyAtTheThresholdIsRemoved` (PR #9). A regra conforme o plano foi implementada com TDD em um componente isolado, `BookingPolicyValidator` (`application/libraries/BookingPolicyValidator.php`). O ciclo red → green → refactor está preservado no histórico da `a3/base` (merge `d3013d5a`): red `d2e56787`, green `88a1fc86`, borda `585c5d4f` e refactor `28a6b302`. **Limitação:** o `BookingPolicyValidator` ainda **não é chamado** por `Booking` nem por `Availability`; o defeito segue ativo no sistema até a integração (ou até o plano ser ajustado para `<=`). Decisão pendente do grupo: corrigir o código ou alinhar o plano.
* **`REV-04` (Agendamento em data passada aceito ao salvar — `Appointments_model::validate`):** Em teste manual (24/11/2026) o painel permitiu salvar um agendamento em data anterior a hoje. A camada de disponibilidade **não** é a origem: `consider_book_advance_timeout()` roda sempre (timeout `0` quando a configuração está ausente) e remove horários menores ou iguais a "agora", o que é verificado por `AvailabilityRulesTest::testConsiderBookAdvanceTimeoutFiltersPastAndKeepsFutureSlots` e `AvailabilityLimitsTest::testTimeoutOfZeroOnlyRemovesTheCurrentMinuteAndThePast` (PR #9). A origem é `Appointments_model::validate()` (`application/models/Appointments_model.php`), que valida campos obrigatórios e o formato das datas, mas **não rejeita `start_datetime` no passado**; o fluxo do painel não passa por `Availability`. Teste unitário de biblioteca não cobre esse caso: é necessário teste de modelo ou E2E (CT adicional, a numerar após conferência com o PDF v2.0). Evidência: tela, data e captura registradas na ata de revisão por pares.

---

## 4. Roteiro de Execução Local e Verificação de Cobertura

Execute os comandos abaixo dentro do ambiente Docker do projeto (`compose.a3.yaml`):

```bash
# 1. Executar a suíte unitária do módulo Availability
docker compose -f compose.a3.yaml exec php-fpm ./vendor/bin/phpunit tests/Unit/Library/AvailabilityRulesTest.php

# 2. Gerar relatório Clover XML e validar o Quality Gate de cobertura
docker compose -f compose.a3.yaml exec php-fpm ./vendor/bin/phpunit --testsuite Unit --coverage-clover a3/ci/clover.xml
python a3/ci/verificar_cobertura.py
```
