<?php 
$__s = siteSettings();
// Fetch products from DB for dynamic footer (single source of truth)
$_footerProducts = [];
try { 
    $_footerProducts = query("SELECT name, slug FROM products WHERE active=1 ORDER BY position, id LIMIT 8"); 
} catch (\Throwable $e) { 
    error_log('[footer] products: '.$e->getMessage()); 
}
?>
</main><!-- /#main-content -->
<footer style="background:var(--footer-bg);color:var(--footer-fg);margin-top:3rem;">

  <!-- Main footer columns (#footer-cols rule in assets/css/pages.css) -->
  <div class="container" style="padding-top:2.75rem;padding-bottom:2.75rem;">
    <div id="footer-cols" style="display:grid;grid-template-columns:1fr;gap:2.5rem;">

      <!-- Brand col -->
      <div>
        <a href="<?= url('index.php') ?>" style="display:inline-flex;align-items:center;gap:0.625rem;font-family:var(--font-display);font-weight:800;font-size:var(--text-md);color:var(--footer-fg-strong);text-decoration:none;margin-bottom:1.125rem;">
          <?php if (!empty($__s['logo_url'])): ?>
            <img src="<?= e($__s['logo_url']) ?>" loading="lazy" alt="<?= e($__s['site_name']) ?>" style="height:2rem;width:auto;max-width:11rem;object-fit:contain;border-radius:0;">
          <?php else: ?>
            <span style="display:grid;place-items:center;height:2.25rem;width:2.25rem;border-radius:0.625rem;background:var(--gradient-primary);color:#fff;font-weight:800;font-size:var(--text-sm);"><?= strtoupper(substr(stSiteName(), 0, 2)) ?></span>
            <?= e($__s['site_name'] ?? stSiteName()) ?>
          <?php endif; ?>
        </a>
        <p class="footer-brand-text">
          <?= e(function_exists('cms') ? cms($__s, 'footer_tagline', 'Trusted software & IT solutions partner.') : ($__s['footer_tagline'] ?? 'Trusted software & IT solutions partner.')) ?>
        </p>
        
        <!-- Address from site settings -->
        <?php if (!empty($__s['address'])): ?>
        <div class="footer-meta-row">
          <i data-lucide="map-pin"></i>
          <span><?= e($__s['address']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Contact info -->
        <?php if (!empty($__s['contact_phone']) || !empty($__s['contact_email'])): ?>
        <div style="display:flex;flex-direction:column;gap:0.5rem;margin-bottom:1.625rem;">
          <?php if (!empty($__s['contact_phone'])): ?>
          <div class="nav-meta">
            <i data-lucide="phone"></i>
            <?= e($__s['contact_phone']) ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($__s['contact_email'])): ?>
          <div class="nav-meta">
            <i data-lucide="mail"></i>
            <?= e($__s['contact_email']) ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Social links -->
        <div style="display:flex;gap:0.5rem;">
          <?php $socials = $__s['social_links'] ?? []; ?>
          <?php if (!empty($socials['facebook'])): ?>
          <a href="<?= e($socials['facebook']) ?>" target="_blank" rel="noreferrer" title="Facebook"
             class="social-pill">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
          </a>
          <?php endif; ?>
          <?php if (!empty($socials['linkedin'])): ?>
          <a href="<?= e($socials['linkedin']) ?>" target="_blank" rel="noreferrer" title="LinkedIn"
             class="social-pill">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
          </a>
          <?php endif; ?>
          <?php if (!empty($socials['twitter'])): ?>
          <a href="<?= e($socials['twitter']) ?>" target="_blank" rel="noreferrer" title="Twitter/X"
             class="social-pill">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          </a>
          <?php endif; ?>
          <?php if (!empty($socials['youtube'])): ?>
          <a href="<?= e($socials['youtube']) ?>" target="_blank" rel="noreferrer" title="YouTube"
             class="social-pill social-pill-yt">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><polygon points="9.75 15.02 15.5 12 9.75 8.98 9.75 15.02" fill="#fff"/></svg>
          </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Company -->
      <div>
        <h5 class="footer-heading"><?= e(__('footer_company')) ?></h5>
        <ul class="footer-list">
          <?php foreach ([['about.php','About Us'],['portfolio.php','Portfolio'],['news.php','News & Blog'],['careers.php','Careers'],['partners.php','Partners'],['contact.php','Contact']] as [$href,$label]): ?>
          <li>
            <a href="<?= url($href) ?>" class="footer-link-strong">
              <?= e($label) ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Products -->
      <div>
        <h5 class="footer-heading"><?= e(__('footer_products')) ?></h5>
        <ul class="footer-list">
          <?php if (!empty($_footerProducts)): ?>
            <?php foreach ($_footerProducts as $_fp): ?>
            <li>
              <a href="<?= !empty($_fp['slug']) ? url('product-detail.php?slug=' . urlencode($_fp['slug'])) : url('products.php') ?>" class="footer-link-strong">
                <?= e($_fp['name']) ?>
              </a>
            </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li><a href="<?= url('products.php') ?>" class="footer-link-strong">Our Products</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Resources -->
      <div>
        <h5 class="footer-heading"><?= e(__('footer_resources')) ?></h5>
        <ul class="footer-list">
         <?php foreach ([['faq.php','FAQ'],['pricing.php','Pricing'],['tools.php','Free Tools'],['services.php','Services'],['portal/index.php','Client Portal']] as [$href,$label]): ?>
          <li>
            <a href="<?= url($href) ?>" class="footer-link-strong">
              <?= e($label) ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>

      </div>
    </div>
  </div>

  <!-- Bottom bar -->
  <div class="footer-bottom-bar">
    <div class="container" style="padding-top:1rem;padding-bottom:1rem;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:0.75rem;">
      <p>
        <?php
          $__copyTpl = trim((string)($__s['copyright_text'] ?? ''));
          if ($__copyTpl === '' && function_exists('cms')) {
              $__copyTpl = cms($__s, 'copyright_text', '');
          }
          if ($__copyTpl !== '') {
              echo e(str_replace(
                  ['{year}', '{site}', '{company}'],
                  [date('Y'), stSiteName(), stCompanyName()],
                  $__copyTpl
              ));
          } else {
              echo sprintf(e(__('footer_copyright')), date('Y'), e($__s['site_name'] ?? SITE_NAME));
          }
        ?>
        | Developed &amp; Design By <a href="https://tankaadhikari.com.np/#about" target="_blank" rel="noopener noreferrer">Aakash Adhikari</a>
      </p>
      <div style="display:flex;align-items:center;gap:0.625rem;flex-wrap:wrap;">
        <a href="<?= url('privacy.php') ?>" class="footer-link">गोपनीयता</a>
        <span class="footer-dot">•</span>
        <a href="<?= url('terms.php') ?>" class="footer-link">सेवाका सर्त</a>
        <span class="footer-dot">•</span>
        <a href="<?= url('cookie-policy.php') ?>" class="footer-link">कुकी नीति</a>
        <span class="footer-dot">•</span>
        <a href="<?= url('sitemap.php') ?>" class="footer-link">Sitemap</a>
      </div>
    </div>
  </div>
</footer>

<!-- ── Floating Action Buttons (WhatsApp + AI + Live Chat) ── -->
<div class="st-float-actions">
<?php
  $__waUrl = function_exists('stWhatsAppUrl') ? stWhatsAppUrl() : '';
  $__waLabel = function_exists('stWhatsAppLabel') ? stWhatsAppLabel() : 'Support WhatsApp';
  $__aiOn = function_exists('stAiChatEnabled') && stAiChatEnabled();
  $__aiLabel = function_exists('stAiChatLabel') ? stAiChatLabel() : 'AI Assistant';
  $__aiWelcome = function_exists('stAiChatWelcome') ? stAiChatWelcome() : 'Hi! Ask me about our products and services.';
?>
<?php if ($__waUrl !== ''): ?>
  <a href="<?= e($__waUrl) ?>"
     target="_blank" rel="noopener noreferrer" class="whatsapp-btn st-float-btn" title="<?= e($__waLabel) ?> — chat with support" id="whatsapp-btn" aria-label="<?= e($__waLabel) ?>">
    <i data-lucide="message-circle" class="ic-20"></i>
    <span class="st-float-label"><?= e($__waLabel) ?></span>
  </a>
<?php endif; ?>

<?php if ($__aiOn): ?>
<div id="st-ai-widget" style="display:contents;">
  <button type="button" id="st-ai-btn" onclick="stAiToggle()" title="<?= e($__aiLabel) ?>"
    class="st-float-btn st-ai-trigger"
    aria-label="<?= e($__aiLabel) ?>"
    aria-expanded="false">
    <i data-lucide="bot" id="st-ai-icon-open" style="width:20px;height:20px;flex-shrink:0;"></i>
    <i data-lucide="x" id="st-ai-icon-close" style="width:18px;height:18px;display:none;flex-shrink:0;"></i>
    <span class="st-float-label"><?= e($__aiLabel) ?></span>
  </button>

  <div id="st-ai-panel" role="dialog" aria-label="<?= e($__aiLabel) ?>" style="display:none;width:22rem;border-radius:1.25rem;overflow:hidden;box-shadow:0 20px 60px rgba(15,23,42,0.18),0 4px 16px rgba(15,23,42,0.10);border:1px solid var(--border);background:var(--card);flex-direction:column;max-height:80vh;">
    <div style="background:linear-gradient(135deg,#0f766e,#0ea5e9);padding:1rem 1.25rem;display:flex;align-items:center;gap:0.75rem;">
      <div style="width:2.25rem;height:2.25rem;border-radius:9999px;background:rgba(255,255,255,0.2);display:grid;place-items:center;flex-shrink:0;">
        <i data-lucide="bot" style="width:16px;height:16px;color:#fff;"></i>
      </div>
      <div class="flex-1" style="min-width:0;">
        <div style="font-family:var(--font-display);font-weight:700;color:#fff;font-size:var(--text-base);"><?= e($__aiLabel) ?></div>
        <div style="font-size:var(--text-xs);color:rgba(255,255,255,0.75);">Public site info only</div>
      </div>
      <button type="button" onclick="stAiToggle()" title="Close" aria-label="Close AI Assistant"
        style="width:2rem;height:2rem;border-radius:9999px;border:none;background:rgba(255,255,255,0.18);color:#fff;display:grid;place-items:center;cursor:pointer;flex-shrink:0;padding:0;">
        <i data-lucide="x" style="width:16px;height:16px;"></i>
      </button>
    </div>
    <div id="st-ai-messages" style="flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:0.5rem;min-height:220px;max-height:320px;"></div>
    <div style="padding:0.75rem;border-top:1px solid var(--border);display:flex;gap:0.5rem;">
      <input type="text" id="st-ai-input" placeholder="Ask about products, pricing…" class="form-input" style="flex:1;font-size:var(--text-sm);"
             onkeydown="if(event.key==='Enter'){event.preventDefault();stAiSend();}">
      <button type="button" onclick="stAiSend()" class="btn btn-primary btn-sm" style="flex-shrink:0;padding:0 0.875rem;" id="st-ai-send-btn">
        <i data-lucide="send" class="ic-14"></i>
      </button>
    </div>
    <div style="padding:0.45rem 0.75rem 0.65rem;font-size:0.625rem;color:var(--muted-foreground);text-align:center;line-height:1.4;">
      Answers use public website content only — not admin or private data.
      <?php if ($__waUrl !== ''): ?> · <a href="<?= e($__waUrl) ?>" target="_blank" rel="noopener" class="text-primary">WhatsApp</a><?php endif; ?>
      · <a href="<?= url('contact.php') ?>" class="text-primary">Contact</a>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Live Chat Widget ── -->
<?php if (($__s['live_chat_enabled'] ?? true) !== false && ($__s['live_chat_enabled'] ?? '1') !== '0'): ?>
<div id="st-chat-widget" style="display:contents;">
  <button id="st-chat-btn" onclick="stChatToggle()" title="Chat with us"
    class="st-float-btn st-chat-trigger"
    aria-label="Open chat"
    aria-expanded="false">
    <i data-lucide="headphones" id="st-chat-icon-open" style="width:20px;height:20px;flex-shrink:0;"></i>
    <i data-lucide="x" id="st-chat-icon-close" style="width:18px;height:18px;display:none;flex-shrink:0;"></i>
    <span class="st-float-label">Live Chat</span>
  </button>

  <div id="st-chat-panel" style="display:none;width:22rem;border-radius:1.25rem;overflow:hidden;box-shadow:0 20px 60px rgba(15,23,42,0.18),0 4px 16px rgba(15,23,42,0.10);border:1px solid var(--border);background:var(--card);flex-direction:column;max-height:80vh;">
    <div style="background:var(--gradient-primary);padding:1rem 1.25rem;display:flex;align-items:center;gap:0.75rem;">
      <div style="width:2.25rem;height:2.25rem;border-radius:9999px;background:rgba(255,255,255,0.2);display:grid;place-items:center;flex-shrink:0;">
        <i data-lucide="headphones" style="width:16px;height:16px;color:#fff;"></i>
      </div>
      <div class="flex-1" style="min-width:0;">
        <div style="font-family:var(--font-display);font-weight:700;color:#fff;font-size:var(--text-base);"><?= e($__s['site_name'] ?? SITE_NAME) ?> Support</div>
        <div style="font-size:var(--text-xs);color:rgba(255,255,255,0.7);">Usually responds within minutes</div>
      </div>
      <button type="button" onclick="stChatToggle()" title="Close" aria-label="Close live chat"
        style="width:2rem;height:2rem;border-radius:9999px;border:none;background:rgba(255,255,255,0.18);color:#fff;display:grid;place-items:center;cursor:pointer;flex-shrink:0;padding:0;">
        <i data-lucide="x" style="width:16px;height:16px;"></i>
      </button>
    </div>
    <div id="st-chat-start" style="padding:1.25rem;display:flex;flex-direction:column;gap:0.875rem;">
      <p style="font-size:var(--text-sm);color:var(--muted-foreground);margin:0;">Hi there! Tell us your name and we'll connect you with our support team right away.</p>
      <input type="text" id="st-visitor-name" placeholder="Your name" class="form-input" style="font-size:var(--text-sm);">
      <input type="email" id="st-visitor-email" placeholder="Email (optional)" class="form-input" style="font-size:var(--text-sm);">
      <button onclick="stChatStart()" class="btn btn-primary btn-sm" style="width:100%;">Start Chat →</button>
      <p style="font-size:var(--text-xs);color:var(--muted-foreground);text-align:center;margin:0;">
        Or <a href="<?= url('portal/tickets-new.php') ?>" class="text-primary">open a tracked support ticket</a>
      </p>
    </div>
    <div id="st-chat-thread" style="display:none;flex-direction:column;flex:1;max-height:60vh;overflow:hidden;">
      <div id="st-chat-messages" style="flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:0.5rem;min-height:200px;max-height:300px;"></div>
      <div style="padding:0.75rem;border-top:1px solid var(--border);display:flex;gap:0.5rem;">
        <input type="text" id="st-msg-input" placeholder="Type a message…" class="form-input" style="flex:1;font-size:var(--text-sm);"
               onkeydown="if(event.key==='Enter')stChatSend()">
        <button onclick="stChatSend()" class="btn btn-primary btn-sm" style="flex-shrink:0;padding:0 0.875rem;">
          <i data-lucide="send" class="ic-14"></i>
        </button>
      </div>
      <div style="padding:0.5rem 0.75rem;font-size:0.625rem;color:var(--muted-foreground);text-align:center;">
        For tracked support → <a href="<?= url('portal/tickets-new.php') ?>" class="text-primary">Open a ticket</a>
      </div>
    </div>
  </div>
</div><!-- end #st-chat-widget -->
<?php endif; ?>
</div><!-- end .st-float-actions -->

<!-- ── Toast ── -->
<div id="toast-container" style="position:fixed;top:1.25rem;right:1.25rem;z-index:9999;display:flex;flex-direction:column;gap:0.625rem;pointer-events:none;"></div>

<!-- ── Scroll-to-top ── -->
<button id="st-scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Back to top" aria-label="Back to top" class="st-scroll-top">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>
</button>

<script>
/* ── Toast ── */
function showToast(msg, type='success') {
  const colors={success:'var(--success-fg)',error:'var(--danger-fg)',warning:'var(--warning-fg)',info:'var(--info-fg)'};
  const icons={
    success:'<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
    error:'<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    warning:'<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info:'<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
  };
  const t=document.createElement('div');
  t.style.cssText='display:flex;align-items:center;gap:0.75rem;padding:0.875rem 1.125rem;border-radius:0.75rem;box-shadow:0 8px 32px rgba(15,23,42,0.15);font-size:var(--text-sm);font-weight:500;border:1px solid;pointer-events:auto;max-width:380px;background:#fff;color:#1e293b;border-color:var(--border);animation:toast-in 0.25s cubic-bezier(0.34,1.56,0.64,1);';
  t.innerHTML=`<span style="color:${colors[type]}">${icons[type]}</span><span>${msg}</span>`;
  document.getElementById('toast-container').appendChild(t);
  setTimeout(()=>{t.style.transition='all 0.3s';t.style.opacity='0';t.style.transform='translateX(1rem)';setTimeout(()=>t.remove(),300);},4000);
}
function confirmDelete(form,msg){if(confirm(msg||'Are you sure?'))form.submit();}

/* ── Popup dismiss ── */
function stDismissPopup(id){
  document.getElementById('st-popup-'+id)?.remove();
  fetch('<?= url('api/popup.php') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+id});
}

/* ── Flash toasts from PHP ── */
<?php
$fs = getFlash('success'); $fe = getFlash('error'); $fw = getFlash('warning');
if ($fs) echo "document.addEventListener('DOMContentLoaded',()=>showToast(".json_encode($fs).",'success'));";
if ($fe) echo "document.addEventListener('DOMContentLoaded',()=>showToast(".json_encode($fe).",'error'));";
if ($fw) echo "document.addEventListener('DOMContentLoaded',()=>showToast(".json_encode($fw).",'warning'));";
?>

<?php if (!empty($__aiOn)): ?>
/* ── AI Assistant ── */
const ST_AI_URL = <?= json_encode(url('api/ai-chat.php')) ?>;
const ST_AI_WELCOME = <?= json_encode($__aiWelcome, JSON_UNESCAPED_UNICODE) ?>;
let stAiHistory = [];
let stAiBusy = false;
let stAiBooted = false;

function stAiToggle() {
  const panel = document.getElementById('st-ai-panel');
  const iconO = document.getElementById('st-ai-icon-open');
  const iconC = document.getElementById('st-ai-icon-close');
  const btn = document.getElementById('st-ai-btn');
  if (!panel) return;
  const isOpen = panel.style.display !== 'none';
  panel.style.display = isOpen ? 'none' : 'flex';
  panel.style.flexDirection = 'column';
  if (iconO) iconO.style.display = isOpen ? 'block' : 'none';
  if (iconC) iconC.style.display = isOpen ? 'none' : 'block';
  if (btn) btn.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
  // Close human live-chat panel if open
  const live = document.getElementById('st-chat-panel');
  if (!isOpen && live && live.style.display !== 'none') {
    live.style.display = 'none';
    const lo = document.getElementById('st-chat-icon-open');
    const lc = document.getElementById('st-chat-icon-close');
    if (lo) lo.style.display = 'block';
    if (lc) lc.style.display = 'none';
  }
  if (!isOpen && !stAiBooted) {
    stAiBooted = true;
    stAiAddBubble('assistant', ST_AI_WELCOME);
    setTimeout(() => document.getElementById('st-ai-input')?.focus(), 50);
  }
  if (typeof lucide !== 'undefined') lucide.createIcons();
}

function stAiEsc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function stAiAddBubble(role, text) {
  const box = document.getElementById('st-ai-messages');
  if (!box) return;
  const isMe = role === 'user';
  const div = document.createElement('div');
  div.style.cssText = 'display:flex;justify-content:' + (isMe ? 'flex-end' : 'flex-start') + ';';
  div.innerHTML = '<div style="max-width:85%;padding:0.5rem 0.75rem;border-radius:' +
    (isMe ? '1rem 0.25rem 1rem 1rem' : '0.25rem 1rem 1rem 1rem') +
    ';background:' + (isMe ? 'var(--primary)' : 'var(--muted)') +
    ';color:' + (isMe ? '#fff' : 'var(--foreground)') +
    ';font-size:var(--text-sm);line-height:1.5;white-space:pre-wrap;word-break:break-word;">' +
    stAiEsc(text) + '</div>';
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
}

function stAiSend() {
  if (stAiBusy) return;
  const input = document.getElementById('st-ai-input');
  const msg = (input?.value || '').trim();
  if (!msg) return;
  input.value = '';
  stAiAddBubble('user', msg);
  stAiHistory.push({ role: 'user', content: msg });
  stAiBusy = true;
  const btn = document.getElementById('st-ai-send-btn');
  if (btn) btn.disabled = true;
  stAiAddBubble('assistant', '…');
  const box = document.getElementById('st-ai-messages');
  const pending = box ? box.lastElementChild : null;

  fetch(ST_AI_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ message: msg, history: stAiHistory.slice(0, -1) })
  })
    .then(r => r.json().then(d => ({ ok: r.ok, d })))
    .then(({ ok, d }) => {
      const reply = (d && d.reply) ? d.reply : (d && d.error) ? d.error : 'Something went wrong.';
      if (pending) pending.remove();
      stAiAddBubble('assistant', reply);
      if (ok && d.reply) stAiHistory.push({ role: 'assistant', content: d.reply });
      if (stAiHistory.length > 16) stAiHistory = stAiHistory.slice(-16);
    })
    .catch(() => {
      if (pending) pending.remove();
      stAiAddBubble('assistant', 'Network error. Please try again or use Contact / WhatsApp.');
    })
    .finally(() => {
      stAiBusy = false;
      if (btn) btn.disabled = false;
      input?.focus();
    });
}
<?php endif; ?>

/* ── Live Chat ── */
let stConvId = localStorage.getItem('st_conv_id') ? parseInt(localStorage.getItem('st_conv_id')) : 0;
let stLastMsgId = 0;
let stPollTimer = null;
const CHAT_URL = '<?= url('api/chat.php') ?>';

function stChatToggle() {
  const panel = document.getElementById('st-chat-panel');
  const iconO  = document.getElementById('st-chat-icon-open');
  const iconC  = document.getElementById('st-chat-icon-close');
  const isOpen = panel.style.display !== 'none';
  panel.style.display = isOpen ? 'none' : 'flex';
  panel.style.flexDirection = 'column';
  iconO.style.display = isOpen ? 'block' : 'none';
  iconC.style.display = isOpen ? 'none' : 'block';
  // Close AI panel if open
  const ai = document.getElementById('st-ai-panel');
  if (!isOpen && ai && ai.style.display !== 'none') {
    ai.style.display = 'none';
    const ao = document.getElementById('st-ai-icon-open');
    const ac = document.getElementById('st-ai-icon-close');
    if (ao) ao.style.display = 'block';
    if (ac) ac.style.display = 'none';
    const ab = document.getElementById('st-ai-btn');
    if (ab) ab.setAttribute('aria-expanded', 'false');
  }
  if (!isOpen && stConvId) { stChatShowThread(); stStartPoll(); }
  if (typeof lucide !== 'undefined') lucide.createIcons();
}

function stChatStart() {
  const name  = document.getElementById('st-visitor-name').value.trim();
  const email = document.getElementById('st-visitor-email').value.trim();
  if (!name) { showToast('Please enter your name.','warning'); return; }
  const fd = new FormData();
  fd.append('action','start'); fd.append('visitor_name',name); fd.append('visitor_email',email);
  fetch(CHAT_URL, {method:'POST', body:fd})
    .then(r=>r.json())
    .then(data=>{
      if (data.ok) {
        stConvId = data.id;
        localStorage.setItem('st_conv_id', stConvId);
        stChatShowThread();
        stAddMsg('admin','Hi, '+data.visitor_name+'! Thanks for reaching out. Our team will respond shortly. You can also open a tracked support ticket from the Client Portal.',true);
        stStartPoll();
      } else { showToast(data.error||'Failed to start chat.','error'); }
    }).catch(()=>showToast('Network error.','error'));
}

function stChatShowThread() {
  document.getElementById('st-chat-start').style.display = 'none';
  document.getElementById('st-chat-thread').style.display = 'flex';
}

function stChatSend() {
  const input = document.getElementById('st-msg-input');
  const msg   = input.value.trim();
  if (!msg || !stConvId) return;
  input.value = '';
  stAddMsg('visitor', msg, true);
  const fd = new FormData();
  fd.append('action','send'); fd.append('conv_id',stConvId); fd.append('message',msg);
  fetch(CHAT_URL,{method:'POST',body:fd}).catch(()=>{});
}

function stAddMsg(sender, text, isNew=false) {
  const box  = document.getElementById('st-chat-messages');
  const isMe = sender === 'visitor';
  const div  = document.createElement('div');
  div.style.cssText = 'display:flex;justify-content:'+(isMe?'flex-end':'flex-start')+';';
  const bubble = document.createElement('div');
  bubble.style.cssText = 'max-width:80%;padding:0.5rem 0.75rem;border-radius:'+(isMe?'1rem 0.25rem 1rem 1rem':'0.25rem 1rem 1rem 1rem')+';background:'+(isMe?'var(--primary)':'var(--muted)')+';color:'+(isMe?'#fff':'var(--foreground)')+';font-size:var(--text-sm);line-height:1.5;white-space:pre-wrap;word-break:break-word;';
  bubble.textContent = String(text || '').replace(/<[^>]+>/g, ' ');
  div.appendChild(bubble);
  box.appendChild(div);
  box.scrollTop = box.scrollHeight;
}

function stStartPoll() {
  if (stPollTimer) clearInterval(stPollTimer);
  if (!stConvId) return;
  stPollTimer = setInterval(stPoll, 6000);
}

function stPoll() {
  if (!stConvId) return;
  fetch(CHAT_URL+'?action=poll&conv_id='+stConvId+'&since_id='+stLastMsgId)
    .then(r=>r.json())
    .then(data=>{
      if (data.ok && data.messages?.length) {
        data.messages.forEach(m=>{
          if (m.sender==='admin') stAddMsg('admin', m.message);
          stLastMsgId = Math.max(stLastMsgId, m.id);
        });
      }
    }).catch(()=>{});
}

document.addEventListener('DOMContentLoaded',()=>{
  if (stConvId) { stChatShowThread(); stPoll(); stStartPoll(); }
});

/* ── Newsletter subscribe ── */
function stSubscribe(e){
  e.preventDefault();
  const email = document.getElementById('sub-email-input').value.trim();
  const btn   = document.getElementById('sub-submit-btn');
  if (!email) return;
  btn.disabled = true; btn.textContent = '…';
  fetch('<?= url('api/index.php') ?>?r=newsletter', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({email})
  }).then(r=>r.json()).then(d=>{
    if (d.data) { showToast('Subscribed! Thank you.','success'); document.getElementById('sub-email-input').value=''; }
    else         showToast(d.message||'Could not subscribe.','error');
  }).catch(()=>showToast('Network error. Please try again.','error'))
  .finally(()=>{ btn.disabled=false; btn.textContent='Subscribe'; });
}

/* ── Scroll-to-top visibility + lazy-load images ── */
(function(){
  var btn = document.getElementById('st-scroll-top');
  if (btn) {
    window.addEventListener('scroll', function() {
      btn.classList.toggle('visible', window.scrollY > 320);
    }, {passive: true});
  }
  if ('IntersectionObserver' in window) {
    var imgObs = new IntersectionObserver(function(entries) {
      entries.forEach(function(e) {
        if (e.isIntersecting) {
          var img = e.target;
          img.addEventListener('load', function(){ img.classList.add('loaded'); });
          if (img.complete) img.classList.add('loaded');
          imgObs.unobserve(img);
        }
      });
    }, {rootMargin: '120px'});
    document.querySelectorAll('img[loading="lazy"]').forEach(function(img) {
      imgObs.observe(img);
    });
  } else {
    document.querySelectorAll('img[loading="lazy"]').forEach(function(img){ img.style.opacity='1'; });
  }
})();
</script>

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('<?= SITE_URL ?>/sw.js').catch(function(){});
  });
}
</script>
</body>
</html>
