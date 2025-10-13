@section('title', $data['title'] ?? 'Imagery Uploads')
@section('meta_description', '')

<x-app-layout>
    <section class="p-1 md:p-4">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">{{ $data['title'] }}</h1>
            <p class="text-foreground/60 mt-1">Upload / Add Your Imagery</p>
        </div>

        <div class="mb-4 flex justify-start">
            <div class="flex space-x-2">
                <x-button-secondary href="{{ route('admin.imagery.index') }}" size="small">
                    <i class="ri-arrow-left-line mr-2"></i> Back to Imagery List
                </x-button-secondary>
            </div>
        </div>

        <x-card>
            <div class="space-y-2">
                <form class="space-y-2">
                    <x-input-label class="text-sm font-medium" for="source-type">Source Type</x-input-label>
                    <x-select-input class="px-2! py-1!" id="sourceType" name="source-type" required>
                        <option value="sentinel-2">Sentinel-2</option>
                        <option value="landsat">Landsat</option>
                        <option value="quicksat">Quicksat</option>
                    </x-select-input>
                    <x-input-error class="mt-2" :messages="$errors->get('source-type')" />

                    <x-input-label class="text-sm font-medium" for="imagery-upload">Upload your imagery file</x-input-label>
                    <input class="border-foreground/30 bg-neutral file:bg-foreground/10 focus:border-primary focus:ring-primary block w-full rounded-lg border text-sm shadow-sm file:me-4 file:border-0 file:px-4 file:py-2 focus:z-10 disabled:pointer-events-none disabled:opacity-50" id="fileInput" name="imagery-upload" type="file" accept=".tif,.tiff,.ecw,.zip">
                    <x-input-error class="mt-2" :messages="$errors->get('imagery-upload')" />
                </form>

                <!-- info file -->
                <div class="text-foreground-70 mt-2 hidden text-sm" id="fileInfo"></div>

                <!-- progress bar -->
                <div class="bg-foreground/20 mt-2 h-4 w-full rounded">
                    <div class="bg-primary h-4 rounded" id="progressBar" style="width: 0%;"></div>
                </div>
                <p class="text-foreground/100 mt-1 text-sm" id="progressText">Belum ada upload.</p>

                <!-- tombol kontrol -->
                <div class="space-x-1 space-y-1">
                    <x-button-primary id="startBtn" type="button" size="small">Start Upload</x-button-primary>
                    <x-button-danger id="pauseBtn" type="button" size="small">⏸️ Pause</x-button-danger>
                    <x-button-secondary id="resumeBtn" type="button" size="small">▶️ Resume</x-button-secondary>
                </div>
            </div>
        </x-card>
    </section>


    @push('javascript')
        <script>
            (() => {
                // Cache frequently used DOM references for the uploader workflow.
                const elements = {
                    sourceInput: document.getElementById('sourceType'),
                    fileInput: document.getElementById('fileInput'),
                    fileInfo: document.getElementById('fileInfo'),
                    progressBar: document.getElementById('progressBar'),
                    progressText: document.getElementById('progressText'),
                    startBtn: document.getElementById('startBtn'),
                    pauseBtn: document.getElementById('pauseBtn'),
                    resumeBtn: document.getElementById('resumeBtn'),
                };

                const allElementsReady = Object.values(elements).every(Boolean);

                if (!allElementsReady) {
                    console.warn('Imagery uploader controls missing. Skipping uploader bootstrap.', {
                        hasSourceInput: Boolean(elements.sourceInput),
                        hasFileInput: Boolean(elements.fileInput),
                        hasFileInfo: Boolean(elements.fileInfo),
                        hasProgressBar: Boolean(elements.progressBar),
                        hasProgressText: Boolean(elements.progressText),
                        hasStartBtn: Boolean(elements.startBtn),
                        hasPauseBtn: Boolean(elements.pauseBtn),
                        hasResumeBtn: Boolean(elements.resumeBtn),
                    });
                    return;
                }

                // Configuration values that control chunking and retry behaviour.
                const config = {
                    chunkSize: 5 * 1024 * 1024,
                    maxRetries: 3,
                    autoResetDelay: 4000,
                    imageryProcessingCost: {{ config('app-constants.imagery_processing_cost', 10) }}
                };

                // Endpoints required throughout the upload process.
                const endpoints = {
                    chunk: '{{ route('upload.chunk') }}',
                    merge: '{{ route('upload.merge') }}',
                };

                // Mutable state object tracking progress and timings.
                const state = {
                    paused: false,
                    uploading: false,
                    file: null,
                    uploadId: null,
                    currentChunk: 0,
                    totalChunks: 0,
                    startTime: 0,
                    uploadedBytes: 0
                };

                // Beforeunload handler to prevent navigation during upload
                const beforeUnloadHandler = (e) => {
                    if (state.uploading || (state.file && !state.paused)) {
                        e.preventDefault();
                        e.returnValue = 'An upload is in progress. Are you sure you want to leave?';
                        return e.returnValue;
                    }
                };

                // Ensure the uploader exposes hooks on the global AppMap namespace.
                const ensureAppNamespace = () => {
                    window.AppMap = window.AppMap || {};
                    window.AppMap.uploader = window.AppMap.uploader || {};
                };

                // Toggle button states based on the current upload lifecycle stage.
                const setButtonState = (mode) => {
                    const {
                        startBtn,
                        pauseBtn,
                        resumeBtn
                    } = elements;

                    const disableAll = () => {
                        startBtn.disabled = true;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                    };

                    switch (mode) {
                        case 'ready':
                            startBtn.disabled = false;
                            pauseBtn.disabled = true;
                            resumeBtn.disabled = true;
                            // Restore original button text if it was changed to loading state
                            if (startBtn.innerHTML.includes('Checking')) {
                                startBtn.innerHTML = startBtn.innerHTML.replace(/<i class="ri-loader-4-line animate-spin"><\/i> Checking.../, 'Start Upload');
                            }
                            break;
                        case 'uploading':
                            startBtn.disabled = true;
                            pauseBtn.disabled = false;
                            resumeBtn.disabled = true;
                            // Add beforeunload listener when upload starts
                            window.addEventListener('beforeunload', beforeUnloadHandler);
                            break;
                        case 'paused':
                            startBtn.disabled = true;
                            pauseBtn.disabled = true;
                            resumeBtn.disabled = false;
                            // Keep beforeunload listener when paused
                            break;
                        case 'merging':
                        case 'completed':
                        case 'error':
                            disableAll();
                            if (mode === 'error') {
                                startBtn.disabled = false;
                            }
                            // Remove beforeunload listener when upload completes or errors
                            window.removeEventListener('beforeunload', beforeUnloadHandler);
                            // Restore original button text if it was changed to loading state
                            if (startBtn.innerHTML.includes('Checking')) {
                                startBtn.innerHTML = startBtn.innerHTML.replace(/<i class="ri-loader-4-line animate-spin"><\/i> Checking.../, 'Start Upload');
                            }
                            break;
                        case 'loading':
                            startBtn.disabled = true;
                            startBtn.innerHTML = '<i class="ri-loader-4-line animate-spin"></i> Checking...';
                            pauseBtn.disabled = true;
                            resumeBtn.disabled = true;
                            break;
                        case 'idle':
                        default:
                            disableAll();
                            // Remove beforeunload listener when idle
                            window.removeEventListener('beforeunload', beforeUnloadHandler);
                            // Restore original button text if it was changed to loading state
                            if (startBtn.innerHTML.includes('Checking')) {
                                startBtn.innerHTML = startBtn.innerHTML.replace(/<i class="ri-loader-4-line animate-spin"><\/i> Checking.../, 'Start Upload');
                            }
                            break;
                    }
                };

                // Clear all runtime bookkeeping for a fresh upload session.
                const resetState = () => {
                    state.paused = false;
                    state.uploading = false;
                    state.file = null;
                    state.uploadId = null;
                    state.currentChunk = 0;
                    state.totalChunks = 0;
                    state.startTime = 0;
                    state.uploadedBytes = 0;
                    // Remove beforeunload listener when resetting state
                    window.removeEventListener('beforeunload', beforeUnloadHandler);
                };

                // Restore the UI to an idle appearance without progress.
                const resetUI = () => {
                    const {
                        fileInput,
                        fileInfo,
                        progressBar,
                        progressText
                    } = elements;
                    fileInput.value = '';
                    fileInfo.classList.add('hidden');
                    fileInfo.innerHTML = '';
                    progressBar.style.width = '0%';
                    progressText.textContent = 'Ready for next upload.';
                };

                // Present the selected file name and size before uploading.
                const showFileSummary = (file) => {
                    const {
                        fileInfo,
                        progressBar,
                        progressText
                    } = elements;
                    const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                    const shortName = shortenFilename(file.name, 40);

                    fileInfo.classList.remove('hidden');
                    fileInfo.innerHTML = `
                    <strong>Name:</strong> ${shortName}<br>
                    <strong>Size:</strong> ${sizeMB} MB
                `;

                    progressText.textContent = "✅ File ready to upload. Click 'Start Upload' to begin.";
                    progressBar.style.width = '0%';
                };

                // Update progress bar width and accompanying status text.
                const updateProgressDisplay = (percentage, speedMBps, etaText) => {
                    const {
                        progressBar,
                        progressText
                    } = elements;
                    progressBar.style.width = `${percentage}%`;
                    const speedText = Number.isFinite(speedMBps) ? speedMBps.toFixed(2) : '0.00';
                    progressText.textContent = `Uploading... ${percentage}% | 🚀 ${speedText} MB/s | ⏳ ETA: ${etaText}`;
                };

                // React to new file input selections and prime the uploader.
                const handleFileChange = (event) => {
                    state.file = event.target.files?.[0] || null;

                    if (!state.file) {
                        resetState();
                        resetUI();
                        setButtonState('idle');
                        return;
                    }

                    showFileSummary(state.file);
                    MyZkToast.info('File ready to upload, click Start to begin.');
                    setButtonState('ready');
                };

                // Generate a lightweight identifier for coordinating chunk requests.
                const generateUploadId = () => {
                    const timestamp = Date.now();
                    const random = Math.random().toString(36).substring(2, 10).toUpperCase();
                    return `${timestamp}_${random}`;
                };

                // Convert bytes remaining and elapsed seconds into a readable ETA.
                const formatEta = (remainingBytes, elapsedSeconds) => {
                    if (!Number.isFinite(elapsedSeconds) || elapsedSeconds <= 0) {
                        return '-';
                    }
                    const speedBytesPerSecond = state.uploadedBytes / elapsedSeconds;
                    if (speedBytesPerSecond <= 0) {
                        return '-';
                    }
                    const remainingSeconds = remainingBytes / speedBytesPerSecond;
                    return remainingSeconds > 0 ? formatTimeETA(remainingSeconds) : '-';
                };

                // Upload the next chunk and retry if transient errors occur.
                const uploadNextChunk = async (retryCount = 0) => {
                    if (state.paused || !state.file) return;

                    if (state.currentChunk >= state.totalChunks) {
                        elements.progressText.textContent = '🧩 Preparing file for background merge...';
                        await mergeChunks();
                        return;
                    }

                    const start = state.currentChunk * config.chunkSize;
                    const end = Math.min(state.file.size, start + config.chunkSize);
                    const chunk = state.file.slice(start, end);

                    const formData = new FormData();
                    formData.append('upload_id', state.uploadId);
                    formData.append('chunk_index', state.currentChunk);
                    formData.append('chunk', chunk);

                    try {
                        const response = await fetch(endpoints.chunk, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: formData
                        });

                        const payload = await response.json();
                        if (!response.ok || !payload.success) {
                            throw new Error(payload.message || `Chunk ${state.currentChunk} failed.`);
                        }

                        state.currentChunk += 1;
                        state.uploadedBytes += chunk.size;

                        const elapsedSeconds = Math.max((performance.now() - state.startTime) / 1000, 0.001);
                        const speedMBps = state.uploadedBytes / 1024 / 1024 / elapsedSeconds;
                        const remainingBytes = state.file.size - state.uploadedBytes;
                        const etaText = formatEta(remainingBytes, elapsedSeconds);
                        const progress = Math.round((state.currentChunk / state.totalChunks) * 100);

                        updateProgressDisplay(progress, speedMBps, etaText);

                        if (progress === 100) {
                            MyZkToast.info('Finalising upload on server...');
                        }

                        if (!state.paused) {
                            await uploadNextChunk();
                        }
                    } catch (error) {
                        if (retryCount < config.maxRetries) {
                            const delay = 2000 * (retryCount + 1);
                            setTimeout(() => uploadNextChunk(retryCount + 1), delay);
                            return;
                        }

                        elements.progressText.textContent = `❌ Chunk ${state.currentChunk} failed after ${config.maxRetries} retries. Upload paused.`;
                        MyZkToast.error(`Chunk ${state.currentChunk} failed after ${config.maxRetries} retries.`);
                        state.paused = true;
                        state.uploading = false;
                        setButtonState('paused');
                    }
                };

                // Ask the backend to merge all uploaded chunks into a single file.
                const mergeChunks = async () => {
                    setButtonState('merging');

                    const formData = new FormData();
                    formData.append('upload_id', state.uploadId);
                    formData.append('filename', state.file.name);
                    formData.append('total_chunks', state.totalChunks);
                    formData.append('source_type', elements.sourceInput.value);

                    // Check if user has enough credits to determine processing status
                    const creditCheck = await checkUserCredits();
                    if (!creditCheck.hasCredits) {
                        formData.append('skip_processing', 'true');
                    }

                    try {
                        const response = await fetch(endpoints.merge, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: formData
                        });

                        const result = await response.json();
                        if (!response.ok || !result.success) {
                            throw new Error(result.message || 'Failed to queue merge on server.');
                        }

                        elements.progressBar.style.width = '100%';
                        elements.progressText.textContent = `✅ ${result.message || 'Upload received. Finalising in background.'}`;
                        MyZkToast.success('File received! Finalising in the background.');
                        setButtonState('completed');
                        if (result.data?.currentCredits !== undefined) {
                            $('#current-myCredits').text(formatNumber(result.data.currentCredits, 2));
                        }
                        scheduleAutoReset();
                    } catch (error) {
                        elements.progressText.textContent = `❌ Error: ${error.message}`;
                        MyZkToast.error(error.message || 'Server error while scheduling merge.');
                        setButtonState('error');
                        scheduleAutoReset();
                    }
                };

                // After finishing, reset the UI back to idle after a short delay.
                const scheduleAutoReset = () => {
                    setTimeout(() => {
                        resetState();
                        resetUI();
                        setButtonState('idle');
                    }, config.autoResetDelay);
                };

                // Validate prerequisites and kick off the chunk upload loop.
                const startUpload = () => {
                    if (!state.file) {
                        MyZkToast.warning('Please select a file first!');
                        return;
                    }

                    // Show loading state on start button while checking credits
                    setButtonState('loading');

                    // Check user credits before proceeding
                    checkUserCredits().then(res => {
                        // Restore ready state if user has credits
                        setButtonState('ready');
                        $('#current-myCredits').text(formatNumber(res.currentCredits, 2));

                        // Show confirmation modal before starting upload
                        ZkPopAlert.show({
                            message: `${res.hasCredits ? `This upload will cost ${res.requiredCredits} credit points for processing imagery. Do you want to proceed?` : `Insufficient credit points for processing. You need ${res.requiredCredits} credits. You can still upload the file, but processing will be skipped. Please purchase more credits to continue processing.`}`,
                            icon: '<i class="ri-upload-cloud-2-line text-2xl text-primary"></i>',
                            confirmClass: "focus:ring-primary/80 rounded-md text-sm px-2.5 py-1.5 bg-primary text-primary-foreground border border-primary hover:bg-primary/80 focus:outline-none focus:ring-primary",
                            confirmText: "Yes, Upload",
                            cancelText: "Cancel",
                            onConfirm: () => {
                                state.uploadId = generateUploadId();
                                state.totalChunks = Math.ceil(state.file.size / config.chunkSize);
                                state.currentChunk = 0;
                                state.uploadedBytes = 0;
                                state.paused = false;
                                state.uploading = true;
                                state.startTime = performance.now();

                                MyZkToast.info('🚀 Upload started...');
                                elements.progressText.textContent = `🚀 Uploading ${state.file.name}...`;
                                setButtonState('uploading');
                                uploadNextChunk();
                            }
                        });
                    }).catch(error => {
                        // Restore ready state on error
                        setButtonState('ready');
                        MyZkToast.error('Failed to check credit balance: ' + error.message);
                    });
                };

                // Function to check user credits
                const checkUserCredits = async () => {
                    const response = await fetch('{{ route('user.credits.check') }}');
                    const result = await response.json();

                    if (!result.success) {
                        MyZkToast.error(result.message || 'Failed to check credit balance.');
                        return false;
                    }

                    const currentCredits = parseFloat(formatNumber(result.credits, 2));
                    const requiredCredits = config.imageryProcessingCost || 10; // Default to 10 if not set

                    return new Promise((resolve) => {
                        resolve(data = {
                            hasCredits: currentCredits >= requiredCredits,
                            currentCredits: currentCredits,
                            requiredCredits: requiredCredits
                        });
                    });
                };

                // Suspend ongoing uploads without losing progress state.
                const pauseUpload = () => {
                    if (!state.uploading) {
                        return;
                    }
                    state.paused = true;
                    state.uploading = false;
                    elements.progressText.textContent = '⏸️ Upload paused.';
                    MyZkToast.warning('Upload paused.');
                    setButtonState('paused');
                };

                // Resume uploads after a pause by continuing from the current chunk.
                const resumeUpload = () => {
                    if (!state.file) {
                        return;
                    }
                    state.paused = false;
                    state.uploading = true;
                    elements.progressText.textContent = '▶️ Upload resumed...';
                    MyZkToast.info('Upload resumed...');
                    setButtonState('uploading');
                    uploadNextChunk();
                };

                // Attach DOM event listeners for file and control buttons.
                const bindEventListeners = () => {
                    elements.fileInput.addEventListener('change', handleFileChange);
                    elements.startBtn.addEventListener('click', startUpload);
                    elements.pauseBtn.addEventListener('click', pauseUpload);
                    elements.resumeBtn.addEventListener('click', resumeUpload);
                };

                // Bootstrap the uploader module and fetch initial server data.
                const initialise = () => {
                    ensureAppNamespace();
                    window.setButtonState = setButtonState;

                    resetState();
                    resetUI();
                    setButtonState('idle');
                    bindEventListeners();
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initialise, {
                        once: true
                    });
                } else {
                    initialise();
                }
            })();
        </script>
    @endpush

</x-app-layout>
