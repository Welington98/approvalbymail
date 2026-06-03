# approval by mail

Plugin do GLPI para **aprovar/recusar chamados por e-mail** via link tokenizado,
sem necessidade de login. Fork modernizado, no **Padrao SDB**, do plugin abandonado
"SDB - Acao por e-mail" (ServiceDesk Brasil, GPLv3).

- **Alvo:** GLPI 10.0.x · PHP 8.x · MariaDB
- **Versao:** 0.0.1-alpha (MVP em construcao: aprovacao de TicketValidation)

## Estado (0.0.1-alpha)
S0 — fundacao: instala/desinstala limpo, aba de configuracao e feature flag.
Ainda **nao** envia e-mail (notificacao = S2; motor de validacao = S3).

## Desenvolvimento (servidor de homologacao)
```bash
npm install          # dependencias de build
make deploy          # build + substitui na pasta live do GLPI
make install         # instala via console (uma vez)
make activate        # ativa via console
make logs            # acompanha o log do GLPI
```
Caminhos do ambiente em `deploy/config.sh`.

## Licenca
GPLv3 — veja `LICENSE`.

