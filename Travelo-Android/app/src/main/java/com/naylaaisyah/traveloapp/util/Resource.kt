package com.naylaaisyah.traveloapp.util

sealed class Resource<T> {
    data class Success<T>(val data: T) : Resource<T>()
    data class Error<T>(val exception: Exception, val message: String = "") : Resource<T>()
    class Loading<T> : Resource<T>()
}
