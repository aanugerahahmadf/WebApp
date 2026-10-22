<#
.SYNOPSIS
    Regenerates the PHP protobuf message classes for the wedding.user.v1 API.

.DESCRIPTION
    Runs protoc against every contract in proto/wedding/user/v1 and copies the
    generated App\Grpc\User\V1 tree into app/Grpc/User/V1.

    protoc honours `option php_namespace`, so it emits into
    <out>\App\Grpc\User\V1. We generate into a staging directory first and then
    move the tree into place, because generating straight into app/ would
    produce app/App/Grpc/... .

.PARAMETER Protoc
    Path to protoc.exe. Falls back to PATH lookup when omitted.

.EXAMPLE
    powershell -File scripts\generate-protos.ps1
#>
[CmdletBinding()]
param(
    [string]$Protoc = $env:PROTOC_PATH
)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$protoRoot = Join-Path $root 'proto'
$stage = Join-Path $root 'storage/framework/grpc-proto-stage'
$target = Join-Path $root 'app/Grpc/User/V1'

if (-not $Protoc) {
    $cmd = Get-Command protoc -ErrorAction SilentlyContinue
    if ($cmd) { $Protoc = $cmd.Source }
}

if (-not $Protoc -or -not (Test-Path $Protoc)) {
    throw @"
protoc was not found.
Install it from https://github.com/protocolbuffers/protobuf/releases
then either add it to PATH or set:
    `$env:PROTOC_PATH = 'C:\path\to\protoc.exe'
"@
}

Write-Host "protoc: $Protoc"
& $Protoc --version

if (Test-Path $stage) { Remove-Item -Recurse -Force $stage }
New-Item -ItemType Directory -Force -Path $stage | Out-Null

# protos/wedding/user/v1 is the only active contract set
$files = Get-ChildItem -Path (Join-Path $protoRoot 'wedding/user/v1') -Filter '*.proto' |
    ForEach-Object { "wedding/user/v1/$($_.Name)" }

Write-Host "compiling $($files.Count) contract(s)..."

# protoc writes unused-import warnings to stderr; capture them instead of
# letting PowerShell promote them to terminating errors.
$protocWarnings = @()

Push-Location $protoRoot
try {
    # 'Stop' would turn protoc's unused-import warnings on stderr into
    # terminating errors, so relax it for the duration of the call.
    $ErrorActionPreference = 'Continue'
    $protocWarnings = & $Protoc -I . --php_out=$stage @files 2>&1
    $protocExit = $LASTEXITCODE
    $ErrorActionPreference = 'Stop'

    if ($protocExit -ne 0) {
        $protocWarnings | ForEach-Object { Write-Host "  $_" }
        throw "protoc failed with exit code $protocExit"
    }
} finally {
    Pop-Location
}

$unused = $protocWarnings | Where-Object { $_ -match 'warning:' }
if ($unused) { Write-Host "($($unused.Count) unused-import warning(s) suppressed)" }

$generated = Join-Path $stage 'App/Grpc/User/V1'
if (-not (Test-Path $generated)) {
    throw "protoc produced no output at $generated"
}

New-Item -ItemType Directory -Force -Path $target | Out-Null
Copy-Item "$generated/*" -Destination $target -Recurse -Force

$count = (Get-ChildItem -Path $target -Filter '*.php' -File).Count
$meta = (Get-ChildItem -Path (Join-Path $target 'GPBMetadata') -Filter '*.php' -ErrorAction SilentlyContinue).Count

Remove-Item -Recurse -Force $stage -ErrorAction SilentlyContinue

Write-Host ""
Write-Host "generated $count message class(es) + $meta GPBMetadata file(s)"
Write-Host "-> $target"