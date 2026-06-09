<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Alvo e dados da notificação "approvalrequest".
 *
 * Itemtype-alvo: PluginApprovalbymailAction (a ação tokenizada).
 * Destinatário : o validador (campo `users_id` da própria ação).
 *
 * Padrão SDB-5 (roteia pela stack nativa) + SDB-17 (log chave=valor).
 */
class PluginApprovalbymailNotificationTargetAction extends NotificationTarget
{
    /** Evento próprio do plugin. */
    public const EVENT_APPROVAL_REQUEST = 'approvalrequest';

    /** Alvo customizado: o validador da ação. Id alto para não colidir com alvos do core. */
    public const TARGET_VALIDATOR = 9001;

    public function getEvents()
    {
        return [
            self::EVENT_APPROVAL_REQUEST => __('Approval request by mail', 'approvalbymail'),
        ];
    }

    /**
     * Declara o alvo customizado (aparece na config da notificação).
     */
    public function addAdditionalTargets($event = '')
    {
        $this->addTarget(
            self::TARGET_VALIDATOR,
            __('Validator of the action', 'approvalbymail'),
            Notification::USER_TYPE
        );
    }

    /**
     * Resolve o alvo customizado em destinatário real.
     * $this->obj é a Action; users_id = validador.
     */
    public function addSpecificTargets($data, $options)
    {
        if (
            (int) $data['type'] === Notification::USER_TYPE
            && (int) $data['items_id'] === self::TARGET_VALIDATOR
        ) {
            // Helper do core: busca o usuário pelo campo no objeto do evento e valida deleted/active.
            $this->addUserByField('users_id');
        }
    }

    /**
     * Monta os tags do template, incluindo o link tokenizado.
     */
    public function addDataForTemplate($event, $options = [])
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        $action = $this->obj; // PluginApprovalbymailAction
        $hash   = method_exists($action, 'getEncryptedHash') ? (string) $action->getEncryptedHash() : '';
        $base   = rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/');
        $url    = $base . '/plugins/approvalbymail/front/action.php?hash=' . rawurlencode($hash);

        // Título do chamado (melhor esforço, sem quebrar se algo faltar).
        $title = '';
        if (($action->fields['itemtype'] ?? '') === 'TicketValidation') {
            $tv = new TicketValidation();
            if ($tv->getFromDB((int) ($action->fields['items_id'] ?? 0))) {
                $tkt = new Ticket();
                if ($tkt->getFromDB((int) ($tv->fields['tickets_id'] ?? 0))) {
                    $title = (string) ($tkt->fields['name'] ?? '');
                }
            }
        }

        $this->data['##approvalbymail.url##']         = $url;
        $this->data['##approvalbymail.tickettitle##'] = $title;

        // SDB-17: log estruturado chave=valor (código+string onde houver, valores explícitos).
        Toolbox::logInFile(
            'approvalbymail',
            sprintf(
                "op=addDataForTemplate action_id=%d users_id=%d url_len=%d title_set=%d result=ok\n",
                (int) ($action->fields['id'] ?? 0),
                (int) ($action->fields['users_id'] ?? 0),
                strlen($url),
                $title !== '' ? 1 : 0
            )
        );
    }
}
