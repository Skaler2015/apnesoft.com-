<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Crypto;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Models\Source;
use App\Services\DiscoveryEngine;

/**
 * Source Manager — add/manage discovery sources.
 */
final class SourceController extends AdminController
{
    public function index(array $args = []): never
    {
        $this->requirePermission('source.view');
        $this->render('admin/sources/index', [
            'title'   => 'Source Manager',
            'sources' => Source::all(),
        ]);
    }

    public function create(array $args = []): never
    {
        $this->requirePermission('source.manage');
        $this->render('admin/sources/edit', ['title' => 'Add Source', 'source' => null]);
    }

    public function edit(array $args): never
    {
        $this->requirePermission('source.manage');
        $source = Source::find((int) ($args['id'] ?? 0));
        $this->render('admin/sources/edit', ['title' => 'Edit Source', 'source' => $source]);
    }

    public function save(array $args = []): never
    {
        $this->requirePermission('source.manage');
        Csrf::check($this->request);

        $id = (int) ($args['id'] ?? 0);
        $data = [
            'name'            => $this->request->str('name'),
            'source_type'     => $this->request->str('source_type'),
            'source_url'      => $this->request->str('source_url') ?: null,
            'api_url'         => $this->request->str('api_url') ?: null,
            'auth_method'     => $this->request->str('auth_method') ?: 'none',
            'config'          => $this->request->str('config') ?: null,
            'status'          => $this->request->str('status', 'active'),
            'priority'        => $this->request->int('priority', 5),
            'trust_score'     => $this->request->int('trust_score', 50),
            'crawl_frequency' => $this->request->int('crawl_frequency', 1440),
        ];

        // Validate config JSON if provided.
        if ($data['config'] !== null && json_decode($data['config']) === null && json_last_error() !== JSON_ERROR_NONE) {
            Session::flash('err', 'Config must be valid JSON.');
            $this->redirect(base_url('/admin/sources'));
        }

        // Encrypt API key at rest.
        $apiKey = (string) $this->request->input('api_key', '');
        if ($apiKey !== '') {
            $data['api_key_enc'] = Crypto::encrypt($apiKey);
        }

        if ($id > 0) {
            Database::update('software_sources', $data, ['id' => $id]);
            $this->audit('source.update', 'source', $id);
        } else {
            $id = Database::insert('software_sources', $data);
            $this->audit('source.create', 'source', $id);
        }

        Session::flash('ok', 'Source saved.');
        $this->redirect(base_url('/admin/sources'));
    }

    public function run(array $args): never
    {
        $this->requirePermission('automation.manage');
        Csrf::check($this->request);
        $source = Source::find((int) ($args['id'] ?? 0));
        if ($source !== null) {
            $stats = DiscoveryEngine::runSource($source);
            Source::markSynced((int) $source['id'], true);
            Session::flash('ok', "Synced: created {$stats['created']}, updated {$stats['updated']}, failed {$stats['failed']}.");
        }
        $this->redirect(base_url('/admin/sources'));
    }

    public function delete(array $args): never
    {
        $this->requirePermission('source.manage');
        Csrf::check($this->request);
        Database::delete('software_sources', ['id' => (int) ($args['id'] ?? 0)]);
        Session::flash('ok', 'Source deleted.');
        $this->redirect(base_url('/admin/sources'));
    }
}
