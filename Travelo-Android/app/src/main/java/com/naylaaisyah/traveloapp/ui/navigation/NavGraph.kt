package com.naylaaisyah.traveloapp.ui.navigation

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.naylaaisyah.traveloapp.notifications.DeepLinkTarget
import com.naylaaisyah.traveloapp.ui.screens.auth.LoginScreen
import com.naylaaisyah.traveloapp.ui.screens.auth.RegisterScreen
import com.naylaaisyah.traveloapp.ui.screens.booking.BookingScreen
import com.naylaaisyah.traveloapp.ui.screens.destination.DestinationDetailScreen
import com.naylaaisyah.traveloapp.ui.screens.explore.ExploreScreen
import com.naylaaisyah.traveloapp.ui.screens.favorites.FavoritesScreen
import com.naylaaisyah.traveloapp.ui.screens.home.HomeScreen
import com.naylaaisyah.traveloapp.ui.screens.my_bookings.BookingDetailScreen
import com.naylaaisyah.traveloapp.ui.screens.my_bookings.CompleteDocumentsScreen
import com.naylaaisyah.traveloapp.ui.screens.my_bookings.MyBookingsScreen
import com.naylaaisyah.traveloapp.ui.screens.my_bookings.TicketHistoryScreen
import com.naylaaisyah.traveloapp.ui.screens.payment.PaymentResultScreen
import com.naylaaisyah.traveloapp.ui.screens.payment.PaymentScreen
import com.naylaaisyah.traveloapp.ui.screens.profile.ProfileScreen
import com.naylaaisyah.traveloapp.ui.screens.tour_package.TourPackageDetailScreen

@Composable
fun NavGraph(
    navController: NavHostController,
    startDestination: String = Screen.Login.route,
    pendingDeepLink: DeepLinkTarget? = null,
    onDeepLinkConsumed: (DeepLinkTarget) -> Unit = {},
    onLogout: () -> Unit = {}
) {
    // A cold/warm notification tap while signed in. When the app starts on the
    // login route the target is kept until login succeeds (below).
    LaunchedEffect(startDestination, pendingDeepLink) {
        if (pendingDeepLink != null && startDestination != Screen.Login.route) {
            navController.navigateToDeepLink(pendingDeepLink)
            onDeepLinkConsumed(pendingDeepLink)
        }
    }

    val completeAuth: () -> Unit = {
        navController.navigate(Screen.Home.route) {
            popUpTo(Screen.Login.route) { inclusive = true }
        }
        pendingDeepLink?.let { target ->
            navController.navigateToDeepLink(target)
            onDeepLinkConsumed(target)
        }
    }

    NavHost(
        navController = navController,
        startDestination = startDestination
    ) {
        // Login Screen
        composable(Screen.Login.route) {
            LoginScreen(
                onLoginSuccess = completeAuth,
                onNavigateToRegister = {
                    navController.navigate(Screen.Register.route)
                }
            )
        }
        
        // Register Screen
        composable(Screen.Register.route) {
            RegisterScreen(
                onRegisterSuccess = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(Screen.Register.route) { inclusive = true }
                    }
                    pendingDeepLink?.let { target ->
                        navController.navigateToDeepLink(target)
                        onDeepLinkConsumed(target)
                    }
                },
                onNavigateToLogin = {
                    navController.popBackStack()
                }
            )
        }
        
        // Home Screen
        composable(Screen.Home.route) {
            HomeScreen(
                onDestinationClick = { destinationId ->
                    navController.navigate(Screen.DestinationDetail.createRoute(destinationId))
                },
                onTourPackageClick = { tourPackageId ->
                    navController.navigate(Screen.TourPackageDetail.createRoute(tourPackageId))
                },
                onProfileClick = {
                    navController.navigate(Screen.Profile.route)
                },
                onMyBookingsClick = {
                    navController.navigate(Screen.MyBookings.createRoute())
                },
                onExploreClick = {
                    navController.navigate(Screen.Explore.route)
                },
                onCompleteDocuments = { bookingId ->
                    navController.navigate(Screen.CompleteDocuments.createRoute(bookingId))
                },
                onRequireLogin = onLogout
            )
        }
        
        // Explore Screen
        composable(Screen.Explore.route) {
            ExploreScreen(
                onDestinationClick = { destinationId ->
                    navController.navigate(Screen.DestinationDetail.createRoute(destinationId))
                },
                onTourPackageClick = { tourPackageId ->
                    navController.navigate(Screen.TourPackageDetail.createRoute(tourPackageId))
                },
                onProfileClick = {
                    navController.navigate(Screen.Profile.route)
                },
                onMyBookingsClick = {
                    navController.navigate(Screen.MyBookings.createRoute())
                },
                onHomeClick = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(Screen.Explore.route) { inclusive = true }
                    }
                },
                onRequireLogin = onLogout
            )
        }
        
        // Destination Detail Screen
        composable(
            route = Screen.DestinationDetail.route,
            arguments = listOf(
                navArgument("destinationId") { type = NavType.IntType }
            )
        ) { backStackEntry ->
            val destinationId = backStackEntry.arguments?.getInt("destinationId") ?: 0
            DestinationDetailScreen(
                destinationId = destinationId,
                onBackClick = { navController.popBackStack() },
                onTourPackageClick = { tourPackageId ->
                    navController.navigate(Screen.TourPackageDetail.createRoute(tourPackageId))
                },
                onRequireLogin = onLogout
            )
        }

        // Review moderation notification: destination detail with the review
        // section focused. The backend sends both destination_id and review_id.
        composable(
            route = Screen.Review.route,
            arguments = listOf(
                navArgument("destinationId") { type = NavType.IntType },
                navArgument("reviewId") {
                    type = NavType.IntType
                    defaultValue = -1
                }
            )
        ) { backStackEntry ->
            val destinationId = backStackEntry.arguments?.getInt("destinationId") ?: 0
            val reviewId = backStackEntry.arguments?.getInt("reviewId")?.takeIf { it > 0 }
            DestinationDetailScreen(
                destinationId = destinationId,
                onBackClick = { navController.popBackStack() },
                onTourPackageClick = { tourPackageId ->
                    navController.navigate(Screen.TourPackageDetail.createRoute(tourPackageId))
                },
                onRequireLogin = onLogout,
                focusReviews = true,
                focusReviewId = reviewId
            )
        }
        
        // Tour Package Detail Screen
        composable(
            route = Screen.TourPackageDetail.route,
            arguments = listOf(
                navArgument("tourPackageId") { type = NavType.IntType }
            )
        ) { backStackEntry ->
            val tourPackageId = backStackEntry.arguments?.getInt("tourPackageId") ?: 0
            TourPackageDetailScreen(
                tourPackageId = tourPackageId,
                onBackClick = { navController.popBackStack() },
                onBookNowClick = { id, title, price, maxPeople ->
                    navController.navigate(Screen.Booking.createRoute(id, title, price, maxPeople))
                },
                onRequireLogin = onLogout
            )
        }
        
        // Booking Screen
        composable(
            route = Screen.Booking.route,
            arguments = listOf(
                navArgument("tourPackageId") { type = NavType.IntType },
                navArgument("title") { type = NavType.StringType },
                navArgument("price") { type = NavType.IntType },
                navArgument("maxPeople") {
                    type = NavType.IntType
                    defaultValue = 10
                }
            )
        ) { backStackEntry ->
            val tourPackageId = backStackEntry.arguments?.getInt("tourPackageId") ?: 0
            val title = try {
                backStackEntry.arguments?.getString("title")?.let {
                    java.net.URLDecoder.decode(it, "UTF-8")
                } ?: ""
            } catch (e: Exception) { "" }
            val price = backStackEntry.arguments?.getInt("price") ?: 0
            val maxPeople = backStackEntry.arguments?.getInt("maxPeople") ?: 10
            
            BookingScreen(
                tourPackageId = tourPackageId,
                tourPackageTitle = title,
                tourPackagePrice = price,
                tourPackageMaxPeople = maxPeople,
                onBackClick = { navController.popBackStack() },
                onPaymentSuccess = { bookingId ->
                    // Booking created -> continue to the dedicated payment flow.
                    navController.navigate(Screen.Payment.createRoute(bookingId))
                },
                onContinueToPayment = { bookingId ->
                    navController.navigate(Screen.Payment.createRoute(bookingId))
                }
            )
        }

        // Payment Screen (booking summary -> pay -> processing)
        composable(
            route = Screen.Payment.route,
            arguments = listOf(
                navArgument("bookingId") { type = NavType.IntType }
            )
        ) { backStackEntry ->
            val bookingId = backStackEntry.arguments?.getInt("bookingId") ?: 0
            PaymentScreen(
                bookingId = bookingId,
                onBackClick = { navController.popBackStack() },
                onFinished = { finishedId ->
                    navController.navigate(Screen.PaymentResult.createRoute(finishedId)) {
                        popUpTo(Screen.Payment.route) { inclusive = true }
                    }
                },
                onRebook = { tourPackageId ->
                    navController.navigate(Screen.TourPackageDetail.createRoute(tourPackageId)) {
                        popUpTo(Screen.Payment.route) { inclusive = true }
                    }
                }
            )
        }

        // Payment Result Screen (success / waiting / failed / expired)
        composable(
            route = Screen.PaymentResult.route,
            arguments = listOf(
                navArgument("bookingId") { type = NavType.IntType }
            )
        ) { backStackEntry ->
            val bookingId = backStackEntry.arguments?.getInt("bookingId") ?: 0
            PaymentResultScreen(
                bookingId = bookingId,
                onViewMyBookings = {
                    navController.navigate(Screen.MyBookings.createRoute()) {
                        popUpTo(Screen.Home.route)
                    }
                },
                onPayAgain = { payAgainId ->
                    navController.navigate(Screen.Payment.createRoute(payAgainId)) {
                        popUpTo(Screen.PaymentResult.route) { inclusive = true }
                    }
                }
            )
        }
        
        // Notification deep link: booking detail. A 403/404 is redirected to
        // My Bookings with the friendly message shown as a snackbar there.
        composable(
            route = Screen.BookingDetail.route,
            arguments = listOf(
                navArgument("bookingId") { type = NavType.IntType }
            )
        ) { backStackEntry ->
            val bookingId = backStackEntry.arguments?.getInt("bookingId") ?: 0
            BookingDetailScreen(
                bookingId = bookingId,
                onBackClick = { navController.popBackStack() },
                onOpenMyBookings = { notice ->
                    navController.navigate(Screen.MyBookings.createRoute(notice)) {
                        popUpTo(Screen.Home.route)
                    }
                }
            )
        }

        // Notification deep link: e-ticket. Same verified dialog as My Bookings.
        composable(
            route = Screen.ETicket.route,
            arguments = listOf(
                navArgument("bookingId") { type = NavType.IntType }
            )
        ) { backStackEntry ->
            val bookingId = backStackEntry.arguments?.getInt("bookingId") ?: 0
            BookingDetailScreen(
                bookingId = bookingId,
                openTicketOnLoad = true,
                onBackClick = { navController.popBackStack() },
                onOpenMyBookings = { notice ->
                    navController.navigate(Screen.MyBookings.createRoute(notice)) {
                        popUpTo(Screen.Home.route)
                    }
                }
            )
        }

        // My Bookings Screen
        composable(
            route = Screen.MyBookings.route,
            arguments = listOf(
                navArgument("notice") {
                    type = NavType.StringType
                    nullable = true
                    defaultValue = null
                }
            )
        ) { backStackEntry ->
            MyBookingsScreen(
                onBackClick = { navController.popBackStack() },
                onPayBooking = { bookingId ->
                    navController.navigate(Screen.Payment.createRoute(bookingId))
                },
                onRebookBooking = { tourPackageId ->
                    navController.navigate(Screen.TourPackageDetail.createRoute(tourPackageId))
                },
                onCompleteDocuments = { bookingId ->
                    navController.navigate(Screen.CompleteDocuments.createRoute(bookingId))
                },
                onTicketHistoryClick = {
                    navController.navigate(Screen.TicketHistory.route)
                },
                notice = backStackEntry.arguments?.getString("notice")
            )
        }

        // Used and expired tickets are read-only history; no active e-ticket.
        composable(Screen.TicketHistory.route) {
            TicketHistoryScreen(
                onBackClick = { navController.popBackStack() }
            )
        }

        // Resume an interrupted identity-document upload for an existing booking.
        composable(
            route = Screen.CompleteDocuments.route,
            arguments = listOf(
                navArgument("bookingId") { type = NavType.IntType }
            )
        ) {
            CompleteDocumentsScreen(
                onBackClick = { navController.popBackStack() }
            )
        }
        
        // Favourites ("Favorit Saya"), reached from Profile.
        composable(Screen.Favorites.route) {
            FavoritesScreen(
                onBackClick = { navController.popBackStack() },
                onDestinationClick = { destinationId ->
                    navController.navigate(Screen.DestinationDetail.createRoute(destinationId))
                },
                onTourPackageClick = { tourPackageId ->
                    navController.navigate(Screen.TourPackageDetail.createRoute(tourPackageId))
                },
                onRequireLogin = onLogout
            )
        }

        // Profile Screen
        composable(Screen.Profile.route) {
            ProfileScreen(
                onBackClick = { navController.popBackStack() },
                onMyBookings = {
                    navController.navigate(Screen.MyBookings.createRoute())
                },
                onTicketHistory = {
                    navController.navigate(Screen.TicketHistory.route)
                },
                onFavorites = {
                    navController.navigate(Screen.Favorites.route)
                },
                onLogout = onLogout
            )
        }
    }
}

private fun DeepLinkTarget.route(): String = when (this) {
    is DeepLinkTarget.BookingDetail -> Screen.BookingDetail.createRoute(bookingId)
    is DeepLinkTarget.ETicket -> Screen.ETicket.createRoute(bookingId)
    is DeepLinkTarget.Review -> Screen.Review.createRoute(destinationId, reviewId)
    is DeepLinkTarget.Destination -> Screen.DestinationDetail.createRoute(destinationId)
}

/**
 * Replace whatever is above Home instead of stacking a second copy of the
 * notification target. Back from the target therefore always reaches Home.
 */
private fun NavHostController.navigateToDeepLink(target: DeepLinkTarget) {
    navigate(target.route()) {
        popUpTo(Screen.Home.route) { inclusive = false }
        launchSingleTop = true
    }
}

