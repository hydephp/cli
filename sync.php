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
