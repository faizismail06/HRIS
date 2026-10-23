[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$PhpPath,
    [string]$ProjectPath = (Join-Path $PSScriptRoot '../..'),
    [switch]$Install
)
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path -LiteralPath $ProjectPath).Path
$php = (Resolve-Path -LiteralPath $PhpPath).Path
if (-not (Test-Path -LiteralPath (Join-Path $root 'artisan'))) { throw 'Project path must contain artisan.' }
if ([IO.Path]::GetFileName($php) -ne 'php.exe') { throw 'Use the PHP CLI executable.' }
$hasher = [Security.Cryptography.SHA256]::Create()
try { $suffix = ([BitConverter]::ToString($hasher.ComputeHash([Text.Encoding]::UTF8.GetBytes($root.ToLowerInvariant() + '|' + [Security.Principal.WindowsIdentity]::GetCurrent().User.Value)))).Replace('-','').Substring(0,12) } finally { $hasher.Dispose() }
$names = @("HRIS-$suffix-Worker", "HRIS-$suffix-Scheduler")
if (-not $Install) {
    [pscustomobject]@{Project=$root; PHP=$php; Tasks=$names -join ', '; Mode='Preview; use -Install to register only these project tasks'}
    return
}
$account = [Security.Principal.WindowsIdentity]::GetCurrent().Name
$principal = New-ScheduledTaskPrincipal -UserId $account -LogonType Interactive -RunLevel Limited
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero)
$runner = Join-Path $PSScriptRoot 'Run-HrisService.ps1'
$shell = (Get-Command powershell.exe -ErrorAction Stop).Source
$common = '-NoProfile -NonInteractive -WindowStyle Hidden -File "{0}" -PhpPath "{1}" -ProjectPath "{2}" -Service ' -f $runner,$php,$root
$worker = New-ScheduledTaskAction -Execute $shell -Argument ($common + 'worker') -WorkingDirectory $root
$scheduler = New-ScheduledTaskAction -Execute $shell -Argument ($common + 'scheduler') -WorkingDirectory $root
$trigger = New-ScheduledTaskTrigger -AtLogOn -User $account
foreach ($name in $names) {
    $old = Get-ScheduledTask -TaskName $name -ErrorAction SilentlyContinue
    if ($old -and $old.Actions.WorkingDirectory -ne $root) { throw 'Existing task belongs to a different working directory; refusing replacement.' }
}
Register-ScheduledTask -TaskName $names[0] -Action $worker -Trigger $trigger -Settings $settings -Principal $principal -Description 'Dedicated HRIS worker; current-user development session only.' -Force | Out-Null
Register-ScheduledTask -TaskName $names[1] -Action $scheduler -Trigger $trigger -Settings $settings -Principal $principal -Description 'Dedicated HRIS scheduler; current-user development session only.' -Force | Out-Null
$names | ForEach-Object { Start-ScheduledTask -TaskName $_ }
Write-Output 'Registered and started only the two HRIS project tasks. They require this Windows user to be signed in.'
