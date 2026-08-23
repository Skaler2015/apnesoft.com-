<?php use App\Core\Csrf; ?>
<div class="admin-edit-head">
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software')) ?>">← Back</a>
    <h2 style="margin:0 0 0 4px">➕ Publish new software</h2>
</div>
<p class="muted" style="margin:-4px 0 14px">Fill the details and choose <strong>Published</strong> to make it live immediately. Only add official / authorized download links.</p>
<form method="post" action="<?= e(base_url('/admin/software/new')) ?>" class="admin-form">
    <?= Csrf::field() ?>
    <div class="form-grid">
        <div class="col-2">
            <label style="margin-bottom:6px">Name *</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <input name="name" value="<?= old('name') ?>" required autofocus style="flex:1;min-width:220px">
                <button type="button" id="ai-autofill" class="btn btn-primary">🔎 Auto-fill details</button>
            </div>
            <p id="autofill-status" class="muted small" style="margin-top:6px">
                Type the name and click <strong>Auto-fill</strong> — we fetch the developer, official website,
                download link, version, licence &amp; description from free official catalogues (winget, Chocolatey, GitHub). No API key needed.
            </p>
        </div>
        <label>Developer<input name="developer_name" value="<?= old('developer_name') ?>"></label>
        <label>Version<input name="version" value="<?= old('version') ?>" placeholder="e.g. 1.2.3"></label>
        <label>Official website<input name="official_website" value="<?= old('official_website') ?>" placeholder="https://…"></label>
        <label>Developer website<input name="developer_website" value="<?= old('developer_website') ?>" placeholder="https://…"></label>
        <label class="col-2">Official download URL<input name="official_download_url" value="<?= old('official_download_url') ?>" placeholder="https://… (official / authorized source only)"></label>
        <label>License<input name="license_type" value="<?= old('license_type') ?>" placeholder="MIT, GPL, Freeware…"></label>
        <label>Price type
            <select name="price_type">
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
        <label>Open source
            <select name="is_open_source"><option value="0">No</option><option value="1">Yes</option></select>
        </label>
        <label>Architecture<input name="architecture" value="<?= old('architecture') ?>" placeholder="x64, arm64…"></label>
        <label>File size<input name="file_size" value="<?= old('file_size') ?>" placeholder="~40 MB"></label>
        <label>Min RAM (MB)<input name="min_ram_mb" type="number" value="<?= old('min_ram_mb') ?>" placeholder="e.g. 2048"></label>
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
        <label>Logo URL<input name="logo" value="<?= old('logo') ?>" placeholder="https://… (optional)"></label>

        <label class="col-2">Short description<input name="short_description" value="<?= old('short_description') ?>" maxlength="320" placeholder="One-line summary"></label>
        <label class="col-2">Long description<textarea name="long_description" rows="6"><?= old('long_description') ?></textarea></label>
        <label class="col-2">Minimum requirements<textarea name="minimum_requirements" rows="3"><?= old('minimum_requirements') ?></textarea></label>
    </div>
    <div class="admin-form-actions">
        <button class="btn btn-primary" type="submit">Add software</button>
        <span class="muted small">Trust score is calculated automatically from the source, HTTPS and official URLs. Only add official / authorized sources.</span>
    </div>
</form>

<script>
(function () {
    var btn = document.getElementById('ai-autofill');
    if (!btn) return;
    var nameInput = document.querySelector('input[name="name"]');
    var statusEl = document.getElementById('autofill-status');

    function set(name, val) {
        if (val === undefined || val === null || val === '') return;
        var el = document.querySelector('[name="' + name + '"]');
        if (el) el.value = val;
    }

    btn.addEventListener('click', function () {
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
                ['name', 'developer_name', 'developer_website', 'official_website', 'official_download_url',
                 'version', 'license_type', 'architecture', 'file_size', 'logo',
                 'short_description', 'long_description'].forEach(function (k) { set(k, d[k]); });
                ['price_type', 'is_open_source', 'category_id'].forEach(function (k) {
                    if (d[k] !== undefined && d[k] !== null && d[k] !== '') {
                        var el = document.querySelector('[name="' + k + '"]');
                        if (el) el.value = String(d[k]);
                    }
                });
                if (d.operating_system) {
                    var os = String(d.operating_system).toLowerCase();
                    document.querySelectorAll('input[name="os[]"]').forEach(function (cb) {
                        var lbl = (cb.parentNode.textContent || '').trim().toLowerCase();
                        if (lbl && os.indexOf(lbl) !== -1) cb.checked = true;
                    });
                }
                statusEl.innerHTML = '✓ Filled from <strong>' + (d.source || 'catalogue') + '</strong>' +
                    (d.match ? ' (' + d.match + '% name match)' : '') + '. Review the details, then click <strong>Add software</strong>.';
            })
            .catch(function () {
                btn.disabled = false; btn.textContent = original;
                statusEl.textContent = 'Lookup failed — please fill the form manually.';
            });
    });
})();
</script>
