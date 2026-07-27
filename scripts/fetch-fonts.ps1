$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

$dest = 'C:\project\rafin\resources\fonts'
New-Item -ItemType Directory -Force $dest | Out-Null

# User agent modern supaya Google Fonts mengirim woff2, bukan ttf.
$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'

$families = @(
    @{ name = 'Public Sans';   query = 'Public+Sans:ital,wght@0,400..700;1,400..700'; slug = 'public-sans' },
    @{ name = 'IBM Plex Mono'; query = 'IBM+Plex+Mono:wght@500;600';                  slug = 'ibm-plex-mono' }
)

$faces = @()

foreach ($family in $families) {
    $url = "https://fonts.googleapis.com/css2?family=$($family.query)&display=swap"
    $css = (Invoke-WebRequest -Uri $url -UserAgent $ua -UseBasicParsing -TimeoutSec 60).Content

    # Blok @font-face didahului komentar subset: /* latin */, /* cyrillic */, dst.
    $blocks = [regex]::Matches($css, '/\*\s*([a-z\-]+)\s*\*/\s*(@font-face\s*\{[^}]*\})')

    foreach ($block in $blocks) {
        $subset = $block.Groups[1].Value
        if ($subset -ne 'latin') { continue }   # subset latin saja — kuota itu nyata

        $body = $block.Groups[2].Value

        $src = [regex]::Match($body, "url\((https://[^)]+\.woff2)\)").Groups[1].Value
        if (-not $src) { continue }

        $weight = [regex]::Match($body, 'font-weight:\s*([^;]+);').Groups[1].Value.Trim()
        $style  = [regex]::Match($body, 'font-style:\s*([^;]+);').Groups[1].Value.Trim()
        $range  = [regex]::Match($body, 'unicode-range:\s*([^;]+);').Groups[1].Value.Trim()

        $suffix = if ($style -eq 'italic') { '-italic' } else { '' }
        $weightSlug = ($weight -replace '\s+', '-')
        $file = "$($family.slug)-$weightSlug$suffix.woff2"

        Invoke-WebRequest -Uri $src -OutFile "$dest\$file" -UseBasicParsing -TimeoutSec 120

        $faces += [pscustomobject]@{
            family = $family.name
            file   = $file
            weight = $weight
            style  = $style
            range  = $range
            size   = [math]::Round((Get-Item "$dest\$file").Length / 1KB, 1)
        }
    }
}

$faces | Format-Table family, file, weight, style, size -AutoSize

# CSS @font-face lokal
$lines = @(
    '/*'
    ' * Font di-host sendiri, bukan dari CDN pihak ketiga.'
    ' *'
    ' * Dua alasan yang sama pentingnya: permintaan ke domain lain berarti satu'
    ' * DNS lookup dan satu handshake TLS tambahan di jaringan yang memang sudah'
    ' * lambat, dan setiap permintaan itu memberi tahu pihak ketiga siapa membuka'
    ' * pembukuannya kapan.'
    ' *'
    ' * Subset latin saja, dan font-display: swap supaya teks tampil lebih dulu'
    ' * memakai font sistem daripada menahan layar tetap kosong.'
    ' *'
    ' * Dihasilkan oleh scripts/fetch-fonts.ps1 — jangan diubah tangan.'
    ' */'
    ''
)

foreach ($face in $faces) {
    $lines += '@font-face {'
    $lines += "    font-family: '$($face.family)';"
    $lines += "    font-style: $($face.style);"
    $lines += "    font-weight: $($face.weight);"
    $lines += '    font-display: swap;'
    $lines += "    src: url('../fonts/$($face.file)') format('woff2');"
    $lines += "    unicode-range: $($face.range);"
    $lines += '}'
    $lines += ''
}

[System.IO.File]::WriteAllLines('C:\project\rafin\resources\css\fonts.css', $lines)
Write-Output "resources/css/fonts.css ditulis ($($faces.Count) @font-face)"

$total = ($faces | Measure-Object -Property size -Sum).Sum
Write-Output "Total bobot font: $total KB"
