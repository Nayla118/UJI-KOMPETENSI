package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import androidx.core.content.FileProvider
import com.naylaaisyah.traveloapp.data.repository.InvoiceFile
import java.io.File

/**
 * Saves a downloaded invoice in app cache, then hands it to an HTML viewer.
 * When no viewer is installed it falls back to the share sheet.
 *
 * @return a user-facing error message, or null on success.
 */
internal fun openInvoice(context: Context, invoice: InvoiceFile): String? {
    val file = try {
        val dir = File(context.cacheDir, "invoices").apply { mkdirs() }
        File(dir, invoice.fileName).apply { writeBytes(invoice.bytes) }
    } catch (_: Exception) {
        return "Couldn't save your invoice on this device."
    }

    val uri = try {
        FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    } catch (_: IllegalArgumentException) {
        return "Couldn't open your invoice."
    }

    val viewIntent = Intent(Intent.ACTION_VIEW).apply {
        setDataAndType(uri, "text/html")
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }

    return try {
        context.startActivity(viewIntent)
        null
    } catch (_: ActivityNotFoundException) {
        val shareIntent = Intent(Intent.ACTION_SEND).apply {
            type = "text/html"
            putExtra(Intent.EXTRA_STREAM, uri)
            addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        }
        try {
            context.startActivity(Intent.createChooser(shareIntent, "Invoice"))
            null
        } catch (_: ActivityNotFoundException) {
            "No app can open this invoice. Install a browser or PDF viewer."
        }
    }
}
