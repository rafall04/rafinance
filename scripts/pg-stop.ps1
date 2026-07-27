# Menghentikan PostgreSQL 16 milik Rafin.

$ErrorActionPreference = 'Stop'

$PG   = 'C:\pgsql16'
$DATA = "$PG\data"

& "$PG\bin\pg_ctl.exe" -D $DATA status 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Output "PostgreSQL memang tidak sedang jalan."
    exit 0
}

Start-Process -FilePath "$PG\bin\pg_ctl.exe" `
    -ArgumentList '-D', $DATA, '-m', 'fast', 'stop' `
    -WindowStyle Hidden -Wait

Write-Output "PostgreSQL dihentikan."
