<?php
/**
 * Serves file downloads for public links, logged-in sessions and login share tokens.
 */

use ProjectSend\Classes\Download;
use ProjectSend\Classes\LoginShareToken;
use ProjectSend\Classes\Users;

require_once 'bootstrap.php';

$page_id = 'public_download';

if (empty($_GET['id']) || !is_numeric($_GET['id'])) {
    exit_with_error_code(403);
}

$file_id = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);
$file = new \ProjectSend\Classes\Files($file_id);

if (!$file->recordExists() || $file->expired == true) {
    exit_with_error_code(403);
}

$can_download = false;
$can_view = false;
$download_requested = isset($_GET['download']);
$login_token = !empty($_GET['login_token']) ? trim((string)$_GET['login_token']) : null;
$public_token = !empty($_GET['token']) ? htmlentities($_GET['token']) : null;

if (!empty($login_token)) {
    $login_share_token = new LoginShareToken();
    $token_row = $login_share_token->findByPlainToken($login_token);
    if ($token_row === false) {
        exit_with_error_code(403);
    }

    $token_user = new Users((int)$token_row['user_id']);
    if (!$token_user->userExists() || !$token_user->isActive()) {
        exit_with_error_code(403);
    }

    if (!user_can_download_file($token_user->id, $file->id)) {
        exit_with_error_code(403);
    }

    $login_share_token->touchLastUsed((int)$token_row['id']);
    $can_download = true;
    $can_view = true;
    $file->public_url = BASE_URI . 'download.php?id=' . $file->id . '&login_token=' . rawurlencode($login_token);

    if ($download_requested) {
        $process = new Download();
        $process->downloadAsUser($file->id, $token_user->id, $token_user->isClient());
        exit;
    }
} elseif (!empty($public_token)) {
    if ($file->public_token != $public_token) {
        exit_with_error_code(403);
    }

    if ($file->public == 1) {
        $can_download = true;
        $can_view = true;
    }

    if (get_option('enable_landing_for_all_files') == '1') {
        $can_view = true;
    } else {
        if ($file->public == 0) {
            exit_with_error_code(403);
        }
    }

    if ($can_download && $download_requested) {
        record_new_download(0, $file->id);

        $logger = new \ProjectSend\Classes\ActionsLog;
        $logger->addEntry([
            'action' => 37,
            'owner_user' => null,
            'owner_id' => 0,
            'affected_file' => $file->id,
            'affected_file_name' => $file->filename_original,
        ]);

        $process = new Download;
        $alias = $process->getAlias($file);
        $process->serveFile($file->full_path, $file->filename_unfiltered, $alias);
        exit;
    }
} elseif (user_is_logged_in() && defined('CURRENT_USER_ID') && user_can_download_file(CURRENT_USER_ID, $file->id)) {
    $can_download = true;
    $can_view = true;
    $file->public_url = BASE_URI . 'download.php?id=' . $file->id;

    if ($download_requested) {
        $process = new Download();
        $process->download($file->id);
        exit;
    }
} else {
    exit_with_error_code(403);
}

$dont_redirect_if_logged = 1;

require get_template_file_location('public-download.php');
