<?php

namespace ProjectSend\Classes;

/**
 * Shared upload pipeline for Plupload and API uploads.
 */
class FileUploadPipeline
{
    /**
     * @param string $file_path Absolute path to assembled file on disk
     * @param string $original_filename Client-facing filename
     * @param array $options storage_selection, encrypt_file (0|1)
     * @return array{status: string, message?: string, file?: Files, id?: int, encrypted?: int, public_token?: string}
     */
    public function finalizeUpload($file_path, $original_filename, array $options = [])
    {
        if (!file_exists($file_path)) {
            return [
                'status' => 'error',
                'message' => __('Uploaded file not found.', 'cftp_admin'),
            ];
        }

        $storage_selection = $options['storage_selection'] ?? get_option('default_upload_storage', 'local');
        if ($storage_selection !== 'local' && !current_user_can('upload_storage_select')) {
            $storage_selection = get_option('default_upload_storage', 'local');
        }

        $encrypt_file = $this->resolveEncryptionFlag($options);

        $encryption_metadata = $this->applyEncryption($file_path, $encrypt_file);
        if ($encryption_metadata === false) {
            return [
                'status' => 'error',
                'message' => __('File encryption failed.', 'cftp_admin'),
            ];
        }

        $file = new Files();
        $file->encrypted = $encryption_metadata['encrypted'];
        $file->encryption_key_encrypted = $encryption_metadata['encryption_key_encrypted'];
        $file->encryption_iv = $encryption_metadata['encryption_iv'];
        $file->encryption_algorithm = $encryption_metadata['encryption_algorithm'];
        $file->encryption_file_iv = $encryption_metadata['encryption_file_iv'];

        $route_result = $file->routeToStorage($file_path, $storage_selection, $original_filename);

        if (!$route_result || !isset($route_result['filename_original'])) {
            return [
                'status' => 'error',
                'message' => __('Failed to process file upload to selected storage.', 'cftp_admin'),
            ];
        }

        $file->setDefaults();
        $result = $file->addToDatabase();

        if ($result['status'] !== 'success') {
            return $result;
        }

        return [
            'status' => 'success',
            'file' => $file,
            'id' => $file->getId(),
            'encrypted' => (int)$encryption_metadata['encrypted'],
            'public_token' => $result['public_token'] ?? $file->public_token,
        ];
    }

    private function resolveEncryptionFlag(array $options)
    {
        if (isset($options['encrypt_file']) && (string)$options['encrypt_file'] === '1') {
            return true;
        }
        if (Encryption::isRequired()) {
            return true;
        }
        if (Encryption::isEnabled()) {
            return true;
        }

        return false;
    }

    /**
     * @return array|false Encryption metadata array, or false on failure
     */
    private function applyEncryption($file_path, $encrypt_file)
    {
        if (!$encrypt_file) {
            return [
                'encrypted' => 0,
                'encryption_key_encrypted' => null,
                'encryption_iv' => null,
                'encryption_algorithm' => null,
                'encryption_file_iv' => null,
            ];
        }

        try {
            $encryption = new Encryption();
            $file_key = $encryption->generateFileKey();
            $encrypted_path = $file_path . '.encrypted';
            $encrypt_result = $encryption->encryptFile($file_path, $encrypted_path, $file_key);

            if (!$encrypt_result['success']) {
                return false;
            }

            $encrypted_key_data = $encryption->encryptFileKey($file_key);
            unlink($file_path);
            rename($encrypted_path, $file_path);

            return [
                'encrypted' => 1,
                'encryption_key_encrypted' => $encrypted_key_data['encrypted_key'],
                'encryption_iv' => $encrypted_key_data['iv'],
                'encryption_algorithm' => $encryption->getAlgorithm(),
                'encryption_file_iv' => $encrypt_result['iv'],
            ];
        } catch (\Exception $e) {
            error_log('File encryption error: ' . $e->getMessage());
            return false;
        }
    }
}
