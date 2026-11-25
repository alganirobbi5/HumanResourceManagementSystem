<?php

require_once __DIR__ . '/../common/DBConnection.php';

class PushNotificationService
{
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const FCM_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private $db;
    private $config;
    private $accessToken;

    public function __construct(DBConnection $db = null)
    {
        $this->db = $db ?: new DBConnection();
        $this->config = $this->loadConfig();
    }

    private function loadConfig()
    {
        $local = __DIR__ . '/fcm.local.php';
        if (file_exists($local)) {
            $config = require $local;
            if (is_array($config)) {
                return $config;
            }
        }

        return array(
            'project_id' => getenv('HRIS_FCM_PROJECT_ID') ?: '',
            'client_email' => getenv('HRIS_FCM_CLIENT_EMAIL') ?: '',
            'private_key' => getenv('HRIS_FCM_PRIVATE_KEY') ?: '',
        );
    }

    private function configured()
    {
        return !empty($this->config['project_id'])
            && !empty($this->config['client_email'])
            && !empty($this->config['private_key']);
    }

    public function sendToUser($userId, $title, $body, array $data = array(), $channel = 'attendance_channel')
    {
        if (!$this->configured() || !$userId) {
            return false;
        }

        $row = $this->db->selectOne(
            'SELECT fcm_token FROM users WHERE id = ? AND is_active = 1',
            array((int)$userId)
        );
        if (!$row || empty($row['fcm_token'])) {
            return false;
        }

        return $this->sendToToken($row['fcm_token'], $title, $body, $data, $channel, (int)$userId);
    }

    public function sendToToken($token, $title, $body, array $data, $channel, $userId = null)
    {
        if (!$this->configured() || empty($token)) {
            return false;
        }

        $payload = array(
            'message' => array(
                'token' => $token,
                'notification' => array(
                    'title' => (string)$title,
                    'body' => (string)$body,
                ),
                'data' => $this->stringifyData($data),
                'android' => array(
                    'priority' => 'high',
                    'notification' => array(
                        'channel_id' => $channel,
                        'sound' => 'default',
                    ),
                ),
            ),
        );

        $response = $this->request(sprintf(self::FCM_URL, rawurlencode($this->config['project_id'])), $payload);
        $status = isset($response['status']) ? (int)$response['status'] : 0;

        if ($status === 200) {
            return true;
        }

        if (in_array($status, array(400, 404), true)) {
            $this->clearToken($userId, $token);
        }

        error_log('[FCM] gagal mengirim: HTTP ' . $status . ' ' . substr(isset($response['body']) ? $response['body'] : '', 0, 300));
        return false;
    }

    private function clearToken($userId, $token)
    {
        if ($userId) {
            $this->db->run(
                'UPDATE users SET fcm_token = NULL, fcm_token_updated_at = NULL WHERE id = ? AND fcm_token = ?',
                array((int)$userId, $token)
            );
        } else {
            $this->db->run(
                'UPDATE users SET fcm_token = NULL, fcm_token_updated_at = NULL WHERE fcm_token = ?',
                array($token)
            );
        }
    }

    private function stringifyData(array $data)
    {
        $result = array();
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $result[(string)$key] = (string)$value;
        }
        $result['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';
        return $result;
    }

    private function request($url, array $payload)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return array('status' => 0, 'body' => '');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json; charset=UTF-8',
            ),
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ));
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array('status' => $status, 'body' => $body === false ? '' : $body);
    }

    private function getAccessToken()
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $jwt = $this->createJwt();
        if (!$jwt) {
            return null;
        }

        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
            CURLOPT_POSTFIELDS => http_build_query(array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            )),
        ));
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200 || !$body) {
            error_log('[FCM] gagal OAuth2: HTTP ' . $status);
            return null;
        }
        $decoded = json_decode($body, true);
        $this->accessToken = isset($decoded['access_token']) ? $decoded['access_token'] : null;
        return $this->accessToken;
    }

    private function createJwt()
    {
        if (!function_exists('openssl_sign')) {
            error_log('[FCM] ekstensi openssl tidak tersedia');
            return null;
        }

        $now = time();
        $header = array('alg' => 'RS256', 'typ' => 'JWT');
        $claim = array(
            'iss' => $this->config['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        );

        $encode = function ($value) {
            return rtrim(strtr(base64_encode(json_encode($value)), '+/', '-_'), '=');
        };
        $unsigned = $encode($header) . '.' . $encode($claim);

        $key = openssl_pkey_get_private($this->config['private_key']);
        if ($key === false) {
            error_log('[FCM] private key tidak valid');
            return null;
        }
        $signature = '';
        if (!openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            error_log('[FCM] gagal menandatangani JWT');
            return null;
        }

        return $unsigned . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
