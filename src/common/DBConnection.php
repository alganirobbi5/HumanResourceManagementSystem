<?php

class DBConnection
{
    private static $configCache = null;

    private $conn;
    private $host;
    private $user;
    private $password;
    private $dbName;
    private $port;

    /**
     * Konfigurasi database terpusat.
     * Prioritas: environment variables -> file lokal (tidak di-commit).
     * Tidak ada kredensial yang disimpan di source yang Ter-track.
     */
    public static function config()
    {
        if (self::$configCache !== null) {
            return self::$configCache;
        }

        $envHost = getenv('HRIS_DB_HOST');
        if ($envHost !== false && $envHost !== '') {
            self::$configCache = array(
                'host' => $envHost,
                'port' => getenv('HRIS_DB_PORT') ?: '3306',
                'db' => getenv('HRIS_DB_NAME') ?: 'hris',
                'user' => (string)getenv('HRIS_DB_USER'),
                'pass' => (string)getenv('HRIS_DB_PASSWORD'),
            );
            return self::$configCache;
        }

        $localFile = __DIR__ . '/db.local.php';
        if (file_exists($localFile)) {
            $local = require $localFile;
            if (is_array($local) && isset($local['host'], $local['db'], $local['user'], $local['pass'])) {
                self::$configCache = array(
                    'host' => $local['host'],
                    'port' => isset($local['port']) ? $local['port'] : '3306',
                    'db' => $local['db'],
                    'user' => $local['user'],
                    'pass' => $local['pass'],
                );
                return self::$configCache;
            }
        }

        throw new RuntimeException('Database configuration is not available. Set HRIS_DB_* environment variables or create src/common/db.local.php.');
    }

    /** Koneksi PDO bersama untuk endpoint API. */
    public static function pdo()
    {
        $c = self::config();
        $dsn = 'mysql:host=' . $c['host'] . ';port=' . $c['port'] . ';dbname=' . $c['db'] . ';charset=utf8mb4';
        return new PDO($dsn, $c['user'], $c['pass'], array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
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
                $dsn = 'mysql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->dbName;
                $this->conn = new PDO($dsn, $this->user, $this->password, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));

                if (!$this->conn) {
                    $this->status_fatal = true;
                    echo 'Connection BDD failed';
                    die();
                }
            } catch (Exception $e) {
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
