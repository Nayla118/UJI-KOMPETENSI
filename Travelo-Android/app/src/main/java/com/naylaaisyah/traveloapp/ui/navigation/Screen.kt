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
    data object Booking : Screen("booking/{tourPackageId}/{title}/{price}") {
        fun createRoute(tourPackageId: Int, title: String, price: Int) =
            "booking/$tourPackageId/${java.net.URLEncoder.encode(title, "UTF-8")}/$price"
    }
    data object MyBookings : Screen("my_bookings")
    data object Profile : Screen("profile")
}
