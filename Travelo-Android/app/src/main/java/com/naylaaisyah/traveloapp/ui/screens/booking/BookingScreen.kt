package com.naylaaisyah.traveloapp.ui.screens.booking

import android.app.DatePickerDialog
import android.content.Intent
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.ArrowDropDown
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.CloudUpload
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Error
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Remove
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material3.Checkbox
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.naylaaisyah.traveloapp.data.models.VisitorDocumentInput
import com.naylaaisyah.traveloapp.data.models.VisitorInput
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloIconButton
import com.naylaaisyah.traveloapp.ui.components.TraveloPriceText
import com.naylaaisyah.traveloapp.ui.components.TraveloTextField
import com.naylaaisyah.traveloapp.ui.theme.CardShape
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.DangerDeep
import com.naylaaisyah.traveloapp.ui.theme.DangerTint
import com.naylaaisyah.traveloapp.ui.theme.FieldShape
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.util.SelectedDocument
import com.naylaaisyah.traveloapp.util.describeDocument
import com.naylaaisyah.traveloapp.util.formatDisplayDate
import com.naylaaisyah.traveloapp.util.formatRupiah
import com.naylaaisyah.traveloapp.util.readDocumentBytes
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

/**
 * Step 1 of the flow: booking details + one identity row per traveller.
 *
 * The booking is created first and each selected document is uploaded
 * afterwards (a visitor needs an id before it can own a file). The screen
 * only navigates to payment once every upload succeeded; if one fails the
 * booking is kept and the button becomes a retry.
 */
@Composable
fun BookingScreen(
    tourPackageId: Int,
    tourPackageTitle: String,
    tourPackagePrice: Int,
    tourPackageMaxPeople: Int = 10,
    onBackClick: () -> Unit,
    onPaymentSuccess: (Int) -> Unit,
    onContinueToPayment: (Int) -> Unit = onPaymentSuccess,
    viewModel: BookingViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    var selectedDate by remember { mutableStateOf("") }
    var peopleCount by remember { mutableIntStateOf(1) }
    var specialRequests by remember { mutableStateOf("") }
    var privacyConsent by remember { mutableStateOf(false) }
    var localError by remember { mutableStateOf<String?>(null) }
    var preparingDocuments by remember { mutableStateOf(false) }
    var pickerTarget by remember { mutableIntStateOf(-1) }
    var savedPrefillApplied by remember { mutableStateOf(false) }

    val visitorForms = remember { mutableStateListOf(VisitorFormState()) }

    val maxPeople = if (tourPackageMaxPeople > 0) tourPackageMaxPeople else 10
    val totalPrice = tourPackagePrice.toLong() * peopleCount

    val datePickerDialog = remember {
        val calendar = Calendar.getInstance()
        DatePickerDialog(
            context,
            { _, year, month, dayOfMonth ->
                val chosen = Calendar.getInstance().apply {
                    set(year, month, dayOfMonth)
                }
                selectedDate = SimpleDateFormat("yyyy-MM-dd", Locale.US).format(chosen.time)
            },
            calendar.get(Calendar.YEAR),
            calendar.get(Calendar.MONTH),
            calendar.get(Calendar.DAY_OF_MONTH)
        ).apply {
            // Minus a second so "today" stays selectable on devices that round
            // the minimum date to the start of the next day.
            datePicker.minDate = System.currentTimeMillis() - 1_000
        }
    }

    val documentPicker = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.OpenDocument()
    ) { uri ->
        val index = pickerTarget
        pickerTarget = -1

        if (uri != null && index in visitorForms.indices) {
            val form = visitorForms[index]
            val document = describeDocument(context, uri)

            if (document == null) {
                form.documentError = "Choose a JPG, PNG, or PDF file up to 5 MB."
            } else {
                try {
                    context.contentResolver.takePersistableUriPermission(
                        uri,
                        Intent.FLAG_GRANT_READ_URI_PERMISSION
                    )
                } catch (_: SecurityException) {
                    // Not every provider supports persistable grants; the
                    // bytes are read while this screen is alive either way.
                }

                form.document = document
                form.documentError = null
                // A newly selected file replaces the account's saved copy for
                // this booking and becomes the new saved copy after upload.
                form.usingSavedDocument = false
                form.savedDocumentStatus = null
            }
        }
    }

    LaunchedEffect(peopleCount) {
        while (visitorForms.size < peopleCount) {
            visitorForms.add(VisitorFormState())
        }
        while (visitorForms.size > peopleCount) {
            visitorForms.removeAt(visitorForms.lastIndex)
        }
    }

    LaunchedEffect(uiState.bookerName) {
        if (visitorForms.isNotEmpty() && visitorForms[0].name.isBlank()) {
            visitorForms[0].name = uiState.bookerName
        }
    }

    LaunchedEffect(uiState.savedIdentityLoaded) {
        if (!uiState.savedIdentityLoaded || savedPrefillApplied) return@LaunchedEffect
        savedPrefillApplied = true

        val saved = uiState.savedIdentity ?: return@LaunchedEffect
        if (visitorForms.isEmpty()) return@LaunchedEffect

        val booker = visitorForms[0]

        // Never overwrite a form the traveller has already started filling.
        if (booker.identityNumber.isNotBlank()) return@LaunchedEffect

        val prefill = saved.toBookingPrefill()
        booker.name = prefill.name
        booker.identityType = prefill.identityType
        booker.identityTypeOther = prefill.identityTypeOther
        booker.identityNumber = prefill.identityNumber
        booker.dateOfBirth = prefill.dateOfBirth
        booker.fromSavedIdentity = true
        booker.usingSavedDocument = prefill.useSavedDocument
        booker.savedDocumentStatus = prefill.documentStatus
    }

    LaunchedEffect(uiState.bookingSuccess, uiState.createdBooking) {
        val booking = uiState.createdBooking
        if (uiState.bookingSuccess && booking != null) {
            viewModel.resetState()
            onContinueToPayment(booking.id)
        }
    }

    fun validationMessage(): String? {
        if (selectedDate.isBlank()) {
            return "Choose your travel date first."
        }

        visitorForms.forEachIndexed { index, form ->
            val label = if (index == 0) "Booker" else "Traveller ${index + 1}"

            if (form.name.trim().length < 2) {
                return "$label: enter the full name as shown on the identity document."
            }
            if (form.identityNumber.trim().length < 4) {
                return "$label: enter a valid identity number."
            }
            if (form.identityType == VisitorIdentityType.OTHER && form.identityTypeOther.isBlank()) {
                return "$label: name the official identity document."
            }
            if (form.identityType == VisitorIdentityType.KIA && form.dateOfBirth.isNullOrBlank()) {
                return "$label: a birth date is required for a KIA."
            }
            if (form.document == null && !form.usingSavedDocument) {
                return "$label: upload an identity document photo or PDF."
            }
        }

        if (!privacyConsent) {
            return "Please accept the privacy notice before continuing."
        }

        return null
    }

    Scaffold(containerColor = VanillaCream) { innerPadding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(innerPadding)
                .imePadding()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = TraveloSpacing.screen, vertical = TraveloSpacing.xs)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(TraveloSpacing.xxs)
            ) {
                TraveloIconButton(
                    icon = Icons.AutoMirrored.Filled.ArrowBack,
                    contentDescription = "Back",
                    onClick = onBackClick
                )
                Text(
                    text = "Book tour",
                    style = MaterialTheme.typography.titleLarge,
                    color = PlumInk
                )
            }

            Spacer(Modifier.height(TraveloSpacing.sm))

            TraveloCard {
                Text(
                    text = tourPackageTitle,
                    style = MaterialTheme.typography.titleMedium,
                    color = PlumInk
                )
                Spacer(Modifier.height(TraveloSpacing.xxs))
                TraveloPriceText(
                    amount = tourPackagePrice.toDouble(),
                    compact = true,
                    suffix = "/person"
                )
            }

            Spacer(Modifier.height(TraveloSpacing.lg))

            Text(
                text = "Travel date",
                style = MaterialTheme.typography.titleMedium,
                color = PlumInk
            )
            Spacer(Modifier.height(TraveloSpacing.xs))

            Surface(
                onClick = { datePickerDialog.show() },
                modifier = Modifier.fillMaxWidth(),
                shape = FieldShape,
                color = SurfaceWhite,
                border = BorderStroke(1.dp, PlumBorder)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .heightIn(min = 56.dp)
                        .padding(horizontal = TraveloSpacing.md),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        imageVector = Icons.Default.CalendarMonth,
                        contentDescription = null,
                        tint = Rosewood,
                        modifier = Modifier.size(20.dp)
                    )
                    Spacer(Modifier.width(TraveloSpacing.sm))
                    Text(
                        text = if (selectedDate.isBlank()) {
                            "Choose your travel date"
                        } else {
                            formatDisplayDate(selectedDate)
                        },
                        style = MaterialTheme.typography.bodyLarge,
                        color = if (selectedDate.isBlank()) PlumMuted else PlumInk,
                        modifier = Modifier.weight(1f)
                    )
                    Icon(
                        imageVector = Icons.Default.DateRange,
                        contentDescription = null,
                        tint = PlumMuted,
                        modifier = Modifier.size(20.dp)
                    )
                }
            }

            Spacer(Modifier.height(TraveloSpacing.lg))

            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Travelers",
                    style = MaterialTheme.typography.titleMedium,
                    color = PlumInk,
                    modifier = Modifier.weight(1f)
                )
                Text(
                    text = "Up to $maxPeople",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }
            Spacer(Modifier.height(TraveloSpacing.xs))

            Surface(
                shape = CardShape,
                color = SurfaceWhite,
                border = BorderStroke(1.dp, PlumBorder)
            ) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = TraveloSpacing.xs, vertical = TraveloSpacing.xxs),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        text = if (peopleCount == 1) "1 traveler" else "$peopleCount travelers",
                        style = MaterialTheme.typography.bodyMedium,
                        color = PlumInk,
                        modifier = Modifier
                            .weight(1f)
                            .padding(start = TraveloSpacing.xs)
                    )
                    IconButton(
                        onClick = { if (peopleCount > 1) peopleCount-- },
                        enabled = peopleCount > 1
                    ) {
                        Icon(
                            imageVector = Icons.Default.Remove,
                            contentDescription = "Remove one traveler",
                            tint = if (peopleCount > 1) Rosewood else PlumMuted.copy(alpha = 0.4f)
                        )
                    }
                    Text(
                        text = "$peopleCount",
                        style = MaterialTheme.typography.titleMedium,
                        color = PlumInk,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.width(32.dp)
                    )
                    IconButton(
                        onClick = { if (peopleCount < maxPeople) peopleCount++ },
                        enabled = peopleCount < maxPeople
                    ) {
                        Icon(
                            imageVector = Icons.Default.Add,
                            contentDescription = "Add one traveler",
                            tint = if (peopleCount < maxPeople) Rosewood else PlumMuted.copy(alpha = 0.4f)
                        )
                    }
                }
            }

            Spacer(Modifier.height(TraveloSpacing.lg))

            Text(
                text = "Visitor identity",
                style = MaterialTheme.typography.titleMedium,
                color = PlumInk
            )
            Spacer(Modifier.height(2.dp))
            Text(
                text = "Required for every traveller. Use a KTP, KIA, Kartu Pelajar, or another official identity document.",
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )

            Spacer(Modifier.height(TraveloSpacing.sm))

            visitorForms.forEachIndexed { index, form ->
                VisitorIdentityCard(
                    index = index,
                    form = form,
                    isBooker = index == 0,
                    onPickDocument = {
                        pickerTarget = index
                        documentPicker.launch(arrayOf("image/*", "application/pdf"))
                    }
                )

                if (index != visitorForms.lastIndex) {
                    Spacer(Modifier.height(TraveloSpacing.sm))
                }
            }

            Spacer(Modifier.height(TraveloSpacing.md))

            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.Top
            ) {
                Checkbox(
                    checked = privacyConsent,
                    onCheckedChange = { privacyConsent = it }
                )
                Text(
                    text = "I agree that my identity data and document are used to verify this booking, stored privately, and saved to prefill my future bookings. I can replace it by uploading a new document. Staff review it manually; uploading does not make it verified automatically.",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted,
                    modifier = Modifier
                        .weight(1f)
                        .padding(top = 12.dp)
                )
            }

            Spacer(Modifier.height(TraveloSpacing.lg))

            TraveloTextField(
                value = specialRequests,
                onValueChange = { specialRequests = it },
                label = "Special requests (optional)",
                placeholder = "Meal, room, or accessibility notes",
                singleLine = false
            )

            Spacer(Modifier.height(TraveloSpacing.lg))

            Surface(shape = CardShape, color = BlushMist) {
                Column(modifier = Modifier.padding(TraveloSpacing.md)) {
                    Text(
                        text = "Price summary",
                        style = MaterialTheme.typography.titleMedium,
                        color = PlumInk
                    )
                    Spacer(Modifier.height(TraveloSpacing.sm))

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = "${formatRupiah(tourPackagePrice)} × $peopleCount",
                            style = MaterialTheme.typography.bodyMedium,
                            color = PlumMuted
                        )
                        Text(
                            text = formatRupiah(totalPrice.toDouble()),
                            style = MaterialTheme.typography.bodyMedium,
                            color = PlumInk
                        )
                    }

                    Spacer(
                        Modifier
                            .padding(vertical = TraveloSpacing.sm)
                            .fillMaxWidth()
                            .height(1.dp)
                            .background(PlumBorder)
                    )

                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.SpaceBetween
                    ) {
                        Text(
                            text = "Total",
                            style = MaterialTheme.typography.titleMedium,
                            color = PlumInk
                        )
                        TraveloPriceText(
                            amount = totalPrice.toDouble(),
                            emphasized = true
                        )
                    }
                }
            }

            val shownError = localError ?: uiState.error
            if (shownError != null) {
                Spacer(Modifier.height(TraveloSpacing.md))
                Surface(
                    shape = CardShape,
                    color = DangerTint,
                    border = BorderStroke(1.dp, DangerDeep.copy(alpha = 0.15f))
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(TraveloSpacing.sm),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Default.Error,
                            contentDescription = null,
                            tint = DangerDeep,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(Modifier.width(TraveloSpacing.xs))
                        Text(
                            text = shownError,
                            style = MaterialTheme.typography.bodySmall,
                            color = DangerDeep
                        )
                    }
                }
            }

            Spacer(Modifier.height(TraveloSpacing.md))

            TraveloButton(
                text = if (uiState.awaitingDocumentUploads) {
                    "Retry document upload"
                } else {
                    "Continue to payment"
                },
                onClick = {
                    val problem = validationMessage()

                    if (problem != null) {
                        localError = problem
                    } else {
                        localError = null
                        preparingDocuments = true

                        scope.launch {
                            val documents = withContext(Dispatchers.IO) {
                                visitorForms.map { form ->
                                    val selected = form.document
                                    if (form.usingSavedDocument || selected == null) {
                                        null
                                    } else {
                                        val bytes = readDocumentBytes(context, selected)
                                        if (bytes == null) {
                                            null
                                        } else {
                                            VisitorDocumentInput(
                                                fileName = selected.fileName,
                                                mimeType = selected.mimeType,
                                                bytes = bytes
                                            )
                                        }
                                    }
                                }
                            }

                            preparingDocuments = false

                            val missingDocument = visitorForms.indices.any { index ->
                                !visitorForms[index].usingSavedDocument && documents[index] == null
                            }

                            if (missingDocument) {
                                localError = "One of the selected documents could not be read. Please choose it again."
                                return@launch
                            }

                            viewModel.createBooking(
                                tourPackageId = tourPackageId,
                                bookingDate = selectedDate,
                                peopleCount = peopleCount,
                                specialRequests = specialRequests,
                                visitors = visitorForms.map { form ->
                                    VisitorInput(
                                        name = form.name,
                                        identityType = form.identityType.value,
                                        identityTypeOther = form.identityTypeOther,
                                        identityNumber = form.identityNumber,
                                        dateOfBirth = form.dateOfBirth
                                    )
                                },
                                documents = documents,
                                reuseSavedIdentityDocument = visitorForms.firstOrNull()
                                    ?.usingSavedDocument == true
                            )
                        }
                    }
                },
                loading = uiState.isLoading || preparingDocuments,
                enabled = selectedDate.isNotEmpty() && peopleCount in 1..maxPeople
            )

            Spacer(Modifier.height(TraveloSpacing.sm))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = Icons.Default.Lock,
                    contentDescription = null,
                    tint = PlumMuted,
                    modifier = Modifier.size(14.dp)
                )
                Spacer(Modifier.width(6.dp))
                Text(
                    text = "Payment is processed securely by Midtrans.",
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }

            Spacer(Modifier.height(TraveloSpacing.lg))
        }
    }
}

enum class VisitorIdentityType(val value: String, val label: String) {
    KTP("ktp", "KTP"),
    KIA("kia", "KIA (Kartu Identitas Anak)"),
    STUDENT_CARD("student_card", "Kartu Pelajar"),
    OTHER("other", "Identitas resmi lainnya");

    companion object {
        fun fromValue(value: String?): VisitorIdentityType =
            entries.firstOrNull { it.value == value } ?: KTP
    }
}

class VisitorFormState(name: String = "") {
    var name by mutableStateOf(name)
    var identityType by mutableStateOf(VisitorIdentityType.KTP)
    var identityTypeOther by mutableStateOf("")
    var identityNumber by mutableStateOf("")
    var dateOfBirth by mutableStateOf<String?>(null)
    var document by mutableStateOf<SelectedDocument?>(null)
    var documentError by mutableStateOf<String?>(null)
    // True when this row came from the account-level saved identity. The
    // traveller can still edit every field.
    var fromSavedIdentity by mutableStateOf(false)
    // The backend will copy the account's saved private document into this
    // booking, so no new upload is needed.
    var usingSavedDocument by mutableStateOf(false)
    var savedDocumentStatus by mutableStateOf<String?>(null)
}

/**
 * Marks a saved document as no longer reusable after the identity data it
 * belongs to has been edited.
 */
private fun VisitorFormState.invalidateSavedDocument() {
    if (usingSavedDocument) {
        usingSavedDocument = false
        savedDocumentStatus = null
    }
}

@Composable
private fun VisitorIdentityCard(
    index: Int,
    form: VisitorFormState,
    isBooker: Boolean,
    onPickDocument: () -> Unit
) {
    val context = LocalContext.current

    val birthDateDialog = remember(form) {
        val calendar = Calendar.getInstance()
        DatePickerDialog(
            context,
            { _, year, month, dayOfMonth ->
                form.dateOfBirth = SimpleDateFormat("yyyy-MM-dd", Locale.US)
                    .format(Calendar.getInstance().apply { set(year, month, dayOfMonth) }.time)
                form.invalidateSavedDocument()
            },
            calendar.get(Calendar.YEAR),
            calendar.get(Calendar.MONTH),
            calendar.get(Calendar.DAY_OF_MONTH)
        ).apply {
            datePicker.maxDate = System.currentTimeMillis()
        }
    }

    Surface(
        shape = CardShape,
        color = SurfaceWhite,
        border = BorderStroke(1.dp, PlumBorder)
    ) {
        Column(modifier = Modifier.padding(TraveloSpacing.sm)) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = "Traveller ${index + 1}",
                    style = MaterialTheme.typography.titleSmall,
                    color = PlumInk,
                    modifier = Modifier.weight(1f)
                )
                if (isBooker) {
                    Surface(shape = FieldShape, color = BlushMist) {
                        Text(
                            text = "Booker",
                            style = MaterialTheme.typography.labelSmall,
                            color = Rosewood,
                            modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                        )
                    }
                }
            }

            if (isBooker && form.fromSavedIdentity) {
                Spacer(Modifier.height(4.dp))
                Text(
                    text = "Filled from your saved identity. Edit any field if something changed.",
                    style = MaterialTheme.typography.bodySmall,
                    color = Rosewood
                )
            }

            Spacer(Modifier.height(TraveloSpacing.xs))

            TraveloTextField(
                value = form.name,
                onValueChange = { form.name = it },
                label = "Full name",
                placeholder = "As written on the identity document"
            )

            Spacer(Modifier.height(TraveloSpacing.xs))

            IdentityTypeSelector(
                selected = form.identityType,
                onSelect = { selected ->
                    if (selected != form.identityType) {
                        form.identityType = selected
                        form.invalidateSavedDocument()
                    }
                }
            )

            if (form.identityType == VisitorIdentityType.OTHER) {
                Spacer(Modifier.height(TraveloSpacing.xs))
                TraveloTextField(
                    value = form.identityTypeOther,
                    onValueChange = {
                        if (it != form.identityTypeOther) {
                            form.identityTypeOther = it
                            form.invalidateSavedDocument()
                        }
                    },
                    label = "Identity document name",
                    placeholder = "e.g. Passport, SIM, Kartu Keluarga"
                )
            }

            Spacer(Modifier.height(TraveloSpacing.xs))

            TraveloTextField(
                value = form.identityNumber,
                onValueChange = {
                    if (it != form.identityNumber) {
                        form.identityNumber = it
                        // A saved document belongs to the identity number it
                        // was uploaded for. Editing the number requires a new
                        // file (or a fresh reuse after it is saved again).
                        form.invalidateSavedDocument()
                    }
                },
                label = if (form.identityType == VisitorIdentityType.OTHER) {
                    "Identity number"
                } else {
                    "${form.identityType.label} number"
                },
                placeholder = "Numbers and letters only",
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Text)
            )

            if (form.identityType == VisitorIdentityType.KIA ||
                form.identityType == VisitorIdentityType.STUDENT_CARD
            ) {
                Spacer(Modifier.height(TraveloSpacing.xs))

                Surface(
                    onClick = { birthDateDialog.show() },
                    modifier = Modifier.fillMaxWidth(),
                    shape = FieldShape,
                    color = SurfaceWhite,
                    border = BorderStroke(1.dp, PlumBorder)
                ) {
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .heightIn(min = 56.dp)
                            .padding(horizontal = TraveloSpacing.md),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Default.CalendarMonth,
                            contentDescription = null,
                            tint = Rosewood,
                            modifier = Modifier.size(20.dp)
                        )
                        Spacer(Modifier.width(TraveloSpacing.sm))
                        Text(
                            text = form.dateOfBirth?.let { formatDisplayDate(it) }
                                ?: "Date of birth${if (form.identityType == VisitorIdentityType.KIA) " (required)" else ""}",
                            style = MaterialTheme.typography.bodyMedium,
                            color = if (form.dateOfBirth == null) PlumMuted else PlumInk,
                            modifier = Modifier.weight(1f)
                        )
                    }
                }
            }

            Spacer(Modifier.height(TraveloSpacing.xs))

            Text(
                text = "Identity document",
                style = MaterialTheme.typography.labelLarge,
                color = PlumInk
            )
            Spacer(Modifier.height(4.dp))

            if (isBooker && form.savedDocumentStatus == "rejected" && !form.usingSavedDocument) {
                Text(
                    text = "Your saved document was rejected. Upload a new one to continue.",
                    style = MaterialTheme.typography.bodySmall,
                    color = DangerDeep
                )
                Spacer(Modifier.height(4.dp))
            }

            val document = form.document

            when {
                form.usingSavedDocument -> {
                    SavedIdentityDocumentCard(
                        form = form,
                        onPickDocument = onPickDocument
                    )
                }
                document == null -> {
                    Surface(
                        onClick = onPickDocument,
                        modifier = Modifier.fillMaxWidth(),
                        shape = FieldShape,
                        color = VanillaCream,
                        border = BorderStroke(1.dp, PlumBorder)
                    ) {
                        Row(
                            modifier = Modifier
                                .fillMaxWidth()
                                .heightIn(min = 64.dp)
                                .padding(horizontal = TraveloSpacing.md, vertical = TraveloSpacing.sm),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = Icons.Default.CloudUpload,
                                contentDescription = null,
                                tint = Rosewood,
                                modifier = Modifier.size(24.dp)
                            )
                            Spacer(Modifier.width(TraveloSpacing.sm))
                            Column {
                                Text(
                                    text = "Upload or take a photo",
                                    style = MaterialTheme.typography.bodyMedium,
                                    color = PlumInk
                                )
                                Text(
                                    text = "JPG, PNG, or PDF · max 5 MB",
                                    style = MaterialTheme.typography.bodySmall,
                                    color = PlumMuted
                                )
                            }
                        }
                    }
                }
                else -> {
                    Column {
                        if (document.mimeType.startsWith("image/")) {
                            AsyncImage(
                                model = document.uri,
                                contentDescription = "Preview of the selected identity document",
                                contentScale = ContentScale.Crop,
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(140.dp)
                            )
                            Spacer(Modifier.height(4.dp))
                        }

                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                imageVector = Icons.Default.Description,
                                contentDescription = null,
                                tint = Rosewood,
                                modifier = Modifier.size(20.dp)
                            )
                            Spacer(Modifier.width(TraveloSpacing.xs))
                            Text(
                                text = document.fileName,
                                style = MaterialTheme.typography.bodySmall,
                                color = PlumInk,
                                modifier = Modifier.weight(1f)
                            )
                            IconButton(onClick = onPickDocument) {
                                Icon(
                                    imageVector = Icons.Default.Edit,
                                    contentDescription = "Replace document",
                                    tint = Rosewood
                                )
                            }
                            IconButton(onClick = { form.document = null }) {
                                Icon(
                                    imageVector = Icons.Default.Close,
                                    contentDescription = "Remove document",
                                    tint = PlumMuted
                                )
                            }
                        }
                    }
                }
            }

            if (form.documentError != null) {
                Spacer(Modifier.height(2.dp))
                Text(
                    text = form.documentError.orEmpty(),
                    style = MaterialTheme.typography.bodySmall,
                    color = DangerDeep
                )
            }

            Spacer(Modifier.height(4.dp))
            Text(
                text = if (form.usingSavedDocument) {
                    "Your saved document is stored privately and reused for this booking. Choose another file to replace it, or stop using it to upload a new file."
                } else {
                    "Stored privately. A successful upload is marked \"waiting for review\", not verified. Make sure the whole card is visible and the text is readable."
                },
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )
        }
    }
}

@Composable
private fun SavedIdentityDocumentCard(
    form: VisitorFormState,
    onPickDocument: () -> Unit
) {
    Surface(
        modifier = Modifier.fillMaxWidth(),
        shape = FieldShape,
        color = BlushMist,
        border = BorderStroke(1.dp, PlumBorder)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = TraveloSpacing.md, vertical = TraveloSpacing.sm),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = Icons.Default.Description,
                contentDescription = null,
                tint = Rosewood,
                modifier = Modifier.size(24.dp)
            )
            Spacer(Modifier.width(TraveloSpacing.sm))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = "Saved identity document",
                    style = MaterialTheme.typography.bodyMedium,
                    color = PlumInk
                )
                Text(
                    text = when (form.savedDocumentStatus) {
                        "verified" -> "Reviewed by staff"
                        "pending" -> "Waiting for review"
                        else -> "Saved from your profile"
                    },
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            }
            IconButton(onClick = onPickDocument) {
                Icon(
                    imageVector = Icons.Default.Edit,
                    contentDescription = "Use another identity document",
                    tint = Rosewood
                )
            }
            IconButton(
                onClick = {
                    form.usingSavedDocument = false
                    form.savedDocumentStatus = null
                }
            ) {
                Icon(
                    imageVector = Icons.Default.Close,
                    contentDescription = "Stop using saved identity document",
                    tint = PlumMuted
                )
            }
        }
    }
}

@Composable
private fun IdentityTypeSelector(
    selected: VisitorIdentityType,
    onSelect: (VisitorIdentityType) -> Unit
) {
    var expanded by remember { mutableStateOf(false) }

    Box {
        Surface(
            onClick = { expanded = true },
            modifier = Modifier.fillMaxWidth(),
            shape = FieldShape,
            color = SurfaceWhite,
            border = BorderStroke(1.dp, PlumBorder)
        ) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .heightIn(min = 56.dp)
                    .padding(horizontal = TraveloSpacing.md),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = selected.label,
                    style = MaterialTheme.typography.bodyMedium,
                    color = PlumInk,
                    modifier = Modifier.weight(1f)
                )
                Icon(
                    imageVector = Icons.Default.ArrowDropDown,
                    contentDescription = "Choose identity type",
                    tint = PlumMuted
                )
            }
        }

        DropdownMenu(
            expanded = expanded,
            onDismissRequest = { expanded = false }
        ) {
            VisitorIdentityType.values().forEach { option ->
                DropdownMenuItem(
                    text = { Text(option.label) },
                    onClick = {
                        onSelect(option)
                        expanded = false
                    }
                )
            }
        }
    }
}
