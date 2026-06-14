<?php
// includes/sla.php — SLA calculation & enforcement helpers
// Reads sla_policies and sets sla_response_due / sla_resolution_due on tickets.

if (!function_exists('sla_apply_to_ticket')) {

    // नेपालीमा: sla get policy() — yo function le aafno kaam garchha
    function sla_get_policy(PDO $pdo, string $priority): ?array {
        $s = $pdo->prepare('SELECT * FROM sla_policies WHERE priority = ? AND active = 1 LIMIT 1');
        $s->execute([$priority]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * Set sla_response_due & sla_resolution_due based on policy and createdAt.
     * Call when ticket is created OR when priority changes.
     */
    function sla_apply_to_ticket(PDO $pdo, int $ticketId): void {
        $t = $pdo->prepare('SELECT id, priority, created_at FROM tickets WHERE id = ?');
        $t->execute([$ticketId]);
        $row = $t->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;
        $p = sla_get_policy($pdo, $row['priority']);
        if (!$p) return;
        $base = strtotime($row['created_at']);
        $resp = date('Y-m-d H:i:s', $base + ((int)$p['response_minutes']) * 60);
        $reso = date('Y-m-d H:i:s', $base + ((int)$p['resolution_minutes']) * 60);
        $u = $pdo->prepare('UPDATE tickets SET sla_response_due = ?, sla_resolution_due = ? WHERE id = ?');
        $u->execute([$resp, $reso, $ticketId]);
    }

    /** Mark first response time when staff replies for the first time. */
    function sla_mark_first_response(PDO $pdo, int $ticketId): void {
        $u = $pdo->prepare('UPDATE tickets SET first_response_at = COALESCE(first_response_at, NOW()) WHERE id = ?');
        $u->execute([$ticketId]);
    }

    /** Recompute breach flag (call from cron and on ticket close). */
    function sla_recompute_breach(PDO $pdo, int $ticketId): void {
        $t = $pdo->prepare(
            'SELECT first_response_at, sla_response_due, sla_resolution_due, status, resolved_at
             FROM tickets WHERE id = ?'
        );
        $t->execute([$ticketId]);
        $r = $t->fetch(PDO::FETCH_ASSOC);
        if (!$r) return;
        $breached = 0;
        $now = time();
        // Response breach: no first response and due passed, OR responded after due
        if ($r['sla_response_due']) {
            $due = strtotime($r['sla_response_due']);
            if ($r['first_response_at']) {
                if (strtotime($r['first_response_at']) > $due) $breached = 1;
            } elseif ($now > $due) {
                $breached = 1;
            }
        }
        // Resolution breach
        if (!$breached && $r['sla_resolution_due']) {
            $due = strtotime($r['sla_resolution_due']);
            if (in_array($r['status'], ['resolved','closed'], true) && $r['resolved_at']) {
                if (strtotime($r['resolved_at']) > $due) $breached = 1;
            } elseif ($now > $due) {
                $breached = 1;
            }
        }
        $u = $pdo->prepare('UPDATE tickets SET sla_breached = ? WHERE id = ?');
        $u->execute([$breached, $ticketId]);
    }

    /** Render SLA status badge HTML for a ticket row. */
    function sla_badge(array $ticket): string {
        if (!empty($ticket['sla_breached'])) {
            return '<span class="badge badge-error">SLA Breached</span>';
        }
        $due = $ticket['sla_resolution_due'] ?? null;
        if (!$due) return '<span class="badge badge-ghost">—</span>';
        $left = strtotime($due) - time();
        if ($left <= 0) return '<span class="badge badge-error">Overdue</span>';
        if ($left < 3600) return '<span class="badge badge-warning">'.round($left/60).'m left</span>';
        if ($left < 86400) return '<span class="badge badge-warning">'.round($left/3600).'h left</span>';
        return '<span class="badge badge-success">'.round($left/86400).'d left</span>';
    }
}
