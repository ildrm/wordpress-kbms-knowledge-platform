param(
    [string]$Version = '0.1.0'
)

$ErrorActionPreference = 'Stop'
$workspace = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$buildRoot = Join-Path $workspace 'build'
$stageRoot = Join-Path $buildRoot 'stage'
$pluginRoot = Join-Path $stageRoot 'wp-kbms'
$zipPath = Join-Path $buildRoot ("wp-kbms-{0}.zip" -f $Version)

if (-not $stageRoot.StartsWith($workspace + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw "Unsafe stage path: $stageRoot"
}

New-Item -ItemType Directory -Path $buildRoot -Force | Out-Null
if (Test-Path -LiteralPath $stageRoot) {
    $resolvedStage = (Resolve-Path -LiteralPath $stageRoot).Path
    if ($resolvedStage -ne $stageRoot) {
        throw "Unexpected stage target: $resolvedStage"
    }
    Remove-Item -LiteralPath $resolvedStage -Recurse -Force
}
New-Item -ItemType Directory -Path $pluginRoot -Force | Out-Null

$releaseFiles = @(
    'wp-kbms.php',
    'uninstall.php',
    'LICENSE',
    'README.md',
    'CHANGELOG.md',
    'src',
    'assets',
    'docs'
)
foreach ($entry in $releaseFiles) {
    Copy-Item -LiteralPath (Join-Path $workspace $entry) -Destination $pluginRoot -Recurse
}

if (Test-Path -LiteralPath $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}
Compress-Archive -LiteralPath $pluginRoot -DestinationPath $zipPath -CompressionLevel Optimal

Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $forbidden = $archive.Entries | Where-Object {
        $_.FullName -match '(^|/)(vendor|node_modules|tests|\.git|build|coverage|\.env)(/|$)' -or
        $_.FullName -match '\.(log|map)$'
    }
    if ($forbidden) {
        throw "Forbidden release entries: $($forbidden.FullName -join ', ')"
    }
    Write-Output ("Release: {0}" -f $zipPath)
    Write-Output ("Files: {0}" -f $archive.Entries.Count)
    Write-Output ("Bytes: {0}" -f (Get-Item -LiteralPath $zipPath).Length)
} finally {
    $archive.Dispose()
}
