<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Ação tokenizada: registro que liga (usuário, item, itemtype) a um token
 * de uso único enviado por e-mail.
 *
 * S0: apenas o mapeamento de tabela (a tabela é criada no install).
 * S1: geração do token (bin2hex(random_bytes(32))) e validação
 *     (hash_equals, used_at single-use, expiração).
 */
class PluginApprovalbymailAction extends CommonDBTM
{
    public $dohistory = false;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_approvalbymail_actions';
    }

    public static function getForeignKeyField()
    {
        return 'plugin_approvalbymail_actions_id';
    }

    static function canView()
    {
        return false;
    }

    static function canCreate()
    {
        // Criado internamente pelos hooks a partir do S1.
        return true;
    }

    static function canUpdate()
    {
        return false;
    }

    static function canDelete()
    {
        return false;
    }

    static function canPurge()
    {
        return false;
    }
}

