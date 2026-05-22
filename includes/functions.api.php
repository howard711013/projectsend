<?php

/**
 * Set global user constants for API requests (no PHP session).
 */
function api_set_current_user(\ProjectSend\Classes\Users $session_user)
{
    if (!$session_user->userExists() || !$session_user->isActive()) {
        return false;
    }

    if (!defined('CURRENT_USER_ID')) {
        define('CURRENT_USER_ID', $session_user->id);
    }
    if (!defined('CURRENT_USER_USERNAME')) {
        define('CURRENT_USER_USERNAME', $session_user->username);
    }
    if (!defined('CURRENT_USER_NAME')) {
        define('CURRENT_USER_NAME', $session_user->name);
    }
    if (!defined('CURRENT_USER_EMAIL')) {
        define('CURRENT_USER_EMAIL', $session_user->email);
    }
    if (!defined('CURRENT_USER_LEVEL')) {
        define('CURRENT_USER_LEVEL', intval($session_user->role));
    }
    if (!defined('CURRENT_USER_TYPE')) {
        define('CURRENT_USER_TYPE', $session_user->account_type);
    }

    if (!defined('UPLOAD_MAX_FILESIZE')) {
        if ($session_user->max_file_size == 0 || empty($session_user->max_file_size)) {
            define('UPLOAD_MAX_FILESIZE', (int)MAX_FILESIZE);
        } else {
            define('UPLOAD_MAX_FILESIZE', (int)$session_user->max_file_size);
        }
    }

    if (!defined('CURRENT_USER_DISK_QUOTA')) {
        define('CURRENT_USER_DISK_QUOTA', (int)$session_user->max_disk_quota);
    }
    if (!defined('CURRENT_USER_DISK_USAGE')) {
        define('CURRENT_USER_DISK_USAGE', get_user_disk_usage(CURRENT_USER_ID));
    }

    global $permissions;
    $permissions = new \ProjectSend\Classes\Permissions(CURRENT_USER_ID);

    return true;
}

/**
 * Group IDs the current user may assign to files via API.
 *
 * @return array<int, string> id => name
 */
function api_get_assignable_groups()
{
    if (!current_user_can('manage_groups')) {
        return [];
    }

    $get_user = new \ProjectSend\Classes\Users(CURRENT_USER_ID);
    if (!empty($get_user->limit_upload_to)) {
        return file_editor_get_groups_by_members($get_user->limit_upload_to);
    }

    return file_editor_get_all_groups();
}

/**
 * Validate and normalize group IDs from API input.
 *
 * @param array<int|string> $group_ids
 * @return array{valid: int[], invalid: int[]}
 */
function api_validate_group_ids($group_ids)
{
    if (!is_array($group_ids)) {
        $group_ids = [$group_ids];
    }

    $group_ids = array_values(array_unique(array_filter(array_map('intval', $group_ids))));
    $allowed = api_get_assignable_groups();
    $valid = [];
    $invalid = [];

    foreach ($group_ids as $id) {
        if ($id < 1) {
            continue;
        }
        if (isset($allowed[$id])) {
            $valid[] = $id;
        } else {
            $invalid[] = $id;
        }
    }

    return ['valid' => $valid, 'invalid' => $invalid];
}

/**
 * Parse group_ids from multipart or JSON body.
 *
 * @return array<int|string>
 */
function api_parse_group_ids_from_request()
{
    if (!empty($_POST['group_ids']) && is_array($_POST['group_ids'])) {
        return $_POST['group_ids'];
    }

    if (!empty($_POST['group_ids'])) {
        return explode(',', (string)$_POST['group_ids']);
    }

    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json) && !empty($json['group_ids'])) {
            return is_array($json['group_ids']) ? $json['group_ids'] : explode(',', (string)$json['group_ids']);
        }
    }

    return [];
}
