param([string]$PhpBinary = 'D:\php-8.5.11\php.exe')
$ErrorActionPreference = 'Stop'
$applicationRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$phpConfiguration = (Resolve-Path (Join-Path $applicationRoot '..\.tools\php.ini')).Path
Set-Location -LiteralPath $applicationRoot
& $PhpBinary -c $phpConfiguration artisan schedule:run *>> (Join-Path $applicationRoot 'storage\logs\host-scheduler.log')
exit $LASTEXITCODE
