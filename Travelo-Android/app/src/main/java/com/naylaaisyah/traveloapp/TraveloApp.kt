package com.naylaaisyah.traveloapp

import android.app.Application
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class TraveloApp : Application() {
    override fun onCreate() {
        super.onCreate()
    }
}
