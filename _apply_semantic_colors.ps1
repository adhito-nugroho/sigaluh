$files = Get-ChildItem -Path 'd:\laragon\www\sigaluh2\pages', 'd:\laragon\www\sigaluh2\includes', 'd:\laragon\www\sigaluh2\index.php' -Recurse -Include '*.php'
foreach ($file in $files) {
    $content = [System.IO.File]::ReadAllText($file.FullName)
    
    # Simple search & replace for surface -> neutral and brand -> primary
    $content = $content -replace 'surface-', 'neutral-'
    $content = $content -replace 'brand-', 'primary-'
    
    # Fixing Semantic colors that might have been hardcoded as green/red/amber/indigo
    $content = $content -replace 'bg-green-', 'bg-success-'
    $content = $content -replace 'text-green-', 'text-success-'
    $content = $content -replace 'border-green-', 'border-success-'
    
    $content = $content -replace 'bg-red-', 'bg-error-'
    $content = $content -replace 'text-red-', 'text-error-'
    $content = $content -replace 'border-red-', 'border-error-'
    
    $content = $content -replace 'bg-amber-', 'bg-warning-'
    $content = $content -replace 'text-amber-', 'text-warning-'
    $content = $content -replace 'border-amber-', 'border-warning-'
    
    $content = $content -replace 'bg-indigo-', 'bg-info-'
    $content = $content -replace 'text-indigo-', 'text-info-'
    $content = $content -replace 'border-indigo-', 'border-info-'

    [System.IO.File]::WriteAllText($file.FullName, $content)
}
Write-Host "Batch replace done."
