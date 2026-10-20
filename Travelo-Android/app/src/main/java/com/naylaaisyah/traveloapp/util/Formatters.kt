package com.naylaaisyah.traveloapp.util

import java.text.NumberFormat
import java.util.Locale

private val idrFormat: NumberFormat = NumberFormat.getNumberInstance(Locale("id", "ID")).apply {
    maximumFractionDigits = 0
    minimumFractionDigits = 0
}

/** Formats a backend amount (IDR) as "Rp 1.250.000". */
fun formatRupiah(amount: Double): String = "Rp " + idrFormat.format(amount)

fun formatRupiah(amount: Int): String = formatRupiah(amount.toDouble())

/** Formats an ISO-ish date string (yyyy-MM-dd) for display, falling back to the raw value. */
fun formatDisplayDate(raw: String?): String {
    if (raw.isNullOrBlank()) return "-"
    return runCatching {
        val parts = raw.take(10).split("-")
        val months = listOf(
            "Jan", "Feb", "Mar", "Apr", "Mei", "Jun",
            "Jul", "Agu", "Sep", "Okt", "Nov", "Des"
        )
        val day = parts[2].trimStart('0').ifEmpty { "0" }
        val month = months[parts[1].toInt() - 1]
        "$day $month ${parts[0]}"
    }.getOrDefault(raw)
}
