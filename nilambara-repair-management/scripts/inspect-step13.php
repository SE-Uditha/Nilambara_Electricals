<?php
// Read-only operational metadata; no usernames, secrets or business record values.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes = [];
foreach (Illuminate\Support\Facades\Route::getRoutes() as $route) {
    $routes[] = ['name' => $route->getName(), 'uri' => $route->uri(), 'methods' => $route->methods(), 'action' => $route->getActionName(), 'middleware' => $route->gatherMiddleware()];
}
$roles = [];
foreach (App\Modules\RolesPermissions\Models\Role::with('permissions')->get() as $role) {
    $roles[$role->name] = $role->permissions->pluck('name')->sort()->values()->all();
}
$result = ['captured_at' => date(DATE_ATOM), 'php' => PHP_VERSION, 'laravel' => app()->version(),
    'server' => Illuminate\Support\Facades\DB::selectOne('SELECT VERSION() AS v')->v,
    'database' => Illuminate\Support\Facades\DB::getDatabaseName(),
    'routes' => $routes, 'roles' => $roles,
    'individual_override_count' => Illuminate\Support\Facades\DB::table('permission_user')->count(),
    'session_lifetime_minutes' => config('session.lifetime'),
    'sinhala_labels' => ['dashboard' => __('ui.dashboard', [], 'si'), 'customers' => __('ui.customers', [], 'si')],
    'backup' => ['time' => config('backup.schedule_time'), 'timezone' => config('backup.timezone'), 'retention_days' => config('backup.retention_days'), 'maximum_backups' => config('backup.maximum_backups'), 'destination_configured' => (bool)config('backup.root'), 'drive_configured' => (bool)config('backup.google_drive.folder_id')],
    'business' => ['name' => config('business.name'), 'currency' => config('business.currency'), 'timezone' => config('business.timezone'), 'logo' => config('business.logo')],
    'job_statuses' => array_column(App\Enums\JobStatus::cases(), 'value'),
    'models' => [], 'backup_config_source' => file_exists(config_path('backup.php')) ? 'config/backup.php' : 'config/backups.php'];
foreach (glob(app_path('Modules/*/Models/*.php')) as $path) {
    $result['models'][] = str_replace('\\', '/', substr($path, strlen(base_path())+1));
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
