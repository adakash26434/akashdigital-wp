<?php
/**
 * includes/admin-img-upload.php
 * Reusable inline image-upload widget for admin forms.
 *
 * Usage — before calling this file, set:
 *   $imgField   = 'image_url';          // form field name
 *   $imgValue   = $editing['image_url'] ?? ''; // current value
 *   $imgLabel   = 'Image';              // label text
 *   $imgRequired = true;                // optional, default false
 *
 * The widget renders:
 *   - A hidden <input> with the field name holding the final URL
 *   - A preview of the current image (if any)
 *   - A file-picker button that uploads via /api/admin-upload.php
 *   - A small text field showing/allowing manual URL entry as fallback
 */
$_iu_field    = $imgField    ?? 'image_url';
$_iu_val      = $imgValue    ?? '';
$_iu_label    = $imgLabel    ?? 'Image';
$_iu_required = !empty($imgRequired);
$_iu_uid      = 'imgup_' . bin2hex(random_bytes(4)); // unique per widget
?>

<div style="display:flex;flex-direction:column;gap:0.5rem;">
  <label class="form-label fs-2xs2">
    <?= e($_iu_label) ?>
    <?php if ($_iu_required): ?><span class="text-danger-token">*</span><?php endif; ?>
  </label>

    <!-- Hidden field used for JS-syncing; this is the submitted value -->
    <input type="hidden"
      id="<?= $_iu_uid ?>_hidden"
      name="<?= e($_iu_field) ?>"
      value="<?= e($_iu_val) ?>">

  <!-- Preview -->
  <div id="<?= $_iu_uid ?>_preview"
       style="<?= $_iu_val ? '' : 'display:none;' ?>width:100%;max-height:140px;overflow:hidden;border-radius:0.625rem;border:1px solid var(--border);background:var(--muted);">
    <img alt="Image" id="<?= $_iu_uid ?>_img"
         src="<?= e($_iu_val) ?>"
         alt="Preview"
         style="width:100%;max-height:140px;object-fit:cover;display:block;">
  </div>

  <!-- Upload button + file input -->
  <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
    <label style="cursor:pointer;">
      <input type="file"
             id="<?= $_iu_uid ?>_file"
             accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml"
             style="display:none;"
             onchange="stImgUpload('<?= $_iu_uid ?>')">
      <span id="<?= $_iu_uid ?>_btn"
            class="btn btn-sm btn-ghost"
            style="border:1px dashed var(--border);display:inline-flex;align-items:center;gap:0.375rem;">
        <i data-lucide="upload" style="width:14px;height:14px;"></i>
        <span id="<?= $_iu_uid ?>_btnlabel">Upload Image</span>
      </span>
    </label>
    <?php if ($_iu_val): ?>
    <button type="button"
            onclick="stImgClear('<?= $_iu_uid ?>')"
            id="<?= $_iu_uid ?>_clear"
            class="btn btn-sm"
            style="color:var(--danger-fg);background:var(--danger-soft);border:none;">
      <i data-lucide="x" style="width:12px;height:12px;"></i> Remove
    </button>
    <?php endif; ?>
  </div>

  <!-- Fallback: paste URL manually -->
  <details style="margin-top:0.25rem;">
    <summary style="font-size:var(--text-xs);color:var(--muted-foreground);cursor:pointer;user-select:none;">
      Or paste image URL manually
    </summary>
        <input type="url"
          id="<?= $_iu_uid ?>_url"
          /* manual URL field is a fallback and not the primary submitted value */
          name="<?= e($_iu_field) ?>_manual"
           class="form-input fs-sm2"
           style="margin-top:0.375rem;"
           placeholder="https://..."
           value="<?= e($_iu_val) ?>"
           oninput="stImgUrlInput('<?= $_iu_uid ?>', this.value)">
  </details>
</div>

<script>
(function() {
  // Guard: only define once
  if (window._stImgUploadReady) return;
  window._stImgUploadReady = true;

  window.stImgUpload = function(uid) {
    var file = document.getElementById(uid + '_file').files[0];
    if (!file) return;
    var btn = document.getElementById(uid + '_btn');
    var lbl = document.getElementById(uid + '_btnlabel');
    lbl.textContent = 'Uploading…';
    btn.style.opacity = '0.6';
    var fd = new FormData();
    fd.append('file', file);
    fetch('<?= url('api/admin-upload.php') ?>', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.ok && d.url) {
          stImgSet(uid, d.url);
          if (typeof showToast === 'function') showToast('Image uploaded!', 'success');
        } else {
          if (typeof showToast === 'function') showToast(d.error || 'Upload failed.', 'error');
        }
      })
      .catch(function() {
        if (typeof showToast === 'function') showToast('Network error during upload.', 'error');
      })
      .finally(function() {
        lbl.textContent = 'Upload Image';
        btn.style.opacity = '1';
      });
  };

  window.stImgSet = function(uid, url) {
    document.getElementById(uid + '_hidden').value = url;
    document.getElementById(uid + '_img').src = url;
    document.getElementById(uid + '_preview').style.display = '';
    var urlInput = document.getElementById(uid + '_url');
    if (urlInput) urlInput.value = url;
    // Show clear button
    var clr = document.getElementById(uid + '_clear');
    if (!clr) {
      var btn = document.getElementById(uid + '_btn').parentElement.parentElement;
      var c = document.createElement('button');
      c.type = 'button'; c.id = uid + '_clear';
      c.className = 'btn btn-sm';
      c.style.cssText = 'color:var(--danger-fg);background:var(--danger-soft);border:none;';
      c.innerHTML = '<i data-lucide="x" style="width:12px;height:12px;"></i> Remove';
      c.onclick = function() { stImgClear(uid); };
      btn.appendChild(c);
      if (window.lucide) lucide.createIcons();
    }
  };

  window.stImgClear = function(uid) {
    document.getElementById(uid + '_hidden').value = '';
    document.getElementById(uid + '_preview').style.display = 'none';
    document.getElementById(uid + '_img').src = '';
    var urlInput = document.getElementById(uid + '_url');
    if (urlInput) urlInput.value = '';
    var clr = document.getElementById(uid + '_clear');
    if (clr) clr.remove();
  };

  window.stImgUrlInput = function(uid, val) {
    document.getElementById(uid + '_hidden').value = val;
    var img = document.getElementById(uid + '_img');
    var prev = document.getElementById(uid + '_preview');
    if (val) { img.src = val; prev.style.display = ''; }
    else { prev.style.display = 'none'; }
  };
})();
</script>
<?php
// Reset vars so they don't bleed to next widget call
unset($imgField, $imgValue, $imgLabel, $imgRequired, $_iu_field, $_iu_val, $_iu_label, $_iu_required, $_iu_uid);
?>
