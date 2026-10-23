package com.naylaaisyah.traveloapp.ui.screens.my_bookings

import android.content.Intent
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Close
import androidx.compose.material.icons.filled.CloudUpload
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Error
import androidx.compose.material.icons.filled.HourglassEmpty
import androidx.compose.material.icons.filled.Lock
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
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import coil.compose.AsyncImage
import com.naylaaisyah.traveloapp.R
import com.naylaaisyah.traveloapp.data.models.Visitor
import com.naylaaisyah.traveloapp.data.models.VisitorDocumentInput
import com.naylaaisyah.traveloapp.ui.components.TraveloButton
import com.naylaaisyah.traveloapp.ui.components.TraveloButtonVariant
import com.naylaaisyah.traveloapp.ui.components.TraveloCard
import com.naylaaisyah.traveloapp.ui.components.TraveloErrorState
import com.naylaaisyah.traveloapp.ui.components.TraveloLoadingState
import com.naylaaisyah.traveloapp.ui.components.TraveloTopBar
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.CardShape
import com.naylaaisyah.traveloapp.ui.theme.DangerDeep
import com.naylaaisyah.traveloapp.ui.theme.DangerTint
import com.naylaaisyah.traveloapp.ui.theme.FieldShape
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SageDeep
import com.naylaaisyah.traveloapp.ui.theme.SageTint
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import com.naylaaisyah.traveloapp.ui.theme.VanillaCream
import com.naylaaisyah.traveloapp.ui.theme.WarningDeep
import com.naylaaisyah.traveloapp.ui.theme.WarningTint
import com.naylaaisyah.traveloapp.util.SelectedDocument
import com.naylaaisyah.traveloapp.util.describeDocument
import com.naylaaisyah.traveloapp.util.readDocumentBytes
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/**
 * Recovery screen for a booking whose identity documents were not completed.
 *
 * It is opened with the existing booking id, reloads visitor rows from the
 * backend and offers a picker only for visitors that still have no document.
 * The app never persists a picker URI, so after a restart the user simply
 * selects the file again instead of relying on a possibly revoked permission.
 */
@Composable
fun CompleteDocumentsScreen(
    onBackClick: () -> Unit,
    viewModel: CompleteDocumentsViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    val selected = remember { mutableStateMapOf<Int, SelectedDocument>() }
    var pickerTarget by remember { mutableIntStateOf(-1) }
    var localError by remember { mutableStateOf<String?>(null) }
    var preparing by remember { mutableStateOf(false) }

    val picker = rememberLauncherForActivityResult(
        contract = ActivityResultContracts.OpenDocument()
    ) { uri ->
        val visitorId = pickerTarget
        pickerTarget = -1

        if (uri != null && visitorId > 0) {
            val document = describeDocument(context, uri)

            if (document == null) {
                localError = "Choose a JPG, PNG, or PDF file up to 5 MB."
            } else {
                try {
                    context.contentResolver.takePersistableUriPermission(
                        uri,
                        Intent.FLAG_GRANT_READ_URI_PERMISSION
                    )
                } catch (_: SecurityException) {
                    // The bytes are read by this screen at upload time; some
                    // providers do not support persistable grants.
                }

                selected[visitorId] = document
                localError = null
            }
        }
    }

    LaunchedEffect(uiState.visitors) {
        val currentIds = uiState.visitors.map { it.id }.toSet()
        selected.keys.toList()
            .filter { it !in currentIds || uiState.visitors.firstOrNull { v -> v.id == it }?.hasDocument == true }
            .forEach { selected.remove(it) }
    }

    val missing = uiState.visitors.filter { !it.hasDocument }
    val allSelected = missing.isNotEmpty() && missing.all { selected[it.id] != null }
    val shownError = localError ?: uiState.error

    Scaffold(
        topBar = {
            TraveloTopBar(
                title = stringResource(R.string.documents_title),
                onBackClick = onBackClick
            )
        },
        containerColor = VanillaCream
    ) { paddingValues ->
        when {
            uiState.isLoading && uiState.visitors.isEmpty() -> {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues)
                        .padding(horizontal = TraveloSpacing.screen)
                ) {
                    TraveloLoadingState()
                }
            }

            shownError != null && uiState.visitors.isEmpty() -> {
                TraveloErrorState(
                    message = shownError,
                    onRetry = {
                        localError = null
                        viewModel.clearError()
                        viewModel.load()
                    },
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues),
                    title = stringResource(R.string.documents_load_failed),
                    horizontalPadding = TraveloSpacing.screen
                )
            }

            else -> {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(paddingValues)
                        .verticalScroll(rememberScrollState())
                        .padding(horizontal = TraveloSpacing.screen, vertical = TraveloSpacing.sm)
                ) {
                    if (uiState.completed) {
                        CompleteState(
                            onBackClick = onBackClick
                        )
                        return@Column
                    }

                    Text(
                        text = stringResource(R.string.documents_intro),
                        style = MaterialTheme.typography.bodyMedium,
                        color = PlumMuted
                    )

                    Spacer(Modifier.height(TraveloSpacing.md))

                    uiState.visitors.forEach { visitor ->
                        VisitorDocumentCard(
                            visitor = visitor,
                            selection = selected[visitor.id],
                            enabled = !uiState.uploading && !preparing,
                            onPick = {
                                pickerTarget = visitor.id
                                picker.launch(arrayOf("image/*", "application/pdf"))
                            },
                            onRemove = { selected.remove(visitor.id) }
                        )
                        Spacer(Modifier.height(TraveloSpacing.sm))
                    }

                    if (shownError != null) {
                        Spacer(Modifier.height(TraveloSpacing.xs))
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
                        text = if (uiState.uploading) {
                            stringResource(R.string.documents_uploading)
                        } else {
                            stringResource(R.string.documents_upload)
                        },
                        onClick = {
                            if (!allSelected) {
                                localError = "Choose a document for every traveller who still needs one."
                                return@TraveloButton
                            }

                            localError = null
                            preparing = true

                            scope.launch {
                                val documents = withContext(Dispatchers.IO) {
                                    missing.mapNotNull { visitor ->
                                        val selection = selected[visitor.id] ?: return@mapNotNull null
                                        val bytes = readDocumentBytes(context, selection)
                                            ?: return@mapNotNull null
                                        visitor.id to VisitorDocumentInput(
                                            fileName = selection.fileName,
                                            mimeType = selection.mimeType,
                                            bytes = bytes
                                        )
                                    }
                                }

                                val unreadable = missing.filter { visitor ->
                                    documents.none { it.first == visitor.id }
                                }

                                preparing = false

                                if (unreadable.isNotEmpty()) {
                                    // A stale URI is recoverable: clear it and
                                    // ask for a fresh selection.
                                    unreadable.forEach { selected.remove(it.id) }
                                    localError = context.getString(R.string.documents_file_unreadable)
                                    return@launch
                                }

                                viewModel.uploadMissing(documents.toMap())
                            }
                        },
                        loading = uiState.uploading || preparing,
                        enabled = allSelected && uiState.visitors.isNotEmpty()
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
                            text = stringResource(R.string.documents_private_note),
                            style = MaterialTheme.typography.bodySmall,
                            color = PlumMuted,
                            textAlign = TextAlign.Center
                        )
                    }

                    Spacer(Modifier.height(TraveloSpacing.lg))
                }
            }
        }
    }
}

@Composable
private fun CompleteState(onBackClick: () -> Unit) {
    TraveloCard {
        Icon(
            imageVector = Icons.Default.CheckCircle,
            contentDescription = null,
            tint = SageDeep,
            modifier = Modifier.size(40.dp)
        )
        Spacer(Modifier.height(TraveloSpacing.sm))
        Text(
            text = stringResource(R.string.documents_all_complete_title),
            style = MaterialTheme.typography.titleMedium,
            color = PlumInk
        )
        Spacer(Modifier.height(4.dp))
        Text(
            text = stringResource(R.string.documents_all_complete_message),
            style = MaterialTheme.typography.bodyMedium,
            color = PlumMuted
        )
        Spacer(Modifier.height(TraveloSpacing.md))
        TraveloButton(
            text = stringResource(R.string.documents_back),
            onClick = onBackClick,
            variant = TraveloButtonVariant.Secondary
        )
    }
}

@Composable
private fun VisitorDocumentCard(
    visitor: Visitor,
    selection: SelectedDocument?,
    enabled: Boolean,
    onPick: () -> Unit,
    onRemove: () -> Unit
) {
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
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        text = visitor.name,
                        style = MaterialTheme.typography.titleSmall,
                        color = PlumInk
                    )
                    visitor.identityTypeLabel?.takeIf { it.isNotBlank() }?.let { label ->
                        Text(
                            text = label,
                            style = MaterialTheme.typography.bodySmall,
                            color = PlumMuted
                        )
                    }
                }
                DocumentStatusChip(visitor = visitor)
            }

            Spacer(Modifier.height(TraveloSpacing.sm))

            if (visitor.hasDocument) {
                Text(
                    text = when (visitor.documentStatus) {
                        "verified" -> "This document has been reviewed by staff."
                        else -> "A document is on file and is waiting for manual review. No upload is needed."
                    },
                    style = MaterialTheme.typography.bodySmall,
                    color = PlumMuted
                )
            } else {
                val document = selection
                if (document == null) {
                    Surface(
                        onClick = onPick,
                        modifier = Modifier.fillMaxWidth(),
                        shape = FieldShape,
                        color = VanillaCream,
                        border = BorderStroke(1.dp, PlumBorder),
                        enabled = enabled
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
                                    text = stringResource(R.string.documents_pick),
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
                } else {
                    Column {
                        if (document.mimeType.startsWith("image/")) {
                            AsyncImage(
                                model = document.uri,
                                contentDescription = "Preview of the selected identity document",
                                contentScale = ContentScale.Crop,
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .height(130.dp)
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
                            IconButton(onClick = onPick, enabled = enabled) {
                                Icon(
                                    imageVector = Icons.Default.Edit,
                                    contentDescription = stringResource(R.string.documents_replace),
                                    tint = Rosewood
                                )
                            }
                            IconButton(onClick = onRemove, enabled = enabled) {
                                Icon(
                                    imageVector = Icons.Default.Close,
                                    contentDescription = stringResource(R.string.documents_remove),
                                    tint = PlumMuted
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun DocumentStatusChip(visitor: Visitor) {
    val status = visitor.documentStatus
    val label = when (status) {
        "verified" -> stringResource(R.string.documents_status_verified)
        "pending" -> stringResource(R.string.documents_status_pending)
        "rejected" -> stringResource(R.string.documents_status_rejected)
        "purged" -> stringResource(R.string.documents_status_purged)
        else -> stringResource(R.string.documents_status_missing)
    }
    val background = when (status) {
        "verified" -> SageTint
        "pending" -> WarningTint
        else -> DangerTint
    }
    val foreground = when (status) {
        "verified" -> SageDeep
        "pending" -> WarningDeep
        else -> DangerDeep
    }
    val icon = when (status) {
        "verified" -> Icons.Default.CheckCircle
        "pending" -> Icons.Default.HourglassEmpty
        else -> Icons.Default.Error
    }

    Surface(shape = FieldShape, color = background) {
        Row(
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(
                imageVector = icon,
                contentDescription = null,
                tint = foreground,
                modifier = Modifier.size(14.dp)
            )
            Spacer(Modifier.width(4.dp))
            Text(
                text = label,
                style = MaterialTheme.typography.labelSmall,
                color = foreground
            )
        }
    }
}
