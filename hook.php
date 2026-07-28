<?php
/**
 * Instalação/desinstalação do plugin approval by mail.
 * Padrão SDB: simétrico, idempotente, sem SQL com input concatenado.
 */

function plugin_approvalbymail_install(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

    // --- Tabela de configuração (feature flags) ---
    $config_table = PluginApprovalbymailConfig::getTable();
    if (!$DB->tableExists($config_table)) {
        $DB->doQuery(
            "CREATE TABLE IF NOT EXISTS `$config_table` (
                `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`      VARCHAR(100) NOT NULL,
                `content`   VARCHAR(255) NULL DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT '0',
                `date_mod`  TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Seeds: validação (ON), solução (OFF — opt-in), followup privado (ON).
        $DB->insert($config_table, [
            'id'        => PluginApprovalbymailConfig::TICKET_VALIDATION,
            'name'      => 'Ticket - Aprovação de Validação',
            'content'   => 'Envia e-mail para aprovar/recusar a validação de chamado',
            'is_active' => 1,
            'date_mod'  => $now,
        ]);
        $DB->insert($config_table, [
            'id'        => PluginApprovalbymailConfig::TICKET_SOLUTION,
            'name'      => 'Ticket - Aprovação de Solução',
            'content'   => 'Envia e-mail para o requerente aprovar/recusar a solução do chamado',
            'is_active' => 0,
            'date_mod'  => $now,
        ]);
        $DB->insert($config_table, [
            'id'        => PluginApprovalbymailConfig::FOLLOWUP_PRIVATE,
            'name'      => 'Acompanhamento de auditoria — privado',
            'content'   => 'Sim = acompanhamento privado (só técnicos); Não = público',
            'is_active' => 1,
            'date_mod'  => $now,
        ]);
        $DB->insert($config_table, [
            'id'        => PluginApprovalbymailConfig::LOGO,
            'name'      => 'URL da Logo',
            'content'   => '',
            'is_active' => 1,
            'date_mod'  => $now,
        ]);
    }

    // --- Tabela de ações tokenizadas ---
    $action_table = PluginApprovalbymailAction::getTable();
    if (!$DB->tableExists($action_table)) {
        $userfk = User::getForeignKeyField();
        $DB->doQuery(
            "CREATE TABLE IF NOT EXISTS `$action_table` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `$userfk`       INT UNSIGNED NOT NULL DEFAULT '0',
                `items_id`      INT UNSIGNED NOT NULL DEFAULT '0',
                `itemtype`      VARCHAR(100) NOT NULL,
                `token`         VARCHAR(128) NOT NULL,
                `used_at`       TIMESTAMP NULL DEFAULT NULL,
                `date_creation` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `itemtype_items_id` (`itemtype`, `items_id`),
                KEY `token` (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // --- Garante linha da logo (migração) ---
    $logo_exists = false;
    foreach ($DB->request(['FROM' => $config_table, 'WHERE' => ['id' => PluginApprovalbymailConfig::LOGO]]) as $_row) {
        $logo_exists = true;
    }
    if (!$logo_exists) {
        $DB->insert($config_table, [
            'id'        => PluginApprovalbymailConfig::LOGO,
            'name'      => 'URL da Logo',
            'content'   => '',
            'is_active' => 1,
            'date_mod'  => $now,
        ]);
    }

    // --- Modelos de notificação (S2) ---
    if (!PluginApprovalbymailNotification::installNotificationModels()) {
        return false;
    }

    return true;
}

function plugin_approvalbymail_uninstall(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    PluginApprovalbymailNotification::uninstallNotificationModels();

    foreach ([
        PluginApprovalbymailAction::getTable(),
        PluginApprovalbymailConfig::getTable(),
    ] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    return true;
}
