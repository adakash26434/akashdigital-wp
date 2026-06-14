<?php // Shared contact form fields for admin/support-contacts.php ?>
<div>
  <label class="form-label">Label <span class="text-danger-token">*</span></label>
  <input type="text" name="label" required class="form-input" placeholder="e.g. Main Office, WhatsApp Support">
</div>
<div>
  <label class="form-label">Type</label>
  <select name="type" class="form-input">
    <option value="phone"> Phone / Call</option>
    <option value="whatsapp"> WhatsApp</option>
    <option value="email"> Email</option>
    <option value="emergency"> Emergency</option>
    <option value="address"> Address / Location</option>
    <option value="branch"> Branch Office</option>
  </select>
</div>
<div>
  <label class="form-label">Department</label>
  <select name="department" class="form-input">
    <option value="">— General / All —</option>
    <option value="complaint">Complaint</option>
    <option value="account">Account</option>
    <option value="legal">Legal</option>
    <option value="administration">Administration</option>
    <option value="audit">Audit</option>
    <option value="marketing">Marketing</option>
    <option value="support">Support</option>
  </select>
</div>
<div>
  <label class="form-label">Value <span class="text-danger-token">*</span></label>
  <input type="text" name="value" required class="form-input" placeholder="+977 980-000-0000 or email or address">
</div>
<div>
  <label class="form-label">Description / Hours</label>
  <input type="text" name="description" class="form-input" placeholder="e.g. Mon–Fri 9am–6pm">
</div>
<div>
  <label class="form-label">Display Order</label>
  <input type="number" name="position" class="form-input" value="0" min="0">
</div>
<div style="display:flex;align-items:center;gap:0.5rem;">
  <input type="checkbox" name="is_primary" id="is_primary_cb" style="width:1rem;height:1rem;">
  <label for="is_primary_cb" class="form-label" style="margin:0;">Mark as primary contact</label>
</div>
