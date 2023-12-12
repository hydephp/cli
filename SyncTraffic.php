<?php

/**
 * @internal Super crude script to sync traffic data from GitHub to a local JSON database.
 * @example php SyncTraffic.php owner/repo github_pat_1234567890 [--debug]
 * @see https://docs.github.com/en/rest/metrics/traffic?apiVersion=2022-11-28
 */

// Check if --debug is passed as an argument, if so, enable debug mode.
$debug = in_array('--debug', $argv);

// get first argument as the repo (owner/repo)
$repo = $argv[1] ?? 'null';
assert(str_contains($repo, '/'), 'Invalid repo');

// get second argument as the access token
$accessToken = $argv[2] ?? 'null';
assert(str_starts_with($accessToken, 'github_pat_'), 'Invalid access token');

$database = json_decode(file_get_contents('database.json'), true);

function getResponse(string $endpoint): array
{
    //curl - L \
    //  -H 'Accept: application/vnd.github+json' \
    //  -H 'Authorization: Bearer <YOUR-TOKEN>' \
    //  -H 'X-GitHub-Api-Version: 2022-11-28' \
    //https://api.github.com/repos/OWNER/REPO/traffic/clones

    global $debug;
    global $repo;
    global $accessToken;

    $url = "https://api.github.com/repos/{$repo}/traffic/".  $endpoint;

    $userAgent = 'HydePHP Traffic Controller';

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github+json',
        "Authorization: Bearer {$accessToken}",
        'X-GitHub-Api-Version: 2022-11-28',
        "User-Agent: {$userAgent}",
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new \Exception('Curl error: ' . curl_error($ch));
    }

    // Check status code
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($statusCode >= 400) {
        throw new \Exception('Invalid status code: ' . $statusCode."\n" . $response);
    }

    curl_close($ch);

    // $response now contains the API response
    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new \Exception('Invalid response: '."\n" . $response);
    }

    if ($debug) {
        echo '<response>';
        print_r($data);
        echo '</response>';
    }

    return $data;
}

$clones = getResponse('clones');

foreach ($clones['clones'] as $clone) {
    $database['traffic'][$clone['timestamp']]['clones'] = [
        'count' => $clone['count'],
        'uniques' => $clone['uniques'],
    ];
}

$views = getResponse('views');

foreach ($views['views'] as $view) {
    $database['traffic'][$view['timestamp']]['views'] = [
        'count' => $view['count'],
        'uniques' => $view['uniques'],
    ];
}

$popularPaths = getResponse('popular/paths');

foreach ($popularPaths as $path) {
    $key = hash('sha256', $path['path']);
    $existing = $database['popularPaths'][$key] ?? [];

    // Get the interval the data is collected for. Key is the existing key, or today's date - 14 days justified to the start of that day, value is today's date, justified to the start of that day.
    // Todo store each interval as a separate entry in the interval array? Or we store the paths under a dated entry in the database?
    $interval = $existing['interval'] ?? [];
    $interval[key($interval) ?? date('Y-m-d', strtotime('-14 days', strtotime(date('Y-m-d'))))] = date('Y-m-d', strtotime(date('Y-m-d')));

    $database['popularPaths'][$key] = [
        'path' => $path['path'],
        'title' => $path['title'],
        'count' => max($path['count'], $existing['count'] ?? 0),
        'uniques' => max($path['uniques'], $existing['uniques'] ?? 0),
        'interval' => [key($interval) => current($interval)],
    ];
}


$popularReferrers = getResponse('popular/referrers');
// We store these under each year-month, so we can have some sort of tracking over time.
$referrerDateKey = date('Y-m', (time()));

foreach ($popularReferrers as $referrer) {
    $existing = $database['popularReferrers'][$referrerDateKey][$referrer['referrer']] ?? [];
    $database['popularReferrers'][$referrerDateKey][$referrer['referrer']] = [
        'count' => max($referrer['count'], $existing['count'] ?? 0),
        'uniques' => max($referrer['uniques'], $existing['uniques'] ?? 0)
    ];
}

file_put_contents('database.json', json_encode($database, JSON_PRETTY_PRINT));
