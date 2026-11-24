# Mendaftarkan backup harian SI GALUH ke Windows Task Scheduler (23:15 setiap hari).
# Jalankan sekali: powershell -ExecutionPolicy Bypass -File scripts\backup\register-task.ps1
$ErrorActionPreference = 'Stop'

$taskName = 'SI GALUH - Backup Harian'
$batPath  = Join-Path $PSScriptRoot 'run-backup.bat'

$action   = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument ('/c ""{0}""' -f $batPath)
$trigger  = New-ScheduledTaskTrigger -Daily -At '23:15'
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Hours 2) `
    -MultipleInstances IgnoreNew `
    -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -WakeToRun

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger `
    -Settings $settings `
    -Description 'Backup database + foto SI GALUH ke Google Drive (23:15 setiap hari)' `
    -Force | Out-Null

Get-ScheduledTask -TaskName $taskName | Format-List TaskName, State
