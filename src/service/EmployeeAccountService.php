<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../service/LeaveQuotaService.php';

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

    private function hasTable($table)
    {
        return (bool)$this->pdo->query("SHOW TABLES LIKE '" . $table . "'")->fetchColumn();
    }

    private function employeeByUsername($username)
    {
        $stmt = $this->pdo->prepare('SELECT id FROM employees WHERE user_name = ? LIMIT 1');
        $stmt->execute([(string)$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function masterRow($table, $id, $companyId = null)
    {
        $sql = 'SELECT id FROM ' . $table . ' WHERE id = ? AND status = 1';
        $params = array((int)$id);
        if ($companyId !== null) {
            $sql .= ' AND company_id = ?';
            $params[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function normalizeGender($gender)
    {
        $key = strtolower(preg_replace('/\s+/', '', (string)$gender));
        $aliases = array(
            'l' => 'Male', 'm' => 'Male', 'male' => 'Male',
            'p' => 'Female', 'f' => 'Female', 'female' => 'Female',
        );
        return isset($aliases[$key]) ? $aliases[$key] : (string)$gender;
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

    private function insertUser($employeeId, $username, $password, $isActive = 1, $companyId = null)
    {
        $columns = 'employee_id, username, password_hash, role, is_active';
        $values = array(
            (int)$employeeId,
            $username,
            password_hash((string)$password, PASSWORD_BCRYPT),
            'employee',
            (int)$isActive ? 1 : 0
        );
        if ($this->hasColumn('users', 'company_id')) {
            $columns .= ', company_id';
            $values[] = $companyId !== null ? (int)$companyId : null;
        }
        $stmt = $this->pdo->prepare('INSERT INTO users (' . $columns . ') VALUES (' .
            implode(', ', array_fill(0, count($values), '?')) . ')');
        $stmt->execute($values);
        return (int)$this->pdo->lastInsertId();
    }

    public function create(array $data)
    {
        $firstName = trim((string)(isset($data['first_name']) ? $data['first_name'] : ''));
        $lastName = trim((string)(isset($data['last_name']) ? $data['last_name'] : ''));
        $username = trim((string)(isset($data['user_name']) ? $data['user_name']
            : (isset($data['username']) ? $data['username'] : '')));
        $password = (string)(isset($data['password']) ? $data['password'] : '');
        $email = trim((string)(isset($data['email']) ? $data['email'] : ''));
        $mobileNo = trim((string)(isset($data['mobile_no']) ? $data['mobile_no'] : ''));
        $gender = $this->normalizeGender(trim((string)(isset($data['gender']) ? $data['gender'] : '')));
        $departmentId = isset($data['department_id']) ? (string)$data['department_id'] : '';
        $designationId = isset($data['designation_id']) ? (string)$data['designation_id'] : '';
        $status = isset($data['status']) ? (string)$data['status'] : '1';
        $createBy = isset($data['create_by']) ? trim((string)$data['create_by']) : null;
        $companyId = isset($data['company_id']) && $data['company_id'] !== null && $data['company_id'] !== ''
            ? (int)$data['company_id'] : null;

        if ($firstName === '' || strlen($firstName) > 30) {
            throw new InvalidArgumentException('first_name wajib diisi (maksimal 30 karakter)');
        }
        if ($lastName === '' || strlen($lastName) > 30) {
            throw new InvalidArgumentException('last_name wajib diisi (maksimal 30 karakter)');
        }
        if ($username === '' || strlen($username) > 30) {
            throw new InvalidArgumentException('user_name wajib diisi (maksimal 30 karakter)');
        }
        if ($password === '' || strlen($password) > 20) {
            throw new InvalidArgumentException('password wajib diisi (maksimal 20 karakter)');
        }
        if ($gender === '' || strlen($gender) > 6) {
            throw new InvalidArgumentException('gender wajib diisi (maksimal 6 karakter)');
        }
        if ($email === '' || strlen($email) > 30) {
            throw new InvalidArgumentException('email wajib diisi (maksimal 30 karakter)');
        }
        if ($mobileNo === '' || strlen($mobileNo) > 15) {
            throw new InvalidArgumentException('mobile_no wajib diisi (maksimal 15 karakter)');
        }
        if (!ctype_digit($departmentId) || (int)$departmentId <= 0
            || !$this->masterRow('departments', $departmentId, $companyId)) {
            throw new InvalidArgumentException('department_id tidak valid');
        }
        $designationTable = $this->hasTable('employee_designations') ? 'employee_designations' : 'designations';
        if (!ctype_digit($designationId) || (int)$designationId <= 0
            || !$this->masterRow($designationTable, $designationId, $companyId)) {
            throw new InvalidArgumentException('designation_id tidak valid');
        }
        if (!ctype_digit($status) || !in_array((int)$status, array(0, 1), true)) {
            throw new InvalidArgumentException('status harus 0 atau 1');
        }
        if ($this->employeeByUsername($username) || $this->userByUsername($username)) {
            throw new RuntimeException('Username sudah digunakan', 409);
        }

        $columns = array('first_name', 'last_name', 'user_name', 'password', 'gender',
            'email', 'department_id', 'designation_id', 'mobile_no', 'status');
        $values = array($firstName, $lastName, $username, $password, $gender,
            $email, (int)$departmentId, (int)$designationId, $mobileNo, (int)$status);
        if ($this->hasColumn('employees', 'create_by')) {
            $columns[] = 'create_by';
            $values[] = $createBy;
        }
        if ($this->hasColumn('employees', 'created_date')) {
            $columns[] = 'created_date';
            $values[] = date('Y-m-d H:i:s');
        }
        if ($this->hasColumn('employees', 'company_id')) {
            $columns[] = 'company_id';
            $values[] = $companyId;
        }

        $this->pdo->beginTransaction();
        try {
            $sql = 'INSERT INTO employees (' . implode(', ', $columns) . ') VALUES (' .
                implode(', ', array_fill(0, count($values), '?')) . ')';
            $this->pdo->prepare($sql)->execute($values);
            $employeeId = (int)$this->pdo->lastInsertId();

            $this->insertUser($employeeId, $username, $password, (int)$status, $companyId);
            $quota = new LeaveQuotaService($this->pdo);
            $quota->ensureForEmployee($employeeId, 12);
            $this->pdo->commit();
            return $employeeId;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ((string)$e->getCode() === '23000') {
                throw new RuntimeException('Username sudah digunakan', 409);
            }
            throw $e;
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
        $stmt = $this->pdo->prepare('SELECT id, company_id FROM employees WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$employeeId]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee) {
            throw new InvalidArgumentException('Karyawan tidak ditemukan', 404);
        }
        $companyId = $employee['company_id'] !== null ? (int)$employee['company_id'] : null;
        if (array_key_exists('department_id', $data)
            && !$this->masterRow('departments', $data['department_id'], $companyId)) {
            throw new InvalidArgumentException('Department tidak valid untuk perusahaan ini', 422);
        }
        if (array_key_exists('designation_id', $data)) {
            $designationTable = $this->hasTable('employee_designations') ? 'employee_designations' : 'designations';
            if (!$this->masterRow($designationTable, $data['designation_id'], $companyId)) {
                throw new InvalidArgumentException('Jabatan tidak valid untuk perusahaan ini', 422);
            }
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
                $updateSql = 'UPDATE employees SET ' . implode(', ', $fields) . ' WHERE id = ?';
                if ($companyId !== null) {
                    $updateSql .= ' AND company_id = ?';
                    $params[] = $companyId;
                }
                $this->pdo->prepare($updateSql)->execute($params);
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
