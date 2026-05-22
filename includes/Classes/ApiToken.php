<?php

namespace ProjectSend\Classes;

use PDO;

class ApiToken
{
    private $dbh;
    private $logger;

    public function __construct(?PDO $dbh = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }
        $this->dbh = $dbh;
        $this->logger = new ActionsLog();
    }

    public static function generatePlainToken()
    {
        return 'ps_live_' . bin2hex(random_bytes(32));
    }

    public static function hashToken($plain_token)
    {
        return hash('sha256', $plain_token);
    }

    public static function extractPrefix($plain_token)
    {
        return substr($plain_token, 0, 16);
    }

    /**
     * @return array{plain: string, id: int, prefix: string}|false
     */
    public function create($user_id, $name, $expires_at = null)
    {
        $user_id = (int)$user_id;
        $name = trim($name);
        if ($user_id < 1 || $name === '') {
            return false;
        }

        $plain = self::generatePlainToken();
        $hash = self::hashToken($plain);
        $prefix = self::extractPrefix($plain);

        $statement = $this->dbh->prepare(
            "INSERT INTO " . TABLE_API_TOKENS . "
            (user_id, name, token_hash, token_prefix, scopes, expires_at)
            VALUES (:user_id, :name, :token_hash, :token_prefix, 'upload', :expires_at)"
        );
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->bindParam(':name', $name);
        $statement->bindParam(':token_hash', $hash);
        $statement->bindParam(':token_prefix', $prefix);
        $statement->bindParam(':expires_at', $expires_at);
        $statement->execute();

        return [
            'plain' => $plain,
            'id' => (int)$this->dbh->lastInsertId(),
            'prefix' => $prefix,
        ];
    }

    /**
     * @return array|false
     */
    public function findByPlainToken($plain_token)
    {
        if (empty($plain_token) || !preg_match('/^ps_live_[a-f0-9]{64}$/', $plain_token)) {
            return false;
        }

        $hash = self::hashToken($plain_token);
        $statement = $this->dbh->prepare(
            "SELECT * FROM " . TABLE_API_TOKENS . "
            WHERE token_hash = :hash AND revoked_at IS NULL
            LIMIT 1"
        );
        $statement->bindParam(':hash', $hash);
        $statement->execute();

        if ($statement->rowCount() < 1) {
            return false;
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
            return false;
        }

        return $row;
    }

    public function touchLastUsed($token_id)
    {
        $token_id = (int)$token_id;
        $statement = $this->dbh->prepare(
            "UPDATE " . TABLE_API_TOKENS . " SET last_used_at = NOW() WHERE id = :id"
        );
        $statement->bindParam(':id', $token_id, PDO::PARAM_INT);
        $statement->execute();
    }

    public function revoke($token_id, $user_id)
    {
        $token_id = (int)$token_id;
        $user_id = (int)$user_id;
        $statement = $this->dbh->prepare(
            "UPDATE " . TABLE_API_TOKENS . " SET revoked_at = NOW()
            WHERE id = :id AND user_id = :user_id AND revoked_at IS NULL"
        );
        $statement->bindParam(':id', $token_id, PDO::PARAM_INT);
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() > 0;
    }

    /**
     * @return array<int, array>
     */
    public function listForUser($user_id)
    {
        $user_id = (int)$user_id;
        $statement = $this->dbh->prepare(
            "SELECT id, name, token_prefix, scopes, expires_at, created_at, last_used_at, revoked_at
            FROM " . TABLE_API_TOKENS . "
            WHERE user_id = :user_id
            ORDER BY created_at DESC"
        );
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countActiveForUser($user_id)
    {
        $user_id = (int)$user_id;
        $statement = $this->dbh->prepare(
            "SELECT COUNT(*) FROM " . TABLE_API_TOKENS . "
            WHERE user_id = :user_id AND revoked_at IS NULL"
        );
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->execute();

        return (int)$statement->fetchColumn();
    }
}
