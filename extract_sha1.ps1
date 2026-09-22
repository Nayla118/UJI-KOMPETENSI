$keystorePath = "$env:USERPROFILE\.android\debug.keystore"
$password = "android"
$alias = "androiddebugkey"

Write-Host "Extracting SHA1 from debug keystore..."
Write-Host "Keystore: $keystorePath"

if (Test-Path $keystorePath) {
    # Extract SHA1 using keytool
    $output = keytool -list -v -keystore $keystorePath -alias $alias -storepass $password -keypass $password 2>&1
    
    # Extract SHA1 line
    $sha1Line = $output | Select-String "SHA1:" | Select-Object -First 1
    
    if ($sha1Line) {
        Write-Host "`n✅ SHA1 found:"
        Write-Host $sha1Line
        
        # Extract just the hash
        $sha1Hash = ($sha1Line -split "SHA1: ")[1]
        Write-Host "`nFormatted SHA1 (lowercase, no colon):"
        Write-Host ($sha1Hash.Replace(":", "").ToLower())
    } else {
        Write-Host "❌ SHA1 not found in output"
        Write-Host "Full output:"
        $output
    }
} else {
    Write-Host "❌ Debug keystore not found at: $keystorePath"
}
