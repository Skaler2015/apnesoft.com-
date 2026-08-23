<?php use App\Core\Csrf; ?>
<div class="admin-edit-head">
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software')) ?>">← Back</a>
    <h2 style="margin:0 0 0 4px">➕ Publish new software</h2>
</div>
<p class="muted" style="margin:-4px 0 10px">Fill the details and choose <strong>Published</strong> to make it live immediately. Only add official / authorized download links.</p>
<?php if (!empty($panelLabel)): ?>
    <div class="flash flash-ok" style="margin:0 0 14px">📌 This will publish to your <strong><?= e($panelLabel) ?></strong> site only. (Tick more operating systems below only if the app is truly cross-platform.)</div>
<?php endif; ?>
<form method="post" action="<?= e(base_url('/admin/software/new')) ?>" class="admin-form" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <div class="form-grid">
        <div class="col-2">
            <label style="margin-bottom:6px">Name *</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <input name="name" id="f-name" value="<?= old('name') ?>" required autofocus style="flex:1;min-width:200px">
                <button type="button" id="ai-autofill" class="btn btn-primary">🔎 Auto-fill</button>
                <button type="submit" formaction="<?= e(base_url('/admin/software/quick')) ?>" class="btn btn-ghost"
                        title="Auto-fill from the name and publish in one click"
                        onclick="return (document.getElementById('f-name').value.trim().length>1) || (alert('Type a software name first.'),false);">✨ AI publish</button>
            </div>
            <p id="autofill-status" class="muted small" style="margin-top:6px">
                Type the name → <strong>Auto-fill</strong> loads the details, or <strong>AI publish</strong> fills &amp; publishes in one click.
                Data comes from free official catalogues (winget, Chocolatey, GitHub). No API key needed.
            </p>
        </div>

        <!-- Live preview -->
        <div class="col-2">
            <label style="margin-bottom:6px">Live preview (how it looks on the site)</label>
            <div class="pv-card">
                <div class="pv-ic" id="pv-ic">A</div>
                <div style="min-width:0">
                    <div><strong id="pv-name">Software name</strong> <span class="license-badge lb-free" id="pv-badge" style="display:none"></span></div>
                    <p class="muted small" id="pv-desc" style="margin:3px 0 8px">Short description will appear here…</p>
                    <span class="btn btn-sm btn-primary" id="pv-dl" style="pointer-events:none">⬇ Download</span>
                </div>
            </div>
        </div>

        <label>Developer<input name="developer_name" id="f-dev" value="<?= old('developer_name') ?>"></label>
        <label>Version<input name="version" value="<?= old('version') ?>" placeholder="e.g. 1.2.3"></label>
        <label>Official website<input name="official_website" value="<?= old('official_website') ?>" placeholder="https://…"></label>
        <label>Developer website<input name="developer_website" value="<?= old('developer_website') ?>" placeholder="https://…"></label>
        <label class="col-2">Official download URL<input name="official_download_url" value="<?= old('official_download_url') ?>" placeholder="https://… (official / authorized source only)"></label>
        <label>License<input name="license_type" value="<?= old('license_type') ?>" placeholder="MIT, GPL, Freeware…"></label>
        <label>Price type
            <select name="price_type" id="f-price">
                <?php foreach (['','free','open_source','freemium','paid','trial'] as $p): ?>
                    <option value="<?= $p ?>"><?= $p ?: '—' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Category
            <select name="category_id">
                <option value="">—</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Release date<input name="release_date" type="date" value="<?= old('release_date') ?>"></label>

        <div class="col-2">
            <label style="margin-bottom:6px">Operating systems</label>
            <div style="display:flex;gap:16px;flex-wrap:wrap">
                <?php $preOs = $preOs ?? 0; foreach ($oss as $os): ?>
                    <label class="check" style="font-weight:400">
                        <input type="checkbox" name="os[]" value="<?= (int) $os['id'] ?>" <?= (int) $os['id'] === (int) $preOs ? 'checked' : '' ?>> <?= e($os['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <label>Status
            <select name="status">
                <option value="published">Published (live now)</option>
                <option value="review">Review (pending)</option>
                <option value="draft">Draft</option>
            </select>
        </label>
        <label style="flex-direction:row;align-items:center;gap:8px;font-weight:400">
            <input type="checkbox" name="auto_update" value="1" style="width:auto"> 🔁 Auto-update version when a newer one is found
        </label>

        <!-- Logo: URL or upload -->
        <label>Logo URL<input name="logo" id="f-logo" value="<?= old('logo') ?>" placeholder="https://… (optional)"></label>
        <label>Logo — upload image<input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif"></label>

        <!-- Demo video -->
        <label class="col-2">🎥 Demo / YouTube video URL<input name="video_url" value="<?= old('video_url') ?>" placeholder="https://youtube.com/watch?v=… (optional)"></label>

        <!-- Screenshots -->
        <div class="col-2">
            <label style="margin-bottom:6px">📸 Screenshots — upload images (you can pick several)</label>
            <input type="file" name="screenshots[]" accept="image/png,image/jpeg,image/webp" multiple>
            <div class="muted small" style="margin-top:4px">These show as a gallery on the software page.</div>
        </div>

        <label class="col-2">Short description<input name="short_description" id="f-short" value="<?= old('short_description') ?>" maxlength="320" placeholder="One-line summary"></label>
        <label class="col-2">Long description<textarea name="long_description" rows="6"><?= old('long_description') ?></textarea></label>

        <!-- Features / Pros / Cons -->
        <label class="col-2">⭐ Features <span class="muted small">(one per line)</span><textarea name="features" rows="4" placeholder="Fast downloads&#10;Auto captions&#10;Works offline"><?= old('features') ?></textarea></label>
        <label>✓ Pros <span class="muted small">(one per line)</span><textarea name="pros" rows="4" placeholder="Easy to use&#10;Free"><?= old('pros') ?></textarea></label>
        <label>✕ Cons <span class="muted small">(one per line)</span><textarea name="cons" rows="4" placeholder="Shows ads"><?= old('cons') ?></textarea></label>

        <!-- Tags -->
        <label class="col-2">🏷️ Tags <span class="muted small">(comma separated)</span><input name="tags" value="<?= old('tags') ?>" placeholder="video editor, free, offline"></label>

        <label class="col-2">Minimum requirements<textarea name="minimum_requirements" rows="3"><?= old('minimum_requirements') ?></textarea></label>
    </div>
    <div class="admin-form-actions">
        <button class="btn btn-primary" type="submit">Add software</button>
        <span class="muted small">Trust score is calculated automatically from the source, HTTPS and official URLs. Only add official / authorized sources.</span>
    </div>
</form>

<style>
.pv-card{display:flex;gap:12px;align-items:flex-start;background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:14px;max-width:520px}
.pv-ic{width:46px;height:46px;border-radius:11px;background:linear-gradient(135deg,var(--brand),var(--brand-2));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1.2rem;flex:0 0 auto;overflow:hidden}
.pv-ic img{width:46px;height:46px;object-fit:contain}
</style>

<script>
(function () {
    // ---- Auto-fill ----
    var btn = document.getElementById('ai-autofill');
    var nameInput = document.querySelector('input[name="name"]');
    var statusEl = document.getElementById('autofill-status');
    function set(name, val) {
        if (val === undefined || val === null || val === '') return;
        var el = document.querySelector('[name="' + name + '"]');
        if (el) el.value = val;
    }
    if (btn) btn.addEventListener('click', function () {
        var name = (nameInput.value || '').trim();
        if (name.length < 2) { statusEl.textContent = 'Type a software name first.'; nameInput.focus(); return; }
        var original = btn.textContent;
        btn.disabled = true; btn.textContent = '⏳ Fetching…';
        statusEl.textContent = 'Searching official catalogues…';
        fetch(<?= json_encode(base_url('/admin/software/lookup')) ?> + '?name=' + encodeURIComponent(name))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                btn.disabled = false; btn.textContent = original;
                if (!res.ok) { statusEl.textContent = res.message || 'Nothing found — fill the form manually.'; return; }
                var d = res.data || {};
                ['name','developer_name','developer_website','official_website','official_download_url',
                 'version','license_type','logo','short_description','long_description'].forEach(function (k) { set(k, d[k]); });
                ['price_type','category_id'].forEach(function (k) {
                    if (d[k] !== undefined && d[k] !== null && d[k] !== '') { var el = document.querySelector('[name="' + k + '"]'); if (el) el.value = String(d[k]); }
                });
                if (d.operating_system) {
                    var os = String(d.operating_system).toLowerCase();
                    document.querySelectorAll('input[name="os[]"]').forEach(function (cb) {
                        var lbl = (cb.parentNode.textContent || '').trim().toLowerCase();
                        if (lbl && os.indexOf(lbl) !== -1) cb.checked = true;
                    });
                }
                statusEl.innerHTML = '✓ Filled from <strong>' + (d.source || 'catalogue') + '</strong>' + (d.match ? ' (' + d.match + '% match)' : '') + '. Review, then Add software.';
                updatePreview();
            })
            .catch(function () { btn.disabled = false; btn.textContent = original; statusEl.textContent = 'Lookup failed — fill the form manually.'; });
    });

    // ---- Live preview ----
    var LB = {free:['FREE','lb-free'], open_source:['OPEN SOURCE','lb-oss'], freemium:['FREEMIUM','lb-freemium'], trial:['TRIAL','lb-trial'], paid:['PAID','lb-paid']};
    function updatePreview() {
        var name = (document.getElementById('f-name').value || '').trim();
        var short = (document.getElementById('f-short').value || '').trim();
        var dev = (document.getElementById('f-dev').value || '').trim();
        var logo = (document.getElementById('f-logo').value || '').trim();
        var price = document.getElementById('f-price').value;
        document.getElementById('pv-name').textContent = name || 'Software name';
        document.getElementById('pv-desc').textContent = short || (dev ? 'By ' + dev : 'Short description will appear here…');
        var ic = document.getElementById('pv-ic');
        ic.innerHTML = logo ? '<img src="' + logo + '" alt="">' : (name ? name.charAt(0).toUpperCase() : 'A');
        var badge = document.getElementById('pv-badge');
        if (LB[price]) { badge.textContent = LB[price][0]; badge.className = 'license-badge ' + LB[price][1]; badge.style.display = 'inline-block'; }
        else { badge.style.display = 'none'; }
        // Download label from ticked OS
        var osLabel = 'Download';
        document.querySelectorAll('input[name="os[]"]:checked').forEach(function (cb) {
            var t = (cb.parentNode.textContent || '').trim();
            if (t) osLabel = '⬇ Download for ' + t;
        });
        document.getElementById('pv-dl').textContent = osLabel;
    }
    ['f-name','f-short','f-dev','f-logo','f-price'].forEach(function (id) {
        var el = document.getElementById(id); if (el) el.addEventListener('input', updatePreview);
    });
    document.querySelectorAll('input[name="os[]"]').forEach(function (cb) { cb.addEventListener('change', updatePreview); });
    updatePreview();
})();
</script>
