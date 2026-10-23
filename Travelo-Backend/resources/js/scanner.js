const root = document.getElementById('ticket-scanner');

if (root) {
    initTicketScanner(root);
}

function initTicketScanner(scannerRoot) {
    const redeemUrl = scannerRoot.dataset.redeemUrl;
    const csrfToken = scannerRoot.dataset.csrfToken;
    const secureScannerUrl = scannerRoot.dataset.secureScannerUrl || '';

    const reader = document.getElementById('scanner-reader');
    const placeholder = document.getElementById('scanner-placeholder');
    const startButton = document.getElementById('scanner-start');
    const stopButton = document.getElementById('scanner-stop');
    const nextButton = document.getElementById('scanner-next');
    const cameraStatus = document.getElementById('scanner-camera-status');
    const cameraPicker = document.getElementById('scanner-camera-picker');
    const cameraSelect = document.getElementById('scanner-camera-select');
    const insecureWarning = document.getElementById('scanner-insecure-warning');
    const secureLink = document.getElementById('scanner-secure-link');
    const manualForm = document.getElementById('manual-ticket-form');
    const manualInput = document.getElementById('manual-ticket-token');
    const manualSubmit = document.getElementById('manual-ticket-submit');
    const photoButton = document.getElementById('scanner-photo-button');
    const photoInput = document.getElementById('scanner-photo-input');
    const fileButton = document.getElementById('scanner-file-button');
    const fileInput = document.getElementById('scanner-file-input');

    const resultPanel = document.getElementById('scanner-result');
    const resultCard = document.getElementById('scanner-result-card');
    const resultIconWrap = document.getElementById('scanner-result-icon-wrap');
    const resultIcon = document.getElementById('scanner-result-icon');
    const resultKicker = document.getElementById('scanner-result-kicker');
    const resultTitle = document.getElementById('scanner-result-title');
    const resultMessage = document.getElementById('scanner-result-message');
    const resultDetails = document.getElementById('scanner-result-details');
    const resultNextButton = document.getElementById('scanner-result-next');
    const resultCloseButton = document.getElementById('scanner-result-close');
    const resultXButton = document.getElementById('scanner-result-x');

    if (!redeemUrl || !csrfToken || !reader || !manualForm || !manualInput) {
        return;
    }

    let scanner = null;
    let scannerLibrary = null;
    let cameraRunning = false;
    let cameraStarting = false;
    let submitting = false;
    let detectionHintTimer = null;
    let availableCameras = [];
    let activeCameraId = null;
    let camerasLoaded = false;
    let resumeScannerOnDismiss = false;

    const tones = {
        success: {
            wrap: 'bg-sage/30',
            icon: 'text-success',
            kicker: 'text-success',
            symbol: 'check_circle',
            card: 'border-success/30',
        },
        warning: {
            wrap: 'bg-orange-100',
            icon: 'text-warning',
            kicker: 'text-warning',
            symbol: 'block',
            card: 'border-warning/30',
        },
        danger: {
            wrap: 'bg-red-100',
            icon: 'text-danger',
            kicker: 'text-danger',
            symbol: 'error',
            card: 'border-danger/30',
        },
        neutral: {
            wrap: 'bg-blush',
            icon: 'text-primary',
            kicker: 'text-primary',
            symbol: 'info',
            card: 'border-slate-200',
        },
    };

    function setCameraStatus(message) {
        if (cameraStatus) {
            cameraStatus.textContent = message;
        }
    }

    function clearDetectionHint() {
        if (detectionHintTimer) {
            clearTimeout(detectionHintTimer);
            detectionHintTimer = null;
        }
    }

    function armDetectionHint() {
        clearDetectionHint();

        detectionHintTimer = setTimeout(() => {
            detectionHintTimer = null;

            if (!cameraRunning || submitting) {
                return;
            }

            setCameraStatus(
                'No QR detected yet. Move closer or farther from the screen, avoid glare, or use Take QR photo / Scan QR image.'
            );
        }, 8000);
    }

    function setBusy(busy) {
        submitting = busy;
        if (manualSubmit) {
            manualSubmit.disabled = busy;
        }
        if (fileButton) {
            fileButton.disabled = busy;
        }
        if (fileInput) {
            fileInput.disabled = busy;
        }
        if (photoButton) {
            photoButton.disabled = busy;
        }
        if (photoInput) {
            photoInput.disabled = busy;
        }
        if (startButton) {
            startButton.disabled = busy;
        }
        if (stopButton) {
            stopButton.disabled = busy;
        }
    }

    function renderCameraPicker() {
        if (!cameraPicker || !cameraSelect) {
            return;
        }

        cameraSelect.replaceChildren();

        availableCameras.forEach((camera, index) => {
            const option = document.createElement('option');
            option.value = camera.id;
            option.textContent = camera.label || `Camera ${index + 1}`;
            cameraSelect.append(option);
        });

        const hasChoice = availableCameras.length > 1;
        cameraPicker.classList.toggle('hidden', !hasChoice);
        cameraPicker.classList.toggle('flex', hasChoice);

        if (activeCameraId) {
            cameraSelect.value = activeCameraId;
        }
    }

    function pickPreferredCamera(cameras) {
        if (!cameras.length) {
            return null;
        }

        if (cameras.length === 1) {
            return cameras[0];
        }

        // Phone browsers usually label the rear camera "back" or "environment".
        // Prefer it explicitly instead of hoping the browser honours
        // facingMode. If labels are unavailable, the last camera is usually
        // the rear one on Android.
        return cameras.find((camera) => /back|rear|environment|belakang/i.test(camera.label || ''))
            || cameras[cameras.length - 1];
    }

    async function ensureCameras() {
        if (camerasLoaded) {
            return;
        }

        try {
            const cameras = await scannerLibrary.Html5Qrcode.getCameras();
            availableCameras = Array.isArray(cameras) ? cameras.filter((camera) => camera?.id) : [];
            camerasLoaded = availableCameras.length > 0;

            const preferred = pickPreferredCamera(availableCameras);
            activeCameraId = preferred?.id || null;
            renderCameraPicker();
        } catch {
            // Permission errors are reported by start() with a clearer message.
            // If enumeration is unavailable, fall back to facingMode below.
            availableCameras = [];
            activeCameraId = null;
            renderCameraPicker();
        }
    }

    async function refreshCameraListFromDevices() {
        if (!navigator.mediaDevices?.enumerateDevices) {
            return;
        }

        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const cameras = devices
                .filter((device) => device.kind === 'videoinput' && device.deviceId)
                .map((device, index) => ({
                    id: device.deviceId,
                    label: device.label || `Camera ${index + 1}`,
                }));

            if (!cameras.length) {
                return;
            }

            availableCameras = cameras;
            camerasLoaded = true;

            if (!activeCameraId || !availableCameras.some((camera) => camera.id === activeCameraId)) {
                activeCameraId = pickPreferredCamera(availableCameras)?.id || null;
            }

            renderCameraPicker();
        } catch {
            // Enumeration is best-effort; the active stream can still scan.
        }
    }

    function cameraCandidates() {
        const candidates = [];

        if (activeCameraId) {
            candidates.push({ deviceId: { exact: activeCameraId } });
        }

        // If the preferred camera cannot start (for example a front camera
        // that reports an unsupported constraint), try every other camera the
        // browser exposes before giving up.
        availableCameras.forEach((camera) => {
            if (camera.id !== activeCameraId) {
                candidates.push({ deviceId: { exact: camera.id } });
            }
        });

        candidates.push({ facingMode: 'environment' }, { facingMode: 'user' });

        return candidates;
    }

    function syncActiveCameraFromPreview() {
        const video = reader.querySelector('video');
        const track = video?.srcObject?.getVideoTracks?.()[0];
        const deviceId = track?.getSettings?.().deviceId;

        if (deviceId && availableCameras.some((camera) => camera.id === deviceId)) {
            activeCameraId = deviceId;
            renderCameraPicker();
        }
    }

    function closeResultDialog() {
        if (!resultPanel) {
            return;
        }

        if (typeof resultPanel.close === 'function' && resultPanel.open) {
            resultPanel.close();
        } else {
            resultPanel.removeAttribute('open');
        }
    }

    function openResultDialog() {
        if (!resultPanel) {
            return;
        }

        if (typeof resultPanel.showModal === 'function') {
            if (!resultPanel.open) {
                resultPanel.showModal();
            }
        } else {
            resultPanel.setAttribute('open', '');
        }

        const focusTarget = resultNextButton && !resultNextButton.classList.contains('hidden')
            ? resultNextButton
            : resultCloseButton;

        focusTarget?.focus({ preventScroll: true });
    }

    function dismissResultDialog() {
        const shouldResume = resumeScannerOnDismiss;
        resumeScannerOnDismiss = false;
        closeResultDialog();

        if (shouldResume) {
            scanNext();
            return;
        }

        nextButton?.focus({ preventScroll: true });
    }

    function hideResult() {
        resumeScannerOnDismiss = false;
        closeResultDialog();
        nextButton?.classList.add('hidden');
        nextButton?.classList.remove('inline-flex');
    }

    function showResult(tone, kicker, title, message, details = [], options = {}) {
        if (!resultPanel) {
            return;
        }

        const style = tones[tone] || tones.neutral;
        const allowNext = options.allowNext ?? (tone === 'success');
        const resumeOnDismiss = options.resumeOnDismiss ?? (tone !== 'success');

        if (resultCard) {
            resultCard.className = `rounded-2xl border bg-white p-5 shadow-2xl sm:p-6 ${style.card}`;
        }
        if (resultIconWrap) {
            resultIconWrap.className = `flex size-11 shrink-0 items-center justify-center rounded-full ${style.wrap}`;
        }
        if (resultIcon) {
            resultIcon.className = `material-symbols-outlined text-2xl ${style.icon}`;
            resultIcon.textContent = style.symbol;
        }
        if (resultKicker) {
            resultKicker.className = `text-xs font-bold uppercase tracking-wider ${style.kicker}`;
            resultKicker.textContent = kicker;
        }
        if (resultTitle) {
            resultTitle.textContent = title;
        }
        if (resultMessage) {
            resultMessage.textContent = message;
        }

        if (resultDetails) {
            resultDetails.replaceChildren();
            details.forEach(([label, value]) => {
                const text = value === null || value === undefined ? '' : String(value).trim();
                if (text === '') {
                    return;
                }

                const row = document.createElement('div');
                row.className = 'flex items-start justify-between gap-4';

                const term = document.createElement('dt');
                term.className = 'text-muted';
                term.textContent = label;

                const description = document.createElement('dd');
                description.className = 'text-right font-semibold text-ink';
                description.textContent = text;

                row.append(term, description);
                resultDetails.append(row);
            });
            resultDetails.classList.toggle('hidden', resultDetails.childElementCount === 0);
        }

        // Only a successful redemption gets the primary "Scan next ticket"
        // action. Invalid or failed checks keep the warning-only Close action
        // and resume the camera automatically after it is dismissed.
        resultNextButton?.classList.toggle('hidden', !allowNext);
        resultNextButton?.classList.toggle('inline-flex', allowNext);
        nextButton?.classList.toggle('hidden', !allowNext);
        nextButton?.classList.toggle('inline-flex', allowNext);
        resumeScannerOnDismiss = resumeOnDismiss;

        openResultDialog();
    }

    function showSuccess(body) {
        const data = body?.data || {};
        const destination = data.destination
            ? [data.destination.name, data.destination.city].filter(Boolean).join(', ')
            : '';
        const visitors = Number(data.visitor_count);
        const travelers = Number.isFinite(visitors) && visitors > 0
            ? `${visitors} ${visitors === 1 ? 'traveler' : 'travelers'}`
            : '';

        showResult(
            'success',
            'Check-in complete',
            'Ticket redeemed',
            'The ticket is now marked as used. The traveller can enter once.',
            [
                ['Reference', data.reference],
                ['Guest', data.holder_name],
                ['Destination', destination],
                ['Visit date', data.visit_date],
                ['Travelers', travelers],
            ]
        );
    }

    function showFailure(status, body) {
        const reason = body?.reason;
        const serverMessage = typeof body?.message === 'string' ? body.message : '';

        if (status === 401 || status === 419) {
            showResult(
                'danger',
                'Session',
                'Sign in again',
                'Your admin session has expired. Reload the page and sign in before scanning another ticket.',
                [],
                { resumeOnDismiss: false }
            );
            return;
        }

        if (status === 0) {
            showResult(
                'danger',
                'Connection',
                'Could not check the ticket',
                'The server could not be reached. Check the network connection and try again.',
                [],
                { resumeOnDismiss: false }
            );
            return;
        }

        if (reason === 'used') {
            showResult(
                'warning',
                'Already used',
                'Do not admit again',
                'This ticket has already been redeemed at the gate.'
            );
            return;
        }

        if (reason === 'expired') {
            showResult(
                'danger',
                'Expired',
                'Ticket has expired',
                'The visit date for this booking has passed.'
            );
            return;
        }

        if (reason === 'not_due') {
            const visitDate = body?.data?.visit_date || '';
            const reference = body?.data?.reference || '';

            showResult(
                'warning',
                'Not today',
                'Ticket is not valid yet',
                serverMessage || 'This ticket is for a future visit date and cannot be redeemed today.',
                [
                    ['Visit date', visitDate],
                    ['Reference', reference],
                ]
            );
            setCameraStatus('Ticket is for a future visit date. It was not redeemed.');
            return;
        }

        if (reason === 'not_paid') {
            showResult(
                'danger',
                'Not eligible',
                'Payment is not confirmed',
                'This booking cannot enter because its payment is not confirmed.'
            );
            return;
        }

        if (reason === 'forbidden_destination') {
            showResult(
                'danger',
                'Wrong destination',
                'Not authorized for this ticket',
                'Your staff account is not assigned to this ticket\'s destination.'
            );
            return;
        }

        if (reason === 'invalid_token' || status === 422) {
            showResult(
                'danger',
                'Invalid ticket',
                'Ticket could not be verified',
                'This is not a valid Travelo ticket token. Scan the QR again or use the manual fallback.'
            );
            return;
        }

        showResult(
            'danger',
            'Not redeemed',
            'Ticket could not be redeemed',
            serverMessage || 'Please check the ticket and try again.'
        );
    }

    async function redeemToken(token) {
        hideResult();
        clearDetectionHint();

        // Pause a running camera before checking. This prevents a second
        // frame from submitting the same QR while the first request is open.
        if (scanner && cameraRunning) {
            try {
                scanner.pause(true);
            } catch {
                // A pause failure must not prevent the ticket check.
            }
        }

        setCameraStatus('Checking ticket with the server...');

        try {
            const response = await fetch(redeemUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ token }),
            });

            let body = null;

            try {
                body = await response.json();
            } catch {
                body = null;
            }

            if (response.redirected && body === null) {
                // An expired session redirects to the login HTML page. Do not
                // report that as a successful ticket check.
                showFailure(401, null);
                setCameraStatus('Your session has expired. Reload and sign in again.');
            } else if (response.ok && body?.success === true) {
                showSuccess(body);
                setCameraStatus('Ticket redeemed. Press Scan next ticket when ready.');
            } else {
                showFailure(response.status, body);
                setCameraStatus('Ticket was not redeemed. Check the result before trying again.');
            }
        } catch {
            showFailure(0, null);
            setCameraStatus('The server could not be reached.');
        } finally {
            manualInput.value = '';
        }
    }

    async function submitToken(rawToken) {
        const token = String(rawToken || '').trim();

        if (submitting || token === '') {
            return;
        }

        setBusy(true);

        try {
            await redeemToken(token);
        } finally {
            setBusy(false);
        }
    }

    async function loadScannerLibrary() {
        if (!scannerLibrary) {
            scannerLibrary = await import('html5-qrcode');
        }

        return scannerLibrary;
    }

    function createScanner(elementId) {
        return new scannerLibrary.Html5Qrcode(elementId, {
            verbose: false,
            formatsToSupport: [scannerLibrary.Html5QrcodeSupportedFormats.QR_CODE],
            // ZXing is bundled with html5-qrcode and gives consistent results
            // across browsers. Native BarcodeDetector support differs by
            // browser/device and is not reliable enough for gate operations.
            experimentalFeatures: { useBarCodeDetectorIfSupported: false },
        });
    }

    function onQrDecoded(decodedText) {
        if (submitting) {
            return;
        }

        clearDetectionHint();
        setCameraStatus('QR detected. Checking ticket...');
        submitToken(decodedText);
    }

    function cameraErrorText(error) {
        return [error?.name, error?.message, String(error || '')]
            .filter(Boolean)
            .join(' ');
    }

    function isCameraPermissionError(error) {
        return /notallowederror|securityerror|permission denied|not allowed/i.test(cameraErrorText(error));
    }

    function cameraErrorMessage(error) {
        const errorText = cameraErrorText(error);

        if (isCameraPermissionError(error)) {
            return 'Camera permission was denied. Allow camera access in the browser, use Take QR photo, or use the manual fallback.';
        }

        if (/notfounderror|overconstrainederror|no camera found|no usable camera/i.test(errorText)) {
            return 'No usable camera was found on this device. Use Take QR photo on a phone, Scan QR image, or the manual fallback.';
        }

        if (/notreadableerror|track starterror|device in use|could not start video source/i.test(errorText)) {
            return 'The camera is busy in another app. Close it and try again, or use Take QR photo.';
        }

        return 'The camera could not be started. Use Take QR photo, choose another camera, or use the manual fallback.';
    }

    function cameraOptions() {
        return {
            fps: 10,
            qrbox: (viewfinderWidth, viewfinderHeight) => {
                // Leave enough margin for the camera preview but scan most of
                // the frame. A fixed 250px box cropped larger QR codes on
                // high-resolution phone cameras.
                const edge = Math.max(
                    160,
                    Math.min(
                        Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.8),
                        360
                    )
                );

                return { width: edge, height: edge };
            },
        };
    }

    function activeCameraLabel() {
        return availableCameras.find((camera) => camera.id === activeCameraId)?.label || '';
    }

    async function startCamera({ automatic = false } = {}) {
        if (cameraRunning || cameraStarting || submitting) {
            return;
        }

        if (!window.isSecureContext) {
            setCameraStatus(
                'This page is not HTTPS, so the browser blocks live camera access. Use Take QR photo, open the secure scanner, or use the manual fallback.'
            );
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setCameraStatus('This browser does not support live camera scanning. Use Take QR photo or the manual token fallback.');
            return;
        }

        cameraStarting = true;

        if (startButton) {
            startButton.disabled = true;
        }
        setCameraStatus(automatic ? 'Starting the camera automatically...' : 'Requesting camera access...');

        try {
            await loadScannerLibrary();
            await ensureCameras();

            reader.classList.remove('hidden');
            placeholder?.classList.add('hidden');

            let lastError = null;
            let started = false;

            for (const target of cameraCandidates()) {
                try {
                    scanner = createScanner('scanner-reader');
                    setCameraStatus(
                        target?.deviceId?.exact
                            ? 'Starting the selected camera...'
                            : 'Trying the default camera...'
                    );

                    await scanner.start(target, cameraOptions(), onQrDecoded, () => {
                        // Per-frame decode misses are expected while the camera
                        // searches for a QR code; there is nothing to report.
                    });

                    started = true;

                    if (target?.deviceId?.exact) {
                        activeCameraId = target.deviceId.exact;
                        renderCameraPicker();
                    }

                    break;
                } catch (error) {
                    lastError = error;

                    if (scanner) {
                        try {
                            scanner.clear();
                        } catch {
                            // Ignore cleanup failures before trying the next camera.
                        }
                        scanner = null;
                    }

                    // A denied permission applies to every camera, so trying
                    // the remaining devices would only repeat the same error.
                    if (isCameraPermissionError(error)) {
                        break;
                    }
                }
            }

            if (!started) {
                throw lastError || new Error('No camera could be started.');
            }

            cameraRunning = true;

            // After permission is granted, enumerateDevices exposes friendly
            // labels and any camera that getCameras() missed.
            await refreshCameraListFromDevices();
            syncActiveCameraFromPreview();

            hideResult();
            startButton?.classList.add('hidden');
            stopButton?.classList.remove('hidden');
            stopButton?.classList.add('inline-flex');

            const label = activeCameraLabel();
            setCameraStatus(
                `Camera active${label ? ` (${label})` : ''}. Scanning is automatic; hold the QR code inside the square. If it is not detected, move closer or use Take QR photo.`
            );
            armDetectionHint();
        } catch (error) {
            clearDetectionHint();
            reader.classList.add('hidden');
            placeholder?.classList.remove('hidden');
            cameraRunning = false;

            if (scanner) {
                try {
                    scanner.clear();
                } catch {
                    // Ignore cleanup failures after a failed start.
                }
                scanner = null;
            }

            setCameraStatus(
                automatic && isCameraPermissionError(error)
                    ? 'The browser could not open the permission prompt yet. Press Start camera and allow camera access, or use Take QR photo / the manual fallback.'
                    : cameraErrorMessage(error)
            );
        } finally {
            cameraStarting = false;

            if (startButton) {
                startButton.disabled = false;
            }
        }
    }

    async function stopCamera() {
        if (!scanner || !cameraRunning) {
            return;
        }

        clearDetectionHint();

        if (stopButton) {
            stopButton.disabled = true;
        }

        try {
            await scanner.stop();
            scanner.clear();
        } catch {
            // Ignore stop/cleanup errors; the camera may already be released.
        }

        scanner = null;
        cameraRunning = false;
        reader.classList.add('hidden');
        placeholder?.classList.remove('hidden');
        startButton?.classList.remove('hidden');
        startButton?.classList.add('inline-flex');
        stopButton?.classList.add('hidden');
        stopButton?.classList.remove('inline-flex');
        setCameraStatus('Camera is off.');
        if (stopButton) {
            stopButton.disabled = false;
        }

        if (manualInput) {
            manualInput.focus({ preventScroll: true });
        }
    }

    async function switchCamera(nextCameraId) {
        if (!nextCameraId || nextCameraId === activeCameraId || submitting) {
            return;
        }

        activeCameraId = nextCameraId;
        renderCameraPicker();

        if (!scanner || !cameraRunning) {
            return;
        }

        clearDetectionHint();
        cameraRunning = false;

        if (cameraSelect) {
            cameraSelect.disabled = true;
        }
        setCameraStatus('Switching camera...');

        try {
            await scanner.stop();
            scanner.clear();
        } catch {
            // The old track may already be gone; startCamera still gets a
            // fresh scanner instance below.
        }

        scanner = null;
        reader.classList.add('hidden');
        startButton?.classList.remove('hidden');
        startButton?.classList.add('inline-flex');
        stopButton?.classList.add('hidden');
        stopButton?.classList.remove('inline-flex');

        await startCamera();

        if (cameraSelect) {
            cameraSelect.disabled = false;
        }
    }

    async function scanNext() {
        hideResult();
        manualInput.value = '';

        if (scanner && cameraRunning) {
            try {
                if (scanner.getState() === scannerLibrary.Html5QrcodeScannerState.PAUSED) {
                    scanner.resume();
                }

                setCameraStatus('Camera active. Scanning is automatic; hold the next QR code inside the square, or use Take QR photo.');
                armDetectionHint();
                manualInput.focus({ preventScroll: true });
                return;
            } catch {
                // Fall through to a fresh camera start if resume is not possible.
            }
        }

        await startCamera();
    }

    async function scanQrImage(file) {
        if (!file || submitting) {
            return;
        }

        setBusy(true);
        clearDetectionHint();
        setCameraStatus('Reading the QR code from the selected image...');

        if (scanner && cameraRunning) {
            try {
                scanner.pause(true);
            } catch {
                // A pause failure must not prevent the image scan.
            }
        }

        let fileScanner = null;

        try {
            await loadScannerLibrary();
            fileScanner = createScanner('scanner-file-reader');

            // showImage=false keeps the selected screenshot out of the page.
            // Only the decoded token is sent to the server.
            const decodedText = await fileScanner.scanFile(file, false);

            await redeemToken(decodedText);
        } catch (error) {
            const errorText = String(error || '');
            const noQrFound = /not found|notfound|no multiformat readers|parse error/i.test(errorText);

            if (noQrFound) {
                showResult(
                    'danger',
                    'No QR found',
                    'Could not find a ticket QR',
                    'Take a sharp, uncropped photo or screenshot of the e-ticket QR and try again.'
                );
                setCameraStatus('No QR code was found in the selected image.');
            } else {
                showResult(
                    'danger',
                    'Image not readable',
                    'Could not read the selected image',
                    'Choose a PNG or JPEG photo/screenshot of the e-ticket, or use the live camera.'
                );
                setCameraStatus('The selected image could not be read.');
            }
        } finally {
            if (fileScanner) {
                try {
                    fileScanner.clear();
                } catch {
                    // Ignore cleanup failures after an image scan.
                }
            }

            if (fileInput) {
                fileInput.value = '';
            }

            if (photoInput) {
                photoInput.value = '';
            }

            setBusy(false);
        }
    }

    if (!window.isSecureContext) {
        insecureWarning?.classList.remove('hidden');

        if (secureLink && secureScannerUrl) {
            secureLink.href = secureScannerUrl;
            secureLink.classList.remove('hidden');
        }

        setCameraStatus(
            'Live camera is blocked because this page is not HTTPS. Use Take QR photo, open the secure scanner, or use the manual fallback.'
        );
    }

    function autoStartCamera() {
        if (cameraRunning || cameraStarting || submitting || !window.isSecureContext) {
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setCameraStatus('This browser does not support live camera scanning. Use Take QR photo or the manual token fallback.');
            return;
        }

        const startWhenVisible = () => {
            if (!cameraRunning && !cameraStarting && !submitting) {
                startCamera({ automatic: true });
            }
        };

        const startAfterPaint = () => window.setTimeout(startWhenVisible, 150);

        if (document.visibilityState === 'hidden') {
            const onVisible = () => {
                if (document.visibilityState !== 'visible') {
                    return;
                }

                document.removeEventListener('visibilitychange', onVisible);
                startAfterPaint();
            };

            document.addEventListener('visibilitychange', onVisible);
            return;
        }

        startAfterPaint();
    }

    startButton?.addEventListener('click', () => startCamera());
    stopButton?.addEventListener('click', stopCamera);
    nextButton?.addEventListener('click', scanNext);
    cameraSelect?.addEventListener('change', () => switchCamera(cameraSelect.value));

    resultNextButton?.addEventListener('click', scanNext);
    resultCloseButton?.addEventListener('click', dismissResultDialog);
    resultXButton?.addEventListener('click', dismissResultDialog);

    resultPanel?.addEventListener('cancel', (event) => {
        event.preventDefault();
        dismissResultDialog();
    });

    resultPanel?.addEventListener('click', (event) => {
        if (event.target !== resultPanel) {
            return;
        }

        const rect = resultPanel.getBoundingClientRect();
        const outside = event.clientX < rect.left
            || event.clientX > rect.right
            || event.clientY < rect.top
            || event.clientY > rect.bottom;

        if (outside) {
            dismissResultDialog();
        }
    });

    resultPanel?.addEventListener('close', () => {
        if (nextButton && !nextButton.classList.contains('hidden')) {
            nextButton.focus({ preventScroll: true });
        }
    });

    photoButton?.addEventListener('click', () => {
        if (!submitting) {
            photoInput?.click();
        }
    });

    photoInput?.addEventListener('change', () => {
        const file = photoInput.files?.[0];

        if (file) {
            scanQrImage(file);
        }
    });

    fileButton?.addEventListener('click', () => {
        if (!submitting) {
            fileInput?.click();
        }
    });

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files?.[0];

        if (file) {
            scanQrImage(file);
        }
    });

    manualForm.addEventListener('submit', (event) => {
        event.preventDefault();

        if (submitting) {
            return;
        }

        const token = manualInput.value.trim();

        if (token === '') {
            manualInput.focus();
            return;
        }

        submitToken(token);
    });

    window.addEventListener('pagehide', () => {
        clearDetectionHint();
        if (scanner && cameraRunning) {
            scanner.stop().catch(() => {});
        }
    });

    // Opening the scanner starts the rear camera without another tap. The
    // browser may still ask for camera permission on the first visit; if it
    // requires a gesture, the Start camera and Take QR photo fallbacks remain.
    autoStartCamera();
}
