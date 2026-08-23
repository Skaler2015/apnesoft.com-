<?php use App\Core\Csrf; /** @var array $missing @var array $packs @var array $queue @var bool $aiReady @var ?string $panelLabel */ ?>
<div class="admin-edit-head">
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software')) ?>">← Back</a>
    <h2 style="margin:0 0 0 4px">🚀 Bulk publish</h2>
    <span style="flex:1"></span>
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/publishing')) ?>">📊 Dashboard</a>
</div>
<?php if (!empty($panelLabel)): ?>
    <div class="flash flash-ok" style="margin:6px 0 12px">📌 Everything here publishes to your <strong><?= e($panelLabel) ?></strong> site.</div>
<?php endif; ?>

<?php if (($queue['pending'] ?? 0) > 0): ?>
    <div class="flash" style="background:var(--surface-2);border:1px solid var(--border);margin-bottom:12px">
        ⚡ <strong><?= number_format($queue['pending']) ?></strong> software waiting in the background queue — the hourly cron is publishing them.
        (<?= number_format($queue['done'] ?? 0) ?> done, <?= number_format($queue['duplicate'] ?? 0) ?> duplicates)
    </div>
<?php endif; ?>

<div class="admin-panel">
    <h3 style="margin:0 0 6px">Paste software names — one per line</h3>
    <p class="muted small" style="margin:0 0 10px">Each name is looked up on official catalogues, filled (with AI if your key is set) and published.
        You can also paste an <strong>official website URL</strong> on a line. Duplicates are skipped automatically.</p>

    <form id="qform" method="post" action="<?= e(base_url('/admin/software/queue')) ?>">
        <?= Csrf::field() ?>
        <textarea id="names" name="names" rows="8" style="width:100%;font-family:monospace;font-size:.9rem"
                  placeholder="VLC&#10;OBS Studio&#10;7-Zip&#10;https://www.videolan.org/vlc/"></textarea>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:12px">
            <button type="button" id="go" class="btn btn-primary">▶ Publish now (watch progress)</button>
            <button type="submit" class="btn btn-ghost">⚡ Queue in background</button>
            <label class="btn btn-ghost btn-sm" style="cursor:pointer;margin:0">📊 Import CSV
                <input type="file" id="csv" accept=".csv,text/csv" hidden>
            </label>
            <span id="summary" class="muted small"></span>
        </div>
    </form>
    <p class="muted small" style="margin-top:8px"><strong>Publish now</strong> = watch it happen (keep tab open).
        <strong>Queue in background</strong> = close the tab, the cron publishes them for you.</p>
    <div id="progress" style="margin-top:14px;display:flex;flex-direction:column;gap:6px"></div>
</div>

<!-- AI category fill -->
<div class="admin-panel">
    <h3 style="margin:0 0 6px">🤖 AI category fill</h3>
    <p class="muted small" style="margin:0 0 10px">Describe what you want and AI lists real software (official links only), then queues them.
        <?php if (!$aiReady): ?><strong style="color:var(--red)">Needs your Anthropic API key in <a href="<?= e(base_url('/admin/settings')) ?>">Settings</a>.</strong><?php endif; ?></p>
    <form method="post" action="<?= e(base_url('/admin/software/ai-category')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <?= Csrf::field() ?>
        <input name="request" placeholder="e.g. 50 popular PDF tools" style="flex:1;min-width:220px" <?= $aiReady ? '' : 'disabled' ?>>
        <input name="count" type="number" value="30" min="5" max="100" style="width:90px" title="How many" <?= $aiReady ? '' : 'disabled' ?>>
        <button class="btn btn-primary" <?= $aiReady ? '' : 'disabled' ?>>✨ List &amp; queue</button>
    </form>
</div>

<!-- Trending -->
<div class="admin-panel">
    <h3 style="margin:0 0 6px">📈 Trending / new apps</h3>
    <p class="muted small" style="margin:0 0 10px">Queue popular apps that have been active recently (from GitHub) — published in the background.</p>
    <form method="post" action="<?= e(base_url('/admin/software/trending')) ?>" class="inline">
        <?= Csrf::field() ?>
        <button class="btn btn-sm btn-primary">📈 Queue trending apps</button>
    </form>
</div>

<!-- Ready-made packs -->
<?php if (!empty($packs)): ?>
<div class="admin-panel">
    <h3 style="margin:0 0 6px">🗂️ Ready-made packs</h3>
    <p class="muted small" style="margin:0 0 10px">One click queues a whole curated pack (published in the background).</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <?php foreach ($packs as $slug => $cnt): if ($cnt < 2) continue; ?>
            <form method="post" action="<?= e(base_url('/admin/software/pack')) ?>" class="inline">
                <?= Csrf::field() ?>
                <input type="hidden" name="pack" value="<?= e($slug) ?>">
                <button class="btn btn-sm btn-ghost"><?= e(ucwords(str_replace('-', ' ', (string) $slug))) ?> <span class="muted">(<?= (int) $cnt ?>)</span></button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Missing popular apps -->
<?php if (!empty($missing)): ?>
<div class="admin-panel">
    <h3 style="margin:0 0 6px">⭐ Popular apps not on your site yet</h3>
    <p class="muted small" style="margin:0 0 10px">Click to queue them above, then Publish now / Queue.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ($missing as $name): ?>
            <button type="button" class="btn btn-sm btn-ghost miss" data-name="<?= e($name) ?>">+ <?= e($name) ?></button>
        <?php endforeach; ?>
        <button type="button" id="addAll" class="btn btn-sm btn-primary">+ Add all</button>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var namesEl = document.getElementById('names');
    var go = document.getElementById('go');
    var prog = document.getElementById('progress');
    var summary = document.getElementById('summary');
    var csrfInput = document.querySelector('#qform input[name="_csrf"]');
    var CSRF_NAME = csrfInput ? csrfInput.name : '_csrf';
    var CSRF_VAL = csrfInput ? csrfInput.value : '';
    var URL_PUBLISH = <?= json_encode(base_url('/admin/software/publish-one')) ?>;

    function addName(n) {
        var cur = namesEl.value.trim();
        var lines = cur ? cur.split('\n') : [];
        if (lines.indexOf(n) === -1) { lines.push(n); namesEl.value = lines.join('\n'); }
    }
    document.querySelectorAll('.miss').forEach(function (b) {
        b.addEventListener('click', function () { addName(b.getAttribute('data-name')); b.disabled = true; b.textContent = '✓ ' + b.getAttribute('data-name'); });
    });
    var addAll = document.getElementById('addAll');
    if (addAll) addAll.addEventListener('click', function () { document.querySelectorAll('.miss').forEach(function (b) { if (!b.disabled) b.click(); }); });

    var csv = document.getElementById('csv');
    if (csv) csv.addEventListener('change', function () {
        var f = csv.files[0]; if (!f) return;
        var r = new FileReader();
        r.onload = function () {
            String(r.result).split(/\r\n|\r|\n/).forEach(function (ln) {
                var first = ln.split(',')[0].replace(/^"|"$/g, '').trim();
                if (first && first.toLowerCase() !== 'name') addName(first);
            });
        };
        r.readAsText(f);
    });

    function row(name) {
        var d = document.createElement('div');
        d.style.cssText = 'display:flex;align-items:center;gap:10px;background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:.88rem';
        d.innerHTML = '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span><span class="st" style="font-weight:700">⏳</span>';
        d.firstChild.textContent = name;
        prog.appendChild(d);
        return d.querySelector('.st');
    }
    function publish(line) {
        var body = new URLSearchParams();
        body.append(CSRF_NAME, CSRF_VAL);
        if (/^https?:\/\//i.test(line)) body.append('url', line); else body.append('name', line);
        return fetch(URL_PUBLISH, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body }).then(function (r) { return r.json(); });
    }
    go.addEventListener('click', function () {
        var lines = namesEl.value.split(/\r\n|\r|\n/).map(function (s) { return s.trim(); }).filter(Boolean);
        if (!lines.length) { summary.textContent = 'Add some names first.'; return; }
        go.disabled = true; go.textContent = '⏳ Publishing…'; prog.innerHTML = '';
        var done = 0, ok = 0, dup = 0, miss = 0;
        (function next(i) {
            if (i >= lines.length) { go.disabled = false; go.textContent = '▶ Publish now (watch progress)';
                summary.textContent = '✅ Done — ' + ok + ' published, ' + dup + ' already existed, ' + miss + ' not found.'; return; }
            var st = row(lines[i]);
            publish(lines[i]).then(function (res) {
                done++;
                if (res.status === 'published') { ok++; st.innerHTML = '<span style="color:var(--green)">✓ Published</span>'; }
                else if (res.status === 'duplicate') { dup++; st.innerHTML = '<span style="color:var(--muted)">already exists</span>'; }
                else if (res.status === 'notfound') { miss++; st.innerHTML = '<span style="color:var(--yellow)">not found</span>'; }
                else { st.innerHTML = '<span style="color:var(--red)">failed</span>'; }
                summary.textContent = done + ' / ' + lines.length + ' processed…';
                next(i + 1);
            }).catch(function () { st.innerHTML = '<span style="color:var(--red)">error</span>'; next(i + 1); });
        })(0);
    });
})();
</script>
