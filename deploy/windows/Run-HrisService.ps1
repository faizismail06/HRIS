param(
    [Parameter(Mandatory)][string]$PhpPath,
    [Parameter(Mandatory)][string]$ProjectPath,
    [Parameter(Mandatory)][ValidateSet('worker','scheduler')][string]$Service
)
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path -LiteralPath $ProjectPath).Path
$php = (Resolve-Path -LiteralPath $PhpPath).Path
if (-not (Test-Path -LiteralPath (Join-Path $root 'artisan'))) { throw 'Invalid HRIS working directory.' }
$arguments = if ($Service -eq 'worker') { @('artisan','queue:work','database','--queue=default','--sleep=3','--tries=3','--timeout=330','--max-time=3600','--memory=256') } else { @('artisan','schedule:work','--no-interaction') }
while ($true) {
    $child = Start-Process -FilePath $php -ArgumentList $arguments -WorkingDirectory $root -WindowStyle Hidden -Wait -PassThru
    if ($child.ExitCode -ne 0) { exit $child.ExitCode }
    Start-Sleep -Seconds 2
}
