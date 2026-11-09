package com.naylaaisyah.traveloapp.util

import com.naylaaisyah.traveloapp.BuildConfig

object Constants {
    // Resolved at build time (see app/build.gradle.kts). Debug defaults to the
    // emulator host; override per machine with `travelo.baseUrl` in
    // local.properties or -Ptravelo.baseUrl=... without editing source.
    val BASE_URL: String = BuildConfig.BASE_URL
    
    // Firebase Configuration
    const val FIREBASE_PROJECT_ID = "travelo-818bc"
    const val FIREBASE_WEB_CLIENT_ID = "267490774278-oo4agh278lgqbi35pqq5va6prgvilo35.apps.googleusercontent.com"
    
    // Shared Preferences
    const val PREF_NAME = "travelo_prefs"
    const val PREF_AUTH_TOKEN = "auth_token"
    const val PREF_USER_ID = "user_id"
    const val PREF_USER_EMAIL = "user_email"
    
    // Payment (Snap flow uses a server-issued snap_token opened in WebView;
    // no Midtrans keys belong in the app. The server key must ONLY live in
    // backend .env as MIDTRANS_SERVER_KEY.)
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

/**
 * Builds a loadable image URL. Absolute URLs (http/https) are returned as-is;
 * relative storage paths are resolved against the API base URL so the image is
 * always fetched from the same host the app already talks to.
 */
fun buildImageUrl(baseUrl: String, path: String?): String? {
    if (path.isNullOrBlank()) {
        return null
    }
    return if (path.startsWith("http://") || path.startsWith("https://")) {
        path
    } else {
        "${baseUrl.trimEnd('/')}/${path.trimStart('/')}"
    }
}
