<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Gatilho de criação de pedido de validação de chamado.
 * Quando um TicketValidation é criado, gera a ação tokenizada que será
 * enviada por e-mail ao validador (o link de aprovar/recusar).
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
        $users_id_validate = (int) ($item->fields['users_id_validate'] ?? 0);
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
                "Falha ao criar acao para TicketValidation #{$validation_id}\n"
            );
            return;
        }

        // Token NÃO é registrado em log (é segredo). Só o rastro mínimo.
        Toolbox::logInFile(
            'approvalbymail',
            sprintf(
                "Acao #%d criada para TicketValidation #%d (validador %d)\n",
                (int) $action->fields['id'],
                $validation_id,
                $users_id_validate
            )
        );

        // S2: aqui o Hash cifrado ($action->getEncryptedHash()) alimentará
        //     o link do e-mail de notificação ao validador.
    }
}

