<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Gatilho de aprovação de SOLUÇÃO por e-mail.
 *
 * Quando uma solução (ITILSolution) é adicionada a um chamado e nasce
 * aguardando aprovação do requerente (status WAITING), gera a ação tokenizada
 * e dispara a notificação ao REQUERENTE (link de aprovar/recusar).
 *
 * Espelha PluginApprovalbymailTicketValidation; muda objeto e destinatário,
 * e adiciona dois portões: feature flag TICKET_SOLUTION e status == WAITING.
 */
class PluginApprovalbymailItilSolution extends CommonDBTM
{
    /** Evento de notificação da solução (modelo criado no S3b). */
    public const EVENT_SOLUTION_APPROVAL = 'solutionapproval';

    /**
     * Hook 'item_add' em ITILSolution.
     */
    public static function item_add(ITILSolution $item): void
    {
        /** @var \DBmysql $DB */
        global $DB;

        // Portão 1: feature flag (Configurar > Approval by Mail).
        if (!PluginApprovalbymailConfig::isFeatureActive(PluginApprovalbymailConfig::TICKET_SOLUTION)) {
            return;
        }

        // Só tratamos solução de CHAMADO (não Change/Problem).
        if (($item->fields['itemtype'] ?? '') !== 'Ticket') {
            return;
        }

        // Portão 2: só quando a solução nasce AGUARDANDO aprovação do requerente.
        // (autoclose imediato entra ACCEPTED -> nada a fazer.)
        if ((int) ($item->fields['status'] ?? 0) !== (int) CommonITILValidation::WAITING) {
            return;
        }

        $solution_id = (int) ($item->fields['id'] ?? 0);
        $tickets_id  = (int) ($item->fields['items_id'] ?? 0);
        if ($solution_id <= 0 || $tickets_id <= 0) {
            return;
        }

        // Destinatário = requerente do chamado (Ticket_User type=REQUESTER).
        $requester_id = 0;
        foreach ($DB->request([
            'SELECT' => 'users_id',
            'FROM'   => 'glpi_tickets_users',
            'WHERE'  => ['tickets_id' => $tickets_id, 'type' => CommonITILActor::REQUESTER],
            'ORDER'  => 'id',
        ]) as $r) {
            $requester_id = (int) $r['users_id'];
            if ($requester_id > 0) {
                break;
            }
        }
        if ($requester_id <= 0) {
            Toolbox::logInFile(
                'approvalbymail',
                sprintf("op=item_add itemtype=ITILSolution solution_id=%d result=fail_no_requester\n", $solution_id)
            );
            return;
        }

        $action = PluginApprovalbymailAction::createAction(
            $requester_id,
            ITILSolution::class,
            $solution_id
        );
        if ($action === null) {
            Toolbox::logInFile(
                'approvalbymail',
                sprintf("op=item_add itemtype=ITILSolution solution_id=%d result=fail_createaction\n", $solution_id)
            );
            return;
        }

        // Entidade do chamado, para o roteamento da notificação (em memória).
        $entities_id = 0;
        $ticket = new Ticket();
        if ($ticket->getFromDB($tickets_id)) {
            $entities_id = (int) $ticket->fields['entities_id'];
        }
        $action->fields['entities_id'] = $entities_id;

        Toolbox::logInFile(
            'approvalbymail',
            sprintf(
                "op=item_add itemtype=ITILSolution solution_id=%d action_id=%d users_id=%d entity=%d result=ok\n",
                $solution_id,
                (int) $action->fields['id'],
                $requester_id,
                $entities_id
            )
        );

        // Dispara a notificação ao requerente. Sem modelo (S3b) ainda, o evento
        // não enfileira e-mail — mas o gatilho/Action ficam provados no log.
        NotificationEvent::raiseEvent(self::EVENT_SOLUTION_APPROVAL, $action);
        Toolbox::logInFile(
            'approvalbymail',
            sprintf(
                "op=raiseEvent event=%s action_id=%d entity=%d result=queued\n",
                self::EVENT_SOLUTION_APPROVAL,
                (int) $action->fields['id'],
                $entities_id
            )
        );
    }
}
