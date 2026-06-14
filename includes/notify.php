<?php
// includes/notify.php — In-app notifications helper
// Usage: notify($pdo, $userId, 'ticket', 'New reply', 'You have a new reply on ticket #123', '/portal/ticket.php?id=123', 'message-square');

if (!function_exists('notify')) {
    // नेपालीमा: User lai in-app notification pathaune
    function notify(PDO $pdo, int $userId, string $type, string $title, ?string $body = null, ?string $linkUrl = null, string $icon = 'bell'): int {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, type, title, body, link_url, icon) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $type, $title, $body, $linkUrl, $icon]);
        return (int)$pdo->lastInsertId();
    }

    // नेपालीमा: User lai in-app notification pathaune
    function notify_bulk(PDO $pdo, array $userIds, string $type, string $title, ?string $body = null, ?string $linkUrl = null, string $icon = 'bell'): int {
        if (!$userIds) return 0;
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, title, body, link_url, icon) VALUES (?, ?, ?, ?, ?, ?)');
        $n = 0;
        foreach ($userIds as $uid) {
            $stmt->execute([(int)$uid, $type, $title, $body, $linkUrl, $icon]);
            $n++;
        }
        return $n;
    }

    // नेपालीमा: User lai in-app notification pathaune
    function notify_unseen_count(PDO $pdo, int $userId): int {
        $s = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND seen_at IS NULL');
        $s->execute([$userId]);
        return (int)$s->fetchColumn();
    }

    // नेपालीमा: User lai in-app notification pathaune
    function notify_mark_seen(PDO $pdo, int $userId, ?int $notificationId = null): void {
        if ($notificationId) {
            $s = $pdo->prepare('UPDATE notifications SET seen_at = NOW() WHERE id = ? AND user_id = ? AND seen_at IS NULL');
            $s->execute([$notificationId, $userId]);
        } else {
            $s = $pdo->prepare('UPDATE notifications SET seen_at = NOW() WHERE user_id = ? AND seen_at IS NULL');
            $s->execute([$userId]);
        }
    }
}
