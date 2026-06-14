<?php
/**
 * Notice Popup Widget
 * Include this in head.php, admin-layout-start.php, portal-layout.php
 * 
 * Usage:
 *   $currentPage = 'public'; // 'public' | 'admin' | 'client'
 *   include __DIR__ . '/notice-popup.php';
 */

// Prevent multiple inclusions
if (defined('NOTICE_POPUP_LOADED')) return;
define('NOTICE_POPUP_LOADED', true);

// Get active notice for current page context
$notice = null;
try {
    $now = date('Y-m-d H:i:s');
    $sql = "SELECT * FROM notices WHERE is_active=1 
            AND (starts_at IS NULL OR starts_at <= ?)
            AND (ends_at IS NULL OR ends_at >= ?)
            ORDER BY created_at DESC LIMIT 1";
    $stmt = query($sql, [$now, $now]);
    $notice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if notice targets current page type
    if ($notice && $notice['target_pages'] !== 'all') {
        $targets = explode(',', $notice['target_pages']);
        $targets = array_map('trim', $targets);
        if (!in_array($currentPage ?? '', $targets) && !in_array('all', $targets)) {
            $notice = null;
        }
    }
} catch (\Throwable $e) {
    // Silently fail - don't break pages
    $notice = null;
}
?>
<?php if ($notice): ?>
<style>
.notice-popup-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    animation: notice-fade-in 0.3s ease;
}
@keyframes notice-fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}
.notice-popup {
    background: var(--card);
    border-radius: var(--radius-lg);
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    animation: notice-scale-in 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes notice-scale-in {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.notice-popup-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark, #4338ca) 100%);
    color: #fff;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.notice-popup-header h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.notice-popup-close {
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 50%;
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #fff;
    transition: background 0.2s;
}
.notice-popup-close:hover { background: rgba(255,255,255,0.3); }
.notice-popup-image {
    width: 100%;
    max-height: 200px;
    object-fit: cover;
}
.notice-popup-body {
    padding: 1.5rem;
}
.notice-popup-body h4 {
    margin: 0 0 0.75rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--foreground);
}
.notice-popup-body p {
    margin: 0;
    color: var(--muted-foreground);
    line-height: 1.6;
    white-space: pre-wrap;
}
.notice-popup-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.notice-popup-timer {
    font-size: 0.75rem;
    color: var(--muted-foreground);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.notice-popup-timer-bar {
    width: 60px;
    height: 4px;
    background: var(--muted);
    border-radius: 2px;
    overflow: hidden;
}
.notice-popup-timer-fill {
    height: 100%;
    background: var(--primary);
    border-radius: 2px;
    animation: timer-countdown 60s linear forwards;
}
@keyframes timer-countdown {
    from { width: 100%; }
    to { width: 0%; }
}
.notice-popup-btn {
    padding: 0.5rem 1.25rem;
    border-radius: var(--radius);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
}
.notice-popup-btn-primary {
    background: var(--primary);
    color: #fff;
}
.notice-popup-btn-primary:hover { background: var(--primary-dark, #4338ca); }
.notice-popup-btn-outline {
    background: transparent;
    color: var(--muted-foreground);
    border: 1px solid var(--border);
}
.notice-popup-btn-outline:hover { background: var(--muted); }
</style>

<div id="notice-popup-overlay" class="notice-popup-overlay">
    <div class="notice-popup" role="dialog" aria-modal="true" aria-labelledby="notice-title">
        <div class="notice-popup-header">
            <h3 id="notice-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                Notice
            </h3>
            <button class="notice-popup-close" onclick="closeNoticePopup()" aria-label="Close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <?php if (!empty($notice['image_url'])): ?>
        <img src="<?= e($notice['image_url']) ?>" alt="" class="notice-popup-image" onerror="this.remove()">
        <?php endif; ?>
        <div class="notice-popup-body">
            <h4><?= e($notice['title']) ?></h4>
            <p><?= e($notice['content']) ?></p>
        </div>
        <div class="notice-popup-footer">
            <div class="notice-popup-timer">
                <span>Auto-close in</span>
                <div class="notice-popup-timer-bar">
                    <div class="notice-popup-timer-fill" id="notice-timer-fill"></div>
                </div>
                <span id="notice-timer-text">60s</span>
            </div>
            <button class="notice-popup-btn notice-popup-btn-primary" onclick="closeNoticePopup()">Got it!</button>
        </div>
    </div>
</div>

<script>
(function() {
    let timerSeconds = 60;
    let timerInterval;
    
    function closeNoticePopup() {
        var overlay = document.getElementById('notice-popup-overlay');
        if (overlay) {
            overlay.style.opacity = '0';
            overlay.style.transform = 'scale(0.95)';
            overlay.style.transition = 'all 0.2s ease';
            setTimeout(function() { overlay.remove(); }, 200);
        }
        if (timerInterval) clearInterval(timerInterval);
    }
    
    // Close on overlay click (not popup click)
    document.addEventListener('click', function(e) {
        if (e.target.id === 'notice-popup-overlay') closeNoticePopup();
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeNoticePopup();
    });
    
    // Countdown timer
    timerInterval = setInterval(function() {
        timerSeconds--;
        var textEl = document.getElementById('notice-timer-text');
        if (textEl) textEl.textContent = timerSeconds + 's';
        if (timerSeconds <= 0) {
            clearInterval(timerInterval);
            closeNoticePopup();
        }
    }, 1000);
    
    // Expose close function globally
    window.closeNoticePopup = closeNoticePopup;
})();
</script>
<?php endif; ?>