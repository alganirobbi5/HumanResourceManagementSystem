<?php

require_once __DIR__ . '/../common/DBConnection.php';

class AuditLog
{
    private $db;

    public function __construct(DBConnection $db = null)
    {
        $this->db = $db ?: new DBConnection();
    }

    public function record($actorUserId, $action, $entityType, $entityId, $before = null, $after = null)
    {
        $this->db->insert(
            'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, before_data, after_data)
             VALUES (?, ?, ?, ?, ?, ?)',
            array(
                $actorUserId !== null ? (int)$actorUserId : null,
                $action,
                $entityType,
                $entityId !== null ? (int)$entityId : null,
                $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null
            )
        );
    }
}
