$ErrorActionPreference = "Stop"
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root = "D:\xampp\htdocs\megasol-sms"
$staging = Join-Path $root "deploy\package\root"
$zipPath = Join-Path $root "deploy\megasol-deploy.zip"

if (Test-Path $staging) { Remove-Item $staging -Recurse -Force }
New-Item -ItemType Directory -Force -Path $staging | Out-Null

$excludeDirs = @("node_modules", ".git", "tests", "deploy", ".claude", ".cursor", "public")
$excludeFiles = @(".env", ".phpunit.result.cache")

Get-ChildItem -Path $root -Force | Where-Object {
    $name = $_.Name
    if ($excludeDirs -contains $name) { return $false }
    if ($_.PSIsContainer) { return $true }
    if ($excludeFiles -contains $name) { return $false }
    return $true
} | ForEach-Object {
    if ($_.PSIsContainer) {
        $dest = Join-Path $staging $_.Name
        & robocopy $_.FullName $dest /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
        if ($LASTEXITCODE -ge 8) {
            throw "robocopy failed for $($_.Name) (exit $LASTEXITCODE)"
        }
    } else {
        Copy-Item $_.FullName -Destination (Join-Path $staging $_.Name)
    }
}

robocopy (Join-Path $root "public") (Join-Path $staging "public") /E /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
if ($LASTEXITCODE -ge 8) {
    throw "robocopy failed for public (exit $LASTEXITCODE)"
}

Copy-Item (Join-Path $root "deploy\root\index.php") (Join-Path $staging "index.php") -Force
Copy-Item (Join-Path $root "deploy\root\.htaccess") (Join-Path $staging ".htaccess") -Force
Copy-Item (Join-Path $root "deploy\.env.production.example") (Join-Path $staging ".env.production") -Force

$setupDir = Join-Path $staging "setup"
New-Item -ItemType Directory -Force -Path $setupDir | Out-Null
Copy-Item (Join-Path $root "deploy\UPLOAD_INSTRUCTIONS.txt") (Join-Path $setupDir "UPLOAD_INSTRUCTIONS.txt") -Force

$sqlExport = Join-Path $root "deploy\megasol_sms.sql"
if (Test-Path $sqlExport) {
    Copy-Item $sqlExport (Join-Path $setupDir "megasol_sms.sql") -Force
}

Get-ChildItem (Join-Path $staging "bootstrap\cache") -Filter "*.php" -ErrorAction SilentlyContinue | Remove-Item -Force
Get-ChildItem (Join-Path $staging "storage\logs") -Filter "*.log" -ErrorAction SilentlyContinue | Remove-Item -Force
Get-ChildItem (Join-Path $staging "storage\framework\views") -Filter "*.php" -ErrorAction SilentlyContinue | Remove-Item -Force

$required = @(
    "index.php",
    "app",
    "bootstrap",
    "config",
    "database",
    "routes",
    "resources",
    "storage",
    "public\build",
    "vendor\composer\autoload_real.php",
    "vendor\symfony\deprecation-contracts\function.php",
    ".env.production"
)

$missing = @()
foreach ($rel in $required) {
    $path = Join-Path $staging $rel
    if (-not (Test-Path $path)) { $missing += $rel }
}
if ($missing.Count -gt 0) {
    throw "Staging incomplete. Missing:`n$($missing -join "`n")"
}

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
[System.IO.Compression.ZipFile]::CreateFromDirectory(
    $staging,
    $zipPath,
    [System.IO.Compression.CompressionLevel]::Optimal,
    $false
)

Remove-Item $staging -Recurse -Force

$zipSize = (Get-Item $zipPath).Length
Write-Output "Created $zipPath ($([math]::Round($zipSize/1MB, 2)) MB)"
Write-Output "Extract directly into subdomain root - files sit at top level (no extra folder)."
