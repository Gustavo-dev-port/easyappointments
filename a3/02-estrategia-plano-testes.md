# Entregável 2 — Estratégia e Plano de Testes (Módulo `Availability`)

## 1. Escopo e Justificativa de Risco
Em conformidade com a diretriz de profundidade nos módulos críticos, o esforço de verificação foi concentrado na biblioteca de cálculo de disponibilidade (`application/libraries/Availability.php`) e suas interações com `Appointments_model`, `Services_model` e `Providers_model` do **Easy!Appointments 1.6.1**[cite: 2, 6, 7]. Falhas neste módulo comprometem diretamente a operação do negócio ao gerar *overbooking* (conflito de horários) ou bloquear slots válidos de atendimento.

---

## 2. Pirâmide de Testes do Módulo
A distribuição da suíte automatizada segue a proporção **70% / 20% / 10%** para equilibrar velocidade de execução no pipeline de CI e confiabilidade:

* **Base — Testes Unitários (70%):** Execução isolada de regras puras de cálculo de janelas de tempo, validação de jornada (`working_plan`), exceções (`working_plan_exceptions`), pausas (`breaks`), tipos de disponibilidade (`flexible` vs. `fixed`) e limites de antecedência (`book_advance_timeout`), sem acesso ao banco de dados.
* **Meio — Testes de Integração (20%):** Validação da integração entre a classe `Availability` e a camada de persistência (MySQL), verificando sobreposição de agendamentos existentes, bloqueios de indisponibilidade (`is_unavailable`), capacidade simultânea (`attendants_number`) e ignorância do próprio registro durante edições (`$exclude_appointment_id`).
* **Topo — Testes de Aceitação / BDD (10%):** Cenários escritos em Gherkin validando o fluxo funcional de ponta a ponta na consulta pública de horários[cite: 5].

---

## 3. Plano de Testes Enxuto (Modelo IEEE 829)

* **Identificador:** `PT-EA-AVAIL-2026-02`
* **Objetos de Teste:** `Availability.php` (métodos `get_available_hours()`, `get_available_periods()` e cálculos auxiliares de intervalo), além dos parâmetros de configuração do sistema (`ea_settings`, `ea_user_settings`, `ea_services`).
* **Atributos de Qualidade (ISO 25010):**
  * *Adequação Funcional:* Completude e correção matemática dos horários disponibilizados perante as regras RN01 a RN12[cite: 8].
  * *Confiabilidade:* Ausência de agendamentos simultâneos incompatíveis (RN07) e tratamento seguro de bordas de horário[cite: 8].
  * *Integridade / Segurança Operacional:* Bloqueio estrito de agendamentos fora dos prazos de antecedência e limite futuro (RN11, RN12)[cite: 8].
* **Exclusões de Escopo:** Disparos de e-mail SMTP, sincronização externa via Google Calendar / CalDAV e renderização visual CSS.
* **Ambiente e Critérios de Entrada:** Ambiente containerizado (`compose.a3.yaml`) com PHP 8.2+, MySQL e PHPUnit configurados (`phpunit.xml`), utilizando massa de dados determinística (*fixtures* de profissional com expediente `08:00–18:00`, pausa `12:00–13:00` e serviço padrão de `60 minutos`).
* **Critérios de Saída / Aprovação:**
  1. 100% de aprovação nos casos de teste vinculados aos riscos críticos **RK01 a RK06**[cite: 8].
  2. Cobertura de ramos (*Branch Coverage*) $\ge 85\%$ na classe `Availability.php`.
  3. Zero regressões reportadas pela análise estática (`phpstan.neon`).

---

## 4. Matriz de Casos de Teste de Caixa Preta (Partição de Equivalência e Valor Limite)

Os casos abaixo derivam diretamente das regras de negócio (**RN01–RN12**) e riscos (**RK01–RK06**) mapeados pela equipe[cite: 5, 8]:

| ID | Regra / Risco | Técnica Aplicada | Pré-condições e Entradas | Resultado Esperado |
| :--- | :--- | :--- | :--- | :--- |
| **CT-01** | RN01, RN05 / RK01, RK04[cite: 8] | **Valor Limite** (Fim do expediente) | Jornada: `08:00–18:00`. Serviço: `60 min`. Consultar slots de borda: `16:59`, `17:00` e `17:01`. | `16:59` e `17:00` são exibidos; `17:01` é omitido (término às `18:01` ultrapassa o expediente). |
| **CT-02** | RN02 / RK02[cite: 8] | **Partição de Equivalência** (Dia inativo) | Profissional configurado com domingo (`sunday: null`) no `working_plan`. Consultar data correspondente a um domingo. | O sistema retorna lista vazia (`[]`) sem erros de execução. |
| **CT-03** | RN03 / RK05[cite: 8] | **Partição de Equivalência** (Exceção de agenda) | Jornada padrão `08:00–18:00`, mas data `2026-12-24` possui entrada em `working_plan_exceptions` das `08:00–12:00`. | Sistema sobrescreve a jornada padrão e exibe slots apenas até `11:00` (para serviço de 60 min). |
| **CT-04** | RN04 / RK02[cite: 8] | **Valor Limite** (Início e fim de pausa) | Pausa: `12:00–13:00`. Serviço: `30 min`. Avaliar slots: `11:30`, `11:31`, `12:30` e `13:00`. | `11:30` e `13:00` disponíveis; `11:31` (termina `12:01`) e `12:30` bloqueados. |
| **CT-05** | RN06 / RK01[cite: 8] | **Partição de Equivalência** (Tipo de disponibilidade) | Profissional configurado com `availabilities_type = fixed` vs. `flexible` para serviço de `45 min`. | Em `fixed`, gera slots a cada 45 min (`08:00`, `08:45`, `09:30`); em `flexible`, gera slots nos intervalos base do sistema. |
| **CT-06** | RN07 / RK03[cite: 8] | **Partição de Equivalência** (Conflito direto) | Agendamento existente das `14:00` às `15:00` (`is_unavailable = 0`). Novo cliente consulta slots de `60 min`. | Slot `14:00` é removido; slots adjacentes `13:00` e `15:00` permanecem disponíveis. |
| **CT-07** | RN08 / RK05[cite: 8] | **Partição de Equivalência** (Bloqueio administrativo) | Registro de indisponibilidade (`is_unavailable = 1`) das `09:00` às `11:00`. Consultar manhã. | Nenhum slot entre `09:00` e `10:59` é ofertado ao cliente. |
| **CT-08** | RN09 / RK03[cite: 8] | **Partição de Equivalência** (Reagendamento) | Editar agendamento existente (`id = 42`, horário `10:00–11:00`) informando `$exclude_appointment_id = 42`. | O horário `10:00` permanece visível e selecionável para o próprio agendamento em edição. |
| **CT-09** | RN10 / RK03[cite: 8] | **Valor Limite** (Capacidade `attendants_number`) | Serviço com `attendants_number = 2`. Testar com `1` agendamento às `10:00` e depois com `2` agendamentos às `10:00`. | Com 1 agendamento, `10:00` continua disponível; ao atingir 2 agendamentos, `10:00` torna-se indisponível. |
| **CT-10** | RN11 / RK06[cite: 8] | **Valor Limite** (Antecedência mínima) | Relógio atual: `10:00`. `book_advance_timeout = 60 min`. Testar slots: `10:59`, `11:00` e `11:01`. | `10:59` bloqueado; `11:00` (limite exato) e `11:01` disponíveis. |
| **CT-11** | RN12 / RK06[cite: 8] | **Valor Limite** (Limite futuro) | Data atual: `Dia D`. `future_booking_limit = 30 dias`. Consultar `Dia D+30` e `Dia D+31`. | `Dia D+30` retorna horários normalmente; `Dia D+31` retorna lista vazia. |

---

## 5. Análise de Caixa Branca e Roteiro de Execução

* **Critério de Cobertura:** **Cobertura de Ramos (*Branch Coverage*) $\ge 85\%$** medida via PHPUnit + PCOV/Xdebug.
* **Justificativa Técnica:** O algoritmo de disponibilidade possui diversas ramificações condicionais (presença de exceções de calendário, modo `fixed` vs. `flexible`, contagem de atendentes simultâneos e filtro de antecedência). A cobertura de linhas simples (*Statement Coverage*) ocultaria ramos `else` não exercitados (como o fallback quando não há exceção cadastrada); portanto, *Branch Coverage* é obrigatório para validar todas as decisões lógicas[cite: 5].
* **Roteiro de Execução:**
  1. Subir os containers da stack A3: `docker compose -f compose.a3.yaml up -d`
  2. Executar a suíte de testes unitários e de integração: `docker compose -f compose.a3.yaml exec php-fpm ./vendor/bin/phpunit --testsuite unit,integration`
  3. Gerar o relatório de cobertura HTML/Clover: `docker compose -f compose.a3.yaml exec php-fpm ./vendor/bin/phpunit --coverage-text --coverage-html a3/coverage-report`