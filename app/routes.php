<?php

declare(strict_types=1);

use App\Controllers\AlternativesController;
use App\Controllers\CategoryController;
use App\Controllers\CompareController;
use App\Controllers\DownloadController;
use App\Controllers\FinderController;
use App\Controllers\HomeController;
use App\Controllers\OsController;
use App\Controllers\PageController;
use App\Controllers\SearchController;
use App\Controllers\SoftwareController;
use App\Controllers\SystemController;
use App\Controllers\WebhookController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\AutomationController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\ReviewController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\SoftwareAdminController;
use App\Controllers\Admin\SourceController;
use App\Core\Router;

/** @var Router $router */

// --- Public site -------------------------------------------------------------
$router->get('/', [HomeController::class, 'index']);

$router->get('/software', [SoftwareController::class, 'index']);
$router->get('/software/{slug}', [SoftwareController::class, 'show']);

$router->get('/categories', [CategoryController::class, 'index']);
$router->get('/category/{slug}', [CategoryController::class, 'show']);

$router->get('/os/{slug}', [OsController::class, 'show']);

$router->get('/search', [SearchController::class, 'index']);
$router->get('/api/suggest', [SearchController::class, 'suggest']);

$router->get('/software-finder', [FinderController::class, 'index']);
$router->post('/software-finder', [FinderController::class, 'match']);

$router->get('/compare', [CompareController::class, 'index']);
$router->get('/compare/{slug}', [CompareController::class, 'show']);

$router->get('/alternatives/{slug}', [AlternativesController::class, 'show']);

$router->get('/new-software', [PageController::class, 'newSoftware']);
$router->get('/software-updates', [PageController::class, 'updates']);
$router->get('/low-end-pc', [PageController::class, 'lowEndPc']);

$router->get('/download/{slug}', [DownloadController::class, 'go']);

// --- System ------------------------------------------------------------------
$router->get('/sitemap.xml', [SystemController::class, 'sitemap']);
$router->get('/sitemaps/{name}.xml', [SystemController::class, 'sitemapSegment']);
$router->get('/robots.txt', [SystemController::class, 'robots']);
$router->post('/webhooks/github', [WebhookController::class, 'github']);

// --- Admin -------------------------------------------------------------------
$router->get('/admin/login', [AuthController::class, 'showLogin']);
$router->post('/admin/login', [AuthController::class, 'login']);
$router->post('/admin/logout', [AuthController::class, 'logout']);

$router->get('/admin', [DashboardController::class, 'index']);
$router->get('/admin/notifications', [DashboardController::class, 'notifications']);

$router->get('/admin/platform', [\App\Controllers\Admin\PlatformController::class, 'chooser']);
$router->post('/admin/platform/reset', [\App\Controllers\Admin\PlatformController::class, 'wipe']);
$router->get('/admin/platform/{slug}', [\App\Controllers\Admin\PlatformController::class, 'select']);

$router->get('/admin/bulk-import', [\App\Controllers\Admin\BulkImportController::class, 'index']);
$router->post('/admin/bulk-import/start', [\App\Controllers\Admin\BulkImportController::class, 'start']);
$router->post('/admin/bulk-import/toggle', [\App\Controllers\Admin\BulkImportController::class, 'toggle']);
$router->post('/admin/bulk-import/catalog', [\App\Controllers\Admin\BulkImportController::class, 'catalog']);
$router->post('/admin/bulk-import/catalog-all', [\App\Controllers\Admin\BulkImportController::class, 'catalogAll']);
$router->post('/admin/bulk-import/dedupe', [\App\Controllers\Admin\BulkImportController::class, 'dedupe']);

$router->get('/admin/ai', [\App\Controllers\Admin\AiController::class, 'index']);
$router->post('/admin/ai/start', [\App\Controllers\Admin\AiController::class, 'start']);

$router->get('/admin/software', [SoftwareAdminController::class, 'index']);
$router->get('/admin/software/lookup', [SoftwareAdminController::class, 'lookup']);
$router->get('/admin/software/ai-fill', [SoftwareAdminController::class, 'aiFill']);
$router->get('/admin/software/import-url', [SoftwareAdminController::class, 'importUrl']);
$router->get('/admin/software/suggest', [SoftwareAdminController::class, 'suggest']);
$router->get('/admin/software/bulk', [SoftwareAdminController::class, 'bulk']);
$router->post('/admin/software/publish-one', [SoftwareAdminController::class, 'publishOne']);
$router->post('/admin/software/queue', [SoftwareAdminController::class, 'queueBackground']);
$router->post('/admin/software/pack', [SoftwareAdminController::class, 'pack']);
$router->post('/admin/software/ai-category', [SoftwareAdminController::class, 'aiCategory']);
$router->post('/admin/software/trending', [SoftwareAdminController::class, 'trending']);
$router->post('/admin/software/category', [SoftwareAdminController::class, 'addCategory']);
$router->get('/admin/publishing', [SoftwareAdminController::class, 'dashboard']);
$router->get('/admin/software/new', [SoftwareAdminController::class, 'create']);
$router->post('/admin/software/new', [SoftwareAdminController::class, 'store']);
$router->post('/admin/software/quick', [SoftwareAdminController::class, 'quickPublish']);
$router->get('/admin/software/{id}/edit', [SoftwareAdminController::class, 'edit']);
$router->post('/admin/software/{id}/edit', [SoftwareAdminController::class, 'update']);
$router->post('/admin/software/{id}/enhance', [\App\Controllers\Admin\AiController::class, 'enhanceOne']);
$router->post('/admin/software/{id}/action', [SoftwareAdminController::class, 'action']);

$router->get('/admin/sources', [SourceController::class, 'index']);
$router->get('/admin/sources/new', [SourceController::class, 'create']);
$router->post('/admin/sources/new', [SourceController::class, 'save']);
$router->get('/admin/sources/{id}/edit', [SourceController::class, 'edit']);
$router->post('/admin/sources/{id}/edit', [SourceController::class, 'save']);
$router->post('/admin/sources/{id}/run', [SourceController::class, 'run']);
$router->post('/admin/sources/{id}/delete', [SourceController::class, 'delete']);

$router->get('/admin/automation', [AutomationController::class, 'index']);
$router->post('/admin/automation/run', [AutomationController::class, 'run']);
$router->post('/admin/automation/toggle', [AutomationController::class, 'toggle']);

$router->get('/admin/review', [ReviewController::class, 'index']);
$router->post('/admin/review/duplicate/{id}', [ReviewController::class, 'resolveDuplicate']);

$router->get('/admin/settings', [SettingsController::class, 'index']);
$router->post('/admin/settings', [SettingsController::class, 'save']);
