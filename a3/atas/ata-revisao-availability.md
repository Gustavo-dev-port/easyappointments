# Ata de Revisão por Pares – Availability

Código revisado: `application/libraries/Availability.php`, versão da `a3/base` no commit `8b8225fa` (Easy!Appointments 1.6.1); o arquivo segue idêntico na `a3/base` atual.
Os campos entre colchetes devem ser preenchidos por quem fez a revisão. Os achados abaixo vêm de leitura do código; o revisor deve conferir cada referência antes de assinar.

## 1. Objetivo

Realizar uma revisão por pares do módulo Availability, verificando a organização do código e os comportamentos relacionados ao cálculo dos horários disponíveis, à jornada de trabalho e à prevenção de conflitos de agendamento.

A revisão tem como finalidade identificar possíveis problemas, registrar observações e encaminhar correções quando necessário.

## 2. Checklist de revisão

Classificação: Conforme / Não conforme / Não verificado.

| Nº | Critério verificado | Resultado |
|----|---------------------|-----------|
| 1 | O cálculo considera os horários de funcionamento cadastrados para o profissional? | Não verificado [revisor] |
| 2 | Os intervalos e pausas cadastrados são respeitados? | Não verificado [revisor] |
| 3 | A duração do serviço é considerada no cálculo da disponibilidade? | Não verificado [revisor] |
| 4 | Os agendamentos existentes são considerados para evitar conflitos? | Não verificado [revisor] |
| 5 | Os períodos bloqueados ou indisponíveis são respeitados? | Não verificado [revisor] |
| 6 | A alteração de um agendamento evita conflito com o próprio registro? | Não verificado [revisor] |
| 7 | Existem verificações para parâmetros ou entradas inválidas? | Conforme em parte: as configurações de antecedência e de limite futuro passam por `is_numeric` com piso em 0 (linhas 596–597 e 636–637), mas o fuso do provedor não é validado (REV-02) e a data passada não é rejeitada pelo model (REV-04) |
| 8 | O código apresenta organização e clareza suficientes para manutenção? | Conforme, com ressalva (REV-03) |

Observação: o modelo de checklist não tem item para antecedência mínima e limite de agendamento futuro (RN11 e RN12). O que foi lido sobre essas regras está registrado nos achados.

## 3. Achados da revisão

| Identificação | Problema ou observação | Referência no código | Severidade | Encaminhamento |
|---|---|---|---|---|
| REV-01 | A antecedência mínima descarta o horário que cai exatamente em `agora + prazo`, porque a comparação usa `<=`. Com prazo de 120 minutos, o horário de 2h00 exatas é recusado. O plano de testes (CT08) espera que 2h00 seja aceito e 1h59 recusado. | `application/libraries/Availability.php`, linha 606, método `consider_book_advance_timeout` | Média | Decidir a regra de borda (aceitar ou recusar o horário exato), registrar a decisão em RN11/CT08 e cobrir com teste de valor limite. |
| REV-02 | O código lê `$provider['timezone']` direto, sem checar se a chave existe nem se o fuso é válido. Um provedor sem fuso gera exceção em vez de erro tratado. | `Availability.php`, linhas 594 e 634 | Baixa | Validar o parâmetro ou documentar a pré-condição. Cobrir com teste de entrada inválida. |
| REV-03 | O `secretaries_model` é carregado duas vezes seguidas no construtor. É redundância sem efeito, mas prejudica a clareza. | `Availability.php`, linhas 38 e 39 | Baixa (clareza) | Remover a linha duplicada em PR de limpeza. |
| REV-04 | A validação do agendamento não rejeita data no passado. O fluxo público de reserva filtra horários passados (pela disponibilidade e por `get_unavailable_dates`, que bloqueia dias anteriores a hoje). Porém `Appointments_model::validate` confere só formato das datas, duração mínima e existência dos registros. Qualquer caminho que chame `save()` sem passar pela checagem de disponibilidade pode gravar agendamento retroativo. O grupo observou em uso que o sistema permite marcar antes de hoje. | `application/models/Appointments_model.php`, linhas 80–140 (`validate`); `application/controllers/Booking.php`, linhas 622–660 (`check_datetime_availability`) e linha 844 | Média | Reproduzido em teste manual em 24/11/2026 (tela, data e captura: [grupo] — ver `Testes manuais`). Decidir se agendamento retroativo é permitido para administradores. Se não for, acrescentar guarda de data passada em `Appointments_model::validate` e um caso de teste de modelo ou E2E (partição inválida: data no passado), a numerar após conferência com o PDF v2.0 do plano de testes. |

Limite da revisão: os achados REV-01 a REV-04 vêm da leitura de `consider_book_advance_timeout`, `consider_future_booking_limit`, do construtor, de `Appointments_model::validate` e de `Booking::check_datetime_availability`. Os métodos `get_available_hours`, `get_available_periods`, `remove_breaks`, `remove_unavailability_events` e `consider_multiple_attendants` ainda precisam ser lidos pelo revisor (itens 1 a 6 do checklist).

## 4. Ações corretivas

| Ação necessária | Responsável | Prazo | Situação |
|---|---|---|---|
| Decidir a regra de borda da antecedência mínima e atualizar RN11/CT08 (REV-01) | [a definir pelo grupo] | [a definir] | Pendente |
| Confirmar onde o agendamento retroativo ocorre e decidir se é permitido (REV-04) | [a definir pelo grupo] | [a definir] | Pendente |
| Escrever teste de valor limite para REV-01 (o `BookingPolicyValidator` já cobre a regra do plano) e um caso de modelo ou E2E para REV-04 | [a definir pelo grupo] | [a definir] | Pendente |
| Validar o fuso do provedor ou documentar a pré-condição (REV-02) | [a definir pelo grupo] | [a definir] | Pendente |
| Remover o carregamento duplicado de `secretaries_model` (REV-03) | [a definir pelo grupo] | [a definir] | Pendente |
| Ler e classificar os itens 1 a 6 do checklist | [revisor] | 25/11/2026 | Pendente |

## 5. Conclusão

Resultado da revisão: Pendente [revisor: Aprovado / Aprovado com ajustes / Reprovado, após concluir os itens 1 a 6].

Resumo dos resultados: quatro achados registrados (duas severidades médias e duas baixas); itens 7 e 8 classificados; itens 1 a 6 ainda não verificados.

Correções realizadas ou pendentes: todas pendentes.

Evidência: código do commit `8b8225fa` da `a3/base`, nas linhas citadas. [acrescentar link do PR ou do commit que registrar esta ata]

Participantes: [revisor] e Julia.

Data de encerramento: [até 25/11/2026].
