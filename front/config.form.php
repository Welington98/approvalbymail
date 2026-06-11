<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

$plugin = new Plugin();
if (!$plugin->isActivated('approvalbymail')) {
    Html::displayNotFoundError();
    return;
}

Session::checkRight('config', UPDATE);

if (isset($_POST['update_config'])) {
    $config = new PluginApprovalbymailConfig();

    foreach ([PluginApprovalbymailConfig::TICKET_VALIDATION, PluginApprovalbymailConfig::TICKET_SOLUTION, PluginApprovalbymailConfig::FOLLOWUP_PRIVATE] as $id) {
        if ($config->getFromDB($id)) {
            $new = isset($_POST['is_active_' . $id]) ? (int) $_POST['is_active_' . $id] : 0;
            if ((int) $config->fields['is_active'] !== $new) {
                $config->update([
                    'id'        => $id,
                    'is_active' => $new,
                    'date_mod'  => $_SESSION['glpi_currenttime'],
                ]);
            }
        }
    }

    Session::addMessageAfterRedirect(__('Configuration updated', 'approvalbymail'), true, INFO);
}

// Sempre retorna para a pagina de configuracao (evita ERR_TOO_MANY_REDIRECTS).
Html::redirect(Plugin::getWebDir('approvalbymail') . '/front/config.php');

