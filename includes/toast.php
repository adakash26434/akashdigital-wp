<?php
/**
 * includes/toast.php — Shared toast notification widget
 * Used by admin-layout-close.php and portal-layout-end.php.
 *
 * Renders the #toast-container div + showToast() JS function once.
 * SVG icons use currentColor so they pick up the wrapper's CSS color.
 */
?>
<div id="toast-container" style="position:fixed;top:1.25rem;right:1.25rem;z-index:9999;display:flex;flex-direction:column;gap:0.625rem;pointer-events:none;"></div>
<script>
function showToast(msg,type='success'){
  var colors={
    success:'var(--success-fg)',
    error:  'var(--danger-fg)',
    warning:'var(--warning-fg)',
    info:   'var(--info-fg)'
  };
  var icons={
    success:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
    error:  '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>',
    warning:'<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info:   '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
  };
  var c=colors[type]||colors.info;
  var t=document.createElement('div');
  t.style.cssText='display:flex;align-items:center;gap:0.75rem;padding:0.875rem 1.125rem;border-radius:0.75rem;box-shadow:0 8px 32px rgba(15,23,42,0.15);font-size:0.875rem;font-weight:500;border:1px solid var(--border);background:var(--card);color:var(--foreground);max-width:380px;animation:toast-in 0.25s cubic-bezier(0.34,1.56,0.64,1);pointer-events:auto;';
  t.innerHTML='<span style="display:flex;align-items:center;color:'+c+'">'+(icons[type]||icons.info)+'</span><span>'+msg+'</span>';
  document.getElementById('toast-container').appendChild(t);
  setTimeout(function(){t.style.opacity='0';t.style.transform='translateX(1rem)';t.style.transition='all 0.3s';setTimeout(function(){t.remove();},300);},4000);
}
</script>
