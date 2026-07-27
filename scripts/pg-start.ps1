# Menjalankan PostgreSQL 16 milik Rafin (port 5433).
#
# Kenapa Start-Process dan bukan pemanggilan langsung: di Windows, postmaster
# mewarisi handle stdout milik shell pemanggil, sehingga `pg_ctl -w start`
# tidak pernah kembali selama server hidup. Mengarahkan output ke berkas
# memutus pewarisan itu.

$ErrorActionPreference = 'Stop'

$PG   = 'C:\pgsql16'
$DATA = "$PG\data"

& "$PG\bin\pg_ctl.exe" -D $DATA status 2>&1 | Out-Null
if ($LASTEXITCODE -eq 0) {
    Write-Output "PostgreSQL sudah jalan di port 5433."
    exit 0
}

Start-Process -FilePath "$PG\bin\pg_ctl.exe" `
    -ArgumentList '-D', $DATA, '-l', "$PG\pg.log", 'start' `
    -WindowStyle Hidden

foreach ($attempt in 1..30) {
    & "$PG\bin\pg_ctl.exe" -D $DATA status 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) {
        Write-Output "PostgreSQL jalan di port 5433."
        exit 0
    }
    Start-Sleep -Milliseconds 300
}

Write-Error "PostgreSQL tidak kunjung siap. Lihat $PG\pg.log"
exit 1
