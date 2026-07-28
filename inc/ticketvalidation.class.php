<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Gatilho de criação de pedido de validação de chamado.
 * Quando um TicketValidation é criado, gera a ação tokenizada e dispara
 * a notificação por e-mail ao validador (o link de aprovar/recusar).
 */
class PluginApprovalbymailTicketValidation extends CommonDBTM
{
    /**
     * Hook 'item_add' em TicketValidation.
     */
    public static function item_add(TicketValidation $item): void
    {
        // Respeita o feature flag (Configurar > Approval by Mail).
        $config = new PluginApprovalbymailConfig();
        if (
            $config->getFromDB(PluginApprovalbymailConfig::TICKET_VALIDATION)
            && !(bool) $config->fields['is_active']
        ) {
            return;
        }

        // O destinatário da ação é o validador designado.
        // GLPI 10: users_id_validate | GLPI 11: items_id_target + itemtype_target
        $users_id_validate = (int) ($item->fields['users_id_validate'] ?? 0);
        if ($users_id_validate <= 0) {
            $users_id_validate = (int) (($item->fields['itemtype_target'] ?? '') === User::class
                ? ($item->fields['items_id_target'] ?? 0)
                : 0);
        }
        $validation_id     = (int) ($item->fields['id'] ?? 0);
        if ($users_id_validate <= 0 || $validation_id <= 0) {
            return;
        }

        $action = PluginApprovalbymailAction::createAction(
            $users_id_validate,
            TicketValidation::class,
            $validation_id
        );
        if ($action === null) {
            Toolbox::logInFile(
                'approvalbymail',
                sprintf(
                    "op=item_add itemtype=TicketValidation validation_id=%d result=fail_createaction\n",
                    $validation_id
                )
            );
            return;
        }

        // Entidade do chamado, para o roteamento da notificação.
        // (A Action não é entity-assigned; setamos em memória só para o raiseEvent.)
        $entities_id = 0;
        $tickets_id  = (int) ($item->fields['tickets_id'] ?? 0);
        if ($tickets_id > 0) {
            $ticket = new Ticket();
            if ($ticket->getFromDB($tickets_id)) {
                $entities_id = (int) $ticket->fields['entities_id'];
            }
        }
        $action->fields['entities_id'] = $entities_id;

        // Token NÃO é registrado em log (é segredo). Só o rastro mínimo (SDB-17).
        Toolbox::logInFile(
            'approvalbymail',
            sprintf(
                "op=item_add itemtype=TicketValidation validation_id=%d action_id=%d users_id=%d entity=%d result=ok\n",
                $validation_id,
                (int) $action->fields['id'],
                $users_id_validate,
                $entities_id
            )
        );

        // Dispara a notificação: enfileira o e-mail ao validador com o link tokenizado.
        NotificationEvent::raiseEvent(
            PluginApprovalbymailNotificationTargetAction::EVENT_APPROVAL_REQUEST,
            $action
        );

        Toolbox::logInFile(
            'approvalbymail',
            sprintf(
                "op=raiseEvent event=approvalrequest action_id=%d entity=%d result=queued\n",
                (int) $action->fields['id'],
                $entities_id
            )
        );
    }
}
