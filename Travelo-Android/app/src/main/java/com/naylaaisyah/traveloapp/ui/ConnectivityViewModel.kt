package com.naylaaisyah.traveloapp.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naylaaisyah.traveloapp.util.NetworkMonitor
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * App-wide connectivity state for the offline banner. Starts from the real
 * current status so the first frame does not briefly claim the device is
 * online, then follows NetworkMonitor's callback flow.
 */
@HiltViewModel
class ConnectivityViewModel @Inject constructor(
    private val networkMonitor: NetworkMonitor
) : ViewModel() {

    private val _isOnline = MutableStateFlow(networkMonitor.currentStatus())
    val isOnline: StateFlow<Boolean> = _isOnline.asStateFlow()

    init {
        viewModelScope.launch {
            networkMonitor.isOnline.collect { online ->
                _isOnline.value = online
            }
        }
    }

    fun refresh() {
        _isOnline.value = networkMonitor.currentStatus()
    }
}
