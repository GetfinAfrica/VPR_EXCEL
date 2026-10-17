param([int]$Port = 8088, [string]$Php = 'C:\xampp\php\php.exe')
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
if ($Port -lt 1024 -or $Port -gt 65535) { throw 'Choose a port between 1024 and 65535.' }
foreach ($currency in @('USD','ZWG')) {
    foreach ($suffix in @('ID','KEY','ENVIRONMENT','ENABLED','CURRENCY_CONFIRMED')) {
        $name = 'VPR_PAYNOW_' + $currency + '_' + $suffix
        if (-not [Environment]::GetEnvironmentVariable($name, 'Process')) {
            [Environment]::SetEnvironmentVariable($name, [Environment]::GetEnvironmentVariable($name, 'User'), 'Process')
        }
    }
    $environment = [Environment]::GetEnvironmentVariable('VPR_PAYNOW_' + $currency + '_ENVIRONMENT','Process')
    if ($environment -and $environment -ne 'test') { throw 'This launcher accepts test profiles only. Check the Paynow account mode as well.' }
}
& $Php (Join-Path $PSScriptRoot 'prepare_dev_db.php') $Port
if ($LASTEXITCODE -ne 0) { throw 'Unable to prepare the development database.' }
$env:VPR_CONFIG_FILE = Join-Path $projectRoot 'data\vpr.dev.config.php'
Write-Host "VPR development: http://127.0.0.1:$Port (separate database; Ctrl+C to stop)"
& $Php -S "127.0.0.1:$Port" -t $projectRoot (Join-Path $PSScriptRoot 'dev_router.php')
