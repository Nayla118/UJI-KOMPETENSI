package com.naylaaisyah.traveloapp.di

import android.content.Context
import android.util.Log
import com.google.android.gms.common.GooglePlayServicesUtil
import com.google.android.gms.common.ConnectionResult
import com.google.firebase.auth.FirebaseAuth
import com.google.android.gms.auth.api.signin.GoogleSignIn
import com.google.android.gms.auth.api.signin.GoogleSignInClient
import com.google.android.gms.auth.api.signin.GoogleSignInOptions
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object FirebaseModule {
    
    @Provides
    @Singleton
    fun provideFirebaseAuth(): FirebaseAuth {
        return FirebaseAuth.getInstance()
    }
    
    @Provides
    @Singleton
    fun provideGoogleSignInOptions(): GoogleSignInOptions {
        // IMPORTANT:
        // requestIdToken() must use the Web client ID (client_type = 3) from google-services.json,
        // not the Android OAuth client ID (client_type = 1).
        // Using the Android client ID often causes Google Sign-In to fail or return cancelled.
        return GoogleSignInOptions.Builder(GoogleSignInOptions.DEFAULT_SIGN_IN)
            .requestIdToken("267490774278-oo4agh278lgqbi35pqq5va6prgvilo35.apps.googleusercontent.com")
            .requestEmail()
            .requestProfile()
            .build()
    }
    
    @Provides
    @Singleton
    fun provideGoogleSignInClient(
        @ApplicationContext context: Context,
        googleSignInOptions: GoogleSignInOptions
    ): GoogleSignInClient {
        // Check Google Play Services availability
        val gmsAvailability = GooglePlayServicesUtil.isGooglePlayServicesAvailable(context)
        Log.d("FirebaseModule", "Google Play Services availability: $gmsAvailability")
        
        when (gmsAvailability) {
            ConnectionResult.SUCCESS -> {
                Log.d("FirebaseModule", "✓ Google Play Services is available")
            }
            ConnectionResult.SERVICE_MISSING -> {
                Log.e("FirebaseModule", "✗ Google Play Services is missing on this device/emulator")
            }
            ConnectionResult.SERVICE_VERSION_UPDATE_REQUIRED -> {
                Log.w("FirebaseModule", "⚠ Google Play Services needs update")
            }
            ConnectionResult.SERVICE_DISABLED -> {
                Log.e("FirebaseModule", "✗ Google Play Services is disabled")
            }
            else -> {
                Log.w("FirebaseModule", "⚠ Unknown Google Play Services status: $gmsAvailability")
            }
        }
        
        return GoogleSignIn.getClient(context, googleSignInOptions)
    }
}
