package com.naylaaisyah.traveloapp.util

import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * Prices are always formatted from a server amount. These tests pin the
 * Indonesian thousands separator so the booking summary and the e-ticket never
 * drift apart.
 */
class FormattersTest {

    @Test
    fun `double amount uses dot thousands separators`() {
        assertEquals("Rp 1.250.000", formatRupiah(1_250_000.0))
    }

    @Test
    fun `int amount is formatted like a double`() {
        assertEquals("Rp 2.000.000", formatRupiah(2_000_000))
    }

    @Test
    fun `zero is still prefixed with rupiah`() {
        assertEquals("Rp 0", formatRupiah(0.0))
    }

    @Test
    fun `discount amount keeps no decimals`() {
        assertEquals("Rp 300.000", formatRupiah(300_000.0))
    }
}
