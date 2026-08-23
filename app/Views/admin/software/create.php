<?php
use App\Core\Csrf;
$isEdit = ($mode ?? 'create') === 'edit';
$s = $s ?? [];
$raw = $isEdit ? $s : ($_POST ?? []);
$fv = static function (string $k, string $d = '') use ($raw) {
    $v = $raw[$k] ?? $d;
    return is_scalar($v) ? (string) $v : $d;
};
$action = $action ?? base_url('/admin/software/new');
?>
<?php if ($isEdit && ($_GET['published'] ?? '') === '1'): ?>
<div id="pubModal" style="position:fixed;inset:0;background:rgba(10,12,20,.6);display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px" onclick="if(event.target===this)this.remove()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;max-width:720px;width:100%;max-height:90vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,.4)">
        <div style="display:flex;align-items:center;gap:10px;padding:16px 20px;border-bottom:1px solid var(--border)">
            <strong style="font-size:1.05rem">🎉 Published — here's how it looks</strong><span style="flex:1"></span>
            <button onclick="document.getElementById('pubModal').remove()" class="btn btn-sm btn-ghost">✕ Close</button>
        </div>
        <iframe src="<?= e(base_url('/software/' . ($s['slug'] ?? ''))) ?>" title="Preview" loading="lazy" style="width:100%;height:60vh;border:0;background:#fff"></iframe>
        <?php $liveUrl = base_url('/software/' . ($s['slug'] ?? '')); ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;padding:16px 20px;border-top:1px solid var(--border)">
            <a class="btn btn-primary" href="<?= e($liveUrl) ?>" target="_blank">Open live page ↗</a>
            <button type="button" class="btn btn-ghost" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?= e($liveUrl) ?>');this.textContent='✓ Copied';">🔗 Link copy</button>
            <a class="btn btn-ghost" href="https://wa.me/?text=<?= rawurlencode(($s['name'] ?? '') . ' — ' . $liveUrl) ?>" target="_blank" rel="noopener">🟢 WhatsApp</a>
            <a class="btn btn-ghost" href="<?= e(base_url('/admin/software/new')) ?>">➕ Add another</a>
        </div>
    </div>
</div>
<?php endif; ?>
<div class="admin-edit-head">
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software')) ?>">← Back</a>
    <h2 style="margin:0 0 0 4px"><?= $isEdit ? '✏️ Edit software' : '➕ Publish new software' ?></h2>
    <?php if ($isEdit): ?>
        <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/software/' . ($s['slug'] ?? ''))) ?>" target="_blank">View ↗</a>
        <form method="post" action="<?= e(base_url('/admin/software/' . ($s['id'] ?? 0) . '/enhance')) ?>" class="inline" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='✨ Enhancing…';">
            <?= Csrf::field() ?><button class="btn btn-sm btn-primary" title="Rewrite description &amp; features from real data using AI">✨ Enhance with AI</button>
        </form>
        <span style="flex:1"></span>
        <form method="post" action="<?= e(base_url('/admin/software/' . ($s['id'] ?? 0) . '/action')) ?>" class="inline" onsubmit="return confirm('Delete this software permanently? This cannot be undone.')">
            <?= Csrf::field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-ghost" style="color:var(--red)">Delete</button>
        </form>
    <?php endif; ?>
</div>
<?php if (!$isEdit): ?>
<p class="muted" style="margin:-4px 0 10px">Fill the details and choose <strong>Published</strong> to make it live immediately. Only add official / authorized download links.</p>
<?php endif; ?>
<?php if (!empty($panelLabel)): ?>
    <div class="flash flash-ok" style="margin:0 0 14px">📌 This will publish to your <strong><?= e($panelLabel) ?></strong> site<?= $isEdit ? '' : ' only' ?>. (Tick more operating systems below only if the app is truly cross-platform.)</div>
<?php endif; ?>
<form method="post" action="<?= e($action) ?>" class="admin-form" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <div class="form-cards">

    <div class="form-tabs" id="form-tabs">
        <button type="button" class="ftab is-active" data-tab="basics">📝 Basics</button>
        <button type="button" class="ftab" data-tab="media">🖼️ Media</button>
        <button type="button" class="ftab" data-tab="content">✍️ Content</button>
        <button type="button" class="ftab" data-tab="review">✓ Review</button>
    </div>

    <section class="form-card" data-tab="basics">
        <div class="fc-h"><span class="fc-ic">📝</span><div><h3>मुख्य जानकारी</h3><p>नाम भरें — बाकी details Auto-fill / Import से अपने-आप</p></div></div>
        <div class="form-grid">
        <div class="col-2">
            <label style="margin-bottom:6px">Name *</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <input name="name" id="f-name" value="<?= e($fv('name')) ?>" required autofocus autocomplete="off" list="name-suggest" style="flex:1;min-width:200px">
                <datalist id="name-suggest"></datalist>
                <button type="button" id="ai-autofill" class="btn btn-ghost">🔎 Auto-fill</button>
                <button type="button" id="ai-fill" class="btn btn-primary" title="Fill everything — links, long description, features, pros/cons, tags — using AI">✨ AI fill (full)</button>
            </div>
            <p id="autofill-status" class="muted small" style="margin-top:6px">
                <strong>🔎 Auto-fill</strong> = details <em>plus</em> features, pros/cons, tags &amp; description built from real data — <strong>no key needed</strong>.
                <strong>✨ AI fill</strong> = the same, but with a longer, better-written description (needs your Anthropic API key in
                <a href="<?= e(base_url('/admin/settings')) ?>">Settings</a>).
            </p>
        </div>

        <!-- Import from the official website (most reliable for commercial software) -->
        <div class="col-2">
            <label style="margin-bottom:6px">Import from official URL <span class="muted small">(best for commercial / niche software)</span></label>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <input id="f-url" placeholder="https://www.example.com/  (paste the official website)" style="flex:1;min-width:200px" autocomplete="off">
                <button type="button" id="url-import" class="btn btn-primary">⬇ Import</button>
            </div>
            <p id="url-status" class="muted small" style="margin-top:6px">
                Free catalogues don't have every commercial app. Paste the <strong>official website</strong> here and we'll read the real
                name, logo, description &amp; download link straight from that page — no GitHub guesses.
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

        <label>Developer<input name="developer_name" id="f-dev" value="<?= e($fv('developer_name')) ?>"></label>
        <label>Version<input name="version" value="<?= e($fv('version')) ?>" placeholder="e.g. 1.2.3"></label>
        <label>File size<input name="file_size" id="f-size" value="<?= e($fv('file_size')) ?>" placeholder="e.g. 45 MB"></label>
        </div>
    </section>

    <section class="form-card" data-tab="basics">
        <div class="fc-h"><span class="fc-ic">🔗</span><div><h3>Links, दाम &amp; category</h3><p>official website/download, price, category, OS versions</p></div></div>
        <div class="form-grid">
        <?php
            $oldOsv = $checkedOsv ?? (isset($_POST['os_versions']) && is_array($_POST['os_versions']) ? $_POST['os_versions'] : []);
            $osGroupOrder = ['Windows', 'macOS', 'iOS / iPadOS', 'Android', 'Linux', 'Other'];
            $osGroups = array_fill_keys($osGroupOrder, []);
            $osGroupOf = static function (string $v): string {
                $l = mb_strtolower($v);
                if (str_contains($l, 'windows')) return 'Windows';
                if (str_contains($l, 'mac')) return 'macOS';
                if (str_contains($l, 'ipad') || str_contains($l, 'ios')) return 'iOS / iPadOS';
                if (str_contains($l, 'android')) return 'Android';
                if (str_contains($l, 'linux') || str_contains($l, 'ubuntu') || str_contains($l, 'debian')) return 'Linux';
                return 'Other';
            };
            foreach (($osVersions ?? []) as $v) { $osGroups[$osGroupOf($v)][] = $v; }
            $panelGroup = $panelGroup ?? '';
            // If a platform panel is open, show only that group.
            $visibleGroups = $panelGroup !== '' ? [$panelGroup] : $osGroupOrder;
        ?>
        <div class="col-2">
            <label style="margin-bottom:6px">Operating system versions
                <span class="muted small"><?= $panelGroup !== '' ? '(' . e($panelGroup) . ' — tick one or more)' : '(open the list and tick one or more)' ?></span>
            </label>
            <div class="osv-dd" id="osv-dd" data-panel-group="<?= e($panelGroup) ?>">
                <button type="button" class="osv-toggle" id="osv-toggle" aria-expanded="false">
                    <span id="osv-summary">Select OS versions…</span><span class="osv-caret">▾</span>
                </button>
                <div class="osv-panel" id="osv-panel" hidden>
                    <div id="osv-groups">
                        <?php foreach ($visibleGroups as $g): ?>
                        <?php if ($panelGroup === '' && empty($osGroups[$g])) continue; ?>
                            <div class="osv-group" data-group="<?= e($g) ?>">
                                <div class="osv-group-h"><?= e($g) ?></div>
                                <div class="osv-group-items">
                                    <?php foreach ($osGroups[$g] as $v): ?>
                                        <label class="check osv-item">
                                            <input type="checkbox" name="os_versions[]" value="<?= e($v) ?>" <?= in_array($v, $oldOsv, true) ? 'checked' : '' ?>> <?= e($v) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="osv-panel-foot">
                        <button type="button" id="osv-add-btn" class="btn btn-ghost btn-sm" title="Add a new OS version to this list">+ New version</button>
                        <button type="button" id="osv-done" class="btn btn-primary btn-sm">Done</button>
                    </div>
                </div>
            </div>
        </div>
        <label>Official website<input name="official_website" value="<?= e($fv('official_website')) ?>" placeholder="https://…"></label>
        <label>Developer website<input name="developer_website" value="<?= e($fv('developer_website')) ?>" placeholder="https://…"></label>
        <label class="col-2">Official download URL<input name="official_download_url" value="<?= e($fv('official_download_url')) ?>" placeholder="https://… (official / authorized source only)"></label>
        <div>
            <label style="margin-bottom:6px">Price type</label>
            <div style="display:flex;gap:8px">
                <select name="price_type" id="f-price" style="flex:1">
                    <option value="">—</option>
                    <?php foreach (($priceTypes ?? []) as $pv => $pl): ?>
                        <option value="<?= e($pv) ?>" <?= $fv('price_type') === $pv ? 'selected' : '' ?>><?= e($pl) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="price-add-btn" class="btn btn-ghost" title="Add a new price type">+ New</button>
            </div>
        </div>
        <div>
            <label style="margin-bottom:6px">Category</label>
            <div style="display:flex;gap:8px">
                <select name="category_id" id="f-cat" style="flex:1">
                    <option value="">—</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $fv('category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" id="cat-add-btn" class="btn btn-ghost" title="Add a new category">+ New</button>
            </div>
        </div>
        <label>Release date<input name="release_date" type="date" value="<?= e($fv('release_date')) ?>"></label>
        </div>
    </section>

    <section class="form-card" data-tab="basics">
        <div class="fc-h"><span class="fc-ic">🖥️</span><div><h3>Platform &amp; status</h3><p>कौन-से OS, कब live हो, auto-update</p></div></div>
        <div class="form-grid">
        <div class="col-2">
            <label style="margin-bottom:6px">Operating systems</label>
            <div style="display:flex;gap:16px;flex-wrap:wrap">
                <?php
                    $preOs = $preOs ?? 0;
                    $checkedOsIds = $checkedOsIds ?? [(int) $preOs];
                    foreach ($oss as $os):
                ?>
                    <label class="check" style="font-weight:400">
                        <input type="checkbox" name="os[]" value="<?= (int) $os['id'] ?>" <?= in_array((int) $os['id'], array_map('intval', $checkedOsIds), true) ? 'checked' : '' ?>> <?= e($os['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <label>Status
            <select name="status">
                <?php
                    $curStatus = $fv('status', 'published');
                    $statusOpts = $isEdit
                        ? ['published' => 'Published', 'review' => 'Review', 'draft' => 'Draft', 'rejected' => 'Rejected', 'disabled' => 'Disabled']
                        : ['published' => 'Published (live now)', 'review' => 'Review (pending)', 'draft' => 'Draft'];
                    foreach ($statusOpts as $sv => $sl):
                ?>
                    <option value="<?= $sv ?>" <?= $curStatus === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label style="flex-direction:row;align-items:center;gap:8px;font-weight:400">
            <input type="checkbox" name="auto_update" value="1" style="width:auto" <?= $fv('auto_update') == '1' ? 'checked' : '' ?>> 🔁 Auto-update version when a newer one is found
        </label>
        </div>
    </section>

    <section class="form-card" data-tab="media">
        <div class="fc-h"><span class="fc-ic">🖼️</span><div><h3>Media</h3><p>logo, screenshots और demo video</p></div></div>
        <div class="form-grid">
        <!-- Logo: URL or upload -->
        <label>Logo URL<input name="logo" id="f-logo" value="<?= e($fv('logo')) ?>" placeholder="https://… (optional)"></label>
        <label>Logo — upload image<input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/gif"></label>

        <!-- Demo video -->
        <label class="col-2">🎥 Demo / YouTube video URL<input name="video_url" value="<?= e($fv('video_url')) ?>" placeholder="https://youtube.com/watch?v=… (optional)"></label>

        <!-- Screenshots -->
        <div class="col-2">
            <label style="margin-bottom:6px">📸 Screenshots — upload images (you can pick several)</label>
            <input type="file" name="screenshots[]" accept="image/png,image/jpeg,image/webp" multiple>
            <div class="muted small" style="margin-top:4px">These show as a gallery on the software page.</div>
        </div>
        </div>
    </section>

    <section class="form-card" data-tab="content">
        <div class="fc-h"><span class="fc-ic">✍️</span><div><h3>Content</h3><p>description, features, pros/cons, tags</p></div></div>
        <div class="form-grid">
        <label class="col-2">Short description<input name="short_description" id="f-short" value="<?= e($fv('short_description')) ?>" maxlength="320" placeholder="One-line summary"></label>
        <div class="col-2">
            <label style="margin-bottom:6px">Long description</label>
            <div class="rt-tb">
                <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                <button type="button" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                <button type="button" data-cmd="formatBlock" data-val="h3" title="Heading">H</button>
                <button type="button" data-cmd="createLink" title="Link">🔗</button>
            </div>
            <div id="rt-ed" class="rt-ed" contenteditable="true"><?= sanitize_rich((string) $fv('long_description')) ?></div>
            <textarea name="long_description" id="rt-src" hidden><?= e($fv('long_description')) ?></textarea>
        </div>

        <!-- Features / Pros / Cons -->
        <label class="col-2">⭐ Features <span class="muted small">(one per line)</span><textarea name="features" rows="4" placeholder="Fast downloads&#10;Auto captions&#10;Works offline"><?= e($featuresText ?? '') ?></textarea></label>
        <label>✓ Pros <span class="muted small">(one per line)</span><textarea name="pros" rows="4" placeholder="Easy to use&#10;Free"><?= e($prosText ?? '') ?></textarea></label>
        <label>✕ Cons <span class="muted small">(one per line)</span><textarea name="cons" rows="4" placeholder="Shows ads"><?= e($consText ?? '') ?></textarea></label>

        <!-- Tags -->
        <label class="col-2">🏷️ Tags <span class="muted small">(comma separated)</span><input name="tags" value="<?= e($tagsText ?? '') ?>" placeholder="video editor, free, offline"></label>

        <label class="col-2">Minimum requirements<textarea name="minimum_requirements" rows="3"><?= e($fv('minimum_requirements')) ?></textarea></label>
        </div>
    </section>

    <section class="form-card" data-tab="review" id="tab-review">
        <div class="fc-h"><span class="fc-ic">✓</span><div><h3>Review — publish से पहले जाँच</h3><p>तैयारी, SEO और क्या बाकी है — नीचे दिखेगा</p></div></div>
        <div id="review-slot" class="muted small">तैयारी की जानकारी यहाँ आएगी…</div>
    </section>

    </div><!-- /form-cards -->
    <div class="admin-form-actions">
        <?php if ($isEdit): ?>
            <button class="btn btn-primary" type="submit">💾 Save changes</button>
            <span class="muted small">Trust <?= (int) ($s['trust_score'] ?? 0) ?> · Quality <?= (int) ($s['quality_score'] ?? 0) ?> · <?= e($s['verification_status'] ?? '') ?></span>
        <?php else: ?>
            <button class="btn btn-primary" type="submit">Add software</button>
            <button class="btn btn-ghost" type="submit" name="and_new" value="1" title="Publish and open a fresh form for the next one">Add &amp; next ➜</button>
            <span class="muted small">Trust score is calculated automatically. Only add official / authorized sources.</span>
        <?php endif; ?>
    </div>
</form>

<div id="price-modal" style="display:none;position:fixed;inset:0;background:rgba(10,12,20,.6);align-items:center;justify-content:center;z-index:1000;padding:20px">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;max-width:420px;width:100%;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,.4)">
        <h3 style="margin:0 0 12px;font-size:1.1rem">➕ New price type</h3>
        <input id="price-name" placeholder="e.g. Subscription" style="width:100%" maxlength="40">
        <div id="price-msg" class="muted small" style="margin-top:6px">It gets added to the list and selected automatically.</div>
        <div style="display:flex;gap:10px;margin-top:16px;justify-content:flex-end">
            <button type="button" id="price-cancel" class="btn btn-ghost btn-sm">Cancel</button>
            <button type="button" id="price-save" class="btn btn-primary btn-sm">Add price type</button>
        </div>
    </div>
</div>

<div id="osv-modal" style="display:none;position:fixed;inset:0;background:rgba(10,12,20,.6);align-items:center;justify-content:center;z-index:1000;padding:20px">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;max-width:420px;width:100%;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,.4)">
        <h3 style="margin:0 0 12px;font-size:1.1rem">➕ New OS version</h3>
        <input id="osv-name" placeholder="e.g. Windows Server 2022" style="width:100%" maxlength="60">
        <div id="osv-msg" class="muted small" style="margin-top:6px">It gets added to the list and ticked automatically.</div>
        <div style="display:flex;gap:10px;margin-top:16px;justify-content:flex-end">
            <button type="button" id="osv-cancel" class="btn btn-ghost btn-sm">Cancel</button>
            <button type="button" id="osv-save" class="btn btn-primary btn-sm">Add version</button>
        </div>
    </div>
</div>

<div id="cat-modal" style="display:none;position:fixed;inset:0;background:rgba(10,12,20,.6);align-items:center;justify-content:center;z-index:1000;padding:20px">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;max-width:420px;width:100%;padding:20px;box-shadow:0 20px 60px rgba(0,0,0,.4)">
        <h3 style="margin:0 0 12px;font-size:1.1rem">➕ New category</h3>
        <input id="cat-name" placeholder="Category name (e.g. Video Editors)" style="width:100%" maxlength="120">
        <div id="cat-msg" class="muted small" style="margin-top:6px"></div>
        <div style="display:flex;gap:10px;margin-top:16px;justify-content:flex-end">
            <button type="button" id="cat-cancel" class="btn btn-ghost btn-sm">Cancel</button>
            <button type="button" id="cat-save" class="btn btn-primary btn-sm">Add category</button>
        </div>
    </div>
</div>

<style>
.pv-card{display:flex;gap:12px;align-items:flex-start;background:var(--surface-2);border:1px solid var(--border);border-radius:12px;padding:14px;max-width:520px}
.pv-ic{width:46px;height:46px;border-radius:11px;background:linear-gradient(135deg,var(--brand),var(--brand-2));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1.2rem;flex:0 0 auto;overflow:hidden}
.pv-ic img{width:46px;height:46px;object-fit:contain}
.rt-tb{display:flex;gap:4px;background:var(--surface-2);border:1px solid var(--border);border-bottom:0;border-radius:8px 8px 0 0;padding:6px 8px}
.rt-tb button{background:var(--surface);border:1px solid var(--border);border-radius:6px;min-width:32px;height:28px;cursor:pointer;color:var(--text);font-size:.85rem}
.rt-tb button:hover{border-color:var(--brand);color:var(--brand)}
.rt-ed{background:var(--surface-2);border:1px solid var(--border);border-radius:0 0 8px 8px;padding:11px 13px;min-height:120px;color:var(--text);font-size:.92rem;line-height:1.6;outline:none}
.rt-ed:focus{border-color:var(--brand)}
.rt-ed h3{font-size:1.05rem;margin:.4em 0}
.rt-ed ul{padding-left:1.3em;margin:.4em 0}
/* OS versions dropdown */
.osv-dd{position:relative;max-width:520px}
.osv-toggle{display:flex;align-items:center;gap:8px;width:100%;text-align:left;background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:10px 12px;color:var(--text);cursor:pointer;font-size:.92rem}
.osv-toggle:hover{border-color:var(--brand)}
.osv-toggle #osv-summary{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.osv-caret{color:var(--muted)}
.osv-panel{position:absolute;z-index:50;top:calc(100% + 4px);left:0;right:0;background:var(--surface);border:1px solid var(--border);border-radius:10px;box-shadow:0 16px 40px rgba(0,0,0,.35);padding:8px;max-height:340px;overflow:auto}
.osv-group{padding:4px 4px 8px}
.osv-group-h{font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700;margin:6px 4px 6px}
.osv-group-items{display:flex;flex-wrap:wrap;gap:6px 16px}
.osv-item{font-weight:400;white-space:nowrap}
.osv-panel-foot{display:flex;gap:8px;justify-content:space-between;align-items:center;border-top:1px solid var(--border);padding:8px 4px 2px;margin-top:4px;position:sticky;bottom:-8px;background:var(--surface)}
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
        if (name === 'long_description') { var ed = document.getElementById('rt-ed'); if (ed) ed.innerText = val; }
    }
    // Fill features / pros / cons / tags (arrays) — used by every free fill flow.
    function fillRich(d) {
        if (!d) return;
        if (Array.isArray(d.features) && d.features.length) { var f = document.querySelector('[name="features"]'); if (f) f.value = d.features.join('\n'); }
        if (Array.isArray(d.pros) && d.pros.length) { var p = document.querySelector('[name="pros"]'); if (p) p.value = d.pros.join('\n'); }
        if (Array.isArray(d.cons) && d.cons.length) { var c = document.querySelector('[name="cons"]'); if (c) c.value = d.cons.join('\n'); }
        if (Array.isArray(d.tags) && d.tags.length) set('tags', d.tags.join(', '));
    }
    // Tick any OS-version checkboxes whose label appears in the detected OS text.
    function tickOsVersions(osText) {
        if (!osText) return;
        var t = String(osText).toLowerCase();
        var any = false;
        document.querySelectorAll('input[name="os_versions[]"]').forEach(function (cb) {
            if (t.indexOf(cb.value.toLowerCase()) >= 0) { cb.checked = true; any = true; }
        });
        if (any) { var s = document.getElementById('osv-summary'); if (s && window.__osvUpdate) window.__osvUpdate(); }
    }

    // ---- Add a category inline (popup) ----
    var catBtn = document.getElementById('cat-add-btn');
    var catModal = document.getElementById('cat-modal');
    var catSel = document.getElementById('f-cat');
    if (catBtn && catModal) {
        var catName = document.getElementById('cat-name');
        var catMsg = document.getElementById('cat-msg');
        var catSave = document.getElementById('cat-save');
        var ci = document.querySelector('.admin-form input[name="_csrf"]');
        var C_NAME = ci ? ci.name : '_csrf', C_VAL = ci ? ci.value : '';
        function closeCat() { catModal.style.display = 'none'; }
        catBtn.addEventListener('click', function () { catModal.style.display = 'flex'; catName.value = ''; catMsg.textContent = ''; catName.focus(); });
        document.getElementById('cat-cancel').addEventListener('click', closeCat);
        catModal.addEventListener('click', function (e) { if (e.target === catModal) closeCat(); });
        catName.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); catSave.click(); } });
        catSave.addEventListener('click', function () {
            var n = catName.value.trim();
            if (n.length < 2) { catMsg.textContent = 'Enter a category name.'; return; }
            catSave.disabled = true; catMsg.textContent = 'Adding…';
            var body = new URLSearchParams(); body.append(C_NAME, C_VAL); body.append('name', n);
            fetch(<?= json_encode(base_url('/admin/software/category')) ?>, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    catSave.disabled = false;
                    if (!res.ok) { catMsg.textContent = res.message || 'Failed.'; return; }
                    var opt = catSel.querySelector('option[value="' + res.id + '"]');
                    if (!opt) { opt = document.createElement('option'); opt.value = res.id; opt.textContent = res.name; catSel.appendChild(opt); }
                    catSel.value = String(res.id);
                    closeCat();
                })
                .catch(function () { catSave.disabled = false; catMsg.textContent = 'Failed — try again.'; });
        });
    }

    // ---- Add a price type inline (popup) ----
    var priceBtn = document.getElementById('price-add-btn');
    var priceModal = document.getElementById('price-modal');
    var priceSel = document.getElementById('f-price');
    if (priceBtn && priceModal && priceSel) {
        var priceName = document.getElementById('price-name');
        var priceMsg = document.getElementById('price-msg');
        var priceSave = document.getElementById('price-save');
        var pi = document.querySelector('.admin-form input[name="_csrf"]');
        var P_NAME = pi ? pi.name : '_csrf', P_VAL = pi ? pi.value : '';
        function closePrice() { priceModal.style.display = 'none'; }
        priceBtn.addEventListener('click', function () { priceModal.style.display = 'flex'; priceName.value = ''; priceMsg.textContent = 'It gets added to the list and selected automatically.'; priceName.focus(); });
        document.getElementById('price-cancel').addEventListener('click', closePrice);
        priceModal.addEventListener('click', function (e) { if (e.target === priceModal) closePrice(); });
        priceName.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); priceSave.click(); } });
        priceSave.addEventListener('click', function () {
            var n = priceName.value.trim();
            if (n.length < 2) { priceMsg.textContent = 'Enter a price type.'; return; }
            priceSave.disabled = true; priceMsg.textContent = 'Adding…';
            var body = new URLSearchParams(); body.append(P_NAME, P_VAL); body.append('name', n);
            fetch(<?= json_encode(base_url('/admin/software/price-type')) ?>, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    priceSave.disabled = false;
                    if (!res.ok) { priceMsg.textContent = res.message || 'Failed.'; return; }
                    var opt = priceSel.querySelector('option[value="' + res.value + '"]');
                    if (!opt) { opt = document.createElement('option'); opt.value = res.value; opt.textContent = res.label; priceSel.appendChild(opt); }
                    priceSel.value = res.value;
                    closePrice();
                    if (typeof updatePreview === 'function') updatePreview();
                })
                .catch(function () { priceSave.disabled = false; priceMsg.textContent = 'Failed — try again.'; });
        });
    }

    // ---- OS versions: grouped dropdown + add-your-own ----
    var osvDd = document.getElementById('osv-dd');
    if (osvDd) {
        var osvToggle = document.getElementById('osv-toggle');
        var osvPanel = document.getElementById('osv-panel');
        var osvGroups = document.getElementById('osv-groups');
        var osvSummary = document.getElementById('osv-summary');
        var osvModal = document.getElementById('osv-modal');
        var osvName = document.getElementById('osv-name');
        var osvMsg = document.getElementById('osv-msg');
        var osvSave = document.getElementById('osv-save');
        var oi = document.querySelector('.admin-form input[name="_csrf"]');
        var O_NAME = oi ? oi.name : '_csrf', O_VAL = oi ? oi.value : '';
        var panelGroup = osvDd.getAttribute('data-panel-group') || '';

        function groupOf(v) {
            var l = v.toLowerCase();
            if (l.indexOf('windows') >= 0) return 'Windows';
            if (l.indexOf('mac') >= 0) return 'macOS';
            if (l.indexOf('ipad') >= 0 || l.indexOf('ios') >= 0) return 'iOS / iPadOS';
            if (l.indexOf('android') >= 0) return 'Android';
            if (l.indexOf('linux') >= 0 || l.indexOf('ubuntu') >= 0 || l.indexOf('debian') >= 0) return 'Linux';
            return 'Other';
        }
        function updateSummary() {
            var picked = [];
            osvGroups.querySelectorAll('input[name="os_versions[]"]:checked').forEach(function (cb) { picked.push(cb.value); });
            osvSummary.textContent = picked.length ? picked.join(', ') : 'Select OS versions…';
            osvSummary.style.color = picked.length ? 'var(--text)' : 'var(--muted)';
        }
        function ensureGroup(name) {
            var g = osvGroups.querySelector('.osv-group[data-group="' + name + '"]');
            if (g) return g.querySelector('.osv-group-items');
            g = document.createElement('div');
            g.className = 'osv-group'; g.setAttribute('data-group', name);
            g.innerHTML = '<div class="osv-group-h"></div><div class="osv-group-items"></div>';
            g.querySelector('.osv-group-h').textContent = name;
            osvGroups.appendChild(g);
            return g.querySelector('.osv-group-items');
        }
        function addOsvChip(val, checked) {
            var dup = false;
            osvGroups.querySelectorAll('input[name="os_versions[]"]').forEach(function (cb) {
                if (cb.value.toLowerCase() === val.toLowerCase()) { dup = true; if (checked) cb.checked = true; }
            });
            if (dup) { updateSummary(); return; }
            var items = ensureGroup(panelGroup || groupOf(val));
            var lab = document.createElement('label');
            lab.className = 'check osv-item';
            var cb = document.createElement('input');
            cb.type = 'checkbox'; cb.name = 'os_versions[]'; cb.value = val; cb.checked = !!checked;
            cb.addEventListener('change', updateSummary);
            lab.appendChild(cb); lab.appendChild(document.createTextNode(' ' + val));
            items.appendChild(lab);
            updateSummary();
        }

        function openPanel() { osvPanel.hidden = false; osvToggle.setAttribute('aria-expanded', 'true'); }
        function closePanel() { osvPanel.hidden = true; osvToggle.setAttribute('aria-expanded', 'false'); }
        osvToggle.addEventListener('click', function () { osvPanel.hidden ? openPanel() : closePanel(); });
        document.getElementById('osv-done').addEventListener('click', closePanel);
        document.addEventListener('click', function (e) { if (!osvDd.contains(e.target)) closePanel(); });
        osvGroups.querySelectorAll('input[name="os_versions[]"]').forEach(function (cb) { cb.addEventListener('change', updateSummary); });
        window.__osvUpdate = updateSummary;
        updateSummary();

        // "+ New version" popup
        if (osvModal && osvName && osvSave) {
            function closeOsv() { osvModal.style.display = 'none'; }
            document.getElementById('osv-add-btn').addEventListener('click', function () {
                osvModal.style.display = 'flex'; osvName.value = '';
                osvMsg.textContent = 'It gets added to the right group and ticked automatically.'; osvName.focus();
            });
            document.getElementById('osv-cancel').addEventListener('click', closeOsv);
            osvModal.addEventListener('click', function (e) { if (e.target === osvModal) closeOsv(); });
            osvName.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); osvSave.click(); } });
            osvSave.addEventListener('click', function () {
                var n = osvName.value.trim();
                if (n.length < 2) { osvMsg.textContent = 'Enter an OS version.'; return; }
                osvSave.disabled = true; osvMsg.textContent = 'Adding…';
                var body = new URLSearchParams(); body.append(O_NAME, O_VAL); body.append('name', n);
                fetch(<?= json_encode(base_url('/admin/software/os-version')) ?>, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        osvSave.disabled = false;
                        if (!res.ok) { osvMsg.textContent = res.message || 'Failed.'; return; }
                        addOsvChip(res.name || n, true);
                        closeOsv();
                    })
                    .catch(function () { osvSave.disabled = false; osvMsg.textContent = 'Failed — try again.'; });
            });
        }
    }

    // ---- Auto-fill the logo from Google when a website/download URL is entered ----
    var logoIn = document.getElementById('f-logo');
    function hostOf(u) { try { return new URL(u.match(/^https?:\/\//i) ? u : 'https://' + u).hostname; } catch (e) { return ''; } }
    function autoLogoFrom(url) {
        if (!logoIn || (logoIn.value || '').trim() !== '') return; // don't overwrite a chosen logo
        var h = hostOf((url || '').trim());
        if (!h) return;
        logoIn.value = 'https://www.google.com/s2/favicons?domain=' + h + '&sz=128';
        if (typeof updatePreview === 'function') updatePreview();
    }
    ['official_website', 'official_download_url', 'developer_website'].forEach(function (nm) {
        var el = document.querySelector('[name="' + nm + '"]');
        if (el) el.addEventListener('blur', function () { autoLogoFrom(el.value); });
    });

    // ---- Rich text editor for the long description ----
    var rtEd = document.getElementById('rt-ed');
    var rtSrc = document.getElementById('rt-src');
    if (rtEd && rtSrc) {
        document.querySelectorAll('.rt-tb button').forEach(function (b) {
            b.addEventListener('click', function () {
                var cmd = b.getAttribute('data-cmd');
                rtEd.focus();
                if (cmd === 'createLink') { var u = prompt('Link URL:', 'https://'); if (u) document.execCommand('createLink', false, u); }
                else if (cmd === 'formatBlock') { document.execCommand('formatBlock', false, b.getAttribute('data-val')); }
                else { document.execCommand(cmd, false, null); }
                rtSrc.value = rtEd.innerHTML;
            });
        });
        rtEd.addEventListener('input', function () { rtSrc.value = rtEd.innerHTML; });
        var frm = rtEd.closest('form');
        if (frm) frm.addEventListener('submit', function () { rtSrc.value = rtEd.innerHTML; });
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
                 'version','license_type','logo','short_description','long_description',
                 'file_size','release_date','minimum_requirements'].forEach(function (k) { set(k, d[k]); });
                ['price_type','category_id'].forEach(function (k) {
                    if (d[k] !== undefined && d[k] !== null && d[k] !== '') { var el = document.querySelector('[name="' + k + '"]'); if (el) el.value = String(d[k]); }
                });
                fillRich(d);
                tickOsVersions(d.operating_system);
                // Note: operating systems keep the current panel's selection — we
                // don't override them, so a Windows panel stays Windows.
                statusEl.innerHTML = '✓ Filled from <strong>' + (d.source || 'catalogue') + '</strong>' + (d.match ? ' (' + d.match + '% match)' : '') + '. Features, pros/cons &amp; tags added from real data. Review, then Add software.';
                updatePreview();
            })
            .catch(function () { btn.disabled = false; btn.textContent = original; statusEl.textContent = 'Lookup failed — fill the form manually.'; });
    });

    // ---- Import from official URL (most reliable for commercial software) ----
    var urlBtn = document.getElementById('url-import');
    var urlIn = document.getElementById('f-url');
    var urlStatus = document.getElementById('url-status');
    if (urlBtn && urlIn) urlBtn.addEventListener('click', function () {
        var url = (urlIn.value || '').trim();
        if (!/^https?:\/\//i.test(url)) { urlStatus.textContent = 'Paste the full official website URL (starting with https://).'; urlIn.focus(); return; }
        var original = urlBtn.textContent;
        urlBtn.disabled = true; urlBtn.textContent = '⏳ Reading…';
        urlStatus.textContent = 'Reading the official page…';
        fetch(<?= json_encode(base_url('/admin/software/import-url')) ?> + '?url=' + encodeURIComponent(url))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                urlBtn.disabled = false; urlBtn.textContent = original;
                if (!res.ok) { urlStatus.textContent = res.message || 'Could not read that page.'; return; }
                var d = res.data || {};
                ['name','developer_name','developer_website','official_website','official_download_url',
                 'version','license_type','logo','short_description','long_description'].forEach(function (k) { set(k, d[k]); });
                if (d.category_id !== undefined && d.category_id !== null && d.category_id !== '') {
                    var ce = document.getElementById('f-cat'); if (ce) ce.value = String(d.category_id);
                }
                if (!d.official_website) set('official_website', url);
                fillRich(d);
                urlStatus.innerHTML = '✓ Imported from the official page — including features, pros/cons &amp; tags built from the real page. Review, then <strong>Add software</strong>.';
                updatePreview();
            })
            .catch(function () { urlBtn.disabled = false; urlBtn.textContent = original; urlStatus.textContent = 'Import failed — check the URL and try again.'; });
    });

    // ---- AI fill (full: official details + AI-written rich content) ----
    var aiBtn = document.getElementById('ai-fill');
    function fillList(name, arr) {
        if (!Array.isArray(arr) || !arr.length) return;
        var el = document.querySelector('[name="' + name + '"]');
        if (el) el.value = arr.join('\n');
    }
    if (aiBtn) aiBtn.addEventListener('click', function () {
        var name = (nameInput.value || '').trim();
        if (name.length < 2) { statusEl.textContent = 'Type a software name first.'; nameInput.focus(); return; }
        var original = aiBtn.textContent;
        aiBtn.disabled = true; aiBtn.textContent = '⏳ Writing with AI…';
        statusEl.textContent = 'Finding official details and writing a full description with AI… (may take ~15s)';
        fetch(<?= json_encode(base_url('/admin/software/ai-fill')) ?> + '?name=' + encodeURIComponent(name))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                aiBtn.disabled = false; aiBtn.textContent = original;
                if (!res.ok) { statusEl.textContent = res.message || 'Failed.'; return; }
                var d = res.lookup || {};
                ['name','developer_name','developer_website','official_website','official_download_url',
                 'version','license_type','logo','file_size','release_date'].forEach(function (k) { set(k, d[k]); });
                ['price_type','category_id'].forEach(function (k) {
                    if (d[k] !== undefined && d[k] !== null && d[k] !== '') { var el = document.querySelector('[name="' + k + '"]'); if (el) el.value = String(d[k]); }
                });
                tickOsVersions(d.operating_system);
                var a = res.ai;
                if (a) {
                    set('short_description', a.short_description);
                    set('long_description', a.long_description);
                    set('minimum_requirements', a.minimum_requirements);
                    fillList('features', a.features);
                    fillList('pros', a.pros);
                    fillList('cons', a.cons);
                    if (Array.isArray(a.tags)) set('tags', a.tags.join(', '));
                    statusEl.innerHTML = '✨ AI filled the full page (description, features, pros/cons, tags). Review, then <strong>Add software</strong>.';
                } else if (!res.ai_available) {
                    statusEl.innerHTML = '⚠️ Basic details filled. For the long description, features, pros/cons &amp; tags, add your Anthropic API key in <a href="<?= e(base_url('/admin/settings')) ?>">Settings</a>, then try AI fill again.';
                } else {
                    statusEl.textContent = 'Details filled, but AI failed: ' + (res.ai_message || 'unknown error');
                }
                updatePreview();
            })
            .catch(function () { aiBtn.disabled = false; aiBtn.textContent = original; statusEl.textContent = 'AI fill failed — try Auto-fill instead.'; });
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
    // ---- Name autocomplete ----
    var dl = document.getElementById('name-suggest');
    var acTimer = null;
    if (dl) document.getElementById('f-name').addEventListener('input', function () {
        var q = this.value.trim();
        if (q.length < 2) return;
        clearTimeout(acTimer);
        acTimer = setTimeout(function () {
            fetch(<?= json_encode(base_url('/admin/software/suggest')) ?> + '?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    dl.innerHTML = '';
                    (res.items || []).forEach(function (n) { var o = document.createElement('option'); o.value = n; dl.appendChild(o); });
                }).catch(function () {});
        }, 250);
    });

    ['f-name','f-short','f-dev','f-logo','f-price'].forEach(function (id) {
        var el = document.getElementById(id); if (el) el.addEventListener('input', updatePreview);
    });
    document.querySelectorAll('input[name="os[]"]').forEach(function (cb) { cb.addEventListener('change', updatePreview); });
    updatePreview();
})();
</script>

<style>
.mini-tool{font-size:.72rem;padding:3px 9px;margin-top:6px;border-radius:7px;border:1px solid var(--border);background:var(--surface-2);color:var(--text);cursor:pointer;align-self:flex-start}
.mini-tool:hover{border-color:var(--brand);color:var(--brand)}
.tool-row{display:flex;gap:6px;flex-wrap:wrap;margin-top:6px}
.tool-warn{margin-top:8px;background:color-mix(in srgb,var(--yellow) 14%,var(--surface));color:var(--yellow);border:1px solid color-mix(in srgb,var(--yellow) 40%,var(--border));border-radius:9px;padding:8px 11px;font-size:.84rem;font-weight:600}
.tool-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.tool-chip{font-size:.75rem;padding:3px 9px;border-radius:999px;border:1px dashed var(--border);background:var(--surface-2);color:var(--muted);cursor:pointer}
.tool-chip:hover{border-color:var(--brand);color:var(--brand)}
.tool-thumb img{margin-top:8px;max-width:200px;border-radius:8px;border:1px solid var(--border);display:block}
.tool-dz{margin-top:8px;border:1.5px dashed var(--border);border-radius:10px;padding:14px;text-align:center;color:var(--muted);font-size:.85rem;background:var(--surface-2);cursor:pointer}
.tool-dz.over{border-color:var(--brand);color:var(--brand);background:color-mix(in srgb,var(--brand) 8%,var(--surface-2))}
.tool-prev{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
.tool-prev .tp{position:relative;width:92px;height:62px;border-radius:8px;overflow:hidden;border:1px solid var(--border)}
.tool-prev .tp img{width:100%;height:100%;object-fit:cover}
.tool-prev .rm{position:absolute;top:3px;right:3px;background:rgba(0,0,0,.6);color:#fff;border-radius:50%;width:18px;height:18px;display:grid;place-items:center;cursor:pointer;font-size:.68rem}
.tool-ind{display:block;margin-top:6px;font-size:.8rem;font-weight:600}
.tool-ind .ok{color:var(--green)}.tool-ind .bad{color:var(--red)}
.tool-check{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 16px;margin-bottom:16px;box-shadow:var(--shadow)}
.tool-check .ch-h{display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:.9rem}
.tool-check .cbar{height:9px;border-radius:999px;background:var(--surface-2);overflow:hidden;margin:9px 0}
.tool-check .cbar i{display:block;height:100%;background:linear-gradient(90deg,var(--brand),var(--brand-2));transition:width .3s}
.tool-check .miss{display:flex;flex-wrap:wrap;gap:6px}
.tool-check .miss span{font-size:.73rem;padding:2px 9px;border-radius:999px;background:color-mix(in srgb,var(--red) 13%,transparent);color:var(--red)}
.tool-meter{display:flex;gap:16px;margin-top:10px;font-size:.78rem;color:var(--muted);flex-wrap:wrap}
.tool-meter b{color:var(--text)}
.tool-save-pill{position:fixed;bottom:20px;left:20px;background:var(--text);color:var(--surface);border-radius:999px;padding:6px 13px;font-size:.78rem;opacity:0;transition:.3s;z-index:60;pointer-events:none}
.tool-save-pill.show{opacity:.92}
.tool-restore{display:flex;gap:10px;align-items:center;flex-wrap:wrap;background:var(--surface);border:1px solid var(--brand);border-radius:12px;padding:11px 14px;margin-bottom:14px;font-size:.88rem;font-weight:600}
.tool-tpl{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px;font-size:.85rem}
.tool-tpl select{padding:.4em .6em;border:1px solid var(--border);border-radius:8px;background:var(--surface-2);color:var(--text)}
.pv-card.phone-mode{max-width:300px;border-radius:26px;border:8px solid var(--surface-2)}
</style>

<script>
(function () {
    var form = document.querySelector('.admin-form');
    if (!form) return;
    // Hidden accumulator for website-screenshot URLs (must live inside the form).
    var shotUrlsField = document.createElement('input');
    shotUrlsField.type = 'hidden'; shotUrlsField.name = 'screenshot_urls'; shotUrlsField.id = 'f-shoturls';
    form.appendChild(shotUrlsField);
    var byName = function (n) { return form.querySelector('[name="' + n + '"]'); };
    var $ = function (id) { return document.getElementById(id); };
    var ci = form.querySelector('input[name="_csrf"]');
    var C_NAME = ci ? ci.name : '_csrf', C_VAL = ci ? ci.value : '';
    var isEdit = /\/\d+\/edit/.test(form.getAttribute('action') || '');
    var U = {
        dupe: <?= json_encode(base_url('/admin/software/dupe-check')) ?>,
        link: <?= json_encode(base_url('/admin/software/link-check')) ?>,
        ver:  <?= json_encode(base_url('/admin/software/version-fetch')) ?>,
        ai:   <?= json_encode(base_url('/admin/software/ai-assist')) ?>,
        edit: <?= json_encode(base_url('/admin/software/')) ?>
    };
    function toast(msg) {
        var p = $('tool-pill') || (function () { var e = document.createElement('div'); e.id = 'tool-pill'; e.className = 'tool-save-pill'; document.body.appendChild(e); return e; })();
        p.textContent = msg; p.classList.add('show'); clearTimeout(p._t); p._t = setTimeout(function () { p.classList.remove('show'); }, 1600);
    }
    function toolBtn(label, title, fn) { var b = document.createElement('button'); b.type = 'button'; b.className = 'mini-tool'; b.textContent = label; if (title) b.title = title; b.addEventListener('click', fn); return b; }
    function after(el, node) { if (el) el.insertAdjacentElement('afterend', node); }
    function longText() { var ed = $('rt-ed'); return ed ? (ed.innerText || '').trim() : (byName('long_description') ? byName('long_description').value : ''); }
    function setLong(t) { var ed = $('rt-ed'); if (ed) ed.innerText = t; var src = $('rt-src'); if (src) src.value = t; }

    // ---------- A3: duplicate warning ----------
    var nameEl = byName('name'), dupBox = null, dupT;
    function setDup(html) {
        if (!nameEl) return;
        if (!dupBox) { dupBox = document.createElement('div'); dupBox.className = 'tool-warn'; (nameEl.closest('.col-2') || nameEl.parentNode).appendChild(dupBox); }
        dupBox.innerHTML = html; dupBox.style.display = html ? 'block' : 'none';
    }
    if (nameEl && !isEdit) nameEl.addEventListener('input', function () {
        clearTimeout(dupT); dupT = setTimeout(function () {
            var n = (nameEl.value || '').trim(); if (n.length < 2) { setDup(''); return; }
            fetch(U.dupe + '?name=' + encodeURIComponent(n)).then(function (r) { return r.json(); }).then(function (res) {
                setDup(res.exists ? '⚠️ “' + res.name + '” पहले से publish है — <a href="' + U.edit + res.id + '/edit">खोलें</a>' : '');
            }).catch(function () {});
        }, 450);
    });

    // ---------- A2: paste-to-import ----------
    var urlEl = $('f-url'), impBtn = $('url-import');
    if (urlEl && impBtn) urlEl.addEventListener('paste', function () { setTimeout(function () { if (/^https?:\/\//i.test((urlEl.value || '').trim())) impBtn.click(); }, 60); });
    if (nameEl && urlEl && impBtn) nameEl.addEventListener('paste', function () {
        setTimeout(function () { var v = (nameEl.value || '').trim(); if (/^https?:\/\//i.test(v)) { urlEl.value = v; nameEl.value = ''; impBtn.click(); } }, 60);
    });

    // ---------- A5: keyboard shortcuts ----------
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'Enter')) {
            e.preventDefault(); var b = form.querySelector('.admin-form-actions .btn-primary'); if (b) b.click();
        }
    });

    // ---------- D4: version fetch ----------
    var verEl = byName('version');
    if (verEl) after(verEl, toolBtn('↻ latest लाओ', 'official page से version', function () {
        var w = ((byName('official_website') || {}).value || (byName('official_download_url') || {}).value || '').trim();
        if (!w) { toast('पहले website भरें'); return; }
        var btn = this; btn.disabled = true; btn.textContent = '…';
        fetch(U.ver + '?url=' + encodeURIComponent(w)).then(function (r) { return r.json(); }).then(function (res) {
            btn.disabled = false; btn.textContent = '↻ latest लाओ';
            if (res.version) verEl.value = res.version;
            if (res.file_size && byName('file_size') && !byName('file_size').value) byName('file_size').value = res.file_size;
            if (res.release_date && byName('release_date') && !byName('release_date').value) byName('release_date').value = res.release_date;
            toast(res.ok ? '✓ version मिला' : 'version नहीं मिला');
        }).catch(function () { btn.disabled = false; btn.textContent = '↻ latest लाओ'; });
    }));

    // ---------- D1 + D2: link check + official-domain verify ----------
    var dlEl = byName('official_download_url');
    if (dlEl) {
        var ind = document.createElement('span'); ind.className = 'tool-ind'; (dlEl.closest('label') || dlEl.parentNode).appendChild(ind);
        dlEl.addEventListener('blur', function () {
            var u = (dlEl.value || '').trim(); if (!/^https?:/i.test(u)) { ind.textContent = ''; return; }
            ind.textContent = 'जाँच रहे हैं…';
            fetch(U.link + '?url=' + encodeURIComponent(u)).then(function (r) { return r.json(); }).then(function (res) {
                var html = res.ok ? '<span class="ok">✔ link ठीक (' + res.status + ')</span>' : '<span class="bad">✕ link ' + (res.status || 'नहीं खुला') + '</span>';
                var ow = ((byName('official_website') || {}).value || '').trim();
                try { if (ow) { var h1 = new URL(u).hostname.replace(/^www\./, ''), h2 = new URL(ow).hostname.replace(/^www\./, ''); if (h1 && h2 && (h1.indexOf(h2) >= 0 || h2.indexOf(h1) >= 0)) html += ' <span class="ok">✓ official domain</span>'; } } catch (e) {}
                ind.innerHTML = html;
            }).catch(function () { ind.textContent = ''; });
        });
    }

    // ---------- B6: short-from-long ----------
    var shortEl = byName('short_description');
    if (shortEl) after(shortEl, toolBtn('⤵ long से short बनाओ', '', function () {
        var l = longText(); if (!l) { toast('पहले long description भरें'); return; }
        var s = l.split(/(?<=[.!?])\s/)[0] || l; shortEl.value = s.slice(0, 300); shortEl.dispatchEvent(new Event('input'));
    }));

    // ---------- B4/B5: AI grammar fix + translate (on long description) ----------
    var rtTb = document.querySelector('.rt-tb');
    if (rtTb) {
        function aiRun(mode, label) {
            var t = longText(); if (!t) { toast('description खाली है'); return; }
            toast('AI ' + label + '…');
            var body = new URLSearchParams(); body.append(C_NAME, C_VAL); body.append('mode', mode); body.append('text', t);
            fetch(U.ai, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
                .then(function (r) { return r.json(); }).then(function (res) {
                    if (res.ok) { setLong(res.text); toast('✓ हो गया'); }
                    else toast(res.need_key ? 'Settings में API key डालें' : (res.message || 'AI विफल'));
                }).catch(function () { toast('AI विफल'); });
        }
        var g = document.createElement('button'); g.type = 'button'; g.title = 'Grammar/spelling ठीक करो (AI)'; g.textContent = '✦ Fix'; g.addEventListener('click', function () { aiRun('fix', 'सुधार'); });
        var hi = document.createElement('button'); hi.type = 'button'; hi.title = 'हिंदी में अनुवाद (AI)'; hi.textContent = 'हिंदी'; hi.addEventListener('click', function () { aiRun('translate', 'अनुवाद'); });
        var en = document.createElement('button'); en.type = 'button'; en.title = 'English में अनुवाद (AI)'; en.textContent = 'EN'; en.addEventListener('click', function () { aiRun('en', 'translate'); });
        rtTb.appendChild(g); rtTb.appendChild(hi); rtTb.appendChild(en);
    }

    // ---------- B1: tag suggestions ----------
    var tagEl = byName('tags');
    if (tagEl) {
        var tw = document.createElement('div'); tw.className = 'tool-chips'; (tagEl.closest('label') || tagEl.parentNode).appendChild(tw);
        function suggestTags() {
            var pool = ['free', 'offline', 'portable', 'lightweight'];
            document.querySelectorAll('input[name="os[]"]:checked').forEach(function (cb) { pool.push((cb.parentNode.textContent || '').trim().toLowerCase()); });
            var cat = $('f-cat'); if (cat && cat.selectedIndex > 0) pool.push(cat.options[cat.selectedIndex].text.toLowerCase());
            (byName('name').value || '').toLowerCase().split(/\s+/).forEach(function (w) { if (w.length > 3) pool.push(w); });
            var have = (tagEl.value || '').toLowerCase(), uniq = [];
            pool.forEach(function (t) { t = t.trim(); if (t && uniq.indexOf(t) < 0 && have.indexOf(t) < 0) uniq.push(t); });
            tw.innerHTML = '';
            uniq.slice(0, 8).forEach(function (t) { var c = document.createElement('button'); c.type = 'button'; c.className = 'tool-chip'; c.textContent = '＋ ' + t; c.addEventListener('click', function () { tagEl.value = (tagEl.value ? tagEl.value.replace(/,\s*$/, '') + ', ' : '') + t; c.remove(); }); tw.appendChild(c); });
        }
        ['name', 'price_type'].forEach(function (n) { var e = byName(n); if (e) { e.addEventListener('input', suggestTags); e.addEventListener('change', suggestTags); } });
        if ($('f-cat')) $('f-cat').addEventListener('change', suggestTags);
        document.querySelectorAll('input[name="os[]"]').forEach(function (cb) { cb.addEventListener('change', suggestTags); });
        suggestTags();
    }

    // ---------- C6: YouTube thumbnail ----------
    var vidEl = byName('video_url');
    if (vidEl) {
        var vt = document.createElement('div'); vt.className = 'tool-thumb'; (vidEl.closest('label') || vidEl.parentNode).appendChild(vt);
        vidEl.addEventListener('input', function () {
            var m = (vidEl.value || '').match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([A-Za-z0-9_-]{11})/);
            vt.innerHTML = m ? '<img src="https://img.youtube.com/vi/' + m[1] + '/mqdefault.jpg" alt="video thumbnail">' : '';
        });
    }

    // ---------- E3: select all platforms ----------
    var osBoxes = document.querySelectorAll('input[name="os[]"]');
    if (osBoxes.length) {
        var last = osBoxes[osBoxes.length - 1];
        after(last.closest('.check') || last, toolBtn('☑ सभी platforms', 'सभी OS चुनें', function () {
            osBoxes.forEach(function (cb) { cb.checked = true; cb.dispatchEvent(new Event('change')); });
        }));
    }

    // ---------- C1 + C2 + C3: drag-drop / paste / website screenshot ----------
    var shotEl = form.querySelector('input[name="screenshots[]"]');
    var sUrls = $('f-shoturls');
    if (shotEl) {
        var dz = document.createElement('div'); dz.className = 'tool-dz'; dz.textContent = '⬇ तस्वीरें यहाँ खींचकर छोड़ें, या कहीं भी Ctrl+V से paste करें';
        after(shotEl, dz);
        var prev = document.createElement('div'); prev.className = 'tool-prev'; after(dz, prev);
        after(prev, toolBtn('📸 website से screenshot लो', '', function () {
            var w = ((byName('official_website') || {}).value || (byName('official_download_url') || {}).value || '').trim();
            if (!/^https?:/i.test(w)) { toast('पहले official website भरें'); return; }
            var u = 'https://s.wordpress.com/mshots/v1/' + encodeURIComponent(w) + '?w=1280';
            sUrls.value = sUrls.value ? sUrls.value + '\n' + u : u;
            var d = document.createElement('div'); d.className = 'tp'; d.innerHTML = '<img src="' + u + '" alt="">'; prev.appendChild(d); toast('✓ जोड़ा गया');
        }));
        var dt = new DataTransfer();
        function render() {
            [].slice.call(prev.querySelectorAll('.tp[data-file]')).forEach(function (n) { n.remove(); });
            Array.prototype.forEach.call(dt.files, function (f, idx) {
                var u = URL.createObjectURL(f); var d = document.createElement('div'); d.className = 'tp'; d.setAttribute('data-file', '1');
                d.innerHTML = '<img src="' + u + '"><span class="rm">✕</span>';
                d.querySelector('.rm').addEventListener('click', function () { var nd = new DataTransfer(); Array.prototype.forEach.call(dt.files, function (ff, j) { if (j !== idx) nd.items.add(ff); }); dt = nd; shotEl.files = dt.files; render(); });
                prev.insertBefore(d, prev.firstChild);
            });
        }
        function addFiles(files) { Array.prototype.forEach.call(files, function (f) { if (/^image\//.test(f.type)) dt.items.add(f); }); shotEl.files = dt.files; render(); }
        ['dragover', 'dragenter'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('over'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('over'); }); });
        dz.addEventListener('drop', function (e) { addFiles(e.dataTransfer.files); });
        dz.addEventListener('click', function () { shotEl.click(); });
        shotEl.addEventListener('change', function () { if (shotEl.files && shotEl.files.length && shotEl.files !== dt.files) addFiles(shotEl.files); });
        document.addEventListener('paste', function (e) {
            if (!e.clipboardData) return; var imgs = [];
            Array.prototype.forEach.call(e.clipboardData.items, function (it) { if (it.type && it.type.indexOf('image') === 0) { var f = it.getAsFile(); if (f) imgs.push(f); } });
            if (imgs.length) { addFiles(imgs); toast('✓ screenshot paste हुआ'); }
        });
    }

    // ---------- E1: phone preview toggle ----------
    var pvCard = document.querySelector('.pv-card');
    if (pvCard) after(pvCard, toolBtn('📱 मोबाइल में देखें', '', function () { pvCard.classList.toggle('phone-mode'); this.textContent = pvCard.classList.contains('phone-mode') ? '🖥️ सामान्य' : '📱 मोबाइल में देखें'; }));

    // ---------- B2 + B3 + D3: SEO/readability meter + completeness checklist ----------
    var chk = document.createElement('div'); chk.className = 'tool-check';
    chk.innerHTML = '<div class="ch-h"><span>📋 Post तैयारी</span><span id="chk-pct">0%</span></div><div class="cbar"><i id="chk-bar" style="width:0"></i></div><div class="miss" id="chk-miss"></div><div class="tool-meter"><span>SEO: <b id="chk-seo">—</b></span><span>पढ़ने में: <b id="chk-read">—</b></span></div>';
    form.insertBefore(chk, form.firstChild.nextSibling);
    function fileChosen(n) { var e = form.querySelector('input[name="' + n + '"]'); return e && e.files && e.files.length; }
    function recompute() {
        var items = [
            ['नाम', (byName('name').value || '').trim() !== ''],
            ['Download/Website', (((byName('official_download_url') || {}).value || '') + ((byName('official_website') || {}).value || '')).trim() !== ''],
            ['Short desc', (byName('short_description').value || '').trim() !== ''],
            ['Long desc', longText().length > 20],
            ['Logo', ((byName('logo') || {}).value || '').trim() !== '' || fileChosen('logo_file')],
            ['Category', $('f-cat') && $('f-cat').value !== ''],
            ['OS', !!form.querySelector('input[name="os[]"]:checked')],
            ['Version', (byName('version') && byName('version').value.trim() !== '')]
        ];
        var done = items.filter(function (i) { return i[1]; }).length, pct = Math.round(done / items.length * 100);
        $('chk-pct').textContent = pct + '%'; $('chk-bar').style.width = pct + '%';
        var miss = $('chk-miss'); miss.innerHTML = '';
        items.filter(function (i) { return !i[1]; }).forEach(function (i) { var s = document.createElement('span'); s.textContent = '✕ ' + i[0]; miss.appendChild(s); });
        // SEO
        var title = (byName('name').value || '').length, sd = (byName('short_description').value || '').length;
        var seo = 0; if (title >= 3 && title <= 60) seo += 40; else if (title) seo += 20;
        if (sd >= 50 && sd <= 160) seo += 40; else if (sd) seo += 20;
        if (longText().length > 200) seo += 20;
        $('chk-seo').textContent = seo + '/100';
        // readability
        var lt = longText(); var words = lt ? lt.split(/\s+/).length : 0; var sents = lt ? (lt.split(/[.!?]+/).filter(Boolean).length || 1) : 1;
        var wps = words / sents; $('chk-read').textContent = !lt ? '—' : (wps < 18 ? 'आसान' : wps < 26 ? 'ठीक' : 'कठिन');
    }
    form.addEventListener('input', recompute); form.addEventListener('change', recompute);
    var edEl = $('rt-ed'); if (edEl) edEl.addEventListener('input', recompute);
    recompute();

    // ---------- A4: autosave draft (create mode only) ----------
    if (!isEdit) {
        var KEY = 'addsw_draft_v1';
        function collect() { var o = {}; form.querySelectorAll('[name]').forEach(function (el) { if (el.type === 'file' || el.name === '_csrf' || el.name === 'screenshot_urls') return; if (el.type === 'checkbox') o['cb:' + el.name + ':' + el.value] = el.checked ? 1 : 0; else o[el.name] = el.value; }); o['__long'] = longText(); return o; }
        function applyDraft(o) { Object.keys(o).forEach(function (k) { if (k === '__long') { if (longText().length < 2) setLong(o[k]); return; } if (k.indexOf('cb:') === 0) { var p = k.split(':'); var cb = form.querySelector('[name="' + p[1] + '"][value="' + p[2] + '"]'); if (cb) cb.checked = !!o[k]; } else { var el = byName(k); if (el && (el.value || '') === '') el.value = o[k]; } }); recompute(); if (typeof updatePreview === 'function') updatePreview(); }
        var saved = null; try { saved = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) {}
        if (saved && (saved.name || saved.__long)) {
            var rb = document.createElement('div'); rb.className = 'tool-restore';
            rb.innerHTML = '<span>📝 पिछला अधूरा draft मिला।</span>';
            var yes = toolBtn('बहाल करें', '', function () { applyDraft(saved); rb.remove(); });
            var no = toolBtn('हटाएँ', '', function () { try { localStorage.removeItem(KEY); } catch (e) {} rb.remove(); });
            yes.style.marginTop = '0'; no.style.marginTop = '0'; rb.appendChild(yes); rb.appendChild(no);
            form.insertBefore(rb, form.firstChild.nextSibling);
        }
        var sT; form.addEventListener('input', function () { clearTimeout(sT); sT = setTimeout(function () { try { localStorage.setItem(KEY, JSON.stringify(collect())); toast('💾 सेव'); } catch (e) {} }, 800); });
        if (edEl) edEl.addEventListener('input', function () { clearTimeout(sT); sT = setTimeout(function () { try { localStorage.setItem(KEY, JSON.stringify(collect())); } catch (e) {} }, 800); });
        form.addEventListener('submit', function () { try { localStorage.removeItem(KEY); } catch (e) {} });
    }

    // ---------- A6 / E4: templates ----------
    var TKEY = 'addsw_templates_v1';
    function templates() { try { return JSON.parse(localStorage.getItem(TKEY) || '{}'); } catch (e) { return {}; } }
    var tpl = document.createElement('div'); tpl.className = 'tool-tpl';
    var sel = document.createElement('select'); sel.innerHTML = '<option value="">📂 Template लोड करें…</option>';
    Object.keys(templates()).forEach(function (n) { var o = document.createElement('option'); o.value = n; o.textContent = n; sel.appendChild(o); });
    sel.addEventListener('change', function () { var t = templates()[sel.value]; if (!t) return; Object.keys(t).forEach(function (k) { if (k === '__long') { setLong(t[k]); return; } if (k.indexOf('cb:') === 0) { var p = k.split(':'); var cb = form.querySelector('[name="' + p[1] + '"][value="' + p[2] + '"]'); if (cb) cb.checked = !!t[k]; } else { var el = byName(k); if (el) el.value = t[k]; } }); recompute(); if (typeof updatePreview === 'function') updatePreview(); toast('✓ template लगा'); });
    var saveTpl = toolBtn('💾 Template सेव करो', 'मौजूदा form को template बनाओ', function () {
        var n = prompt('Template का नाम:'); if (!n) return; var all = templates();
        var o = {}; form.querySelectorAll('[name]').forEach(function (el) { if (el.type === 'file' || el.name === '_csrf' || el.name === 'name' || el.name === 'screenshot_urls') return; if (el.type === 'checkbox') o['cb:' + el.name + ':' + el.value] = el.checked ? 1 : 0; else o[el.name] = el.value; }); o['__long'] = longText();
        all[n] = o; try { localStorage.setItem(TKEY, JSON.stringify(all)); } catch (e) {} var op = document.createElement('option'); op.value = n; op.textContent = n; sel.appendChild(op); toast('✓ template सेव');
    });
    saveTpl.style.marginTop = '0'; tpl.appendChild(sel); tpl.appendChild(saveTpl);
    form.insertBefore(tpl, form.firstChild.nextSibling);
})();
</script>

<script>
(function () {
    var tabs = document.querySelectorAll('.ftab');
    var cards = document.querySelectorAll('.form-card[data-tab]');
    if (!tabs.length || !cards.length) return;

    // Move the injected "Post तैयारी" checklist into the Review tab.
    var tc = document.querySelector('.tool-check');
    var slot = document.getElementById('review-slot');
    if (tc && slot) { slot.innerHTML = ''; slot.appendChild(tc); }

    function show(tab) {
        cards.forEach(function (c) { c.classList.toggle('tab-hidden', c.getAttribute('data-tab') !== tab); });
        tabs.forEach(function (t) { t.classList.toggle('is-active', t.getAttribute('data-tab') === tab); });
        var top = document.querySelector('.admin-edit-head'); if (top) top.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.getAttribute('data-tab')); }); });
    show('basics');

    // If the user submits and a tab has an invalid field, jump to that tab.
    var form = document.querySelector('.admin-form');
    if (form) form.addEventListener('submit', function (e) {
        var bad = form.querySelector(':invalid');
        if (bad) {
            var card = bad.closest('.form-card[data-tab]');
            if (card && card.classList.contains('tab-hidden')) { show(card.getAttribute('data-tab')); }
        }
    });
})();
</script>
