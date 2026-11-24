# Evidências do TDD e do BDD da RN11 (`BookingPolicyValidator`)

Capturas feitas em 24/11/2026, na máquina local, via `docker compose -p a3-ea -f compose.a3.yaml exec php-fpm` (PHP 8.5.12, PHPUnit 12.5.13). Hashes do TDD: red `d2e56787`, green `88a1fc86`, borda `585c5d4f`, refactor `28a6b302` (PR #6).

## TDD (PHPUnit)

| Print | Fase | O que mostra |
|---|---|---|
| `01-phpunit-sem-testes-esqueleto.png` | Preparação | Classe de teste existe, mas "No tests executed" |
| `02-RED-phpunit-falha-esperado-RN11-recebido-vazio.png` | Red | `testRejectsBookingWithLessThanTwoHoursAdvance` falha: esperado `['RN11']`, recebido `[]` (1 teste, 1 falha) |
| `03-GREEN-phpunit-OK-1-teste.png` | Green | OK (1 test, 1 assertion) |
| `04-suite-completa-192-testes.png` | Green | Suíte completa sem regressão: 192 testes, 266 asserções, 7 depreciações, 4 ignorados |
| `05-phpunit-OK-2-testes-borda.png` | Borda | Teste de exatamente 2h adicionado e passando (2 testes) |
| `06-mutacao-phpunit-falha-com-menor-ou-igual.png` | Mutação | Com `<=` no lugar de `<`, `testAcceptsBookingWithExactlyTwoHoursAdvance` falha: esperado `[]`, recebido `['RN11']` |
| `07-codigo-com-menor-ou-igual-mutante.png` | Mutação | O código mutante (`<=`) |
| `08-codigo-revertido-menor.png` | Mutação | Código revertido para `<` |
| `09-phpunit-OK-2-testes-apos-reverter.png` | Mutação | Testes voltam a passar após reverter |
| `10-phpunit-OK-2-testes-apos-refactor.png` | Refactor | Testes passam após o refactor (constante e docblock) |
| `11-suite-completa-193-testes.png` | Final | Suíte completa: 193 testes, 267 asserções, 7 depreciações, 4 ignorados (os `known-bug`) |
| `19-phpstan-sem-erros.png` | Estática | `phpstan analyse`: [OK] No errors |

## BDD (Behat)

| Print | O que mostra |
|---|---|
| `12-behat-passos-pendentes.png` | Cenários CT08a e CT08b escritos, passos "When" pendentes (2 pendentes, 2 ignorados) |
| `13-behat-2-cenarios-passando.png` | 2 cenários e 8 passos passando |
| `14-behat-mutacao-erro-interno-assert.png` | Tentativa de mutação com falha interna (`assert(self::$instance instanceof Configuration)`), não relacionada à regra. Mantida como registro; a evidência válida da mutação no BDD é a `15` |
| `15-behat-mutacao-mensagem-clara-CT08b.png` | Com `<=`, o CT08b falha com "Esperado [], recebido ["RN11"]" (1 passou, 1 falhou) |
| `16-behat-verde-apos-reverter.png` | Após reverter, os 2 cenários voltam a passar |
| `17-behat-verde-docblock-movido.png` | Behat verde com o docblock reposicionado acima de `validate()` |
| `18-behat-mutacao-CT08b-falha.png` | Código mutante e falha do CT08b lado a lado |

Os nomes dos arquivos são descrições do conteúdo conferidas por visualização (as imagens 05, 07 a 10 e 16 foram identificadas pelo nome do arquivo e pelo contexto da sequência).
