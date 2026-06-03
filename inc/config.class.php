<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Configuração do plugin: feature flags + utilitários de cripto.
 */
class PluginApprovalbymailConfig extends CommonDBTM
{
    /** Feature flags (IDs fixos na tabela de config). */
    public const TICKET_VALIDATION = 1;
    // S2+/fases seguintes: CHANGE_VALIDATION, TICKET_SOLUTION, etc.

    public $dohistory = false;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_approvalbymail_config';
    }

    static function getTypeName($nb = 0)
    {
        return __('Approval by Mail', 'approvalbymail');
    }

    static function canView()
    {
        return Session::haveRight('config', READ);
    }

    static function canCreate()
    {
        return false;
    }

    static function canUpdate()
    {
        return Session::haveRight('config', UPDATE);
    }

    static function canDelete()
    {
        return false;
    }

    static function canPurge()
    {
        return false;
    }

    // ---- Aba dentro de Config (Configurar > Geral) ----

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate && $item instanceof Config) {
            return self::getTypeName();
        }
        return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Config) {
            self::showConfigForm();
        }
        return true;
    }

    public static function showConfigForm(): void
    {
        /** @var DBmysql $DB */
        global $DB;

        $action = Plugin::getWebDir('approvalbymail') . '/front/config.form.php';

        echo '<form name="approvalbymail_config" method="post" action="' . htmlspecialchars($action) . '">';
        echo '<table class="tab_cadre_fixe">';
        echo '<tr><th colspan="3">' . self::getTypeName() . '</th></tr>';
        echo '<tr class="tab_bg_1">';
        echo '<th>' . __('Action', 'glpi') . '</th>';
        echo '<th>' . __('Description', 'glpi') . '</th>';
        echo '<th>' . __('Active', 'glpi') . '</th>';
        echo '</tr>';

        foreach ($DB->request(['FROM' => self::getTable(), 'ORDER' => 'id']) as $row) {
            echo '<tr class="tab_bg_1">';
            echo '<td>' . htmlspecialchars((string) $row['name']) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['content'] ?? '')) . '</td>';
            echo '<td>';
            Dropdown::showYesNo('is_active_' . (int) $row['id'], (int) $row['is_active']);
            echo '</td>';
            echo '</tr>';
        }

        echo '<tr class="tab_bg_2"><td colspan="3" class="center">';
        echo Html::submit(_x('button', 'Save'), [
            'name'  => 'update_config',
            'class' => 'btn btn-primary',
        ]);
        echo '</td></tr>';
        echo '</table>';
        Html::closeForm();
    }

    // ---- Cripto (Padrão SDB-1): chave gerenciada pelo GLPI ----

    /**
     * Cifra um texto usando a chave do GLPI (GLPIKey).
     */
    public static function encrypt(string $plaintext): string
    {
        return (new GLPIKey())->encrypt($plaintext);
    }

    /**
     * Decifra um texto; retorna null em caso de falha (token adulterado/ inválido).
     */
    public static function decrypt(string $ciphertext): ?string
    {
        try {
            // '+' vira espaço ao passar por URL — desfaz antes de decifrar.
            return (new GLPIKey())->decrypt(str_replace(' ', '+', $ciphertext));
        } catch (\Throwable $e) {
            return null;
        }
    }
}

