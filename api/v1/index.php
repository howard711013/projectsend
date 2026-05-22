<?php

define('IS_API', true);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use ProjectSend\Classes\ApiAuth;
use ProjectSend\Classes\ApiFileUploadService;
use ProjectSend\Classes\ApiResponse;

$auth = new ApiAuth();
$auth_result = $auth->authenticateRequest();

if (!$auth_result['ok']) {
    ApiResponse::error(
        $auth_result['http'],
        $auth_result['code'],
        $auth_result['message']
    );
}

$request_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$request_path = is_string($request_path) ? $request_path : '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Match /api/v1/files (with optional trailing slash and install subfolder prefix)
if ($method === 'POST' && preg_match('#/api/v1/files/?$#', $request_path)) {
    $uploader = new ApiFileUploadService();
    $uploader->handleUpload();
}

ApiResponse::error(404, 'not_found', __('API endpoint not found.', 'cftp_admin'));
