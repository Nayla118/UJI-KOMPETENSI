package com.naylaaisyah.traveloapp.ui.navigation

import androidx.compose.runtime.Composable
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.naylaaisyah.traveloapp.ui.screens.auth.LoginScreen
import com.naylaaisyah.traveloapp.ui.screens.auth.RegisterScreen
import com.naylaaisyah.traveloapp.ui.screens.booking.BookingScreen
import com.naylaaisyah.traveloapp.ui.screens.destination.DestinationDetailScreen
import com.naylaaisyah.traveloapp.ui.screens.explore.ExploreScreen
import com.naylaaisyah.traveloapp.ui.screens.home.HomeScreen
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
    onLogout: () -> Unit = {}
) {
    NavHost(
        navController = navController,
        startDestination = startDestination
    ) {
        // Login Screen
        composable(Screen.Login.route) {
            LoginScreen(
                onLoginSuccess = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(Screen.Login.route) { inclusive = true }
                    }
                },
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
                    navController.navigate(Screen.MyBookings.route)
                },
                onExploreClick = {
                    navController.navigate(Screen.Explore.route)
                },
                onCompleteDocuments = { bookingId ->
                    navController.navigate(Screen.CompleteDocuments.createRoute(bookingId))
                }
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
                    navController.navigate(Screen.MyBookings.route)
                },
                onHomeClick = {
                    navController.navigate(Screen.Home.route) {
                        popUpTo(Screen.Explore.route) { inclusive = true }
                    }
                }
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
                }
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
                }
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
                    navController.navigate(Screen.MyBookings.route) {
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
        
        // My Bookings Screen
        composable(Screen.MyBookings.route) {
            MyBookingsScreen(
                onBackClick = { navController.popBackStack() },
                onPayBooking = { bookingId ->
                    navController.navigate(Screen.Payment.createRoute(bookingId))
                },
                onCompleteDocuments = { bookingId ->
                    navController.navigate(Screen.CompleteDocuments.createRoute(bookingId))
                },
                onTicketHistoryClick = {
                    navController.navigate(Screen.TicketHistory.route)
                }
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
        
        // Profile Screen
        composable(Screen.Profile.route) {
            ProfileScreen(
                onBackClick = { navController.popBackStack() },
                onMyBookings = {
                    navController.navigate(Screen.MyBookings.route)
                },
                onTicketHistory = {
                    navController.navigate(Screen.TicketHistory.route)
                },
                onLogout = onLogout
            )
        }
    }
}

