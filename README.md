# Approval by Mail (GLPI)

Plugin para o **GLPI 10.0** que permite aprovar ou recusar **pedidos de validação de
chamado** por um link enviado por e-mail, **sem login**. A autorização vem inteiramente
de um token de uso único — não é preciso acessar o GLPI para decidir.

Fork modernizado (Padrão SDB) do plugin abandonado "SDB - Ação por e-mail" (GPLv3).

## Como funciona

1. Um pedido de validação de chamado é criado no GLPI (`TicketValidation`).
2. O plugin gera uma **ação tokenizada** e envia um e-mail ao validador com um link.
3. O validador abre o link e vê uma **página pública** com o chamado, a mensagem do
   solicitante e os botões **Aprovar** / **Reprovar**.
4. A decisão é aplicada ao chamado (status + comentário), o status global de validação
   é recalculado, o **token é consumido** (uso único) e o **solicitante é notificado**.

## Requisitos

- GLPI **10.0.x** (testado em 10.0.17)
- PHP **8.1+**
- Notificações por e-mail habilitadas no GLPI (Configurar → Notificações)

## Instalação

```bash
# copie a pasta do plugin para glpi/plugins/approvalbymail e então:
php bin/console plugin:install  -u <usuário_glpi> approvalbymail
php bin/console plugin:activate                   approvalbymail
```

Em *Configurar → Approval by Mail*, mantenha ligada a opção **Ticket - Aprovação**.

## Configuração do e-mail

O modelo de notificação é um `NotificationTemplate` nativo, **editável pelo admin** em
*Configurar → Notificações → Modelos de notificação → "Approval by mail - request"*.
Tags disponíveis no corpo:

- `##approvalbymail.url##` — link da página de decisão (uso único)
- `##approvalbymail.tickettitle##` — título do chamado

## Segurança

- Token forte de **uso único**: `bin2hex(random_bytes(32))`, comparação com `hash_equals`,
  expiração por TTL, consumido (`used_at`) após o primeiro uso válido.
- **GET nunca escreve** — protege contra pré-carregamento de link por antivírus/cliente
  de e-mail; a decisão só é aplicada no **POST** confirmado.
- **Motivo obrigatório ao reprovar** (validado no navegador e no servidor).
- Erros genéricos na página (não revelam se o token é forjado, expirado ou usado).
- CSRF do GLPI no POST; conteúdo do solicitante sanitizado antes da tela.
- Decisão aplicada em transação; criptografia via `GLPIKey`; acesso a dados via query builder.

## Limitações conhecidas

- Textos da página e do template estão em **português**; i18n (PT/EN) planejado antes do 1.0.
- `comment_validation` gravado como texto puro (refinamento via `Sanitizer` previsto).

## Licença e autoria

GPLv3 — Carlos Alberto Correa Filho (IPT.br).
