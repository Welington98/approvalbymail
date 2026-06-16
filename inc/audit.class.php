<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Transparência/responsabilização (item 2 da decisão de segurança).
 *
 * Ao registrar uma decisão por e-mail (bearer-token), grava um acompanhamento
 * (ITILFollowup) atribuído ao DECISOR no chamado, com quem/como/quando/de onde,
 * e um log chave=valor. Privado/público vem do flag FOLLOWUP_PRIVATE.
 *
 * Genérico: serve para Validação (S2) e Solução (S3) — muda só o rótulo $kind.
 */
class PluginApprovalbymailAudit extends CommonDBTM
{
    /**
     * @param int    $tickets_id  Chamado alvo.
     * @param int    $decider_id  Usuário a quem o token pertencia (validador/requerente).
     * @param string $kind        Rótulo: 'Validação' ou 'Solução'.
     * @param string $decision    'approve' | 'reject'.
     * @param string $ip          IP de origem do clique (REMOTE_ADDR).
     */
    public static function recordDecision(
        int $tickets_id,
        int $decider_id,
        string $kind,
        string $decision,
        string $ip
    ): bool {
        if ($tickets_id <= 0) {
            return false;
        }

        $now   = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $label = $decision === 'approve' ? 'APROVADA' : 'RECUSADA';
        $ipTxt = $ip !== '' ? $ip : 'desconhecido';

        // Nome amigável do decisor (sem link).
        $deciderName = 'usuário #' . $decider_id;
        $u = new User();
        if ($decider_id > 0 && $u->getFromDB($decider_id)) {
            $fn = $u->getFriendlyName();
            if ($fn !== '') {
                $deciderName = $fn;
            }
        }

        $is_private = PluginApprovalbymailConfig::isFeatureActive(
            PluginApprovalbymailConfig::FOLLOWUP_PRIVATE
        ) ? 1 : 0;

        $content = sprintf(
            '%s %s via Approval by Mail. Decisor: %s (#%d). Origem: IP %s. Data: %s.',
            $kind,
            $label,
            $deciderName,
            $decider_id,
            $ipTxt,
            Html::convDateTime($now)
        );

        $ok = false;
        try {
            $fup = new ITILFollowup();
            $ok  = (bool) $fup->add([
                'itemtype'   => 'Ticket',
                'items_id'   => $tickets_id,
                'users_id'   => $decider_id,
                'content'    => $content,
                'is_private' => $is_private,
                'date'       => $now,
            ]);
        } catch (\Throwable $e) {
            $ok = false;
        }

        Toolbox::logInFile('approvalbymail', sprintf(
            "op=audit_followup ticket=%d decider=%d kind=%s decision=%s ip=%s private=%d result=%s\n",
            $tickets_id,
            $decider_id,
            $kind,
            $decision,
            $ipTxt,
            $is_private,
            $ok ? 'ok' : 'fail'
        ));

        return $ok;
    }
}
