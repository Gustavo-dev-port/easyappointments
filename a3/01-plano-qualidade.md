# Plano de Qualidade e SQA

## 1. Objetivo e escopo

Este Plano de Qualidade e SQA (Software Quality Assurance) estabelece a política, os objetivos, os padrões e as atividades que serão utilizados pelo grupo para garantir e avaliar a qualidade do Easy!Appointments durante o desenvolvimento da A3.

O software escolhido é o Easy!Appointments, um sistema web de agendamento desenvolvido em PHP, utilizando o framework CodeIgniter.

O foco da avaliação será o módulo **Availability**, responsável pelo cálculo e pela disponibilização dos horários para agendamento, considerando sua relação com os demais componentes necessários para esse funcionamento.

O trabalho é realizado sobre a versão 1.6.1, fixada pelo SHA informado pelo grupo e registrada em `a3/registros/versao.txt`.

**Identificação técnica do projeto:**

- Software: Easy!Appointments.
- Versão: 1.6.1.
- SHA informado: `d30cba6d90772e3a76dcba358322e55746704339`.
- Repositório: fork do grupo (`Gustavo-dev-port/easyappointments`).
- Branch de trabalho: `a3/base`.
- Ambiente: Docker, por meio do arquivo `compose.a3.yaml`.
- PHP: 8.5.
- Banco de dados: MySQL 8.0.
- Testes automatizados: PHPUnit 12 e Behat (BDD).
- Módulo crítico: `application/libraries/Availability.php`.

**Escopo da qualidade:** cálculo de horários disponíveis, respeito à jornada de trabalho, duração dos serviços, consideração de pausas e períodos indisponíveis, prevenção de conflitos entre agendamentos e integridade dos dados gravados no agendamento.

**Fora do escopo:** criação de novas funcionalidades não relacionadas ao módulo crítico, integrações externas como Google Calendar e CalDAV e avaliação aprofundada da interface visual.

## 2. Política de qualidade

Nenhuma alteração relacionada ao comportamento crítico de disponibilidade será considerada concluída sem que tenha sido validada por testes automatizados, revisada por outro integrante e registrada no controle de versões.

A qualidade será garantida por meio de:

- Verificação dos comportamentos esperados do módulo Availability.
- Revisão de código por outro integrante antes da integração das alterações.
- Execução e acompanhamento dos testes automatizados.
- Registro dos resultados, falhas e correções.
- Controle de versões por meio do Git e GitHub.
- Armazenamento de evidências sem senhas, tokens ou dados pessoais.
- Acompanhamento dos objetivos e indicadores de qualidade definidos neste plano.

O objetivo é reduzir falhas no cálculo da disponibilidade, evitar inconsistências nos agendamentos e manter a rastreabilidade das alterações realizadas pelo grupo.

## 3. Objetivos mensuráveis de qualidade

Os objetivos foram definidos a partir dos riscos do produto, listados no Plano de Testes `PT-EA-SQ04-2026` v2.0 (`RK01` a `RK06`), e de um risco de processo.

| Objetivo | Indicador e meta | Risco relacionado |
|---|---|---|
| Garantir que os horários disponibilizados respeitem a jornada de trabalho cadastrada. | 100% dos cenários críticos de jornada selecionados para teste devem apresentar o resultado esperado. | `RK02` – jornada de trabalho. |
| Garantir que o sistema evite conflitos entre agendamentos. | 100% dos cenários críticos de conflito selecionados para teste devem apresentar o resultado esperado. | `RK03` – conflitos na agenda. |
| Garantir que as alterações no módulo crítico sejam revisadas e rastreáveis. | 100% das pull requests que alterem o `Availability.php` devem possuir revisão de outro integrante antes da integração (exigida pela proteção da `a3/base`: 1 aprovação e check `qualidade`). | Risco de processo: alteração sem revisão e sem rastreabilidade (fora da lista `RK01`–`RK06` do produto). |

As metas serão verificadas por meio dos resultados dos testes, registros das revisões e histórico de pull requests.

Os objetivos não representam resultados já alcançados. Seu cumprimento deve ser demonstrado por evidências durante a execução do projeto.

## 4. Padrões e referências adotados

**ISO/IEC 25010 – Modelo de qualidade de software.** Referência para selecionar as características de qualidade mais importantes para o Easy!Appointments e justificar sua prioridade de acordo com os riscos do sistema.

**IEEE 829 – Documentação de testes.** Referência para organizar o planejamento e a execução dos testes, em conjunto com o Entregável 2 (`a3/02-estrategia-plano-testes.md`).

**`.editorconfig`.** Padroniza a formatação do código: finais de linha LF, quebra de linha final e indentação de quatro espaços, com exceções para arquivos específicos.

**`.prettierrc.json`.** Define limite de 120 caracteres por linha, aspas simples e vírgula final, conforme a configuração do projeto.

**Git e GitHub.** Controle de versões, histórico de alterações, organização das branches e revisão de código por pull requests.

A aplicação desses padrões é verificada no repositório do grupo. A referência ao IEEE 829 não representa certificação ou conformidade formal com a norma.

## 5. Características de qualidade priorizadas – ISO/IEC 25010

Foram selecionadas três características prioritárias, as mesmas do Plano de Testes `PT-EA-SQ04-2026` v2.0.

### 5.1 Adequação funcional

Capacidade do software de fornecer funcionalidades que atendam às necessidades previstas. Foi priorizada porque o objetivo principal do Easy!Appointments é permitir o agendamento de serviços: o cálculo dos horários disponíveis precisa considerar corretamente a jornada do profissional, a duração dos serviços, os intervalos e os horários já ocupados. Uma falha aqui pode disponibilizar horários incorretos e prejudicar o processo de agendamento.

### 5.2 Confiabilidade

Capacidade do sistema de manter seu funcionamento correto e consistente durante a utilização. Foi priorizada porque o sistema precisa evitar conflitos entre agendamentos e apresentar informações coerentes sobre a disponibilidade dos profissionais. Uma falha aqui pode permitir sobreposição de horários e inconsistências na agenda.

### 5.3 Segurança (operação e integridade dos dados)

Capacidade de proteger a integridade dos dados e de impedir operações indevidas. Foi priorizada porque o sistema grava agendamentos que precisam obedecer às regras do negócio: uma operação que o sistema aceita, mas não deveria, compromete a agenda. Exemplo real, encontrado na revisão por pares: o `Appointments_model::validate()` não rejeita agendamento com data no passado (`REV-04`, ver `a3/atas/ata-revisao-availability.md`).

> Observação de alinhamento: versões anteriores deste plano usavam "Usabilidade" como terceira característica. Ela foi substituída por "Segurança" para ficar igual ao Plano de Testes v2.0, que é o documento de referência dos testes. A interface visual segue fora do escopo (seção 1).

## 6. Linha de base e acompanhamento da qualidade

Dados iniciais, medidos antes das intervenções do grupo (tag `a3-baseline`):

| Indicador | Valor inicial |
|---|---|
| Testes automatizados | 95 testes |
| Asserções | 140 |
| Depreciações identificadas | 7 |
| Cobertura de linhas em `application/libraries` | 12,90% (256 de 1985) |
| Cobertura de linhas no `Availability.php` | 24,91% (67 de 269) |
| Métodos cobertos no `Availability.php` | 2 de 9 |

A cobertura é indicador de acompanhamento e não prova isolada de qualidade. Também são avaliados os resultados dos testes, os comportamentos críticos e os problemas encontrados nas revisões.

**Evidência da execução inicial:**

- `a3/registros/phpunit_baseline.txt` e `a3/registros/cobertura_baseline.txt` (saídas do PHPUnit e da cobertura).
- `a3/evidencias/baseline-local/` (prints numerados e README com a explicação de cada figura).

**Evolução medida:** após os testes do módulo (PR #7), a execução no GitHub Actions registrou 108 testes e 89,59% de cobertura de linhas no `Availability.php` (241 de 269), conforme `a3/evidencias/testes-unitarios-e2-e3/execucao-pr7.txt`.

## 7. Atividades de Software Quality Assurance (SQA)

### 7.1 Revisão por pares

Revisão do arquivo `application/libraries/Availability.php` por um integrante diferente do responsável pelo código. A revisão verifica:

- Cálculo dos horários disponíveis.
- Respeito à jornada de trabalho.
- Consideração da duração dos serviços.
- Respeito às pausas e períodos indisponíveis.
- Prevenção de conflitos e sobreposição de agendamentos.
- Tratamento de parâmetros e entradas inválidas.
- Organização e clareza do código.
- Existência de testes relacionados aos comportamentos críticos.

Problemas, sugestões e encaminhamentos são registrados em ata.

### 7.2 Testes automatizados

A suíte PHPUnit verifica o comportamento do módulo crítico após as alterações; o Behat cobre cenários de aceitação. São acompanhados os resultados, as depreciações e a cobertura. O planejamento detalhado e os casos de teste estão em `a3/02-estrategia-plano-testes.md`. O pipeline (`.github/workflows/ci.yml`) roda PHPStan, PHPUnit com cobertura, Behat e o gate de cobertura do `Availability.php`.

### 7.3 Controle de versões

As alterações são registradas no Git, com mensagens de commit que descrevem o que foi modificado e o motivo (convenção em `a3/README.md`) e entram por pull request, com revisão de outro integrante antes da integração na `a3/base`.

### 7.4 Registro de evidências

Os resultados das revisões, testes e verificações são armazenados no repositório (`a3/`). Não são incluídos arquivos de configuração com credenciais, senhas, tokens ou dados pessoais.

## 8. Planejamento da revisão por pares

Revisão sobre `application/libraries/Availability.php`. Os itens marcados **A DEFINIR** dependem de decisão do grupo e devem ser preenchidos até 25/11/2026.

| Atividade | Responsável | Prazo |
|---|---|---|
| Disponibilizar o código e a versão que será revisada. | A DEFINIR (responsável pelo código) | 25/11/2026 |
| Realizar a leitura e aplicar o checklist de revisão. | A DEFINIR (revisor, diferente do autor) | 25/11/2026 |
| Registrar os problemas e sugestões encontrados. | A DEFINIR (revisor) | 25/11/2026 |
| Avaliar e encaminhar as correções necessárias. | A DEFINIR (responsável pelo código) | A DEFINIR |
| Registrar a conclusão da revisão. | Julia e revisor | 25/11/2026 |

A revisão ocorre antes da integração da alteração correspondente, e os achados devem ser reais, identificados na leitura do código.

**Registro:** `a3/atas/ata-revisao-availability.md`.

## 9. Auditoria simples do processo

Auditoria interna para verificar se o grupo segue as práticas de qualidade estabelecidas, com oito itens:

1. As alterações estão sendo registradas por meio de commits no Git?
2. As pull requests recebem revisão de outro integrante antes da integração?
3. Os testes automatizados são executados após alterações relevantes?
4. Os resultados das execuções dos testes são registrados?
5. As evidências de desenvolvimento e testes estão armazenadas no repositório?
6. As evidências estão livres de senhas, tokens e dados pessoais?
7. Os padrões de formatação definidos pelo projeto estão sendo respeitados?
8. A versão do software, o SHA e a branch de trabalho estão identificados e registrados?

Classificação de cada item: **Conforme** (há evidência de cumprimento), **Não conforme** (há evidência de descumprimento) ou **Não verificado** (não foi possível confirmar). As não conformidades têm descrição, ação corretiva, responsável e prazo.

A existência e o funcionamento do pipeline de integração contínua são verificados no repositório, sem presumir que o GitHub Actions esteja funcionando.

**Registro:** `a3/atas/ata-auditoria-processo.md`.

## 10. Organização das evidências

- `a3/01-plano-qualidade.md` – plano de qualidade e SQA (este documento).
- `a3/02-estrategia-plano-testes.md` – estratégia e plano de testes (Entregável 2).
- `a3/atas/ata-revisao-availability.md` – ata da revisão por pares.
- `a3/atas/ata-auditoria-processo.md` – ata da auditoria do processo.
- `a3/registros/` – `versao.txt` (versão e SHA), `phpunit_baseline.txt` e `cobertura_baseline.txt`.
- `a3/evidencias/` – logs, prints e demais evidências das atividades.
- `a3/ci/verificar_cobertura.py` e `.github/workflows/ci.yml` – gate de cobertura e pipeline.

As evidências permitem identificar a atividade realizada, a data, o responsável e o resultado obtido.

## 11. Critério de conclusão

O Entregável 1 é considerado concluído quando o plano de qualidade estiver documentado, os três objetivos mensuráveis estiverem definidos, os padrões e as características de qualidade estiverem justificados, a revisão por pares tiver sido realizada e registrada e a auditoria do processo estiver preenchida com evidências reais.

As ações corretivas identificadas têm responsáveis e prazos definidos, para acompanhar a melhoria da qualidade durante o desenvolvimento do Easy!Appointments.
