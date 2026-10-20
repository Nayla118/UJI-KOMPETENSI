package com.naylaaisyah.traveloapp.ui.screens.auth

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.focus.onFocusChanged
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.naylaaisyah.traveloapp.ui.theme.BlushMist
import com.naylaaisyah.traveloapp.ui.theme.PlumBorder
import com.naylaaisyah.traveloapp.ui.theme.PlumInk
import com.naylaaisyah.traveloapp.ui.theme.PlumMuted
import com.naylaaisyah.traveloapp.ui.theme.Rosewood
import com.naylaaisyah.traveloapp.ui.theme.SurfaceWhite
import com.naylaaisyah.traveloapp.ui.theme.TraveloSpacing
import kotlinx.coroutines.delay

private const val OTP_LENGTH = 6

/**
 * Step 2 of the registration flow: enter the 6-digit code that was emailed.
 *
 * The backend only issues the session token after this code is verified, so
 * this screen is the actual gate — it is rendered by the register screen while
 * `AuthState.otpPending` is true.
 */
@Composable
fun OtpVerification(
    maskedEmail: String,
    resendAfter: Int,
    isVerifying: Boolean,
    isResending: Boolean,
    error: String?,
    onVerify: (String) -> Unit,
    onResend: () -> Unit,
    onBack: () -> Unit,
    onDismissError: () -> Unit
) {
    var digits by remember { mutableStateOf(List(OTP_LENGTH) { "" }) }
    val focusRequesters = remember { List(OTP_LENGTH) { FocusRequester() } }
    val code = digits.joinToString("")

    // Resend cooldown countdown (restarts whenever a code is requested).
    var resendTick by remember { mutableStateOf(0) }
    var countdown by remember(resendTick) { mutableStateOf(resendAfter) }
    LaunchedEffect(resendTick) {
        while (countdown > 0) {
            delay(1000)
            countdown -= 1
        }
    }

    // Put the caret in the first box as soon as the screen appears.
    LaunchedEffect(Unit) {
        delay(300)
        focusRequesters.firstOrNull()?.requestFocus()
    }

    // Auto-submit as soon as the 6th digit lands.
    LaunchedEffect(code) {
        if (code.length == OTP_LENGTH && !isVerifying && !isResending) {
            onVerify(code)
        }
    }

    fun focus(index: Int) {
        focusRequesters[index.coerceIn(0, OTP_LENGTH - 1)].requestFocus()
    }

    fun submit() {
        if (code.length == OTP_LENGTH && !isVerifying) {
            onVerify(code)
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .verticalScroll(rememberScrollState())
            .imePadding()
    ) {
        AuthBrandBar(onBackClick = onBack)

        Spacer(modifier = Modifier.height(8.dp))

        AuthHeader(
            eyebrow = "Verification",
            title = "Check your email",
            subtitle = "Enter the six-digit code we sent to $maskedEmail."
        )

        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = TraveloSpacing.screen)
        ) {
            Spacer(modifier = Modifier.height(24.dp))

            AuthErrorBanner(error = error, onDismiss = onDismissError)
            if (error != null) Spacer(modifier = Modifier.height(16.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                repeat(OTP_LENGTH) { index ->
                    OtpBox(
                        digit = digits[index],
                        onValueChange = { input ->
                            val raw = input.filter { it.isDigit() }
                            val current = digits[index]
                            val list = digits.toMutableList()

                            when {
                                raw.isEmpty() -> {
                                    // Backspace / clear.
                                    list[index] = ""
                                    digits = list
                                    if (index > 0) focus(index - 1)
                                }

                                raw.length > 1 &&
                                    current.isNotEmpty() &&
                                    raw.startsWith(current) -> {
                                    // Typed onto an existing digit: keep only the new tail.
                                    var target = index
                                    for (ch in raw.substring(current.length)) {
                                        if (target < OTP_LENGTH) {
                                            list[target] = ch.toString()
                                            target++
                                        }
                                    }
                                    digits = list
                                    focus(target - 1)
                                }

                                raw.length > 1 -> {
                                    // A whole code was pasted.
                                    var target = index
                                    for (ch in raw) {
                                        if (target < OTP_LENGTH) {
                                            list[target] = ch.toString()
                                            target++
                                        }
                                    }
                                    digits = list
                                    focus(target - 1)
                                }

                                else -> {
                                    // A single digit typed into this box.
                                    list[index] = raw
                                    digits = list
                                    if (index < OTP_LENGTH - 1) focus(index + 1)
                                }
                            }
                        },
                        focusRequester = focusRequesters[index],
                        imeAction = if (index == OTP_LENGTH - 1) {
                            ImeAction.Done
                        } else {
                            ImeAction.Next
                        },
                        onImeAction = {
                            if (index == OTP_LENGTH - 1) submit() else focus(index + 1)
                        },
                        modifier = Modifier.weight(1f)
                    )
                }
            }

            Spacer(modifier = Modifier.height(12.dp))

            Text(
                text = "The code is valid for 5 minutes and can be used once.",
                style = MaterialTheme.typography.bodySmall,
                color = PlumMuted
            )

            Spacer(modifier = Modifier.height(24.dp))

            AuthPrimaryButton(
                text = "Verify and continue",
                onClick = { submit() },
                enabled = code.length == OTP_LENGTH,
                isLoading = isVerifying
            )

            Spacer(modifier = Modifier.height(12.dp))

            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.Center,
                verticalAlignment = Alignment.CenterVertically
            ) {
                if (countdown > 0) {
                    Text(
                        text = "Resend code in ${formatCountdown(countdown)}",
                        style = MaterialTheme.typography.labelMedium,
                        color = PlumMuted
                    )
                } else {
                    TextButton(
                        onClick = {
                            resendTick += 1
                            onResend()
                        },
                        enabled = !isResending
                    ) {
                        if (isResending) {
                            CircularProgressIndicator(
                                modifier = Modifier.height(16.dp),
                                color = Rosewood,
                                strokeWidth = 2.dp
                            )
                        } else {
                            Text(
                                text = "Resend code",
                                style = MaterialTheme.typography.labelLarge,
                                color = Rosewood
                            )
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            AuthFooterLink(
                prompt = "Wrong email?",
                actionLabel = "Use another account",
                onClick = onBack
            )

            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}

/** Single digit input rendered as a focused/unfocused code box. */
@Composable
private fun OtpBox(
    digit: String,
    onValueChange: (String) -> Unit,
    focusRequester: FocusRequester,
    imeAction: ImeAction,
    onImeAction: () -> Unit,
    modifier: Modifier = Modifier
) {
    var focused by remember { mutableStateOf(false) }

    val borderColor = when {
        focused -> Rosewood
        digit.isNotEmpty() -> Rosewood.copy(alpha = 0.45f)
        else -> PlumBorder
    }
    val containerColor = if (focused) BlushMist else SurfaceWhite

    Box(
        modifier = modifier
            .height(56.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(containerColor)
            .border(1.dp, borderColor, RoundedCornerShape(12.dp)),
        contentAlignment = Alignment.Center
    ) {
        BasicTextField(
            value = digit,
            onValueChange = onValueChange,
            modifier = Modifier
                .fillMaxWidth()
                .focusRequester(focusRequester)
                .onFocusChanged { focused = it.isFocused },
            textStyle = TextStyle(
                color = PlumInk,
                fontSize = 22.sp,
                fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center
            ),
            keyboardOptions = KeyboardOptions(
                keyboardType = KeyboardType.NumberPassword,
                imeAction = imeAction
            ),
            keyboardActions = KeyboardActions(
                onNext = { onImeAction() },
                onDone = { onImeAction() }
            ),
            singleLine = true,
            maxLines = 1,
            cursorBrush = SolidColor(Rosewood)
        )
    }
}

private fun formatCountdown(totalSeconds: Int): String {
    val minutes = totalSeconds / 60
    val seconds = totalSeconds % 60
    return String.format(java.util.Locale.US, "%d:%02d", minutes, seconds)
}
