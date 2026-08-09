[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$targetDir = "$env:USERPROFILE\php_portable"
$zipPath = "$env:USERPROFILE\php_portable.zip"

if (-not (Test-Path $targetDir)) {
    New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
}

Write-Host "Fetching PHP archives directory..."
$web = Invoke-WebRequest -Uri "https://windows.php.net/downloads/releases/archives/" -UseBasicParsing

$regex = [regex]'href="(php-8\.\d+\.\d+-nts-Win32-vs\d+-x64\.zip)"'
$matches = $regex.Matches($web.Content)

if ($matches.Count -gt 0) {
    $zipName = $matches[$matches.Count - 1].Groups[1].Value
    $downloadUrl = "https://windows.php.net/downloads/releases/archives/" + $zipName
} else {
    $downloadUrl = "https://windows.php.net/downloads/releases/archives/php-8.2.12-nts-Win32-vs16-x64.zip"
}

Write-Host "Selected PHP Download URL: $downloadUrl"
Write-Host "Downloading..."
Invoke-WebRequest -Uri $downloadUrl -OutFile $zipPath -UserAgent "Mozilla/5.0"

Write-Host "Extracting to $targetDir..."
Expand-Archive -Path $zipPath -DestinationPath $targetDir -Force
Remove-Item $zipPath -Force

Write-Host "Configuring php.ini..."
$iniPath = Join-Path $targetDir "php.ini"
if (-not (Test-Path $iniPath)) {
    $devIni = Join-Path $targetDir "php.ini-development"
    if (Test-Path $devIni) {
        Copy-Item $devIni $iniPath
    }
}

Write-Host "SUCCESS! PHP Portable is ready at $targetDir"
