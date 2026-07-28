<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Ação tokenizada: liga (usuário, item, itemtype) a um token de uso único
 * enviado por e-mail. Núcleo de segurança do plugin (Padrão SDB-3/4).
 */
class PluginApprovalbymailAction extends CommonDBTM
{
    public $dohistory = false;

    /** Validade do token, em dias. */
    public const TOKEN_TTL_DAYS = 7;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_approvalbymail_actions';
    }

    public static function getForeignKeyField()
    {
        return 'plugin_approvalbymail_actions_id';
    }

    static function canView(): bool
    {
        return false;
    }

    static function canCreate(): bool
    {
        return true;
    }

    static function canUpdate(): bool
    {
        return false;
    }

    static function canDelete(): bool
    {
        return false;
    }

    static function canPurge(): bool
    {
        return false;
    }

    // ---------------------------------------------------------------
    // Núcleo do token
    // ---------------------------------------------------------------

    /**
     * Gera um token forte (Padrão SDB-3): 32 bytes aleatórios em hex.
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Cria um registro de ação com token novo.
     * Retorna a instância criada, ou null em caso de falha.
     */
    public static function createAction(int $users_id, string $itemtype, int $items_id): ?self
    {
        if ($itemtype === '' || $items_id <= 0) {
            return null;
        }

        $action = new self();
        $id = $action->add([
            User::getForeignKeyField() => $users_id,
            'items_id'                 => $items_id,
            'itemtype'                 => $itemtype,
            'token'                    => self::generateToken(),
            'used_at'                  => null,
            'date_creation'            => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);

        if (!$id) {
            return null;
        }

        // Recarrega para garantir os campos consistentes (id, token, datas).
        if (!$action->getFromDB($id)) {
            return null;
        }

        return $action;
    }

    /**
     * Payload em claro do link: "id:itemtype:token".
     */
    public function buildHash(): string
    {
        return $this->fields['id'] . ':' . $this->fields['itemtype'] . ':' . $this->fields['token'];
    }

    /**
     * Payload cifrado (via GLPIKey) para colocar no link do e-mail.
     */
    public function getEncryptedHash(): string
    {
        return PluginApprovalbymailConfig::encrypt($this->buildHash());
    }

    /**
     * O token expirou (date_creation + TTL)?
     */
    public function isExpired(): bool
    {
        if (empty($this->fields['date_creation'])) {
            return true;
        }
        $created = strtotime((string) $this->fields['date_creation']);
        if ($created === false) {
            return true;
        }
        return (time() - $created) > (self::TOKEN_TTL_DAYS * 86400);
    }

    /**
     * Já foi consumido (single-use)?
     */
    public function isUsed(): bool
    {
        return !empty($this->fields['used_at']);
    }

    /**
     * Resolve um Hash cifrado para uma ação VÁLIDA, ou null.
     *
     * Faz todas as checagens server-side (Padrão SDB-4):
     * decifra, faz parse, confere token com hash_equals, coerência de
     * itemtype, uso único e expiração. NUNCA confia no que vem da URL.
     */
    public static function resolve(string $encryptedHash): ?self
    {
        $plain = PluginApprovalbymailConfig::decrypt($encryptedHash);
        if ($plain === null) {
            return null;
        }

        $parts = explode(':', $plain, 3);
        if (count($parts) !== 3) {
            return null;
        }
        [$id, $itemtype, $token] = $parts;

        $id = (int) $id;
        if ($id <= 0 || $token === '') {
            return null;
        }

        $action = new self();
        if (!$action->getFromDB($id)) {
            return null;
        }

        // Comparações em tempo constante onde há segredo (token).
        if (!hash_equals((string) $action->fields['token'], $token)) {
            return null;
        }
        if (!hash_equals((string) $action->fields['itemtype'], $itemtype)) {
            return null;
        }
        if ($action->isUsed()) {
            return null;
        }
        if ($action->isExpired()) {
            return null;
        }

        return $action;
    }

    /**
     * Marca o token como consumido (single-use). Chamar dentro de transação
     * junto com a gravação da ação real (no S3).
     */
    public function markUsed(): bool
    {
        return (bool) $this->update([
            'id'      => $this->fields['id'],
            'used_at' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }
}
