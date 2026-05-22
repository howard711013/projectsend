<?php

namespace ProjectSend\Classes;

class ApiAuth
{
    private $token_row;

    public function authenticateRequest()
    {
        if (get_option('api_enabled') != '1') {
            return [
                'ok' => false,
                'http' => 503,
                'code' => 'api_disabled',
                'message' => __('API access is disabled.', 'cftp_admin'),
            ];
        }

        $plain = $this->extractBearerToken();
        if (empty($plain)) {
            return [
                'ok' => false,
                'http' => 401,
                'code' => 'invalid_token',
                'message' => __('Missing or invalid Authorization header.', 'cftp_admin'),
            ];
        }

        $api_token = new ApiToken();
        $row = $api_token->findByPlainToken($plain);
        if ($row === false) {
            return [
                'ok' => false,
                'http' => 401,
                'code' => 'invalid_token',
                'message' => __('Invalid or expired API token.', 'cftp_admin'),
            ];
        }

        if ($this->isRateLimited($row['id'])) {
            return [
                'ok' => false,
                'http' => 429,
                'code' => 'rate_limited',
                'message' => __('API rate limit exceeded.', 'cftp_admin'),
            ];
        }

        $user = new Users((int)$row['user_id']);
        if (!$user->userExists() || !$user->isActive()) {
            return [
                'ok' => false,
                'http' => 401,
                'code' => 'invalid_token',
                'message' => __('Token owner account is not active.', 'cftp_admin'),
            ];
        }

        if (!api_set_current_user($user)) {
            return [
                'ok' => false,
                'http' => 401,
                'code' => 'invalid_token',
                'message' => __('Could not establish user context.', 'cftp_admin'),
            ];
        }

        if (!current_user_can('upload')) {
            return [
                'ok' => false,
                'http' => 403,
                'code' => 'forbidden',
                'message' => __('You do not have permission to upload files.', 'cftp_admin'),
            ];
        }

        $api_token->touchLastUsed($row['id']);
        $this->recordRateHit($row['id']);
        $this->token_row = $row;

        return ['ok' => true, 'token' => $row];
    }

    public function getTokenRow()
    {
        return $this->token_row;
    }

    private function extractBearerToken()
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }

    private function rateLimitFilePath($token_id)
    {
        $dir = ROOT_DIR . DS . 'upload' . DS . 'temp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . DS . 'api_rl_' . (int)$token_id . '.json';
    }

    private function isRateLimited($token_id)
    {
        $limit = (int)get_option('api_rate_limit_per_minute', null, '60');
        if ($limit < 1) {
            return false;
        }

        $path = $this->rateLimitFilePath($token_id);
        if (!file_exists($path)) {
            return false;
        }

        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data) || empty($data['hits'])) {
            return false;
        }

        $now = time();
        $hits = array_filter($data['hits'], function ($t) use ($now) {
            return ($now - (int)$t) < 60;
        });

        return count($hits) >= $limit;
    }

    private function recordRateHit($token_id)
    {
        $limit = (int)get_option('api_rate_limit_per_minute', null, '60');
        if ($limit < 1) {
            return;
        }

        $path = $this->rateLimitFilePath($token_id);
        $now = time();
        $hits = [];

        if (file_exists($path)) {
            $data = json_decode((string)file_get_contents($path), true);
            if (is_array($data) && !empty($data['hits'])) {
                $hits = array_filter($data['hits'], function ($t) use ($now) {
                    return ($now - (int)$t) < 60;
                });
            }
        }

        $hits[] = $now;
        file_put_contents($path, json_encode(['hits' => array_values($hits)]), LOCK_EX);
    }
}
