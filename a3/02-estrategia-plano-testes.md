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

* **`REV-01` (Borda `<=` na RN11 — `Availability::consider_book_advance_timeout`):** O código legado de `Availability.php` compara o horário do slot com `<=` em relação ao limite de antecedência mínima, recusando o horário exato da borda (`2h00`), enquanto a especificação do **CT08** determina aceitar o horário exato de `2h00` (`<`). A regra corrigida foi isolada e validada via TDD no `BookingPolicyValidator`.
* **`REV-04` (Ausência de bloqueio defensivo para data passada em `Availability::get_available_hours`):** O método `get_available_hours()` calcula a grade diária de horários a partir do `working_plan` e depende exclusivamente de `consider_book_advance_timeout()` (quando `book_advance_timeout > 0`) para filtrar horários já transcorridos; caso o timeout seja `0` ou omitido, datas/horários no passado não são rejeitados explicitamente na camada de disponibilidade.

---

## 4. Roteiro de Execução Local e Verificação de Cobertura

Execute os comandos abaixo dentro do ambiente Docker do projeto (`compose.a3.yaml`):

```bash
# 1. Executar a suíte unitária do módulo Availability
docker compose -f compose.a3.yaml exec php-fpm ./vendor/bin/phpunit tests/Unit/Library/AvailabilityRulesTest.php

# 2. Gerar relatório Clover XML e validar o Quality Gate de cobertura
docker compose -f compose.a3.yaml exec php-fpm ./vendor/bin/phpunit --testsuite Unit --coverage-clover a3/ci/clover.xml
python a3/ci/verificar_cobertura.py