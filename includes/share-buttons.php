<?php
/**
 * Social share buttons — expects $shareUrl, $shareTitle; optional $shareMessage, $shareVariant ('compact'|'bar'), $shareCopyId
 */
$shareMessage = $shareMessage ?? null;
$shareVariant = $shareVariant ?? 'compact';
$shareLinks   = socialShareLinks($shareUrl, $shareTitle, $shareMessage);
$shareCopyId  = $shareCopyId ?? ('share-copy-' . substr(md5($shareUrl), 0, 8));
$shareLabel   = $shareLabel ?? 'Share';
?>
<div class="st-share<?= $shareVariant === 'bar' ? ' st-share--bar' : '' ?>">
  <?php if ($shareVariant === 'bar'): ?>
  <span class="st-share__label"><?= e($shareLabel) ?></span>
  <?php endif; ?>
  <div class="st-share__actions">
    <a href="<?= e($shareLinks['whatsapp']) ?>" target="_blank" rel="noopener noreferrer" class="st-share-btn st-share-btn--wa" title="Share on WhatsApp" aria-label="Share on WhatsApp">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
    </a>
    <a href="<?= e($shareLinks['facebook']) ?>" target="_blank" rel="noopener noreferrer" class="st-share-btn st-share-btn--fb" title="Share on Facebook" aria-label="Share on Facebook">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
    </a>
    <a href="<?= e($shareLinks['linkedin']) ?>" target="_blank" rel="noopener noreferrer" class="st-share-btn st-share-btn--in" title="Share on LinkedIn" aria-label="Share on LinkedIn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
    </a>
    <a href="<?= e($shareLinks['twitter']) ?>" target="_blank" rel="noopener noreferrer" class="st-share-btn st-share-btn--x" title="Share on X" aria-label="Share on X">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
    </a>
    <button type="button" class="st-share-btn st-share-btn--copy" title="Copy link" aria-label="Copy link"
            data-copy-url="<?= e($shareUrl) ?>" data-copy-label="Copy link" onclick="stCopyShareLink(this)">
      <i data-lucide="link-2" class="ic-16"></i>
    </button>
  </div>
</div>
<?php if (empty($GLOBALS['st_share_script_loaded'])): $GLOBALS['st_share_script_loaded'] = true; ?>
<script>
function stCopyShareLink(btn) {
  var url = btn.getAttribute('data-copy-url');
  if (!url) return;
  var label = btn.getAttribute('data-copy-label') || 'Copy link';
  var done = function () {
    btn.classList.add('st-share-btn--copied');
    btn.setAttribute('title', 'Copied!');
    btn.setAttribute('aria-label', 'Link copied');
    setTimeout(function () {
      btn.classList.remove('st-share-btn--copied');
      btn.setAttribute('title', label);
      btn.setAttribute('aria-label', label);
    }, 2000);
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(done);
  } else {
    var ta = document.createElement('textarea');
    ta.value = url;
    ta.style.position = 'fixed';
    ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    document.body.removeChild(ta);
  }
}
</script>
<?php endif; ?>
