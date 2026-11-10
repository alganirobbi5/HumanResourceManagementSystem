<?php

require_once __DIR__ . '/DBConnection.php';
require_once __DIR__ . '/Json.php';

class Auth
{
    private static $profile = null;
    private static $resolved = false;

    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login(array $user)
    {
        self::start();
        session_regenerate_id(true);

        $db = new DBConnection();
        $token = bin2hex(random_bytes(32));
        $db->run('UPDATE users SET api_token = ? WHERE id = ?', array($token, (int)$user['id']));

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['username'];
        $_SESSION['employee_id'] = $user['employee_id'] !== null ? (int)$user['employee_id'] : null;
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['api_token'] = $token;

        // Kompatibilitas dengan halaman admin lama (hanya HR yang boleh)
        if ($user['role'] === 'hr') {
            $_SESSION['admin_name'] = $user['display_name'];
        } else {
            unset($_SESSION['admin_name'], $_SESSION['admin_password']);
        }
    }

    public static function logout()
    {
        self::start();
        if (!empty($_SESSION['user_id'])) {
            try {
                $db = new DBConnection();
                $db->run('UPDATE users SET api_token = NULL WHERE id = ?', array((int)$_SESSION['user_id']));
            } catch (Exception $e) {
                // abaikan, sesi tetap dihancurkan
            }
        }
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$profile = null;
        self::$resolved = false;
    }

    /**
     * Sesi dari login versi lama hanya berisi admin_name (tanpa user_id).
     * Buatkan/pakai baris users agar halaman baru tidak memaksa login ulang.
     */
    private static function legacySessionUpgrade()
    {
        $name = isset($_SESSION['admin_name']) ? trim((string)$_SESSION['admin_name']) : '';
        if ($name === '') {
            return null;
        }
        try {
            $db = new DBConnection();

            // Bila ada profil karyawan dengan NIK yang sama, hubungkan otomatis.
            $employee = $db->selectOne(
                'SELECT id FROM employees WHERE user_name = ? ORDER BY id LIMIT 1',
                array($name)
            );
            $employeeId = $employee ? (int)$employee['id'] : null;

            $existing = $db->selectOne('SELECT id, employee_id FROM users WHERE username = ? LIMIT 1', array($name));
            if ($existing) {
                if ($existing['employee_id'] === null && $employeeId !== null) {
                    $db->run('UPDATE users SET employee_id = ? WHERE id = ?', array($employeeId, (int)$existing['id']));
                }
                return (int)$existing['id'];
            }

            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $id = $db->insert(
                "INSERT INTO users (username, email, password_hash, role, employee_id, is_active)
                 VALUES (?, NULL, ?, 'hr', ?, 1)",
                array($name, $hash, $employeeId)
            );
            return (int)$id;
        } catch (Exception $e) {
            return null;
        }
    }

    /** Identitas dari sesi web atau header Authorization: Bearer (untuk mobile nanti). */
    public static function identify()
{
    if (self::$resolved) {
        return self::$profile;
    }
    self::$resolved = true;
    self::start();

    // TAMBAHAN: Deteksi login jalur lama (tabel admins)  
    if (!empty($_SESSION['admin_name']) && empty($_SESSION['user_id'])) {
        self::$profile = [
            'id' => 1,
            'username' => $_SESSION['admin_name'],
            'role' => 'hr',
            'display_name' => $_SESSION['admin_name'],
            'employee_id' => null 
        ];
        return self::$profile;
    }
    
    $userId = null;
    if (!empty($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
    } else {
        // ... (SISA KODE ASLI TEMAN ANDA DI BAWAH INI BIARKAN SAJA) ...
        $header = '';
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (preg_match('/Bearer\s+(\S+)/', $header, $m)) {
            $db = new DBConnection();
            $row = $db->selectOne('SELECT id FROM users WHERE api_token = ? AND is_active = 1', array($m[1]));
            if ($row) {
                $userId = (int)$row['id'];
            }
        }
    }
    
    // ... (LANJUTKAN SISA KODE ASLI DI BAWAHNYA) ...

        if (!$userId && !empty($_SESSION['admin_name'])) {
            $userId = self::legacySessionUpgrade();
        }

        if (!$userId) {
            self::$profile = null;
            return null;
        }

        $db = new DBConnection();
        $row = $db->selectOne(
            'SELECT u.id, u.username, u.role, u.employee_id, u.is_active,
                    e.first_name, e.last_name, e.department_id, e.designation_id,
                    e.user_name AS employee_nik,
                    d.name AS department_name, dg.title AS designation_name
             FROM users u
             LEFT JOIN employees e ON e.id = u.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN employee_designations dg ON dg.id = e.designation_id
             WHERE u.id = ? AND u.is_active = 1',
            array($userId)
        );
        if (!$row) {
            self::$profile = null;
            return null;
        }
        $row['display_name'] = trim($row['first_name'] . ' ' . $row['last_name']) !== ''
            ? trim($row['first_name'] . ' ' . $row['last_name'])
            : $row['username'];

        // Selaraskan sesi (termasuk hasil upgrade sesi lama) agar menu & halaman konsisten.
        $_SESSION['user_id'] = (int)$row['id'];
        $_SESSION['user_role'] = $row['role'];
        $_SESSION['employee_id'] = $row['employee_id'] !== null ? (int)$row['employee_id'] : null;
        $_SESSION['display_name'] = $row['display_name'];

        self::$profile = $row;
        return self::$profile;
    }

    public static function check()
    {
        return self::identify() !== null;
    }

    public static function user()
    {
        return self::identify();
    }

    public static function employeeId()
    {
        $u = self::identify();
        return $u ? $u['employee_id'] : null;
    }

    public static function isHr()
    {
        $u = self::identify();
        return $u && $u['role'] === 'hr';
    }

    public static function requireLogin()
    {
        if (!self::check()) {
            self::redirect('../../index.php');
        }
    }

    public static function requireHr()
    {
        self::requireLogin();
        if (!self::isHr()) {
            self::redirect('../../index.php?msg=Anda tidak memiliki akses ke halaman tersebut');
        }
    }

    /** Untuk endpoint JSON: hentikan dengan 401 bila belum login. */
    public static function requireApi()
    {
        $u = self::identify();
        if (!$u) {
            Json::error('Sesi login berakhir, silakan login kembali', 401);
        }
        return $u;
    }

    public static function requireApiHr()
    {
        $u = self::requireApi();
        if ($u['role'] !== 'hr') {
            Json::error('Anda tidak memiliki izin untuk melakukan aksi ini', 403);
        }
        return $u;
    }

    public static function redirect($url)
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
        } else {
            echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        }
        exit;
    }
}
