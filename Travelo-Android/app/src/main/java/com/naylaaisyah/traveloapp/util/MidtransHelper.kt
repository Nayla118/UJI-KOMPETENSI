package com.naylaaisyah.traveloapp.util

import android.app.Activity
import android.content.Context
import android.content.Intent
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.appcompat.app.AlertDialog
import com.naylaaisyah.traveloapp.ui.screens.payment.PaymentWebViewActivity

/**
 * Midtrans Payment Helper
 * 
 * This class handles Midtrans payment integration using an in-app WebView Activity.
 * The payment page is shown inside the app instead of opening an external browser.
 */
object MidtransHelper {
    
    private var webView: WebView? = null
    private var currentDialog: AlertDialog? = null
    
    /**
     * Start payment flow using Snap Token
     * Opens the Midtrans Snap payment page in a dedicated Activity with WebView
     */
    fun startPayment(
        activity: Activity,
        snapToken: String,
        onSuccess: () -> Unit,
        onPending: () -> Unit,
        onError: (String) -> Unit,
        onCancelled: () -> Unit
    ) {
        try {
            // Launch the PaymentWebViewActivity which will handle the payment in-app
            val intent = Intent(activity, PaymentWebViewActivity::class.java).apply {
                putExtra(PaymentWebViewActivity.EXTRA_SNAP_TOKEN, snapToken)
                putExtra(PaymentWebViewActivity.EXTRA_IS_SANDBOX, Constants.MIDTRANS_IS_SANDBOX)
            }
            
            activity.startActivityForResult(intent, 1001)
            
            println("Midtrans Payment started with token: $snapToken")
        } catch (e: Exception) {
            println("Midtrans Payment Error: ${e.message}")
            onError(e.message ?: "Payment failed")
        }
    }
    
    /**
     * Start payment flow using Snap Token - Fallback to Dialog if Activity fails
     * Opens the Midtrans Snap payment page in a WebView displayed in a Dialog
     */
    fun startPaymentWithDialog(
        activity: Activity,
        snapToken: String,
        onSuccess: () -> Unit,
        onPending: () -> Unit,
        onError: (String) -> Unit,
        onCancelled: () -> Unit
    ) {
        try {
            // Build the Snap redirect URL from the token
            val baseUrl = if (Constants.MIDTRANS_IS_SANDBOX) {
                "https://app.sandbox.midtrans.com/snap/v2/vtweb/"
            } else {
                "https://app.midtrans.com/snap/v2/vtweb/"
            }
            
            val snapUrl = "$baseUrl$snapToken"
            
            // Create a WebView for payment
            webView = WebView(activity).apply {
                settings.javaScriptEnabled = true
                settings.domStorageEnabled = true
                settings.loadWithOverviewMode = true
                settings.useWideViewPort = true
                settings.allowFileAccess = true
                settings.allowContentAccess = true
                
                webViewClient = object : WebViewClient() {
                    // Handle older Android versions
                    @Suppress("deprecation")
                    override fun shouldOverrideUrlLoading(view: WebView?, url: String?): Boolean {
                        return handleUrl(url)
                    }
                    
                    // Handle Android 24+ (API 24+)
                    override fun shouldOverrideUrlLoading(
                        view: WebView?,
                        request: android.webkit.WebResourceRequest?
                    ): Boolean {
                        return handleUrl(request?.url?.toString())
                    }
                    
                    private fun handleUrl(url: String?): Boolean {
                        if (url == null) return false
                        
                        // Log the URL for debugging
                        android.util.Log.d("MidtransHelper", "Loading URL: $url")
                        
                        // Check for success - capture all success scenarios
                        if (url.contains("status=success") || 
                            url.contains("transaction_status=capture") || 
                            url.contains("transaction_status=settlement") ||
                            url.contains("result_code=200") ||
                            (url.contains("result") && url.contains("success"))) {
                            dismissDialog()
                            onSuccess()
                            return true
                        }
                        
                        // Check for pending
                        if (url.contains("status=pending") || 
                            url.contains("transaction_status=pending") ||
                            url.contains("result_code=201")) {
                            dismissDialog()
                            onPending()
                            return true
                        }
                        
                        // Check for error
                        if (url.contains("status=error") || 
                            url.contains("transaction_status=deny") ||
                            url.contains("transaction_status=expire") ||
                            url.contains("transaction_status=cancel")) {
                            dismissDialog()
                            onError("Payment was denied")
                            return true
                        }
                        
                        // Check for cancel
                        if (url.contains("/cancel") || url.contains("status=cancel")) {
                            dismissDialog()
                            onCancelled()
                            return true
                        }
                        
                        // For all other URLs (including payment method selection pages),
                        // load them in the WebView - don't open external browser
                        return false
                    }
                    
                    override fun onPageFinished(view: WebView?, url: String?) {
                        super.onPageFinished(view, url)
                        if (url != null && url.contains("result") && url.contains("success")) {
                            dismissDialog()
                            onSuccess()
                        }
                    }
                }
                
                loadUrl(snapUrl)
            }
            
            // Show WebView in a Dialog
            currentDialog = AlertDialog.Builder(activity)
                .setTitle("Payment")
                .setView(webView)
                .setCancelable(true)
                .setOnCancelListener {
                    dismissDialog()
                    onCancelled()
                }
                .create()
            
            currentDialog?.show()
            
            println("Midtrans Payment initiated with URL: $snapUrl")
        } catch (e: Exception) {
            println("Midtrans Payment Error: ${e.message}")
            onError(e.message ?: "Payment failed")
        }
    }
    
    /**
     * Dismiss the payment dialog and cleanup
     */
    private fun dismissDialog() {
        currentDialog?.dismiss()
        currentDialog = null
        webView?.destroy()
        webView = null
    }
    
    /**
     * Cleanup resources
     */
    fun cleanup() {
        dismissDialog()
    }
}
