<?php
// Shared subscription form fields (used by add + edit modals in admin/subscriptions.php)
$prefix = isset($prefix) ? $prefix : (isset($id) ? 'edit' : 'add');
?>
<div class="af-modal-form">
  <div class="form-section">
    <div class="form-section-title">Client & product</div>
    <div class="form-group">
      <label class="form-label">Client <span class="text-danger-token">*</span></label>
      <select name="user_id" id="<?=$prefix?>-user_id" required class="form-input">
        <option value="">Select client…</option>
      </select>
    </div>
    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label">Product (catalog)</label>
        <select name="product_id" id="<?=$prefix?>-product_id" class="form-input">
          <option value="">— none —</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Product / Software Name <span class="text-danger-token">*</span></label>
        <input type="text" name="product_name" required class="form-input" placeholder="e.g. Core Banking System">
      </div>
    </div>
    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label">Plan</label>
        <input type="text" name="plan_name" class="form-input" placeholder="e.g. Growth, Enterprise"
               list="plan-name-suggestions" autocomplete="off">
        <datalist id="plan-name-suggestions">
          <?php foreach(($GLOBALS['_sub_planNames'] ?? []) as $_spn): ?>
          <option value="<?= e($_spn) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label class="form-label">License Key</label>
        <input type="text" name="license_key" class="form-input" placeholder="e.g. ANK-2025-XXXXX">
      </div>
    </div>
    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label">Deployment</label>
        <select name="deployment_type" class="form-input">
          <option value="cloud">Cloud</option>
          <option value="on-premise">On-Premise</option>
          <option value="hybrid">Hybrid</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Branches</label>
        <input type="number" name="branches" class="form-input" value="1" min="1">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Members Limit</label>
      <input type="number" name="members_limit" class="form-input" placeholder="e.g. 5000">
    </div>
  </div>

  <div class="form-section">
    <div class="form-section-title">Billing & status</div>
    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label">Amount (NPR)</label>
        <input type="number" name="amount" class="form-input" placeholder="e.g. 12999" step="0.01">
      </div>
      <div class="form-group">
        <label class="form-label">Billing Cycle</label>
        <select name="billing_cycle" class="form-input">
          <option value="monthly">Monthly</option>
          <option value="quarterly">Quarterly</option>
          <option value="annually" selected>Annually</option>
          <option value="one-time">One-time</option>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Status</label>
      <select name="status" class="form-input">
        <option value="trial">Trial</option>
        <option value="active" selected>Active</option>
        <option value="suspended">Suspended</option>
        <option value="expired">Expired</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>
    <div class="form-grid-2">
      <div class="form-group">
        <label class="form-label">Start Date</label>
        <input type="date" name="starts_at" class="form-input" value="<?=date('Y-m-d')?>">
      </div>
      <div class="form-group">
        <label class="form-label">Expiry Date</label>
        <input type="date" name="expires_at" class="form-input">
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Next Renewal</label>
      <input type="date" name="next_renewal" class="form-input">
    </div>
    <div class="form-group">
      <label class="form-label">Notes (internal)</label>
      <textarea name="notes" class="form-input form-textarea" rows="2" placeholder="Any internal notes…"></textarea>
    </div>
  </div>
</div>
