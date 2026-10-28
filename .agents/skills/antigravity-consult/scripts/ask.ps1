param(
  [Parameter(Mandatory = $true, Position = 0)][string]$Prompt,
  [string]$Model = 'gemini-3.8-flash-medium'
)

# Antigravity Consult — ask Gemini/Claude through the owner's Google OAuth session.
# Usage:
#   $sb = [scriptblock]::Create((Get-Content -Raw -Encoding UTF8 '.agents/skills/antigravity-consult/scripts/ask.ps1'))
#   & $sb -Prompt "..." -Model gemini-3.8-flash-medium
#
# The CLI writes its session history under ~/.gemini, which the DSH sandbox denies;
# that only makes the JSON "status" report ERROR ("stream interrupted") while the
# answer itself is complete, so any non-empty `response` counts as success.

$agyCandidates = @(
  (Join-Path $env:LOCALAPPDATA 'agy\bin\agy.exe'),
  'C:\Users\nadya\AppData\Local\agy\bin\agy.exe'
)
$agy = $agyCandidates | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
if (-not $agy) {
  $cmd = Get-Command 'agy.exe' -ErrorAction SilentlyContinue
  if ($cmd) { $agy = $cmd.Source }
}
if (-not $agy) {
  Write-Output '[agy] not found: install Antigravity CLI and run `agy` once to sign in.'
  exit 3
}

$root = (Get-Location).Path
$tmp = Join-Path $root '_tmp'
if (-not (Test-Path $tmp)) { New-Item -ItemType Directory -Force -Path $tmp | Out-Null }
$log = Join-Path $tmp 'agy-last.log'
$outFile = Join-Path $tmp 'agy-out.json'

$raw = & $agy -p $Prompt --output-format json --model $Model --log-file $log 2>$null | Out-String
if (-not $raw -or -not $raw.Trim()) {
  Write-Output ("[agy] empty output. Log: " + $log)
  exit 2
}
[System.IO.File]::WriteAllText($outFile, $raw, [System.Text.UTF8Encoding]::new($false))

$json = $null
try { $json = $raw | ConvertFrom-Json } catch {}

if (-not $json) {
  Write-Output '[agy] no JSON result. Raw tail:'
  Write-Output (($raw.Trim() -split "`n") | Select-Object -Last 3)
  exit 2
}

$usage = $json.usage
if ($json.response -and $json.response.Trim()) {
  Write-Output $json.response.TrimEnd()
  Write-Output ''
  Write-Output ("[agy] model={0} status={1} {2}s tokens in/out={3}/{4}" -f $Model, $json.status, [int]$json.duration_seconds, $usage.input_tokens, $usage.output_tokens)
  if ($json.error -and $json.error -notmatch 'stream was interrupted') {
    Write-Output ('[agy] note: ' + $json.error)
  }
  exit 0
}

Write-Output ("[agy] empty response, status={0}, error={1}" -f $json.status, $json.error)
if ($json.error -match 'location is not supported') {
  Write-Output '[agy] hint: Google geo-restriction. Retry, or enable a VPN, then repeat.'
}
if ($json.error -match 'authentication') {
  Write-Output '[agy] hint: run `agy` once in a normal terminal and sign in again.'
}
exit 1
