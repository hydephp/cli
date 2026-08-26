<?php

/** @internal Build the documentation manual. */

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/lib/ansi-themes.php';
require_once __DIR__.'/lib/manual-version.php';

chdir(__DIR__.'/..');

if (! is_dir('docs/manual')) {
    mkdir('docs/manual', recursive: true);
}

task('getting|got', 'command list', function (&$commands): void {
    $commands = hyde_exec('list --format=json --no-ansi', true);
    $commands = json_decode($commands, true);
}, $commands);

task('building|built', 'Html manual', function () use ($commands): void {
    $names = array_map(fn (array $command): string => $command['name'], $commands['commands']);
    $names = array_filter($names, fn (string $name): bool => ! in_array($name, ['_complete', 'completion']));
    $names = array_values($names);

    $manual = [];

    echo "\n\n";
    foreach ($names as $name) {
        ansi_echo(" > Building entry for command '$name'\n", '37');

        $info = hyde_exec("help $name --ansi", true);
        $info = ansi_to_html($info);
        $signature = 'hyde '.$name;
        $manual[] = <<<HTML
        <section>
        <h2>$name</h2>

        <pre class="terminal-screen"><div class="signature"><span class="caret">$ </span>$signature<span class="caret"> [options] [arguments]</span></div>
        $info</pre>

        </section>
        HTML."\n";
    }
    echo "\n";

    // In the future, we could save each entry to a separate file, but now we just implode them into one.
    $entries = "\n".implode("\n", $manual);
    $themes = ansi_html_themes();
    $themeSelector = theme_selector_widget();
    $theme = get_theme_key(get_default_ansi_theme());
    $template = get_template();
    $version = parse_version(trim(hyde_exec('--version --no-ansi', true)));
    $portableSection = portable_manual_html();

    $data = compact(['themes', 'themeSelector', 'theme', 'entries', 'template', 'version', 'portableSection']);

    $manual = view($template, $data);

    file_put_contents('docs/manual/manual.html', $manual);
});

task('building|built', 'XML manual', function (): void {
    $xml = hyde_exec('list --format=xml --no-ansi', true);
    file_put_contents('docs/manual/manual.xml', $xml);
});

task('building|built', 'Markdown manual', function (): void {
    $md = hyde_exec('list --format=md --no-ansi', true);
    file_put_contents('docs/manual/manual.md', portable_manual_markdown()."\n\n".$md);
});

/** The conceptual part of the manual is maintained here beside the generated commands. */
function portable_manual_markdown(): string
{
    return <<<'MD'
## Portable sites

A Portable project can be content and configuration only:

```
_pages/
_posts/
_media/
_static/
hyde.yml
```

It does not need a local PHP or Composer installation. Choose Portable when you want the
smallest, easiest-to-copy site and do not need Composer addons or custom PHP dependencies.
Choose a Composer project when you need those extensions; it uses the dependencies declared by
the project and keeps its own asset behavior.

### Default styling

The standalone Hyde executable includes Hyde's production `app.css`. A fresh Portable site is
therefore styled even though `_media/app.css` does not exist. Both `hyde build` and `hyde serve`
use this bundled default, so the standard site works offline without Tailwind Play CDN, Vite,
Node, npm, or the Hyde stylesheet CDN.

### Custom styling and media directories

Create `_media/app.css` to override the bundled stylesheet. Hyde serves or builds the user file,
does not expose the bundled replacement, and never modifies or overwrites the source file. If
the media directory is configured as `assets`, the corresponding source and virtual stylesheet
path is `assets/app.css`, and the served/generated URL is the configured media output path.
MD;
}

function portable_manual_html(): string
{
    return <<<'HTML'
    <section>
    <h2>Portable sites</h2>
    <p>A Portable project can consist only of:</p>
    <pre>_pages/
    _posts/
    _media/
    _static/
    hyde.yml</pre>
    <p>It needs no local PHP or Composer installation. Choose Portable for a content-only site;
    choose a Composer project when you need addons or custom PHP dependencies. Composer projects
    use the dependencies and asset behavior declared by the project.</p>
    <h3>Default styling</h3>
    <p>The standalone executable includes Hyde's production <code>app.css</code>. A fresh
    Portable site is styled even though <code>_media/app.css</code> does not exist. Both
    <code>hyde build</code> and <code>hyde serve</code> use this bundled default, offline and
    without Tailwind Play CDN, Vite, Node, npm, or the Hyde stylesheet CDN.</p>
    <h3>Custom styling</h3>
    <p>Creating <code>_media/app.css</code> overrides the bundled default. The CLI never changes
    or overwrites the user file. When the media directory is configured as <code>assets</code>,
    the virtual stylesheet is <code>assets/app.css</code> and its served/generated URL follows
    the configured media output path.</p>
    </section>
    HTML;
}

/** Execute a command in the Hyde CLI and return the output. */
function hyde_exec(string $command, bool $cache = false): string
{
    $cacheKey = 'bin/cache/'.md5($command);

    if ($cache && file_exists($cacheKey)) {
        return file_get_contents($cacheKey);
    }

    exec("php hyde $command", $output, $exitCode);

    if ($exitCode !== 0) {
        throw new Exception("Failed to execute command: $command");
    }

    $output = implode("\n", $output);

    if ($cache) {
        file_put_contents($cacheKey, $output);
    }

    return $output;
}

/** Output a message with ANSI colors. */
function ansi_echo(string $message, string $color): void
{
    echo "\e[{$color}m$message\e[0m";
}

/** Run a task and output the time it took to complete. */
function task(string $verb, string $subject, callable $task, &$output = null): void
{
    [$start, $end] = str_contains($verb, '|')
        ? explode('|', $verb)
        : [$verb, $verb];

    [$start, $end] = [ucfirst($start), ucfirst($end)];

    $timeStart = microtime(true);
    ansi_echo("$start $subject...", '36');

    $task($output);

    $time = round((microtime(true) - $timeStart) * 1000, 2);
    ansi_echo("\r$end $subject ($time ms)\n", '32');
}

function ansi_to_html(string $output): string
{
    $output = htmlspecialchars($output);
    $output = preg_replace('/\e\[(\d+)(;\d+)*m/', '</span><span class="ansi-$1">', $output);

    return "<span class=\"ansi-0\">$output</span>";
}

function ansi_html_themes(): string
{
    return "\n".implode("\n", array_map('build_theme', get_themes()));
}

function theme_selector_widget(): string
{
    $themes = get_themes();

    $options = '';
    foreach ($themes as $theme) {
        $key = get_theme_key($theme);
        $name = ucfirst($key);

        if ($theme::class === get_default_ansi_theme()::class) {
            $options .= "<option value=\"$name\" selected>$name</option>";
        } else {
            $options .= "<option value=\"$key\">$name</option>";
        }
    }

    return <<<HTML
    <label for="theme-selector">Theme:</label>
    <select id="theme-selector">
        $options
    </select>

    <script>
        const selector = document.getElementById('theme-selector');
        selector.addEventListener('change', () => {
            const theme = selector.value;
            document.body.className = '';
            document.body.classList.add('theme-' + theme.toLowerCase().replace('theme', ''));
        });
    </script>
    HTML;
}

function build_theme(ThemeInterface $theme): string
{
    $colors = $theme::colors();

    $identifier = strtolower(str_replace('Theme', '', (new ReflectionClass($theme))->getShortName()));

    $theme = <<<CSS
            .theme-$identifier .terminal-screen {
                 color: {$theme::foreground()};
                 background: {$theme::background()};
                 font-family: {$theme::fontFamily()};
            }

    CSS;

    $theme .= "\n";
    foreach ($colors as $code => $color) {
        $theme .= "        .theme-$identifier .ansi-$code { color: $color; }\n";
    }

    return rtrim($theme)."\n    ";
}

function get_theme_key(ThemeInterface $theme): string
{
    return strtolower(str_replace('Theme', '', (new ReflectionClass($theme))->getShortName()));
}

function get_themes(): array
{
    return [
        new FiraTheme(),
        new CampbellTheme(),
        new ClassicTheme(),
    ];
}

function get_default_ansi_theme(): ThemeInterface
{
    return new FiraTheme();
}

function get_template(): string
{
    return <<<'BLADE'
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <!--
            This HydePHP manual is provided under the MIT License, with the content dual-licensed as CC BY 4.0.
            For more information, visit https://hydephp.com/license and https://creativecommons.org/licenses/by-sa/4.0/
            respectively for the licenses governing the software and the contents of this manual.
        --> 
        <meta charset="UTF-8">
        <title>HydePHP CLI Manual</title>
        <style>{{ themes }}</style>
        <style>
            body {
                font-family: sans-serif;
                background: #f8f8f8;
                color: #292d3e;
                margin: 0;
                padding: 0;
                display: flex;
                flex-direction: column;
            }
            main {
                margin: 1em auto;
            }
            h2 {
                font-size: 1.5em;
                margin: 1em 0 0.5em;
            }
            label {
                font-size: 0.9em;
            }
            select {
                border-radius: 0.25rem;
            }
            a {
                text-decoration: none;
                border-radius: 0.25rem;
                color: #292d3e;
            }
            a:hover {
                text-decoration: underline;
            }
            menu a {
                color: #fff;
                background: #292d3e;
                margin-left: 0.5em;
                margin-right: 0.25em;
            }
            menu a:hover {
                color: #82aaff;
            }
            menu {
                float: right;
                display: inline;
                margin: 0;
            }
            .menubar {
                background: #292d3e;
                color: #fff;
                padding: 1em;
            }
            .menubar h2 {
                margin: 0;
                display: inline;
                font-size: 1em;
            }
            .terminal-screen {
                font-size: 12px;
                width: 128ch;
                overflow-x: auto;
                padding: 1em;
                border-radius: 0.25rem;
            }
            .signature {
                background: rgba(0, 0, 0, 0.1);
                padding: 1em 1em 0.75em;
                margin: -1em -1em -0.75em;
            }
            .signature .caret {
                color: #999;
                user-select: none;
            }
            footer {
                text-align: center;
                font-size: 0.9em;
                border-top: 1px solid #ddd;
                padding: 0.5em 4em;
                width: 80vw;
                max-width: 720px;
                margin: 0 auto;
            }
            footer p {
                margin: 0.5em 0;
            }
            footer .footer-links a {
                color: #0037da;
            }
        </style>
    </head>
    <body class="theme-{{ theme }}">
    <nav class="menubar">
        <h2>
            HydePHP CLI Manual
        </h2>
        <menu>
            {{ themeSelector }}
            <a href="manual.xml">XML Version</a>
            <a href="manual.md">Markdown Version</a>
            <span style="user-select: none; opacity: 0.5;">|</span>
            <a href="https://hydephp.github.io/cli" title="Go back" aria-label="Go back" style="display: inline-flex;">
                <span style="margin-right: 0.25em;">Exit</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256" xml:space="preserve" style="width: 1em; height: 1em;">
                    <g style="stroke: none; stroke-width: 0; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: none; fill-rule: nonzero; opacity: 1;" transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)" >
                        <path d="M 86.356 46.27 c 0.031 -0.065 0.059 -0.131 0.085 -0.199 c 0.042 -0.11 0.076 -0.222 0.104 -0.336 c 0.016 -0.062 0.034 -0.123 0.046 -0.186 c 0.034 -0.181 0.055 -0.364 0.055 -0.548 l 0 0 c 0 0 0 0 0 0 c 0 -0.184 -0.022 -0.367 -0.055 -0.548 c -0.012 -0.064 -0.03 -0.124 -0.046 -0.186 c -0.029 -0.114 -0.062 -0.226 -0.104 -0.336 c -0.026 -0.068 -0.055 -0.134 -0.086 -0.199 c -0.046 -0.099 -0.099 -0.194 -0.156 -0.288 c -0.039 -0.063 -0.077 -0.126 -0.12 -0.186 c -0.02 -0.027 -0.033 -0.057 -0.054 -0.084 L 74.316 27.93 c -1.009 -1.313 -2.894 -1.561 -4.207 -0.551 c -1.313 1.009 -1.561 2.893 -0.551 4.207 L 77.56 42 H 30.903 c -1.657 0 -3 1.343 -3 3 c 0 1.657 1.343 3 3 3 h 46.656 l -8.001 10.414 c -1.01 1.314 -0.763 3.197 0.551 4.207 c 0.545 0.419 1.188 0.621 1.826 0.621 c 0.9 0 1.79 -0.403 2.381 -1.172 l 11.71 -15.242 c 0.021 -0.027 0.035 -0.057 0.055 -0.085 c 0.043 -0.06 0.08 -0.122 0.119 -0.184 C 86.257 46.464 86.31 46.369 86.356 46.27 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(255,255,255); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                        <path d="M 60.442 90 H 9.353 c -1.657 0 -3 -1.343 -3 -3 V 3 c 0 -1.657 1.343 -3 3 -3 h 51.089 c 1.657 0 3 1.343 3 3 v 30.054 c 0 1.657 -1.343 3 -3 3 s -3 -1.343 -3 -3 V 6 H 12.353 v 78 h 45.089 V 55.61 c 0 -1.657 1.343 -3 3 -3 s 3 1.343 3 3 V 87 C 63.442 88.657 62.1 90 60.442 90 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(255,255,255); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                    </g>
                </svg>
            </a>
        </menu>
    </nav>
    <main>{{ portableSection }}{{ entries }}</main>
    <footer>
        <p>
            Manual for the <a href="https://hydephp.github.io/cli?ref=manual">HydePHP CLI</a> - Version {{ version }}
        </p>
        <p>
            <small>Content distributed under the HydePHP <a href="https://hydephp.com/license" rel="nofollow">MIT License</a>, dual-licensed as <a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="nofollow">CC BY 4.0</a>.</small>
        </p>
        <p class="footer-links">
            <small>
                <a href="" download="hydephp-manual.html" title="Download the HTML manual for offline use">Download for offline use</a>
                <span style="opacity: 0.5; user-select: none;">|</span>
                <a href="https://github.com/hydephp/cli">GitHub</a>
                <span style="opacity: 0.5; user-select: none;">|</span>
                <a href="https://hydephp.com?ref=manual">HydePHP</a>
            </small>
        </p>
    </footer>
    </body>
    </html>
    BLADE;
}

function view(string $template, array $data): string
{
    foreach ($data as $key => $value) {
        $template = str_replace("{{ $key }}", $value, $template);
    }

    return $template;
}
