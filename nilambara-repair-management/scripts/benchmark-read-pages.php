<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('This benchmark is restricted to the local application database.');
config(['session.driver' => 'array']);
$actor = App\Models\User::role('administrator')->where('is_active', true)->first();
if (!$actor || !$actor->hasActiveAccount()) throw new RuntimeException('An active local administrator is required for the read-only benchmark.');
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$metrics = [];
$capture = false; $queries = [];
Illuminate\Support\Facades\DB::listen(function ($query) use (&$capture, &$queries) { if ($capture) $queries[] = ['sql' => $query->sql, 'ms' => $query->time]; });
foreach (['/dashboard','/repair-jobs','/customers','/invoices','/payments','/repair-jobs/create'] as $path) {
    $times = []; $counts = []; $database = []; $sql = [];
    for ($run = 0; $run < 5; $run++) {
        Illuminate\Support\Facades\Auth::guard('web')->setUser($actor->fresh());
        $request = Illuminate\Http\Request::create('http://localhost:8000'.$path, 'GET');
        $queries = []; $capture = true; $start = hrtime(true);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $elapsed = (hrtime(true) - $start) / 1e6; $capture = false;
        if ($response->getStatusCode() !== 200) throw new RuntimeException($path.' returned '.$response->getStatusCode());
        if ($run > 0) { $times[] = $elapsed; $counts[] = count($queries); $database[] = array_sum(array_column($queries, 'ms')); }
        $sql = $queries;
    }
    sort($times); sort($counts); sort($database);
    $frequencies = array_count_values(array_column($sql, 'sql')); arsort($frequencies);
    $metrics[$path] = ['render_median_ms' => round(($times[1]+$times[2])/2,2), 'queries' => $counts[2], 'database_median_ms' => round(($database[1]+$database[2])/2,2), 'repeated_sql' => array_slice($frequencies,0,3,true)];
}
$directory = __DIR__.'/../storage/framework/testing/performance';
if (!is_dir($directory)) mkdir($directory, 0700, true);
$phase = ($argv[1] ?? 'before') === 'after' ? 'after' : 'before';
file_put_contents($directory.'/'.$phase.'.json', json_encode($metrics,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
foreach ($metrics as $path=>$metric) { unset($metric['repeated_sql']); echo $path.' '.json_encode($metric),PHP_EOL; }
echo 'PHP OPcache loaded: '.(extension_loaded('Zend OPcache') ? 'yes' : 'no').'; CLI enabled: '.ini_get('opcache.enable_cli'),PHP_EOL;
