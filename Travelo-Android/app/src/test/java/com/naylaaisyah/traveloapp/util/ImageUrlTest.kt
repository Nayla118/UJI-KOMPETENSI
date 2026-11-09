package com.naylaaisyah.traveloapp.util

import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Test

/**
 * Pins the rule that Coil always loads images from the same host the app talks
 * to: absolute http/https URLs pass through, relative storage paths are
 * resolved against the API base URL.
 */
class ImageUrlTest {

    @Test
    fun `absolute http url is returned as-is`() {
        assertEquals(
            "http://10.0.2.2:8000/storage/destinations/1.jpg",
            buildImageUrl("http://10.0.2.2:8000/", "http://10.0.2.2:8000/storage/destinations/1.jpg")
        )
    }

    @Test
    fun `absolute https url is returned as-is`() {
        assertEquals(
            "https://images.unsplash.com/photo-1?w=800",
            buildImageUrl("http://10.0.2.2:8000/", "https://images.unsplash.com/photo-1?w=800")
        )
    }

    @Test
    fun `relative path is resolved against base url`() {
        assertEquals(
            "http://10.0.2.2:8000/storage/destinations/1.jpg",
            buildImageUrl("http://10.0.2.2:8000/", "storage/destinations/1.jpg")
        )
    }

    @Test
    fun `relative path with leading slash does not double the slash`() {
        assertEquals(
            "http://10.0.2.2:8000/storage/destinations/1.jpg",
            buildImageUrl("http://10.0.2.2:8000/", "/storage/destinations/1.jpg")
        )
    }

    @Test
    fun `base url without trailing slash still works`() {
        assertEquals(
            "http://10.0.2.2:8000/storage/destinations/1.jpg",
            buildImageUrl("http://10.0.2.2:8000", "/storage/destinations/1.jpg")
        )
    }

    @Test
    fun `null path returns null`() {
        assertNull(buildImageUrl("http://10.0.2.2:8000/", null))
    }

    @Test
    fun `blank path returns null`() {
        assertNull(buildImageUrl("http://10.0.2.2:8000/", ""))
        assertNull(buildImageUrl("http://10.0.2.2:8000/", "   "))
    }
}
