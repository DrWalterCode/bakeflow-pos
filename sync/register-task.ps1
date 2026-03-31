param(
    [string]$TaskName = 'BakeFlowPOSSync',
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
)

$envFile = Join-Path $ProjectRoot '.env'
$intervalSeconds = 300

if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        if ($_ -match '^\s*SYNC_INTERVAL\s*=\s*(\d+)\s*$') {
            $intervalSeconds = [int]$Matches[1]
        }
    }
}

$minutes = [Math]::Max([int][Math]::Ceiling($intervalSeconds / 60.0), 1)
$runSync = Join-Path $ProjectRoot 'sync\run-sync.bat'

$action = New-ScheduledTaskAction -Execute $runSync
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1)
$trigger.Repetition = New-ScheduledTaskRepetitionSettingsSet -Interval (New-TimeSpan -Minutes $minutes) -Duration ([TimeSpan]::MaxValue)
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force | Out-Null
Write-Host "Registered $TaskName to run every $minutes minute(s)."
