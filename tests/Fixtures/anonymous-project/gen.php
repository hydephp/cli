<?php

# Generate test files

$files = [
    '_docs/index.md',
    '_posts/hello-world.md',
    '_pages/about.md',
    '_pages/demo.html',
    '_pages/index.blade.php',
];

foreach ($files as $file) {
    $dir = dirname($file);

    if (!is_dir($dir)) {
        mkdir($dir);
    }

    $title = ucwords(trim(str_replace('/', ' - ', str_replace(['_', '-', '.md', '.html', '.blade.php'], [' ', ' ', '', '', ''], $file))));
    file_put_contents($file, sprintf("%s\n\n%s\n", str_ends_with($file, '.md') ? sprintf('# %s', $title) : sprintf('<h1>%s</h1>', $title), $file));
}
