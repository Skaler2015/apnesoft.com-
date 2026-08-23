<?php use App\Core\Csrf;
/** @var array $candidates @var array $recent @var string $platformSlug @var ?string $panelLabel */
?>
<div class="admin-edit-head">
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/platform')) ?>">← Platforms</a>
    <h2 style="margin:0 0 0 4px">🧭 Discover<?= $panelLabel ? ' — ' . e($panelLabel) : '' ?></h2>
</div>
<?php if ($panelLabel): ?>
    <div class="flash flash-ok" style="margin:0 0 14px">📌 Showing apps for your <strong><?= e($panelLabel) ?></strong> site. Publishing adds them to that site.</div>
<?php else: ?>
    <div class="flash" style="background:var(--surface-2);border:1px solid var(--border);margin:0 0 14px">Open a platform panel (Windows / Mac / iOS / Android) to see apps for that platform only.</div>
<?php endif; ?>

<form id="dform" class="inline" style="display:none"><?= Csrf::field() ?></form>

<section style="margin-bottom:26px">
    <h3 style="margin:0 0 4px">✨ Ready to publish <span class="muted small">(<?= count($candidates) ?> popular <?= $panelLabel ? e($panelLabel) : '' ?> apps not on your site yet)</span></h3>
    <p class="muted small" style="margin:0 0 12px">One-click publish with the official link. Details &amp; description fill in automatically.</p>
    <?php if (empty($candidates)): ?>
        <div class="flash" style="background:var(--surface-2);border:1px solid var(--border)">🎉 Everything popular for this platform is already published. Use <a href="<?= e(base_url('/admin/software/new')) ?>">Add Software</a> or <a href="<?= e(base_url('/admin/software/bulk')) ?>">Bulk publish</a> for more.</div>
    <?php else: ?>
        <div class="disc-grid">
            <?php foreach ($candidates as $c): ?>
                <div class="disc-card" data-name="<?= e($c['name']) ?>" data-url="<?= e($c['website']) ?>">
                    <div class="disc-top">
                        <div class="disc-logo"><?php if ($c['logo']): ?><img src="<?= e($c['logo']) ?>" alt="" loading="lazy"><?php else: ?><?= e(strtoupper(mb_substr($c['name'], 0, 1))) ?><?php endif; ?></div>
                        <div style="min-width:0;flex:1">
                            <strong style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($c['name']) ?></strong>
                            <span class="muted small"><?= e($c['developer']) ?></span>
                        </div>
                        <?php if ($c['price']): ?><span class="chip chip-soft small"><?= e(str_replace('_', ' ', $c['price'])) ?></span><?php endif; ?>
                    </div>
                    <p class="muted small disc-desc"><?= e($c['desc']) ?></p>
                    <div class="disc-actions">
                        <button type="button" class="btn btn-sm btn-primary disc-pub">⬇ Publish</button>
                        <a class="btn btn-sm btn-ghost" href="<?= e($c['website']) ?>" target="_blank" rel="noopener">Site ↗</a>
                        <span class="disc-st muted small"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section>
    <h3 style="margin:0 0 12px">🆕 Recently published <span class="muted small"><?= $panelLabel ? 'on ' . e($panelLabel) : 'across all platforms' ?></span></h3>
    <?php if (empty($recent)): ?>
        <p class="muted">Nothing published for this platform yet — publish something above to see it here.</p>
    <?php else: ?>
        <div class="disc-grid">
            <?php foreach ($recent as $r): ?>
                <div class="disc-card">
                    <div class="disc-top">
                        <div class="disc-logo"><?php if (!empty($r['logo'])): ?><img src="<?= e($r['logo']) ?>" alt="" loading="lazy"><?php else: ?><?= e(strtoupper(mb_substr($r['name'], 0, 1))) ?><?php endif; ?></div>
                        <div style="min-width:0;flex:1">
                            <strong style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($r['name']) ?></strong>
                            <span class="muted small"><?= e($r['operating_system'] ?: '—') ?></span>
                        </div>
                        <span class="chip <?= $r['status'] === 'published' ? 'chip-soft' : '' ?> small"><?= e($r['status']) ?></span>
                    </div>
                    <p class="muted small disc-desc"><?= e(str_excerpt($r['short_description'] ?? '', 90)) ?></p>
                    <div class="disc-actions">
                        <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software/' . $r['id'] . '/edit')) ?>">Edit</a>
                        <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/software/' . $r['slug'])) ?>" target="_blank" rel="noopener">View ↗</a>
                        <span class="muted small" style="margin-left:auto"><?= e(time_ago($r['created_at'])) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<style>
.disc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px}
.disc-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:8px}
.disc-top{display:flex;align-items:center;gap:10px}
.disc-logo{width:40px;height:40px;border-radius:9px;background:var(--surface-2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--muted);overflow:hidden;flex:0 0 auto}
.disc-logo img{width:40px;height:40px;object-fit:contain}
.disc-desc{margin:0;min-height:2.4em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.disc-actions{display:flex;align-items:center;gap:8px;margin-top:auto}
.disc-st{margin-left:auto}
</style>

<script>
(function () {
    var f = document.getElementById('dform');
    var ci = f ? f.querySelector('input[name="_csrf"]') : null;
    var C_NAME = ci ? ci.name : '_csrf', C_VAL = ci ? ci.value : '';
    var URL_PUBLISH = <?= json_encode(base_url('/admin/software/publish-one')) ?>;
    document.querySelectorAll('.disc-pub').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.disc-card');
            var name = card.getAttribute('data-name');
            var url = card.getAttribute('data-url') || '';
            var st = card.querySelector('.disc-st');
            btn.disabled = true; btn.textContent = '⏳'; st.textContent = 'Publishing…';
            var body = new URLSearchParams();
            body.append(C_NAME, C_VAL); body.append('name', name); if (url) body.append('url', url);
            fetch(URL_PUBLISH, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.status === 'published' && res.id) {
                        st.innerHTML = '✅ <a href="<?= e(base_url('/admin/software/')) ?>' + res.id + '/edit">Published</a>';
                        btn.textContent = '✓ Done';
                        card.style.opacity = '.7';
                    } else if (res.status === 'duplicate') {
                        st.textContent = 'Already on site'; btn.textContent = '✓';
                    } else {
                        st.textContent = 'No official data'; btn.disabled = false; btn.textContent = '⬇ Publish';
                    }
                })
                .catch(function () { st.textContent = 'Failed'; btn.disabled = false; btn.textContent = '⬇ Publish'; });
        });
    });
})();
</script>
