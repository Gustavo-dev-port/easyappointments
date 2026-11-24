# Testes manuais (24/11/2026)

Testes de aceitação feitos à mão no ambiente local (`localhost:8088`, Docker, Easy!Appointments 1.6.1), com serviço "Consulta de Avaliação" e dados fictícios (João Silva, Maria Oliveira). As capturas foram recortadas para tirar a barra de abas do navegador.

| Cenário | Regra relacionada | Resultado | Imagem |
|---|---|---|---|
| Agendamento pela página pública (serviço, atendente, data e hora, dados do cliente) | RN01, RN05 (grade de horários) | Agendamento registrado com sucesso | `01-agendamento-sucesso.png` |
| Prevenção de sobreposição: o horário já agendado não aparece mais na lista | RN07 (conflito) | Horário de 9h ausente na lista do mesmo dia | `02-prevencao-sobreposicao.png` |
| Edição de um agendamento pelo painel (observação alterada) | RN09 (edição sem falso conflito) | Alteração salva | `03-edicao-agendamento.png` |
| Exclusão de um agendamento | — | Registro deixa de aparecer na agenda | `04-exclusao-agendamento.png` |
| Liberação do horário após a exclusão | RN07 | O horário de 9h volta a aparecer na página pública | `05-horario-liberado.png` |

**Achado fora desta tabela (REV-04):** ao salvar um agendamento com data anterior a hoje o sistema não bloqueou. Tela, data e captura ainda precisam ser registradas pelo grupo (ver `a3/atas/ata-revisao-availability.md`).
