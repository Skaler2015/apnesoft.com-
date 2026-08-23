<?php use App\Core\Csrf; $st = $settings; ?>
<form method="post" action="<?= e(base_url('/admin/settings')) ?>" class="admin-form" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <div class="admin-panel">
        <h2>Branding</h2>
        <div class="form-grid">
            <label>Website name<input name="site_name" value="<?= e($st['site_name'] ?? '') ?>"></label>
            <label>Tagline<input name="tagline" value="<?= e($st['tagline'] ?? '') ?>"></label>

            <div class="col-2" style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-end;border-top:1px solid var(--border);padding-top:12px">
                <div>
                    <label style="margin-bottom:6px">Logo — upload image</label>
                    <?php if (!empty($st['logo'])): ?>
                        <div style="margin-bottom:6px"><img src="<?= e($st['logo']) ?>" alt="current logo" style="max-height:36px;max-width:180px;background:#fff;border-radius:6px;padding:4px"></div>
                    <?php endif; ?>
                    <input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif,image/x-icon">
                    <div class="muted small">PNG/JPG/WEBP/SVG, under 2 MB. Or paste a URL below.</div>
                </div>
                <div>
                    <label style="margin-bottom:6px">Favicon — upload image</label>
                    <?php if (!empty($st['favicon'])): ?>
                        <div style="margin-bottom:6px"><img src="<?= e($st['favicon']) ?>" alt="current favicon" style="max-height:32px;background:#fff;border-radius:6px;padding:4px"></div>
                    <?php endif; ?>
                    <input type="file" name="favicon_file" accept="image/png,image/x-icon,image/svg+xml">
                    <div class="muted small">Square icon (32×32 or 512×512), under 2 MB.</div>
                </div>
            </div>

            <label>Logo URL (optional)<input name="logo" value="<?= e($st['logo'] ?? '') ?>" placeholder="https://…"></label>
            <label>Favicon URL (optional)<input name="favicon" value="<?= e($st['favicon'] ?? '') ?>" placeholder="https://…"></label>
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
        <h2>✨ AI enhancement</h2>
        <p class="muted small">Uses the Anthropic API to rewrite software descriptions and features from
            their real metadata. Your key is stored encrypted and never exposed to the frontend.
            Get a key at <code>console.anthropic.com</code>.</p>
        <?php $hasKey = ($st['ai_api_key_enc'] ?? '') !== ''; ?>
        <div class="form-grid">
            <label class="col-2">Anthropic API key
                <input name="ai_api_key" type="password" autocomplete="off"
                       placeholder="<?= $hasKey ? '•••••••••• (saved — leave blank to keep)' : 'sk-ant-…' ?>">
            </label>
            <?php if ($hasKey): ?>
                <label class="col-2" style="flex-direction:row;align-items:center;gap:8px;font-weight:400">
                    <input type="checkbox" name="ai_api_key_clear" value="1" style="width:auto"> Remove the saved API key
                </label>
            <?php endif; ?>
            <label>Model
                <select name="ai_model">
                    <?php foreach (\App\Services\AiEnhancer::MODELS as $id => $label): ?>
                        <option value="<?= e($id) ?>" <?= ($st['ai_model'] ?? \App\Services\AiEnhancer::DEFAULT_MODEL) === $id ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="flex-direction:row;align-items:center;gap:8px;font-weight:400">
                <input type="checkbox" name="ai_enabled" value="1" <?= ($st['ai_enabled'] ?? '0') === '1' ? 'checked' : '' ?> style="width:auto">
                Auto-enhance new software hourly (via cron)
            </label>
        </div>
        <p class="muted small">AI only rephrases real facts — it never invents versions, developers, features
            or security claims. Manage runs from <a href="<?= e(base_url('/admin/ai')) ?>">AI Enhancer</a>.</p>
    </div>
    <div class="admin-panel">
        <h2>🚀 Publishing automation</h2>
        <div class="form-grid">
            <label style="flex-direction:row;align-items:center;gap:8px;font-weight:400">
                <input type="checkbox" name="auto_screenshot" value="1" <?= ($st['auto_screenshot'] ?? '0') === '1' ? 'checked' : '' ?> style="width:auto">
                🖼️ Capture a real website screenshot for each published software
            </label>
            <label>🔁 Daily auto-publish — new popular apps per day
                <input name="daily_publish" type="number" min="0" max="100" value="<?= e($st['daily_publish'] ?? '0') ?>" placeholder="0 = off">
            </label>
        </div>
        <p class="muted small">Screenshots use the free WordPress mShots service. Daily auto-publish queues that many still-missing
            popular apps every day (needs the hourly cron). Set to 0 to turn off.</p>
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
