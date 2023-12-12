<?php

/**
 * @internal Super crude script to sync traffic data from GitHub to a local JSON database.
 *
 * @example php SyncTraffic.php owner/repo github_pat_1234567890 [--debug]
 *
 * @see https://docs.github.com/en/rest/metrics/traffic?apiVersion=2022-11-28
 */

echo "Syncing traffic data!\n";

// Run config

// Check if --debug is passed as an argument, if so, enable debug mode.
$debug = in_array('--debug', $argv);

// get first argument as the repo (owner/repo)
$repo = $argv[1] ?? 'null';
assert(str_contains($repo, '/'), 'Invalid repo');

// get second argument as the access token
$accessToken = $argv[2] ?? 'null';
assert(str_starts_with($accessToken, 'github_pat_'), 'Invalid access token');

$database = json_decode(file_get_contents('database.json'), true);

$syncTraffic = new SyncTraffic($database, $repo, $accessToken, $debug);
$database = $syncTraffic->fetch();

file_put_contents('database.json', json_encode($database, JSON_PRETTY_PRINT));

echo "Done!\n";

echo "All done!\n";

// Helpers

class SyncTraffic
{
    private array $database;

    private string $repo;

    private string $accessToken;

    private bool $debug;

    public function __construct(array $database, string $repo, string $accessToken, bool $debug = false)
    {
        $this->database = $database;
        $this->repo = $repo;
        $this->accessToken = $accessToken;
        $this->debug = $debug;
    }

    public function fetch(): array
    {
        return $this->fetchTraffic();
    }

    private function getResponse(string $endpoint): array
    {
        $name = match ($endpoint) {
            'clones' => 'clones',
            'views' => 'views',
            'popular/paths' => 'popular paths',
            'popular/referrers' => 'popular referrers',
            default => null,
        };
        assert($name !== null, 'Invalid endpoint');
        echo ' - Fetching '.$name.'... ';

        //curl - L \
        //  -H 'Accept: application/vnd.github+json' \
        //  -H 'Authorization: Bearer <YOUR-TOKEN>' \
        //  -H 'X-GitHub-Api-Version: 2022-11-28' \
        //https://api.github.com/repos/OWNER/REPO/traffic/clones

        $debug = $this->debug;
        $repo = $this->repo;
        $accessToken = $this->accessToken;

        $url = "https://api.github.com/repos/{$repo}/traffic/".$endpoint;

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
            throw new \Exception('Curl error: '.curl_error($ch));
        }

        // Check status code
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($statusCode >= 400) {
            throw new \Exception('Invalid status code: '.$statusCode."\n".$response);
        }

        curl_close($ch);

        // $response now contains the API response
        $data = json_decode($response, true);

        if (! is_array($data)) {
            throw new \Exception('Invalid response: '."\n".$response);
        }

        $padding = 20 - strlen($name);

        echo str_pad(' ', $padding)."Done!\n";

        if ($debug) {
            echo '<response>';
            print_r($data);
            echo '</response>';
        }

        return $data;
    }

    private function fetchTraffic(): array
    {
        $database = $this->database;
        $clones = $this->getResponse('clones');

        foreach ($clones['clones'] as $clone) {
            $database['traffic'][$clone['timestamp']]['clones'] = [
                'count' => $clone['count'],
                'uniques' => $clone['uniques'],
            ];
        }

        $views = $this->getResponse('views');

        foreach ($views['views'] as $view) {
            $database['traffic'][$view['timestamp']]['views'] = [
                'count' => $view['count'],
                'uniques' => $view['uniques'],
            ];
        }

        // We store these under each year-month, so we can have some sort of tracking over time.
        // The reason we do this monthly is because we can't get the data for a specific date, only the last 14 days,
        // and since we can't know which day some data is for, we don't know when it overlaps with the previous data if
        // we fetch data for the same day multiple times. Having it stored monthly means we get an average that is at least somewhat accurate.
        $popularDataKey = date('Y-m', (time()));

        $popularPaths = $this->getResponse('popular/paths');

        foreach ($popularPaths as $path) {
            // Since the paths are messy, we use a hash of the path as the key.
            $key = hash('sha256', $path['path']);

            $existing = $database['popular'][$popularDataKey]['paths'][$key] ?? [];
            $database['popular'][$popularDataKey]['paths'][$key] = [
                'path' => $path['path'],
                'title' => $path['title'],
                'count' => max($path['count'], $existing['count'] ?? 0),
                'uniques' => max($path['uniques'], $existing['uniques'] ?? 0),
            ];
        }

        $popularReferrers = $this->getResponse('popular/referrers');

        foreach ($popularReferrers as $referrer) {
            $existing = $database['popular'][$popularDataKey]['referrers'][$referrer['referrer']] ?? [];
            $database['popular'][$popularDataKey]['referrers'][$referrer['referrer']] = [
                'count' => max($referrer['count'], $existing['count'] ?? 0),
                'uniques' => max($referrer['uniques'], $existing['uniques'] ?? 0),
            ];
        }

        // Save the database
        echo 'Saving database... ';

        return $database;
    }
}
