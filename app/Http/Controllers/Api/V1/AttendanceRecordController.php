<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Resources\Api\V1\AttendanceRecordResource;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceRecordController extends Controller
{
    /**
     * 勤怠一覧を取得する
     */
    public function index(
        IndexAttendanceRecordRequest $request
    ): AnonymousResourceCollection {
        $query = Attendance::with('user', 'attendanceBreaks');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('work_date', $request->input('date'));
        }

        if ($request->filled('month')) {
            $month = $request->input('month');

            $query->whereBetween('work_date', [
                $month . '-01',
                date('Y-m-t', strtotime($month . '-01')),
            ]);
        }

        $perPage = (int) $request->input('per_page', 20);

        $attendances = $query
            ->orderByDesc('work_date')
            ->paginate($perPage);

        return AttendanceRecordResource::collection($attendances);
    }

    /**
     * 勤怠詳細を取得する
     */
    public function show(
        Attendance $attendanceRecord
    ): AttendanceRecordResource {
        $attendanceRecord->load([
            'user',
            'attendanceBreaks',
            'stampCorrectionRequests',
        ]);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 勤怠を新規登録する
     */
    public function store(
        StoreAttendanceRecordRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $attendance = $request->user()->attendances()->create([
            'work_date' => $validated['date'],
            'clock_in' => $validated['clock_in'],
            'clock_out' => $validated['clock_out'] ?? null,
            'comment' => $validated['comment'] ?? null,
        ]);

        $attendance->load([
            'user',
            'attendanceBreaks',
        ]);

        return (new AttendanceRecordResource($attendance))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 勤怠を更新する
     */
    public function update(
        UpdateAttendanceRecordRequest $request,
        Attendance $attendanceRecord
    ): AttendanceRecordResource {
        $this->authorize('update', $attendanceRecord);

        $validated = $request->validated();

        $attendanceRecord->update([
            'work_date' => $validated['date'],
            'clock_in' => $validated['clock_in'],
            'clock_out' => $validated['clock_out'] ?? null,
            'comment' => $validated['comment'] ?? null,
        ]);

        $attendanceRecord->load([
            'user',
            'attendanceBreaks',
        ]);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 勤怠を削除する
     */
    public function destroy(
        Attendance $attendanceRecord
    ): Response {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}