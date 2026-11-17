<#
.SYNOPSIS
  Prepara lo zip di rilascio del plugin, pronto da caricare dal File Manager di WePanel
  in wp-content/plugins/ (e da attivare dall'amministrazione).

.DESCRIPTION
  Lo zip contiene la cartella "academy-elearning/" con solo ciò che serve in produzione.
  Restano FUORI: documenti (docs/), script di sviluppo (bin/), CLAUDE.md, .git, il modulo di
  prova e la pagina "Prova accessi".
  I nomi dentro lo zip usano la barra "/" (Compress-Archive di Windows PowerShell 5.1 usa
  "\" e si rompe sui server Linux: per questo si usa .NET).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File bin\build-zip.ps1
#>
[CmdletBinding()]
param(
	[string] $OutputDir = ''
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$slug = 'academy-elearning'

# Versione letta dall'intestazione del plugin.
$header = Get-Content -Raw -Path (Join-Path $root 'academy-elearning.php')
if ($header -notmatch '(?m)^\s*\*\s*Version:\s*(\S+)') { throw 'Versione non trovata in academy-elearning.php' }
$version = $Matches[1]

if ($OutputDir -eq '') { $OutputDir = Join-Path $root 'dist' }
New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null
$zipPath = Join-Path $OutputDir ("$slug-$version.zip")
if (Test-Path $zipPath) { [System.IO.File]::Delete($zipPath) }

# Cosa NON va nello zip (percorsi relativi alla radice del plugin, con "/").
$excludeDirs  = @('.git', '.github', 'docs', 'bin', 'dist', 'node_modules', 'tests', 'modules/esempio-di-prova')
$excludeFiles = @('CLAUDE.md', 'README.md', '.gitignore', '.gitattributes', 'admin/class-admin-access-debug.php', 'Thumbs.db', '.DS_Store')

$included = 0
$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
	Get-ChildItem -Path $root -Recurse -File -Force | ForEach-Object {
		$relative = $_.FullName.Substring($root.Length).TrimStart('\', '/').Replace('\', '/')
		foreach ($dir in $excludeDirs) {
			if ($relative -eq $dir -or $relative.StartsWith("$dir/")) { return }
		}
		if ($excludeFiles -contains $relative) { return }
		if ($_.Extension -eq '.zip' -or $_.Name -eq '.gitkeep') { return }
		[void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, "$slug/$relative", [System.IO.Compression.CompressionLevel]::Optimal)
		$script:included++
	}
}
finally {
	$zip.Dispose()
}

$sizeKb = [math]::Round((Get-Item $zipPath).Length / 1KB)
Write-Output "Creato: $zipPath"
Write-Output "File inclusi: $included - Dimensione: $sizeKb KB - Versione: $version"
