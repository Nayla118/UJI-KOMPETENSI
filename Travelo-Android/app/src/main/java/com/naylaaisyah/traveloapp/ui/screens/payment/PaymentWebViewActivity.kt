package com.naylaaisyah.traveloapp.ui.screens.payment

import android.app.Activity
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.webkit.WebChromeClient
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.view.WindowManager

/**
 * Activity that displays the Midtrans payment page in a WebView.
 *
 * IMPORTANT: Many Midtrans payment methods (VA, Indomaret, etc.) do NOT
 * redirect to the finish callback URL. The WebView might stay on Midtrans
 * domain showing "Waiting for payment" or "Payment successful" page.
 *
 * Because of this, we use aggressive URL detection AND a timeout fallback.
 * The ViewModel's verifyAndRefresh() will ALWAYS check the actual status
 * with the backend regardless of what result code we return here.
 */
class PaymentWebViewActivity : Activity() {

    companion object {
        const val EXTRA_SNAP_TOKEN = "snap_token"
        const val EXTRA_IS_SANDBOX = "is_sandbox"
        const val RESULT_PAYMENT_SUCCESS = 1001
        const val RESULT_PAYMENT_PENDING = 1002
        const val RESULT_PAYMENT_CANCELLED = 1003

        private const val TIMEOUT_MS = 3 * 60 * 1000L
        private const val CHECK_INTERVAL_MS = 5000L
    }

    private var webView: WebView? = null
    private val handler = Handler(Looper.getMainLooper())
    private var hasResult = false
    private var openTime = 0L

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
                android.util.Log.d("PaymentWebView", "Polling URL: $url (elapsed: ${elapsed}ms)")

                if (detectResultFromUrl(url)) {
                    return
                }
            }

            handler.postDelayed(this, CHECK_INTERVAL_MS)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        window.setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE)

        val snapToken = intent.getStringExtra(EXTRA_SNAP_TOKEN) ?: run {
            finish()
            return
        }
        val isSandbox = intent.getBooleanExtra(EXTRA_IS_SANDBOX, true)

        val baseUrl = if (isSandbox) {
            "https://app.sandbox.midtrans.com/snap/v2/vtweb/"
        } else {
            "https://app.midtrans.com/snap/v2/vtweb/"
        }

        val snapUrl = "$baseUrl$snapToken"
        openTime = System.currentTimeMillis()

        webView = WebView(this).apply {
            settings.javaScriptEnabled = true
            settings.domStorageEnabled = true
            settings.loadWithOverviewMode = true
            settings.useWideViewPort = true
            settings.allowFileAccess = true
            settings.allowContentAccess = true
            settings.cacheMode = WebSettings.LOAD_DEFAULT
            settings.mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
            settings.setSupportZoom(true)
            settings.builtInZoomControls = true
            settings.displayZoomControls = false
            settings.allowFileAccess = false

            webChromeClient = object : WebChromeClient() {}

            webViewClient = object : WebViewClient() {
                @Suppress("deprecation")
                override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                    return handleUrl(url)
                }

                override fun shouldOverrideUrlLoading(
                    view: WebView?,
                    request: android.webkit.WebResourceRequest?
                ): Boolean {
                    return handleUrl(request?.url?.toString())
                }

                private fun handleUrl(url: String?): Boolean {
                    if (url == null) return false
                    android.util.Log.d("PaymentWebView", "URL load: $url")
                    return detectResultFromUrl(url)
                }

                override fun onPageFinished(view: WebView?, url: String?) {
                    super.onPageFinished(view, url)
                    if (url != null && !hasResult) {
                        android.util.Log.d("PaymentWebView", "Page finished: $url")
                        detectResultFromUrl(url)
                    }
                }

                override fun onReceivedError(
                    view: WebView?,
                    errorCode: Int,
                    description: String?,
                    failingUrl: String?
                ) {
                    super.onReceivedError(view, errorCode, description, failingUrl)
                }
            }

            loadUrl(snapUrl)
        }

        setContentView(webView)

        handler.postDelayed(urlCheckRunnable, CHECK_INTERVAL_MS)
    }

    private fun detectResultFromUrl(url: String): Boolean {
        if (hasResult) return true

        val urlLower = url.lowercase()

        // Success patterns - Midtrans Snap v4 and various payment method redirect patterns
        if (urlLower.contains("finish-deeplink") ||
            urlLower.contains("deeplink/payment") ||
            urlLower.contains("/success") ||
            urlLower.contains("status=success") ||
            urlLower.contains("transaction_status=capture") ||
            urlLower.contains("transaction_status=settlement") ||
            urlLower.contains("result_code=200") ||
            (urlLower.contains("result") && urlLower.contains("success")) ||
            urlLower.contains("payment_status=paid") ||
            urlLower.contains("status_code=200")
        ) {
            android.util.Log.d("PaymentWebView", "SUCCESS detected: $url")
            setResult(RESULT_PAYMENT_SUCCESS)
            hasResult = true
            handler.removeCallbacks(urlCheckRunnable)
            finish()
            return true
        }

        // Pending patterns
        if (urlLower.contains("status=pending") ||
            urlLower.contains("transaction_status=pending") ||
            urlLower.contains("result_code=201")
        ) {
            android.util.Log.d("PaymentWebView", "PENDING detected: $url")
            setResult(RESULT_PAYMENT_PENDING)
            hasResult = true
            handler.removeCallbacks(urlCheckRunnable)
            finish()
            return true
        }

        // Error/deny patterns
        if (urlLower.contains("status=error") ||
            urlLower.contains("transaction_status=deny") ||
            urlLower.contains("transaction_status=expire") ||
            urlLower.contains("transaction_status=cancel")
        ) {
            android.util.Log.d("PaymentWebView", "ERROR detected: $url")
            setResult(RESULT_CANCELED)
            hasResult = true
            handler.removeCallbacks(urlCheckRunnable)
            finish()
            return true
        }

        // Cancel URL
        if (urlLower.contains("/cancel") || urlLower.contains("status=cancel")) {
            android.util.Log.d("PaymentWebView", "CANCEL detected: $url")
            setResult(RESULT_PAYMENT_CANCELLED)
            hasResult = true
            handler.removeCallbacks(urlCheckRunnable)
            finish()
            return true
        }

        return false
    }

    override fun onDestroy() {
        handler.removeCallbacks(urlCheckRunnable)
        webView?.destroy()
        webView = null
        super.onDestroy()
    }

    @Deprecated("Deprecated in Java")
    override fun onBackPressed() {
        setResult(RESULT_PAYMENT_CANCELLED)
        super.onBackPressed()
    }
}
