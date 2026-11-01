# Builds a cPanel-ready package: nilambara-cpanel.zip containing
#   nilambara-app\  -> upload to /home/CPUSER/ (outside public_html)
#   public_html\    -> upload contents to /home/CPUSER/public_html/
# Usage (from the project folder): powershell -File scripts\build-cpanel.ps1
param(
    [string]$AppFolder = 'nilambara-app',
    [string]$PhpIni = (Join-Path $PSScriptRoot '..\..\.tools\php.ini'),
    [string]$ComposerPhar = (Join-Path $PSScriptRoot '..\..\.tools\composer.phar')
)

$ErrorActionPreference = 'Stop'
$project = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$out = Join-Path (Split-Path $project) 'cpanel-build'
$app = Join-Path $out $AppFolder
$web = Join-Path $out 'public_html'
$zip = Join-Path (Split-Path $project) 'nilambara-cpanel.zip'

if (Test-Path $PhpIni) { $env:PHPRC = (Split-Path (Resolve-Path $PhpIni).Path) }

Remove-Item $out -Recurse -Force -ErrorAction SilentlyContinue
Remove-Item $zip -Force -ErrorAction SilentlyContinue

Write-Host 'Building frontend assets...'
Push-Location $project
npm run build
if ($LASTEXITCODE -ne 0) { throw 'npm run build failed' }
Pop-Location

Write-Host 'Copying application...'
robocopy $project $app /E /XD node_modules vendor .git tests public scripts /XF .env '*.log' phpunit.xml /NFL /NDL /NJS /NP | Out-Null

Write-Host 'Installing production dependencies...'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
Push-Location $app
if (Test-Path $ComposerPhar) { php $ComposerPhar install --no-dev --optimize-autoloader --no-interaction } else { composer install --no-dev --optimize-autoloader --no-interaction }
if ($LASTEXITCODE -ne 0) { throw 'composer install failed' }
Pop-Location

Get-ChildItem (Join-Path $app 'storage\logs') -File -ErrorAction SilentlyContinue | Remove-Item -Force
Get-ChildItem (Join-Path $app 'storage\framework\views') -File -ErrorAction SilentlyContinue | Where-Object Name -ne '.gitignore' | Remove-Item -Force
Get-ChildItem (Join-Path $app 'storage\framework\cache\data') -Recurse -File -ErrorAction SilentlyContinue | Where-Object Name -ne '.gitignore' | Remove-Item -Force
Get-ChildItem (Join-Path $app 'storage\framework\sessions') -File -ErrorAction SilentlyContinue | Where-Object Name -ne '.gitignore' | Remove-Item -Force

Write-Host 'Preparing public_html...'
robocopy (Join-Path $project 'public') $web /E /NFL /NDL /NJS /NP | Out-Null
$index = Join-Path $web 'index.php'
$content = Get-Content $index -Raw
$content = $content.Replace("__DIR__.'/../", "__DIR__.'/../$AppFolder/")
# public_html is not inside the app folder, so Laravel must be told where public assets live
$content = $content.Replace('$app->handleRequest(', "`$app->usePublicPath(__DIR__);`r`n`$app->handleRequest(")
[System.IO.File]::WriteAllText($index, $content)

Compress-Archive -Path (Join-Path $out '*') -DestinationPath $zip -Force
Write-Host "Done: $zip"
