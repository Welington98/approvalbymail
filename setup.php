<?php
/**
 * approval by mail — ação por e-mail no GLPI.
 * Fork modernizado (Padrão SDB) do plugin "SDB - Ação por e-mail" (GPLv3).
 */

define('PLUGIN_APPROVALBYMAIL_VERSION', '0.0.1-alpha');
define('PLUGIN_APPROVALBYMAIL_MIN_GLPI', '10.0.0');
define('PLUGIN_APPROVALBYMAIL_MAX_GLPI', '10.0.99');

/**
 * Inicialização do plugin (chamada em todo carregamento do GLPI).
 */
function plugin_init_approvalbymail(): void
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    // Conformidade CSRF dos formulários do plugin.
    $PLUGIN_HOOKS['csrf_compliant']['approvalbymail'] = true;

    $plugin = new Plugin();
    if (!$plugin->isInstalled('approvalbymail') || !$plugin->isActivated('approvalbymail')) {
        return;
    }

    // Aba de configuração dentro de "Configurar > Geral".
    Plugin::registerClass(PluginApprovalbymailConfig::class, [
        'addtabon' => Config::class,
    ]);

    // Link de engrenagem na lista de plugins.
    $PLUGIN_HOOKS['config_page']['approvalbymail'] = 'front/config.php';

    // S1+: aqui entram os hooks 'item_add' (TicketValidation) e afins.
}

/**
 * Metadados do plugin.
 */
function plugin_version_approvalbymail(): array
{
    return [
        'name'           => 'Approval by Mail',
        'version'        => PLUGIN_APPROVALBYMAIL_VERSION,
        'author'         => 'Carlos Alberto Correa Filho - IPT.br',
        'license'        => 'GPLv3',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_APPROVALBYMAIL_MIN_GLPI,
                'max' => PLUGIN_APPROVALBYMAIL_MAX_GLPI,
            ],
        ],
    ];
}

/**
 * Pré-requisitos (a faixa de versão do GLPI já é validada via requirements).
 */
function plugin_approvalbymail_check_prerequisites(): bool
{
    return true;
}

/**
 * Verificação de configuração mínima para ativar.
 */
function plugin_approvalbymail_check_config($verbose = false): bool
{
    return true;
}

