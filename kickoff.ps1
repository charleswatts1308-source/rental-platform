# renters.rent — local dev kickoff
#
# Runs three long-running processes in this same terminal, multiplexing their
# output:
#   1. mailpit               (SMTP catcher on :1025, web UI on :8025)
#   2. php artisan serve     (Laravel dev server on :8000)
#   3. php artisan queue:work (drains queued mail to Mailpit)
#
# Press Ctrl+C in this terminal to stop all three.

$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

# Idempotent: makes sure local .ps1 scripts can run on this account. Silently
# swallows the "already set" or Group-Policy-controlled cases.
try {
    Set-ExecutionPolicy -Scope CurrentUser -ExecutionPolicy RemoteSigned -Force -ErrorAction Stop
} catch {
    # Already permissive or locked by policy — either way fine for our purposes.
}

# Sanity-check the binaries are on PATH before we start anything.
foreach ($cmd in @('mailpit', 'php')) {
    if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) {
        Write-Error "$cmd is not on PATH. Install it (e.g. 'winget install axllent.mailpit' for mailpit) or add its folder to your user PATH, then restart this terminal."
        exit 1
    }
}

$processes = @()
$processes += Start-Process -FilePath 'mailpit' -PassThru -NoNewWindow
$processes += Start-Process -FilePath 'php' -ArgumentList 'artisan','serve' -PassThru -NoNewWindow
$processes += Start-Process -FilePath 'php' -ArgumentList 'artisan','queue:work' -PassThru -NoNewWindow

Write-Host ''
Write-Host 'Started: mailpit  /  php artisan serve  /  php artisan queue:work'
Write-Host '  Mailpit UI : http://localhost:8025'
Write-Host '  Laravel    : http://localhost:8000'
Write-Host 'Ctrl+C in this terminal to stop all three.'
Write-Host ''

try {
    Wait-Process -Id ($processes | ForEach-Object { $_.Id }) -ErrorAction SilentlyContinue
}
finally {
    foreach ($p in $processes) {
        if ($p -and -not $p.HasExited) {
            try { Stop-Process -Id $p.Id -Force -ErrorAction SilentlyContinue } catch {}
        }
    }
    Write-Host 'Stopped.'
}
