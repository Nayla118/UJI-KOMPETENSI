# Script untuk add Google account ke emulator via ADB (Windows)

Write-Host "Checking if ADB is available..."
$adbPath = (Get-Command adb -ErrorAction SilentlyContinue).Path

if (-not $adbPath) {
    Write-Host "❌ ADB not found in PATH"
    Write-Host "Android SDK Tools harus diinstall"
    Write-Host ""
    Write-Host "Android SDK location biasanya di:"
    Write-Host "  C:\Users\$env:USERNAME\AppData\Local\Android\Sdk\platform-tools"
    exit 1
}

Write-Host "✅ ADB found at: $adbPath"
Write-Host ""

Write-Host "Checking emulator devices..."
$devices = & adb devices
Write-Host $devices

Write-Host ""
Write-Host "Opening Google account setup screen on emulator..."
& adb shell am start -n com.android.settings/com.android.settings.accounts.AddAccountSettings

Write-Host ""
Write-Host "✅ Google account setup screen should open on emulator"
Write-Host ""
Write-Host "Now on emulator:"
Write-Host "  1. Tap 'Google' account type"
Write-Host "  2. Enter your Gmail credentials"
Write-Host "  3. Accept all permissions"
Write-Host "  4. Done!"
Write-Host ""
Write-Host "Then, come back to app and try 'Sign in with Google'"
