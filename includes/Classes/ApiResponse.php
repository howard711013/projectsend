<?php

namespace ProjectSend\Classes;

class ApiResponse
{
    public static function json($http_code, $status, $data = [], $error = null)
    {
        header('Content-Type: application/json');
        http_response_code($http_code);

        $body = ['status' => $status];
        if ($status === 'success') {
            $body['data'] = $data;
        } else {
            $body['error'] = $error;
        }

        echo json_encode($body);
        exit;
    }

    public static function success($http_code, $data)
    {
        self::json($http_code, 'success', $data);
    }

    public static function error($http_code, $code, $message)
    {
        self::json($http_code, 'error', [], [
            'code' => $code,
            'message' => $message,
        ]);
    }
}
