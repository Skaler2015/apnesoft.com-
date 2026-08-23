<?php use App\Core\Csrf; /** @var array $cards */ ?>
<div class="plat-choose">
    <h1>Choose a platform to manage</h1>
    <p class="muted">All four share the same catalogue &amp; settings — a change in one panel applies everywhere.
        Pick a platform to manage its software.</p>

    <div class="plat-grid">
        <?php foreach ($cards as $slug => $c): ?>
            <a class="plat-card plat-<?= e($slug) ?>" href="<?= e(base_url('/admin/platform/' . $slug)) ?>">
                <span class="plat-ico"><?= $c['icon'] ?></span>
                <span class="plat-name"><?= e($c['label']) ?></span>
                <span class="plat-count"><?= number_format($c['count']) ?> software</span>
                <span class="plat-go">Open panel →</span>
            </a>
        <?php endforeach; ?>
    </div>

</div>

<style>
.plat-choose{max-width:880px;margin:10px auto;text-align:center}
.plat-choose h1{font-size:1.5rem;margin:0 0 6px}
.plat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:26px 0 34px}
.plat-card{display:flex;flex-direction:column;align-items:center;gap:6px;padding:26px 14px;
  background:var(--surface);border:1px solid var(--border);border-radius:16px;text-decoration:none;
  color:var(--text);transition:.15s;box-shadow:var(--shadow)}
.plat-card:hover{transform:translateY(-3px);border-color:var(--brand);box-shadow:0 10px 30px rgba(79,70,229,.18)}
.plat-ico{font-size:2.6rem;line-height:1}
.plat-name{font-size:1.15rem;font-weight:800}
.plat-count{font-size:.8rem;color:var(--muted)}
.plat-go{margin-top:8px;font-size:.8rem;font-weight:700;color:var(--brand)}
.plat-danger{border:1px dashed var(--red);border-radius:12px;padding:16px;margin-top:20px;text-align:left;background:color-mix(in srgb,var(--red) 6%,transparent)}
.plat-danger h3{margin:0 0 4px;font-size:1rem}
@media(max-width:760px){.plat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:420px){.plat-grid{grid-template-columns:1fr}}
</style>
