<?php

/**
 * @internal Run configuration for traffic sync.
 *
 * @example php sync.php owner/repo github_pat_1234567890 [--debug]
 */

require_once __DIR__.'/SyncTraffic.php';

echo "Syncing traffic data!\n";

[$debug, $repo, $accessToken] = getValidatedArguments();

$database = json_decode(file_get_contents('database.json'), true);

$syncTraffic = new SyncTraffic($database, $repo, $accessToken, $debug);
$database = $syncTraffic->fetch();

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
    $database['_database']['last_updated'] = time();
    $database['_database']['content_hash'] = $contentHash;

    return $database;
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

                assert(is_int($table['last_updated']));
                assert(is_string($table['content_hash']));
                assert(strlen($table['content_hash']) === 64);
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
