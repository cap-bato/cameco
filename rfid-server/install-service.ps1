<#
.SYNOPSIS
    Registers the Cameco RFID Reader as a Windows service via NSSM.

.DESCRIPTION
    Run this script as Administrator on the gate PC.
    Prerequisites:
      - nssm.exe in PATH or C:\tools\nssm\nssm.exe
      - Python virtual environment already created (.venv\)
      - .env file filled in (API_URL, API_KEY, DEVICE_ID)

.EXAMPLE
    # Default — uses script's own directory as the rfid-server base
    .\install-service.ps1

    # Override base path and service account
    .\install-service.ps1 -BasePath "D:\apps\rfid-server" -ServiceUser ".\rfiduser" -ServicePass "SecureP@ss"

.NOTES
    IMPORTANT: You MUST run as the PC's actual user account, NOT LOCAL SYSTEM.
    pynput requires access to the interactive user's keyboard session.
#>

param (
    [string] $BasePath    = (Split-Path -Parent $MyInvocation.MyCommand.Path),
    [string] $ServiceName = 'CamecoRfidServer',
    [string] $NssmPath    = '',          # leave blank to auto-detect
    [string] $ServiceUser = '',          # e.g. ".\rfiduser"  or "DOMAIN\user"
    [string] $ServicePass = ''           # account password (required if ServiceUser is set)
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# ── Locate nssm ───────────────────────────────────────────────────────────────
if (-not $NssmPath) {
    $candidates = @('nssm', 'C:\tools\nssm\nssm.exe', 'C:\nssm\nssm.exe')
    foreach ($c in $candidates) {
        if (Get-Command $c -ErrorAction SilentlyContinue) { $NssmPath = $c; break }
    }
}
if (-not $NssmPath) {
    Write-Error "nssm.exe not found. Download from https://nssm.cc and place in PATH or C:\tools\nssm\"
    exit 1
}
Write-Host "[OK] nssm found: $NssmPath"

# ── Paths ─────────────────────────────────────────────────────────────────────
$python = Join-Path $BasePath '.venv\Scripts\python.exe'
$script = Join-Path $BasePath 'main.py'
$logsDir = Join-Path $BasePath 'logs'
$logFile = Join-Path $logsDir 'service.log'
$envFile = Join-Path $BasePath '.env'

if (-not (Test-Path $python))  { Write-Error "Virtual environment not found at: $python";  exit 1 }
if (-not (Test-Path $script))  { Write-Error "main.py not found at: $script";              exit 1 }
if (-not (Test-Path $envFile)) { Write-Error ".env not found at: $envFile — copy .env.example and fill it in"; exit 1 }

if (-not (Test-Path $logsDir)) { New-Item -ItemType Directory -Path $logsDir -Force | Out-Null }
Write-Host "[OK] Logs directory: $logsDir"

# ── Remove old service if it exists ──────────────────────────────────────────
$existing = & $NssmPath status $ServiceName 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "[INFO] Removing existing service '$ServiceName'..."
    & $NssmPath stop   $ServiceName confirm 2>&1 | Out-Null
    & $NssmPath remove $ServiceName confirm
}

# ── Install ───────────────────────────────────────────────────────────────────
Write-Host "[INFO] Installing service '$ServiceName'..."
& $NssmPath install $ServiceName $python $script

& $NssmPath set $ServiceName AppDirectory    $BasePath
& $NssmPath set $ServiceName DisplayName     'Cameco RFID Reader'
& $NssmPath set $ServiceName Description     'RFID badge reader for Cameco timekeeping'
& $NssmPath set $ServiceName Start           SERVICE_AUTO_START

# ── Logging ───────────────────────────────────────────────────────────────────
& $NssmPath set $ServiceName AppStdout       $logFile
& $NssmPath set $ServiceName AppStderr       $logFile
& $NssmPath set $ServiceName AppRotateFiles  1
& $NssmPath set $ServiceName AppRotateBytes  5242880   # 5 MB

# ── Auto-restart on crash (5 second delay) ───────────────────────────────────
& $NssmPath set $ServiceName AppRestartDelay 5000

# ── Service account ───────────────────────────────────────────────────────────
# CRITICAL: must run as the interactive user, not LOCAL SYSTEM.
# pynput only works when the service has access to the user's keyboard session.
if ($ServiceUser) {
    if (-not $ServicePass) { Write-Error "-ServicePass is required when -ServiceUser is provided"; exit 1 }
    & $NssmPath set $ServiceName ObjectName $ServiceUser $ServicePass
    Write-Host "[OK] Service account: $ServiceUser"
} else {
    Write-Warning @"
[WARN] No -ServiceUser supplied. Service will run as LOCAL SYSTEM.
       pynput WILL NOT capture keystrokes in that case.
       Re-run with: .\install-service.ps1 -ServiceUser '.\<username>' -ServicePass '<password>'
"@
}

# ── Start ─────────────────────────────────────────────────────────────────────
Write-Host "[INFO] Starting service..."
& $NssmPath start $ServiceName
Start-Sleep -Seconds 2
$status = & $NssmPath status $ServiceName 2>&1
Write-Host "[STATUS] $ServiceName : $status"

Write-Host ""
Write-Host "Done. Use these commands to manage the service:"
Write-Host "  nssm start   $ServiceName"
Write-Host "  nssm stop    $ServiceName    # graceful shutdown — device goes offline"
Write-Host "  nssm restart $ServiceName"
Write-Host "  nssm status  $ServiceName"
Write-Host "  nssm remove  $ServiceName confirm"
