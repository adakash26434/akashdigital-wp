    </main>
  </div>
</div>

<?php require_once __DIR__ . '/toast.php'; ?>

<?php 
// Notice popup for admin pages
$currentPage = 'admin';
include __DIR__ . '/notice-popup.php';
?>
<script>
// toggleTheme() is defined globally in includes/head.php — available on all pages
function confirmDelete(form,msg){if(confirm(msg||'Delete this item? This cannot be undone.'))form.submit();}

// ── Sidebar navigation functions ──────────────────────────────
function toggleNavGroup(id){
  const el=document.getElementById(id);
  const chev=document.getElementById(id+'-chevron');
  if(!el)return;
  const open=el.style.display!=='none';
  el.style.display=open?'none':'block';
  if(chev)chev.style.transform=open?'rotate(0deg)':'rotate(180deg)';
  try{const st=JSON.parse(localStorage.getItem('st-nav-groups')||'{}');st[id]=!open;localStorage.setItem('st-nav-groups',JSON.stringify(st));}catch(e){}
}
// Restore nav group states (don't override active group)
(function(){
  try{
    const st=JSON.parse(localStorage.getItem('st-nav-groups')||'{}');
    Object.entries(st).forEach(([id,open])=>{
      const el=document.getElementById(id);
      const chev=document.getElementById(id+'-chevron');
      if(el&&el.style.display!==''&&!open){el.style.display='none';if(chev)chev.style.transform='rotate(0deg)';}
    });
  }catch(e){}
})();
function openAdminSidebar(){
  document.getElementById('admin-sidebar').classList.add('sidebar-open');
  document.getElementById('admin-sidebar-overlay').classList.add('show');
  document.getElementById('admin-sidebar-close-btn').style.display='flex';
  document.body.style.overflow='hidden';
}
function closeAdminSidebar(){
  document.getElementById('admin-sidebar').classList.remove('sidebar-open');
  document.getElementById('admin-sidebar-overlay').classList.remove('show');
  document.body.style.overflow='';
}
function checkAdminSidebarBtn(){
  const btn=document.getElementById('admin-sidebar-open-btn');
  if(window.innerWidth<768){btn.style.display='flex';}
  else{btn.style.display='none';closeAdminSidebar();}
}
checkAdminSidebarBtn();
window.addEventListener('resize',checkAdminSidebarBtn);

// ── Alert auto-dismiss (5 seconds) ────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.alert-success, .alert-error, .alert').forEach(function(el) {
    if (el.classList.contains('alert-persistent')) return;
    setTimeout(function() {
      el.style.transition = 'opacity 0.5s, transform 0.5s';
      el.style.opacity = '0'; el.style.transform = 'translateY(-6px)';
      setTimeout(function(){ if(el.parentNode) el.parentNode.removeChild(el); }, 520);
    }, 5000);
  });
});

// ── Global form submit loading state ──────────────────────────
document.addEventListener('submit', function(e) {
  const form = e.target;
  if (form.dataset.noLoading) return;
  const btn = form.querySelector('button[type="submit"]');
  if (!btn || btn.dataset.loading) return;
  btn.dataset.loading = '1';
  const origHtml = btn.innerHTML;
  btn.innerHTML = '<span class="btn-spinner"></span>' + (btn.dataset.loadingText || btn.textContent.trim() + '…');
  setTimeout(() => { if (btn.dataset.loading) { btn.innerHTML = origHtml; delete btn.dataset.loading; } }, 12000);
});

// ── In-panel form tab switching (.af-tab-btn / .af-tab-pane) ──────────────
(function(){
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.af-tab-btn');
    if (!btn) return;
    var nav  = btn.closest('.af-tab-nav');
    var form = btn.closest('form');
    if (!nav || !form) return;
    nav.querySelectorAll('.af-tab-btn').forEach(function(b){ b.classList.remove('active'); });
    btn.classList.add('active');
    form.querySelectorAll('.af-tab-pane').forEach(function(p){ p.classList.remove('active'); });
    var pane = form.querySelector('[data-tab-pane="' + btn.dataset.tab + '"]');
    if (pane) pane.classList.add('active');
  });
})();

// ── Admin Tab Layout (aft-list / aft-form panels) ─────────────
(function(){
  var listEl=document.getElementById('aft-list');
  var formEl=document.getElementById('aft-form');
  var tabs=document.querySelectorAll('.af-page-tab');
  if(!listEl||!formEl||tabs.length<2)return;
  var isEdit=!!new URLSearchParams(location.search).get('edit');
  tabs[0].addEventListener('click',function(e){
    e.preventDefault();
    listEl.style.display='';formEl.style.display='none';
    tabs[0].classList.add('active');tabs[1].classList.remove('active');
  });
  if(!isEdit){
    tabs[1].addEventListener('click',function(e){
      e.preventDefault();
      listEl.style.display='none';formEl.style.display='';
      tabs[0].classList.remove('active');tabs[1].classList.add('active');
    });
  }
})();

<?php
$s = getFlash('success'); $e2 = getFlash('error'); $w = getFlash('warning');
if ($s)  echo "document.addEventListener('DOMContentLoaded',()=>showToast(".json_encode($s).",'success'));";
if ($e2) echo "document.addEventListener('DOMContentLoaded',()=>showToast(".json_encode($e2).",'error'));";
if ($w)  echo "document.addEventListener('DOMContentLoaded',()=>showToast(".json_encode($w).",'warning'));";
?>
</script>
</body>
</html>
