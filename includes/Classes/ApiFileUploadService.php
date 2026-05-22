<?php

namespace ProjectSend\Classes;

class ApiFileUploadService
{
    /**
     * @return void exits via ApiResponse
     */
    public function handleUpload()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            ApiResponse::error(405, 'method_not_allowed', __('Method not allowed.', 'cftp_admin'));
        }

        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            ApiResponse::error(400, 'missing_file', __('No file was uploaded.', 'cftp_admin'));
        }

        $original_filename = !empty($_POST['filename'])
            ? basename((string)$_POST['filename'])
            : basename((string)$_FILES['file']['name']);

        if (!file_is_allowed($original_filename)) {
            ApiResponse::error(422, 'invalid_file_type', __('Invalid file extension.', 'cftp_admin'));
        }

        $upload_error = $_FILES['file']['error'] ?? UPLOAD_ERR_OK;
        if ($upload_error !== UPLOAD_ERR_OK) {
            ApiResponse::error(400, 'upload_error', __('File upload failed.', 'cftp_admin'));
        }

        $temp_dir = ROOT_DIR . DS . 'upload' . DS . 'temp' . DS . 'api';
        if (!is_dir($temp_dir)) {
            @mkdir($temp_dir, 0755, true);
        }

        $temp_path = $temp_dir . DS . 'api_' . bin2hex(random_bytes(16)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_filename);

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $temp_path)) {
            ApiResponse::error(500, 'storage_error', __('Could not store uploaded file.', 'cftp_admin'));
        }

        @set_time_limit(UPLOAD_TIME_LIMIT);

        $pipeline = new FileUploadPipeline();
        $result = $pipeline->finalizeUpload($temp_path, $original_filename, [
            'storage_selection' => $_POST['storage'] ?? get_option('default_upload_storage', 'local'),
            'encrypt_file' => $_POST['encrypt'] ?? null,
        ]);

        if ($result['status'] !== 'success') {
            @unlink($temp_path);
            $message = $result['message'] ?? __('Upload failed.', 'cftp_admin');
            $code = (strpos(strtolower($message), 'quota') !== false) ? 'quota_exceeded' : 'upload_failed';
            ApiResponse::error(400, $code, $message);
        }

        /** @var Files $file */
        $file = $result['file'];

        $description = isset($_POST['description']) ? sanitize_description($_POST['description']) : '';
        $title = isset($_POST['title']) ? encode_html($_POST['title']) : '';

        $group_ids = api_parse_group_ids_from_request();
        $assignment_result = $this->applyGroupAssignments($file, $group_ids, $description, $title);

        $download_url = BASE_URI . 'download.php?id=' . (int)$file->getId();

        ApiResponse::success(201, [
            'id' => (int)$file->getId(),
            'filename' => $file->filename_original,
            'size' => (int)$file->size,
            'public_token' => $file->public_token,
            'encrypted' => (int)$result['encrypted'],
            'download_url' => $download_url,
            'groups_assigned' => $assignment_result['assigned'],
            'groups_invalid' => $assignment_result['invalid'],
        ]);
    }

    /**
     * Assign file to client groups (not individual clients).
     *
     * @param Files $file
     * @param array $group_ids
     * @return array{assigned: int[], invalid: int[], skipped?: string}
     */
    private function applyGroupAssignments(Files $file, array $group_ids, $description = '', $title = '')
    {
        if (empty($group_ids)) {
            if ($description !== '' || $title !== '') {
                $this->updateFileMetadata($file, $description, $title);
            }
            return ['assigned' => [], 'invalid' => []];
        }

        if (!current_user_can('edit_files')) {
            ApiResponse::error(403, 'forbidden', __('Assigning groups requires edit files permission.', 'cftp_admin'));
        }

        if (!current_user_can('manage_groups')) {
            ApiResponse::error(403, 'forbidden', __('Assigning client groups requires manage groups permission.', 'cftp_admin'));
        }

        $validation = api_validate_group_ids($group_ids);
        if (!empty($validation['invalid'])) {
            ApiResponse::error(422, 'invalid_group', __('One or more group IDs are invalid or not allowed.', 'cftp_admin'));
        }

        $save_data = [
            'name' => $title !== '' ? $title : $file->filename_original,
            'description' => $description,
            'assignments' => [
                'clients' => [],
                'groups' => $validation['valid'],
            ],
        ];

        if (!$file->save($save_data)) {
            ApiResponse::error(500, 'assignment_failed', __('File uploaded but group assignment failed.', 'cftp_admin'));
        }

        return [
            'assigned' => $validation['valid'],
            'invalid' => [],
        ];
    }

    private function updateFileMetadata(Files $file, $description, $title)
    {
        if (!current_user_can('edit_files')) {
            return;
        }

        if ($description === '' && $title === '') {
            return;
        }

        $file->refresh();
        $file->save([
            'name' => $title !== '' ? $title : $file->filename_original,
            'description' => $description,
            'assignments' => [
                'clients' => $file->assignments_clients,
                'groups' => $file->assignments_groups,
            ],
        ]);
    }
}
