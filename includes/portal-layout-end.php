    </main>
  </div>
</div>
<?php require_once __DIR__ . '/toast.php'; ?>

<?php
// Portal Support WhatsApp float (personalized for logged-in client)
$__portalWa = function_exists('stWhatsAppUrl') ? stWhatsAppUrl($__user ?? null, 'portal') : '';
$__portalWaLabel = function_exists('stWhatsAppLabel') ? stWhatsAppLabel() : 'Support WhatsApp';
if ($__portalWa !== ''):
?>
<a href="<?= e($__portalWa) ?>" target="_blank" rel="noopener noreferrer"
   class="portal-wa-float" title="<?= e($__portalWaLabel) ?>" aria-label="<?= e($__portalWaLabel) ?>">
  <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
  <span><?= e($__portalWaLabel) ?></span>
</a>
<style>
.portal-wa-float{
  position:fixed;right:1.25rem;bottom:1.25rem;z-index:90;
  display:inline-flex;align-items:center;gap:0.5rem;
  padding:0.75rem 1.1rem;border-radius:9999px;
  background:#25d366;color:#fff;font-weight:700;font-size:0.875rem;
  text-decoration:none;box-shadow:0 8px 24px rgba(37,211,102,.35);
  transition:transform .15s ease,box-shadow .15s ease;
}
.portal-wa-float:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(37,211,102,.45);color:#fff;}
@media(max-width:640px){
  .portal-wa-float span{display:none;}
  .portal-wa-float{padding:0.85rem;border-radius:9999px;}
}
</style>
<?php endif; ?>

<?php 
// Notice popup for client portal pages
$currentPage = 'client';
include __DIR__ . '/notice-popup.php';
?>

<script>
<?php
$s = getFlash('success'); $e2 = getFlash('error'); $w = getFlash('warning');
if ($s)  echo "document.addEventListener('DOMContentLoaded',()=>showToast(".json_encode($s).",'success'));";
if ($e2) echo "document.addEventListener('DOMContentLoaded',()=>showToast(".json_encode($e2).",'error'));";
if ($w)  echo "document.addEventListener('DOMContentLoaded',()=>showToast(".json_encode($w).",'warning'));";
?>
</script>
</body>
</html>
