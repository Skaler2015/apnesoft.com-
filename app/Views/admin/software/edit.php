<?php use App\Core\Csrf; $s = $software; ?>
<div class="admin-edit-head">
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software')) ?>">← Back</a>
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/software/' . $s['slug'])) ?>" target="_blank">View on site ↗</a>
    <form method="post" action="<?= e(base_url('/admin/software/' . $s['id'] . '/enhance')) ?>" class="inline"
          onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='✨ Enhancing…';">
        <?= Csrf::field() ?>
        <button class="btn btn-sm btn-primary" title="Rewrite description &amp; features from real data using AI">✨ Enhance with AI</button>
    </form>
    <span style="flex:1"></span>
    <?php if ($s['status'] === 'published'): ?>
        <form method="post" action="<?= e(base_url('/admin/software/' . $s['id'] . '/action')) ?>" class="inline">
            <?= Csrf::field() ?><input type="hidden" name="action" value="disable">
            <button class="btn btn-sm btn-ghost">Disable</button>
        </form>
    <?php endif; ?>
    <form method="post" action="<?= e(base_url('/admin/software/' . $s['id'] . '/action')) ?>" class="inline" onsubmit="return confirm('Delete this software permanently? This cannot be undone.')">
        <?= Csrf::field() ?><input type="hidden" name="action" value="delete">
        <button class="btn btn-sm btn-ghost" style="color:var(--red)">Delete</button>
    </form>
</div>
<form method="post" action="<?= e(base_url('/admin/software/' . $s['id'] . '/edit')) ?>" class="admin-form">
    <?= Csrf::field() ?>
    <div class="form-grid">
        <label class="col-2">Name<input name="name" value="<?= e($s['name']) ?>" required></label>
        <label>Developer<input name="developer_name" value="<?= e($s['developer_name']) ?>"></label>
        <label>Version<input name="version" value="<?= e($s['version']) ?>"></label>
        <label>Official website<input name="official_website" value="<?= e($s['official_website']) ?>"></label>
        <label>Developer website<input name="developer_website" value="<?= e($s['developer_website']) ?>"></label>
        <label class="col-2">Official download URL<input name="official_download_url" value="<?= e($s['official_download_url']) ?>"></label>
        <label>License<input name="license_type" value="<?= e($s['license_type']) ?>"></label>
        <label>Price type
            <select name="price_type">
                <?php foreach (['','free','open_source','freemium','paid','trial'] as $p): ?>
                    <option value="<?= $p ?>" <?= $s['price_type'] === $p ? 'selected' : '' ?>><?= $p ?: '—' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Category
            <select name="category_id">
                <option value="">—</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $s['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Operating System<input name="operating_system" value="<?= e($s['operating_system']) ?>"></label>
        <label>Architecture<input name="architecture" value="<?= e($s['architecture']) ?>"></label>
        <label>File size<input name="file_size" value="<?= e($s['file_size']) ?>"></label>
        <label>Min RAM (MB)<input name="min_ram_mb" type="number" value="<?= e($s['min_ram_mb']) ?>"></label>
        <label>Open source
            <select name="is_open_source"><option value="0" <?= !$s['is_open_source'] ? 'selected' : '' ?>>No</option><option value="1" <?= $s['is_open_source'] ? 'selected' : '' ?>>Yes</option></select>
        </label>
        <label>Status
            <select name="status">
                <?php foreach (['published','review','draft','rejected','disabled'] as $st): ?>
                    <option value="<?= $st ?>" <?= $s['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="col-2">Short description<input name="short_description" value="<?= e($s['short_description']) ?>" maxlength="320"></label>
        <label class="col-2">Long description<textarea name="long_description" rows="6"><?= e($s['long_description']) ?></textarea></label>
        <label class="col-2">Minimum requirements<textarea name="minimum_requirements" rows="3"><?= e($s['minimum_requirements']) ?></textarea></label>
    </div>
    <div class="admin-form-actions">
        <button class="btn btn-primary" type="submit">Save changes</button>
        <span class="muted small">Trust <?= (int) $s['trust_score'] ?> · Quality <?= (int) $s['quality_score'] ?> · Verification <?= e($s['verification_status']) ?></span>
    </div>
</form>
