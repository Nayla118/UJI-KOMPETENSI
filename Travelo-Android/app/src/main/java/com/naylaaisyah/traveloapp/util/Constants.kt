package com.naylaaisyah.traveloapp.util

object Constants {
    // API Configuration
    const val BASE_URL_EMULATOR = "http://10.0.2.2:8000/"
    const val BASE_URL_NGROK = "https://postcartilaginous-erlinda-unchicly.ngrok-free.dev/"
    const val BASE_URL = BASE_URL_NGROK
    
    // Firebase Configuration
    const val FIREBASE_PROJECT_ID = "travelo-818bc"
    const val FIREBASE_WEB_CLIENT_ID = "267490774278-oo4agh278lgqbi35pqq5va6prgvilo35.apps.googleusercontent.com"
    
    // Shared Preferences
    const val PREF_NAME = "travelo_prefs"
    const val PREF_AUTH_TOKEN = "auth_token"
    const val PREF_USER_ID = "user_id"
    const val PREF_USER_EMAIL = "user_email"
    
    // Payment
    const val MIDTRANS_SERVER_KEY = "SB-Mid-server-key"  // Replace with actual key
    const val MIDTRANS_CLIENT_KEY = "SB-Mid-client-key"  // Replace with actual key
    const val MIDTRANS_IS_SANDBOX = true
    
    // Network
    const val CONNECT_TIMEOUT = 30L  // seconds
    const val READ_TIMEOUT = 30L     // seconds
    const val WRITE_TIMEOUT = 30L    // seconds
    
    // Pagination
    const val DEFAULT_PAGE_SIZE = 10
    
    // Cache
    const val CACHE_SIZE = 10 * 1024 * 1024  // 10 MB
    
    // Validation
    const val MIN_PASSWORD_LENGTH = 8
    const val MAX_PASSWORD_LENGTH = 128
}
