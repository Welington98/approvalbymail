<?php

include('../../../inc/includes.php');

Session::checkLoginUser();

$plugin = new Plugin();
if (!$plugin->isActivated('approvalbymail')) {
    Html::displayNotFoundError();
    return;
}

Session::checkRight('config', READ);

Html::header(
    PluginApprovalbymailConfig::getTypeName(),
    $_SERVER['PHP_SELF'],
    'config',
    'plugins'
);

PluginApprovalbymailConfig::showConfigForm();

Html::footer();
