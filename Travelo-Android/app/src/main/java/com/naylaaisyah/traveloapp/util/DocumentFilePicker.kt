package com.naylaaisyah.traveloapp.util

import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
import java.util.Locale

const val MAX_DOCUMENT_BYTES = 5L * 1024 * 1024
val ALLOWED_DOCUMENT_MIMES = setOf("image/jpeg", "image/png", "application/pdf")

/** A document selected with the system picker, before it is read into memory. */
data class SelectedDocument(
    val uri: Uri,
    val fileName: String,
    val mimeType: String,
    val sizeBytes: Long
)

/**
 * Reads picker metadata and applies the same client-side limits as the
 * backend. The backend still validates type and size; this is only for fast,
 * actionable feedback.
 */
fun describeDocument(context: Context, uri: Uri): SelectedDocument? {
    val resolver = context.contentResolver
    val mimeType = resolver.getType(uri)?.lowercase(Locale.US) ?: return null

    if (mimeType !in ALLOWED_DOCUMENT_MIMES) {
        return null
    }

    var name = "identity-document"
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

    if (size > MAX_DOCUMENT_BYTES) {
        return null
    }

    return SelectedDocument(uri, name, mimeType, size)
}

/**
 * Reads the bytes at upload time. A stale or revoked URI simply returns null,
 * so the caller can ask the user to pick the file again instead of crashing.
 */
fun readDocumentBytes(context: Context, document: SelectedDocument): ByteArray? =
    runCatching {
        context.contentResolver.openInputStream(document.uri)?.use { stream ->
            stream.readBytes().takeIf { it.size <= MAX_DOCUMENT_BYTES }
        }
    }.getOrNull()
