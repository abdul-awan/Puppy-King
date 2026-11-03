# Makes a ZIP of this app to upload to shared hosting (Hostinger).
#
#   1. Builds the frontend (npm run build -> public\build).
#   2. Copies the app to a temporary folder WITHOUT what must not go to the server:
#      .env (your local passwords and key), node_modules, vendor (Composer installs it on the server),
#      public\hot (it would make the live site load its scripts from your own PC), tests, the local
#      SQLite file, logs, caches, sessions, compiled views and files uploaded locally (logos, attachments).
#   3. Adds deploy\hostinger.env.example as .env.hostinger.example and makes the ZIP
#      (with "/" in the paths, so it unpacks correctly on the Linux server).
#
# Run it in PowerShell in the project folder (C:\wamp64\www\billing):
#   powershell -ExecutionPolicy Bypass -File deploy\package-for-hosting.ps1
# The result is deploy\billing-upload.zip. Nothing in the project itself is changed (apart from the fresh build).

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$zip = Join-Path $PSScriptRoot 'billing-upload.zip'
$stage = Join-Path ([System.IO.Path]::GetTempPath()) ('billing-upload-' + [guid]::NewGuid().ToString('N'))

Set-Location $root

Write-Output '1) Building the frontend (npm run build) ...'
& npm run build
if ($LASTEXITCODE -ne 0) { throw 'npm run build failed; nothing was packed.' }
if (-not (Test-Path (Join-Path $root 'public\build\manifest.json'))) { throw 'public\build\manifest.json is missing after the build.' }

try {
    Write-Output '2) Copying the app to a temporary folder ...'
    robocopy $root $stage /E /R:1 /W:1 /NFL /NDL /NJH /NJS /NP `
        /XD node_modules vendor .git .idea .vscode tests deploy `
        /XF .env .env.backup hot database.sqlite .phpunit.result.cache '*.log' | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "Copying failed (robocopy code $LASTEXITCODE)." }

    # Empty storage and bootstrap\cache, keeping their .gitignore files (the folders must exist on the server).
    foreach ($folder in 'storage', 'bootstrap\cache') {
        Get-ChildItem (Join-Path $stage $folder) -Recurse -File -Force |
            Where-Object { $_.Name -ne '.gitignore' } |
            Remove-Item -Force
    }

    # Remove the folders that only local work made (e.g. storage\app\private\teams), deepest first.
    Get-ChildItem (Join-Path $stage 'storage') -Recurse -Directory -Force |
        Sort-Object { $_.FullName.Length } -Descending |
        Where-Object { -not (Get-ChildItem $_.FullName -Recurse -File -Force) } |
        Remove-Item -Recurse -Force

    Copy-Item (Join-Path $PSScriptRoot 'hostinger.env.example') (Join-Path $stage '.env.hostinger.example')

    Write-Output '3) Making the ZIP ...'
    Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem
    if (Test-Path $zip) { Remove-Item $zip -Force }

    $archive = [System.IO.Compression.ZipFile]::Open($zip, 'Create')
    try {
        foreach ($file in Get-ChildItem $stage -Recurse -File -Force) {
            $name = $file.FullName.Substring($stage.Length + 1).Replace('\', '/')
            [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.FullName, $name, 'Optimal')
        }
    }
    finally {
        $archive.Dispose()
    }

    Write-Output '4) Checking the ZIP ...'
    $archive = [System.IO.Compression.ZipFile]::OpenRead($zip)
    try {
        $names = @($archive.Entries | ForEach-Object { $_.FullName })
        $forbidden = $names | Where-Object {
            $_ -match '\\' -or $_ -eq '.env' -or $_ -eq 'public/hot' -or $_ -like 'vendor/*' -or
            $_ -like 'node_modules/*' -or $_ -like 'tests/*' -or $_ -like 'storage/logs/*.log' -or $_ -like '*.sqlite'
        }
        if ($forbidden) { throw "These must not be in the ZIP: $($forbidden -join ', ')" }

        foreach ($required in 'artisan', 'composer.json', 'composer.lock', 'public/index.php', 'public/.htaccess',
            'public/build/manifest.json', 'storage/framework/views/.gitignore', 'bootstrap/cache/.gitignore', '.env.hostinger.example') {
            if ($names -notcontains $required) { throw "Missing in the ZIP: $required" }
        }

        Write-Output ('   OK: {0} files, {1:N1} MB -> {2}' -f $names.Count, ((Get-Item $zip).Length / 1MB), $zip)
    }
    finally {
        $archive.Dispose()
    }
}
finally {
    if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
}
