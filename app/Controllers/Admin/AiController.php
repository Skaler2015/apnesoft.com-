<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Csrf;
use App\Core\Session;
use App\Services\AiEnhancer;

/**
 * AI content enrichment. Uses the configured Anthropic API key to rewrite a
 * software page's description / features from its real metadata — never
 * inventing facts. Available per-software and as a controlled batch.
 */
final class AiController extends AdminController
{
    /** GET /admin/ai — overview + batch runner. */
    public function index(array $args = []): never
    {
        $this->requirePermission('software.manage');

        $running = $this->request->query('run') === '1';
        $result = null;
        if ($running && AiEnhancer::isConfigured()) {
            $result = AiEnhancer::enhanceBatch(8);
        }

        $pending = AiEnhancer::pendingCount();
        $finished = $running && ($result === null || $result['enhanced'] === 0 || $pending === 0);

        $this->render('admin/ai', [
            'title'      => 'AI Enhancer',
            'configured' => AiEnhancer::isConfigured(),
            'running'    => $running,
            'result'     => $result,
            'pending'    => $pending,
            'enhanced'   => AiEnhancer::enhancedCount(),
            'model'      => AiEnhancer::model(),
            'finished'   => $finished,
        ]);
    }

    /** POST /admin/ai/start — begin the auto-refreshing batch run. */
    public function start(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        if (!AiEnhancer::isConfigured()) {
            Session::flash('err', 'Add your Anthropic API key in Settings first.');
            $this->redirect(base_url('/admin/ai'));
        }
        $this->audit('ai.batch.start');
        $this->redirect(base_url('/admin/ai?run=1'));
    }

    /** POST /admin/software/{id}/enhance — enhance one item, from its edit page. */
    public function enhanceOne(array $args = []): never
    {
        $this->requirePermission('software.manage');
        Csrf::check($this->request);
        $id = (int) ($args['id'] ?? 0);

        if (!AiEnhancer::isConfigured()) {
            Session::flash('err', 'Add your Anthropic API key in Settings first.');
            $this->redirect(base_url('/admin/software/' . $id . '/edit'));
        }

        $res = AiEnhancer::enhance($id);
        $this->audit('ai.enhance', 'software', $id);
        Session::flash($res['ok'] ? 'ok' : 'err',
            $res['ok'] ? '✨ ' . $res['message'] : $res['message']);
        $this->redirect(base_url('/admin/software/' . $id . '/edit'));
    }
}
