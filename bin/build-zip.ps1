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
	# Si scende nelle cartelle una per volta e si saltano subito quelle escluse (docs/ contiene
	# strumenti con percorsi lunghissimi che Windows non riesce a leggere).
	function Add-Folder([string] $folder) {
		foreach ($item in Get-ChildItem -LiteralPath $folder -Force) {
			$relative = $item.FullName.Substring($root.Length).TrimStart('\', '/').Replace('\', '/')
			if ($item.PSIsContainer) {
				$skip = $false
				foreach ($dir in $excludeDirs) {
					if ($relative -eq $dir -or $relative.StartsWith("$dir/")) { $skip = $true }
				}
				if (-not $skip) { Add-Folder $item.FullName }
				continue
			}
			if ($excludeFiles -contains $relative) { continue }
			if ($item.Extension -eq '.zip' -or $item.Name -eq '.gitkeep') { continue }
			[void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $item.FullName, "$slug/$relative", [System.IO.Compression.CompressionLevel]::Optimal)
			$script:included++
		}
	}
	Add-Folder $root
}
finally {
	$zip.Dispose()
}

$sizeKb = [math]::Round((Get-Item $zipPath).Length / 1KB)
Write-Output "Creato: $zipPath"
Write-Output "File inclusi: $included - Dimensione: $sizeKb KB - Versione: $version"
