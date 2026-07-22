/**
 * Narayani Infosys — Nepali BS Datepicker  (v1.3)
 * Self-contained. No external dependencies.
 *
 * Usage:  <input type="date" name="foo" data-bs-picker>
 *   → The original input is hidden (holds AD date for PHP form submission).
 *   → A visible BS text display + calendar popup is injected next to it.
 *
 * API:
 *   window.initBsPickers()          — re-scan DOM (call after dynamic inserts)
 *   window.adToBs(y, m, d)          → { y, m, d }
 *   window.bsToAd(by, bm, bd)       → Date (JS Date object)
 */
;(function(global) {
  'use strict';

  /* ── Month / Day labels ───────────────────────────────────────── */
  var BS_M_EN = ['Baisakh','Jestha','Ashadh','Shrawan','Bhadra','Ashwin','Kartik','Mangsir','Poush','Magh','Falgun','Chaitra'];
  var BS_M_NE = ['बैशाख','जेठ','असार','श्रावण','भाद्र','आश्विन','कार्तिक','मंसिर','पुष','माघ','फाल्गुण','चैत्र'];
  var BS_M_NE_NUM = ['१', '२', '३', '४', '५', '६', '७', '८', '९', '१०', '११', '१२'];
  var BS_M    = BS_M_NE; // Default to Nepali
  var DAY_LABELS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

  /* ── BS Month-day lookup table  (year → 12 month lengths) ────────
     Reference epoch: BS 2000/1/1 = AD 1943/4/14 (Thursday)
     Source: GoN / Department of Printing official calendar            */
  var T = {
    2000:[30,32,31,32,31,30,30,30,29,30,29,31],
    2001:[31,31,32,31,31,31,30,29,30,29,30,30],
    2002:[31,31,32,32,31,30,30,29,30,29,30,30],
    2003:[31,32,31,32,31,30,30,30,29,29,30,31],
    2004:[30,32,31,32,31,30,30,30,29,30,29,31],
    2005:[31,31,32,31,31,31,30,29,30,29,30,30],
    2006:[31,31,32,32,31,30,30,29,30,29,30,30],
    2007:[31,32,31,32,31,30,30,30,29,29,30,31],
    2008:[31,31,31,32,31,31,29,30,30,29,29,31],
    2009:[31,31,32,31,31,31,30,29,30,29,30,30],
    2010:[31,31,32,32,31,30,30,29,30,29,30,30],
    2011:[31,32,31,32,31,30,30,30,29,29,30,31],
    2012:[31,31,31,32,31,31,29,30,30,29,30,30],
    2013:[31,31,32,31,31,31,30,29,30,29,30,30],
    2014:[31,31,32,32,31,30,30,29,30,29,30,30],
    2015:[31,32,31,32,31,30,30,30,29,29,30,31],
    2016:[31,31,31,32,31,31,29,30,30,29,30,30],
    2017:[31,31,32,31,31,31,30,29,30,29,30,30],
    2018:[31,32,31,32,31,30,30,29,30,29,30,30],
    2019:[31,32,31,32,31,30,30,30,29,30,29,31],
    2020:[31,31,31,32,31,31,30,29,30,29,30,30],
    2021:[31,31,32,31,31,31,30,29,30,29,30,30],
    2022:[31,32,31,32,31,30,30,30,29,29,30,30],
    2023:[31,32,31,32,31,30,30,30,29,30,29,31],
    2024:[31,31,31,32,31,31,30,29,30,29,30,30],
    2025:[31,31,32,31,31,31,30,29,30,29,30,30],
    2026:[31,32,31,32,31,30,30,30,29,29,30,31],
    2027:[30,32,31,32,31,30,30,30,29,30,29,31],
    2028:[31,31,32,31,31,31,30,29,30,29,30,30],
    2029:[31,31,32,31,32,30,30,29,30,29,30,30],
    2030:[31,32,31,32,31,30,30,30,29,29,30,31],
    2031:[30,32,31,32,31,30,30,30,29,30,29,31],
    2032:[31,31,32,31,31,31,30,29,30,29,30,30],
    2033:[31,31,32,32,31,30,30,29,30,29,30,30],
    2034:[31,32,31,32,31,30,30,30,29,29,30,31],
    2035:[30,32,31,32,31,31,29,30,30,29,29,31],
    2036:[31,31,32,31,31,31,30,29,30,29,30,30],
    2037:[31,31,32,32,31,30,30,29,30,29,30,30],
    2038:[31,32,31,32,31,30,30,30,29,29,30,31],
    2039:[31,31,31,32,31,31,29,30,30,29,30,30],
    2040:[31,31,32,31,31,31,30,29,30,29,30,30],
    2041:[31,31,32,32,31,30,30,29,30,29,30,30],
    2042:[31,32,31,32,31,30,30,30,29,29,30,31],
    2043:[31,31,31,32,31,31,29,30,30,29,30,30],
    2044:[31,31,32,31,31,31,30,29,30,29,30,30],
    2045:[31,32,31,32,31,30,30,29,30,29,30,30],
    2046:[31,32,31,32,31,30,30,30,29,29,30,31],
    2047:[31,31,31,32,31,31,30,29,30,29,30,30],
    2048:[31,31,32,31,31,31,30,29,30,29,30,30],
    2049:[31,32,31,32,31,30,30,30,29,29,30,30],
    2050:[31,32,31,32,31,30,30,30,29,30,29,31],
    2051:[31,31,31,32,31,31,30,29,30,29,30,30],
    2052:[31,31,32,31,31,31,30,29,30,29,30,30],
    2053:[31,32,31,32,31,30,30,30,29,29,30,30],
    2054:[31,32,31,32,31,30,30,30,29,30,29,31],
    2055:[31,31,32,31,31,31,30,29,30,29,30,30],
    2056:[31,31,32,31,32,30,30,29,30,29,30,30],
    2057:[31,32,31,32,31,30,30,30,29,29,30,31],
    2058:[30,32,31,32,31,30,30,30,29,30,29,31],
    2059:[31,31,32,31,31,31,30,29,30,29,30,30],
    2060:[31,31,32,32,31,30,30,29,30,29,30,30],
    2061:[31,32,31,32,31,30,30,30,29,29,30,31],
    2062:[30,32,31,32,31,31,29,30,29,30,29,31],
    2063:[31,31,32,31,31,31,30,29,30,29,30,30],
    2064:[31,31,32,32,31,30,30,29,30,29,30,30],
    2065:[31,32,31,32,31,30,30,30,29,29,30,31],
    2066:[31,31,31,32,31,31,29,30,30,29,29,31],
    2067:[31,31,32,31,31,31,30,29,30,29,30,30],
    2068:[31,31,32,32,31,30,30,29,30,29,30,30],
    2069:[31,32,31,32,31,30,30,30,29,29,30,31],
    2070:[31,31,31,32,31,31,29,30,30,29,30,30],
    2071:[31,31,32,31,31,31,30,29,30,29,30,30],
    2072:[31,32,31,32,31,30,30,29,30,29,30,30],
    2073:[31,32,31,32,31,30,30,30,29,29,30,31],
    2074:[31,31,31,32,31,31,30,29,30,29,30,30],
    2075:[31,31,32,31,31,31,30,29,30,29,30,30],
    2076:[31,32,31,32,31,30,30,30,29,29,30,30],
    2077:[31,32,31,32,31,30,30,30,29,30,29,31],
    2078:[31,31,31,32,31,31,30,29,30,29,30,30],
    2079:[31,31,32,31,31,31,30,29,30,29,30,30],
    2080:[31,32,31,32,31,30,30,30,29,29,30,30],
    2081:[31,32,31,32,31,30,30,30,30,30,29,30],
    2082:[31,31,32,31,31,31,30,29,30,29,30,30],
    2083:[31,31,32,31,31,31,30,29,30,29,30,30],
    2084:[31,31,32,31,31,30,30,30,29,30,30,30],
    2085:[31,32,31,32,30,31,30,30,29,30,30,30],
    2086:[30,32,31,32,31,30,30,30,29,30,30,30],
    2087:[31,31,32,31,31,31,30,30,29,30,30,30],
    2088:[30,31,32,32,30,31,30,30,29,30,30,30],
    2089:[30,32,31,32,31,30,30,30,29,30,30,30],
    2090:[30,32,31,32,31,30,30,30,29,30,30,30],
    2091:[31,31,32,31,31,31,30,30,29,30,30,30],
    2092:[30,31,32,32,31,30,30,30,29,30,30,30],
    2093:[30,32,31,32,31,30,30,30,29,30,30,30],
    2094:[31,31,32,31,31,30,30,30,29,30,30,30],
    2095:[31,31,32,31,31,31,30,29,30,30,30,30],
    2096:[30,31,32,32,31,30,30,29,30,29,30,30],
    2097:[31,32,31,32,31,30,30,30,29,30,30,30],
    2098:[31,31,32,31,31,31,29,30,29,30,30,31],
    2099:[31,31,32,31,31,31,30,29,29,30,30,30],
    2100:[31,32,31,32,30,31,30,29,30,29,30,30],
  };

  var EPOCH_BS = {y:2000, m:1, d:1};
  var EPOCH_AD = new Date(1943, 3, 14); // April 14, 1943

  /* ── Days since BS epoch ─────────────────────────────────────── */
  function daysSinceBsEpoch(by, bm, bd) {
    var days = 0;
    for (var y = EPOCH_BS.y; y < by; y++) {
      if (!T[y]) break;
      for (var mi = 0; mi < 12; mi++) days += T[y][mi];
    }
    var row = T[by] || T[EPOCH_BS.y];
    for (var mi = 0; mi < bm - 1; mi++) days += row[mi];
    days += bd - 1;
    return days;
  }

  /* ── BS → AD ──────────────────────────────────────────────────── */
  function bsToAd(by, bm, bd) {
    var days = daysSinceBsEpoch(by, bm, bd);
    var ad = new Date(EPOCH_AD);
    ad.setDate(ad.getDate() + days);
    return ad;
  }

  /* ── AD → BS ──────────────────────────────────────────────────── */
  function adToBs(ay, am, ad) {
    var adDate = new Date(ay, am - 1, ad);
    var diffMs = adDate - EPOCH_AD;
    var diffDays = Math.round(diffMs / 86400000);
    if (diffDays < 0) return null;
    var by = EPOCH_BS.y, bm = 1, bd = 1;
    while (diffDays > 0) {
      var row = T[by];
      if (!row) break;
      var daysInMonth = row[bm - 1];
      if (diffDays >= daysInMonth) {
        diffDays -= daysInMonth;
        bm++;
        if (bm > 12) { bm = 1; by++; }
      } else {
        bd += diffDays;
        diffDays = 0;
      }
    }
    return { y: by, m: bm, d: bd };
  }

  /* ── Zero-pad ────────────────────────────────────────────────── */
  function z2(n) { return n < 10 ? '0' + n : '' + n; }

  /* ── Format BS date for display (Nepali) ──────────────────────── */
  function formatBs(y, m, d) {
    return toNepaliNum(d) + ' ' + BS_M[m - 1] + ', ' + toNepaliNum(y);
  }

  /* ── AD date string (for hidden input) ───────────────────────── */
  function toAdStr(d) {
    return d.getFullYear() + '-' + z2(d.getMonth() + 1) + '-' + z2(d.getDate());
  }

  function parseAdStr(str) {
    if (!str) return null;
    var p = str.split('T')[0].split('-');
    if (p.length < 3) return null;
    var y = +p[0], m = +p[1], d = +p[2];
    if (!y || !m || !d) return null;
    return new Date(y, m - 1, d);
  }

  function adTs(d) {
    return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
  }

  function isValidJobAd(d) {
    return d && adTs(d) >= adTs(new Date(1970, 0, 1));
  }

  function todayAdStart() {
    var n = new Date();
    return new Date(n.getFullYear(), n.getMonth(), n.getDate());
  }

  /* ── Convert number to Nepali numeral ───────────────────────── */
  function toNepaliNum(n) {
    var nepaliDigits = ['०','१','२','३','४','५','६','७','८','९'];
    return String(n).replace(/[0-9]/g, function(d) { return nepaliDigits[d]; });
  }

  /* ── Build calendar grid HTML ────────────────────────────────── */
  function buildGrid(year, month, selectedBs) {
    var row = T[year];
    if (!row) return '<p style="padding:1rem;color:var(--muted-foreground);font-size:.8rem;">Year out of range</p>';
    var daysInMonth = row[month - 1];
    var firstAd = bsToAd(year, month, 1);
    var startDow = firstAd.getDay(); // 0=Sun

    var html = '<div class="st-bsp-days-header">';
    DAY_LABELS.forEach(function(d) { html += '<div>' + d + '</div>'; });
    html += '</div><div class="st-bsp-grid">';

    for (var i = 0; i < startDow; i++) html += '<div></div>';
    for (var d = 1; d <= daysInMonth; d++) {
      var sel = selectedBs && selectedBs.y === year && selectedBs.m === month && selectedBs.d === d;
      html += '<button type="button" class="st-bsp-day' + (sel ? ' selected' : '') + '" data-d="' + d + '">' + toNepaliNum(d) + '</button>';
    }
    html += '</div>';
    return html;
  }

  /* ── Create picker for one input ────────────────────────────── */
  function createPicker(hidden) {
    if (hidden.dataset.bsPickerInit) return;
    hidden.dataset.bsPickerInit = '1';
    hidden.style.display = 'none';

    var isOptional   = hidden.hasAttribute('data-bs-optional');
    var minToday     = hidden.hasAttribute('data-bs-min-today');
    var defaultToday = hidden.hasAttribute('data-bs-default-today');
    var minAdStr     = hidden.getAttribute('data-bs-min-ad');
    var minAdDate    = minAdStr ? parseAdStr(minAdStr) : (minToday ? todayAdStart() : null);
    if (minAdDate && !isValidJobAd(minAdDate)) minAdDate = todayAdStart();

    // Check if datetime-local
    var isDateTime = hidden.type === 'datetime-local';
    var savedTime = '';
    
    // Determine initial BS date from AD value (YYYY-MM-DD or YYYY-MM-DDTHH:MM)
    var initBs = null;
    if (hidden.value) {
      var adInit = parseAdStr(hidden.value);
      if (isValidJobAd(adInit)) {
        initBs = adToBs(adInit.getFullYear(), adInit.getMonth() + 1, adInit.getDate());
        if (isDateTime && hidden.value.indexOf('T') !== -1) {
          savedTime = hidden.value.split('T')[1].substring(0, 5);
        }
        // Keep hidden value in canonical AD form
        hidden.value = toAdStr(adInit) + (isDateTime && savedTime ? 'T' + savedTime + ':00' : '');
      } else {
        hidden.value = '';
      }
    }

    // Required date fields with no value → default to today (e.g. Application Deadline)
    if (!initBs && defaultToday && !isOptional) {
      var todayDef = todayAdStart();
      initBs = adToBs(todayDef.getFullYear(), todayDef.getMonth() + 1, todayDef.getDate());
      hidden.value = toAdStr(todayDef);
    }

    var today = new Date();
    var curBs = initBs || adToBs(today.getFullYear(), today.getMonth() + 1, today.getDate());
    var viewY = curBs.y, viewM = curBs.m;

    function adAllowed(ad) {
      if (!isValidJobAd(ad)) return false;
      if (minAdDate && adTs(ad) < adTs(minAdDate)) return false;
      return true;
    }

    function setDate(by, bm, bd, ad) {
      if (!adAllowed(ad)) return false;
      initBs = { y: by, m: bm, d: bd };
      var timeVal = timeInput ? timeInput.value : '';
      hidden.value = toAdStr(ad) + (isDateTime && timeVal ? 'T' + timeVal + ':00' : '');
      disp.value   = formatBs(by, bm, bd);
      adLabel.textContent = ad.toLocaleDateString('en-GB', {year:'numeric',month:'short',day:'numeric'}) + ' AD';
      clearBtn.style.display = '';
      hidden.dispatchEvent(new Event('change', {bubbles:true}));
      return true;
    }

    function clearDate() {
      initBs = null;
      hidden.value = '';
      disp.value   = '';
      clearBtn.style.display = 'none';
      adLabel.textContent = '';
    }

    // Wrapper
    var wrap = document.createElement('div');
    wrap.className = 'st-bsp-wrap';
    wrap.style.cssText = 'position:relative;display:flex;width:100%;align-items:center;gap:0.5rem;';
    hidden.parentNode.insertBefore(wrap, hidden);
    wrap.appendChild(hidden);

    // Display input
    var disp = document.createElement('input');
    disp.type = 'text';
    disp.readOnly = true;
    disp.className = (hidden.className || 'form-input') + ' st-bsp-display';
    disp.placeholder = 'BS मिति छान्नुहोस्';
    disp.style.cssText = 'cursor:pointer;padding-right:2.25rem;';
    if (initBs) {
      disp.value = formatBs(initBs.y, initBs.m, initBs.d);
    }
    wrap.insertBefore(disp, hidden);
    
    // Time input for datetime-local
    var timeInput = null;
    if (isDateTime) {
      timeInput = document.createElement('input');
      timeInput.type = 'time';
      timeInput.className = 'form-input st-bsp-time';
      timeInput.style.cssText = 'width:100px;flex-shrink:0;margin-left:0.5rem;';
      timeInput.value = savedTime || (function() {
        var now = new Date();
        var h = String(now.getHours()).padStart(2,'0');
        var m = String(now.getMinutes()).padStart(2,'0');
        return h + ':' + m;
      })();
      // Insert BEFORE hidden, AFTER display
      wrap.insertBefore(timeInput, hidden.nextSibling);
    }

    // Calendar icon (after time input for datetime)
    var icon = document.createElement('span');
    icon.innerHTML = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
    icon.style.cssText = 'position:absolute;right:.625rem;top:50%;transform:translateY(-50%);color:var(--muted-foreground);pointer-events:none;display:flex;align-items:center;';
    wrap.appendChild(icon);

    // Clear button (only visible when a date is set)
    var clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.innerHTML = '&times;';
    clearBtn.className = 'st-bsp-clear';
    clearBtn.title = 'Clear date';
    clearBtn.style.display = initBs ? '' : 'none';
    wrap.appendChild(clearBtn);

    // Popup - append to wrapper for proper containment
    var popup = document.createElement('div');
    popup.className = 'st-bsp-popup';
    popup.style.display = 'none';
    popup.innerHTML =
      '<div class="st-bsp-header">' +
        '<button type="button" class="st-bsp-nav" id="bsp-prev">&#8249;</button>' +
        '<div class="st-bsp-title"></div>' +
        '<button type="button" class="st-bsp-nav" id="bsp-next">&#8250;</button>' +
      '</div>' +
      '<div class="st-bsp-body"></div>' +
      '<div class="st-bsp-footer">' +
        '<span class="st-bsp-ad-label"></span>' +
        '<button type="button" class="st-bsp-today">Today</button>' +
      '</div>';
    wrap.appendChild(popup);

    var titleEl  = popup.querySelector('.st-bsp-title');
    var bodyEl   = popup.querySelector('.st-bsp-body');
    var adLabel  = popup.querySelector('.st-bsp-ad-label');
    var btnPrev  = popup.querySelector('#bsp-prev');
    var btnNext  = popup.querySelector('#bsp-next');
    var btnToday = popup.querySelector('.st-bsp-today');

    if (initBs) {
      var initAd = bsToAd(initBs.y, initBs.m, initBs.d);
      adLabel.textContent = initAd.toLocaleDateString('en-GB', {year:'numeric',month:'short',day:'numeric'}) + ' AD';
    }

    function renderPopup() {
      titleEl.textContent = toNepaliNum(viewY) + ' ' + BS_M[viewM - 1];
      bodyEl.innerHTML = buildGrid(viewY, viewM, initBs);
      bodyEl.querySelectorAll('.st-bsp-day').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var d = +this.dataset.d;
          var ad = bsToAd(viewY, viewM, d);
          if (!setDate(viewY, viewM, d, ad)) {
            disp.style.borderColor = 'var(--danger, #dc2626)';
            setTimeout(function() { disp.style.borderColor = ''; }, 1200);
            return;
          }
          closePopup();
        });
      });
      var firstAd = bsToAd(viewY, viewM, 1);
      adLabel.textContent = firstAd.toLocaleDateString('en-GB', {year:'numeric',month:'short',day:'numeric'}) + ' AD';
    }

    function openPopup() {
      renderPopup();
      // Show offscreen first to measure real height
      popup.style.visibility = 'hidden';
      popup.style.display = 'block';
      popup.style.position = 'absolute';
      popup.style.zIndex = '9999';

      var popH   = popup.offsetHeight;
      var popW   = popup.offsetWidth || 272;
      var wrapW  = wrap.offsetWidth;
      var vw     = window.innerWidth;
      var vh     = window.innerHeight;
      var GAP    = 6;

      // Vertical: prefer below; flip above if not enough space
      var rect = disp.getBoundingClientRect();
      var wrapRect = wrap.getBoundingClientRect();
      
      // Position relative to wrapper
      var top;
      if (rect.bottom + GAP + popH <= vh) {
        top = rect.bottom - wrapRect.top + GAP;           // below
      } else if (rect.top - GAP - popH >= 0) {
        top = -(popH + GAP);       // above
      } else {
        top = -(popH + GAP); // above by default
      }

      // Horizontal: align to left edge, clamp within viewport
      var left = Math.max(-GAP, Math.min(rect.left - wrapRect.left, vw - wrapRect.left - popW - GAP));

      popup.style.top        = top + 'px';
      popup.style.left       = left + 'px';
      popup.style.visibility = '';
    }
    function closePopup() {
      popup.style.display = 'none';
    }

    disp.addEventListener('click', function(e) {
      e.stopPropagation();
      popup.style.display === 'none' ? openPopup() : closePopup();
    });
    btnPrev.addEventListener('click', function(e) {
      e.stopPropagation();
      viewM--; if (viewM < 1) { viewM = 12; viewY--; }
      renderPopup();
    });
    btnNext.addEventListener('click', function(e) {
      e.stopPropagation();
      viewM++; if (viewM > 12) { viewM = 1; viewY++; }
      renderPopup();
    });
    btnToday.addEventListener('click', function(e) {
      e.stopPropagation();
      var now = new Date();
      var t = adToBs(now.getFullYear(), now.getMonth() + 1, now.getDate());
      viewY = t.y; viewM = t.m;
      if (setDate(t.y, t.m, t.d, now)) {
        renderPopup();
        closePopup();
      }
    });
    clearBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      clearDate();
    });
    
    // Sync time with hidden field on time change
    if (timeInput) {
      timeInput.addEventListener('change', function() {
        if (initBs) {
          var ad = bsToAd(initBs.y, initBs.m, initBs.d);
          hidden.value = toAdStr(ad) + 'T' + timeInput.value + ':00';
        }
      });
    }
    document.addEventListener('click', function(e) {
      if (!wrap.contains(e.target)) closePopup();
    });

    var form = hidden.closest('form');
    if (form) {
      if (!form._bsPickerSubmitHook) {
        form._bsPickerSubmitHook = true;
        form.addEventListener('submit', function() {
          form.querySelectorAll('[data-bs-picker]').forEach(function(el) {
            var w = el.closest('.st-bsp-wrap');
            if (!w) return;
            var dEl = w.querySelector('.st-bsp-display');
            if (el.hasAttribute('data-bs-optional') && dEl && !dEl.value.trim()) {
              el.value = '';
            }
          });
        });
      }
    }
  }

  /* ── Public API ──────────────────────────────────────────────── */
  global.adToBs = adToBs;
  global.bsToAd = bsToAd;
  global.initBsPickers = function() {
    document.querySelectorAll('[data-bs-picker]').forEach(function(el) {
      // Skip if already processed (has wrapper)
      if (el.dataset.bsPickerDone) return;
      el.dataset.bsPickerDone = '1';
      createPicker(el);
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', global.initBsPickers);
  } else {
    global.initBsPickers();
  }

})(window);
