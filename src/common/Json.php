<?php

class Json
{
    public static function out($data, $status = 200)
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function ok($message = 'OK', array $extra = array())
    {
        $payload = array_merge(array('success' => true, 'message' => $message), $extra);
        self::out($payload);
    }

    public static function error($message, $status = 400, array $extra = array())
    {
        $payload = array_merge(array('success' => false, 'message' => $message), $extra);
        self::out($payload, $status);
    }

    public static function readBody()
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : array();
    }
}
