# Runtime acceptance test for the HydeCLI native executable on Windows.
#
# The PowerShell counterpart of tests/System/acceptance.sh. It needs nothing installed
# beyond PowerShell itself, so it can run on a machine where PHP was never present.
#
# Usage: pwsh tests/System/acceptance.ps1 -Hyde C:\path\to\hyde-windows-x86_64.exe

param(
    [Parameter(Mandatory = $true)]
    [string] $Hyde
)

$ErrorActionPreference = 'Continue'

if (-not (Test-Path $Hyde)) {
    Write-Error "Not found: $Hyde"
    exit 2
}

$script:Checks = 0
$script:Failures = 0

function Pass([string] $description) {
    $script:Checks++
    Write-Host "  ok    $description"
}

function Fail([string] $description, [string] $detail = '') {
    $script:Checks++
    $script:Failures++
    Write-Host "  FAIL  $description"
    if ($detail) { Write-Host "        $detail" }
}

function Assert-Contains([string] $description, [string] $haystack, [string] $needle) {
    if ($haystack -like "*$needle*") { Pass $description } else { Fail $description "expected to contain: $needle" }
}

function Assert-Missing([string] $description, [string] $path) {
    if (Test-Path $path) { Fail $description "unexpectedly exists: $path" } else { Pass $description }
}

function Invoke-Hyde([string] $directory, [string[]] $arguments) {
    Push-Location $directory
    try {
        $output = & $Hyde @arguments 2>&1 | Out-String
        return @{ Output = $output; Status = $LASTEXITCODE }
    } finally {
        Pop-Location
    }
}

$work = Join-Path ([System.IO.Path]::GetTempPath()) ("hyde-acceptance-" + [System.Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Force -Path $work | Out-Null

try {
    Write-Host '==> Environment'

    if (Get-Command php -ErrorAction SilentlyContinue) {
        Fail 'no PHP is installed' 'php was found on the search path'
    } else {
        Pass 'no PHP is installed'
    }

    Write-Host '==> The executable runs'

    $version = Invoke-Hyde $work @('--version', '--no-ansi')
    Assert-Contains 'hyde --version works' $version.Output 'HydePHP'

    Write-Host '==> Portable project'

    $site = Join-Path $work 'site'
    New-Item -ItemType Directory -Force -Path (Join-Path $site '_pages') | Out-Null
    Set-Content -Path (Join-Path $site '_pages\index.md') -Value "---`ntitle: Test Page`n---`n`n# Hello Portable World`n"

    $info = Invoke-Hyde $site @('info', '--no-ansi')
    Assert-Contains 'info reports a portable project' $info.Output 'Project type: Portable'
    Assert-Contains 'info reports an embedded framework' $info.Output '(embedded)'
    Assert-Contains 'info reports a bundled runtime' $info.Output '(bundled)'

    $build = Invoke-Hyde $site @('build', '--no-ansi')
    Assert-Contains 'a portable project builds' $build.Output 'Your static site has been built!'

    $indexPath = Join-Path $site '_site\index.html'
    if (Test-Path $indexPath) {
        Assert-Contains 'the built page has the expected content' (Get-Content $indexPath -Raw) 'Hello Portable World'
    } else {
        Fail 'the built page has the expected content' "missing file: $indexPath"
    }

    Assert-Missing 'building creates no vendor directory' (Join-Path $site 'vendor')
    Assert-Missing 'building creates no composer manifest' (Join-Path $site 'composer.json')

    $routes = Invoke-Hyde $site @('route:list', '--no-ansi')
    Assert-Contains 'route:list works' $routes.Output '_pages/index.md'

    $made = Invoke-Hyde $site @('make:page', 'About', '--no-ansi', '--no-interaction')
    Assert-Contains 'make:page works' $made.Output 'About'

    if (Test-Path (Join-Path $site '_pages\about.md')) {
        Pass 'make:page wrote the file'
    } else {
        Fail 'make:page wrote the file'
    }

    Write-Host '==> HydePHP v3'

    # v3 does not bump the version number, so the artifact is proven to carry the v3
    # development line by behaviour that the released v2 line does not have.

    Assert-Contains 'info reports the v3 development line' $info.Output 'v3 development line'

    $listed = Invoke-Hyde $site @('list', '--no-ansi')

    if ($listed.Output -match 'rebuild') {
        Fail 'the rebuild command v3 removed is absent'
    } else {
        Pass 'the rebuild command v3 removed is absent'
    }

    Set-Content -Path (Join-Path $site '_pages\v3-probe.md') -Value @(
        '# Probe',
        '',
        '```php title="app/Model.php"',
        "echo 1;",
        '```'
    )

    New-Item -ItemType Directory -Force -Path (Join-Path $site '_static') | Out-Null
    Set-Content -Path (Join-Path $site '_static\CNAME') -Value 'example.com'
    Set-Content -Path (Join-Path $site '_site\stray.txt') -Value 'stray'

    Invoke-Hyde $site @('build', '--no-ansi') | Out-Null

    $probePath = Join-Path $site '_site\v3-probe.html'
    if (Test-Path $probePath) {
        Assert-Contains 'a code block title renders a v3 label' (Get-Content $probePath -Raw) 'hyde-code-block-label'
    } else {
        Fail 'a code block title renders a v3 label'
    }

    $cnamePath = Join-Path $site '_site\CNAME'
    if (Test-Path $cnamePath) {
        Assert-Contains '_static files are copied to the site root' (Get-Content $cnamePath -Raw) 'example.com'
    } else {
        Fail '_static files are copied to the site root'
    }

    Assert-Missing 'the output directory is emptied completely' (Join-Path $site '_site\stray.txt')

    Remove-Item (Join-Path $site '_pages\v3-probe.md') -Force

    Write-Host '==> Configuration'

    Set-Content -Path (Join-Path $site 'hyde.yml') -Value 'name: "Configured Site Name"'
    Set-Content -Path (Join-Path $site '_pages\site-name.blade.php') -Value '{{ config("hyde.name", "not-set") }}'

    Invoke-Hyde $site @('build', '--no-ansi') | Out-Null

    $namePath = Join-Path $site '_site\site-name.html'
    if (Test-Path $namePath) {
        Assert-Contains 'hyde.yml configuration is honoured' (Get-Content $namePath -Raw) 'Configured Site Name'
    } else {
        Fail 'hyde.yml configuration is honoured' "missing file: $namePath"
    }

    Write-Host '==> Creating a project'

    $workspace = Join-Path $work 'workspace'
    New-Item -ItemType Directory -Force -Path $workspace | Out-Null

    $created = Invoke-Hyde $workspace @('new', 'my-site', '--portable', '--no-ansi', '--no-interaction')
    Assert-Contains 'hyde new --portable succeeds' $created.Output 'Created a portable Hyde site'
    Assert-Missing 'the new project has no composer manifest' (Join-Path $workspace 'my-site\composer.json')
    Assert-Missing 'the new project has no vendor directory' (Join-Path $workspace 'my-site\vendor')

    $newBuild = Invoke-Hyde (Join-Path $workspace 'my-site') @('build', '--no-ansi')
    Assert-Contains 'the new project builds immediately' $newBuild.Output 'Your static site has been built!'

    Write-Host '==> hyde new --composer without Composer'

    $composerAttempt = Invoke-Hyde $workspace @('new', 'composer-site', '--composer', '--no-ansi', '--no-interaction')

    if ($composerAttempt.Status -eq 0) {
        Fail 'hyde new --composer fails without Composer' 'it reported success'
    } else {
        Pass 'hyde new --composer fails without Composer'
    }

    Assert-Contains 'the failure explains what to do' $composerAttempt.Output 'Creating a Composer project requires Composer.'
    Assert-Missing 'no directory is left behind' (Join-Path $workspace 'composer-site')

    Write-Host '==> Project detection'

    $unrelated = Join-Path $work 'unrelated'
    New-Item -ItemType Directory -Force -Path (Join-Path $unrelated '_pages') | Out-Null
    Set-Content -Path (Join-Path $unrelated '_pages\index.md') -Value '# Unrelated'
    Set-Content -Path (Join-Path $unrelated 'composer.json') -Value '{"name":"acme/thing","description":"mentions hyde/framework","require":{"monolog/monolog":"^3.0"}}'

    $unrelatedInfo = Invoke-Hyde $unrelated @('info', '--no-ansi')
    Assert-Contains 'an unrelated manifest stays portable' $unrelatedInfo.Output 'Project type: Portable'

    $broken = Join-Path $work 'broken'
    New-Item -ItemType Directory -Force -Path (Join-Path $broken '_pages') | Out-Null
    Set-Content -Path (Join-Path $broken '_pages\index.md') -Value '# Broken'
    Set-Content -Path (Join-Path $broken 'composer.json') -Value '{"name":"acme/site","require":{"hyde/framework":"^2.0"}}'

    $brokenBuild = Invoke-Hyde $broken @('build', '--no-ansi')

    if ($brokenBuild.Status -eq 0) {
        Fail 'a Composer project with no vendor fails' 'it reported success'
    } else {
        Pass 'a Composer project with no vendor fails'
    }

    Assert-Contains 'the failure names composer install' $brokenBuild.Output 'composer install'
    Assert-Missing 'nothing was built' (Join-Path $broken '_site')

    Write-Host '==> Serving'

    $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
    $listener.Start()
    $port = $listener.LocalEndpoint.Port
    $listener.Stop()

    $server = Start-Process -FilePath $Hyde -ArgumentList @('serve', '--host=127.0.0.1', "--port=$port", '--no-ansi') -WorkingDirectory $site -PassThru -NoNewWindow

    try {
        $ready = $false

        for ($i = 0; $i -lt 60; $i++) {
            try {
                $response = Invoke-WebRequest -Uri "http://127.0.0.1:$port/" -TimeoutSec 2 -UseBasicParsing
                $ready = $true
                break
            } catch {
                Start-Sleep -Seconds 1
            }
        }

        if ($ready) {
            Pass 'hyde serve answers an HTTP request'
            Assert-Contains 'the served page has the expected content' $response.Content 'Hello Portable World'

            try {
                $dashboard = Invoke-WebRequest -Uri "http://127.0.0.1:$port/dashboard" -TimeoutSec 5 -UseBasicParsing
                Assert-Contains 'the realtime compiler dashboard is served' $dashboard.Content 'Dashboard'
            } catch {
                Fail 'the realtime compiler dashboard is served' $_.Exception.Message
            }
        } else {
            Fail 'hyde serve answers an HTTP request' 'the server never accepted a connection'
        }
    } finally {
        # Stop the server and the built-in web server it started, by process id only.
        Get-CimInstance Win32_Process -Filter "ParentProcessId = $($server.Id)" |
            ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

        Stop-Process -Id $server.Id -Force -ErrorAction SilentlyContinue
    }

    Write-Host ''
    Write-Host "==> $($script:Checks - $script:Failures)/$($script:Checks) checks passed"
} finally {
    Remove-Item -Recurse -Force $work -ErrorAction SilentlyContinue
}

if ($script:Failures -gt 0) { exit 1 }
