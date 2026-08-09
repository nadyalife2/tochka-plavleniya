$ErrorActionPreference = "Continue"

$servers = @(
    "C:\mcp_servers\ui-expert-mcp\dist\index.js",
    "C:\mcp_servers\ux-mcp-server\dist\index.js",
    "C:\mcp_servers\MCP-Stack-for-UI-UX-Designers\inspire-mcp\build\index.js"
)

$allOk = $true
foreach ($s in $servers) {
    if (Test-Path $s) {
        Write-Host "[SUCCESS] Built file exists: $s"
    } else {
        Write-Host "[ERROR] Built file missing: $s"
        $allOk = $false
    }
}

if ($allOk) {
    Write-Host "`n🎉 Active UI/UX MCP Servers (without Figma) ready!"
}
