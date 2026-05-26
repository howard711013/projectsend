<?php

function upgrade_2026052601()
{
    global $dbh;

    $query = "CREATE TABLE IF NOT EXISTS " . TABLE_LOGIN_SHARE_TOKENS . " (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        session_id varchar(128) NOT NULL,
        token_hash varchar(64) NOT NULL,
        token_prefix varchar(16) NOT NULL,
        expires_at timestamp NOT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        last_used_at timestamp NULL DEFAULT NULL,
        revoked_at timestamp NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY idx_login_share_token_hash (token_hash),
        INDEX idx_login_share_user_id (user_id),
        INDEX idx_login_share_session_id (session_id),
        INDEX idx_login_share_revoked_at (revoked_at),
        INDEX idx_login_share_expires_at (expires_at),
        FOREIGN KEY (user_id) REFERENCES " . TABLE_USERS . "(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

    $statement = $dbh->prepare($query);
    $statement->execute();
}
