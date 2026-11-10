<?php

require_once __DIR__ . '/../common/DBConnection.php';

class NotificationService
{
    private $db;

    public function __construct(DBConnection $db = null)
    {
        $this->db = $db ?: new DBConnection();
    }

    public function notifyUser($userId, $title, $message, $type = 'general', $entityType = null, $entityId = null)
    {
        if (!$userId) {
            return false;
        }
        $this->db->insert(
            'INSERT INTO notifications (recipient_user_id, title, message, type, related_entity_type, related_entity_id)
             VALUES (?, ?, ?, ?, ?, ?)',
            array((int)$userId, $title, $message, $type, $entityType, $entityId !== null ? (int)$entityId : null)
        );
        return true;
    }

    public function notifyHr($title, $message, $type = 'general', $entityType = null, $entityId = null)
    {
        $rows = $this->db->selectAll("SELECT id FROM users WHERE role = 'hr' AND is_active = 1");
        foreach ($rows as $row) {
            $this->notifyUser($row['id'], $title, $message, $type, $entityType, $entityId);
        }
    }

    public function notifyEmployee($employeeId, $title, $message, $type = 'general', $entityType = null, $entityId = null)
    {
        if (!$employeeId) {
            return false;
        }
        $user = $this->db->selectOne(
            'SELECT id FROM users WHERE employee_id = ? AND is_active = 1 ORDER BY id LIMIT 1',
            array((int)$employeeId)
        );
        if (!$user) {
            return false;
        }
        return $this->notifyUser($user['id'], $title, $message, $type, $entityType, $entityId);
    }

    public function forUser($userId, $limit = 10, $onlyUnread = false)
    {
        $sql = 'SELECT * FROM notifications WHERE recipient_user_id = ?'
            . ($onlyUnread ? ' AND read_at IS NULL' : '')
            . ' ORDER BY id DESC LIMIT ' . (int)$limit;
        return $this->db->selectAll($sql, array((int)$userId));
    }

    public function unreadCount($userId)
    {
        $row = $this->db->selectOne(
            'SELECT COUNT(id) AS total FROM notifications WHERE recipient_user_id = ? AND read_at IS NULL',
            array((int)$userId)
        );
        return $row ? (int)$row['total'] : 0;
    }

    public function markRead($userId, $id = null)
    {
        if ($id !== null) {
            $this->db->run(
                'UPDATE notifications SET read_at = NOW() WHERE id = ? AND recipient_user_id = ? AND read_at IS NULL',
                array((int)$id, (int)$userId)
            );
        } else {
            $this->db->run(
                'UPDATE notifications SET read_at = NOW() WHERE recipient_user_id = ? AND read_at IS NULL',
                array((int)$userId)
            );
        }
    }
}
