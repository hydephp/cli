<?php

/**
 * @internal Run configuration for traffic sync.
 *
 * @example php sync.php owner/repo github_pat_1234567890 [--debug]
 */

require_once __DIR__.'/SyncTraffic.php';

echo "Syncing traffic data!\n";

[$debug, $repo, $accessToken] = getValidatedArguments();

/**
 * @noinspection PhpUndefinedClassInspection
 *
 * @psalm-type Timestamp = string<timestamp('YYYY-MM-DDTHH:MM:SSZ')>
 * @psalm-type YearMonth = string<timestamp('YYYY-MM')>
 * @psalm-type Sha256 = string<sha256>
 * @psalm-type Domain = string<domain>
 *
 * @var $database  array{
 *   '_database' : array{
 *     'last_updated' : int,
 *     'content_hash' : string,
 *     'database_size_bytes' : int,
 *     'database_size_lines' : int,
 *     'total_views' : int,
 *     'total_clones' : int,
 *     'total_installs' : int,
 *     'total_binary_downloads' : int
 *   },
 *   'traffic' : array<Timestamp, array{
 *     'views' : array{
 *       'count' : int,
 *       'uniques' : int
 *     },
 *     'clones' : array{
 *       'count' : int,
 *       'uniques' : int
 *     }
 *   }>,
 *   'popular' : array<Timestamp, array{
 *     'paths' : array<Sha256, array{
 *       'path' : string,
 *       'title' : string,
 *       'count' : int,
 *       'uniques' : int
 *     }>,
 *     'referrers' : array<Domain, array{
 *       'count' : int,
 *       'uniques' : int
 *     }>
 *   }>
 * }
 */
$database = json_decode(file_get_contents('database.json'), true);

$syncTraffic = new SyncTraffic($database, $repo, $accessToken, $debug);
$database = $syncTraffic->fetch();

// Sync total installs
echo ' - Syncing release installs... ';
$totalInstalls = syncTotalInstalls();
$database['_database']['total_installs'] = $totalInstalls;
echo "     Done!\n";

// Sync standalone binary downloads
echo ' - Syncing binary downloads... ';
$totalBinaryDownloads = syncTotalBinaryDownloads($repo, $accessToken);
$database['_database']['total_binary_downloads'] = $totalBinaryDownloads;
echo "    Done!\n";

// Save the database
echo 'Saving database... ';

$database = updateDatabaseMetadata($database);

validateDatabaseSchema($database);

file_put_contents('database.json', json_encode($database, JSON_PRETTY_PRINT));

echo "Done!\n";

echo "All done!\n";

// Helpers

function getValidatedArguments(): array
{
    // Check if --debug is passed as an argument, if so, enable debug mode.
    global $argv;
    $debug = in_array('--debug', $argv);

    // get first argument as the repo (owner/repo)
    $repo = $argv[1] ?? 'null';
    assert(str_contains($repo, '/'), 'Invalid repo');

    // get second argument as the access token
    $accessToken = $argv[2] ?? 'null';
    assert(str_starts_with($accessToken, 'github_pat_'), 'Invalid access token');

    return [$debug, $repo, $accessToken];
}

function updateDatabaseMetadata(array $database): array
{
    $contentHash = hash('sha256', json_encode($database));
    $databaseSize = getDatabaseSize($database);
    $database['_database']['last_updated'] = time();
    $database['_database']['content_hash'] = $contentHash;

    $database['_database']['database_size_bytes'] = $databaseSize['bytes'];
    $database['_database']['database_size_lines'] = $databaseSize['lines'];
    $database['_database']['total_views'] = getTotalViews($database);
    $database['_database']['total_clones'] = getTotalClones($database);

    return $database;
}

function getDatabaseSize(array $database): array
{
    $json = json_encode($database, JSON_PRETTY_PRINT);

    return [
        'bytes' => strlen($json),
        'lines' => substr_count($json, "\n") + 1,
    ];
}

function getTotalViews(array $database): int
{
    $viewsSum = 0;

    foreach ($database['traffic'] as $timestamp => $traffic) {
        $viewsSum += $traffic['views']['count'];
    }

    return $viewsSum;
}

function getTotalClones(array $database): int
{
    $clonesSum = 0;

    foreach ($database['traffic'] as $timestamp => $traffic) {
        $clonesSum += $traffic['clones']['count'];
    }

    return $clonesSum;
}

/** Validate the data integrity */
function validateDatabaseSchema(array $database): void
{
    foreach ($database as $tableKey => $table) {
        assert(in_array($tableKey, ['_database', 'traffic', 'popular']));
        assert(is_array($table));

        switch ($tableKey) {
            case '_database':
                assert(array_key_exists('last_updated', $table));
                assert(array_key_exists('content_hash', $table));
                assert(array_key_exists('database_size_bytes', $table));
                assert(array_key_exists('database_size_lines', $table));
                assert(array_key_exists('total_views', $table));
                assert(array_key_exists('total_clones', $table));
                assert(array_key_exists('total_installs', $table));
                assert(array_key_exists('total_binary_downloads', $table));

                assert(is_int($table['last_updated']));
                assert(is_string($table['content_hash']));
                assert(strlen($table['content_hash']) === 64);
                assert(is_int($table['database_size_bytes']));
                assert(is_int($table['database_size_lines']));
                assert(is_int($table['total_views']));
                assert(is_int($table['total_clones']));
                assert(is_int($table['total_installs']));
                assert(is_int($table['total_binary_downloads']));
                break;
            case 'traffic':
                foreach ($table as $dateKey => $date) {
                    assert(is_string($dateKey));
                    assert(strlen($dateKey) === 20);
                    assert(str_ends_with($dateKey, 'T00:00:00Z'));

                    assert(array_key_exists('views', $date));
                    assert(array_key_exists('clones', $date));

                    assert(is_array($date['views']));
                    assert(is_array($date['clones']));

                    assert(array_key_exists('count', $date['views']));
                    assert(array_key_exists('uniques', $date['views']));
                    assert(array_key_exists('count', $date['clones']));
                    assert(array_key_exists('uniques', $date['clones']));

                    assert(is_int($date['views']['count']));
                    assert(is_int($date['views']['uniques']));
                    assert(is_int($date['clones']['count']));
                    assert(is_int($date['clones']['uniques']));
                }
                break;
            case 'popular':
                foreach ($table as $dateKey => $date) {
                    assert(is_string($dateKey));
                    assert(strlen($dateKey) === 7);
                    assert(str_contains($dateKey, '-'));

                    assert(array_key_exists('paths', $date));

                    assert(is_array($date['paths']));

                    foreach ($date['paths'] as $pathKey => $path) {
                        assert(is_string($pathKey));
                        assert(strlen($pathKey) === 64);

                        assert(array_key_exists('path', $path));
                        assert(array_key_exists('title', $path));
                        assert(array_key_exists('count', $path));
                        assert(array_key_exists('uniques', $path));

                        assert(is_string($path['path']));
                        assert(is_string($path['title']));
                        assert(is_int($path['count']));
                        assert(is_int($path['uniques']));
                    }
                }
                break;
        }
    }
}

function syncTotalInstalls(): int
{
    // Merges Packagist installs and GitHub releases downloads

    $packagistInstalls = json_decode(file_get_contents('https://img.shields.io/packagist/dt/hyde/cli.json'), true);
    $githubReleases = json_decode(file_get_contents('https://img.shields.io/github/downloads/hydephp/cli/total.json'), true);

    return $packagistInstalls['value'] + $githubReleases['value'];
}

/**
 * Get the total number of downloads for standalone HydePHP CLI binaries.
 *
 * GitHub's release download totals include signatures and any other attached
 * assets. For this statistic we count only the actual platform executables.
 */
function syncTotalBinaryDownloads(string $repo, string $accessToken): int
{
    $binaryNames = [
        // Legacy executable used by older releases.
        'hyde',

        // Current platform-specific executables.
        'hyde-linux-x86_64',
        'hyde-linux-arm64',
        'hyde-macos-x86_64',
        'hyde-macos-arm64',
        'hyde-windows-x86_64.exe',
    ];

    $total = 0;
    $page = 1;

    do {
        $url = sprintf(
            'https://api.github.com/repos/%s/releases?per_page=100&page=%d',
            $repo,
            $page,
        );

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: HydePHP Traffic Controller',
            'Accept: application/vnd.github+json',
            "Authorization: Bearer $accessToken",
            'X-GitHub-Api-Version: 2022-11-28',
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Curl error: '.curl_error($ch));
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($statusCode >= 400) {
            throw new Exception(sprintf(
                "Invalid status code while fetching releases: %s\n%s",
                $statusCode,
                $response,
            ));
        }

        curl_close($ch);

        $releases = json_decode($response, true);

        if (! is_array($releases)) {
            throw new Exception(sprintf(
                "Invalid releases response:\n%s",
                $response,
            ));
        }

        foreach ($releases as $release) {
            // Draft releases have not actually been released to users.
            if ($release['draft'] ?? false) {
                continue;
            }

            foreach ($release['assets'] ?? [] as $asset) {
                if (in_array($asset['name'] ?? '', $binaryNames, true)) {
                    $total += $asset['download_count'] ?? 0;
                }
            }
        }

        $page++;
    } while (count($releases) === 100);

    return $total;
}
