<?php

namespace ProjectSend\Classes;

use PDO;

class LoginShareToken
{
    private PDO $dbh;

    public function __construct(?PDO $dbh = null)
    {
        if (empty($dbh)) {
            global $dbh;
        }

        $this->dbh = $dbh;
    }

    public static function generatePlainToken(): string
    {
        return 'ps_login_' . bin2hex(random_bytes(32));
    }

    public static function hashToken(string $plain_token): string
    {
        return hash('sha256', $plain_token);
    }

    public static function extractPrefix(string $plain_token): string
    {
        return substr($plain_token, 0, 16);
    }

    /**
     * @return array{plain: string, id: int, prefix: string, expires_at: string}|false
     */
    public function create(int $user_id, string $session_id, int $ttl_seconds = 86400)
    {
        $user_id = (int)$user_id;
        $session_id = trim($session_id);

        if ($user_id < 1 || $session_id === '') {
            return false;
        }

        $plain = self::generatePlainToken();
        $hash = self::hashToken($plain);
        $prefix = self::extractPrefix($plain);
        $expires_at = gmdate('Y-m-d H:i:s', time() + $ttl_seconds);

        $statement = $this->dbh->prepare(
            'INSERT INTO ' . TABLE_LOGIN_SHARE_TOKENS . '
            (user_id, session_id, token_hash, token_prefix, expires_at)
            VALUES (:user_id, :session_id, :token_hash, :token_prefix, :expires_at)'
        );
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->bindParam(':session_id', $session_id);
        $statement->bindParam(':token_hash', $hash);
        $statement->bindParam(':token_prefix', $prefix);
        $statement->bindParam(':expires_at', $expires_at);
        $statement->execute();

        return [
            'plain' => $plain,
            'id' => (int)$this->dbh->lastInsertId(),
            'prefix' => $prefix,
            'expires_at' => $expires_at,
        ];
    }

    /**
     * @return array<string, mixed>|false
     */
    public function findByPlainToken(string $plain_token)
    {
        if (empty($plain_token) || !preg_match('/^ps_login_[a-f0-9]{64}$/', $plain_token)) {
            return false;
        }

        $hash = self::hashToken($plain_token);
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->dbh->prepare(
            'SELECT * FROM ' . TABLE_LOGIN_SHARE_TOKENS . '
            WHERE token_hash = :hash
            AND revoked_at IS NULL
            AND expires_at > :now
            LIMIT 1'
        );
        $statement->bindParam(':hash', $hash);
        $statement->bindParam(':now', $now);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : false;
    }

    public function touchLastUsed(int $token_id): void
    {
        $last_used_at = gmdate('Y-m-d H:i:s');
        $statement = $this->dbh->prepare(
            'UPDATE ' . TABLE_LOGIN_SHARE_TOKENS . ' SET last_used_at = :last_used_at WHERE id = :id'
        );
        $statement->bindParam(':last_used_at', $last_used_at);
        $statement->bindParam(':id', $token_id, PDO::PARAM_INT);
        $statement->execute();
    }

    public function revokeBySessionId(string $session_id, int $user_id): int
    {
        $revoked_at = gmdate('Y-m-d H:i:s');
        $statement = $this->dbh->prepare(
            'UPDATE ' . TABLE_LOGIN_SHARE_TOKENS . '
            SET revoked_at = :revoked_at
            WHERE session_id = :session_id
            AND user_id = :user_id
            AND revoked_at IS NULL'
        );
        $statement->bindParam(':revoked_at', $revoked_at);
        $statement->bindParam(':session_id', $session_id);
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }

    public function revokeAllForUser(int $user_id): int
    {
        $revoked_at = gmdate('Y-m-d H:i:s');
        $statement = $this->dbh->prepare(
            'UPDATE ' . TABLE_LOGIN_SHARE_TOKENS . '
            SET revoked_at = :revoked_at
            WHERE user_id = :user_id
            AND revoked_at IS NULL'
        );
        $statement->bindParam(':revoked_at', $revoked_at);
        $statement->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount();
    }

    public function cleanupExpiredTokens(): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->dbh->prepare(
            'DELETE FROM ' . TABLE_LOGIN_SHARE_TOKENS . ' WHERE expires_at <= :now'
        );
        $statement->bindParam(':now', $now);
        $statement->execute();

        return $statement->rowCount();
    }
}
