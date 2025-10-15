<?php

namespace App\Http\Controllers;

use App\Models\FieldArea;
use App\Models\ImageryData;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Jobs\MergeImageryChunksJob;
use App\Jobs\ProcessImageryJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\OrderImageryConfirmation;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class ImageryDataController extends Controller
{
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

    public function imageryOrder(Request $request)
    {
        $request->validate([
            'geometry' => 'required',
            'name_feature' => 'required|string|max:255',
            'area_hectares' => 'required|numeric|min:0.01',
        ]);

        $timestamp = time();

        // Calculate credit cost: using global constant credits per hectare
        $creditCost = round(($request->area_hectares / 10000) * config('app-constants.imagery_credit_cost_per_hectare'), 2);

        $data = [
            'timestamp' => $timestamp,
            'order_id' => $request->id,
            'name_feature' => $request->name_feature,
            'geometry' => $request->geometry,
            'area_hectares' => round(($request->area_hectares / 10000), 4),
            'credit_cost' => $creditCost,
            'area_hectares_actual' => $request->area_hectares,
        ];

        $keyCache = 'Checkout_' . $timestamp . '_' . Str::random(10) . '';
        Cache::put($keyCache, $data, now()->addHours(2));

        return redirect()->to('/imagery-checkout?id=' . $keyCache);  // method checkout
    }

    public function imageryCheckout(Request $request)
    {
        $id = $request->id;

        if ($id) {
            $cacheData = Cache::get($id);
            if ($cacheData) {
                $data = $cacheData;
            } else {

                return redirect()->route('appMap')->with('error', 'Application data not found or has expired.');
            }
        } else {
            return redirect()->route('appMap')->with('error', 'Application data not found');
        }

        $data['title'] = 'Checkout';

        return view('pages.front.order.checkoutImagery', compact('data'));
    }

    /**
     * Handle imagery checkout using credit points
     */
    public function processCheckoutImagery(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
            'name_feature' => 'required|string|max:255',
            'geometry' => 'required',
            'area_hectares' => 'required|numeric|min:0.01',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => ['required', 'string', 'max:20', 'regex:/^[\+]?[0-9\s\-\(\)]{7,20}$/'],
        ]);

        $cacheData = Cache::get($request->order_id);
        if (!$cacheData) {
            return redirect()->route('appMap')->with('error', 'Checkout failed, data not found or expired.');
        }

        $user = Auth::user();

        // Check if user has enough credit points
        $userCredit = $user->credits;
        $currentCredits = $userCredit ? $userCredit->credits : 0;

        if ($currentCredits < $cacheData['credit_cost']) {
            Log::warning('ImageryDataController@processCheckoutImagery: Insufficient credit points', [
                'user_id' => Auth::id(),
                'required_credits' => $cacheData['credit_cost'],
                'available_credits' => $currentCredits
            ]);
            return redirect()->back()->with('error', 'Insufficient credit points. You need ' . $cacheData['credit_cost'] . ' credits but you only have ' . $currentCredits . ' credits.');
        }

        try {
            // Deduct credit points from user
            $userCredit->credits -= $cacheData['credit_cost'];
            $userCredit->save();

            Log::info('ImageryDataController@processCheckoutImagery: Credits deducted successfully', [
                'user_id' => Auth::id(),
                'deducted_credits' => $cacheData['credit_cost'],
                'remaining_credits' => $userCredit->credits
            ]);

            // Create field area data record
            $fieldArea = FieldArea::create([
                'user_id' => $user->id,
                'name' => $cacheData['name_feature'],
                'geom' => $cacheData['geometry'],
                'area_ha' => $cacheData['area_hectares'],
            ]);

            // Clear cache
            Cache::forget($request->order_id);

            try {
                $bodyMail = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'order_id' => $fieldArea->id,
                    'name_feature' => $cacheData['name_feature'],
                    'geometry' => $cacheData['geometry'],
                    'area_hectares' => $cacheData['area_hectares'],
                    'credit_cost' => $cacheData['credit_cost'],
                ];
                Mail::to($user->email)->send(new OrderImageryConfirmation($bodyMail));
            } catch (\Exception $e) {
                Log::error("Failed to send imagery confirmation email: " . $e->getMessage());
                // Continue with the process even if email fails
            }

            return redirect()->route('appMap')->with('success', 'Your imagery order has been successfully placed using ' . $cacheData['credit_cost'] . ' credit points.');
        } catch (\Exception $e) {
            // Restore user credits if something went wrong
            if (isset($userCredit)) {
                $userCredit->credits += $cacheData['credit_cost'];
                $userCredit->save();
            }

            Log::error("Imagery checkout error: " . $e->getMessage(), ['user_id' => Auth::id(), 'exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', 'An error occurred during checkout. Please try again.');
        }
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
                    'chunk_id',
                    'chunk_total',
                    'upload_status',
                    'processing_status',
                    'uploaded_at',
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Imagery data fetched successfully.',
                'data' => $uploads,
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
            $chunkDir = storage_path("app/tmp_uploads/{$uploadId}");

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

            $chunkDir = storage_path("app/tmp_uploads/{$uploadId}");
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

                    if ($userCredit->credits < $requiredCredits) {
                        Log::warning('ImageryDataController@mergeChunks: Insufficient credit points during transaction', [
                            'user_id' => $user->id,
                            'required_credits' => $requiredCredits,
                            'available_credits' => $userCredit->credits
                        ]);
                        throw new \Exception('Insufficient credit points.');
                    }

                    $userCredit->credits -= $requiredCredits;
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
            $filePath = storage_path('app/public/' . str_replace('storage/', '', $imagery->path));
            if (!File::exists($filePath)) {
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
            $filePath = storage_path('app/public/' . str_replace('storage/', '', $imagery->processed_path));
            if (!File::exists($filePath)) {
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

            $requiredCredits = config('app-constants.imagery_processing_cost', 10);

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
                if ($lockedUserCredit->credits < $requiredCredits) {
                    throw new \Exception('Insufficient credit points. You need ' . $requiredCredits . ' credits but you only have ' . $lockedUserCredit->credits . ' credits.');
                }

                // Deduct credits
                $lockedUserCredit->credits -= $requiredCredits;
                $lockedUserCredit->save();

                // Update imagery status to waiting
                $imagery->update([
                    'processing_status' => 'waiting',
                    'scheduled_deletion_at' => now()->addDays(7)
                ]);

                Log::info("Retrying processing for imagery {$imagery->id}. Credits deducted: {$requiredCredits}");

                // Dispatch processing job
                ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');
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

            $chunkDir = storage_path('app/tmp_uploads/' . $imagery->chunk_id);
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
            // Delete the original physical file from storage
            if (file_exists($imagery->path)) {
                unlink($imagery->path);
            }

            // Delete processed imagery files if they exist
            if (file_exists($imagery->processed_path)) {
                unlink($imagery->processed_path);
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
}
