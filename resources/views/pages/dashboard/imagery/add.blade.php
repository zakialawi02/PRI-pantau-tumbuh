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
                    <x-button-primary id="startBtn" type="button" size="small">🚀 Start Upload</x-button-primary>
                    <x-button-danger id="pauseBtn" type="button" size="small">⏸️ Pause</x-button-danger>
                    <x-button-secondary id="resumeBtn" type="button" size="small">▶️ Resume</x-button-secondary>
                </div>
            </div>
        </x-card>
    </section>


    @push('javascript')
        <script>
            const sourceInput = document.getElementById('sourceType');
            const fileInput = document.getElementById('fileInput');
            const fileInfo = document.getElementById('fileInfo');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const startBtn = document.getElementById('startBtn');
            const pauseBtn = document.getElementById('pauseBtn');
            const resumeBtn = document.getElementById('resumeBtn');
            const myDataContainer = document.getElementById('myDataContainer');

            // === STATE ===
            let paused = false;
            let uploading = false;
            let file = null;
            let uploadId = null;
            let currentChunk = 0;
            let totalChunks = 0;
            const chunkSize = 5 * 1024 * 1024; // 5 MB per chunk
            let startTime = null;
            let uploadedBytes = 0;

            // === INIT ===
            setButtonState("idle");

            // === FILE SELECT ===
            fileInput.addEventListener("change", (e) => {
                file = e.target.files[0];
                if (!file) return;

                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                const shortName = shortenFilename(file.name, 40);

                fileInfo.classList.remove("hidden");
                fileInfo.innerHTML = `
            <strong>Name:</strong> ${shortName}<br>
            <strong>Size:</strong> ${sizeMB} MB
        `;

                progressText.textContent = "✅ File ready to upload. Click 'Start Upload' to begin.";
                progressBar.style.width = "0%";
                MyZkToast.info("File ready to upload, click Start to begin.");
                setButtonState("ready");
            });

            // === START UPLOAD ===
            startBtn.addEventListener("click", () => {
                if (!file) {
                    MyZkToast.warning("Please select a file first!");
                    return;
                }

                uploadId = Math.random().toString(36).substring(2, 12);
                totalChunks = Math.ceil(file.size / chunkSize);
                currentChunk = 0;
                uploadedBytes = 0;
                paused = false;
                uploading = true;
                startTime = performance.now();

                MyZkToast.info("🚀 Upload started...");
                progressText.textContent = `🚀 Uploading ${file.name}...`;
                setButtonState("uploading");
                uploadNextChunk();
            });

            // === PAUSE ===
            pauseBtn.addEventListener("click", () => {
                if (!uploading) return;
                paused = true;
                uploading = false;
                progressText.textContent = "⏸️ Upload paused.";
                MyZkToast.warning("Upload paused.");
                setButtonState("paused");
            });

            // === RESUME ===
            resumeBtn.addEventListener("click", () => {
                if (!file) return;
                paused = false;
                uploading = true;
                progressText.textContent = "▶️ Upload resumed...";
                MyZkToast.info("Upload resumed...");
                setButtonState("uploading");
                uploadNextChunk();
            });

            // === UPLOAD CHUNK FUNCTION ===
            async function uploadNextChunk(retryCount = 0) {
                if (paused || !file) return;

                if (currentChunk >= totalChunks) {
                    progressText.textContent = "🧩 Merging file on server...";
                    return mergeChunks();
                }

                const start = currentChunk * chunkSize;
                const end = Math.min(file.size, start + chunkSize);
                const chunk = file.slice(start, end);
                const chunkSizeBytes = end - start;

                const formData = new FormData();
                formData.append("upload_id", uploadId);
                formData.append("chunk_index", currentChunk);
                formData.append("chunk", chunk);

                try {
                    const res = await fetch('{{ route('upload.chunk') }}', {
                        method: "POST",
                        headers: {
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        body: formData,
                    });

                    const data = await res.json();
                    if (!res.ok || !data.success) {
                        throw new Error(data.message || `Chunk ${currentChunk} failed.`);
                    }

                    currentChunk++;
                    uploadedBytes += chunkSizeBytes;

                    const now = performance.now();
                    const elapsedSec = (now - startTime) / 1000;
                    const speedMBps = (uploadedBytes / 1024 / 1024 / elapsedSec).toFixed(2);
                    const remainingBytes = file.size - uploadedBytes;
                    const estRemainingSec = remainingBytes / (speedMBps * 1024 * 1024);
                    const etaText = estRemainingSec > 0 ? formatTimeETA(estRemainingSec) : "-";

                    const progress = Math.round((currentChunk / totalChunks) * 100);
                    progressBar.style.width = `${progress}%`;
                    progressText.textContent = `Uploading... ${progress}% | 🚀 ${speedMBps} MB/s | ⏳ ETA: ${etaText}`;

                    if (progress === 100) {
                        MyZkToast.info("Merging file on server...");
                    }

                    if (!paused) uploadNextChunk();

                } catch (err) {
                    if (retryCount < 3) {
                        setTimeout(() => uploadNextChunk(retryCount + 1), 2000 * (retryCount + 1));
                    } else {
                        progressText.textContent = `❌ Chunk ${currentChunk} failed after 3 retries. Upload paused.`;
                        MyZkToast.error(`Chunk ${currentChunk} failed after 3 retries.`);
                        paused = true;
                        uploading = false;
                        setButtonState("paused");
                    }
                }
            }

            // === MERGE CHUNKS FUNCTION ===
            async function mergeChunks() {
                setButtonState("merging");

                const sourceType = sourceInput.value;
                const formData = new FormData();
                formData.append("upload_id", uploadId);
                formData.append("filename", file.name);
                formData.append("total_chunks", totalChunks);
                formData.append("source_type", sourceType); // Add source type to form data

                try {
                    const res = await fetch('{{ route('upload.merge') }}', {
                        method: "POST",
                        headers: {
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        body: formData,
                    });

                    const result = await res.json();

                    if (res.ok && result.success) {
                        progressBar.style.width = "100%";
                        progressText.textContent = `✅ Upload complete! ${result.message || "Processing started in background."} Redirecting...`;
                        MyZkToast.success(`${result.message || "Processing started in background."} Redirecting...`);
                        setButtonState("done");
                        afterFinish();
                    } else {
                        throw new Error(result.message || "Failed to merge file on server.");
                    }

                } catch (err) {
                    progressText.textContent = `❌ Error: ${err.message}`;
                    MyZkToast.error(err.message || "Server error during merge.");
                    setButtonState("error");
                    afterFinish();
                }
            }

            // === RESET ON FINISH ===
            function afterFinish() {
                setTimeout(() => {
                    window.location.href = "{{ route('admin.imagery.index') }}";
                }, 3000);
            }

            // === HELPER FUNCTIONS ===
            function setButtonState(state) {
                switch (state) {
                    case "idle":
                        startBtn.disabled = true;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                        break;
                    case "ready": // file sudah dipilih
                        startBtn.disabled = false;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                        break;
                    case "uploading":
                        startBtn.disabled = true;
                        pauseBtn.disabled = false;
                        resumeBtn.disabled = true;
                        break;
                    case "paused":
                        startBtn.disabled = true;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = false;
                        break;
                    case "merging":
                        startBtn.disabled = true;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                        break;
                    case "done":
                        startBtn.disabled = true;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                        break;
                    case "error":
                        startBtn.disabled = false;
                        pauseBtn.disabled = true;
                        resumeBtn.disabled = true;
                        break;
                }
            }

            // === PREVENT NAVIGATION DURING UPLOAD ===
            let isUploadInProgress = false;

            // Update the upload state when starting/pausing/resuming
            function setUploadState(state) {
                isUploadInProgress = state;
            }

            // Override the setButtonState function to also update upload state
            const originalSetButtonState = setButtonState;
            setButtonState = function(state) {
                // Set upload state based on button state
                if (state === "uploading" || state === "merging") {
                    setUploadState(true);
                } else if (state === "done" || state === "error" || state === "idle") {
                    setUploadState(false);
                }
                originalSetButtonState(state);
            };

            // Add beforeunload event listener
            window.addEventListener('beforeunload', function(e) {
                if (isUploadInProgress) {
                    // For modern browsers
                    e.preventDefault();
                    // For older browsers
                    e.returnValue = '';
                    return 'Upload is in progress. Are you sure you want to leave?';
                }
            });
        </script>
    @endpush

</x-app-layout>
