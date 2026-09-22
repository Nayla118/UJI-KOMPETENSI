#!/bin/bash
# Script untuk add Google account ke emulator via ADB

# Pastikan emulator running
echo "Checking emulator status..."
adb devices

echo ""
echo "Adding Google account via adb..."

# Method 1: Menggunakan am command untuk buka account setup
adb shell am start -n com.android.settings/com.android.settings.accounts.AddAccountSettings

echo "✓ Google account setup screen should open on emulator"
echo ""
echo "Now:"
echo "1. Tap 'Google' account type"
echo "2. Enter your Gmail credentials"
echo "3. Accept permissions"
echo "4. Done!"
