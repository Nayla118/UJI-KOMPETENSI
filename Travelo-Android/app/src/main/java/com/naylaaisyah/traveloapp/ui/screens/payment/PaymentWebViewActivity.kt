package com.naylaaisyah.traveloapp.ui.screens.payment

import android.annotation.SuppressLint
import android.content.ActivityNotFoundException
import android.content.Intent
import android.content.res.ColorStateList
import android.graphics.Bitmap
import android.net.Uri
import android.net.http.SslError
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.os.Message
import android.view.Gravity
import android.view.View
import android.view.WindowManager
import android.webkit.SslErrorHandler
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Button
import android.widget.FrameLayout
import android.widget.ImageButton
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import androidx.activity.ComponentActivity
import androidx.activity.OnBackPressedCallback
import com.naylaaisyah.traveloapp.BuildConfig
import com.naylaaisyah.traveloapp.data.models.PaymentOutcome
import com.naylaaisyah.traveloapp.data.models.outcome
import com.naylaaisyah.traveloapp.data.repository.TraveloRepository
import com.naylaaisyah.traveloapp.util.Resource
import com.naylaaisyah.traveloapp.util.SessionManager
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch

/**
 * Activity that displays the official Midtrans Snap payment page in an
 * in-app WebView.
 *
 * Security / mechanism is 100% official Midtrans: we only load the Snap
 * vtweb URL built from the server-issued snap_token. No credentials, no
 * card data, and no payment logic live in the app.
 *
 * Improvements over the previous bare-WebView version:
 * - Modern header with back button, title and "secure via Midtrans" badge.
 * - Loading state (progress bar + first-load overlay).
 * - Error state with retry (network / HTTP / SSL failures).
 * - Longer 10-minute timeout so slow methods (VA, retail, QRIS) are not
 *   cut off; periodic URL polling still detects completion.
 * - Hardened WebView settings (no file access, no mixed content).
 *
 * Result codes are unchanged: callers MUST still verify with the backend
 * (verify-and-sync) regardless of the code returned here, because many
 * payment methods never redirect to a success URL.
 */
@SuppressLint("SetJavaScriptEnabled", "SetTextI18n")
@AndroidEntryPoint
class PaymentWebViewActivity : ComponentActivity() {

    companion object {
        const val EXTRA_SNAP_TOKEN = "snap_token"
        const val EXTRA_BOOKING_ID = "booking_id"
        const val EXTRA_IS_SANDBOX = "is_sandbox"
        const val RESULT_PAYMENT_SUCCESS = 1001
        const val RESULT_PAYMENT_PENDING = 1002
        const val RESULT_PAYMENT_CANCELLED = 1003

        private const val TIMEOUT_MS = 10 * 60 * 1000L
        private const val CHECK_INTERVAL_MS = 5000L

        /**
         * Backend status polling while Snap is open. This is what lets the
         * app leave the payment page automatically after a QRIS / VA / wallet
         * payment that never redirects to a merchant success URL.
         */
        private const val STATUS_POLL_INTERVAL_MS = 5000L

        // "Cute & Dreamy" brand tokens (kept in sync with ui/theme/Colors.kt).
        // This Activity builds its views in plain Android code, so the Compose
        // color tokens cannot be reused directly.
        private val PINK_PRIMARY = android.graphics.Color.parseColor("#A95372")     // Rosewood
        private val PINK_BACKGROUND = android.graphics.Color.parseColor("#FFF9F7")  // Vanilla Cream
        private val PINK_SURFACE = android.graphics.Color.parseColor("#FFFFFF")
        private val PINK_HEADER = android.graphics.Color.parseColor("#F9E7ED")      // Blush Mist
        private val PINK_TEXT = android.graphics.Color.parseColor("#493747")        // Plum Ink
        private val PINK_TEXT_SOFT = android.graphics.Color.parseColor("#6E5A68")   // Plum Muted
    }

    private var webView: WebView? = null
    private var progressBar: ProgressBar? = null
    private var loadingOverlay: LinearLayout? = null
    private var loadingText: TextView? = null
    private var errorView: LinearLayout? = null
    private var errorMessage: TextView? = null
    private var lastSnapUrl: String = ""
    private val handler = Handler(Looper.getMainLooper())
    private var hasResult = false
    private var openTime = 0L
    private var firstPageLoaded = false
    private var bookingId = 0

    @Inject
    lateinit var repository: TraveloRepository

    @Inject
    lateinit var sessionManager: SessionManager

    private val statusScope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private var statusPollJob: Job? = null

    private val urlCheckRunnable = object : Runnable {
        override fun run() {
            if (hasResult) return

            val elapsed = System.currentTimeMillis() - openTime
            if (elapsed > TIMEOUT_MS) {
                android.util.Log.w("PaymentWebView", "Timeout after ${elapsed}ms, returning pending")
                setResult(RESULT_PAYMENT_PENDING)
                hasResult = true
                finish()
                return
            }

            webView?.let { w ->
                val url = w.url ?: ""
                if (url.isNotEmpty()) {
                    logDebug("Polling current payment URL (elapsed: ${elapsed}ms)")
                    if (detectResultFromUrl(url)) return
                }
            }

            handler.postDelayed(this, CHECK_INTERVAL_MS)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                onUserBack()
            }
        })

        window.setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE)

        val snapToken = intent.getStringExtra(EXTRA_SNAP_TOKEN) ?: run {
            finish()
            return
        }
        bookingId = intent.getIntExtra(EXTRA_BOOKING_ID, 0)
        val isSandbox = intent.getBooleanExtra(EXTRA_IS_SANDBOX, true)

        val baseUrl = if (isSandbox) {
            "https://app.sandbox.midtrans.com/snap/v2/vtweb/"
        } else {
            "https://app.midtrans.com/snap/v2/vtweb/"
        }
        lastSnapUrl = "$baseUrl$snapToken"
        openTime = System.currentTimeMillis()

        buildUi()
        setupWebView()
        webView?.loadUrl(lastSnapUrl)
        handler.postDelayed(urlCheckRunnable, CHECK_INTERVAL_MS)
        startAutoReturnPolling()
    }

    /**
     * Watches backend truth while the Snap page is still open. When the
     * webhook (or the user's payment method) marks the booking paid, this
     * activity closes itself and [PaymentScreen] takes over with the
     * "confirming your payment" waiting state. The app never infers success
     * from the page URL alone — only the authenticated backend response can
     * trigger this auto-return.
     */
    private fun startAutoReturnPolling() {
        if (bookingId <= 0) return

        statusPollJob = statusScope.launch {
            while (isActive && !hasResult) {
                val token = sessionManager.authToken ?: return@launch

                when (val result = repository.getPaymentStatus(token, bookingId)) {
                    is Resource.Success -> {
                        if (!hasResult && result.data.outcome() == PaymentOutcome.SUCCESS) {
                            logDebug("Backend confirms payment; returning to app automatically")
                            setResult(RESULT_PAYMENT_SUCCESS)
                            finishWithResult()
                            return@launch
                        }
                    }
                    is Resource.Error -> logDebug("Payment status poll failed; will retry")
                    else -> Unit
                }

                delay(STATUS_POLL_INTERVAL_MS)
            }
        }
    }

    private fun buildUi() {
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.MATCH_PARENT
            )
        }

        val header = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(dp(8), dp(12), dp(16), dp(12))
            setBackgroundColor(PINK_HEADER)
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
            )
        }
        val backButton = ImageButton(this).apply {
            setImageResource(android.R.drawable.ic_menu_close_clear_cancel)
            contentDescription = "Back"
            setBackgroundColor(android.graphics.Color.TRANSPARENT)
            // Tint the stock icon with the brand pink so the header stays on-brand.
            setColorFilter(PINK_PRIMARY)
            setOnClickListener { onUserBack() }
        }
        val titleBox = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
            setPadding(dp(8), 0, 0, 0)
        }
        val title = TextView(this).apply {
            text = "Secure Payment"
            textSize = 17f
            setTextColor(PINK_TEXT)
            setTypeface(typeface, android.graphics.Typeface.BOLD)
        }
        val subtitle = TextView(this).apply {
            text = "Secured by Midtrans"
            textSize = 12f
            setTextColor(PINK_TEXT_SOFT)
        }
        titleBox.addView(title)
        titleBox.addView(subtitle)
        header.addView(backButton)
        header.addView(titleBox)
        root.addView(header)

        progressBar = ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal).apply {
            max = 100
            visibility = View.VISIBLE
            progressTintList = ColorStateList.valueOf(PINK_PRIMARY)
            progressBackgroundTintList = ColorStateList.valueOf(PINK_HEADER)
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                dp(4)
            )
        }
        root.addView(progressBar)

        val content = FrameLayout(this).apply {
            setBackgroundColor(PINK_BACKGROUND)
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.MATCH_PARENT,
                0, 1f
            )
        }

        webView = WebView(this).apply {
            layoutParams = FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT
            )
        }
        content.addView(webView)

        loadingOverlay = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            setBackgroundColor(PINK_BACKGROUND)
            layoutParams = FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT
            )
            val spinner = ProgressBar(this@PaymentWebViewActivity).apply {
                isIndeterminate = true
                indeterminateTintList = ColorStateList.valueOf(PINK_PRIMARY)
            }
            loadingText = TextView(this@PaymentWebViewActivity).apply {
                text = "Loading secure payment…"
                textSize = 14f
                setTextColor(PINK_TEXT_SOFT)
                setPadding(0, dp(12), 0, 0)
            }
            addView(spinner)
            addView(loadingText)
        }
        content.addView(loadingOverlay)

        errorView = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            gravity = Gravity.CENTER
            visibility = View.GONE
            setBackgroundColor(PINK_BACKGROUND)
            setPadding(dp(24), dp(24), dp(24), dp(24))
            layoutParams = FrameLayout.LayoutParams(
                FrameLayout.LayoutParams.MATCH_PARENT,
                FrameLayout.LayoutParams.MATCH_PARENT
            )
            errorMessage = TextView(this@PaymentWebViewActivity).apply {
                text = "Connection problem. Please check your internet and retry."
                textSize = 14f
                gravity = Gravity.CENTER
                setTextColor(PINK_TEXT)
            }
            val retry = Button(this@PaymentWebViewActivity).apply {
                text = "Retry"
                setTextColor(android.graphics.Color.WHITE)
                backgroundTintList = ColorStateList.valueOf(PINK_PRIMARY)
                setOnClickListener {
                    errorView?.visibility = View.GONE
                    loadingOverlay?.visibility = View.VISIBLE
                    webView?.loadUrl(lastSnapUrl)
                }
            }
            val close = Button(this@PaymentWebViewActivity).apply {
                text = "Close"
                setTextColor(PINK_PRIMARY)
                backgroundTintList = ColorStateList.valueOf(PINK_SURFACE)
                setOnClickListener { onUserBack() }
            }
            addView(errorMessage)
            addView(retry)
            addView(close)
        }
        content.addView(errorView)

        root.addView(content)
        setContentView(root)
    }

    private fun setupWebView() {
        val w = webView ?: return
        w.settings.apply {
            javaScriptEnabled = true // required by Snap
            domStorageEnabled = true // required by Snap
            // Required for onCreateWindow: Snap hands the e-wallet payment
            // off to a popup; without it the popup is silently dropped.
            setSupportMultipleWindows(true)
            loadWithOverviewMode = true
            useWideViewPort = true
            cacheMode = WebSettings.LOAD_DEFAULT
            // Hardened: Snap is https-only, no file access needed.
            allowFileAccess = false
            allowContentAccess = false
            mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
            setSupportZoom(true)
            builtInZoomControls = true
            displayZoomControls = false
        }

        w.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView?, newProgress: Int) {
                progressBar?.progress = newProgress
                progressBar?.visibility = if (newProgress in 1..99) View.VISIBLE else View.GONE
            }

            /**
             * Snap opens the e-wallet hand-off (GoPay / ShopeePay / DANA
             * sandbox simulator, 3DS frame, "open payment page" link) with
             * window.open(). A WebView without multi-window support silently
             * DROPS that window, so the user only sees Snap's "waiting for
             * payment" screen and the transaction stays pending forever.
             *
             * Fix: create a hidden popup WebView and forward every URL it
             * navigates to into the MAIN WebView, which owns result detection
             * and the visible UI.
             */
            override fun onCreateWindow(view: WebView?, isDialog: Boolean, isUserGesture: Boolean, resultMsg: Message?): Boolean {
                if (resultMsg == null) return false
                val transport = resultMsg.obj ?: return false
                val popup = WebView(this@PaymentWebViewActivity).apply {
                    settings.javaScriptEnabled = true
                    settings.domStorageEnabled = true
                    webViewClient = object : WebViewClient() {
                        @Suppress("deprecation")
                        override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                            forwardFromPopup(url)
                            return true
                        }

                        override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                            forwardFromPopup(request?.url?.toString())
                            return true
                        }

                        override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                            super.onPageStarted(view, url, favicon)
                            if (url != null && !url.startsWith("about:") && !url.startsWith("data:")) {
                                forwardFromPopup(url)
                            }
                        }
                    }
                }

                // WebView.Transport is not exposed in the public SDK stubs, so
                // it is attached reflectively. If that ever fails we return
                // false and the popup behaves like before (dropped) instead of
                // crashing the payment screen.
                val attached = runCatching {
                    val setter = transport.javaClass.methods
                        .firstOrNull { it.name == "setWebView" && it.parameterTypes.size == 1 }
                    if (setter != null) {
                        setter.invoke(transport, popup)
                    } else {
                        val field = transport.javaClass.declaredFields
                            .firstOrNull { it.type == WebView::class.java }
                            ?: throw NoSuchMethodException("setWebView")
                        field.isAccessible = true
                        field.set(transport, popup)
                    }
                }.onFailure {
                    android.util.Log.w("PaymentWebView", "Could not attach popup WebView: ${it.message}")
                    popup.destroy()
                }.isSuccess

                if (!attached) return false
                resultMsg.sendToTarget()
                return true
            }

            override fun onCloseWindow(window: WebView?) {
                window?.destroy()
                super.onCloseWindow(window)
            }
        }

        w.webViewClient = object : WebViewClient() {
            @Suppress("deprecation")
            override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                return handleUrl(url)
            }

            override fun shouldOverrideUrlLoading(view: WebView?, request: WebResourceRequest?): Boolean {
                return handleUrl(request?.url?.toString())
            }

            private fun handleUrl(url: String?): Boolean {
                if (url == null) return false
                logDebug("Navigating payment WebView")

                // Non-http(s) hand-off (gopay://, gojek://, shopeepay://,
                // intent:// ...) must leave the WebView: a WebView cannot
                // load those schemes, which used to leave the user stuck on
                // Snap's "waiting" screen with the transaction still pending.
                val scheme = runCatching { Uri.parse(url).scheme?.lowercase() }.getOrNull()
                if (scheme != null && scheme != "http" && scheme != "https" && scheme != "about" && scheme != "data") {
                    return openExternalUrl(url)
                }

                return detectResultFromUrl(url)
            }

            override fun onPageStarted(view: WebView?, url: String?, favicon: Bitmap?) {
                super.onPageStarted(view, url, favicon)
                if (!firstPageLoaded) loadingOverlay?.visibility = View.VISIBLE
            }

            override fun onPageFinished(view: WebView?, url: String?) {
                super.onPageFinished(view, url)
                firstPageLoaded = true
                loadingOverlay?.visibility = View.GONE
                if (url != null && !hasResult) {
                    logDebug("Payment page finished")
                    detectResultFromUrl(url)
                }
            }

            override fun onReceivedError(view: WebView?, request: WebResourceRequest?, error: WebResourceError?) {
                super.onReceivedError(view, request, error)
                if (request?.isForMainFrame == true && !firstPageLoaded) showError(
                    "Connection problem (${error?.errorCode}). Please retry."
                )
            }

            @Suppress("deprecation")
            override fun onReceivedError(view: WebView?, errorCode: Int, description: String?, failingUrl: String?) {
                super.onReceivedError(view, errorCode, description, failingUrl)
                if (!firstPageLoaded) showError(description ?: "Connection problem. Please retry.")
            }

            override fun onReceivedHttpError(view: WebView?, request: WebResourceRequest?, errorResponse: android.webkit.WebResourceResponse?) {
                super.onReceivedHttpError(view, request, errorResponse)
                if (request?.isForMainFrame == true && !firstPageLoaded) showError(
                    "Payment page failed to load (HTTP ${errorResponse?.statusCode})."
                )
            }

            override fun onReceivedSslError(view: WebView?, handler: SslErrorHandler?, error: SslError?) {
                // Never bypass SSL for payments: cancel and show error.
                handler?.cancel()
                showError("Secure connection failed. Please check your network and retry.")
            }
        }
    }

    private fun showError(message: String) {
        loadingOverlay?.visibility = View.GONE
        errorMessage?.text = message
        errorView?.visibility = View.VISIBLE
    }

    private fun onUserBack() {
        // Return CANCELLED, but callers always re-verify with the backend,
        // so a payment completed in another tab still reconciles to paid.
        if (!hasResult) {
            setResult(RESULT_PAYMENT_CANCELLED)
            hasResult = true
        }
        handler.removeCallbacks(urlCheckRunnable)
        finish()
    }

    /** Load a URL opened by a popup window inside the MAIN WebView. */
    private fun forwardFromPopup(url: String?) {
        if (url.isNullOrBlank() || url.startsWith("about:") || url.startsWith("data:")) return
        logDebug("Moving popup payment page into the main WebView")
        val scheme = runCatching { Uri.parse(url).scheme?.lowercase() }.getOrNull()
        if (scheme != null && scheme != "http" && scheme != "https") {
            openExternalUrl(url)
            return
        }
        val w = webView ?: return
        // The main WebViewClient runs detection on this navigation.
        if (!detectResultFromUrl(url)) {
            w.loadUrl(url)
        }
    }

    /**
     * Hand a non-http(s) URL (wallet deeplink / intent://) to the system.
     * Returns true always: the WebView must never try to load it itself.
     */
    private fun openExternalUrl(url: String): Boolean {
        logDebug("Handing payment flow off to an external app")
        return try {
            val intent = if (url.startsWith("intent://")) {
                Intent.parseUri(url, Intent.URI_INTENT_SCHEME).apply {
                    addCategory(Intent.CATEGORY_BROWSABLE)
                    component = null
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK
                }
            } else {
                Intent(Intent.ACTION_VIEW, Uri.parse(url)).apply {
                    addCategory(Intent.CATEGORY_BROWSABLE)
                    flags = Intent.FLAG_ACTIVITY_NEW_TASK
                }
            }
            startActivity(intent)
            true
        } catch (e: ActivityNotFoundException) {
            android.util.Log.w("PaymentWebView", "No app can open the external payment link")
            // Sandbox simulator / wallet app not available on this device:
            // tell the user instead of leaving a frozen page behind.
            showError(
                "This payment method needs to continue outside the app " +
                    "(wallet app or payment simulator) and it is not available on this device. " +
                    "Please pick another method, or open the payment link in a browser."
            )
            true
        } catch (e: Exception) {
            android.util.Log.w("PaymentWebView", "Failed to open the external payment link")
            true
        }
    }

    /**
     * Result detection.
     *
     * Only TWO kinds of URL may close this page:
     *  1. our own merchant callbacks (/booking/success|pending|error) — the
     *     end of the Snap flow, and
     *  2. a real completion status carried as a TOP-LEVEL query parameter.
     *
     * Deliberately NOT matched (these caused premature closes, which kicked
     * the user out of the payment page BEFORE the wallet/PIN step and left
     * the Midtrans transaction pending forever):
     *  - substring guesses on the whole URL such as "deeplink/payment",
     *    "finish-deeplink", "status_code=200", "result_code=200" — these are
     *    Midtrans's own navigation steps, not payment completion, and
     *  - anything inside a nested callback_url/redirect_url parameter.
     *
     * Backend truth (webhook + verify-and-sync) always decides the final
     * result; this only decides WHEN the payment page closes.
     */
    private fun detectResultFromUrl(url: String): Boolean {
        if (hasResult) return true

        val uri = runCatching { Uri.parse(url) }.getOrNull() ?: return false
        if (!uri.isHierarchical) return false

        val path = (uri.path ?: "").lowercase()
        // Top-level query parameters only: getQueryParameter() never reads
        // into the value of callback_url / redirect_url.
        fun q(name: String): String? = runCatching { uri.getQueryParameter(name)?.lowercase() }.getOrNull()

        val tx = q("transaction_status")
        val status = q("status")
        val payStatus = q("payment_status")

        val isSuccess =
            path.endsWith("/booking/success") ||
                path.endsWith("/success") ||
                path.endsWith("/finish") ||
                tx == "settlement" ||
                tx == "capture" ||
                payStatus == "paid" ||
                status == "success"

        val isPending =
            path.endsWith("/booking/pending") ||
                path.endsWith("/pending") ||
                tx == "pending" ||
                tx == "authorize" ||
                status == "pending"

        val isFailure =
            path.endsWith("/booking/error") ||
                path.endsWith("/error") ||
                path.endsWith("/cancel") ||
                tx == "deny" ||
                tx == "failure" ||
                tx == "expire" ||
                tx == "cancel" ||
                status == "error" ||
                status == "cancel" ||
                status == "failed"

        return when {
            isSuccess -> {
                logDebug("Payment success page detected")
                setResult(RESULT_PAYMENT_SUCCESS)
                finishWithResult()
                true
            }
            isFailure -> {
                logDebug("Payment failure page detected")
                // Backend re-verifies: a real settlement that arrived late is
                // still reconciled to paid by verify-and-sync.
                setResult(RESULT_CANCELED)
                finishWithResult()
                true
            }
            isPending -> {
                // NEVER auto-close on pending. Closing here is what used to
                // kick the user out of the payment flow while the wallet /
                // simulator window was still in flight, leaving every
                // transaction stuck at pending on Midtrans. The page keeps
                // loading (our landing page explains the state) and the user
                // leaves with the header back button, which always
                // reconciles against the backend.
                logDebug("Payment pending page detected; keeping the flow open")
                false
            }
            else -> false
        }
    }

    private fun finishWithResult() {
        hasResult = true
        handler.removeCallbacks(urlCheckRunnable)
        finish()
    }

    /** Never log payment URLs outside debug builds. */
    private fun logDebug(message: String) {
        if (BuildConfig.DEBUG) {
            android.util.Log.d("PaymentWebView", message)
        }
    }

    private fun dp(value: Int): Int = (value * resources.displayMetrics.density).toInt()

    override fun onDestroy() {
        handler.removeCallbacks(urlCheckRunnable)
        statusPollJob?.cancel()
        statusScope.cancel()
        webView?.destroy()
        webView = null
        super.onDestroy()
    }
}
