<?php use App\Core\Csrf; ?>
<div class="page-head"><div class="container">
    <h1>Software Finder</h1>
    <p class="muted">Answer a few questions and we'll rank the best matches for you — transparently.</p>
</div></div>
<div class="container finder-wrap">
    <form id="finder-form" class="finder-form-card" data-csrf="<?= e(Csrf::token()) ?>">
        <div class="finder-step">
            <label class="finder-label">What do you need?</label>
            <div class="finder-options" data-group="need">
                <?php
                $needs = ['pdf-tools'=>'PDF editing','video-editing'=>'Video editing','photo-editing'=>'Photo editing',
                    'screen-recording'=>'Screen recording','security'=>'Antivirus / Security','file-tools'=>'File compression',
                    'developer-tools'=>'Programming','office'=>'Office work','backup'=>'Backup','remote-tools'=>'Remote desktop',
                    'media-players'=>'Media playback','utilities'=>'Utilities'];
                foreach ($needs as $slug=>$label): ?>
                    <button type="button" class="finder-chip" data-name="need" data-value="<?= $slug ?>"><?= e($label) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="finder-grid">
            <div class="finder-field">
                <label>Operating System</label>
                <select name="os">
                    <option value="">Any</option>
                    <?php foreach ($operatingSystems as $os): ?>
                        <option value="<?= e($os['slug']) ?>"><?= e($os['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="finder-field">
                <label>RAM available</label>
                <select name="ram">
                    <option value="">Any</option>
                    <option value="2048">2 GB</option>
                    <option value="4096">4 GB</option>
                    <option value="8192">8 GB</option>
                    <option value="16384">16 GB+</option>
                </select>
            </div>
            <div class="finder-field">
                <label>Experience</label>
                <select name="level">
                    <option value="">Any</option>
                    <option value="beginner">Beginner</option>
                    <option value="professional">Professional</option>
                </select>
            </div>
        </div>

        <div class="finder-toggles">
            <label class="check"><input type="checkbox" name="free" value="1"> Free only</label>
            <label class="check"><input type="checkbox" name="open_source" value="1"> Open source only</label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">Find My Software</button>
    </form>

    <div id="finder-results" class="finder-results" hidden>
        <h2>Best matches for you</h2>
        <div class="card-grid" data-results-grid></div>
    </div>
</div>
