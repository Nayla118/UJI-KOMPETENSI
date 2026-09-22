# This is a configuration file for ProGuard.
# http://proguard.sourceforge.net/index.html#manual/usage.html

# Basic ProGuard rules for Android
-keepattributes SourceFile,LineNumberTable
-renamesourcefileattribute SourceFile

# Keep our custom classes
-keep class com.naylaaisyah.traveloapp.** { *; }

# Keep all model classes
-keep class com.naylaaisyah.traveloapp.data.** { *; }
-keep class com.naylaaisyah.traveloapp.ui.** { *; }

# Firebase
-keep class com.firebase.** { *; }
-keep class com.google.firebase.** { *; }
-keepclasseswithmembernames class com.google.firebase.** { *; }
-keepclasseswithmembernames interface com.google.firebase.** { *; }

# Google Play Services
-keep class com.google.android.gms.** { *; }
-keep interface com.google.android.gms.** { *; }

# Retrofit
-keepattributes Signature
-keepattributes *Annotation*
-keep class com.squareup.okhttp.** { *; }
-keep interface com.squareup.okhttp.** { *; }
-keep class retrofit.** { *; }
-keepclasseswithmembers class * {
    @retrofit.http.<methods> <methods>;
}

# OkHttp
-keep class okhttp3.** { *; }
-keep interface okhttp3.** { *; }
-keep class okio.** { *; }

# Gson
-keep class com.google.gson.** { *; }
-keep class * extends com.google.gson.TypeAdapter
-keep class * implements com.google.gson.TypeAdapterFactory
-keep class * implements com.google.gson.JsonSerializer
-keep class * implements com.google.gson.JsonDeserializer

# Kotlin coroutines
-keep class kotlinx.coroutines.** { *; }

# Keep Android classes
-keep class android.** { *; }
-keep interface android.** { *; }

# Keep AndroidX classes
-keep class androidx.** { *; }
-keep interface androidx.** { *; }

# Preserve line numbers for debugging
-keepattributes SourceFile,LineNumberTable

# Remove logging
-assumenosideeffects class android.util.Log {
    public static *** d(...);
    public static *** v(...);
    public static *** i(...);
}
