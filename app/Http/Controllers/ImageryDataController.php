<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ImageryData;
use App\Jobs\ProcessImageryJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ImageryDataController extends Controller
{
    public function index(Request $request)
    {
        $data = [
            'title' => in_array(Auth::user()->role, ['superadmin', 'admin']) ? __('Imagery Management') : __('My Imagery'),
        ];

        if ($request->ajax()) {
            $user = Auth::user();

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
                    if ($data->processing_status === 'error') {
                        $actions .= '<button class="btn-retry-imagery bg-primary hover:bg-primary/80 inline-flex w-fit items-center rounded-full px-2 py-1 text-xs font-medium" data-id="' . $data->id . '" type="button" title="Retry Processing">';
                        $actions .= '<i class="ri-repeat-2-line mr-1"></i> Retry Processing';
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
                ->get(['id', 'original_name', 'stored_name', 'size', 'format', 'path', 'processing_status', 'uploaded_at']);

            return response()->json([
                'success' => true,
                'message' => 'Imagery data fetched successfully.',
                'data' => $uploads,
            ]);
        } catch (\Throwable $e) {
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
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
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
            $validated = $request->validate([
                'upload_id' => 'required|string',
                'filename' => 'required|string',
                'total_chunks' => 'required|integer',
                'source_type' => 'required|string|in:sentinel-2,landsat,quicksat',
            ]);

            $user = Auth::user();
            $uploadId = $validated['upload_id'];
            $filename = $validated['filename'];
            $sourceType = $validated['source_type'];

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $allowed = ['tif', 'tiff', 'ecw', 'jp2', 'zip'];

            if (!in_array($ext, $allowed)) {
                return response()->json(['success' => false, 'message' => 'Invalid file format.'], 422);
            }

            // === Rename final file ===
            $timestamp = now()->format('YmdHis');
            $randomStr = Str::upper(Str::random(8));
            $cleanOriginal = Str::slug(pathinfo($filename, PATHINFO_FILENAME));
            $storedName = "{$timestamp}_{$randomStr}_{$cleanOriginal}.{$ext}";

            $chunkDir = storage_path("app/tmp_uploads/{$uploadId}");
            $finalPath = storage_path("app/public/citra/{$storedName}");

            if (!File::exists(dirname($finalPath))) {
                File::makeDirectory(dirname($finalPath), 0777, true);
            }

            // === Combine all chunks ===
            $output = fopen($finalPath, 'ab');
            if (!$output) {
                throw new \Exception("Failed to open final file for writing.");
            }

            for ($i = 0; $i < $validated['total_chunks']; $i++) {
                $chunkFile = "{$chunkDir}/chunk_{$i}";
                if (File::exists($chunkFile)) {
                    $chunkData = File::get($chunkFile);
                    if ($chunkData === false) {
                        fclose($output);
                        throw new \Exception("Failed to read chunk {$i}.");
                    }
                    fwrite($output, $chunkData);
                } else {
                    fclose($output);
                    return response()->json(['success' => false, 'message' => "Chunk {$i} missing."], 500);
                }
            }
            fclose($output);

            // Clean up chunk directory
            if (!File::deleteDirectory($chunkDir)) {
                Log::warning("Failed to delete chunk directory: {$chunkDir}");
            }

            // === Save to DB ===
            $fileSize = File::size($finalPath);
            if ($fileSize === false) {
                throw new \Exception("Failed to get file size.");
            }

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => $sourceType,
                'original_name' => $filename,
                'stored_name' => $storedName,
                'size' => $fileSize,
                'format' => $ext,
                'path' => "storage/citra/{$storedName}",
                'upload_status' => 'done',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            // === Dispatch background job for Python processing ===
            ProcessImageryJob::dispatch($imagery->id);

            return response()->json([
                'success' => true,
                'message' => 'Upload completed. Processing started in background.',
                'data' => [
                    'id' => $imagery->id,
                    'path' => "storage/citra/{$storedName}",
                    'processing_status' => 'waiting',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Clean up any partially created files
            if (isset($finalPath) && File::exists($finalPath)) {
                File::delete($finalPath);
            }

            // Clean up chunk directory if it still exists
            if (isset($chunkDir) && File::exists($chunkDir)) {
                File::deleteDirectory($chunkDir);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to merge chunks.',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Throwable $e) {
            // Clean up any partially created files
            if (isset($finalPath) && File::exists($finalPath)) {
                File::delete($finalPath);
            }

            // Clean up chunk directory if it still exists
            if (isset($chunkDir) && File::exists($chunkDir)) {
                File::deleteDirectory($chunkDir);
            }

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while merging chunks.',
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
                return response()->json([
                    'success' => false,
                    'message' => 'File not found.',
                ], 404);
            }

            // Return the file as a download
            return response()->download($filePath, $imagery->original_name);
        } catch (\Exception $e) {
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to download processed file: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function retryProcessing(ImageryData $imagery)
    {
        try {
            $imagery->update([
                'processing_status' => 'waiting',
            ]);

            Log::info("Retrying processing for imagery {$imagery->id}.");

            ProcessImageryJob::dispatch($imagery->id);

            return response()->json([
                'success' => true,
                'message' => 'Re-processing started, queued for background processing.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry processing: ' . $e->getMessage(),
                'error' => $e->getMessage(),
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete imagery: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
