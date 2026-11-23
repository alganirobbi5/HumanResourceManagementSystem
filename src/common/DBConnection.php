<?php

class DBConnection
{
    private static $configCache = null;
    private static $safeErrors = false;

    private $conn;
    private $host;
    private $user;
    private $password;
    private $dbName;
    private $port;

    /**
     * Satu-satunya sumber konfigurasi database untuk web dan API.
     *
     * HRIS_ENV=production : hanya HRIS_DB_* yang dipakai, db.local.php diabaikan.
     * HRIS_ENV=local (default) : db.local.php (tidak di-commit) dipakai, HRIS_DB_* tetap
     * dapat menimpa per field untuk keperluan pengujian.
     */
    public static function config()
    {
        if (self::$configCache !== null) {
            return self::$configCache;
        }

        $mode = strtolower(trim((string)getenv('HRIS_ENV')));
        if ($mode === '') {
            $mode = 'local';
        }
        if ($mode !== 'local' && $mode !== 'production') {
            throw new RuntimeException('Invalid HRIS_ENV. Use "local" or "production".');
        }

        if ($mode === 'production') {
            $config = array(
                'host' => 'localhost',
                'port' => '3306',
                'db' => 'hris',
                'user' => '',
                'pass' => '',
            );
        } else {
            $localFile = __DIR__ . '/db.local.php';
            if (!file_exists($localFile)) {
                throw new RuntimeException('Local database configuration is missing. Create src/common/db.local.php (not committed) or set HRIS_ENV=production with HRIS_DB_* variables.');
            }
            $local = require $localFile;
            if (!is_array($local)) {
                throw new RuntimeException('Local database configuration is invalid.');
            }
            $config = array(
                'host' => isset($local['host']) ? (string)$local['host'] : '',
                'port' => isset($local['port']) ? (string)$local['port'] : '3306',
                'db' => isset($local['db']) ? (string)$local['db'] : '',
                'user' => isset($local['user']) ? (string)$local['user'] : '',
                'pass' => array_key_exists('pass', $local) ? (string)$local['pass'] : '',
            );
        }

        $envKeys = array(
            'HRIS_DB_HOST' => 'host',
            'HRIS_DB_PORT' => 'port',
            'HRIS_DB_NAME' => 'db',
            'HRIS_DB_USER' => 'user',
            'HRIS_DB_PASSWORD' => 'pass',
        );
        foreach ($envKeys as $name => $key) {
            $value = getenv($name);
            if ($value !== false && $value !== '') {
                $config[$key] = (string)$value;
            }
        }

        if ($config['host'] === '' || $config['db'] === '' || $config['user'] === '' || $config['pass'] === '') {
            throw new RuntimeException('Database configuration is incomplete. Check HRIS_ENV and the active configuration source.');
        }

        $config['mode'] = $mode;
        self::$configCache = $config;
        return self::$configCache;
    }

    /** Dipakai endpoint diagnostik agar kegagalan koneksi tidak menampilkan detail internal. */
    public static function enableSafeErrors()
    {
        self::$safeErrors = true;
    }

    /** Koneksi PDO bersama untuk endpoint API (memakai config() yang sama dengan jalur web). */
    public static function pdo()
    {
        $c = self::config();
        $dsn = 'mysql:host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['db'] . ';charset=utf8mb4';
        return new PDO($dsn, $c['user'], $c['pass'], array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ));
    }

    function __construct($params = array())
    {
        $c = self::config();
        $this->conn = false;
        $this->host = $c['host'];
        $this->user = $c['user'];
        $this->password = $c['pass'];
        $this->dbName = $c['db'];
        $this->port = $c['port'];
        $this->connect();
    }

    function __destruct()
    {
        $this->disconnect();
    }

    private function connect()
    {
        if (!$this->conn) {
            try {
                $dsn = 'mysql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->dbName . ';charset=utf8mb4';
                $this->conn = new PDO($dsn, $this->user, $this->password, array(
                    PDO::ATTR_TIMEOUT => 5,
                ));
            } catch (Exception $e) {
                if (self::$safeErrors) {
                    throw new RuntimeException('Database connection failed', 0, $e);
                }
                die('Erreur : ' . $e->getMessage());
            }
        }

        return $this->conn;
    }

    function disconnect()
    {
        if ($this->conn) {
            $this->conn = null;
        }
    }

    function getOne($query)
    {
        $result = $this->conn->prepare($query);
        $ret = $result->execute();

        if (!$ret) {
            echo 'PDO::errorInfo():';
            echo '<br />';
            echo 'error SQL: ' . $query;
            die();
        }

        $result->setFetchMode(PDO::FETCH_ASSOC);
        $reponse = $result->fetch();

        return $reponse;
    }

    function getAll($query)
    {
        $result = $this->conn->prepare($query);
        $ret = $result->execute();

        if (!$ret) {
            echo 'PDO::errorInfo():';
            echo '<br />';
            echo 'error SQL: ' . $query;
            die();
        }

        $result->setFetchMode(PDO::FETCH_ASSOC);
        $reponse = $result->fetchAll();

        return $reponse;
    }

    function execute($query)
    {
        if (!$response = $this->conn->exec($query)) {
            echo 'PDO::errorInfo():';
            echo '<br />';
            echo 'error SQL: ' . $query;
            die();
        }

        return $response;
    }

    function getPdo()
    {
        return $this->conn;
    }

    // Jalankan statement dengan parameter terikat (prepared statement).
    function run($query, array $params = array())
    {
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    function selectAll($query, array $params = array())
    {
        $stmt = $this->run($query, $params);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        return $stmt->fetchAll();
    }

    function selectOne($query, array $params = array())
    {
        $stmt = $this->run($query, $params);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    function insert($query, array $params = array())
    {
        $this->run($query, $params);
        return (int)$this->conn->lastInsertId();
    }

    function beginTransaction()
    {
        if (!$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
        }
    }

    function commit()
    {
        if ($this->conn->inTransaction()) {
            $this->conn->commit();
        }
    }

    function rollBack()
    {
        if ($this->conn->inTransaction()) {
            $this->conn->rollBack();
        }
    }
}
