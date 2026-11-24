# Evidências do Entregável 4 — pipeline com quality gate

Descrição e interpretação em `a3/04-pipeline.md` (seção 5). Capturas de 18/11 a 19/11/2026, exceto `gate-local-aprova-reprova.txt` (24/11).

| Arquivo | O que mostra |
|---|---|
| `ci-1-vermelho.png` | CI nº 1 (commit `ed1fa95`): `qualidade` vermelho (19 s), `frontend-build` verde (50 s) |
| `ci-2-diagnostico-fuso.png` | Log do diagnóstico: `Asia/Calcutta` ausente no runner |
| `ci-2-gate-aprovado.png` | Gate da execução nº 2: 67/269 = 24,91%, meta 24%, aprovado |
| `ci-2-verde.png` | Lista de execuções: nº 2 verde (`af1b475`) e nº 1 vermelha (`ed1fa95`) |
| `push-direto-recusado.txt` | Push direto na `a3/base` recusado (GH006) |
| `pr1-bloqueado.png` | PR #1: `qualidade` vermelho com meta 30%, merge bloqueado |
| `pr1-gate-log.png` e `.txt` | Log do gate reprovando (24,91% contra 30%) |
| `pr1-verde-aguardando-aprovacao.png` | PR #1 com meta 24%: checks verdes, falta aprovação |
| `pr1-liberado.png` | PR #1 aprovado (MusiMus1 e hzmotta), botão de merge liberado |
| `pr1-merged.png` | PR #1 mergeado (commit `26e3449`) |
| `pr2-verde.png` e `pr2-merged.png` | PR #2 (PHPStan com baseline): checks verdes e merge |
| `pr3-reprova-e-corrige.png` e `pr3-merged.png` | PR #3: erro proposital reprova, correção libera; merge com 4 checks |
| `protecao-branch-1.png` a `-3.png` | Regra de proteção da `a3/base` (Settings > Branches) |
| `gate-local-aprova-reprova.txt` | Script do gate nos dois sentidos (meta 80 aprova, meta 96 reprova, código 1) |
