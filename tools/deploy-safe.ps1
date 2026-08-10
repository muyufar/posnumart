# Deploy aman ke pos.numartmagelang.com
# - Hanya daftar / siapkan file kode yang berubah
# - Tidak menghapus file unik di server
# - Tidak menyertakan secret & folder runtime
#
# Contoh:
#   .\tools\deploy-safe.ps1
#   .\tools\deploy-safe.ps1 -Since "HEAD~5"
#   .\tools\deploy-safe.ps1 -Since "482d657" -Zip

param(
    [string]$Since = "HEAD~10",
    [switch]$Zip,
    [switch]$IncludeUntracked
)

$ErrorActionPreference = "Stop"
$root = Resolve-Path (Join-Path $PSScriptRoot "..")
Set-Location $root

function Get-GitNames([string[]]$GitArgs) {
    $prev = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    try {
        $out = & git @GitArgs 2>$null
        if ($LASTEXITCODE -ne 0) { return @() }
        return @($out | Where-Object { $_ -and $_.Trim() -ne "" })
    }
    finally {
        $ErrorActionPreference = $prev
    }
}

$outDir = Join-Path $root "tools\deploy-out"
New-Item -ItemType Directory -Force -Path $outDir | Out-Null

$neverUpload = @(
    "aksi/koneksi.php",
    "aksi/sync-db-remote.config.php",
    "aksi/marketplace-config.php",
    "api/wa-cron-key.php",
    "api/wa-official.config.php",
    "api/wa-app.config.php",
    "api/wa-gateway.config.php",
    "api/sync-db-export.config.php",
    "wa-engine/.env",
    "midtrans-api/.env"
)

$neverPrefix = @(
    "uploads/barang/",
    "aksi/sync-db-cache/",
    "backups/",
    "database/",
    "db/",
    ".git/",
    ".vscode/",
    ".claw/",
    "memory/",
    "wa-engine/node_modules/",
    "wa-engine/sessions/",
    "midtrans-api/node_modules/",
    "node_modules/",
    "tests/",
    "tools/",
    "openclaw-workspace-state.json"
)

function Test-ShouldSkip([string]$rel) {
    $norm = $rel -replace "\\", "/"
    foreach ($f in $neverUpload) {
        if ($norm -eq $f) { return $true }
    }
    foreach ($p in $neverPrefix) {
        if ($norm.StartsWith($p)) { return $true }
    }
    if ($norm -match '(^|/ )error_log$' -or $norm -match '\.sql$') { return $true }
    if ($norm -eq "openclaw-workspace-state.json") { return $true }
    if ($norm -match '\.(md|log|zip)$' -and $norm -notmatch '^docs/') { return $true }
    return $false
}

Write-Host "== Deploy aman numart ==" -ForegroundColor Cyan
Write-Host "Target domain : pos.numartmagelang.com"
Write-Host "Remote path   : /public_html"
Write-Host "Base commit   : $Since"
Write-Host ""

$changed = @()
$changed += Get-GitNames @("diff", "--name-only", "--diff-filter=ACMRT", "$Since", "HEAD")
$changed += Get-GitNames @("diff", "--name-only", "--diff-filter=ACMRT")
$changed += Get-GitNames @("diff", "--cached", "--name-only", "--diff-filter=ACMRT")

if ($IncludeUntracked) {
    $changed += Get-GitNames @("ls-files", "--others", "--exclude-standard")
}

$files = $changed |
    Where-Object { $_ -and (Test-Path $_) } |
    ForEach-Object { ($_ -replace "\\", "/") } |
    Where-Object { -not (Test-ShouldSkip $_) } |
    Sort-Object -Unique

$listPath = Join-Path $outDir "upload-list.txt"
$files | Set-Content -Encoding UTF8 $listPath

Write-Host "Checklist sebelum upload:" -ForegroundColor Yellow
Write-Host "1) hPanel Hostinger -> Files -> Backup (atau download zip public_html)"
Write-Host "2) Pastikan JANGAN Sync Remote->Local / Sync dengan Delete"
Write-Host "3) Upload HANYA file di daftar berikut (SFTP: Upload File)"
Write-Host "4) Folder unik server (uploads, config production) dibiarkan utuh"
Write-Host ""

if ($files.Count -eq 0) {
    Write-Host "Tidak ada file kode yang berubah untuk di-upload." -ForegroundColor DarkYellow
    Write-Host "Coba: .\tools\deploy-safe.ps1 -Since HEAD~20 -IncludeUntracked"
    exit 0
}

Write-Host ("File siap upload ({0}):" -f $files.Count) -ForegroundColor Green
$files | ForEach-Object { Write-Host "  - $_" }
Write-Host ""
Write-Host "Daftar tersimpan: $listPath"

if ($Zip) {
    $stamp = Get-Date -Format "yyyyMMdd_HHmmss"
    $zipPath = Join-Path $outDir "numart_patch_$stamp.zip"
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

    $stage = Join-Path $outDir ("stage_" + $stamp)
    New-Item -ItemType Directory -Force -Path $stage | Out-Null
    try {
        foreach ($rel in $files) {
            $src = Join-Path $root ($rel -replace "/", "\")
            $dst = Join-Path $stage ($rel -replace "/", "\")
            $dstDir = Split-Path -Parent $dst
            if (-not (Test-Path $dstDir)) {
                New-Item -ItemType Directory -Force -Path $dstDir | Out-Null
            }
            Copy-Item -LiteralPath $src -Destination $dst -Force
        }
        Compress-Archive -Path (Join-Path $stage "*") -DestinationPath $zipPath -Force
    }
    finally {
        Remove-Item -LiteralPath $stage -Recurse -Force -ErrorAction SilentlyContinue
    }

    Write-Host ""
    Write-Host "Patch zip dibuat: $zipPath" -ForegroundColor Green
    Write-Host "Extract zip ini ke public_html (merge), JANGAN kosongkan folder dulu."
}

Write-Host ""
Write-Host "Cara upload di Cursor (aman):" -ForegroundColor Cyan
Write-Host "- Pasang ekstensi SFTP"
Write-Host "- Isi password di .vscode/sftp.json bila perlu"
Write-Host "- Klik kanan tiap file di daftar -> Upload"
Write-Host "- Atau extract patch zip via File Manager Hostinger ke public_html"
Write-Host "- Jangan pakai 'Sync Local -> Remote' dengan delete"
