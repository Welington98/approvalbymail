<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

$plugin = new Plugin();
if (!$plugin->isActivated('approvalbymail')) {
    Html::displayNotFoundError();
    return;
}

// Só quem pode configurar o GLPI mexe nos flags.
Session::checkRight('config', UPDATE);

if (isset($_POST['update_config'])) {
    $config = new PluginApprovalbymailConfig();

    foreach ([PluginApprovalbymailConfig::TICKET_VALIDATION] as $id) {
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

Html::back();

