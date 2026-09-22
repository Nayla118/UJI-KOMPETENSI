# Check Google accounts on emulator
Write-Host "Checking Google accounts on emulator..."
Write-Host ""

$accounts = & adb shell am account list
Write-Host "Accounts found:"
Write-Host $accounts

Write-Host ""
Write-Host "Checking specifically for Google accounts:"
$googleAccounts = & adb shell dumpsys account | Select-String "Account {"
Write-Host $googleAccounts

Write-Host ""
Write-Host "If NO Google accounts listed, run:"
Write-Host "  adb shell am account list google"
