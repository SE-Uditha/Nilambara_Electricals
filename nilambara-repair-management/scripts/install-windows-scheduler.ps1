param([string]$PhpBinary = 'D:\php-8.5.11\php.exe')
$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $PhpBinary -PathType Leaf)) { throw 'PHP executable not found.' }
$applicationRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$runner = (Resolve-Path (Join-Path $PSScriptRoot 'run-scheduler.ps1')).Path
$taskName = 'Nilambara-Laravel-Scheduler'
$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existingTask) {
    if ($existingTask.Actions.Arguments -notlike ('*'+$runner+'*')) { throw 'An unrelated task already uses this name. No task was changed.' }
    Write-Output 'The Nilambara scheduler task already exists.'
    exit 0
}
$arguments = '-NoProfile -WindowStyle Hidden -File "'+$runner+'" -PhpBinary "'+$PhpBinary+'"'
$action = New-ScheduledTaskAction -Execute (Join-Path $PSHOME 'powershell.exe') -Argument $arguments -WorkingDirectory $applicationRoot
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal = New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -Hidden -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 180)
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description 'Run this application Laravel scheduler every minute; application policy performs daily 02:00 Asia/Colombo backups.' | Out-Null
Write-Output 'Installed one scheduler task. Windows must be running, this account logged in, and MySQL available. For unattended logged-off operation, configure a dedicated service account in Task Scheduler.'
