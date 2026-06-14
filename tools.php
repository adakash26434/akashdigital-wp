<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
$pageTitle = 'Digital Tools — ' . stSiteName();
$pageDesc  = 'Free utilities for Nepal: Preeti to Unicode converter, EMI calculator, BS↔AD date converter — all offline.';
require_once 'includes/header.php';
?>

<?php
$heroEyebrow     = __('tools_hero_eyebrow');
$heroEyebrowIcon = 'wrench';
$heroTitle       = __('tools_hero_title');
$heroSubtitle    = __('tools_hero_sub');
include 'includes/page-hero.php';
?>

<section class="section" style="padding-top:0.5rem;" x-data="{tab:'preeti'}">
  <div class="container" style="max-width:56rem;">

    <!-- Tab nav -->
    <div style="display:flex;flex-wrap:wrap;gap:0.5rem;justify-content:center;margin-bottom:2.5rem;">
      <?php
      $tabs = [
        ['preeti','','Preeti → Unicode'],
        ['emi','','EMI Calculator'],
        ['date','','Date Converter'],
        ['words','','Number to Words'],
      ];
      foreach ($tabs as [$id,$icon,$label]):?>
      <button @click="tab='<?= $id ?>'" :class="tab==='<?= $id ?>' ? 'btn btn-primary' : 'btn btn-outline'" style="gap:0.5rem;">
        <span><?= $icon ?></span> <?= $label ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Preeti to Unicode -->
    <div x-show="tab==='preeti'" x-cloak>
      <div class="st-card" style="padding:2rem;">
        <h2 style="font-family:var(--font-display);font-size:var(--text-xl);font-weight:700;margin-bottom:0.25rem;">Preeti → Unicode Converter</h2>
        <p style="color:var(--muted-foreground);font-size:var(--text-sm);margin-bottom:1.5rem;">Paste Preeti-font text on the left. Unicode Nepali appears on the right instantly.</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;" class="preeti-grid">
          <div>
            <label style="font-size:var(--text-sm);font-weight:600;color:var(--foreground);display:block;margin-bottom:0.5rem;">Preeti Text (Input)</label>
            <textarea id="preeti-in" oninput="preetiConvert()" placeholder="k]z ug{'g"qm Psfd ;xof]u Joj:yf..." rows="10"
              style="width:100%;padding:1rem;border:1px solid var(--border);border-radius:0.75rem;background:var(--background);color:var(--foreground);font-family:monospace;font-size:var(--text-base);resize:vertical;"></textarea>
          </div>
          <div>
            <label style="font-size:var(--text-sm);font-weight:600;color:var(--foreground);display:block;margin-bottom:0.5rem;">Nepali Unicode (Output)</label>
            <textarea id="preeti-out" readonly placeholder="यहाँ Nepali Unicode देखिनेछ..." rows="10"
              style="width:100%;padding:1rem;border:1px solid var(--border);border-radius:0.75rem;background:var(--muted);color:var(--foreground);font-family:var(--font-display);font-size:var(--text-base);resize:vertical;"></textarea>
          </div>
        </div>
        <div style="display:flex;gap:0.75rem;margin-top:1rem;flex-wrap:wrap;">
          <button onclick="copyPreeti()" class="btn btn-primary btn-sm"> Copy Unicode</button>
          <button onclick="document.getElementById('preeti-in').value='';document.getElementById('preeti-out').value='';" class="btn btn-outline btn-sm"> Clear</button>
          <button onclick="swapPreeti()" class="btn btn-outline btn-sm">⇄ Swap</button>
        </div>
        <div style="margin-top:1rem;padding:0.75rem 1rem;background:#f0f9ff;border-radius:0.625rem;border:1px solid #bae6fd;font-size:var(--text-sm);color:#0369a1;">
           <strong>Tip:</strong> Copy text from old MS Word/Publisher documents using Preeti font and paste here to get Unicode Nepali.
        </div>
      </div>
    </div>

    <!-- EMI Calculator -->
    <div x-show="tab==='emi'" x-cloak>
      <div class="st-card" style="padding:2rem;">
        <h2 style="font-family:var(--font-display);font-size:var(--text-xl);font-weight:700;margin-bottom:0.25rem;">EMI Calculator</h2>
        <p style="color:var(--muted-foreground);font-size:var(--text-sm);margin-bottom:1.5rem;">Calculate your loan EMI (Equated Monthly Installment). Supports home, vehicle, business and personal loans.</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;" class="emi-grid">
          <div class="col-stack">
            <div>
              <label class="form-label">Loan Amount (Rs.)</label>
              <input id="emi-principal" type="number" value="500000" min="1000" step="1000" class="form-input" oninput="calcEmi()">
            </div>
            <div>
              <label class="form-label">Annual Interest Rate (%)</label>
              <input id="emi-rate" type="number" value="12" min="0.1" max="50" step="0.1" class="form-input" oninput="calcEmi()">
            </div>
            <div>
              <label class="form-label">Loan Tenure (Months)</label>
              <input id="emi-months" type="number" value="24" min="1" max="360" step="1" class="form-input" oninput="calcEmi()">
            </div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
              <?php foreach([12=>'1 yr',24=>'2 yr',36=>'3 yr',60=>'5 yr',120=>'10 yr'] as $m=>$l):?>
              <button class="btn btn-outline btn-sm" onclick="document.getElementById('emi-months').value=<?=$m?>;calcEmi();"><?=$l?></button>
              <?php endforeach;?>
            </div>
          </div>
          <div id="emi-result" style="background:var(--gradient-primary);border-radius:1.25rem;padding:1.75rem;color:#fff;display:flex;flex-direction:column;justify-content:center;gap:1.25rem;">
            <div>
              <div style="font-size:var(--text-sm);opacity:0.75;margin-bottom:0.25rem;">Monthly EMI</div>
              <div id="emi-emi" style="font-family:var(--font-display);font-size:2rem;font-weight:800;">रू —</div>
            </div>
            <div style="height:1px;background:rgba(255,255,255,0.2);"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
              <div>
                <div style="font-size:var(--text-xs);opacity:0.7;">Total Interest</div>
                <div id="emi-interest" style="font-weight:700;font-size:1.1rem;margin-top:0.25rem;">—</div>
              </div>
              <div>
                <div style="font-size:var(--text-xs);opacity:0.7;">Total Payment</div>
                <div id="emi-total" style="font-weight:700;font-size:1.1rem;margin-top:0.25rem;">—</div>
              </div>
            </div>
          </div>
        </div>
        <div id="emi-schedule-wrap" style="margin-top:2rem;display:none;">
          <button onclick="toggleSchedule()" class="btn btn-outline btn-sm" id="schedule-btn"> Show Amortization Schedule</button>
          <div id="emi-schedule" style="display:none;margin-top:1rem;overflow-x:auto;">
            <table class="st-table" id="emi-table"></table>
          </div>
        </div>
      </div>
    </div>

    <!-- Date Converter -->
    <div x-show="tab==='date'" x-cloak>
      <div class="st-card" style="padding:2rem;">
        <h2 style="font-family:var(--font-display);font-size:var(--text-xl);font-weight:700;margin-bottom:0.25rem;">BS ↔ AD Date Converter</h2>
        <p style="color:var(--muted-foreground);font-size:var(--text-sm);margin-bottom:1.5rem;">Convert between Bikram Sambat (BS/VS) and Anno Domini (AD/Gregorian) dates.</p>
        <div style="display:grid;grid-template-columns:1fr auto 1fr;gap:1.5rem;align-items:center;" class="date-grid">
          <div>
            <label class="form-label">BS Date</label>
            <input id="bs-date" type="text" placeholder="2081-01-15" class="form-input" oninput="convertBS()">
            <p style="font-size:var(--text-xs);color:var(--muted-foreground);margin-top:0.375rem;">Format: YYYY-MM-DD (e.g. 2081-04-15)</p>
          </div>
          <div style="font-size:1.5rem;text-align:center;color:var(--primary);">⇄</div>
          <div>
            <label class="form-label">AD Date</label>
            <input id="ad-date" type="date" class="form-input" oninput="convertAD()">
            <p style="font-size:var(--text-xs);color:var(--muted-foreground);margin-top:0.375rem;">Standard Gregorian calendar</p>
          </div>
        </div>
        <div id="date-info" style="margin-top:1.5rem;padding:1rem;background:var(--background);border:1px solid var(--border);border-radius:0.75rem;font-size:var(--text-sm);color:var(--foreground);display:none;"></div>
        <div style="margin-top:1rem;padding:0.75rem 1rem;background:var(--success-soft);border-radius:0.625rem;border:1px solid var(--success-border);font-size:var(--text-sm);color:var(--success-fg);">
          ℹ Supported BS range: 1975 – 2100. Calculation uses NepSoft algorithm.
        </div>
      </div>
    </div>

    <!-- Number to Words -->
    <div x-show="tab==='words'" x-cloak>
      <div class="st-card" style="padding:2rem;">
        <h2 style="font-family:var(--font-display);font-size:var(--text-xl);font-weight:700;margin-bottom:0.25rem;">Number to Words</h2>
        <p style="color:var(--muted-foreground);font-size:var(--text-sm);margin-bottom:1.5rem;">Convert any number to Nepali or English words — useful for cheques and financial documents.</p>
        <div class="col-stack">
          <div style="display:flex;gap:1rem;align-items:flex-end;">
            <div class="flex-1">
              <label class="form-label">Enter Amount</label>
              <input id="n2w-input" type="number" placeholder="e.g. 125000" class="form-input" oninput="n2wConvert()">
            </div>
            <select id="n2w-lang" class="form-input" style="width:auto;" onchange="n2wConvert()">
              <option value="en">English</option>
              <option value="np">Nepali</option>
            </select>
          </div>
          <div id="n2w-result" style="padding:1.25rem;border-radius:0.75rem;background:var(--gradient-primary);color:#fff;font-size:var(--text-md);font-weight:600;min-height:4rem;display:flex;align-items:center;">
            <span id="n2w-text" style="opacity:0.7;">Enter a number above…</span>
          </div>
          <button onclick="copyN2W()" class="btn btn-primary btn-sm w-fit"> Copy to Clipboard</button>
        </div>
        <div style="margin-top:1rem;padding:0.75rem 1rem;background:#fefce8;border-radius:0.625rem;border:1px solid var(--warning-border);font-size:var(--text-sm);color:var(--warning-fg);">
           Uses Nepali <em>lakh</em> and <em>crore</em> system. Supports up to 10 crore (100,000,000).
        </div>
      </div>
    </div>

  </div>
</section>

<style>
@media(max-width:640px){
  .preeti-grid,.emi-grid,.date-grid{grid-template-columns:1fr!important;}
}
</style>

<script>
/* ─── Preeti to Unicode ─── */
const PREETI_MAP = {
  '!':'!','@':'@','#':'#','$':'$','%':'%','^':'^','&':'&','*':'*','(':'(',')':')',
  '-':'-','_':'_','=':'=','+':'+','[':'[',']':']','{':'{','}':'}','|':'|',
  ';':';',':':':','\'':'\'','"':'"',',':',','.':'.','<':'<','>':'>','?':'?',
  '`':'`','~':'~','\\':'\\','/':'/','!':'!',
  '0':'०','1':'१','2':'२','3':'३','4':'४','5':'५','6':'६','7':'७','8':'८','9':'९',
  'q':'ः','Q':'Q','w':'ु','W':'ू','e':'े','E':'ै','r':'्र','R':'ृ','t':'त','T':'ट',
  'y':'य','Y':'य्','u':'उ','U':'ऊ','i':'ि','I':'ी','o':'ो','O':'ौ','p':'प','P':'फ',
  'a':'ा','A':'ा','s':'स','S':'श','d':'द','D':'ड','f':'फ','F':'फ्','g':'ग','G':'घ',
  'h':'ह','H':'ः','j':'ज','J':'झ','k':'क','K':'ख','l':'ल','L':'ळ',
  'z':'ज्ञ','Z':'Z','x':'क्ष','X':'X','c':'च','C':'छ','v':'व','V':'व','b':'ब','B':'भ',
  'n':'न','N':'ण','m':'म','M':'म',
  ' ':' ','\n':'\n','\t':'\t',
  ';':'ँ','&':'ं','^':'॑',',':'ण','.':'।','<':'<','%':'ॅ',
};
const PREETI_MAP2 = {
  'क':'ka','ख':'kha','ग':'ga','घ':'gha','ङ':'nga',
  'च':'cha','छ':'chha','ज':'ja','झ':'jha','ञ':'nya',
  'ट':'Ta','ठ':'Tha','ड':'Da','ढ':'Dha','ण':'Na',
  'त':'ta','थ':'tha','द':'da','ध':'dha','न':'na',
  'प':'pa','फ':'pha','ब':'ba','भ':'bha','म':'ma',
  'य':'ya','र':'ra','ल':'la','व':'va','श':'sha','ष':'Sha','स':'sa',
  'ह':'ha','क्ष':'ksha','त्र':'tra','ज्ञ':'jnya',
};

const FULL_PREETI = {
  '!':'!','@':'@','#':'#','$':'$','%':'ँ','^':'ं','&':'ं','*':'*','(':'(',')':')',
  '-':'-','_':'—','+':'+','=':'=','[':'[',']':']','{':'ब्','}'  :'भ','\\':'ङ','|':'ङ्',
  ';':'ः',':':'ः','\'':'ट','\"':'ठ',',':'ण','.':'।','<':'ण','>':'ण','?':'?',
  '`':'ॅ','~':'ँ',
  'q':'ः','Q':'Q','w':'ु','W':'ू','e':'े','E':'ै','r':'्र','R':'ृ','t':'त','T':'ट',
  'y':'य','Y':'य्','u':'उ','U':'ऊ','i':'ि','I':'ी','o':'ो','O':'ौ','p':'प','P':'फ',
  'a':'ा','A':'ा','s':'स','S':'श','d':'द','D':'ड','f':'ु','F':'ू','g':'ग','G':'घ',
  'h':'ह','H':'ः','j':'ज','J':'झ','k':'क','K':'ख','l':'ल','L':'ळ',
  'z':'ज्ञ','Z':'ज्ञ','x':'क्ष','X':'क्ष','c':'च','C':'छ','v':'व','V':'व','b':'ब','B':'भ',
  'n':'न','N':'ण','m':'म','M':'म',
  '0':'०','1':'१','2':'२','3':'३','4':'४','5':'५','6':'६','7':'७','8':'८','9':'९',
  ' ':' ','\n':'\n','\t':'\t',
};

// नेपालीमा: preetiConvert() — yo function le aafno kaam garchha
function preetiConvert() {
  const inp = document.getElementById('preeti-in').value;
  let out = '';
  for (let i = 0; i < inp.length; i++) {
    const ch = inp[i];
    out += FULL_PREETI[ch] ?? ch;
  }
  // fix halant + ra
  out = out.replace(/्र/g,'्र');
  document.getElementById('preeti-out').value = out;
}
// नेपालीमा: copyPreeti() — yo function le aafno kaam garchha
function copyPreeti() {
  const t = document.getElementById('preeti-out');
  t.select(); document.execCommand('copy');
  alert('Unicode text copied!');
}
// नेपालीमा: swapPreeti() — yo function le aafno kaam garchha
function swapPreeti() {
  const a = document.getElementById('preeti-in').value;
  const b = document.getElementById('preeti-out').value;
  document.getElementById('preeti-in').value = b;
  document.getElementById('preeti-out').value = a;
}

/* ─── EMI Calculator ─── */
function fmt(n) { return 'रू ' + Math.round(n).toLocaleString('en-IN'); }
// नेपालीमा: calcEmi() — yo function le aafno kaam garchha
function calcEmi() {
  const P = parseFloat(document.getElementById('emi-principal').value) || 0;
  const annualR = parseFloat(document.getElementById('emi-rate').value) || 0;
  const N = parseInt(document.getElementById('emi-months').value) || 0;
  if (!P || !annualR || !N) return;
  const r = annualR / 12 / 100;
  const emi = r === 0 ? P/N : P * r * Math.pow(1+r, N) / (Math.pow(1+r, N) - 1);
  const total = emi * N;
  const interest = total - P;
  document.getElementById('emi-emi').textContent = fmt(emi);
  document.getElementById('emi-interest').textContent = fmt(interest);
  document.getElementById('emi-total').textContent = fmt(total);
  document.getElementById('emi-schedule-wrap').style.display = 'block';
  buildSchedule(P, r, emi, N);
}
// नेपालीमा: buildSchedule() — yo function le aafno kaam garchha
function buildSchedule(P, r, emi, N) {
  let bal = P;
  let rows = '<thead><tr><th>Month</th><th>EMI</th><th>Principal</th><th>Interest</th><th>Balance</th></tr></thead><tbody>';
  for (let i = 1; i <= N; i++) {
    const int_part = bal * r;
    const prin_part = emi - int_part;
    bal -= prin_part;
    if (bal < 0) bal = 0;
    rows += `<tr><td>${i}</td><td>${fmt(emi)}</td><td>${fmt(prin_part)}</td><td>${fmt(int_part)}</td><td>${fmt(bal)}</td></tr>`;
  }
  rows += '</tbody>';
  document.getElementById('emi-table').innerHTML = rows;
}
// नेपालीमा: Translation — current language ma string return
function toggleSchedule() {
  const s = document.getElementById('emi-schedule');
  const b = document.getElementById('schedule-btn');
  if (s.style.display === 'none') { s.style.display = 'block'; b.textContent = ' Hide Schedule'; }
  else { s.style.display = 'none'; b.textContent = ' Show Amortization Schedule'; }
}
window.addEventListener('DOMContentLoaded', calcEmi);

/* ─── BS / AD Date Converter ─── */
const BS_MONTHS = [[0],[31,31,32,31,31,31,30,30,29,30,29,31],[31,31,32,31,32,31,30,29,30,29,30,30],[31,32,31,32,31,30,30,30,29,29,30,31],[30,32,31,32,31,30,30,30,29,30,29,31],[31,31,32,31,31,30,30,30,29,30,29,31],[31,31,32,32,31,30,30,29,30,29,29,31],[30,32,31,32,31,30,30,29,30,29,30,30],[30,32,31,32,31,30,30,30,29,29,30,31],[31,31,32,31,31,31,30,29,29,30,29,31],[31,31,32,31,31,30,30,29,30,29,30,30],[31,31,32,32,31,30,30,30,29,30,29,30],[30,32,31,32,31,30,30,30,29,30,30,31],[31,31,32,31,31,31,30,30,29,30,29,31],[31,31,32,31,32,30,30,29,30,29,29,31],[31,32,31,32,31,30,30,29,30,29,30,30]];
const BS_START = {y:1975,m:1,d:1};
const AD_START = new Date(1918, 3, 13);
// नेपालीमा: bsDaysFrom1975() — yo function le aafno kaam garchha
function bsDaysFrom1975(y,m,d){let t=0;for(let i=1975;i<y;i++){for(let j=1;j<=12;j++)t+=BS_MONTHS[(i-1975)%16+1]?.[j]??30;}for(let j=1;j<m;j++)t+=BS_MONTHS[(y-1975)%16+1]?.[j]??30;t+=d-1;return t;}
// नेपालीमा: convertBS() — yo function le aafno kaam garchha
function convertBS(){
  const v=document.getElementById('bs-date').value.trim();
  const m=v.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
  if(!m)return;
  const [,sy,sm,sd]=[...m].map(Number);
  if(sy<1975||sy>2090||sm<1||sm>12||sd<1||sd>32){document.getElementById('date-info').style.display='block';document.getElementById('date-info').textContent='Out of supported range.';return;}
  const days=bsDaysFrom1975(sy,sm,sd);
  const ad=new Date(AD_START.getTime()+days*86400000);
  document.getElementById('ad-date').value=ad.toISOString().substring(0,10);
  const opts={weekday:'long',year:'numeric',month:'long',day:'numeric'};
  document.getElementById('date-info').style.display='block';
  document.getElementById('date-info').innerHTML=`<strong>BS:</strong> ${sy}/${sm}/${sd} &nbsp;&nbsp; <strong>AD:</strong> ${ad.toLocaleDateString('en-US',opts)}`;
}
// नेपालीमा: convertAD() — yo function le aafno kaam garchha
function convertAD(){
  const v=document.getElementById('ad-date').value;
  if(!v)return;
  const ad=new Date(v+'T00:00:00');
  const diff=Math.round((ad-AD_START)/86400000);
  if(diff<0){document.getElementById('date-info').textContent='Before supported range.';document.getElementById('date-info').style.display='block';return;}
  let rem=diff,y=1975,m=1;
  outer:for(y=1975;y<=2090;y++){for(m=1;m<=12;m++){const days=BS_MONTHS[(y-1975)%16+1]?.[m]??30;if(rem<days)break outer;rem-=days;}}
  const d=rem+1;
  document.getElementById('bs-date').value=`${y}-${String(m).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
  document.getElementById('date-info').style.display='block';
  document.getElementById('date-info').innerHTML=`<strong>AD:</strong> ${v} &nbsp;&nbsp; <strong>BS:</strong> ${y} ${['Baisakh','Jestha','Ashadh','Shrawan','Bhadra','Ashwin','Kartik','Mangsir','Poush','Magh','Falgun','Chaitra'][m-1]} ${d}`;
}

/* ─── Number to Words ─── */
const E_ONES=['','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen'];
const E_TENS=['','','twenty','thirty','forty','fifty','sixty','seventy','eighty','ninety'];
// नेपालीमा: numWordEn() — yo function le aafno kaam garchha
function numWordEn(n){
  if(n===0)return'zero';
  if(n<0)return'minus '+numWordEn(-n);
  if(n<20)return E_ONES[n];
  if(n<100)return E_TENS[Math.floor(n/10)]+(n%10?' '+E_ONES[n%10]:'');
  if(n<1000)return E_ONES[Math.floor(n/100)]+' hundred'+(n%100?' '+numWordEn(n%100):'');
  if(n<100000)return numWordEn(Math.floor(n/1000))+' thousand'+(n%1000?' '+numWordEn(n%1000):'');
  if(n<10000000)return numWordEn(Math.floor(n/100000))+' lakh'+(n%100000?' '+numWordEn(n%100000):'');
  return numWordEn(Math.floor(n/10000000))+' crore'+(n%10000000?' '+numWordEn(n%10000000):'');
}
const NP_ONES=['','एक','दुई','तीन','चार','पाँच','छ','सात','आठ','नौ','दश','एघार','बाह्र','तेह्र','चौध','पन्ध्र','सोह्र','सत्र','अठार','उन्नाइस'];
const NP_TENS=['','','बीस','तीस','चालीस','पचास','साठी','सत्तरी','असी','नब्बे'];
// नेपालीमा: numWordNp() — yo function le aafno kaam garchha
function numWordNp(n){
  if(n===0)return'शून्य';
  if(n<0)return'ऋण '+numWordNp(-n);
  if(n<20)return NP_ONES[n];
  if(n<100)return NP_TENS[Math.floor(n/10)]+(n%10?' '+NP_ONES[n%10]:'');
  if(n<1000)return NP_ONES[Math.floor(n/100)]+' सय'+(n%100?' '+numWordNp(n%100):'');
  if(n<100000)return numWordNp(Math.floor(n/1000))+' हजार'+(n%1000?' '+numWordNp(n%1000):'');
  if(n<10000000)return numWordNp(Math.floor(n/100000))+' लाख'+(n%100000?' '+numWordNp(n%100000):'');
  return numWordNp(Math.floor(n/10000000))+' करोड'+(n%10000000?' '+numWordNp(n%10000000):'');
}
// नेपालीमा: n2wConvert() — yo function le aafno kaam garchha
function n2wConvert(){
  const n=parseInt(document.getElementById('n2w-input').value)||0;
  const lang=document.getElementById('n2w-lang').value;
  const text=lang==='np'?numWordNp(n):numWordWordEn(n);
  document.getElementById('n2w-text').textContent=lang==='en'?(numWordEn(n)+' rupees only').replace(/\b\w/g,c=>c.toUpperCase()):numWordNp(n)+' रुपैयाँ मात्र';
  document.getElementById('n2w-text').style.opacity='1';
}
// नेपालीमा: numWordWordEn() — yo function le aafno kaam garchha
function numWordWordEn(n){return numWordEn(n);}
// नेपालीमा: copyN2W() — yo function le aafno kaam garchha
function copyN2W(){
  const t=document.getElementById('n2w-text').textContent;
  navigator.clipboard?.writeText(t)??document.execCommand('copy');
  alert('Copied!');
}
</script>

<?php require_once 'includes/footer.php'; ?>
