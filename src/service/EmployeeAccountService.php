<?php

require_once __DIR__ . '/../common/DBConnection.php';

class EmployeeAccountService
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DBConnection::pdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function hasColumn($table, $column)
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM " . $table . " LIKE '" . $column . "'");
        return $stmt && $stmt->fetch() !== false;
    }

    private function userByEmployeeId($employeeId)
    {
        $stmt = $this->pdo->prepare('SELECT id, username, is_active FROM users WHERE employee_id = ? LIMIT 1');
        $stmt->execute([(int)$employeeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function userByUsername($username, $exceptId = null)
    {
        $sql = 'SELECT id, employee_id FROM users WHERE username = ?';
        $params = array($username);
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = (int)$exceptId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function insertUser($employeeId, $username, $password, $isActive = 1)
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (employee_id, username, password_hash, role, is_active)
             VALUES (?, ?, ?, \'employee\', ?)'
        );
        $stmt->execute(array(
            (int)$employeeId,
            $username,
            password_hash((string)$password, PASSWORD_BCRYPT),
            (int)$isActive ? 1 : 0
        ));
        return (int)$this->pdo->lastInsertId();
    }

    public function create(array $data)
    {
        $firstName = trim((string)(isset($data['first_name']) ? $data['first_name'] : ''));
        $lastName = trim((string)(isset($data['last_name']) ? $data['last_name'] : ''));
        $username = trim((string)(isset($data['username']) ? $data['username'] : ''));
        $password = (string)(isset($data['password']) ? $data['password'] : '');
        if ($firstName === '' || $username === '' || $password === '') {
            throw new InvalidArgumentException('Nama, username, dan password wajib diisi');
        }
        if ($this->userByUsername($username)) {
            throw new RuntimeException('Username sudah digunakan', 409);
        }

        $optional = array(
            'email' => isset($data['email']) ? (string)$data['email'] : '',
            'gender' => isset($data['gender']) ? (string)$data['gender'] : 'L',
            'department_id' => isset($data['department_id']) ? (int)$data['department_id'] : 0,
            'designation_id' => isset($data['designation_id']) ? (int)$data['designation_id'] : 0,
            'mobile_no' => isset($data['mobile_no']) ? (string)$data['mobile_no'] : '',
            'status' => isset($data['status']) ? (int)$data['status'] : 1,
        );

        $this->pdo->beginTransaction();
        try {
            $columns = array('first_name', 'last_name', 'user_name', 'password');
            $values = array($firstName, $lastName, $username, $password);
            foreach ($optional as $column => $value) {
                if ($this->hasColumn('employees', $column)) {
                    $columns[] = $column;
                    $values[] = $value;
                }
            }
            $sql = 'INSERT INTO employees (' . implode(', ', $columns) . ') VALUES (' .
                implode(', ', array_fill(0, count($values), '?')) . ')';
            $this->pdo->prepare($sql)->execute($values);
            $employeeId = (int)$this->pdo->lastInsertId();

            $this->insertUser($employeeId, $username, $password, $optional['status']);
            $this->pdo->commit();
            return $employeeId;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function syncUser($employeeId, $password = null)
    {
        $stmt = $this->pdo->prepare('SELECT id, first_name, last_name, user_name, password FROM employees WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee) {
            return false;
        }

        $this->pdo->beginTransaction();
        try {
            $existing = $this->userByEmployeeId($employeeId);
            if ($existing) {
                $isActive = 1;
                if ($this->hasColumn('employees', 'status')) {
                    $statusStmt = $this->pdo->prepare('SELECT status FROM employees WHERE id = ? LIMIT 1');
                    $statusStmt->execute([(int)$employeeId]);
                    $isActive = (int)$statusStmt->fetchColumn() === 1 ? 1 : 0;
                }
                $this->pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')
                    ->execute(array($isActive, (int)$existing['id']));
                $this->pdo->commit();
                return false;
            }

            $username = (string)$employee['user_name'];
            $secret = $password !== null && $password !== '' ? (string)$password : (string)$employee['password'];
            if ($username === '' || $secret === '' || $this->userByUsername($username)) {
                $this->pdo->rollBack();
                return false;
            }

            $isActive = 1;
            if ($this->hasColumn('employees', 'status')) {
                $statusStmt = $this->pdo->prepare('SELECT status FROM employees WHERE id = ? LIMIT 1');
                $statusStmt->execute([(int)$employeeId]);
                $isActive = (int)$statusStmt->fetchColumn() === 1 ? 1 : 0;
            }
            $this->insertUser($employeeId, $username, $secret, $isActive);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function update($employeeId, array $data)
    {
        $stmt = $this->pdo->prepare('SELECT id FROM employees WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$employeeId]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException('Karyawan tidak ditemukan', 404);
        }

        $fields = array();
        $params = array();
        $allowed = array('first_name', 'last_name', 'email', 'mobile_no', 'gender', 'department_id', 'designation_id', 'status');
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data) || !$this->hasColumn('employees', $field)) {
                continue;
            }
            $fields[] = $field . ' = ?';
            $params[] = $data[$field];
        }
        $password = array_key_exists('password', $data) ? (string)$data['password'] : null;
        if ($password !== null && $password !== '' && $this->hasColumn('employees', 'password')) {
            $fields[] = 'password = ?';
            $params[] = $password;
        }
        $username = isset($data['username']) ? trim((string)$data['username']) : null;

        $this->pdo->beginTransaction();
        try {
            if ($fields) {
                $params[] = (int)$employeeId;
                $this->pdo->prepare('UPDATE employees SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
            }
            $user = $this->userByEmployeeId($employeeId);
            if ($user) {
                $userFields = array();
                $userParams = array();
                if ($password !== null && $password !== '') {
                    $userFields[] = 'password_hash = ?';
                    $userParams[] = password_hash($password, PASSWORD_BCRYPT);
                }
                if ($username !== null && $username !== '') {
                    if ($this->userByUsername($username, (int)$user['id'])) {
                        throw new RuntimeException('Username sudah digunakan', 409);
                    }
                    $userFields[] = 'username = ?';
                    $userParams[] = $username;
                }
                if (array_key_exists('status', $data)) {
                    $userFields[] = 'is_active = ?';
                    $userParams[] = (int)$data['status'] === 1 ? 1 : 0;
                }
                if ($userFields) {
                    $userParams[] = (int)$user['id'];
                    $this->pdo->prepare('UPDATE users SET ' . implode(', ', $userFields) . ' WHERE id = ?')
                        ->execute($userParams);
                }
            } elseif ($username !== null && $username !== '') {
                $secret = $password;
                if ($secret === null || $secret === '') {
                    $secretStmt = $this->pdo->prepare('SELECT password FROM employees WHERE id = ? LIMIT 1');
                    $secretStmt->execute([(int)$employeeId]);
                    $secret = (string)$secretStmt->fetchColumn();
                }
                $this->insertUser($employeeId, $username, $secret, 1);
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function setActive($employeeId, $active)
    {
        $active = (int)$active === 1 ? 1 : 0;
        $this->pdo->beginTransaction();
        try {
            if ($this->hasColumn('employees', 'status')) {
                $this->pdo->prepare('UPDATE employees SET status = ? WHERE id = ?')
                    ->execute(array($active, (int)$employeeId));
            }
            $this->pdo->prepare('UPDATE users SET is_active = ? WHERE employee_id = ?')
                ->execute(array($active, (int)$employeeId));
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
