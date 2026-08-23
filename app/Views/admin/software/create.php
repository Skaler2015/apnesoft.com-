<?php use App\Core\Csrf; ?>
<div class="admin-edit-head">
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software')) ?>">← Back</a>
    <h2 style="margin:0 0 0 4px">➕ Publish new software</h2>
</div>
<p class="muted" style="margin:-4px 0 14px">Fill the details and choose <strong>Published</strong> to make it live immediately. Only add official / authorized download links.</p>
<form method="post" action="<?= e(base_url('/admin/software/new')) ?>" class="admin-form">
    <?= Csrf::field() ?>
    <div class="form-grid">
        <label class="col-2">Name *<input name="name" value="<?= old('name') ?>" required autofocus></label>
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
                <?php foreach ($oss as $os): ?>
                    <label class="check" style="font-weight:400">
                        <input type="checkbox" name="os[]" value="<?= (int) $os['id'] ?>"> <?= e($os['name']) ?>
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
