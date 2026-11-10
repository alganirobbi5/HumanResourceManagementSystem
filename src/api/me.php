<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApi();

Json::ok('OK', array(
    'data' => array(
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'display_name' => $user['display_name'],
        'employee' => $user['employee_id'] ? array(
            'id' => (int)$user['employee_id'],
            'nik' => $user['employee_nik'],
            'department' => $user['department_name'],
            'position' => $user['designation_name'],
        ) : null,
    )
));
