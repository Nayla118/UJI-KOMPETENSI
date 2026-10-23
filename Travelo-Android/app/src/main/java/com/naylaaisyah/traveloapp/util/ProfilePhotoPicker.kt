package com.naylaaisyah.traveloapp.util

import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
import java.util.Locale

const val MAX_PROFILE_PHOTO_BYTES = 10L * 1024 * 1024
val ALLOWED_PROFILE_PHOTO_MIMES = setOf("image/jpeg", "image/jpg", "image/png", "image/webp")

/** An image selected for the profile, before its bytes are uploaded. */
data class SelectedProfilePhoto(
    val uri: Uri,
    val fileName: String,
    val mimeType: String,
    val sizeBytes: Long
)

/**
 * Reads picker metadata and applies the same limits as the backend. The server
 * still validates the image; this only gives fast feedback and avoids reading
 * an oversized file into memory.
 */
fun describeProfilePhoto(context: Context, uri: Uri): SelectedProfilePhoto? {
    val resolver = context.contentResolver
    val mimeType = resolver.getType(uri)?.lowercase(Locale.US) ?: return null

    if (mimeType !in ALLOWED_PROFILE_PHOTO_MIMES) {
        return null
    }

    var name = "profile-photo"
    var size = -1L

    resolver.query(
        uri,
        arrayOf(OpenableColumns.DISPLAY_NAME, OpenableColumns.SIZE),
        null,
        null,
        null
    )?.use { cursor ->
        if (cursor.moveToFirst()) {
            val nameIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
            val sizeIndex = cursor.getColumnIndex(OpenableColumns.SIZE)

            if (nameIndex >= 0) {
                name = cursor.getString(nameIndex) ?: name
            }
            if (sizeIndex >= 0 && !cursor.isNull(sizeIndex)) {
                size = cursor.getLong(sizeIndex)
            }
        }
    }

    if (size > MAX_PROFILE_PHOTO_BYTES) {
        return null
    }

    return SelectedProfilePhoto(uri, name, mimeType, size)
}

/** Reads the selected image, returning null if the URI became unavailable. */
fun readProfilePhotoBytes(context: Context, photo: SelectedProfilePhoto): ByteArray? =
    runCatching {
        context.contentResolver.openInputStream(photo.uri)?.use { stream ->
            stream.readBytes().takeIf { it.size <= MAX_PROFILE_PHOTO_BYTES }
        }
    }.getOrNull()
