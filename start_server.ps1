$dir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$phpExe = Join-Path $dir "php\php.exe"
$siteDir = Join-Path $dir "site"

Write-Host "Starting PHP server at http://localhost:8080 ..."
Write-Host "PHP Exe: $phpExe"
Write-Host "Site Dir: $siteDir"

Set-Location $siteDir
& $phpExe -S localhost:8080
