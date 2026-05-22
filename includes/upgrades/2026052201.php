<?php

function upgrade_2026052201()
{
    global $dbh;

    $query = "CREATE TABLE IF NOT EXISTS " . TABLE_API_TOKENS . " (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        name varchar(255) NOT NULL,
        token_hash varchar(64) NOT NULL,
        token_prefix varchar(16) NOT NULL,
        scopes varchar(255) NOT NULL DEFAULT 'upload',
        expires_at timestamp NULL DEFAULT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        last_used_at timestamp NULL DEFAULT NULL,
        revoked_at timestamp NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY idx_token_hash (token_hash),
        INDEX idx_user_id (user_id),
        INDEX idx_revoked_at (revoked_at),
        FOREIGN KEY (user_id) REFERENCES " . TABLE_USERS . "(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

    $statement = $dbh->prepare($query);
    $statement->execute();

    add_option_if_not_exists('api_enabled', '0');
    add_option_if_not_exists('api_rate_limit_per_minute', '60');
}
