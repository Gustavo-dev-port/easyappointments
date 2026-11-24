# language: pt
Funcionalidade: Antecedência mínima para agendar (RN11)
  Para evitar agendamentos de última hora
  O sistema deve exigir uma antecedência mínima de 2 horas

  Cenário: CT08a - Recusa o horário com menos de 2 horas de antecedência
    Dado que a antecedência mínima é de 120 minutos
    E que agora são 08:00 do dia 01/12/2026
    Quando o cliente escolhe um horário às 09:59
    Então o agendamento é recusado pela regra "RN11"

  Cenário: CT08b - Borda o horário com exatamente 2 horas de antecedência
    Dado que a antecedência mínima é de 120 minutos
    E que agora são 08:00 do dia 01/12/2026
    Quando o cliente escolhe um horário às 10:00
    Então o agendamento é aceito