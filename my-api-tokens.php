<?php
/**
 * Personal API tokens for REST file upload
 */
require_once 'bootstrap.php';
redirect_if_not_logged_in();

if (!current_user_can('upload')) {
    exit_with_error_code(403);
}

$page_title = __('API keys', 'cftp_admin');
$page_id = 'api_tokens';
$active_nav = 'users';

global $flash;

$api_token = new \ProjectSend\Classes\ApiToken();
$max_tokens = 10;
$new_plain_token = null;

if ($_POST) {
    switch ($_POST['do'] ?? '') {
        case 'create_token':
            $name = trim($_POST['token_name'] ?? '');
            if ($name === '') {
                $flash->error(__('Please enter a name for this API key.', 'cftp_admin'));
                break;
            }

            if ($api_token->countActiveForUser(CURRENT_USER_ID) >= $max_tokens) {
                $flash->error(__('Maximum number of active API keys reached.', 'cftp_admin'));
                break;
            }

            $expires_at = null;
            if (!empty($_POST['expires_days']) && is_numeric($_POST['expires_days'])) {
                $days = (int)$_POST['expires_days'];
                if ($days > 0) {
                    $expires_at = date('Y-m-d H:i:s', time() + ($days * 86400));
                }
            }

            $created = $api_token->create(CURRENT_USER_ID, $name, $expires_at);
            if ($created) {
                $_SESSION['api_new_plain_token'] = $created['plain'];
                $flash->success(__('API key created. Copy it now — it will not be shown again.', 'cftp_admin'));
            } else {
                $flash->error(__('Could not create API key.', 'cftp_admin'));
            }
            break;

        case 'revoke_token':
            $token_id = (int)($_POST['token_id'] ?? 0);
            if ($api_token->revoke($token_id, CURRENT_USER_ID)) {
                $flash->success(__('API key revoked.', 'cftp_admin'));
            } else {
                $flash->error(__('Could not revoke API key.', 'cftp_admin'));
            }
            break;
    }

    ps_redirect(BASE_URI . 'my-api-tokens.php');
}

if (!empty($_SESSION['api_new_plain_token'])) {
    $new_plain_token = $_SESSION['api_new_plain_token'];
    unset($_SESSION['api_new_plain_token']);
}

$tokens = $api_token->listForUser(CURRENT_USER_ID);
$api_enabled = get_option('api_enabled') == '1';
$csrf_token = getCsrfToken();

include_once ADMIN_VIEWS_DIR . DS . 'header.php';
?>

<div class="col-12">
    <h2><?php echo $page_title; ?></h2>

    <?php if (!$api_enabled) { ?>
        <div class="alert alert-warning">
            <?php _e('API upload is disabled. Ask an administrator to enable it under Options → Security.', 'cftp_admin'); ?>
        </div>
    <?php } ?>

    <?php if ($new_plain_token) { ?>
        <div class="alert alert-success">
            <p><strong><?php _e('Your new API key (copy now):', 'cftp_admin'); ?></strong></p>
            <pre class="p-3 bg-light border user-select-all"><?php echo html_output($new_plain_token); ?></pre>
        </div>
    <?php } ?>

    <div class="card mb-4">
        <div class="card-header"><?php _e('Upload via curl', 'cftp_admin'); ?></div>
        <div class="card-body">
            <p><?php _e('Assign files to client groups using group_ids (not individual client IDs).', 'cftp_admin'); ?></p>
            <pre class="bg-light p-3 border"><code>curl -X POST "<?php echo html_output(BASE_URI); ?>api/v1/files" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -F "file=@/path/to/file.pdf" \
  -F "description=Optional" \
  -F "group_ids[]=1" \
  -F "group_ids[]=2"</code></pre>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><?php _e('Create API key', 'cftp_admin'); ?></div>
                <div class="card-body">
                    <form method="post" action="<?php echo BASE_URI; ?>my-api-tokens.php" class="form-horizontal">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />
                        <input type="hidden" name="do" value="create_token" />
                        <div class="mb-3">
                            <label for="token_name" class="form-label"><?php _e('Name', 'cftp_admin'); ?></label>
                            <input type="text" name="token_name" id="token_name" class="form-control" required maxlength="255" placeholder="<?php _e('e.g. CI deployment', 'cftp_admin'); ?>" />
                        </div>
                        <div class="mb-3">
                            <label for="expires_days" class="form-label"><?php _e('Expires in (days, optional)', 'cftp_admin'); ?></label>
                            <input type="number" name="expires_days" id="expires_days" class="form-control" min="1" max="3650" />
                        </div>
                        <button type="submit" class="btn btn-primary" <?php echo $api_enabled ? '' : 'disabled'; ?>><?php _e('Generate key', 'cftp_admin'); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><?php _e('Your API keys', 'cftp_admin'); ?></div>
                <div class="card-body">
                    <?php if (empty($tokens)) { ?>
                        <p class="text-muted"><?php _e('No API keys yet.', 'cftp_admin'); ?></p>
                    <?php } else { ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Name', 'cftp_admin'); ?></th>
                                    <th><?php _e('Prefix', 'cftp_admin'); ?></th>
                                    <th><?php _e('Created', 'cftp_admin'); ?></th>
                                    <th><?php _e('Last used', 'cftp_admin'); ?></th>
                                    <th><?php _e('Status', 'cftp_admin'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tokens as $row) {
                                    $revoked = !empty($row['revoked_at']);
                                    ?>
                                    <tr>
                                        <td><?php echo html_output($row['name']); ?></td>
                                        <td><code><?php echo html_output($row['token_prefix']); ?>…</code></td>
                                        <td><?php echo format_date($row['created_at']); ?></td>
                                        <td><?php echo !empty($row['last_used_at']) ? format_date($row['last_used_at']) : '—'; ?></td>
                                        <td>
                                            <?php if ($revoked) {
                                                _e('Revoked', 'cftp_admin');
                                            } elseif (!empty($row['expires_at']) && strtotime($row['expires_at']) < time()) {
                                                _e('Expired', 'cftp_admin');
                                            } else {
                                                _e('Active', 'cftp_admin');
                                            } ?>
                                        </td>
                                        <td>
                                            <?php if (!$revoked) { ?>
                                            <form method="post" action="<?php echo BASE_URI; ?>my-api-tokens.php" class="d-inline" onsubmit="return confirm('<?php echo htmlspecialchars(__('Revoke this key?', 'cftp_admin'), ENT_QUOTES, 'UTF-8'); ?>');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" />
                                                <input type="hidden" name="do" value="revoke_token" />
                                                <input type="hidden" name="token_id" value="<?php echo (int)$row['id']; ?>" />
                                                <button type="submit" class="btn btn-sm btn-danger"><?php _e('Revoke', 'cftp_admin'); ?></button>
                                            </form>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_VIEWS_DIR . DS . 'footer.php'; ?>
