<?php
/**
 * Página pública (sem login) de aprovação/reprovação por link tokenizado.
 *
 * Princípios (Padrão SDB-4): a autorização vem inteiramente do token; o GET
 * NUNCA escreve; a escrita só ocorre no POST confirmado; erros são genéricos.
 *
 * S3b: o POST aplica a decisão no TicketValidation (status + comentário),
 * recalcula o global_validation do chamado e consome o token (single-use),
 * tudo em transação. Reprovar exige motivo. Mostra a mensagem do solicitante.
 */

include('../../../inc/includes.php');

// Página pública: deliberadamente SEM Session::checkLoginUser().

/**
 * Renderiza uma página HTML mínima e autossuficiente, depois encerra.
 */
function abm_html_page(string $title, string $bodyHtml): void
{
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo "<!DOCTYPE html>\n<html lang=\"pt-BR\"><head>";
    echo "<meta charset=\"utf-8\">";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">";
    echo "<title>{$t}</title>";
    echo "<style>"
        . ":root{--abm-fg:#1f2933;--abm-muted:#616e7c;--abm-line:#e4e7eb;"
        . "--abm-ok:#0b8457;--abm-no:#b42318;--abm-bg:#f7f8fa;}"
        . "*{box-sizing:border-box;}"
        . "body{margin:0;background:var(--abm-bg);color:var(--abm-fg);"
        . "font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;line-height:1.5;}"
        . ".abm-wrap{max-width:34rem;margin:3rem auto;padding:0 1rem;}"
        . ".abm-card{background:#fff;border:1px solid var(--abm-line);border-radius:.75rem;"
        . "padding:1.75rem;box-shadow:0 1px 3px rgba(0,0,0,.06);}"
        . "h1{font-size:1.25rem;margin:0 0 1rem;}"
        . ".abm-muted{color:var(--abm-muted);font-size:.95rem;}"
        . ".abm-ticket{background:var(--abm-bg);border:1px solid var(--abm-line);"
        . "border-radius:.5rem;padding:.75rem 1rem;margin:1rem 0;}"
        . ".abm-submission{background:var(--abm-bg);border-left:3px solid #9aa5b1;"
        . "padding:.6rem .9rem;border-radius:.4rem;margin:.25rem 0 .5rem;}"
        . "label{display:block;font-weight:600;margin:1rem 0 .4rem;}"
        . "textarea{width:100%;min-height:5rem;border:1px solid var(--abm-line);"
        . "border-radius:.5rem;padding:.6rem;font:inherit;resize:vertical;}"
        . ".abm-err{color:var(--abm-no);font-weight:600;margin:.5rem 0 0;}"
        . ".abm-actions{display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap;}"
        . "button{flex:1 1 8rem;border:0;border-radius:.5rem;padding:.7rem 1rem;"
        . "font:inherit;font-weight:600;color:#fff;cursor:pointer;}"
        . ".abm-approve{background:var(--abm-ok);}.abm-reject{background:var(--abm-no);}"
        . "</style></head><body><div class=\"abm-wrap\"><div class=\"abm-card\">";
    echo $bodyHtml;
    echo "</div><p class=\"abm-muted\" style=\"text-align:center;margin-top:1rem\">"
        . "GLPI &middot; approval by mail</p></div></body></html>";
}

/** Mensagem de erro genérica (não revela expirado/usado/forjado). */
function abm_error_page(): void
{
    abm_html_page(
        'Link inválido',
        '<h1>Link inválido</h1>'
        . '<p class="abm-muted">Este link é inválido, já foi utilizado ou expirou. '
        . 'Se precisar, acesse o sistema para tratar a validação.</p>'
    );
}

/** Página de confirmação (formulário). $error preenche a mensagem de erro do servidor. */
function abm_render_form(
    string $post_url,
    string $csrf,
    string $hash,
    int $ticket_id,
    string $ticket_title,
    string $submission_html,
    string $error = ''
): void {
    $title = $ticket_title !== '' ? $ticket_title : '(sem título)';

    $b  = '<h1>Validação de chamado</h1>';
    $b .= '<p class="abm-muted">Confirme sua decisão sobre o chamado abaixo. '
        . 'Você não precisa fazer login.</p>';
    $b .= '<div class="abm-ticket"><strong>#' . $ticket_id . '</strong> &mdash; '
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';

    if ($submission_html !== '') {
        $b .= '<label>Mensagem do solicitante</label>';
        $b .= '<div class="abm-submission">' . $submission_html . '</div>';
    }
    if ($error !== '') {
        $b .= '<p class="abm-err">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $b .= '<form id="abm-form" method="post" action="'
        . htmlspecialchars($post_url, ENT_QUOTES, 'UTF-8') . '">';
    $b .= '<input type="hidden" name="_glpi_csrf_token" value="'
        . htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') . '">';
    $b .= '<input type="hidden" name="hash" value="'
        . htmlspecialchars($hash, ENT_QUOTES, 'UTF-8') . '">';
    $b .= '<label for="comment">Comentário '
        . '<span class="abm-muted">(obrigatório ao reprovar)</span></label>';
    $b .= '<textarea id="comment" name="comment"></textarea>';
    $b .= '<p id="abm-jserr" class="abm-err" style="display:none">'
        . 'Para reprovar, informe o motivo.</p>';
    $b .= '<div class="abm-actions">';
    $b .= '<button class="abm-approve" type="submit" name="decision" value="approve">Aprovar</button>';
    $b .= '<button class="abm-reject" type="submit" name="decision" value="reject">Reprovar</button>';
    $b .= '</div></form>';
    $b .= '<script>(function(){'
        . 'var f=document.getElementById("abm-form"),'
        . 'c=document.getElementById("comment"),e=document.getElementById("abm-jserr");'
        . 'f.addEventListener("submit",function(ev){var b=ev.submitter;'
        . 'if(b&&b.value==="reject"&&c.value.trim()===""){'
        . 'ev.preventDefault();e.style.display="block";c.focus();}});'
        . '})();</script>';

    abm_html_page('Validação de chamado', $b);
}

$plugin = new Plugin();
if (!$plugin->isActivated('approvalbymail')) {
    abm_error_page();
    return;
}

// --- Resolução do token (read-only; não consome) ---
$hash   = (string) ($_REQUEST['hash'] ?? '');
$action = $hash !== '' ? PluginApprovalbymailAction::resolve($hash) : null;

if ($action === null) {
    Toolbox::logInFile('approvalbymail', "op=action_get result=invalid_or_used\n");
    abm_error_page();
    return;
}

// --- Contexto: validação, chamado e a mensagem do solicitante ---
$tv_id           = (int) ($action->fields['items_id'] ?? 0);
$tv              = new TicketValidation();
$tv_loaded       = ($action->fields['itemtype'] ?? '') === 'TicketValidation' && $tv->getFromDB($tv_id);
$tickets_id      = 0;
$ticket_id       = 0;
$ticket_title    = '';
$submission_html = '';
$tv_status       = 0;

if ($tv_loaded) {
    $tickets_id = (int) $tv->fields['tickets_id'];
    $tv_status  = (int) $tv->fields['status'];
    $sub        = (string) ($tv->fields['comment_submission'] ?? '');
    if ($sub !== '') {
        // SDB-6: conteúdo de usuário sempre sanitizado antes de ir para tela.
        $submission_html = \Glpi\RichText\RichText::getSafeHtml($sub);
    }
    $tkt = new Ticket();
    if ($tkt->getFromDB($tickets_id)) {
        $ticket_id    = (int) $tkt->fields['id'];
        $ticket_title = (string) $tkt->fields['name'];
    }
}

$post_url = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/approvalbymail/front/action.php';
$csrf     = Session::getNewCSRFToken();

// =========================== POST: aplica a decisão ===========================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $raw      = (string) ($_POST['decision'] ?? '');
    $decision = in_array($raw, ['approve', 'reject'], true) ? $raw : '';
    $comment  = trim((string) ($_POST['comment'] ?? ''));

    if ($decision === '') {
        abm_error_page();
        return;
    }

    // Reprovar exige motivo (servidor, além do bloqueio no navegador).
    if ($decision === 'reject' && $comment === '') {
        abm_render_form(
            $post_url, $csrf, $hash, $ticket_id, $ticket_title, $submission_html,
            'Para reprovar, é obrigatório informar o motivo.'
        );
        return;
    }

    if (!$tv_loaded) {
        abm_error_page();
        return;
    }

    // Idempotência: se já não está aguardando, foi decidido em outro lugar.
    if ($tv_status !== (int) TicketValidation::WAITING) {
        $DB->update(
            PluginApprovalbymailAction::getTable(),
            ['used_at' => ($_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'))],
            ['id' => (int) $action->fields['id']]
        );
        Toolbox::logInFile(
            'approvalbymail',
            sprintf("op=action_apply action_id=%d result=already_decided\n", (int) $action->fields['id'])
        );
        abm_html_page(
            'Já validado',
            '<h1>Chamado já validado</h1>'
            . '<p>Este pedido de validação já havia sido respondido. Nenhuma alteração foi feita.</p>'
        );
        return;
    }

    $status = $decision === 'approve' ? (int) TicketValidation::ACCEPTED : (int) TicketValidation::REFUSED;
    $now    = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

    $DB->beginTransaction();
    try {
        // 1) grava a decisão na validação
        $DB->update(TicketValidation::getTable(), [
            'status'             => $status,
            'comment_validation' => $comment,
            'validation_date'    => $now,
        ], ['id' => $tv_id]);

        // 2) recalcula o status global de validação do chamado
        $ticket = new Ticket();
        if ($ticket->getFromDB($tickets_id)) {
            $global = TicketValidation::computeValidationStatus($ticket);
            $DB->update(Ticket::getTable(), ['global_validation' => $global], ['id' => $tickets_id]);
        }

        // 3) consome o token (single-use)
        $DB->update(PluginApprovalbymailAction::getTable(), [
            'used_at' => $now,
        ], ['id' => (int) $action->fields['id']]);

        $DB->commit();
    } catch (\Throwable $e) {
        $DB->rollBack();
        Toolbox::logInFile(
            'approvalbymail',
            sprintf("op=action_apply action_id=%d result=fail_exception\n", (int) $action->fields['id'])
        );
        abm_error_page();
        return;
    }

    Toolbox::logInFile(
        'approvalbymail',
        sprintf(
            "op=action_apply action_id=%d validation_id=%d decision=%s status=%d result=ok\n",
            (int) $action->fields['id'],
            $tv_id,
            $decision,
            $status
        )
    );

    // Fecha o ciclo: notifica o solicitante via notificação nativa "validation_answer".
    $ticket_notif = new Ticket();
    if ($ticket_notif->getFromDB($tickets_id)) {
        NotificationEvent::raiseEvent('validation_answer', $ticket_notif, ['validation_id' => $tv_id]);
        Toolbox::logInFile(
            'approvalbymail',
            sprintf("op=notify_answer validation_id=%d result=queued\n", $tv_id)
        );
    }

    $label = $decision === 'approve' ? 'aprovado' : 'reprovado';
    abm_html_page(
        'Concluído',
        '<h1>Decisão registrada</h1>'
        . '<p>O chamado #' . $ticket_id . ' foi <strong>' . $label . '</strong> com sucesso.</p>'
        . '<p class="abm-muted">Obrigado. Você já pode fechar esta página.</p>'
    );
    return;
}

// =============================== GET: confirmação ==============================
Toolbox::logInFile(
    'approvalbymail',
    sprintf("op=action_get action_id=%d result=shown\n", (int) $action->fields['id'])
);

abm_render_form($post_url, $csrf, $hash, $ticket_id, $ticket_title, $submission_html);
