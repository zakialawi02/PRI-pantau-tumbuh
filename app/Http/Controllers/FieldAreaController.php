<?php

namespace App\Http\Controllers;

use App\Models\FieldArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class FieldAreaController extends Controller
{
    /**
     * Display a listing of the field areas.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data = [
            'title' => in_array(Auth::user()->role, ['superadmin', 'admin']) ? __('Field Area Management') : __('My Field Areas'),
        ];

        $user = Auth::user();

        // Build the query based on user role
        if (in_array($user->role, ['superadmin', 'admin'])) {
            // Admin can see all field areas
            $data['query'] = FieldArea::with(['user', 'subscriptions'])->get();
        } else {
            // Regular users can only see their own field areas
            $data['query'] = FieldArea::with(['user', 'subscriptions'])->where('user_id', $user->id)->get();
        }

        return view('pages.dashboard.fieldArea.index', compact('data'));
    }

    /**
     * Display the specified field area.
     *
     * @param  \App\Models\FieldArea  $fieldArea
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(FieldArea $fieldArea)
    {
        try {
            $user = Auth::user();

            // Build the query based on user role
            if (in_array($user->role, ['superadmin', 'admin'])) {
                // Admin can see all field areas
                $fieldArea->load(['user', 'subscriptions' => function ($query) {
                    $query->withTrashed();
                }]);
            } else {
                // Regular users can only see their own field areas
                if ($fieldArea->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access to this field area.'
                    ], 403);
                }

                $fieldArea->load(['user', 'subscriptions' => function ($query) {
                    $query->withTrashed();
                }]);
            }

            return response()->json([
                'success' => true,
                'data' => $fieldArea
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Field area not found.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching field area details.'
            ], 500);
        }
    }
}
