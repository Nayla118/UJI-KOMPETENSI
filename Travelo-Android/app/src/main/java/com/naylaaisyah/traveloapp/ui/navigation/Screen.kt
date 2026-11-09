package com.naylaaisyah.traveloapp.ui.navigation

sealed class Screen(val route: String) {
    data object Login : Screen("login")
    data object Register : Screen("register")
    data object Home : Screen("home")
    data object Explore : Screen("explore")
    data object DestinationDetail : Screen("destination/{destinationId}") {
        fun createRoute(destinationId: Int) = "destination/$destinationId"
    }
    data object TourPackageDetail : Screen("tour_package/{tourPackageId}") {
        fun createRoute(tourPackageId: Int) = "tour_package/$tourPackageId"
    }
    data object Booking : Screen("booking/{tourPackageId}/{title}/{price}/{maxPeople}") {
        fun createRoute(tourPackageId: Int, title: String, price: Int, maxPeople: Int) =
            "booking/$tourPackageId/${java.net.URLEncoder.encode(title, "UTF-8")}/$price/$maxPeople"
    }
    data object BookingDetail : Screen("booking_detail/{bookingId}") {
        fun createRoute(bookingId: Int) = "booking_detail/$bookingId"
    }
    data object ETicket : Screen("e_ticket/{bookingId}") {
        fun createRoute(bookingId: Int) = "e_ticket/$bookingId"
    }
    data object Review : Screen("review/{destinationId}?reviewId={reviewId}") {
        fun createRoute(destinationId: Int, reviewId: Int? = null): String {
            val base = "review/$destinationId"
            return if (reviewId == null) base else "$base?reviewId=$reviewId"
        }
    }
    data object MyBookings : Screen("my_bookings?notice={notice}") {
        fun createRoute(notice: String? = null): String =
            if (notice.isNullOrBlank()) {
                "my_bookings"
            } else {
                "my_bookings?notice=" + android.net.Uri.encode(notice)
            }
    }
    data object Favorites : Screen("favorites")
    data object TicketHistory : Screen("ticket_history")
    data object CompleteDocuments : Screen("complete_documents/{bookingId}") {
        fun createRoute(bookingId: Int) = "complete_documents/$bookingId"
    }
    data object Profile : Screen("profile")
    data object Payment : Screen("payment/{bookingId}") {
        fun createRoute(bookingId: Int) = "payment/$bookingId"
    }
    data object PaymentResult : Screen("payment_result/{bookingId}") {
        fun createRoute(bookingId: Int) = "payment_result/$bookingId"
    }
}
