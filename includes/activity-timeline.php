<?php
// ═══════════════════════════════════════════════════════════════
// Activity Timeline — v3.6
// Usage:
//   require_once __DIR__ . '/activity-timeline.php';
//   renderActivityTimeline('ticket', $ticketId);
//   // OR feed entries directly:
//   renderActivityTimelineFromArray([
//     ['type'=>'success','title'=>'Ticket opened','at'=>'2025-05-29 10:30:00','by'=>'Ramesh'],
//   ]);
// ═══════════════════════════════════════════════════════════════
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/lang.php';

/**
 * Log one activity entry for any record.
 * Soft-fails if the activity_log table doesn't exist yet.
 */
function logActivity(string $entityType, $entityId, string $action, string $title = '', ?array $meta = null, ?int $userId = null): void {
    try {
        execute(
            "INSERT INTO activity_log (entity_type, entity_id, action, title, meta, user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$entityType, (int)$entityId, $action, $title, $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, $userId ?: ($_SESSION['user_id'] ?? null)]
        );
    } catch (\Throwable $e) {
        // table missing — ignore silently in v3.5 installs
    }
}

/**
 * Fetch and render the timeline for a record.
 */
function renderActivityTimeline(string $entityType, $entityId, int $limit = 50): void {
    $rows = [];
    try {
        $rows = query(
            "SELECT al.*, u.display_name AS actor
               FROM activity_log al
          LEFT JOIN users u ON u.id = al.user_id
              WHERE al.entity_type = ? AND al.entity_id = ?
           ORDER BY al.created_at DESC
              LIMIT " . (int)$limit,
            [$entityType, (int)$entityId]
        );
    } catch (\Throwable $e) { /* missing table */ }

    if (!$rows) {
        echo '<p style="color:var(--muted-foreground);font-size:var(--text-sm);">'
           . (isNepali() ? 'अहिलेसम्म कुनै गतिविधि छैन।' : 'No activity yet.')
           . '</p>';
        return;
    }
    renderActivityTimelineFromArray(array_map(function($r){
        return [
            'type'  => $r['action'] ?? 'info',
            'title' => $r['title'] ?: ucfirst((string)($r['action'] ?? 'event')),
            'at'    => $r['created_at'] ?? null,
            'by'    => $r['actor']     ?? null,
            'meta'  => $r['meta']      ?? null,
        ];
    }, $rows));
}

// नेपालीमा: renderActivityTimelineFromArray() — yo function le aafno kaam garchha
function renderActivityTimelineFromArray(array $entries): void {
    if (!$entries) return;
    echo '<div class="timeline">';
    foreach ($entries as $e) {
        $type = strtolower($e['type'] ?? 'info');
        $cls  = in_array($type, ['success','warning','danger'], true) ? $type : '';
        $at   = !empty($e['at']) ? (function_exists('timeAgo') ? timeAgo($e['at']) : date('M j, Y H:i', strtotime($e['at']))) : '';
        $by   = $e['by'] ?? '';
        echo '<div class="timeline-item ' . $cls . '">';
        echo   '<div class="timeline-body">' . htmlspecialchars((string)($e['title'] ?? ''), ENT_QUOTES) . '</div>';
        echo   '<div class="timeline-meta">';
        if ($by) echo htmlspecialchars($by, ENT_QUOTES) . ' · ';
        echo     htmlspecialchars($at, ENT_QUOTES);
        echo   '</div>';
        echo '</div>';
    }
    echo '</div>';
}
