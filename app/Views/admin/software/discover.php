<?php use App\Core\Csrf;
/** @var array $candidates @var array $recent @var array $cats @var array $stats
 *  @var string $platformSlug @var ?string $panelLabel @var bool $aiReady */
$today = gmdate('Y-m-d');
?>
<div class="admin-edit-head">
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/platform')) ?>">← Platforms</a>
    <h2 style="margin:0 0 0 4px">🧭 Discover<?= $panelLabel ? ' — ' . e($panelLabel) : '' ?></h2>
    <span style="flex:1"></span>
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software/bulk')) ?>">📦 Ready-made packs</a>
</div>

<?php if ($panelLabel): ?>
    <div class="flash flash-ok" style="margin:0 0 12px">📌 Everything here publishes to your <strong><?= e($panelLabel) ?></strong> site.</div>
<?php else: ?>
    <div class="flash" style="background:var(--surface-2);border:1px solid var(--border);margin:0 0 12px">Open a platform panel (Windows / Mac / iOS / Android) to see apps for that platform only.</div>
<?php endif; ?>

<form id="dform" style="display:none"><?= Csrf::field() ?></form>
<form id="qform" method="post" action="<?= e(base_url('/admin/software/queue')) ?>" style="display:none"><?= Csrf::field() ?><input type="hidden" name="names" id="qnames"></form>

<!-- F15: Progress -->
<div class="disc-stats">
    <div class="stat"><b><?= (int) $stats['total'] ?></b><span>कुल publish</span></div>
    <div class="stat"><b>+<?= (int) $stats['week'] ?></b><span>इस हफ़्ते</span></div>
    <div class="stat"><b>+<?= (int) $stats['today'] ?></b><span>आज</span></div>
    <div class="stat"><b><?= (int) $stats['available'] ?></b><span>जोड़ने को तैयार</span></div>
</div>

<!-- F1: Search + options -->
<div class="disc-toolbar">
    <div class="disc-search">
        <span>🔎</span>
        <input id="d-search" placeholder="कोई भी software खोजें या नीचे की सूची फ़िल्टर करें…" autocomplete="off">
        <button type="button" id="d-search-btn" class="btn btn-sm btn-primary">वेब से खोजें</button>
    </div>
    <div class="disc-opts">
        <label class="opt" title="AI से भरपूर description (key ज़रूरी)">
            <input type="checkbox" id="opt-ai" <?= $aiReady ? 'checked' : '' ?> <?= $aiReady ? '' : 'disabled' ?>>
            ✨ AI description<?= $aiReady ? '' : ' (key नहीं)' ?>
        </label>
        <label class="opt" title="publish होते ही website का screenshot">
            <input type="checkbox" id="opt-shot"> 📸 Auto-screenshot
        </label>
    </div>
</div>
<div id="d-search-result"></div>

<!-- F6 / F7: Filters -->
<div class="disc-filters">
    <div class="fchips" id="cat-chips">
        <button type="button" class="fchip on" data-cat="">सभी</button>
        <?php foreach ($cats as $cslug => $cname): ?>
            <button type="button" class="fchip" data-cat="<?= e($cslug) ?>"><?= e($cname) ?></button>
        <?php endforeach; ?>
    </div>
    <div class="fchips" id="price-chips">
        <button type="button" class="fchip io on" data-price="">सभी दाम</button>
        <button type="button" class="fchip io" data-price="free">Free</button>
        <button type="button" class="fchip io" data-price="open_source">Open-source</button>
        <button type="button" class="fchip io" data-price="paid">Paid</button>
    </div>
</div>

<!-- Candidates -->
<section style="margin-top:8px">
    <h3 style="margin:0 0 4px">✨ Ready to publish <span class="muted small">(<span id="vis-count"><?= count($candidates) ?></span> apps)</span></h3>
    <p class="muted small" style="margin:0 0 12px">Tick कई apps → नीचे की पट्टी से एक साथ publish/queue. या किसी एक पर सीधे Publish.</p>

    <?php if (empty($candidates)): ?>
        <div class="flash" style="background:var(--surface-2);border:1px solid var(--border)">🎉 इस platform के सारे popular apps पहले से publish हैं. ऊपर <strong>वेब से खोजें</strong> या <a href="<?= e(base_url('/admin/software/new')) ?>">Add Software</a> इस्तेमाल करें.</div>
    <?php else: ?>
        <div class="disc-grid" id="cand-grid">
            <?php foreach ($candidates as $c): ?>
                <div class="disc-card cand" data-name="<?= e($c['name']) ?>" data-url="<?= e($c['website']) ?>"
                     data-cat="<?= e($c['cat_slug']) ?>" data-price="<?= e($c['price']) ?>" data-cross="<?= (int) $c['cross'] ?>"
                     data-search="<?= e(mb_strtolower($c['name'] . ' ' . $c['developer'] . ' ' . $c['cat_name'])) ?>">
                    <span class="c-check" title="चुनें">✓</span>
                    <span class="badge b-new">नया</span>
                    <div class="disc-top" style="margin-top:14px">
                        <div class="disc-logo"><?php if ($c['logo']): ?><img src="<?= e($c['logo']) ?>" alt="" loading="lazy"><?php else: ?><?= e(strtoupper(mb_substr($c['name'], 0, 1))) ?><?php endif; ?></div>
                        <div style="min-width:0;flex:1">
                            <strong style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($c['name']) ?></strong>
                            <span class="muted small"><?= e($c['developer'] ?: $c['cat_name']) ?></span>
                        </div>
                        <?php if ($c['price']): ?><span class="chip chip-soft small"><?= e(str_replace('_', ' ', $c['price'])) ?></span><?php endif; ?>
                    </div>
                    <p class="muted small disc-desc"><?= e($c['desc']) ?></p>
                    <div class="disc-actions">
                        <button type="button" class="btn btn-sm btn-primary c-draft" title="draft तैयार करें — बाद में review करके publish करें">📝 तैयार करें</button>
                        <button type="button" class="btn btn-sm btn-ghost c-pub" title="अभी live publish करें">⚡ अभी</button>
                        <button type="button" class="btn btn-sm btn-ghost c-prev" title="publish से पहले देखें">👁</button>
                        <?php if ($c['cross']): ?><button type="button" class="btn btn-sm btn-ghost c-all" title="चारों platforms पर live">🌐 All 4</button><?php endif; ?>
                        <span class="c-st muted small"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div id="no-vis" class="muted" style="display:none;padding:20px;text-align:center">इस filter में कोई app नहीं — filter बदलें.</div>
    <?php endif; ?>
</section>

<!-- F16: Import your own list -->
<section style="margin-top:26px">
    <details class="disc-import">
        <summary>📋 अपनी सूची / URL से import करें</summary>
        <p class="muted small" style="margin:8px 0">हर लाइन में एक — software का नाम या official URL. फिर एक साथ publish या background queue.</p>
        <textarea id="imp-text" rows="5" placeholder="Notepad++&#10;https://www.eventlogxp.com/&#10;7-Zip"></textarea>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center">
            <button type="button" id="imp-pub" class="btn btn-sm btn-primary">⬇ सब publish करें</button>
            <button type="button" id="imp-queue" class="btn btn-sm btn-ghost">🕒 Background queue</button>
            <label class="btn btn-sm btn-ghost" style="cursor:pointer">📄 CSV<input type="file" id="imp-csv" accept=".csv,text/csv" hidden></label>
            <span id="imp-st" class="muted small"></span>
        </div>
        <div id="imp-prog" style="display:flex;flex-direction:column;gap:6px;margin-top:10px"></div>
    </details>
</section>

<!-- Prepared drafts — review then publish -->
<section style="margin-top:30px" id="drafts-sec">
    <h3 style="margin:0 0 4px">📝 तैयार posts — review करके publish करें <span class="muted small">(<?= count($drafts) ?>)</span></h3>
    <p class="muted small" style="margin:0 0 12px">ये draft में हैं (साइट पर live नहीं). Edit से जाँचें, फिर <strong>✅ Publish</strong> दबाएँ.</p>
    <?php if (empty($drafts)): ?>
        <p class="muted small" id="no-drafts">अभी कोई draft नहीं — ऊपर किसी app पर <strong>📝 तैयार करें</strong> दबाएँ.</p>
    <?php endif; ?>
    <div class="disc-grid" id="drafts-grid">
        <?php foreach ($drafts as $r): ?>
            <div class="disc-card draft-card" data-id="<?= (int) $r['id'] ?>">
                <span class="badge b-draft">DRAFT</span>
                <div class="disc-top" style="margin-top:12px">
                    <div class="disc-logo"><?php if (!empty($r['logo'])): ?><img src="<?= e($r['logo']) ?>" alt="" loading="lazy"><?php else: ?><?= e(strtoupper(mb_substr($r['name'], 0, 1))) ?><?php endif; ?></div>
                    <div style="min-width:0;flex:1">
                        <strong style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($r['name']) ?>
                            <?php if (!empty($r['version'])): ?><span class="ver">v<?= e($r['version']) ?></span><?php endif; ?>
                        </strong>
                        <span class="muted small"><?= e($r['operating_system'] ?: '—') ?></span>
                    </div>
                </div>
                <p class="muted small disc-desc"><?= e(str_excerpt($r['short_description'] ?? '', 90)) ?></p>
                <div class="disc-actions">
                    <button type="button" class="btn btn-sm btn-primary d-go">✅ Publish</button>
                    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software/' . $r['id'] . '/edit')) ?>">✎ Review</a>
                    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/software/' . $r['slug'])) ?>" target="_blank" rel="noopener">👁 Preview</a>
                    <span class="d-st muted small"></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- F13: Recently published (with version) -->
<section style="margin-top:30px">
    <h3 style="margin:0 0 12px">🆕 Recently published <span class="muted small"><?= $panelLabel ? 'on ' . e($panelLabel) : '(all platforms)' ?></span></h3>
    <?php if (empty($recent)): ?>
        <p class="muted">अभी इस platform पर कुछ नहीं — ऊपर से publish करें.</p>
    <?php else: ?>
        <div class="disc-grid">
            <?php foreach ($recent as $r): $isToday = str_starts_with((string) $r['created_at'], $today); ?>
                <div class="disc-card">
                    <?php if ($isToday): ?><span class="badge b-today">आज</span><?php endif; ?>
                    <div class="disc-top" style="<?= $isToday ? 'margin-top:12px' : '' ?>">
                        <div class="disc-logo"><?php if (!empty($r['logo'])): ?><img src="<?= e($r['logo']) ?>" alt="" loading="lazy"><?php else: ?><?= e(strtoupper(mb_substr($r['name'], 0, 1))) ?><?php endif; ?></div>
                        <div style="min-width:0;flex:1">
                            <strong style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($r['name']) ?>
                                <?php if (!empty($r['version'])): ?><span class="ver">v<?= e($r['version']) ?></span><?php endif; ?>
                            </strong>
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

<!-- F2/F3: sticky action bar -->
<div id="actionbar" class="disc-actionbar" style="display:none">
    <span class="c" id="sel-count">0 चुने</span>
    <span style="flex:1"></span>
    <button type="button" id="sel-clear" class="btn btn-sm btn-ghost">हटाएँ</button>
    <button type="button" id="sel-queue" class="btn btn-sm btn-ghost">🕒 Queue selected</button>
    <button type="button" id="sel-pub" class="btn btn-sm btn-ghost">⚡ Publish selected</button>
    <button type="button" id="sel-draft" class="btn btn-sm btn-primary">📝 तैयार करें</button>
</div>

<!-- F10: preview modal -->
<div id="prev-modal" class="disc-modal" style="display:none">
    <div class="disc-modal-box">
        <div class="disc-modal-head"><strong id="pv-title">Preview</strong><span style="flex:1"></span><button type="button" id="pv-close" class="btn btn-sm btn-ghost">✕</button></div>
        <img id="pv-shot" alt="" style="width:100%;height:230px;object-fit:cover;background:var(--surface-2);border-radius:10px">
        <p id="pv-desc" class="muted small" style="margin:10px 0"></p>
        <div style="display:flex;gap:8px">
            <button type="button" id="pv-pub" class="btn btn-primary">⬇ Publish</button>
            <a id="pv-site" class="btn btn-ghost" href="#" target="_blank" rel="noopener">Official site ↗</a>
        </div>
    </div>
</div>

<style>
.disc-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:4px 0 14px}
.disc-stats .stat{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:11px;text-align:center}
.disc-stats .stat b{display:block;font-size:1.4rem;font-weight:800}
.disc-stats .stat span{font-size:.68rem;color:var(--muted)}
.disc-toolbar{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.disc-search{flex:1;min-width:260px;display:flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:6px 10px}
.disc-search input{flex:1;border:0;background:transparent;color:var(--text);outline:none;font-size:.92rem;padding:5px}
.disc-opts{display:flex;gap:14px;flex-wrap:wrap}
.disc-opts .opt{display:flex;align-items:center;gap:6px;font-size:.84rem;color:var(--muted);cursor:pointer}
.disc-filters{display:flex;flex-direction:column;gap:8px;margin:6px 0 14px}
.fchips{display:flex;flex-wrap:wrap;gap:6px}
.fchip{font-size:.8rem;padding:5px 12px;border-radius:999px;border:1px solid var(--border);background:var(--surface);color:var(--muted);cursor:pointer}
.fchip:hover{border-color:var(--brand)}
.fchip.on{background:var(--brand);color:#fff;border-color:var(--brand)}
.fchip.io.on{background:#4d63e6;border-color:#4d63e6}
.disc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(255px,1fr));gap:12px}
.disc-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:12px 14px;display:flex;flex-direction:column;gap:9px;position:relative;transition:opacity .2s,box-shadow .2s}
.disc-card.sel{outline:2px solid var(--brand);outline-offset:1px}
.disc-card.pubbed{opacity:.6}
.disc-top{display:flex;align-items:center;gap:10px}
.disc-logo{width:40px;height:40px;border-radius:9px;background:var(--surface-2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-weight:800;color:var(--muted);overflow:hidden;flex:0 0 auto}
.disc-logo img{width:40px;height:40px;object-fit:contain}
.disc-desc{margin:0;min-height:2.4em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.disc-actions{display:flex;align-items:center;gap:6px;margin-top:auto;flex-wrap:wrap}
.c-st{margin-left:auto}
.ver{font-family:ui-monospace,monospace;font-size:.72rem;color:#4d63e6;font-weight:700;margin-left:3px}
.c-check{position:absolute;top:9px;right:10px;width:20px;height:20px;border-radius:6px;border:1.5px solid var(--border);background:var(--surface);display:flex;align-items:center;justify-content:center;color:transparent;font-size:.8rem;cursor:pointer;font-weight:800;z-index:2}
.disc-card.sel .c-check{background:var(--brand);border-color:var(--brand);color:#fff}
.badge{position:absolute;top:9px;left:10px;font-size:.6rem;font-weight:800;padding:2px 7px;border-radius:6px;font-family:ui-monospace,monospace;letter-spacing:.03em}
.b-new{background:#4d63e6;color:#fff}
.b-today{background:var(--brand);color:#fff}
.b-draft{background:#c67f10;color:#fff}
.disc-import summary{cursor:pointer;font-weight:600;padding:10px 12px;background:var(--surface);border:1px solid var(--border);border-radius:10px}
.disc-import textarea{width:100%;background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:10px;color:var(--text);font-family:ui-monospace,monospace;font-size:.86rem}
.disc-actionbar{position:sticky;bottom:14px;z-index:40;display:flex;align-items:center;gap:8px;background:var(--text);color:var(--surface);border-radius:12px;padding:10px 16px;box-shadow:0 12px 30px rgba(0,0,0,.35);margin-top:16px}
.disc-actionbar .c{font-weight:800}
.disc-modal{position:fixed;inset:0;background:rgba(10,12,20,.6);display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px}
.disc-modal-box{background:var(--surface);border:1px solid var(--border);border-radius:14px;max-width:560px;width:100%;padding:16px 18px;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.disc-modal-head{display:flex;align-items:center;gap:8px;margin-bottom:12px}
</style>

<script>
(function () {
    var f = document.getElementById('dform');
    var ci = f ? f.querySelector('input[name="_csrf"]') : null;
    var C_NAME = ci ? ci.name : '_csrf', C_VAL = ci ? ci.value : '';
    var URL_PUBLISH = <?= json_encode(base_url('/admin/software/publish-one')) ?>;
    var URL_SEARCH = <?= json_encode(base_url('/admin/software/discover-search')) ?>;
    var EDIT_BASE = <?= json_encode(base_url('/admin/software/')) ?>;

    function opts() {
        var ai = document.getElementById('opt-ai');
        var shot = document.getElementById('opt-shot');
        return { ai: (ai && ai.checked) ? '1' : '0', shot: (shot && shot.checked) ? '1' : '0' };
    }
    function publishReq(name, url, extra) {
        var o = opts();
        var body = new URLSearchParams();
        body.append(C_NAME, C_VAL);
        if (name) body.append('name', name);
        if (url) body.append('url', url);
        body.append('ai', o.ai); body.append('shot', o.shot);
        if (extra && extra.os) body.append('os', extra.os);
        if (extra && extra.draft) body.append('draft', '1');
        return fetch(URL_PUBLISH, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body }).then(function (r) { return r.json(); });
    }
    function stMsg(el, res) {
        if (res.status === 'published' && res.id) {
            if (res.draft) { el.innerHTML = '📝 <a href="' + EDIT_BASE + res.id + '/edit">तैयार — Review</a>'; return 'ok'; }
            el.innerHTML = '✅ <a href="' + EDIT_BASE + res.id + '/edit">Published' + (res.version ? ' v' + res.version : '') + '</a>';
            return 'ok';
        } else if (res.status === 'duplicate') { el.textContent = 'पहले से है'; return 'dup'; }
        el.textContent = 'official data नहीं मिला'; return 'miss';
    }

    // Add a freshly-prepared draft to the drafts section, with a one-click Publish.
    function addDraftCard(res, name, logoHtml) {
        var grid = document.getElementById('drafts-grid'); if (!grid) return;
        var nd = document.getElementById('no-drafts'); if (nd) nd.style.display = 'none';
        var el = document.createElement('div');
        el.className = 'disc-card draft-card'; el.setAttribute('data-id', res.id);
        el.innerHTML = '<span class="badge b-draft">DRAFT</span>'
            + '<div class="disc-top" style="margin-top:12px"><div class="disc-logo">' + (logoHtml || '') + '</div>'
            + '<div style="min-width:0;flex:1"><strong>' + name + (res.version ? ' <span class="ver">v' + res.version + '</span>' : '') + '</strong></div></div>'
            + '<div class="disc-actions"><button type="button" class="btn btn-sm btn-primary d-go">✅ Publish</button>'
            + '<a class="btn btn-sm btn-ghost" href="' + EDIT_BASE + res.id + '/edit">✎ Review</a>'
            + '<span class="d-st muted small"></span></div>';
        grid.insertBefore(el, grid.firstChild);
        wireDraft(el);
    }
    // One-click publish a prepared draft.
    function wireDraft(card) {
        var btn = card.querySelector('.d-go'); if (!btn) return;
        var st = card.querySelector('.d-st'), id = card.getAttribute('data-id');
        btn.addEventListener('click', function () {
            btn.disabled = true; btn.textContent = '⏳'; st.textContent = 'Publishing…';
            var body = new URLSearchParams(); body.append(C_NAME, C_VAL);
            fetch(EDIT_BASE + id + '/go-live', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.ok) { st.innerHTML = '✅ Live'; btn.textContent = '✓ Done'; card.classList.add('pubbed'); }
                    else { st.textContent = res.message || 'Failed'; btn.disabled = false; btn.textContent = '✅ Publish'; }
                }).catch(function () { st.textContent = 'Failed'; btn.disabled = false; btn.textContent = '✅ Publish'; });
        });
    }
    document.querySelectorAll('.draft-card').forEach(wireDraft);

    // ---- Card publish / preview / all-4 ----
    document.querySelectorAll('.cand').forEach(function (card) {
        var name = card.getAttribute('data-name');
        var url = card.getAttribute('data-url') || '';
        var st = card.querySelector('.c-st');
        var pub = card.querySelector('.c-pub');
        var draftBtn = card.querySelector('.c-draft');

        function doPub(btn, os, draft) {
            btn.disabled = true; var t = btn.textContent; btn.textContent = '⏳';
            st.textContent = draft ? 'तैयार कर रहे हैं…' : 'Publishing…';
            publishReq(name, url, { os: os, draft: draft }).then(function (res) {
                var r = stMsg(st, res);
                if (r === 'ok' || r === 'dup') {
                    card.classList.add('pubbed');
                    if (draftBtn) draftBtn.textContent = '✓'; pub.textContent = '✓';
                    if (draft && res.status === 'published' && res.id) {
                        var logo = card.querySelector('.disc-logo'); addDraftCard(res, name, logo ? logo.innerHTML : '');
                    }
                    suggestSimilar(card);
                } else { btn.disabled = false; btn.textContent = t; }
            }).catch(function () { st.textContent = 'Failed'; btn.disabled = false; btn.textContent = t; });
        }
        if (draftBtn) draftBtn.addEventListener('click', function () { doPub(draftBtn, '', true); });
        pub.addEventListener('click', function () { doPub(pub, '', false); });
        var all = card.querySelector('.c-all');
        if (all) all.addEventListener('click', function () { doPub(all, 'windows,macos,ios,android', false); });

        var prev = card.querySelector('.c-prev');
        if (prev) prev.addEventListener('click', function () { openPreview(card); });

        // select toggle (avoid when clicking buttons)
        card.querySelector('.c-check').addEventListener('click', function (e) { e.stopPropagation(); toggleSel(card); });
    });

    // ---- F17: suggest similar (same category) after a publish ----
    function suggestSimilar(card) {
        var cat = card.getAttribute('data-cat');
        var sims = [].slice.call(document.querySelectorAll('.cand[data-cat="' + cat + '"]'))
            .filter(function (c) { return c !== card && !c.classList.contains('pubbed'); }).slice(0, 3);
        if (!sims.length) return;
        var st = card.querySelector('.c-st');
        var wrap = document.createElement('div');
        wrap.style.cssText = 'flex-basis:100%;margin-top:6px;font-size:.76rem;color:var(--muted)';
        wrap.innerHTML = 'मिलते‑जुलते: ';
        sims.forEach(function (c) {
            var b = document.createElement('button');
            b.type = 'button'; b.className = 'btn btn-sm btn-ghost'; b.style.cssText = 'padding:1px 8px;margin:0 4px 4px 0';
            b.textContent = '＋ ' + c.getAttribute('data-name');
            b.addEventListener('click', function () { c.querySelector('.c-pub').click(); b.disabled = true; });
            wrap.appendChild(b);
        });
        card.querySelector('.disc-actions').appendChild(wrap);
    }

    // ---- F2/F3: selection + action bar ----
    var selected = new Set();
    var bar = document.getElementById('actionbar');
    function toggleSel(card) {
        var n = card.getAttribute('data-name');
        if (selected.has(n)) { selected.delete(n); card.classList.remove('sel'); }
        else { selected.add(n); card.classList.add('sel'); }
        document.getElementById('sel-count').textContent = selected.size + ' चुने';
        bar.style.display = selected.size ? 'flex' : 'none';
    }
    document.getElementById('sel-clear').addEventListener('click', function () {
        selected.clear(); document.querySelectorAll('.cand.sel').forEach(function (c) { c.classList.remove('sel'); }); bar.style.display = 'none';
    });
    function runSelected(btn, sel, label) {
        var cards = [].slice.call(document.querySelectorAll('.cand.sel'));
        if (!cards.length) return;
        btn.disabled = true;
        (function next(i) {
            if (i >= cards.length) { btn.disabled = false; btn.textContent = label; return; }
            btn.textContent = '⏳ ' + (i + 1) + '/' + cards.length;
            var b = cards[i].querySelector(sel); if (b) b.click();
            setTimeout(function () { next(i + 1); }, 1200);
        })(0);
    }
    document.getElementById('sel-pub').addEventListener('click', function () { runSelected(this, '.c-pub', '⚡ Publish selected'); });
    document.getElementById('sel-draft').addEventListener('click', function () { runSelected(this, '.c-draft', '📝 तैयार करें'); });
    document.getElementById('sel-queue').addEventListener('click', function () {
        if (!selected.size) return;
        document.getElementById('qnames').value = Array.from(selected).join('\n');
        document.getElementById('qform').submit();
    });

    // ---- F6/F7: filters + search box (client-side) ----
    var curCat = '', curPrice = '', curText = '';
    function applyFilters() {
        var vis = 0;
        document.querySelectorAll('.cand').forEach(function (c) {
            var ok = (!curCat || c.getAttribute('data-cat') === curCat)
                && (!curPrice || c.getAttribute('data-price') === curPrice)
                && (!curText || c.getAttribute('data-search').indexOf(curText) >= 0);
            c.style.display = ok ? '' : 'none';
            if (ok) vis++;
        });
        var vc = document.getElementById('vis-count'); if (vc) vc.textContent = vis;
        var nv = document.getElementById('no-vis'); if (nv) nv.style.display = vis ? 'none' : 'block';
    }
    document.querySelectorAll('#cat-chips .fchip').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('#cat-chips .fchip').forEach(function (x) { x.classList.remove('on'); });
            b.classList.add('on'); curCat = b.getAttribute('data-cat'); applyFilters();
        });
    });
    document.querySelectorAll('#price-chips .fchip').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('#price-chips .fchip').forEach(function (x) { x.classList.remove('on'); });
            b.classList.add('on'); curPrice = b.getAttribute('data-price'); applyFilters();
        });
    });
    var searchEl = document.getElementById('d-search');
    searchEl.addEventListener('input', function () { curText = this.value.trim().toLowerCase(); applyFilters(); });

    // ---- F1: web search (when not in the local list) ----
    document.getElementById('d-search-btn').addEventListener('click', function () {
        var q = searchEl.value.trim();
        var box = document.getElementById('d-search-result');
        if (q.length < 2) { box.innerHTML = ''; return; }
        box.innerHTML = '<div class="muted small" style="padding:8px">🔎 वेब पर खोज रहे हैं…</div>';
        fetch(URL_SEARCH + '?q=' + encodeURIComponent(q)).then(function (r) { return r.json(); }).then(function (res) {
            if (!res.ok) { box.innerHTML = '<div class="flash" style="background:var(--surface-2);border:1px solid var(--border)">' + (res.message || 'कुछ नहीं मिला') + '</div>'; return; }
            box.innerHTML = '';
            var card = document.createElement('div');
            card.className = 'disc-card'; card.style.maxWidth = '320px'; card.style.marginBottom = '12px';
            card.innerHTML = '<div class="disc-top"><div class="disc-logo">' + (res.logo ? '<img src="' + res.logo + '" alt="">' : (res.name[0] || '?').toUpperCase()) + '</div>'
                + '<div style="min-width:0;flex:1"><strong>' + res.name + '</strong><div class="muted small">' + (res.developer || '') + '</div></div>'
                + (res.published ? '<span class="chip chip-soft small">पहले से है</span>' : '') + '</div>'
                + '<p class="muted small disc-desc">' + (res.desc || '') + '</p>'
                + '<div class="disc-actions"><button type="button" class="btn btn-sm btn-primary sr-pub"' + (res.published ? ' disabled' : '') + '>⬇ Publish</button><span class="sr-st muted small"></span></div>';
            box.appendChild(card);
            var stt = card.querySelector('.sr-st'), pbb = card.querySelector('.sr-pub');
            if (pbb && !res.published) pbb.addEventListener('click', function () {
                pbb.disabled = true; pbb.textContent = '⏳'; stt.textContent = 'Publishing…';
                publishReq(res.name, res.website, {}).then(function (r2) { if (stMsg(stt, r2) === 'ok') pbb.textContent = '✓ Done'; else pbb.disabled = false, pbb.textContent = '⬇ Publish'; });
            });
        }).catch(function () { box.innerHTML = '<div class="muted small">खोज विफल.</div>'; });
    });
    searchEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('d-search-btn').click(); } });

    // ---- F10: preview modal ----
    var modal = document.getElementById('prev-modal');
    function openPreview(card) {
        var name = card.getAttribute('data-name'), url = card.getAttribute('data-url') || '';
        document.getElementById('pv-title').textContent = name;
        document.getElementById('pv-desc').textContent = card.querySelector('.disc-desc').textContent;
        document.getElementById('pv-site').href = url || '#';
        var shot = document.getElementById('pv-shot');
        shot.src = url ? ('https://s.wordpress.com/mshots/v1/' + encodeURIComponent(url) + '?w=1024') : '';
        var pub = document.getElementById('pv-pub');
        pub.onclick = function () { modal.style.display = 'none'; card.querySelector('.c-pub').click(); };
        modal.style.display = 'flex';
    }
    document.getElementById('pv-close').addEventListener('click', function () { modal.style.display = 'none'; });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });

    // ---- F16: import list ----
    var impProg = document.getElementById('imp-prog');
    function impRow(name) {
        var d = document.createElement('div');
        d.style.cssText = 'display:flex;gap:8px;align-items:center;font-size:.84rem;background:var(--surface-2);border:1px solid var(--border);border-radius:7px;padding:5px 10px';
        d.innerHTML = '<span style="flex:1"></span><span class="s">⏳</span>'; d.firstChild.textContent = name;
        impProg.appendChild(d); return d.querySelector('.s');
    }
    function impLines() {
        return (document.getElementById('imp-text').value || '').split(/\r\n|\r|\n/).map(function (s) { return s.trim(); }).filter(Boolean);
    }
    document.getElementById('imp-pub').addEventListener('click', function () {
        var lines = impLines(); if (!lines.length) { document.getElementById('imp-st').textContent = 'पहले कुछ नाम डालें.'; return; }
        var btn = this; btn.disabled = true; impProg.innerHTML = '';
        (function next(i) {
            if (i >= lines.length) { btn.disabled = false; document.getElementById('imp-st').textContent = '✓ हो गया.'; return; }
            document.getElementById('imp-st').textContent = (i + 1) + '/' + lines.length;
            var s = impRow(lines[i]);
            var isUrl = /^https?:\/\//i.test(lines[i]);
            publishReq(isUrl ? '' : lines[i], isUrl ? lines[i] : '', {}).then(function (r) {
                s.textContent = r.status === 'published' ? '✅' : (r.status === 'duplicate' ? '↺' : '—');
                next(i + 1);
            }).catch(function () { s.textContent = '✕'; next(i + 1); });
        })(0);
    });
    document.getElementById('imp-queue').addEventListener('click', function () {
        var lines = impLines(); if (!lines.length) return;
        document.getElementById('qnames').value = lines.join('\n'); document.getElementById('qform').submit();
    });
    document.getElementById('imp-csv').addEventListener('change', function () {
        var file = this.files[0]; if (!file) return; var r = new FileReader();
        r.onload = function () {
            var names = [];
            String(r.result).split(/\r\n|\r|\n/).forEach(function (ln) { var a = ln.split(',')[0].replace(/^"|"$/g, '').trim(); if (a && a.toLowerCase() !== 'name') names.push(a); });
            var t = document.getElementById('imp-text'); t.value = (t.value ? t.value + '\n' : '') + names.join('\n');
        };
        r.readAsText(file);
    });
})();
</script>
