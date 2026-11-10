<?php

require_once __DIR__ . '/../common/DBConnection.php';

class RateLimiter
{
    /**
     * Batasi frekuensi request per sesi. Mengembalikan true bila masih boleh.
     */
    public static function allow($key, $minSeconds)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $slot = 'rate_' . $key;
        $now = time();
        if (isset($_SESSION[$slot]) && ($now - (int)$_SESSION[$slot]) < $minSeconds) {
            return false;
        }
        $_SESSION[$slot] = $now;
        return true;
    }
}
