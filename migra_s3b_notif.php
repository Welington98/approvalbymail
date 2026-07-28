<?php

/**
 * migra_s3b_notif.php — recria os modelos de notificação do approvalbymail.
 *
 * Cirúrgico: chama APENAS PluginApprovalbymailNotification::installNotificationModels(),
 * que é idempotente (faz uninstall + recria os 2 modelos: validação + solução).
 * NÃO toca em tabelas nem nas flags do plugin — por isso é seguro, ao contrário
 * de um `plugin:install --force` que poderia reprocessar o install inteiro.
 *
 * Uso (no servidor, como o usuário que roda o GLPI):
 *   php migra_s3b_notif.php
 *
 * Verificação esperada depois:
 *   SELECT id,name,event,is_active FROM glpi_notifications
 *     WHERE itemtype='PluginApprovalbymailAction';   -- 2 linhas
 */

if (PHP_SAPI !== 'cli') {
    die("CLI only\n");
}

define('GLPI_ROOT', '/var/www/verdanadesk/glpi');
chdir(GLPI_ROOT);

include(GLPI_ROOT . '/inc/includes.php');

if (!class_exists('PluginApprovalbymailNotification')) {
    fwrite(STDERR, "ERRO: PluginApprovalbymailNotification não carregada — o plugin está ativo?\n");
    exit(1);
}

$ok = PluginApprovalbymailNotification::installNotificationModels();

echo $ok
    ? "OK: modelos recriados (validação + solução). Confira glpi_notifications.\n"
    : "FALHOU: veja /var/www/verdanadesk/files/_log/approvalbymail.log\n";

exit($ok ? 0 : 1);
