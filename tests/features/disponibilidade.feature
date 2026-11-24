# language: pt
Funcionalidade: Cálculo de disponibilidade de horários para agendamento
  Como um cliente ou atendente do Easy!Appointments
  Quero consultar apenas horários realmente livres e respeitando as regras da agenda
  Para evitar conflitos de atendimento e agendamentos inválidos

  Contexto:
    Dado que o profissional "João Silva" possui jornada de segunda a sexta das "08:00" às "18:00"
    E possui uma pausa configurada das "12:00" às "13:00"
    E o serviço "Consulta Padrão" possui duração de 60 minutos e 1 atendente configurado

  Cenário: Exibir somente horários dentro do expediente e compatíveis com a duração (RN01, RN05)
    Dado que não existem agendamentos para o dia "2026-12-10"
    Quando o cliente consultar os horários disponíveis para o serviço "Consulta Padrão"
    Então o sistema deve disponibilizar o horário "08:00"
    E o último horário disponibilizado deve ser "17:00"
    E não deve apresentar horários a partir das "17:01"

  Cenário: Não disponibilizar horários que invadam pausas cadastradas (RN04)
    Dado que o cliente selecionou o serviço "Consulta Padrão" de 60 minutos
    Quando consultar os horários disponíveis para o dia "2026-12-10"
    Então os horários "11:30" e "12:00" não devem ser exibidos
    E o horário "13:00" deve estar disponível

  Cenário: Impedir conflito com agendamentos já existentes (RN07)
    Dado que já existe um agendamento confirmado para "João Silva" no dia "2026-12-10" das "14:00" às "15:00"
    Quando outro cliente consultar os horários disponíveis para o mesmo dia e serviço
    Então o horário "14:00" não deve ser exibido como disponível
    E os horários "13:00" e "15:00" devem permanecer disponíveis

  Cenário: Respeitar períodos bloqueados e indisponibilidades (RN03, RN08)
    Dado que existe um período cadastrado como indisponível no dia "2026-12-10" das "09:00" às "11:00"
    Quando o cliente consultar os horários disponíveis para esse dia
    Então nenhum horário entre "09:00" e "10:59" deve ser oferecido

  Cenário: Permitir alteração de agendamento sem falso conflito (RN09)
    Dado que existe o agendamento de ID 101 para a cliente "Maria" no dia "2026-12-10" das "10:00" às "11:00"
    Quando o atendente editar o agendamento de ID 101 para consultar horários no dia "2026-12-10"
    Então o horário "10:00" deve ser exibido como disponível para reagendamento

  Cenário: Respeitar a capacidade de múltiplos atendentes no mesmo serviço (RN10)
    Dado que o serviço "Atendimento Compartilhado" possui duração de 60 minutos e 2 atendentes
    E já existe 1 agendamento confirmado às "10:00" para esse serviço
    Quando um segundo cliente consultar os horários disponíveis
    Então o horário "10:00" ainda deve ser exibido como disponível

  Cenário: Bloquear horários que violem a antecedência mínima configurada (RN11)
    Dado que a antecedência mínima do sistema é de 120 minutos
    E que o horário atual do servidor é "2026-12-10 08:30"
    Quando o cliente consultar os horários disponíveis para "2026-12-10"
    Então os horários "09:00" e "10:00" não devem ser disponibilizados
    E o horário "10:30" deve estar disponível