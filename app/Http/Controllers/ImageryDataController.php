<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\FieldArea;
use App\Models\ImageryData;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Jobs\ProcessImageryJob;
use App\Services\CreditService;
use App\Services\GeoServerService;
use Illuminate\Support\Facades\DB;
use App\Jobs\MergeImageryChunksJob;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessSentinelClipJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use App\Jobs\ProcessSentinelSceneJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ImageryDataController extends Controller
{
    protected GeoServerService $geoServerService;
    protected CreditService $creditService;

    public function __construct(GeoServerService $geoServerService, CreditService $creditService)
    {
        $this->geoServerService = $geoServerService;
        $this->creditService = $creditService;
    }

    public function index(Request $request)
    {
        $data = [
            'title' => in_array(Auth::user()->role, ['superadmin', 'admin']) ? __('Imagery Management') : __('My Imagery'),
        ];

        if ($request->ajax()) {
            $user = Auth::user();
            $user->loadMissing('credits');

            // Build the query based on user role
            if (in_array($user->role, ['superadmin', 'admin'])) {
                // Admin can see all imagery uploads
                $query = ImageryData::with(['user']);
            } else {
                // Regular users can only see their own imagery uploads
                $query = ImageryData::with(['user'])->where('user_id', $user->id);
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('action', function ($data) {
                    $actions = '<div class="flex flex-col gap-1">';
                    // Show retry button only if processing_status is 'error'
                    if (in_array($data->processing_status, ['skip', 'canceled', 'error'])) {
                        $actions .= '<button class="btn-retry-imagery bg-primary hover:bg-primary/80 inline-flex w-fit items-center rounded-full px-2 py-1 text-xs font-medium" data-id="' . $data->id . '" type="button" title="Retry Processing">';
                        $actions .= '<i class="ri-repeat-2-line mr-1"></i> Retry Processing';
                        $actions .= '</button>';
                    }
                    if (in_array($data->upload_status, ['pending']) && !empty($data->chunk_id)) {
                        $actions .= '<button class="btn-retry-merge bg-primary hover:bg-primary/80 inline-flex w-fit items-center rounded-full px-2 py-1 text-xs font-medium" data-id="' . $data->id . '" type="button" title="Retry Merge">';
                        $actions .= '<i class="ri-refresh-line mr-1"></i> Retry Merge Upload';
                        $actions .= '</button>';
                    }
                    // Download Source button - only show if upload_status is 'done'
                    if ($data->upload_status === 'done') {
                        $actions .= '<button class="btn-download-source bg-secondary hover:bg-secondary/80 inline-flex w-fit items-center rounded-full px-2 py-1 text-xs font-medium" data-id="' . $data->id . '" type="button" title="Download Source">';
                        $actions .= '<i class="ri-download-2-line mr-1"></i> Download Source';
                        $actions .= '</button>';
                    }
                    // Download Result button - only show if processing is completed
                    if ($data->processing_status === 'completed' && !empty($data->processed_path)) {
                        $actions .= '<button class="btn-download-result bg-secondary hover:bg-secondary/80 inline-flex w-fit items-center rounded-full px-2 py-1 text-xs font-medium" data-id="' . $data->id . '" type="button" title="Download Result">';
                        $actions .= '<i class="ri-download-2-line mr-1"></i> Download Result';
                        $actions .= '</button>';
                    }
                    // Delete button
                    $actions .= '<button class="btn-delete-imagery bg-error hover:bg-error/80 inline-flex w-fit items-center rounded-full px-2 py-1 text-xs font-medium" data-id="' . $data->id . '" type="button" title="Delete Imagery">';
                    $actions .= '<i class="ri-delete-bin-line mr-1"></i> Delete';
                    $actions .= '</button>';
                    $actions .= '</div>';

                    return $actions;
                })
                ->editColumn('size', function ($data) {
                    return number_format($data->size / 1000, 2) . ' KB';
                })
                ->editColumn('processing_status', function ($data) {
                    if ($data->processing_status === 'completed') {
                        return $data->processing_status . '<br>' . ($data->processed_at ? $data->processed_at->isoFormat('LL, HH:mm') : 'N/A');
                    } else {
                        return $data->processing_status ?? 'N/A';
                    }
                })
                ->editColumn('created_at', function ($data) {
                    return $data->created_at ? $data->created_at->isoFormat('LL, HH:mm') : 'N/A';
                })
                ->editColumn('updated_at', function ($data) {
                    return $data->updated_at ? $data->updated_at->isoFormat('LL, HH:mm') : 'N/A';
                })
                ->editColumn('user_name', function ($data) {
                    return $data->user->name ?? 'Unknown User';
                })
                ->rawColumns(['action', 'processing_status'])
                ->make(true);
        }

        return view('pages.dashboard.imagery.index', compact('data'));
    }

    public function create()
    {
        $data = [
            'title' => __('Upload Imagery'),
        ];

        return view('pages.dashboard.imagery.add', compact('data'));
    }

    public function listUserImagery()
    {
        try {
            $user = Auth::user();
            $uploads = ImageryData::where('user_id', $user->id)
                ->orderByDesc('uploaded_at')
                ->get([
                    'id',
                    'original_name',
                    'stored_name',
                    'size',
                    'format',
                    'path',
                    'processed_path',
                    'processed_at',
                    'geoserver_store_name',
                    'geoserver_layer_name',
                    'processed_geoserver_store_name',
                    'processed_geoserver_layer_name',
                    'chunk_id',
                    'chunk_total',
                    'upload_status',
                    'processing_status',
                    'uploaded_at',
                ]);

            $workspace = config('geoserver.workspace', '');
            $wmsUrl = rtrim(config('geoserver.wms_url', ''), '/');

            $geoServerService = $this->geoServerService;

            $data = $uploads->map(function (ImageryData $upload) use ($workspace, $wmsUrl, $geoServerService) {
                $sourceLayer = null;
                if (!empty($upload->geoserver_layer_name)) {
                    $sourceBounds = $upload->geoserver_bounds;

                    if (!$sourceBounds && $upload->geoserver_store_name) {
                        try {
                            $sourceBounds = $geoServerService->getCoverageBounds(
                                $upload->geoserver_store_name,
                                $upload->geoserver_layer_name
                            );

                            if ($sourceBounds) {
                                $upload->forceFill(['geoserver_bounds' => $sourceBounds])->save();
                            }
                        } catch (\Throwable $exception) {
                            Log::debug('ImageryDataController: Unable to resolve GeoServer bounds for source imagery.', [
                                'imagery_id' => $upload->id,
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    }

                    $sourceLayer = [
                        'store' => $upload->geoserver_store_name,
                        'layer' => $workspace ? $workspace . ':' . $upload->geoserver_layer_name : $upload->geoserver_layer_name,
                        'name' => $upload->geoserver_layer_name,
                        'wms_url' => $wmsUrl,
                        'bounds' => $sourceBounds,
                    ];
                }

                $processedLayer = null;
                if (!empty($upload->processed_geoserver_layer_name)) {
                    $processedBounds = $upload->processed_geoserver_bounds;

                    if (!$processedBounds && $upload->processed_geoserver_store_name) {
                        try {
                            $processedBounds = $geoServerService->getCoverageBounds(
                                $upload->processed_geoserver_store_name,
                                $upload->processed_geoserver_layer_name
                            );

                            if ($processedBounds) {
                                $upload->forceFill(['processed_geoserver_bounds' => $processedBounds])->save();
                            }
                        } catch (\Throwable $exception) {
                            Log::debug('ImageryDataController: Unable to resolve GeoServer bounds for processed imagery.', [
                                'imagery_id' => $upload->id,
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    }

                    $processedLayer = [
                        'store' => $upload->processed_geoserver_store_name,
                        'layer' => $workspace ? $workspace . ':' . $upload->processed_geoserver_layer_name : $upload->processed_geoserver_layer_name,
                        'name' => $upload->processed_geoserver_layer_name,
                        'wms_url' => $wmsUrl,
                        'bounds' => $processedBounds,
                    ];
                }

                return [
                    'id' => $upload->id,
                    'original_name' => $upload->original_name,
                    'stored_name' => $upload->stored_name,
                    'size' => $upload->size,
                    'format' => $upload->format,
                    'path' => $upload->path,
                    'processed_path' => $upload->processed_path,
                    'processed_at' => optional($upload->processed_at)->toIso8601String(),
                    'chunk_id' => $upload->chunk_id,
                    'chunk_total' => $upload->chunk_total,
                    'upload_status' => $upload->upload_status,
                    'processing_status' => $upload->processing_status,
                    'uploaded_at' => optional($upload->uploaded_at)->toIso8601String(),
                    'geoserver' => [
                        'workspace' => $workspace,
                        'wms_url' => $wmsUrl,
                        'source' => $sourceLayer,
                        'processed' => $processedLayer,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Imagery data fetched successfully.',
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            Log::error('ImageryDataController@listUserImagery: Failed to fetch imagery data', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch imagery data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkProgress(Request $request)
    {
        try {
            $user = Auth::user();

            // Get all imagery uploads for the user
            $uploads = ImageryData::where('user_id', $user->id)
                ->orderByDesc('uploaded_at')
                ->get(['id', 'processing_status', 'processed_path', 'processed_at']);

            return response()->json([
                'success' => true,
                'message' => 'Progress data fetched successfully.',
                'data' => $uploads,
            ]);
        } catch (\Throwable $e) {
            Log::error('ImageryDataController@checkProgress: Failed to fetch progress data', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch progress data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function uploadChunk(Request $request)
    {
        try {
            $validated = $request->validate([
                'upload_id' => 'required|string',
                'chunk_index' => 'required|integer|min:0',
                'chunk' => 'required|file|max:51200', // 50MB
            ]);

            $uploadId = $validated['upload_id'];
            $chunkIndex = $validated['chunk_index'];
            $chunkDir = storage_path("app/tmp/uploads/{$uploadId}");

            if (!File::exists($chunkDir)) {
                File::makeDirectory($chunkDir, 0777, true);
            }

            $file = $request->file('chunk');
            $saved = $file->move($chunkDir, "chunk_{$chunkIndex}");
            if (!$saved) {
                Log::error('ImageryDataController@uploadChunk: Failed to save chunk to temporary directory', [
                    'chunk_dir' => $chunkDir,
                    'chunk_index' => $chunkIndex
                ]);
                throw new \Exception("Failed to save chunk to temporary directory.");
            }

            return response()->json([
                'success' => true,
                'message' => "Chunk {$chunkIndex} uploaded successfully.",
                'data' => [
                    'upload_id' => $uploadId,
                    'chunk_index' => $chunkIndex,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('ImageryDataController@uploadChunk: Validation failed', [
                'user_id' => Auth::id(),
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('ImageryDataController@uploadChunk: Chunk upload failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Chunk upload failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function mergeChunks(Request $request)
    {
        try {
            Log::info('ImageryDataController@mergeChunks: Starting merge chunks process', [
                'user_id' => Auth::id(),
                'request_data' => $request->only(['upload_id', 'filename', 'total_chunks', 'source_type'])
            ]);

            $validated = $request->validate([
                'upload_id' => 'required|string',
                'filename' => 'required|string',
                'total_chunks' => 'required|integer|min:1',
                'source_type' => 'required|string|in:sentinel-2,landsat,quicksat',
            ]);

            $user = Auth::user();
            $uploadId = $validated['upload_id'];
            $filename = $validated['filename'];
            $sourceType = $validated['source_type'];
            $totalChunks = (int) $validated['total_chunks'];

            $chunkDir = storage_path("app/tmp/uploads/{$uploadId}");
            if (!File::isDirectory($chunkDir)) {
                Log::warning('ImageryDataController@mergeChunks: Chunk directory not found', [
                    'user_id' => Auth::id(),
                    'chunk_dir' => $chunkDir
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Chunk directory not found. Please restart the upload.',
                ], 404);
            }

            $skipProcessing = filter_var($request->input('skip_processing'), FILTER_VALIDATE_BOOLEAN);

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $allowed = ['tif', 'tiff', 'ecw', 'jp2', 'zip'];

            if (!in_array($ext, $allowed)) {
                Log::warning('ImageryDataController@mergeChunks: Invalid file format', [
                    'user_id' => Auth::id(),
                    'file_extension' => $ext
                ]);

                return response()->json(['success' => false, 'message' => 'Invalid file format.'], 422);
            }

            $timestamp = now()->format('YmdHis');
            $randomStr = Str::upper(Str::random(8));
            $cleanOriginal = Str::slug(pathinfo($filename, PATHINFO_FILENAME));
            $storedName = "{$timestamp}_{$randomStr}_{$cleanOriginal}.{$ext}";
            $finalPath = storage_path("app/public/imagery/{$storedName}");

            $calculatedSize = 0;
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFile = $chunkDir . DIRECTORY_SEPARATOR . "chunk_{$i}";

                if (!File::exists($chunkFile)) {
                    Log::warning('ImageryDataController@mergeChunks: Chunk missing', [
                        'user_id' => Auth::id(),
                        'chunk_index' => $i,
                        'chunk_file' => $chunkFile
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "Chunk {$i} missing. Please retry the upload.",
                    ], 422);
                }

                $size = File::size($chunkFile);
                if ($size === false) {
                    Log::error('ImageryDataController@mergeChunks: Unable to read chunk size', [
                        'user_id' => Auth::id(),
                        'chunk_index' => $i,
                        'chunk_file' => $chunkFile
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => "Unable to read chunk {$i} size.",
                    ], 500);
                }

                $calculatedSize += $size;
            }

            $requiredCredits = config('app-constants.imagery_processing_cost', 10);

            if (!$skipProcessing) {
                $userCredit = $user->credits;
                if (!$userCredit || $userCredit->credits < $requiredCredits) {
                    $skipProcessing = true;
                    Log::info('ImageryDataController@mergeChunks: Insufficient credits, skipping processing', [
                        'user_id' => Auth::id(),
                        'required_credits' => $requiredCredits,
                        'available_credits' => optional($userCredit)->credits
                    ]);
                }
            }

            $processingStatus = $skipProcessing ? 'skip' : 'waiting';

            $imagery = null;
            $currentCredits = optional($user->credits)->credits ?? 0;

            if ($skipProcessing) {
                $imagery = ImageryData::create([
                    'user_id' => $user->id,
                    'source_type' => $sourceType,
                    'original_name' => $filename,
                    'stored_name' => $storedName,
                    'size' => $calculatedSize,
                    'format' => $ext,
                    'path' => "storage/imagery/{$storedName}",
                    'chunk_id' => $uploadId,
                    'chunk_total' => $totalChunks,
                    'upload_status' => 'merging',
                    'processing_status' => $processingStatus,
                    'uploaded_at' => now(),
                ]);
            } else {
                DB::transaction(function () use (&$imagery, &$currentCredits, $user, $sourceType, $filename, $storedName, $calculatedSize, $ext, $processingStatus, $requiredCredits, $uploadId, $totalChunks) {
                    $userCredit = $user->credits()->lockForUpdate()->first();

                    if (!$userCredit) {
                        Log::error('ImageryDataController@mergeChunks: User credit record not found', [
                            'user_id' => $user->id
                        ]);
                        throw new \Exception('User credit record not found.');
                    }

                    $balanceBefore = (float) $userCredit->credits;

                    if ($balanceBefore < $requiredCredits) {
                        Log::warning('ImageryDataController@mergeChunks: Insufficient credit points during transaction', [
                            'user_id' => $user->id,
                            'required_credits' => $requiredCredits,
                            'available_credits' => $balanceBefore
                        ]);
                        throw new \Exception('Insufficient credit points.');
                    }

                    $userCredit->credits = $balanceBefore - $requiredCredits;
                    $userCredit->save();

                    $imagery = ImageryData::create([
                        'user_id' => $user->id,
                        'source_type' => $sourceType,
                        'original_name' => $filename,
                        'stored_name' => $storedName,
                        'size' => $calculatedSize,
                        'format' => $ext,
                        'path' => "storage/imagery/{$storedName}",
                        'chunk_id' => $uploadId,
                        'chunk_total' => $totalChunks,
                        'upload_status' => 'merging',
                        'processing_status' => $processingStatus,
                        'uploaded_at' => now(),
                    ]);

                    ## TODO: use deductCreditsForProcessing method
                    $this->creditService->logHistory(
                        $user,
                        'debit',
                        $requiredCredits,
                        $balanceBefore,
                        (float) $userCredit->credits,
                        __('Credits deducted for imagery upload'),
                        [
                            'context' => 'imagery_merge',
                            'upload_id' => $uploadId,
                            'source_type' => $sourceType,
                            'filename' => $filename,
                        ],
                        Auth::id(),
                        'imagery_upload',
                        (string) $imagery->id
                    );

                    $currentCredits = (float) $userCredit->credits;
                });

                $user->refresh();
                $currentCredits = optional($user->credits)->credits ?? $currentCredits;
            }

            MergeImageryChunksJob::dispatch(
                $imagery->id,
                $chunkDir,
                $finalPath,
                $totalChunks,
                $skipProcessing,
                $storedName
            )->onQueue('download');

            $message = $skipProcessing
                ? 'Upload received. Processing skipped due to insufficient credits. File will be available after background merging.'
                : 'Upload received. We are merging your file in the background and will start processing shortly.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $imagery->id,
                    'path' => $imagery->path,
                    'processing_status' => $processingStatus,
                    'upload_status' => 'merging',
                    'chunk_id' => $imagery->chunk_id,
                    'chunk_total' => $imagery->chunk_total,
                    'currentCredits' => (float) $currentCredits,
                ],
            ], 202);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('ImageryDataController@mergeChunks: Validation failed', [
                'user_id' => Auth::id(),
                'errors' => $e->errors()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('ImageryDataController@mergeChunks: Failed to queue merge operation', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to queue merge operation.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            Log::error('ImageryDataController@mergeChunks: Unexpected error during merge preparation', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while preparing the merge.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadSource(ImageryData $imagery)
    {
        try {
            // Check if user has permission to download this imagery
            $user = Auth::user();
            if (!in_array($user->role, ['superadmin', 'admin']) && $imagery->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            // Check if the file exists
            $filePath = $this->resolveImageryAbsolutePath($imagery->path);
            if (!$filePath || !File::exists($filePath)) {
                Log::warning('ImageryDataController@downloadSource: Source file not found', [
                    'user_id' => Auth::id(),
                    'imagery_id' => $imagery->id,
                    'file_path' => $filePath
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'File not found.',
                ], 404);
            }

            // Return the file as a download
            return response()->download($filePath, $imagery->original_name);
        } catch (\Exception $e) {
            Log::error('ImageryDataController@downloadSource: Failed to download file', [
                'user_id' => Auth::id(),
                'imagery_id' => $imagery->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function downloadResult(ImageryData $imagery)
    {
        try {
            // Check if user has permission to download this imagery
            $user = Auth::user();
            if (!in_array($user->role, ['superadmin', 'admin']) && $imagery->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            // Check if processing is completed
            if ($imagery->processing_status !== 'completed' || empty($imagery->processed_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Processing not completed yet.',
                ], 400);
            }

            // Check if the processed file exists
            $filePath = $this->resolveImageryAbsolutePath($imagery->processed_path);
            if (!$filePath || !File::exists($filePath)) {
                Log::warning('ImageryDataController@downloadResult: Processed file not found', [
                    'user_id' => Auth::id(),
                    'imagery_id' => $imagery->id,
                    'file_path' => $filePath
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Processed file not found.',
                ], 404);
            }

            // Generate a meaningful filename for the processed file
            $originalName = pathinfo($imagery->original_name, PATHINFO_FILENAME);
            $extension = pathinfo($imagery->processed_path, PATHINFO_EXTENSION);
            $downloadName = $originalName . '_processed.' . $extension;

            // Return the file as a download
            return response()->download($filePath, $downloadName);
        } catch (\Exception $e) {
            Log::error('ImageryDataController@downloadResult: Failed to download processed file', [
                'user_id' => Auth::id(),
                'imagery_id' => $imagery->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download processed file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function retryProcessing(ImageryData $imagery)
    {
        try {
            // Check if user has sufficient credits for processing
            $user = Auth::user();
            $userCredit = $user->credits;
            $currentCredits = $userCredit ? $userCredit->credits : 0;

            $requiredCredits = config('app-constants.imagery_processing_cost', 100);

            if ($currentCredits < $requiredCredits) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient credit points. You need ' . $requiredCredits . ' credits but you only have ' . $currentCredits . ' credits. Please purchase more credits to continue processing.',
                ], 400);
            }

            // Use database transaction with locking to prevent race conditions
            DB::transaction(function () use ($imagery, $userCredit, $requiredCredits, $user) {
                // Lock the user credit record to prevent race conditions
                $lockedUserCredit = $user->credits()->lockForUpdate()->first();

                // If we couldn't lock the record, throw an exception
                if (!$lockedUserCredit) {
                    Log::error('ImageryDataController@retryProcessing: Unable to lock user credit record', [
                        'user_id' => $user->id,
                        'imagery_id' => $imagery->id
                    ]);
                    throw new \Exception('Unable to lock user credit record for update.');
                }

                // Double-check credits after locking
                $balanceBefore = (float) $lockedUserCredit->credits;

                if ($balanceBefore < $requiredCredits) {
                    throw new \Exception('Insufficient credit points. You need ' . $requiredCredits . ' credits but you only have ' . $balanceBefore . ' credits.');
                }

                // Deduct credits
                $lockedUserCredit->credits = $balanceBefore - $requiredCredits;
                $lockedUserCredit->save();

                ## TODO: use deductCreditsForProcessing method
                $this->creditService->logHistory(
                    $user,
                    'debit',
                    $requiredCredits,
                    $balanceBefore,
                    (float) $lockedUserCredit->credits,
                    __('Credits deducted to retry imagery processing'),
                    [
                        'context' => 'imagery_retry',
                        'imagery_id' => $imagery->id,
                    ],
                    Auth::id(),
                    'imagery_retry',
                    (string) $imagery->id
                );

                // Update imagery status to waiting
                $imagery->update([
                    'processing_status' => 'waiting',
                    'scheduled_deletion_at' => now()->addDays(7)
                ]);

                Log::info("Retrying processing for imagery {$imagery->id}. Credits deducted: {$requiredCredits}");

                // Dispatch processing job
                ProcessImageryJob::dispatch(
                    $imagery->id,
                    [
                        'required_credits' => $requiredCredits,
                    ]
                )->onQueue('processing');
            });

            return response()->json([
                'success' => true,
                'message' => 'Credits deducted successfully. Re-processing started, queued for background processing.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ImageryDataController@retryProcessing: Failed to retry processing', [
                'user_id' => Auth::id(),
                'imagery_id' => $imagery->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retry processing: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function retryMerge(ImageryData $imagery)
    {
        try {
            Log::info('ImageryDataController@retryMerge: Attempting to retry imagery merge', [
                'user_id' => Auth::id(),
                'imagery_id' => $imagery->id
            ]);

            if ($imagery->upload_status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Imagery is not awaiting merge.',
                ], 400);
            }

            if (empty($imagery->chunk_id) || empty($imagery->chunk_total)) {
                Log::warning('ImageryDataController@retryMerge: Chunk information is missing', [
                    'user_id' => Auth::id(),
                    'imagery_id' => $imagery->id,
                    'chunk_id' => $imagery->chunk_id,
                    'chunk_total' => $imagery->chunk_total
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Chunk information is missing. Please re-upload the imagery.',
                ], 400);
            }

            $chunkDir = storage_path('app/tmp/uploads/' . $imagery->chunk_id);
            if (!File::isDirectory($chunkDir)) {
                Log::warning('ImageryDataController@retryMerge: Chunk directory not found', [
                    'user_id' => Auth::id(),
                    'imagery_id' => $imagery->id,
                    'chunk_dir' => $chunkDir
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Chunk directory not found. Please re-upload the imagery.',
                ], 404);
            }

            $finalPath = storage_path('app/public/imagery/' . $imagery->stored_name);
            $skipProcessing = $imagery->processing_status === 'skip';

            $imagery->update([
                'upload_status' => 'merging',
                'processing_status' => $skipProcessing ? 'skip' : 'waiting',
            ]);

            MergeImageryChunksJob::dispatch(
                $imagery->id,
                $chunkDir,
                $finalPath,
                (int) $imagery->chunk_total,
                $skipProcessing,
                $imagery->stored_name
            )->onQueue('download');

            return response()->json([
                'success' => true,
                'message' => 'Merge job restarted successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to requeue imagery merge: ' . $e->getMessage(), [
                'imagery_id' => $imagery->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restart merge: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(ImageryData $imagery)
    {
        try {
            if ($imagery->geoserver_layer_name || $imagery->geoserver_store_name) {
                $this->geoServerService->removeImageryPublication(
                    $imagery->geoserver_layer_name,
                    $imagery->geoserver_store_name
                );
            }

            if ($imagery->processed_geoserver_layer_name || $imagery->processed_geoserver_store_name) {
                $this->geoServerService->removeImageryPublication(
                    $imagery->processed_geoserver_layer_name,
                    $imagery->processed_geoserver_store_name
                );
            }

            // Delete the original physical file from storage
            $sourcePath = $this->resolveImageryAbsolutePath($imagery->path);
            if ($sourcePath && File::exists($sourcePath)) {
                File::delete($sourcePath);
            }

            // Delete processed imagery files if they exist
            $processedPath = $this->resolveImageryAbsolutePath($imagery->processed_path);
            if ($processedPath && File::exists($processedPath)) {
                File::delete($processedPath);
            }

            // Delete the database record
            $imagery->delete();

            return response()->json([
                'success' => true,
                'message' => 'Imagery deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ImageryDataController@destroy: Failed to delete imagery', [
                'user_id' => Auth::id(),
                'imagery_id' => $imagery->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete imagery: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function processSceneSentinel2(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'download_url' => ['required', 'url'],
            'product_id' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['nullable', 'string', 'max:255'],
            'download_filename' => ['nullable', 'string', 'max:255'],
        ]);

        $requiredCredits = (float) config('app-constants.imagery_processing_cost', 10);
        $currentCredits = $this->creditService->getRemainingCredits($user->id);

        if ($requiredCredits > 0 && $currentCredits < $requiredCredits) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to process Sentinel imagery.',
                'data' => [
                    'current_credits' => $currentCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 402);
        }

        $rawTitle = trim($validated['title'] ?? '') ?: now()->format('YmdHis') . '_Sentinel_Scene';
        $displayTitle = $this->sanitizeDisplayName($rawTitle . '_' . now()->format('YmdHis'));
        $finalDisplayName = $displayTitle . '.tif';

        $disk = Storage::disk('public');
        $imageryDirectory = 'imagery';
        $sentinelDirectory = 'imagery/download/sentinel';

        $disk->makeDirectory($imageryDirectory);
        $disk->makeDirectory($sentinelDirectory);
        $zipFilename = $this->ensureUniqueFilename($sentinelDirectory, $displayTitle, 'zip');
        $outputBase = Str::limit($displayTitle . '_multispectral', 160, '');
        $outputFilename = $this->ensureUniqueFilename($imageryDirectory, $outputBase, 'tif');

        $deductedCredits = false;

        try {
            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2',
                'original_name' => $finalDisplayName,
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => 'zip',
                'path' => 'public/' . trim($imageryDirectory, '/') . '/' . $outputFilename,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            if ($requiredCredits > 0) {
                $deductedCredits = $this->creditService->deductCreditsForProcessing(
                    $user->id,
                    $requiredCredits,
                    'SentinelScene',
                    __('Credits deducted for Sentinel imagery processing'),
                    [
                        'context' => 'sentinel_scene',
                        'imagery_id' => $imagery->id,
                    ],
                    'imagery_processing',
                    (string) $imagery->id,
                    $user->id
                );

                if (!$deductedCredits) {
                    $currentCredits = $this->creditService->getRemainingCredits($user->id);
                    $imagery->delete();

                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient credits to process Sentinel imagery.',
                        'data' => [
                            'current_credits' => $currentCredits,
                            'required_credits' => $requiredCredits,
                        ],
                    ], 402);
                }
            }

            ProcessSentinelSceneJob::dispatch(
                $imagery->id,
                $validated['download_url'],
                $sentinelDirectory,
                $zipFilename,
                $outputFilename,
                $finalDisplayName,
                [
                    'product_id' => $validated['product_id'] ?? null,
                    'collection' => $validated['collection'] ?? null,
                    'acquisition_date' => $validated['acquisition_date'] ?? null,
                    'download_filename' => $validated['download_filename'] ?? null,
                    'required_credits' => $requiredCredits,
                ]
            )->onQueue('download');

            $remainingCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel scene queued for processing.',
                'data' => [
                    'id' => $imagery->id,
                    'upload_status' => $imagery->upload_status,
                    'processing_status' => $imagery->processing_status,
                    'current_credits' => $remainingCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('ImageryDataController@processSceneSentinel2 failed to queue processing.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if (isset($imagery)) {
                $imagery->update([
                    'upload_status' => 'failed',
                    'processing_status' => 'error',
                ]);
            }

            if ($deductedCredits && $requiredCredits > 0) {
                $this->creditService->addCreditsToUser(
                    $user->id,
                    $requiredCredits,
                    'ImageryDataController',
                    __('Credits refunded after Sentinel scene failure'),
                    [
                        'context' => 'sentinel_scene_refund',
                        'imagery_id' => isset($imagery) ? $imagery->id : null,
                    ],
                    'imagery_refund',
                    isset($imagery) ? (string) $imagery->id : null,
                    $user->id
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel imagery processing at this time.',
                'data' => [
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'required_credits' => $requiredCredits,
                ],
            ], 500);
        }
    }

    public function processClipSentinel2(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'field_name' => ['required', 'string', 'max:255'],
            'geojson' => ['required_without:field_area_id'],
            'geometry' => ['nullable'],
            'area_hectares' => ['required', 'numeric', 'min:0.01'],
            'estimated_credits' => ['nullable', 'numeric', 'min:0'],
            'field_area_id' => ['nullable', 'uuid', Rule::exists('field_areas', 'id')->where('user_id', $user->id)],
        ]);

        $geojsonPayload = $validated['geojson'] ?? null;
        $fieldArea = null;

        if (!empty($validated['field_area_id'])) {
            $fieldArea = FieldArea::where('id', $validated['field_area_id'])
                ->where('user_id', $user->id)
                ->first();

            if (!$fieldArea) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected field area could not be found.',
                ], 404);
            }

            $geojsonPayload = $fieldArea->geom ?? $geojsonPayload;
        }

        if (is_null($geojsonPayload)) {
            return response()->json([
                'success' => false,
                'message' => 'GeoJSON payload is required.',
            ], 422);
        }

        if (is_string($geojsonPayload)) {
            $decoded = json_decode($geojsonPayload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid GeoJSON payload.',
                ], 422);
            }
            $geojsonPayload = $decoded;
        }

        if (is_array($geojsonPayload) && ($geojsonPayload['type'] ?? '') === 'Feature') {
            $geojsonPayload = [
                'type' => 'FeatureCollection',
                'features' => [$geojsonPayload],
            ];
        }

        if (!is_array($geojsonPayload) || ($geojsonPayload['type'] ?? '') !== 'FeatureCollection' || empty($geojsonPayload['features'])) {
            return response()->json([
                'success' => false,
                'message' => 'GeoJSON payload must be a FeatureCollection with at least one feature.',
            ], 422);
        }

        $feature = $geojsonPayload['features'][0] ?? null;
        if (!is_array($feature) || empty($feature['geometry']) || !is_array($feature['geometry'])) {
            return response()->json([
                'success' => false,
                'message' => 'The provided GeoJSON feature is missing geometry data.',
            ], 422);
        }

        if (!isset($feature['properties']) || !is_array($feature['properties'])) {
            $feature['properties'] = [];
        }
        $geojsonPayload['features'][0] = $feature;

        $geometry = $validated['geometry'] ?? $feature['geometry'];
        if (!is_array($geometry)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid geometry provided.',
            ], 422);
        }

        $areaHa = (float) $validated['area_hectares'];
        $creditRate = (float) config('app-constants.imagery_credit_cost_per_hectare', 0);
        $requiredCredits = round($areaHa * $creditRate, 2);
        if ($requiredCredits < 0) {
            $requiredCredits = 0;
        }

        $currentCredits = $this->creditService->getRemainingCredits($user->id);
        if ($requiredCredits > 0 && $currentCredits < $requiredCredits) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to process clipped Sentinel imagery.',
                'data' => [
                    'current_credits' => $currentCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 402);
        }

        $rawFieldName = trim($validated['field_name']);
        $displayBase = $this->sanitizeDisplayName(($rawFieldName !== '' ? $rawFieldName : 'SentinelClip') . '_' . now()->format('YmdHis'));
        $outputFilename = $this->ensureUniqueFilename('imagery', $displayBase, 'tif');
        $storedName =  $outputFilename;
        $originalName = str_replace(' ', '_', ($rawFieldName !== '' ? $rawFieldName : pathinfo($outputFilename, PATHINFO_FILENAME))) . '_' . now()->format('YmdHis') . '.tif';

        $imagery = null;
        $deductedCredits = false;
        $shouldDeleteFieldAreaOnFailure = false;

        try {
            if (!$fieldArea) {
                $fieldArea = FieldArea::create([
                    'user_id' => $user->id,
                    'name' => $rawFieldName !== '' ? $rawFieldName : $displayBase,
                    'area_ha' => $areaHa,
                    'geom' => $geojsonPayload,
                ]);
                $shouldDeleteFieldAreaOnFailure = true;
            } else {
                $updates = [];
                if ($rawFieldName !== '' && $fieldArea->name !== $rawFieldName) {
                    $updates['name'] = $rawFieldName;
                }
                if ($areaHa > 0 && (float) $fieldArea->area_ha !== (float) $areaHa) {
                    $updates['area_ha'] = $areaHa;
                }

                if (!empty($updates)) {
                    $fieldArea->fill($updates);
                    $fieldArea->save();
                }
            }

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2',
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'size' => 0,
                'format' => 'tif',
                'path' => 'imagery/' . $storedName,
                'upload_status' => 'pending',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            if ($requiredCredits > 0) {
                $deductedCredits = $this->creditService->deductCreditsForProcessing(
                    $user->id,
                    $requiredCredits,
                    'SentinelClip',
                    __('Credits deducted for Sentinel clip processing'),
                    [
                        'context' => 'sentinel_clip',
                        'imagery_id' => $imagery->id,
                        'field_area_id' => $fieldArea->id,
                    ],
                    'imagery_processing',
                    (string) $imagery->id,
                    $user->id
                );

                if (!$deductedCredits) {
                    $currentCredits = $this->creditService->getRemainingCredits($user->id);
                    $imagery->delete();
                    if ($shouldDeleteFieldAreaOnFailure && $fieldArea) {
                        $fieldArea->delete();
                    }

                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient credits to process clipped Sentinel imagery.',
                        'data' => [
                            'current_credits' => $currentCredits,
                            'required_credits' => $requiredCredits,
                        ],
                    ], 402);
                }
            }

            ProcessSentinelClipJob::dispatch(
                $imagery->id,
                $fieldArea->id,
                [
                    'geometry' => $geometry,
                    'geojson' => $geojsonPayload,
                    'output_filename' => $outputFilename,
                    'field_name' => $rawFieldName,
                    'area_hectares' => $areaHa,
                    'required_credits' => $requiredCredits,
                ]
            )->onQueue('processing');

            $remainingCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel clip queued for processing.',
                'data' => [
                    'imagery_id' => $imagery->id,
                    'field_area_id' => $fieldArea->id,
                    'current_credits' => $remainingCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('ImageryDataController@processClip failed to queue processing.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if ($imagery) {
                $imagery->delete();
            }

            if ($shouldDeleteFieldAreaOnFailure && $fieldArea) {
                $fieldArea->delete();
            }

            if ($deductedCredits && $requiredCredits > 0) {
                $this->creditService->addCreditsToUser(
                    $user->id,
                    $requiredCredits,
                    'ImageryDataController',
                    __('Credits refunded after Sentinel clip failure'),
                    [
                        'context' => 'sentinel_clip_refund',
                        'imagery_id' => optional($imagery)->id,
                        'field_area_id' => optional($fieldArea)->id,
                    ],
                    'imagery_refund',
                    optional($imagery)->id ? (string) $imagery->id : null,
                    $user->id
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue clipped Sentinel imagery at this time.',
                'data' => [
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'required_credits' => $requiredCredits,
                ],
            ], 500);
        }
    }

    private function sanitizeDisplayName(string $value): string
    {
        $cleaned = str_replace([
            ' ',
            '\\',
            '/',
            ':',
            "\"",
            '*',
            '?',
            '<',
            '>',
            '|',
            'SAFE',
            '.'
        ], ' ', $value);

        $normalized = trim(preg_replace('/\s+/', '', $cleaned) ?? '');
        $fallback = $normalized !== '' ? $normalized : 'Sentinel_Scene';

        return Str::limit($fallback, 120, '');
    }


    private function ensureUniqueFilename(string $directory, string $baseName, string $extension): string
    {
        $disk = Storage::disk('public');
        $cleanDirectory = trim($directory, '/');
        $extension = ltrim($extension, '.');

        $candidateBase = $baseName !== '' ? $baseName : 'sentinel-scene';
        $filename = sprintf('%s.%s', $candidateBase, $extension);
        $counter = 1;

        $pathPrefix = $cleanDirectory === '' ? '' : $cleanDirectory . '/';

        while ($disk->exists($pathPrefix . $filename)) {
            $filename = sprintf('%s-%d.%s', $candidateBase, $counter, $extension);
            $counter++;
        }

        return $filename;
    }

    private function resolveImageryAbsolutePath(?string $relativePath): ?string
    {
        if (empty($relativePath)) {
            return null;
        }

        $normalized = ltrim($relativePath, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = 'public/' . Str::after($normalized, 'storage/');
        }

        return storage_path('app/' . $normalized);
    }
}
