<?php use App\Core\Csrf; $src = $source; $isNew = $src === null;
$action = $isNew ? '/admin/sources/new' : '/admin/sources/' . $src['id'] . '/edit'; ?>
<a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/sources')) ?>">← Back</a>
<form method="post" action="<?= e(base_url($action)) ?>" class="admin-form narrow">
    <?= Csrf::field() ?>
    <div class="form-grid">
        <label class="col-2">Source name<input name="name" value="<?= e($src['name'] ?? '') ?>" required></label>
        <label>Type
            <select name="source_type" required>
                <?php foreach (['github_api'=>'GitHub API','github_webhook'=>'GitHub Webhook','rss'=>'RSS','atom'=>'Atom','website'=>'Official Website','winget'=>'Winget','public_api'=>'Public API','manual'=>'Manual'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= ($src['source_type'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Status
            <select name="status"><option value="active" <?= ($src['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option><option value="paused" <?= ($src['status'] ?? '') === 'paused' ? 'selected' : '' ?>>Paused</option><option value="disabled" <?= ($src['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option></select>
        </label>
        <label class="col-2">Source URL (feed / homepage)<input name="source_url" value="<?= e($src['source_url'] ?? '') ?>"></label>
        <label class="col-2">API URL (optional)<input name="api_url" value="<?= e($src['api_url'] ?? '') ?>"></label>
        <label class="col-2">API key (stored encrypted; leave blank to keep)<input name="api_key" type="password" autocomplete="off"></label>
        <label>Priority<input name="priority" type="number" value="<?= e($src['priority'] ?? 5) ?>"></label>
        <label>Trust score<input name="trust_score" type="number" min="0" max="100" value="<?= e($src['trust_score'] ?? 50) ?>"></label>
        <label>Crawl frequency (min)<input name="crawl_frequency" type="number" value="<?= e($src['crawl_frequency'] ?? 1440) ?>"></label>
        <label class="col-2">Config (JSON)
            <textarea name="config" rows="5" placeholder='{"repos":["videolan/vlc"],"min_stars":200}'><?= e($src['config'] ?? '') ?></textarea>
        </label>
    </div>
    <p class="muted small">GitHub: <code>{"repos":["owner/repo"],"min_stars":100}</code> · Winget: <code>{"packages":["Mozilla.Firefox"]}</code> · RSS/Website: <code>{"category_id":3,"developer":"Name"}</code></p>
    <button class="btn btn-primary" type="submit">Save source</button>
</form>
