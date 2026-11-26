<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/LeaveLeaderService.php';

$user = Auth::requireApi();

Json::ok('OK', array(
    'data' => array(
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'company_id' => Auth::companyId(),
        'company_code' => Auth::companyCode(),
        'company_name' => Auth::companyName(),
        'display_name' => $user['display_name'],
        'is_leave_leader' => (new LeaveLeaderService())->isLeader((int)$user['id'], Auth::companyId()),
        'employee' => $user['employee_id'] ? array(
            'id' => (int)$user['employee_id'],
            'nik' => $user['employee_nik'],
            'department' => $user['department_name'],
            'position' => $user['designation_name'],
        ) : null,
    )
));
