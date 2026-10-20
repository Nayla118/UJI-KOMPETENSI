import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    id("kotlin-kapt")
    id("com.google.gms.google-services")
    id("com.google.dagger.hilt.android")
}

// API base URL is build configuration, not source code.
//
// Override per machine without touching the repo by adding one line to
// local.properties (git-ignored):
//     travelo.baseUrl=http://192.168.1.10:8000/          (debug)
//     travelo.releaseBaseUrl=https://api.example.com/    (release)
// or pass -Ptravelo.baseUrl=... / -Ptravelo.releaseBaseUrl=... on the command
// line.
//
// Debug builds default to the host machine as seen from the Android emulator
// (10.0.2.2). Use your computer's LAN IP for a physical device. Release builds
// fall back to the existing tunnel so a local override can never leak into a
// release by accident.
val localProperties = Properties().apply {
    val file = rootProject.file("local.properties")
    if (file.exists()) {
        file.inputStream().use { load(it) }
    }
}
fun normalisedBaseUrl(raw: String?): String? =
    raw?.let { if (it.endsWith("/")) it else "$it/" }

val traveloDebugBaseUrl = normalisedBaseUrl(
    localProperties.getProperty("travelo.baseUrl")
        ?: (project.findProperty("travelo.baseUrl") as String?)
)
val traveloReleaseBaseUrl = normalisedBaseUrl(
    localProperties.getProperty("travelo.releaseBaseUrl")
        ?: (project.findProperty("travelo.releaseBaseUrl") as String?)
)
val effectiveReleaseBaseUrl = traveloReleaseBaseUrl
    ?: "https://postcartilaginous-erlinda-unchicly.ngrok-free.dev/"
require(effectiveReleaseBaseUrl.startsWith("https://")) {
    "Release builds must use an https:// API URL. Got: $effectiveReleaseBaseUrl"
}

android {
    namespace = "com.naylaaisyah.traveloapp"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.naylaaisyah.traveloapp"
        minSdk = 26
        targetSdk = 35
        versionCode = 1
        versionName = "1.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
        vectorDrawables {
            useSupportLibrary = true
        }
    }

    buildTypes {
        debug {
            buildConfigField(
                "String",
                "BASE_URL",
                "\"${traveloDebugBaseUrl ?: "http://10.0.2.2:8000/"}\""
            )
        }
        release {
            buildConfigField(
                "String",
                "BASE_URL",
                "\"$effectiveReleaseBaseUrl\""
            )
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_1_8
        targetCompatibility = JavaVersion.VERSION_1_8
    }
    kotlinOptions {
        jvmTarget = "1.8"
    }
    buildFeatures {
        compose = true
        buildConfig = true
    }
    composeOptions {
        kotlinCompilerExtensionVersion = "1.5.8"
    }
    packaging {
        resources {
            excludes += "/META-INF/{AL2.0,LGPL2.1}"
        }
    }
}

dependencies {
    // Core Android
    implementation("androidx.core:core-ktx:1.12.0")
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.7.0")
    implementation("androidx.activity:activity-compose:1.8.1")
    implementation("androidx.compose.ui:ui:1.6.2")
    implementation("androidx.compose.ui:ui-graphics:1.6.2")
    implementation("androidx.compose.ui:ui-tooling-preview:1.6.2")
    implementation("androidx.compose.material3:material3:1.1.2")

    // Pull-to-refresh. Material3 1.1.x has no PullToRefreshBox yet, so use
    // the stable Material pull-refresh modifier alongside M3 components.
    implementation("androidx.compose.material:material:1.6.2")

    // Material Icons Extended
    implementation("androidx.compose.material:material-icons-extended:1.6.2")

    // Compose Navigation
    implementation("androidx.navigation:navigation-compose:2.7.6")

    // Firebase
    implementation(platform("com.google.firebase:firebase-bom:32.7.1"))
    implementation("com.google.firebase:firebase-auth-ktx")
    implementation("com.google.firebase:firebase-firestore-ktx")
    implementation("com.google.firebase:firebase-storage-ktx")
    implementation("com.google.firebase:firebase-analytics-ktx")

    // Google Sign-In & Play Services
    implementation("com.google.android.gms:play-services-auth:20.7.0")
    implementation("com.google.android.gms:play-services-base:18.4.0")

    // Image Loading
    implementation("io.coil-kt:coil-compose:2.5.0")

    // Renders the backend-issued, signed e-ticket QR payload on-device.
    implementation("com.google.zxing:core:3.5.3")

    // Retrofit & OkHttp
    implementation("com.squareup.retrofit2:retrofit:2.9.0")
    implementation("com.squareup.retrofit2:converter-gson:2.9.0")
    implementation("com.squareup.okhttp3:okhttp:4.11.0")
    implementation("com.squareup.okhttp3:logging-interceptor:4.11.0")

    // Gson
    implementation("com.google.code.gson:gson:2.10.1")

    // Coroutines
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.7.3")
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-core:1.7.3")

    // ViewModel & LiveData
    implementation("androidx.lifecycle:lifecycle-viewmodel-ktx:2.7.0")
    implementation("androidx.lifecycle:lifecycle-livedata-ktx:2.7.0")

    // Dagger Hilt
    implementation("com.google.dagger:hilt-android:2.50")
    kapt("com.google.dagger:hilt-compiler:2.50")
    implementation("androidx.hilt:hilt-navigation-compose:1.1.0")

    // WebView
    implementation("androidx.webkit:webkit:1.8.0")

    // Material
    implementation("com.google.android.material:material:1.11.0")

    // Testing
    testImplementation("junit:junit:4.13.2")
    androidTestImplementation("androidx.test.ext:junit:1.1.5")
    androidTestImplementation("androidx.test.espresso:espresso-core:3.5.1")
    androidTestImplementation("androidx.compose.ui:ui-test-junit4:1.6.2")
    debugImplementation("androidx.compose.ui:ui-tooling:1.6.2")
    debugImplementation("androidx.compose.ui:ui-test-manifest:1.6.2")
}
