package com.naylaaisyah.traveloapp.notifications

import android.content.Intent
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import javax.inject.Inject
import javax.inject.Singleton

/**
 * App-scoped holder for a notification destination.
 *
 * A tap can arrive before the NavHost exists (cold start) or before the user is
 * signed in. Keeping the target here lets MainActivity/NavGraph consume it once,
 * after the relevant screen is ready, instead of losing it with the Intent.
 */
@Singleton
class DeepLinkController @Inject constructor() {
    private val _pending = MutableStateFlow<DeepLinkTarget?>(null)
    val pending: StateFlow<DeepLinkTarget?> = _pending.asStateFlow()

    fun handleIntent(intent: Intent?) {
        val bundle = intent?.extras ?: return
        val extras = bundle.keySet().mapNotNull { key ->
            bundle.getString(key)?.let { value -> key to value }
        }.toMap()

        val target = DeepLinkParser.parse(extras) ?: return
        _pending.value = target
    }

    /** Clear only the target that was actually navigated to. */
    fun consume(target: DeepLinkTarget) {
        if (_pending.value == target) {
            _pending.value = null
        }
    }
}
