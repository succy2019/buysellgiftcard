<?php
/**
 * Support tickets: user-created tickets with a threaded conversation that
 * admins can answer and close.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';

class SupportService {
    public const CATEGORIES = [
        'general' => 'General',
        'trading' => 'Trading',
        'gift_cards' => 'Gift cards',
        'technical' => 'Technical',
        'account' => 'Account',
    ];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    private $db;

    public function __construct() {
        $this->db = getDB();
        $this->ensureTables();
    }

    private function ensureTables() {
        $this->db->exec("CREATE TABLE IF NOT EXISTS support_ticket_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ticket_id INT NOT NULL,
            author_type ENUM('user', 'admin') NOT NULL,
            author_id INT NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ticket_id (ticket_id),
            FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
        )");
    }

    public function createTicket($userId, $subject, $category, $priority, $message) {
        $subject = trim((string)$subject);
        $message = trim((string)$message);
        if ($subject === '' || mb_strlen($subject) > 200) {
            return ['success' => false, 'message' => 'Enter a subject of up to 200 characters.'];
        }
        if (mb_strlen($message) < 10 || mb_strlen($message) > 5000) {
            return ['success' => false, 'message' => 'Describe the issue in 10 to 5000 characters.'];
        }
        if (!isset(self::CATEGORIES[$category])) { $category = 'general'; }
        if (!in_array($priority, self::PRIORITIES, true)) { $priority = 'medium'; }

        try {
            $ticketId = 'TK' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $stmt = $this->db->prepare("INSERT INTO support_tickets (ticket_id, user_id, subject, message, category, priority)
                VALUES (:tid, :uid, :subject, :message, :category, :priority)");
            $stmt->execute([
                ':tid' => $ticketId, ':uid' => $userId, ':subject' => $subject,
                ':message' => $message, ':category' => $category, ':priority' => $priority,
            ]);
            return ['success' => true, 'message' => "Ticket $ticketId submitted. We'll reply here soon.", 'ticket_id' => $ticketId];
        } catch (Exception $e) {
            logError('Create ticket error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Could not submit your ticket. Please try again.'];
        }
    }

    public function listUserTickets($userId) {
        $stmt = $this->db->prepare("SELECT ticket_id, subject, category, priority, status, created_at, updated_at
            FROM support_tickets WHERE user_id = :uid ORDER BY updated_at DESC LIMIT 100");
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    /** Fetch a ticket with its replies. $userId restricts access to the owner; null = admin. */
    public function getTicket($ticketId, $userId = null) {
        $sql = "SELECT t.*, u.first_name, u.last_name, u.email FROM support_tickets t
                JOIN users u ON u.id = t.user_id WHERE t.ticket_id = :tid";
        $params = [':tid' => $ticketId];
        if ($userId !== null) {
            $sql .= " AND t.user_id = :uid";
            $params[':uid'] = $userId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $ticket = $stmt->fetch();
        if (!$ticket) { return null; }

        $stmt = $this->db->prepare("SELECT author_type, message, created_at FROM support_ticket_replies
            WHERE ticket_id = :id ORDER BY created_at ASC, id ASC");
        $stmt->execute([':id' => $ticket['id']]);
        $ticket['replies'] = $stmt->fetchAll();
        return $ticket;
    }

    public function addUserReply($userId, $ticketId, $message) {
        $message = trim((string)$message);
        if ($message === '' || mb_strlen($message) > 5000) {
            return ['success' => false, 'message' => 'Enter a reply of up to 5000 characters.'];
        }
        $ticket = $this->getTicket($ticketId, $userId);
        if (!$ticket) { return ['success' => false, 'message' => 'Ticket not found.']; }
        if ($ticket['status'] === 'closed') {
            return ['success' => false, 'message' => 'This ticket is closed. Please open a new one.'];
        }
        $this->insertReply($ticket['id'], 'user', $userId, $message);
        // A reply from the user re-opens a resolved ticket.
        $this->db->prepare("UPDATE support_tickets SET status = 'open' WHERE id = :id AND status = 'resolved'")
            ->execute([':id' => $ticket['id']]);
        return ['success' => true, 'message' => 'Reply sent.'];
    }

    public function addAdminReply($adminId, $ticketId, $message, $status = null) {
        $message = trim((string)$message);
        if ($message === '' || mb_strlen($message) > 5000) {
            return ['success' => false, 'message' => 'Enter a reply of up to 5000 characters.'];
        }
        $ticket = $this->getTicket($ticketId);
        if (!$ticket) { return ['success' => false, 'message' => 'Ticket not found.']; }
        if (!in_array($status, self::STATUSES, true)) { $status = 'in_progress'; }
        $this->insertReply($ticket['id'], 'admin', $adminId, $message);
        $this->db->prepare("UPDATE support_tickets SET status = :s, assigned_to = :a WHERE id = :id")
            ->execute([':s' => $status, ':a' => $adminId, ':id' => $ticket['id']]);
        return ['success' => true, 'message' => 'Reply sent to the customer.'];
    }

    public function updateStatus($adminId, $ticketId, $status) {
        if (!in_array($status, self::STATUSES, true)) {
            return ['success' => false, 'message' => 'Invalid status.'];
        }
        $stmt = $this->db->prepare("UPDATE support_tickets SET status = :s, assigned_to = :a WHERE ticket_id = :tid");
        $stmt->execute([':s' => $status, ':a' => $adminId, ':tid' => $ticketId]);
        return ['success' => true, 'message' => 'Ticket marked ' . str_replace('_', ' ', $status) . '.'];
    }

    public function listAllTickets($status = '', $search = '', $limit = 100) {
        $sql = "SELECT t.ticket_id, t.subject, t.category, t.priority, t.status, t.created_at, t.updated_at,
                       u.first_name, u.last_name, u.email
                FROM support_tickets t JOIN users u ON u.id = t.user_id WHERE 1=1";
        $params = [];
        if (in_array($status, self::STATUSES, true)) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $status;
        }
        if ($search !== '') {
            $sql .= " AND (t.ticket_id LIKE :q1 OR t.subject LIKE :q2 OR u.email LIKE :q3)";
            $like = '%' . $search . '%';
            $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
        }
        $sql .= " ORDER BY FIELD(t.status,'open','in_progress','resolved','closed'), t.updated_at DESC LIMIT " . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countOpen() {
        return (int)$this->db->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','in_progress')")->fetchColumn();
    }

    private function insertReply($ticketDbId, $type, $authorId, $message) {
        $this->db->prepare("INSERT INTO support_ticket_replies (ticket_id, author_type, author_id, message)
            VALUES (:t, :type, :a, :m)")
            ->execute([':t' => $ticketDbId, ':type' => $type, ':a' => $authorId, ':m' => $message]);
        $this->db->prepare("UPDATE support_tickets SET updated_at = NOW() WHERE id = :id")->execute([':id' => $ticketDbId]);
    }
}
