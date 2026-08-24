# Builds the native `hyde.exe` executable on Windows.
#
# This mirrors bin/build-native.sh, which cannot run here: static-php-cli drives the
# Visual Studio toolchain on Windows and expects a native shell. The runtime version
# and the extension set come from build/runtime.json either way, so the two scripts
# can never drift apart on what they build.
#
# Usage: pwsh bin/build-native.ps1 [-SkipSpc] [-Build <sha>]

param(
    [switch] $SkipSpc,
    [string] $Build = ''
)

$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
$Work = if ($env:SPC_WORK_DIR) { $env:SPC_WORK_DIR } else { Join-Path $Root '.build' }
$Config = Join-Path $Root 'build\runtime.json'

$runtime = Get-Content $Config -Raw | ConvertFrom-Json
$phpVersion = $runtime.php
$extensions = ($runtime.extensions.PSObject.Properties.Name) -join ','

Write-Host "==> HydeCLI native build"
Write-Host "    PHP version: $phpVersion"
Write-Host "    Extensions:  $extensions"

New-Item -ItemType Directory -Force -Path $Work | Out-Null

$spc = Join-Path $Work 'spc.exe'

if (-not $SkipSpc) {
    if (-not (Test-Path $spc)) {
        Write-Host '==> Downloading static-php-cli'
        Invoke-WebRequest -Uri 'https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-windows-x64.exe' -OutFile $spc
    }

    Push-Location $Work
    try {
        Write-Host '==> Checking the build environment'
        & $spc doctor --auto-fix
        if ($LASTEXITCODE -ne 0) { throw "spc doctor failed with exit code $LASTEXITCODE" }

        Write-Host '==> Downloading sources'
        & $spc download --with-php=$phpVersion --for-extensions=$extensions --prefer-pre-built --retry=3
        if ($LASTEXITCODE -ne 0) { throw "spc download failed with exit code $LASTEXITCODE" }

        Write-Host '==> Building the PHP CLI and micro SAPI'
        & $spc build $extensions --build-cli --build-micro
        if ($LASTEXITCODE -ne 0) { throw "spc build failed with exit code $LASTEXITCODE" }
    } finally {
        Pop-Location
    }
}

$micro = Join-Path $Work 'buildroot\bin\micro.sfx'
$php = Join-Path $Work 'buildroot\bin\php.exe'

foreach ($artifact in @($micro, $php)) {
    if (-not (Test-Path $artifact)) {
        throw "Missing build artifact: $artifact"
    }
}

Write-Host '==> Installing production dependencies'
composer install --no-interaction --no-progress --prefer-dist --no-dev --optimize-autoloader --working-dir="$Root"
if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }

Write-Host '==> Verifying the embedded dependency graph is v3'
& php (Join-Path $Root 'bin\verify-v3-graph.php')
if ($LASTEXITCODE -ne 0) { throw 'The embedded dependency graph is not HydePHP v3' }

Write-Host '==> Building the executable'
$arguments = @('-d', 'phar.readonly=0', (Join-Path $Root 'bin\build-phar.php'), "--micro=$micro", "--runtime=$php")

if ($Build) { $arguments += "--build=$Build" }

& php @arguments
if ($LASTEXITCODE -ne 0) { throw 'Building the executable failed' }

Write-Host '==> Restoring development dependencies'
composer install --no-interaction --no-progress --prefer-dist --working-dir="$Root"

Write-Host '==> Done'
Get-ChildItem (Join-Path $Root 'builds')
