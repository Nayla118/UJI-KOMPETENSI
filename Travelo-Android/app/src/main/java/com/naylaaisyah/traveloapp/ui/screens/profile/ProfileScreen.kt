package com.naylaaisyah.traveloapp.ui.screens.profile

import android.app.DatePickerDialog
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.Logout
import androidx.compose.material.icons.filled.Cake
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Email
import androidx.compose.material.icons.filled.LocationOn
import androidx.compose.material.icons.filled.Phone
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.PhotoCamera
import androidx.compose.material.icons.filled.Verified
import androidx.compose.material.icons.outlined.CloudOff
import androidx.compose.material.icons.outlined.Close
import androidx.compose.material.icons.outlined.Delete
import androidx.compose.material.icons.outlined.Edit
import androidx.compose.material.icons.outlined.ErrorOutline
import androidx.compose.material.icons.outlined.Info
import androidx.compose.material.icons.outlined.Lock
import androidx.compose.material.icons.outlined.Refresh
import androidx.compose.material.icons.outlined.Shield
import androidx.compose.material.ExperimentalMaterialApi
import androidx.compose.material.pullrefresh.PullRefreshIndicator
import androidx.compose.material.pullrefresh.pullRefresh
import androidx.compose.material.pullrefresh.rememberPullRefreshState
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Divider
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Scaffold
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.repeatOnLifecycle
import coil.compose.SubcomposeAsyncImage
import com.naylaaisyah.traveloapp.BuildConfig
import com.naylaaisyah.traveloapp.data.models.SavedIdentity
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonSize
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloStatusChip
import com.naylaaisyah.traveloapp.ui.components.TraveloStatusTone
import com.naylaaisyah.traveloapp.ui.components.TraveloTextField
import com.naylaaisyah.traveloapp.ui.components.TraveloTopBar
import com.naylaaisyah.traveloapp.ui.screens.auth.AuthViewModel
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.CardShape
import com.naylaaisyah.traveloapp.ui.theme.DangerDeep
import com.naylaaisyah.traveloapp.ui.theme.DangerTint
import com.naylaaisyah.traveloapp.ui.theme.FieldShape
import com.naylaaisyah.traveloapp.ui.theme.HeroShape
import com.naylaaisyah.traveloapp.ui.theme.PillShape
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.RoseTint
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.util.describeProfilePhoto
import com.naylaaisyah.traveloapp.util.formatDisplayDate
import com.naylaaisyah.traveloapp.util.readProfilePhotoBytes
import java.time.LocalDate
import java.util.Calendar
import java.util.Locale

/**
 * The profile is a real account screen: every editable value is stored in the
 * backend and every action has a working destination. Email stays read-only
 * because it is the verified Firebase identity used to sign in.
 */
@OptIn(ExperimentalMaterial3Api::class, ExperimentalMaterialApi::class)
@Composable
fun ProfileScreen(
    onBackClick: () -> Unit,
    onLogout: () -> Unit,
    onMyBookings: () -> Unit = {},
    onTicketHistory: () -> Unit = {},
    viewModel: ProfileViewModel = hiltViewModel(),
    authViewModel: AuthViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val snackbarHostState = remember { SnackbarHostState() }
    var showLogoutConfirm by rememberSaveable { mutableStateOf(false) }
    var showDeleteSavedIdentityConfirm by rememberSaveable { mutableStateOf(false) }
    var showPrivacySheet by rememberSaveable { mutableStateOf(false) }
    var showHelpSheet by rememberSaveable { mutableStateOf(false) }
    var showAboutDialog by rememberSaveable { mutableStateOf(false) }

    val context = LocalContext.current
    val photoPicker = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.GetContent()
    ) { uri ->
        if (uri == null) {
            return@rememberLauncherForActivityResult
        }

        val photo = describeProfilePhoto(context, uri)

        if (photo == null) {
            viewModel.showPhotoError("Choose a JPG, PNG, or WebP image up to 10 MB.")
            return@rememberLauncherForActivityResult
        }

        val bytes = readProfilePhotoBytes(context, photo)

        if (bytes == null) {
            viewModel.showPhotoError("Couldn't read that image. Please choose another one.")
            return@rememberLauncherForActivityResult
        }

        viewModel.uploadProfilePhoto(
            bytes = bytes,
            mimeType = photo.mimeType,
            fileName = photo.fileName
        )
    }

    val chooseProfilePhoto = {
        viewModel.clearPhotoError()
        photoPicker.launch("image/*")
    }

    val displayName = uiState.userName?.takeIf { it.isNotBlank() } ?: "Traveler"
    val displayEmail = uiState.userEmail?.takeIf { it.isNotBlank() } ?: "No email on file"
    val completion = remember(
        uiState.userName,
        uiState.userEmail,
        uiState.phone,
        uiState.dateOfBirth,
        uiState.city,
        uiState.savedIdentity
    ) {
        calculateProfileCompletion(
            name = uiState.userName,
            email = uiState.userEmail,
            phone = uiState.phone,
            dateOfBirth = uiState.dateOfBirth,
            city = uiState.city,
            hasSavedIdentity = uiState.savedIdentity != null
        )
    }
    val isRefreshing = uiState.statsLoading && uiState.hasLoadedProfile
    val pullRefreshState = rememberPullRefreshState(
        refreshing = isRefreshing,
        onRefresh = viewModel::refresh
    )

    val lifecycleOwner = LocalLifecycleOwner.current
    LaunchedEffect(lifecycleOwner) {
        lifecycleOwner.lifecycle.repeatOnLifecycle(Lifecycle.State.RESUMED) {
            viewModel.refresh()
        }
    }

    LaunchedEffect(uiState.message) {
        uiState.message?.let { message ->
            snackbarHostState.showSnackbar(message)
            viewModel.clearMessage()
        }
    }

    LaunchedEffect(uiState.loadError, uiState.hasLoadedProfile) {
        if (uiState.hasLoadedProfile) {
            uiState.loadError?.let { message ->
                snackbarHostState.showSnackbar(message)
                viewModel.clearLoadError()
            }
        }
    }

    Scaffold(
        topBar = {
            TraveloTopBar(
                title = "Profile",
                onBackClick = onBackClick,
                actions = {
                    IconButton(
                        onClick = viewModel::refresh,
                        enabled = !uiState.isLoading &&
                            !uiState.statsLoading &&
                            !uiState.savedIdentityLoading
                    ) {
                        Icon(
                            imageVector = Icons.Outlined.Refresh,
                            contentDescription = "Refresh profile",
                            tint = PlumInk
                        )
                    }
                }
            )
        },
        snackbarHost = { SnackbarHost(hostState = snackbarHostState) },
        containerColor = VanillaCream
    ) { paddingValues ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .pullRefresh(pullRefreshState)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .padding(horizontal = TraveloSpacing.screen)
            ) {
                Spacer(Modifier.height(TraveloSpacing.xs))

                ProfileHeader(
                    displayName = displayName,
                    userEmail = displayEmail,
                    userPhoto = uiState.userPhoto,
                    memberSince = formatMemberSince(uiState.createdAt),
                    tripsCount = uiState.tripsCount,
                    upcomingTrips = uiState.upcomingTrips,
                    statsLoading = uiState.statsLoading,
                    isUploadingPhoto = uiState.isUploadingPhoto,
                    canEdit = uiState.hasLoadedProfile &&
                        !uiState.isLoading &&
                        !uiState.isUploadingPhoto,
                    onEdit = viewModel::openEdit
                )

                if (uiState.hasLoadedProfile) {
                    Spacer(Modifier.height(TraveloSpacing.sm))
                    ProfileCompletionCard(
                        completion = completion,
                        onEdit = viewModel::openEdit
                    )
                }

                Spacer(Modifier.height(TraveloSpacing.lg))

                ProfileSectionLabel("Quick actions")

                Spacer(Modifier.height(TraveloSpacing.xs))

                QuickActionsSection(
                    bookingsSubtitle = bookingSubtitle(uiState),
                    onMyBookings = onMyBookings,
                    onTicketHistory = onTicketHistory,
                    onEditProfile = viewModel::openEdit,
                    onHelp = { showHelpSheet = true }
                )

                val nextTrip = uiState.nextTrip
                if (nextTrip != null) {
                    Spacer(Modifier.height(TraveloSpacing.lg))
                    ProfileSectionLabel("Next trip")
                    Spacer(Modifier.height(TraveloSpacing.xs))
                    NextTripCard(
                        booking = nextTrip,
                        onClick = onMyBookings
                    )
                }

                Spacer(Modifier.height(TraveloSpacing.lg))

                ProfileSectionLabel("Personal details")

                Spacer(Modifier.height(TraveloSpacing.xs))

                when {
                    !uiState.hasLoadedProfile && uiState.isLoading -> {
                        ProfileDetailsLoading()
                    }

                    !uiState.hasLoadedProfile && uiState.loadError != null -> {
                        ProfileDetailsError(
                            message = uiState.loadError.orEmpty(),
                            onRetry = viewModel::loadProfile
                        )
                    }

                    else -> {
                        PersonalDetailsCard(
                            displayName = displayName,
                            displayEmail = displayEmail,
                            phone = uiState.phone,
                            dateOfBirth = uiState.dateOfBirth,
                            city = uiState.city,
                            onEdit = viewModel::openEdit
                        )
                    }
                }

                Spacer(Modifier.height(TraveloSpacing.lg))

                ProfileSectionLabel("Saved identity")

                Spacer(Modifier.height(TraveloSpacing.xs))

                SavedIdentitySection(
                    uiState = uiState,
                    onRetry = viewModel::loadSavedIdentity,
                    onDelete = {
                        viewModel.clearSavedIdentityDeleteError()
                        showDeleteSavedIdentityConfirm = true
                    }
                )

                Spacer(Modifier.height(TraveloSpacing.lg))

                ProfileSectionLabel("Account")

                Spacer(Modifier.height(TraveloSpacing.xs))

                TraveloCard(contentPadding = PaddingValues(0.dp)) {
                    ProfileActionRow(
                        icon = Icons.Outlined.Shield,
                        title = "Privacy & data",
                        subtitle = "Review saved identity and document handling",
                        onClick = { showPrivacySheet = true }
                    )
                    ProfileDetailDivider()
                    ProfileActionRow(
                        icon = Icons.Outlined.Info,
                        title = "About Travelo",
                        subtitle = "Version ${BuildConfig.VERSION_NAME}",
                        onClick = { showAboutDialog = true }
                    )
                }

                Spacer(Modifier.height(TraveloSpacing.lg))

                TraveloButton(
                    text = "Log out",
                    onClick = { showLogoutConfirm = true },
                    variant = TraveloButtonVariant.Secondary,
                    leadingIcon = Icons.AutoMirrored.Filled.Logout
                )

                Spacer(Modifier.height(TraveloSpacing.sm))

                Text(
                    text = "Signed in as $displayEmail",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth()
                )

                Spacer(Modifier.height(TraveloSpacing.xl))
            }

            PullRefreshIndicator(
                refreshing = isRefreshing,
                state = pullRefreshState,
                modifier = Modifier.align(Alignment.TopCenter),
                backgroundColor = SurfaceWhite,
                contentColor = Rosewood
            )
        }
    }

    if (uiState.isEditOpen) {
        EditProfileSheet(
            uiState = uiState,
            onDismiss = viewModel::dismissEdit,
            onNameChange = viewModel::onEditNameChange,
            onPhoneChange = viewModel::onEditPhoneChange,
            onDateOfBirthChange = viewModel::onEditDateOfBirthChange,
            onCityChange = viewModel::onEditCityChange,
            onChangePhoto = chooseProfilePhoto,
            onSave = viewModel::saveProfile
        )
    }

    if (showLogoutConfirm) {
        LogoutDialog(
            onDismiss = { showLogoutConfirm = false },
            onConfirm = {
                showLogoutConfirm = false
                authViewModel.logout()
                onLogout()
            }
        )
    }

    if (showDeleteSavedIdentityConfirm) {
        DeleteSavedIdentityDialog(
            onDismiss = { showDeleteSavedIdentityConfirm = false },
            onConfirm = {
                showDeleteSavedIdentityConfirm = false
                viewModel.deleteSavedIdentity()
            }
        )
    }

    if (showPrivacySheet) {
        PrivacyDataSheet(
            savedIdentity = uiState.savedIdentity,
            onDismiss = { showPrivacySheet = false },
            onDeleteIdentity = {
                showPrivacySheet = false
                viewModel.clearSavedIdentityDeleteError()
                showDeleteSavedIdentityConfirm = true
            }
        )
    }

    if (showHelpSheet) {
        HelpSupportSheet(onDismiss = { showHelpSheet = false })
    }

    if (showAboutDialog) {
        AboutTraveloDialog(onDismiss = { showAboutDialog = false })
    }
}

@Composable
private fun ProfileSectionLabel(text: String) {
    Text(
        text = text,
        style = MaterialTheme.typography.labelLarge,
        color = Rosewood,
        modifier = Modifier.padding(start = TraveloSpacing.xxs)
    )
}

@Composable
private fun ProfileHeader(
    displayName: String,
    userEmail: String,
    userPhoto: String?,
    memberSince: String?,
    tripsCount: Int?,
    upcomingTrips: Int?,
    statsLoading: Boolean,
    isUploadingPhoto: Boolean,
    canEdit: Boolean,
    onEdit: () -> Unit
) {
    val initials = initialsOf(displayName)
    val unavailable = if (statsLoading) "…" else "—"

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(HeroShape)
            .background(
                Brush.linearGradient(
                    colors = listOf(BlushMist, RoseTint, SurfaceWhite)
                )
            )
            .border(
                width = 1.dp,
                color = PlumBorder.copy(alpha = 0.75f),
                shape = HeroShape
            )
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(TraveloSpacing.lg)
        ) {
            Row(verticalAlignment = Alignment.Top) {
                Box(
                    modifier = Modifier
                        .size(92.dp)
                        .clickable(enabled = canEdit, onClick = onEdit)
                ) {
                    Box(
                        modifier = Modifier
                            .fillMaxSize()
                            .clip(CircleShape)
                            .background(SurfaceWhite)
                            .padding(4.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        if (!userPhoto.isNullOrBlank()) {
                            SubcomposeAsyncImage(
                                model = userPhoto,
                                contentDescription = "Profile photo",
                                modifier = Modifier
                                    .fillMaxSize()
                                    .clip(CircleShape),
                                contentScale = ContentScale.Crop,
                                loading = { InitialsAvatar(initials) },
                                error = { InitialsAvatar(initials) }
                            )
                        } else {
                            InitialsAvatar(initials)
                        }

                        if (isUploadingPhoto) {
                            CircularProgressIndicator(
                                color = Rosewood,
                                strokeWidth = 2.dp,
                                modifier = Modifier.size(30.dp)
                            )
                        }
                    }

                    Surface(
                        shape = CircleShape,
                        color = SurfaceWhite,
                        border = BorderStroke(1.dp, PlumBorder),
                        modifier = Modifier
                            .align(Alignment.BottomStart)
                            .size(26.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.PhotoCamera,
                            contentDescription = "Change profile photo",
                            tint = Rosewood,
                            modifier = Modifier.padding(5.dp)
                        )
                    }

                    Surface(
                        shape = CircleShape,
                        color = Rosewood,
                        border = BorderStroke(2.dp, SurfaceWhite),
                        modifier = Modifier
                            .align(Alignment.BottomEnd)
                            .size(26.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Filled.Verified,
                            contentDescription = "Verified account",
                            tint = SurfaceWhite,
                            modifier = Modifier.padding(4.dp)
                        )
                    }
                }

                Spacer(Modifier.width(TraveloSpacing.md))

                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = displayName,
                        style = MaterialTheme.typography.headlineSmall,
                        color = PlumInk,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )

                    Spacer(Modifier.height(2.dp))

                    Text(
                        text = userEmail,
                        style = MaterialTheme.typography.bodySmall,
                        color = PlumMuted,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis
                    )

                    Spacer(Modifier.height(TraveloSpacing.sm))

                    EditProfilePill(enabled = canEdit, onClick = onEdit)
                }
            }

            Spacer(Modifier.height(TraveloSpacing.lg))

            Divider(
                color = PlumBorder.copy(alpha = 0.85f),
                thickness = 1.dp
            )

            Spacer(Modifier.height(TraveloSpacing.md))

            Row(modifier = Modifier.fillMaxWidth()) {
                ProfileStat(
                    value = tripsCount?.toString() ?: unavailable,
                    label = "TRIPS",
                    modifier = Modifier.weight(1f)
                )
                ProfileStatDivider()
                ProfileStat(
                    value = upcomingTrips?.toString() ?: unavailable,
                    label = "UPCOMING",
                    modifier = Modifier.weight(1f)
                )
                ProfileStatDivider()
                ProfileStat(
                    value = memberSince ?: unavailable,
                    label = "MEMBER SINCE",
                    modifier = Modifier.weight(1f)
                )
            }
        }
    }
}

@Composable
private fun EditProfilePill(enabled: Boolean, onClick: () -> Unit) {
    val contentColor = if (enabled) Rosewood else PlumMuted
    Surface(
        onClick = onClick,
        enabled = enabled,
        shape = PillShape,
        color = SurfaceWhite,
        tonalElevation = 0.dp,
        border = BorderStroke(1.dp, PlumBorder)
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = Icons.Outlined.Edit,
                contentDescription = null,
                tint = contentColor,
                modifier = Modifier.size(16.dp)
            )
            Spacer(Modifier.width(6.dp))
            Text(
                text = "Edit profile",
                style = MaterialTheme.typography.labelMedium,
                color = contentColor
            )
        }
    }
}

@Composable
private fun ProfileStat(
    value: String,
    label: String,
    modifier: Modifier = Modifier
) {
    Column(
        modifier = modifier,
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = value,
            style = MaterialTheme.typography.titleLarge,
            color = PlumInk,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis
        )
        Spacer(Modifier.height(2.dp))
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            color = PlumMuted,
            maxLines = 1
        )
    }
}

@Composable
private fun ProfileStatDivider() {
    Box(
        modifier = Modifier
            .padding(horizontal = TraveloSpacing.xs)
            .width(1.dp)
            .height(32.dp)
            .background(PlumBorder)
    )
}

@Composable
private fun PersonalDetailsCard(
    displayName: String,
    displayEmail: String,
    phone: String?,
    dateOfBirth: String?,
    city: String?,
    onEdit: () -> Unit
) {
    TraveloCard(contentPadding = PaddingValues(0.dp)) {
        ProfileDetailRow(
            icon = Icons.Default.Person,
            label = "Full name",
            value = displayName,
            onClick = onEdit
        )
        ProfileDetailDivider()
        ProfileDetailRow(
            icon = Icons.Default.Email,
            label = "Email",
            value = displayEmail,
            supporting = "Managed by your sign-in provider",
            trailingIcon = Icons.Outlined.Lock
        )
        ProfileDetailDivider()
        ProfileDetailRow(
            icon = Icons.Default.Phone,
            label = "Phone number",
            value = phone ?: "Add a phone number",
            isPlaceholder = phone.isNullOrBlank(),
            onClick = onEdit
        )
        ProfileDetailDivider()
        ProfileDetailRow(
            icon = Icons.Default.Cake,
            label = "Date of birth",
            value = if (dateOfBirth.isNullOrBlank()) {
                "Add your date of birth"
            } else {
                formatDisplayDate(dateOfBirth)
            },
            isPlaceholder = dateOfBirth.isNullOrBlank(),
            onClick = onEdit
        )
        ProfileDetailDivider()
        ProfileDetailRow(
            icon = Icons.Default.LocationOn,
            label = "Home city",
            value = city ?: "Add your city",
            isPlaceholder = city.isNullOrBlank(),
            onClick = onEdit
        )
    }
}

@Composable
private fun ProfileDetailRow(
    icon: ImageVector,
    label: String,
    value: String,
    supporting: String? = null,
    isPlaceholder: Boolean = false,
    trailingIcon: ImageVector? = Icons.Outlined.Edit,
    onClick: (() -> Unit)? = null
) {
    val rowModifier = if (onClick != null) {
        Modifier.clickable(onClick = onClick)
    } else {
        Modifier
    }

    Row(
        modifier = rowModifier
            .fillMaxWidth()
            .padding(horizontal = TraveloSpacing.md, vertical = TraveloSpacing.sm),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Surface(
            shape = CircleShape,
            color = BlushMist
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier
                    .padding(10.dp)
                    .size(18.dp)
            )
        }

        Spacer(Modifier.width(TraveloSpacing.sm))

        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = label,
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )
            Text(
                text = value,
                style = MaterialTheme.typography.bodyLarge,
                color = if (isPlaceholder) PlumMuted else PlumInk,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis
            )
            if (supporting != null) {
                Spacer(Modifier.height(2.dp))
                Text(
                    text = supporting,
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }
        }

        if (trailingIcon != null) {
            Spacer(Modifier.width(TraveloSpacing.xs))
            Icon(
                imageVector = trailingIcon,
                contentDescription = null,
                tint = PlumMuted,
                modifier = Modifier.size(18.dp)
            )
        }
    }
}

@Composable
private fun ProfileDetailDivider() {
    Divider(
        modifier = Modifier.padding(start = 64.dp),
        color = PlumBorder.copy(alpha = 0.75f),
        thickness = 1.dp
    )
}

@Composable
private fun ProfileDetailsLoading(message: String = "Loading your details…") {
    TraveloCard {
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            CircularProgressIndicator(
                modifier = Modifier.size(20.dp),
                color = Rosewood,
                strokeWidth = 2.dp
            )
            Spacer(Modifier.width(TraveloSpacing.sm))
            Text(
                text = message,
                style = MaterialTheme.typography.bodyMedium,
                color = PlumMuted
            )
        }
    }
}

@Composable
private fun ProfileDetailsError(
    message: String,
    onRetry: () -> Unit,
    title: String = "Couldn't load your profile"
) {
    TraveloCard {
        Row(verticalAlignment = Alignment.Top) {
            Icon(
                imageVector = Icons.Outlined.CloudOff,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier.size(22.dp)
            )
            Spacer(Modifier.width(TraveloSpacing.sm))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = message,
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }
        }
        Spacer(Modifier.height(TraveloSpacing.xs))
        TextButton(
            onClick = onRetry,
            modifier = Modifier.align(Alignment.End)
        ) {
            Text(
                text = "Try again",
                style = MaterialTheme.typography.labelLarge,
                color = Rosewood
            )
        }
    }
}

@Composable
private fun SavedIdentitySection(
    uiState: ProfileUiState,
    onRetry: () -> Unit,
    onDelete: () -> Unit
) {
    val identity = uiState.savedIdentity

    when {
        identity != null -> {
            SavedIdentityCard(
                identity = identity,
                isDeleting = uiState.isDeletingSavedIdentity,
                deleteError = uiState.savedIdentityDeleteError,
                onDelete = onDelete
            )
        }

        uiState.savedIdentityError != null && !uiState.savedIdentityLoading -> {
            ProfileDetailsError(
                title = "Couldn't load your saved identity",
                message = uiState.savedIdentityError.orEmpty(),
                onRetry = onRetry
            )
        }

        !uiState.hasLoadedSavedIdentity || uiState.savedIdentityLoading -> {
            ProfileDetailsLoading(message = "Loading your saved identity…")
        }

        else -> {
            NoSavedIdentityCard()
        }
    }
}

@Composable
private fun NoSavedIdentityCard() {
    TraveloCard {
        Row(verticalAlignment = Alignment.Top) {
            Surface(
                shape = CircleShape,
                color = BlushMist
            ) {
                Icon(
                    imageVector = Icons.Outlined.Lock,
                    contentDescription = null,
                    tint = Rosewood,
                    modifier = Modifier
                        .padding(10.dp)
                        .size(18.dp)
                )
            }

            Spacer(Modifier.width(TraveloSpacing.sm))

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "No saved identity yet",
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = "Your booker details and private document are saved after your first booking. Future bookings can prefill them automatically.",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }
        }
    }
}

@Composable
private fun SavedIdentityCard(
    identity: SavedIdentity,
    isDeleting: Boolean,
    deleteError: String?,
    onDelete: () -> Unit
) {
    TraveloCard {
        Row(verticalAlignment = Alignment.Top) {
            Surface(
                shape = CircleShape,
                color = BlushMist
            ) {
                Icon(
                    imageVector = Icons.Outlined.Lock,
                    contentDescription = null,
                    tint = Rosewood,
                    modifier = Modifier
                        .padding(10.dp)
                        .size(18.dp)
                )
            }

            Spacer(Modifier.width(TraveloSpacing.sm))

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = identity.name,
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = "${savedIdentityTypeLabel(identity)} · ${
                        maskSavedIdentityNumber(identity.identityNumber)
                    }",
                    style = MaterialTheme.typography.bodyMedium,
                    color = PlumInk
                )
                Spacer(Modifier.height(TraveloSpacing.xs))
                TraveloStatusChip(
                    label = savedIdentityDocumentLabel(identity),
                    tone = savedIdentityTone(identity)
                )
                Spacer(Modifier.height(TraveloSpacing.xs))
                Text(
                    text = "Used to prefill future bookings. Replace the document from a booking form.",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
                identity.updatedAt?.takeIf { it.isNotBlank() }?.let { updatedAt ->
                    Spacer(Modifier.height(2.dp))
                    Text(
                        text = "Last updated ${formatDisplayDate(updatedAt)}",
                        style = MaterialTheme.typography.bodySmall,
                        color = PlumMuted
                    )
                }
            }
        }

        if (deleteError != null) {
            Spacer(Modifier.height(TraveloSpacing.sm))
            InlineError(deleteError)
        }

        Spacer(Modifier.height(TraveloSpacing.md))

        TraveloButton(
            text = "Delete saved identity",
            onClick = onDelete,
            variant = TraveloButtonVariant.Secondary,
            loading = isDeleting,
            enabled = !isDeleting,
            leadingIcon = Icons.Outlined.Delete
        )
    }
}

@Composable
private fun DeleteSavedIdentityDialog(
    onDismiss: () -> Unit,
    onConfirm: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = SurfaceWhite,
        shape = CardShape,
        title = {
            Text(
                text = "Delete saved identity?",
                style = MaterialTheme.typography.titleLarge,
                color = PlumInk
            )
        },
        text = {
            Text(
                text = "This removes the identity details and private document copy saved for your account. Past bookings and their documents are not affected. You can fill them again on your next booking.",
                style = MaterialTheme.typography.bodyMedium,
                color = PlumMuted
            )
        },
        confirmButton = {
            TextButton(onClick = onConfirm) {
                Text(
                    text = "Delete",
                    style = MaterialTheme.typography.labelLarge,
                    color = DangerDeep
                )
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text(
                    text = "Cancel",
                    style = MaterialTheme.typography.labelLarge,
                    color = Rosewood
                )
            }
        }
    )
}

@Composable
private fun ProfileActionRow(
    icon: ImageVector,
    title: String,
    subtitle: String,
    onClick: () -> Unit
) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick)
            .padding(TraveloSpacing.md),
        verticalAlignment = Alignment.CenterVertically
    ) {
        Surface(
            shape = FieldShape,
            color = BlushMist
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier
                    .padding(10.dp)
                    .size(20.dp)
            )
        }

        Spacer(Modifier.width(TraveloSpacing.sm))

        Column(modifier = Modifier.weight(1f)) {
            Text(
                text = title,
                style = MaterialTheme.typography.titleSmall,
                color = PlumInk
            )
            Text(
                text = subtitle,
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )
        }

        Spacer(Modifier.width(TraveloSpacing.xs))

        Icon(
            imageVector = Icons.Default.ChevronRight,
            contentDescription = null,
            tint = PlumMuted,
            modifier = Modifier.size(20.dp)
        )
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun EditProfileSheet(
    uiState: ProfileUiState,
    onDismiss: () -> Unit,
    onNameChange: (String) -> Unit,
    onPhoneChange: (String) -> Unit,
    onDateOfBirthChange: (String) -> Unit,
    onCityChange: (String) -> Unit,
    onChangePhoto: () -> Unit,
    onSave: () -> Unit
) {
    val busy = uiState.isSaving || uiState.isUploadingPhoto
    val context = LocalContext.current
    val sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true)

    val datePickerDialog = remember(uiState.editDateOfBirth) {
        val initial = parseIsoDate(uiState.editDateOfBirth) ?: LocalDate.of(2000, 1, 1)
        DatePickerDialog(
            context,
            { _, year, month, dayOfMonth ->
                onDateOfBirthChange(
                    String.format(Locale.US, "%04d-%02d-%02d", year, month + 1, dayOfMonth)
                )
            },
            initial.year,
            initial.monthValue - 1,
            initial.dayOfMonth
        ).apply {
            // Match the backend rule: a birth date must be before today.
            datePicker.maxDate = System.currentTimeMillis() - 86_400_000L
            datePicker.minDate = Calendar.getInstance().apply {
                set(1900, Calendar.JANUARY, 1, 0, 0, 0)
                set(Calendar.MILLISECOND, 0)
            }.timeInMillis
        }
    }

    ModalBottomSheet(
        onDismissRequest = onDismiss,
        sheetState = sheetState,
        containerColor = VanillaCream
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .imePadding()
                .verticalScroll(rememberScrollState())
                .navigationBarsPadding()
                .padding(horizontal = TraveloSpacing.screen)
                .padding(bottom = TraveloSpacing.lg)
        ) {
            Text(
                text = "Edit profile",
                style = MaterialTheme.typography.headlineSmall,
                color = PlumInk
            )
            Spacer(Modifier.height(2.dp))
            Text(
                text = "Keep these details up to date for smoother bookings.",
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )

            Spacer(Modifier.height(TraveloSpacing.lg))

            ProfilePhotoEditor(
                photoUrl = uiState.userPhoto,
                displayName = uiState.userName,
                isUploading = uiState.isUploadingPhoto,
                enabled = !busy,
                onChangePhoto = onChangePhoto
            )

            if (uiState.photoError != null) {
                Spacer(Modifier.height(TraveloSpacing.sm))
                InlineError(message = uiState.photoError)
            }

            Spacer(Modifier.height(TraveloSpacing.lg))

            TraveloTextField(
                value = uiState.editName,
                onValueChange = onNameChange,
                label = "Full name",
                leadingIcon = Icons.Default.Person,
                isError = uiState.editNameError != null,
                supportingText = uiState.editNameError,
                keyboardOptions = KeyboardOptions(
                    capitalization = androidx.compose.ui.text.input.KeyboardCapitalization.Words
                ),
                enabled = !busy
            )

            Spacer(Modifier.height(TraveloSpacing.sm))

            ReadOnlyEmail(email = uiState.userEmail.orEmpty())

            Spacer(Modifier.height(TraveloSpacing.sm))

            TraveloTextField(
                value = uiState.editPhone,
                onValueChange = onPhoneChange,
                label = "Phone number",
                placeholder = "+62 812 3456 7890",
                leadingIcon = Icons.Default.Phone,
                isError = uiState.editPhoneError != null,
                supportingText = uiState.editPhoneError ?: "Optional",
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                enabled = !busy
            )

            Spacer(Modifier.height(TraveloSpacing.sm))

            DateOfBirthField(
                value = uiState.editDateOfBirth,
                enabled = !busy,
                onClick = { datePickerDialog.show() },
                onClear = { onDateOfBirthChange("") }
            )

            Spacer(Modifier.height(TraveloSpacing.sm))

            TraveloTextField(
                value = uiState.editCity,
                onValueChange = onCityChange,
                label = "Home city",
                placeholder = "Bandung",
                leadingIcon = Icons.Default.LocationOn,
                isError = uiState.editCityError != null,
                supportingText = uiState.editCityError ?: "Optional",
                keyboardOptions = KeyboardOptions(
                    capitalization = androidx.compose.ui.text.input.KeyboardCapitalization.Words
                ),
                enabled = !busy
            )

            if (uiState.saveError != null) {
                Spacer(Modifier.height(TraveloSpacing.md))
                InlineError(message = uiState.saveError)
            }

            Spacer(Modifier.height(TraveloSpacing.lg))

            TraveloButton(
                text = "Save changes",
                onClick = onSave,
                loading = uiState.isSaving,
                enabled = !busy
            )

            Spacer(Modifier.height(TraveloSpacing.xs))

            TextButton(
                onClick = onDismiss,
                enabled = !busy,
                modifier = Modifier.fillMaxWidth()
            ) {
                Text(
                    text = "Cancel",
                    style = MaterialTheme.typography.labelLarge,
                    color = if (uiState.isSaving) PlumMuted else Rosewood
                )
            }
        }
    }
}

@Composable
private fun ProfilePhotoEditor(
    photoUrl: String?,
    displayName: String?,
    isUploading: Boolean,
    enabled: Boolean,
    onChangePhoto: () -> Unit
) {
    val initials = initialsOf(displayName.orEmpty())

    Column(modifier = Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                modifier = Modifier
                    .size(76.dp)
                    .clip(CircleShape)
                    .background(SurfaceWhite)
                    .padding(4.dp),
                contentAlignment = Alignment.Center
            ) {
                if (!photoUrl.isNullOrBlank()) {
                    SubcomposeAsyncImage(
                        model = photoUrl,
                        contentDescription = "Profile photo",
                        modifier = Modifier
                            .fillMaxSize()
                            .clip(CircleShape),
                        contentScale = ContentScale.Crop,
                        loading = { InitialsAvatar(initials) },
                        error = { InitialsAvatar(initials) }
                    )
                } else {
                    InitialsAvatar(initials)
                }

                if (isUploading) {
                    CircularProgressIndicator(
                        color = Rosewood,
                        strokeWidth = 2.dp,
                        modifier = Modifier.size(28.dp)
                    )
                }
            }

            Spacer(Modifier.width(TraveloSpacing.md))

            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Profile photo",
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk
                )
                Spacer(Modifier.height(2.dp))
                Text(
                    text = "JPG, PNG, or WebP up to 10 MB.",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }
        }

        Spacer(Modifier.height(TraveloSpacing.sm))

        TraveloButton(
            text = if (isUploading) "Uploading photo…" else "Change photo",
            onClick = onChangePhoto,
            variant = TraveloButtonVariant.Secondary,
            size = TraveloButtonSize.Medium,
            enabled = enabled,
            loading = isUploading,
            leadingIcon = Icons.Default.PhotoCamera
        )
    }
}

@Composable
private fun ReadOnlyEmail(email: String) {
    Surface(
        shape = FieldShape,
        color = RoseTint,
        border = BorderStroke(1.dp, PlumBorder)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = TraveloSpacing.md, vertical = TraveloSpacing.sm),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = Icons.Outlined.Lock,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier.size(20.dp)
            )
            Spacer(Modifier.width(TraveloSpacing.sm))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Email",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
                Text(
                    text = email.ifBlank { "No email on file" },
                    style = MaterialTheme.typography.bodyLarge,
                    color = PlumInk,
                    maxLines = 1,
                    overflow = TextOverflow.Ellipsis
                )
                Text(
                    text = "Managed by your sign-in provider",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }
        }
    }
}

@Composable
private fun DateOfBirthField(
    value: String,
    enabled: Boolean,
    onClick: () -> Unit,
    onClear: () -> Unit
) {
    Surface(
        onClick = onClick,
        enabled = enabled,
        shape = FieldShape,
        color = SurfaceWhite,
        tonalElevation = 0.dp,
        border = BorderStroke(1.dp, PlumBorder)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .heightIn(min = 64.dp)
                .padding(start = TraveloSpacing.md, end = TraveloSpacing.xxs),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = Icons.Default.CalendarMonth,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier.size(20.dp)
            )
            Spacer(Modifier.width(TraveloSpacing.sm))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Date of birth",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
                Text(
                    text = if (value.isBlank()) {
                        "Choose your date of birth"
                    } else {
                        formatDisplayDate(value)
                    },
                    style = MaterialTheme.typography.bodyLarge,
                    color = if (value.isBlank()) PlumMuted else PlumInk
                )
            }
            if (value.isNotBlank()) {
                IconButton(onClick = onClear, enabled = enabled) {
                    Icon(
                        imageVector = Icons.Outlined.Close,
                        contentDescription = "Clear date of birth",
                        tint = PlumMuted,
                        modifier = Modifier.size(18.dp)
                    )
                }
            } else {
                Icon(
                    imageVector = Icons.Default.DateRange,
                    contentDescription = null,
                    tint = PlumMuted,
                    modifier = Modifier
                        .padding(end = TraveloSpacing.sm)
                        .size(20.dp)
                )
            }
        }
    }
}

@Composable
private fun InlineError(message: String) {
    Surface(
        shape = CardShape,
        color = DangerTint,
        border = BorderStroke(1.dp, DangerDeep.copy(alpha = 0.18f))
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(TraveloSpacing.sm),
            verticalAlignment = Alignment.Top
        ) {
            Icon(
                imageVector = Icons.Outlined.ErrorOutline,
                contentDescription = null,
                tint = DangerDeep,
                modifier = Modifier.size(18.dp)
            )
            Spacer(Modifier.width(TraveloSpacing.xs))
            Text(
                text = message,
                style = MaterialTheme.typography.bodySmall,
                color = DangerDeep
            )
        }
    }
}

@Composable
private fun LogoutDialog(
    onDismiss: () -> Unit,
    onConfirm: () -> Unit
) {
    AlertDialog(
        onDismissRequest = onDismiss,
        containerColor = SurfaceWhite,
        shape = CardShape,
        title = {
            Text(
                text = "Log out?",
                style = MaterialTheme.typography.titleLarge,
                color = PlumInk
            )
        },
        text = {
            Text(
                text = "You'll need to sign in again to view your bookings and e-tickets.",
                style = MaterialTheme.typography.bodyMedium,
                color = PlumMuted
            )
        },
        confirmButton = {
            TextButton(onClick = onConfirm) {
                Text(
                    text = "Log out",
                    style = MaterialTheme.typography.labelLarge,
                    color = DangerDeep
                )
            }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) {
                Text(
                    text = "Cancel",
                    style = MaterialTheme.typography.labelLarge,
                    color = Rosewood
                )
            }
        }
    )
}

@Composable
private fun InitialsAvatar(initials: String) {
    Text(
        text = initials,
        style = MaterialTheme.typography.headlineLarge,
        color = Rosewood,
        textAlign = TextAlign.Center
    )
}

private fun initialsOf(name: String): String {
    val initials = name
        .trim()
        .split(Regex("\\s+"))
        .mapNotNull { part -> part.firstOrNull()?.uppercaseChar() }
        .take(2)
        .joinToString("")
    return initials.ifBlank { "T" }
}

private fun savedIdentityTone(identity: SavedIdentity): TraveloStatusTone {
    return when {
        identity.canReuseDocument -> TraveloStatusTone.Success
        identity.documentStatus.equals("rejected", ignoreCase = true) -> TraveloStatusTone.Danger
        identity.documentStatus.equals("pending", ignoreCase = true) -> TraveloStatusTone.Warning
        identity.hasDocument -> TraveloStatusTone.Success
        else -> TraveloStatusTone.Neutral
    }
}

private fun bookingSubtitle(state: ProfileUiState): String {
    if (state.statsError) {
        return "View bookings and payment status"
    }

    val trips = state.tripsCount ?: return if (state.statsLoading) {
        "Loading your trips…"
    } else {
        "View bookings and payment status"
    }

    if (trips == 0) {
        return "No trips booked yet"
    }

    val upcoming = state.upcomingTrips ?: 0
    if (upcoming > 0) {
        val tripLabel = if (trips == 1) "trip" else "trips"
        return "$trips $tripLabel · $upcoming upcoming"
    }

    return if (trips == 1) "1 trip booked" else "$trips trips booked"
}

private fun formatMemberSince(raw: String?): String? {
    if (raw.isNullOrBlank()) return null

    val parts = raw.take(10).split("-")
    if (parts.size != 3) return null

    val monthIndex = parts.getOrNull(1)?.toIntOrNull()?.minus(1) ?: return null
    val month = listOf(
        "Jan", "Feb", "Mar", "Apr", "Mei", "Jun",
        "Jul", "Agu", "Sep", "Okt", "Nov", "Des"
    ).getOrNull(monthIndex) ?: return null

    return "$month ${parts[0]}"
}

private fun parseIsoDate(raw: String): LocalDate? {
    if (raw.isBlank()) return null
    return runCatching { LocalDate.parse(raw.take(10)) }.getOrNull()
}
