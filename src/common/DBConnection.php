<?php

class DBConnection
{

    private $conn;
    private $host;
    private $user;
    private $password;
    private $dbName;
    private $port;

    function __construct($params=array()) {
        $this->conn = false;
        $this->host = 'localhost';
        $this->user = 'root';
        $this->password = '';
        $this->dbName = 'hrm';
        $this->port = '3306';
        $this->connect();
    }

    function __destruct() {
        $this->disconnect();
    }

    private function connect() {

        if (!$this->conn) {

            try {

                $this->conn = new PDO('mysql:host='.$this->host.';dbname='.$this->dbName.'', $this->user, $this->password, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));

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

    function disconnect() {
        if ($this->conn) {
            $this->conn = null;
        }
    }

    function getOne($query) {

        $result = $this->conn->prepare($query);
        $ret = $result->execute();

        if (!$ret) {
            echo 'PDO::errorInfo():';
            echo '<br />';
            echo 'error SQL: '.$query;
            die();
        }

        $result->setFetchMode(PDO::FETCH_ASSOC);
        $reponse = $result->fetch();

        return $reponse;
    }

    function getAll($query) {

        $result = $this->conn->prepare($query);
        $ret = $result->execute();

        if (!$ret) {
            echo 'PDO::errorInfo():';
            echo '<br />';
            echo 'error SQL: '.$query;
            die();
        }

        $result->setFetchMode(PDO::FETCH_ASSOC);
        $reponse = $result->fetchAll();

        return $reponse;
    }

    function execute($query) {

        if (!$response = $this->conn->exec($query)) {
            echo 'PDO::errorInfo():';
            echo '<br />';
            echo 'error SQL: '.$query;
            die();
        }

        return $response;
    }

    function getPdo() {
        return $this->conn;
    }

    // Jalankan statement dengan parameter terikat (prepared statement).
    function run($query, array $params = array()) {
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    function selectAll($query, array $params = array()) {
        $stmt = $this->run($query, $params);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        return $stmt->fetchAll();
    }

    function selectOne($query, array $params = array()) {
        $stmt = $this->run($query, $params);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    function insert($query, array $params = array()) {
        $this->run($query, $params);
        return (int)$this->conn->lastInsertId();
    }

    function beginTransaction() {
        if (!$this->conn->inTransaction()) {
            $this->conn->beginTransaction();
        }
    }

    function commit() {
        if ($this->conn->inTransaction()) {
            $this->conn->commit();
        }
    }

    function rollBack() {
        if ($this->conn->inTransaction()) {
            $this->conn->rollBack();
        }
    }
}

//$dbConn=new DBConnection();
//
//print_r($dbConn->getAll("SELECT * FROM `admins`"));
