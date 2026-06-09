<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Modelo de notificação do plugin: cria/remove a Notification, o Template,
 * a tradução (corpo com o link tokenizado) e o alvo (validador).
 *
 * Chamado por hook.php no install()/uninstall(). Padrão SDB-5/SDB-11/SDB-17.
 */
class PluginApprovalbymailNotification
{
    public const ITEMTYPE      = 'PluginApprovalbymailAction';
    public const EVENT         = 'approvalrequest';
    public const TEMPLATE_NAME = 'Approval by mail - request';
    public const NOTIF_NAME    = 'Approval by mail - approval request';

    /**
     * Cria o modelo de notificação. Idempotente: limpa resíduo antes de criar.
     */
    public static function installNotificationModels(): bool
    {
        self::uninstallNotificationModels();

        // 1) Template
        $tpl    = new NotificationTemplate();
        $tpl_id = $tpl->add([
            'name'     => self::TEMPLATE_NAME,
            'itemtype' => self::ITEMTYPE,
            'comment'  => 'Created by approvalbymail',
            'css'      => '',
        ]);
        if (!$tpl_id) {
            Toolbox::logInFile('approvalbymail', "op=installNotificationModels step=template result=fail\n");
            return false;
        }

        // 2) Tradução padrão (language='' cobre todos os idiomas)
        $trans = new NotificationTemplateTranslation();
        $ok = $trans->add([
            'notificationtemplates_id' => $tpl_id,
            'language'                 => '',
            'subject'                  => 'Aprovação pendente: ##approvalbymail.tickettitle##',
            'content_text'             => self::defaultBodyText(),
            'content_html'             => self::defaultBodyHtml(),
        ]);
        if (!$ok) {
            Toolbox::logInFile('approvalbymail', "op=installNotificationModels step=translation result=fail\n");
            return false;
        }

        // 3) Notification
        $notif    = new Notification();
        $notif_id = $notif->add([
            'name'         => self::NOTIF_NAME,
            'entities_id'  => 0,
            'itemtype'     => self::ITEMTYPE,
            'event'        => self::EVENT,
            'is_active'    => 1,
            'is_recursive' => 1,
            'comment'      => '',
        ]);
        if (!$notif_id) {
            Toolbox::logInFile('approvalbymail', "op=installNotificationModels step=notification result=fail\n");
            return false;
        }

        // 4) Liga Notification <-> Template no modo e-mail
        //    'mailing' é o valor armazenado para o modo de e-mail no GLPI.
        $link = new Notification_NotificationTemplate();
        if (!$link->add([
            'notifications_id'         => $notif_id,
            'notificationtemplates_id' => $tpl_id,
            'mode'                     => 'mailing',
        ])) {
            Toolbox::logInFile('approvalbymail', "op=installNotificationModels step=link result=fail\n");
            return false;
        }

        // 5) Alvo: o validador (resolvido em addSpecificTargets pela classe de alvo)
        $target = new NotificationTarget();
        if (!$target->add([
            'notifications_id' => $notif_id,
            'items_id'         => PluginApprovalbymailNotificationTargetAction::TARGET_VALIDATOR,
            'type'             => Notification::USER_TYPE,
        ])) {
            Toolbox::logInFile('approvalbymail', "op=installNotificationModels step=target result=fail\n");
            return false;
        }

        Toolbox::logInFile(
            'approvalbymail',
            sprintf("op=installNotificationModels tpl_id=%d notif_id=%d result=ok\n", $tpl_id, $notif_id)
        );
        return true;
    }

    /**
     * Remove tudo que o install criou (teardown completo, SDB-11).
     * Discriminador: itemtype = PluginApprovalbymailAction.
     */
    public static function uninstallNotificationModels(): bool
    {
        /** @var \DBmysql $DB */
        global $DB;

        // Notifications nossas + seus filhos (links e alvos)
        $notif_ids = [];
        foreach ($DB->request([
            'SELECT' => 'id',
            'FROM'   => Notification::getTable(),
            'WHERE'  => ['itemtype' => self::ITEMTYPE, 'event' => self::EVENT],
        ]) as $row) {
            $notif_ids[] = (int) $row['id'];
        }
        foreach ($notif_ids as $nid) {
            $DB->delete(Notification_NotificationTemplate::getTable(), ['notifications_id' => $nid]);
            $DB->delete(NotificationTarget::getTable(), ['notifications_id' => $nid]);
            $DB->delete(Notification::getTable(), ['id' => $nid]);
        }

        // Templates nossos + traduções
        $tpl_ids = [];
        foreach ($DB->request([
            'SELECT' => 'id',
            'FROM'   => NotificationTemplate::getTable(),
            'WHERE'  => ['itemtype' => self::ITEMTYPE],
        ]) as $row) {
            $tpl_ids[] = (int) $row['id'];
        }
        foreach ($tpl_ids as $tid) {
            $DB->delete(NotificationTemplateTranslation::getTable(), ['notificationtemplates_id' => $tid]);
            $DB->delete(NotificationTemplate::getTable(), ['id' => $tid]);
        }

        Toolbox::logInFile(
            'approvalbymail',
            sprintf("op=uninstallNotificationModels notifs=%d tpls=%d result=ok\n", count($notif_ids), count($tpl_ids))
        );
        return true;
    }

    private static function defaultBodyHtml(): string
    {
        return implode("\n", [
            '<p>Olá,</p>',
            '<p>Há um pedido de validação aguardando sua decisão sobre o chamado: '
                . '<strong>##approvalbymail.tickettitle##</strong>.</p>',
            '<p>Você pode aprovar ou recusar diretamente pelo link abaixo, sem precisar fazer login:</p>',
            '<p><a href="##approvalbymail.url##">Abrir aprovação</a></p>',
            '<p style="color:#666;font-size:0.9rem">Este link é de uso único e expira em alguns dias.</p>',
        ]);
    }

    private static function defaultBodyText(): string
    {
        return implode("\n", [
            'Olá,',
            '',
            'Há um pedido de validação aguardando sua decisão sobre o chamado: ##approvalbymail.tickettitle##.',
            'Aprove ou recuse diretamente pelo link abaixo, sem precisar fazer login:',
            '',
            '##approvalbymail.url##',
            '',
            'Este link é de uso único e expira em alguns dias.',
        ]);
    }
}
