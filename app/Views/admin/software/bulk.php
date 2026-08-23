<?php use App\Core\Csrf; /** @var array $missing @var ?string $panelLabel */ ?>
<div class="admin-edit-head">
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software')) ?>">← Back</a>
    <h2 style="margin:0 0 0 4px">🚀 Bulk publish</h2>
</div>
<?php if (!empty($panelLabel)): ?>
    <div class="flash flash-ok" style="margin:6px 0 14px">📌 Everything here publishes to your <strong><?= e($panelLabel) ?></strong> site.</div>
<?php endif; ?>

<div class="admin-panel">
    <h3 style="margin:0 0 6px">Paste software names — one per line</h3>
    <p class="muted small" style="margin:0 0 10px">Each name is looked up on official catalogues, filled (with AI if your key is set), and published.
        You can also paste an <strong>official website URL</strong> on a line. Duplicates are skipped automatically.</p>

    <textarea id="names" rows="8" style="width:100%;font-family:monospace;font-size:.9rem"
              placeholder="VLC&#10;OBS Studio&#10;7-Zip&#10;Notepad++&#10;https://www.videolan.org/vlc/"></textarea>

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:12px">
        <button id="go" class="btn btn-primary">🚀 Publish all</button>
        <label class="btn btn-ghost btn-sm" style="cursor:pointer;margin:0">📊 Import CSV
            <input type="file" id="csv" accept=".csv,text/csv" hidden>
        </label>
        <span id="summary" class="muted small"></span>
    </div>

    <div id="progress" style="margin-top:14px;display:flex;flex-direction:column;gap:6px"></div>
</div>

<?php if (!empty($missing)): ?>
<div class="admin-panel">
    <h3 style="margin:0 0 6px">⭐ Popular apps not on your site yet</h3>
    <p class="muted small" style="margin:0 0 10px">Click to queue them above, then Publish all.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ($missing as $name): ?>
            <button type="button" class="btn btn-sm btn-ghost miss" data-name="<?= e($name) ?>">+ <?= e($name) ?></button>
        <?php endforeach; ?>
        <button type="button" id="addAll" class="btn btn-sm btn-primary">+ Add all</button>
    </div>
</div>
<?php endif; ?>

<span id="csrf" hidden><?= Csrf::field() ?></span>

<script>
(function () {
    var namesEl = document.getElementById('names');
    var go = document.getElementById('go');
    var prog = document.getElementById('progress');
    var summary = document.getElementById('summary');
    var csrfInput = document.querySelector('#csrf input');
    var CSRF_NAME = csrfInput ? csrfInput.name : '_csrf';
    var CSRF_VAL = csrfInput ? csrfInput.value : '';
    var URL_PUBLISH = <?= json_encode(base_url('/admin/software/publish-one')) ?>;

    // Queue popular apps into the textarea.
    function addName(n) {
        var cur = namesEl.value.trim();
        var lines = cur ? cur.split('\n') : [];
        if (lines.indexOf(n) === -1) { lines.push(n); namesEl.value = lines.join('\n'); }
    }
    document.querySelectorAll('.miss').forEach(function (b) {
        b.addEventListener('click', function () { addName(b.getAttribute('data-name')); b.disabled = true; b.textContent = '✓ ' + b.getAttribute('data-name'); });
    });
    var addAll = document.getElementById('addAll');
    if (addAll) addAll.addEventListener('click', function () {
        document.querySelectorAll('.miss').forEach(function (b) { if (!b.disabled) b.click(); });
    });

    // CSV: read first column of each row into the textarea.
    var csv = document.getElementById('csv');
    if (csv) csv.addEventListener('change', function () {
        var f = csv.files[0]; if (!f) return;
        var r = new FileReader();
        r.onload = function () {
            var lines = String(r.result).split(/\r\n|\r|\n/);
            lines.forEach(function (ln) {
                var first = ln.split(',')[0].replace(/^"|"$/g, '').trim();
                if (first && first.toLowerCase() !== 'name') addName(first);
            });
        };
        r.readAsText(f);
    });

    function row(name) {
        var d = document.createElement('div');
        d.style.cssText = 'display:flex;align-items:center;gap:10px;background:var(--surface-2);border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:.88rem';
        d.innerHTML = '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + name + '</span><span class="st" style="font-weight:700">⏳</span>';
        prog.appendChild(d);
        return d.querySelector('.st');
    }

    function publish(line) {
        var body = new URLSearchParams();
        body.append(CSRF_NAME, CSRF_VAL);
        if (/^https?:\/\//i.test(line)) body.append('url', line); else body.append('name', line);
        return fetch(URL_PUBLISH, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
            .then(function (r) { return r.json(); });
    }

    go.addEventListener('click', function () {
        var lines = namesEl.value.split(/\r\n|\r|\n/).map(function (s) { return s.trim(); }).filter(Boolean);
        if (!lines.length) { summary.textContent = 'Add some names first.'; return; }
        go.disabled = true; go.textContent = '⏳ Publishing…'; prog.innerHTML = '';
        var done = 0, ok = 0, dup = 0, miss = 0;

        (function next(i) {
            if (i >= lines.length) {
                go.disabled = false; go.textContent = '🚀 Publish all';
                summary.textContent = '✅ Done — ' + ok + ' published, ' + dup + ' already existed, ' + miss + ' not found.';
                return;
            }
            var st = row(lines[i]);
            st.textContent = '⏳';
            publish(lines[i]).then(function (res) {
                done++;
                if (res.status === 'published') { ok++; st.innerHTML = '<span style="color:var(--green)">✓ Published</span>'; }
                else if (res.status === 'duplicate') { dup++; st.innerHTML = '<span style="color:var(--muted)">already exists</span>'; }
                else if (res.status === 'notfound') { miss++; st.innerHTML = '<span style="color:var(--yellow)">not found</span>'; }
                else { st.innerHTML = '<span style="color:var(--red)">failed</span>'; }
                summary.textContent = done + ' / ' + lines.length + ' processed…';
                next(i + 1);
            }).catch(function () {
                st.innerHTML = '<span style="color:var(--red)">error</span>'; next(i + 1);
            });
        })(0);
    });
})();
</script>
