<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AttendanceRecordController extends Controller
{
    /**
     * 勤怠一覧を取得する
     *
     * @param  IndexAttendanceRecordRequest  $request  検索条件を受け取るリクエスト
     * @return AnonymousResourceCollection 勤怠一覧のリソースコレクション
     */
    public function index(
        IndexAttendanceRecordRequest $request
    ): AnonymousResourceCollection {
        $query = Attendance::with('user')
            ->withSum(
                [
                    'attendanceBreaks as total_break_seconds' => function ($query) {
                        $query->whereNotNull('break_in')
                            ->whereNotNull('break_out');
                    },
                ],
                DB::raw(
                    'TIME_TO_SEC(TIMEDIFF(break_out, break_in))'
                )
            );

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('work_date', $request->input('date'));
        }

        if ($request->filled('month')) {
            $month = $request->input('month');

            $query->whereBetween('work_date', [
                $month.'-01',
                date('Y-m-t', strtotime($month.'-01')),
            ]);
        }

        $perPage = (int) $request->input('per_page', 20);

        $attendance_records = $query
            ->orderByDesc('work_date')
            ->paginate($perPage);

        return AttendanceRecordResource::collection($attendance_records);
    }

    /**
     * 勤怠詳細を取得する
     *
     * @param  Attendance  $attendanceRecord  取得対象の勤怠情報
     * @return AttendanceRecordResource 勤怠詳細のリソース
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
     *
     * @param  StoreAttendanceRecordRequest  $request  登録する勤怠情報を受け取るリクエスト
     * @return JsonResponse 登録した勤怠情報を含むJSONレスポンス
     */
    public function store(
        StoreAttendanceRecordRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $attendance = $request->user()->attendance_records()->create([
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
     *
     * @param  UpdateAttendanceRecordRequest  $request  更新する勤怠情報を受け取るリクエスト
     * @param  Attendance  $attendanceRecord  更新対象の勤怠情報
     * @return AttendanceRecordResource 更新した勤怠情報のリソース
     */
    public function update(
        UpdateAttendanceRecordRequest $request,
        Attendance $attendanceRecord
    ): AttendanceRecordResource {
        $this->authorize('update', $attendanceRecord);

        $validated = $request->validated();

        $updates = [];

        if (array_key_exists('date', $validated)) {
            $updates['work_date'] = $validated['date'];
        }

        if (array_key_exists('clock_in', $validated)) {
            $updates['clock_in'] = $validated['clock_in'];
        }

        if (array_key_exists('clock_out', $validated)) {
            $updates['clock_out'] = $validated['clock_out'];
        }

        if (array_key_exists('comment', $validated)) {
            $updates['comment'] = $validated['comment'];
        }

        $attendanceRecord->update($updates);

        $attendanceRecord->load([
            'user',
            'attendanceBreaks',
        ]);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 勤怠を削除する
     *
     * @param  Attendance  $attendanceRecord  削除対象の勤怠情報
     * @return Response コンテンツを含まないHTTPレスポンス
     */
    public function destroy(
        Attendance $attendanceRecord
    ): Response {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
