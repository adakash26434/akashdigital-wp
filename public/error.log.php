<?php
/**
 * Error Log Viewer - /public/error.log.php
 * 
 * This file provides a simple interface to view PHP error logs.
 * Access this file directly in your browser: /public/error.log.php
 * 
 * For production, consider disabling this or protecting it with authentication.
 */

// Security: Only allow access in development mode
if (getenv('APP_ENV') !== 'development') {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Access Denied</title></head><body>';
    echo '<h1>403 Forbidden</h1><p>Error log viewer is only available in development mode.</p></body></html>';
    exit;
}

// Determine log file path
$logFile = dirname(__DIR__) . '/error.log';
$logFileAlt = ini_get('error_log');

if (!empty($logFileAlt) && file_exists($logFileAlt)) {
    $logFile = $logFileAlt;
}

// Alternative log locations
$altPaths = [
    dirname(__DIR__) . '/logs/error.log',
    '/var/log/php/error.log',
    sys_get_temp_dir() . '/php_errors.log',
];

foreach ($altPaths as $path) {
    if (file_exists($path)) {
        $logFile = $path;
        break;
    }
}

// Handle clearing log
if (isset($_POST['clear']) && $_POST['clear'] === '1') {
    file_put_contents($logFile, '');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Read log content
$logContent = '';
if (file_exists($logFile) && is_readable($logFile)) {
    $logContent = file_get_contents($logFile);
    if (strlen($logContent) > 1024 * 1024) {
        $logContent = "... (truncated - file too large)\n\n" . substr($logContent, -1024 * 512);
    }
} else {
    $logContent = "No log file found at: $logFile";
}

$logLines = $logLines = array_filter(array_map('trim', explode("\n", $logContent)));
$lineCount = count($logLines);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Error Log - <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'Site') ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { font-size: 1.5rem; margin-bottom: 1rem; color: #fff; display: flex; align-items: center; gap: 0.5rem; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem; }
        .info { background: #16213e; padding: 0.75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.875rem; color: #94a3b8; }
        .info code { background: #0f3460; padding: 0.2rem 0.5rem; border-radius: 4px; color: #e94560; }
        .actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer; font-size: 0.875rem; font-weight: 500; transition: all 0.2s; }
        .btn-danger { background: #e94560; color: #fff; }
        .btn-danger:hover { background: #c73659; }
        .btn-secondary { background: #16213e; color: #94a3b8; }
        .btn-secondary:hover { background: #1a1a2e; color: #fff; }
        .log-container { background: #16213e; border-radius: 8px; overflow: hidden; }
        .log-header { padding: 0.75rem 1rem; background: #0f3460; display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; }
        .log-content { padding: 1rem; max-height: 70vh; overflow-y: auto; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace; font-size: 0.8125rem; line-height: 1.6; }
        .log-line { padding: 0.25rem 0; border-bottom: 1px solid #1a1a2e; }
        .log-line:last-child { border-bottom: none; }
        .log-line.error { color: #ff6b6b; }
        .log-line.warning { color: #feca57; }
        .log-line.notice { color: #54a0ff; }
        .log-line.info { color: #5f27cd; }
        .timestamp { color: #576574; margin-right: 0.5rem; }
        .empty { text-align: center; padding: 3rem; color: #576574; }
        .count { color: #94a3b8; }
        .back-link { color: #54a0ff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐛 Error Log</h1>
            <a href="../index.php" class="back-link">← Back to Site</a>
        </div>
        
        <div class="info">
            Log file: <code><?= htmlspecialchars($logFile) ?></code>
            <br><span class="count"><?= $lineCount ?> lines</span>
        </div>
        
        <div class="actions">
            <button onclick="location.reload()" class="btn btn-secondary">🔄 Refresh</button>
            <form method="POST" style="display:inline;">
                <button type="submit" name="clear" value="1" class="btn btn-danger" onclick="return confirm('Clear all log entries?')">🗑️ Clear Log</button>
            </form>
        </div>
        
        <div class="log-container" style="margin-top: 1rem;">
            <div class="log-header">
                <span>Recent Entries</span>
                <span class="count"><?= $lineCount ?> total</span>
            </div>
            <div class="log-content">
                <?php if (empty($logLines)): ?>
                    <div class="empty">No errors logged yet. 🎉</div>
                <?php else: ?>
                    <?php foreach (array_reverse($logLines) as $line): ?>
                        <?php
                        $class = 'info';
                        if (stripos($line, 'error') !== false) $class = 'error';
                        elseif (stripos($line, 'warning') !== false) $class = 'warning';
                        elseif (stripos($line, 'notice') !== false) $class = 'notice';
                        ?>
                        <div class="log-line <?= $class ?>"><?= htmlspecialchars($line) ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>