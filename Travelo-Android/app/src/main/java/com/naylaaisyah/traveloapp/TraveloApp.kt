package com.naylaaisyah.traveloapp

import android.app.Application
import coil.ImageLoader
import coil.ImageLoaderFactory
import dagger.hilt.android.HiltAndroidApp
import okhttp3.OkHttpClient

@HiltAndroidApp
class TraveloApp : Application(), ImageLoaderFactory {
    override fun onCreate() {
        super.onCreate()
    }

    /**
     * Profile photos are served from the same public host as the API. The
     * ngrok free tier returns a warning page unless this header is present,
     * and Coil does not use Retrofit's OkHttp client, so configure its client
     * separately.
     */
    override fun newImageLoader(): ImageLoader {
        val imageClient = OkHttpClient.Builder()
            .addInterceptor { chain ->
                val request = chain.request().newBuilder()
                    .header("ngrok-skip-browser-warning", "true")
                    .build()
                chain.proceed(request)
            }
            .build()

        return ImageLoader.Builder(this)
            .okHttpClient(imageClient)
            .build()
    }
}
