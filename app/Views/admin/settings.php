<?php use App\Core\Csrf; $st = $settings; ?>
<form method="post" action="<?= e(base_url('/admin/settings')) ?>" class="admin-form">
    <?= Csrf::field() ?>
    <div class="admin-panel">
        <h2>Branding</h2>
        <div class="form-grid">
            <label>Website name<input name="site_name" value="<?= e($st['site_name'] ?? '') ?>"></label>
            <label>Tagline<input name="tagline" value="<?= e($st['tagline'] ?? '') ?>"></label>
            <label>Logo URL<input name="logo" value="<?= e($st['logo'] ?? '') ?>"></label>
            <label>Favicon URL<input name="favicon" value="<?= e($st['favicon'] ?? '') ?>"></label>
            <label>Primary color<input name="primary_color" type="color" value="<?= e($st['primary_color'] ?? '#4f46e5') ?>"></label>
            <label>Secondary color<input name="secondary_color" type="color" value="<?= e($st['secondary_color'] ?? '#0ea5e9') ?>"></label>
            <label class="col-2">Footer text<input name="footer_text" value="<?= e($st['footer_text'] ?? '') ?>"></label>
            <label>Contact email<input name="contact_email" value="<?= e($st['contact_email'] ?? '') ?>"></label>
            <label>Twitter URL<input name="social_twitter" value="<?= e($st['social_twitter'] ?? '') ?>"></label>
            <label>GitHub URL<input name="social_github" value="<?= e($st['social_github'] ?? '') ?>"></label>
        </div>
    </div>
    <div class="admin-panel">
        <h2>Auto-publish thresholds</h2>
        <div class="form-grid">
            <label>Auto publish ≥<input name="threshold_auto_publish" type="number" min="0" max="100" value="<?= e($st['threshold_auto_publish'] ?? 90) ?>"></label>
            <label>Conditional ≥<input name="threshold_conditional" type="number" min="0" max="100" value="<?= e($st['threshold_conditional'] ?? 70) ?>"></label>
            <label>Review ≥<input name="threshold_review" type="number" min="0" max="100" value="<?= e($st['threshold_review'] ?? 40) ?>"></label>
        </div>
        <p class="muted small">Below the review threshold, discovered software is rejected automatically.</p>
    </div>
    <div class="admin-panel">
        <h2>Ad slots (HTML)</h2>
        <div class="form-grid">
            <label class="col-2">Header<textarea name="ad_header" rows="2"><?= e($st['ad_header'] ?? '') ?></textarea></label>
            <label class="col-2">In-content<textarea name="ad_incontent" rows="2"><?= e($st['ad_incontent'] ?? '') ?></textarea></label>
            <label class="col-2">Sidebar<textarea name="ad_sidebar" rows="2"><?= e($st['ad_sidebar'] ?? '') ?></textarea></label>
            <label class="col-2">Software page<textarea name="ad_software_page" rows="2"><?= e($st['ad_software_page'] ?? '') ?></textarea></label>
            <label class="col-2">Footer<textarea name="ad_footer" rows="2"><?= e($st['ad_footer'] ?? '') ?></textarea></label>
        </div>
    </div>
    <button class="btn btn-primary" type="submit">Save settings</button>
</form>
