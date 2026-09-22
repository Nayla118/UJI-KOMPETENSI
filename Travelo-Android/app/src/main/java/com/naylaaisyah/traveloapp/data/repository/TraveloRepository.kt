package com.naylaaisyah.traveloapp.data.repository

import com.naylaaisyah.traveloapp.data.api.ApiService
import com.naylaaisyah.traveloapp.data.models.*
import com.naylaaisyah.traveloapp.util.Resource
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class TraveloRepository @Inject constructor(
    private val apiService: ApiService
) {
    // Authentication
    suspend fun login(idToken: String): Resource<AuthResponse> {
        return try {
            val response = apiService.login(FirebaseLoginRequest(idToken))
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, e.message ?: "Login failed")
        }
    }

    fun firebaseLogin(idToken: String): Flow<Resource<Pair<User, String>>> = flow {
        emit(Resource.Loading<Pair<User, String>>())
        try {
            val response = apiService.login(FirebaseLoginRequest(idToken))
            if (response.success && response.data?.token != null && response.data?.user != null) {
                emit(Resource.Success(Pair(response.data.user, response.data.token)))
            } else {
                emit(Resource.Error(Exception(response.data?.message ?: "Login failed")))
            }
        } catch (e: Exception) {
            emit(Resource.Error(e, e.message ?: "Firebase login failed"))
        }
    }

    suspend fun register(
        name: String,
        email: String,
        password: String,
        passwordConfirmation: String
    ): Resource<AuthResponse> {
        return try {
            val response = apiService.register(
                RegisterRequest(name, email, password, passwordConfirmation)
            )
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, e.message ?: "Registration failed")
        }
    }

    // Destinations
    suspend fun getDestinations(page: Int = 1): Resource<List<Destination>> {
        return try {
            val response = apiService.getDestinations(page)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, e.message ?: "Failed to get destinations")
        }
    }

    suspend fun getDestinationDetail(id: Int): Resource<Destination> {
        return try {
            val response = apiService.getDestinationDetail(id)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, e.message ?: "Failed to get destination detail")
        }
    }

    // Tour Packages
    suspend fun getTourPackages(page: Int = 1): Resource<List<TourPackage>> {
        return try {
            val response = apiService.getTourPackages(page)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, e.message ?: "Failed to get tour packages")
        }
    }

    suspend fun getTourPackagesByDestination(destinationId: Int): Resource<List<TourPackage>> {
        return try {
            val response = apiService.getDestinationTourPackages(destinationId)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, e.message ?: "Failed to get tour packages")
        }
    }

    suspend fun getTourPackageDetail(id: Int): Resource<TourPackageDetail> {
        return try {
            val response = apiService.getTourPackageDetail(id)
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, e.message ?: "Failed to get tour package detail")
        }
    }

    // Bookings
    suspend fun getMyBookings(token: String): Resource<List<Booking>> {
        return try {
            val response = apiService.getMyBookings("Bearer $token")
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, e.message ?: "Failed to get bookings")
        }
    }

    suspend fun createBooking(
        token: String,
        tourPackageId: Int,
        numberOfPersons: Int,
        travelDate: String
    ): Resource<CreateBookingResponseData> {
        return try {
            val response = apiService.createBooking(
                "Bearer $token",
                BookingRequest(tourPackageId, numberOfPersons, travelDate)
            )
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, e.message ?: "Failed to create booking")
        }
    }

    fun createBooking(
        token: String,
        request: BookingRequest
    ): Flow<Resource<CreateBookingResponse>> = flow {
        emit(Resource.Loading<CreateBookingResponse>())
        when (val result = createBooking(token, request.tourPackageId, request.peopleCount, request.bookingDate)) {
            is Resource.Success -> {
                val data = result.data
                if (data.booking != null) {
                    emit(
                        Resource.Success(
                            ApiResponse(
                                success = true,
                                message = "Booking created successfully",
                                data = data
                            )
                        )
                    )
                } else {
                    emit(Resource.Error(Exception("Booking creation failed")))
                }
            }
            is Resource.Error -> emit(Resource.Error(result.exception, result.message))
            is Resource.Loading -> emit(Resource.Loading<CreateBookingResponse>())
        }
    }

    fun getBookingById(token: String, bookingId: Int): Flow<Resource<Booking>> = flow {
        emit(Resource.Loading<Booking>())
        try {
            val response = apiService.getBookingDetail("Bearer $token", bookingId)
            if (response.success && response.data != null) {
                emit(Resource.Success(response.data))
            } else {
                emit(Resource.Error(Exception(response.message), response.message))
            }
        } catch (e: Exception) {
            emit(Resource.Error(e, e.message ?: "Failed to get booking"))
        }
    }

    suspend fun createSnapToken(token: String, bookingId: Int): Resource<CreateBookingResponseData> {
        return try {
            val response = apiService.createSnapToken("Bearer $token", SnapTokenRequest(bookingId))
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, e.message ?: "Failed to create snap token")
        }
    }

    fun verifyAndSyncPayment(bookingId: Int, token: String): Flow<Resource<PaymentSyncResponse>> = flow {
        emit(Resource.Loading<PaymentSyncResponse>())
        try {
            val response = apiService.verifyAndSyncPayment("Bearer $token", bookingId)
            if (response.success && response.data != null) {
                emit(Resource.Success(response.data))
            } else {
                emit(Resource.Error(Exception(response.message), response.message))
            }
        } catch (e: Exception) {
            emit(Resource.Error(e, e.message ?: "Payment verification failed"))
        }
    }

    fun retrySyncPayment(bookingId: Int, token: String): Flow<Resource<PaymentSyncResponse>> = verifyAndSyncPayment(bookingId, token)

    // User
    suspend fun getUserProfile(token: String): Resource<User> {
        return try {
            val response = apiService.getUserProfile("Bearer $token")
            if (response.success && response.data != null) {
                Resource.Success(response.data)
            } else {
                Resource.Error(Exception(response.message))
            }
        } catch (e: Exception) {
            Resource.Error(e, e.message ?: "Failed to get user profile")
        }
    }
}
