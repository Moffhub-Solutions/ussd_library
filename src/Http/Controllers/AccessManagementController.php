<?php

declare(strict_types=1);

namespace Moffhub\Ussd\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Moffhub\Ussd\Models\UssdAccessList;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessManagementController extends Controller
{
    /**
     * GET /access-list — List all entries (whitelist or blacklist), paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:whitelist,blacklist',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = UssdAccessList::query();

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $perPage = (int) $request->input('per_page', 15);

        return response()->json($query->orderByDesc('created_at')->paginate($perPage));
    }

    /**
     * POST /access-list — Add phone(s) to whitelist/blacklist.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:whitelist,blacklist',
            'phones' => 'required|array|min:1',
            'phones.*' => 'required|string',
            'reason' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type = $request->input('type');
        $reason = $request->input('reason');
        $expiresAt = $request->input('expires_at') ? new \DateTimeImmutable($request->input('expires_at')) : null;
        $entries = [];

        foreach ($request->input('phones') as $phone) {
            $entry = $type === 'whitelist'
                ? UssdAccessList::addToWhitelist($phone, $reason, 'api', $expiresAt)
                : UssdAccessList::addToBlacklist($phone, $reason, 'api', $expiresAt);

            $entries[] = $entry;
        }

        return response()->json([
            'message' => count($entries).' phone(s) added to '.$type,
            'data' => $entries,
        ], 201);
    }

    /**
     * DELETE /access-list/{id} — Remove a specific entry.
     */
    public function destroy(string $id): JsonResponse
    {
        $entry = UssdAccessList::find($id);

        if (! $entry) {
            return response()->json(['message' => 'Entry not found'], 404);
        }

        $entry->update(['is_active' => false]);

        return response()->json(['message' => 'Entry deactivated successfully']);
    }

    /**
     * POST /access-list/bulk — Bulk add phones.
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:whitelist,blacklist',
            'phones' => 'required|array|min:1',
            'phones.*' => 'required|string',
            'reason' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type = $request->input('type');
        $reason = $request->input('reason');
        $expiresAt = $request->input('expires_at') ? new \DateTimeImmutable($request->input('expires_at')) : null;
        $entries = [];

        DB::transaction(function () use ($request, $type, $reason, $expiresAt, &$entries): void {
            foreach ($request->input('phones') as $phone) {
                $entry = $type === 'whitelist'
                    ? UssdAccessList::addToWhitelist($phone, $reason, 'api', $expiresAt)
                    : UssdAccessList::addToBlacklist($phone, $reason, 'api', $expiresAt);

                $entries[] = $entry;
            }
        });

        return response()->json([
            'message' => count($entries).' phone(s) added to '.$type,
            'data' => $entries,
        ], 201);
    }

    /**
     * DELETE /access-list/bulk — Bulk remove entries.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ids = $request->input('ids');
        $count = UssdAccessList::whereIn('id', $ids)->update(['is_active' => false]);

        return response()->json([
            'message' => $count.' entry/entries deactivated successfully',
        ]);
    }

    /**
     * GET /access-list/export — Export list as CSV or JSON.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:whitelist,blacklist',
            'format' => 'sometimes|in:csv,json',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type = $request->input('type');
        $format = $request->input('format', 'json');
        $entries = UssdAccessList::query()->where('type', $type)->where('is_active', true)->where(function ($q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->get();

        if ($format === 'csv') {
            return $this->exportCsv($entries, $type);
        }

        return response()->json(['data' => $entries]);
    }

    /**
     * GET /rate-limits — Show current rate limit config and active blocks.
     */
    public function rateLimits(): JsonResponse
    {
        $config = [
            'max_requests_per_minute' => config('ussd.rate_limiting.max_requests_per_minute'),
            'max_requests_per_hour' => config('ussd.rate_limiting.max_requests_per_hour'),
            'max_requests_per_day' => config('ussd.rate_limiting.max_requests_per_day'),
        ];

        $activeBlocks = [];

        try {
            $activeBlocks = DB::table('ussd_rate_limits')
                ->whereNotNull('blocked_until')
                ->where('blocked_until', '>', now())
                ->get()
                ->toArray();
        } catch (\Exception) {
            // Table may not exist
        }

        return response()->json([
            'config' => $config,
            'active_blocks' => $activeBlocks,
        ]);
    }

    /**
     * POST /rate-limits/override — Override rate limit for a specific phone.
     */
    public function rateLimitOverride(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'per_minute' => 'sometimes|integer|min:1',
            'per_hour' => 'sometimes|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $phone = $request->input('phone');
        $override = [
            'per_minute' => $request->input('per_minute', config('ussd.rate_limiting.max_requests_per_minute')),
            'per_hour' => $request->input('per_hour', config('ussd.rate_limiting.max_requests_per_hour')),
            'expires_at' => $request->input('expires_at'),
        ];

        $cacheKey = 'ussd_rate_override_'.$phone;
        $ttl = $override['expires_at']
            ? (int) now()->diffInSeconds(new \DateTimeImmutable($override['expires_at']))
            : 86400 * 365;

        Cache::put($cacheKey, $override, $ttl);

        return response()->json([
            'message' => 'Rate limit override set for '.$phone,
            'data' => $override,
        ], 201);
    }

    /**
     * DELETE /rate-limits/override/{phone} — Remove rate limit override.
     */
    public function removeRateLimitOverride(string $phone): JsonResponse
    {
        $cacheKey = 'ussd_rate_override_'.$phone;

        if (! Cache::has($cacheKey)) {
            return response()->json(['message' => 'No override found for this phone number'], 404);
        }

        Cache::forget($cacheKey);

        return response()->json(['message' => 'Rate limit override removed for '.$phone]);
    }

    /**
     * GET /sessions — List active sessions with filters.
     */
    public function sessions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:new,active,grace_period,recovered,expired',
            'phone' => 'sometimes|string',
            'provider' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $query = DB::table('ussd_sessions');

            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->has('phone')) {
                $query->where('phone_number', $request->input('phone'));
            }

            $perPage = (int) $request->input('per_page', 15);

            return response()->json($query->orderByDesc('created_at')->paginate($perPage));
        } catch (\Exception) {
            return response()->json([
                'data' => [],
                'message' => 'Sessions table not available',
            ]);
        }
    }

    /**
     * Export entries as CSV via streamed response.
     *
     * @param  Collection<int, UssdAccessList>  $entries
     */
    private function exportCsv($entries, string $type): StreamedResponse
    {
        $filename = "ussd_{$type}_".date('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($entries): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['ID', 'Phone Number', 'Type', 'Reason', 'Added By', 'Expires At', 'Is Active', 'Created At']);

            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry->id,
                    $entry->phone_number,
                    $entry->type,
                    $entry->reason ?? '',
                    $entry->added_by ?? '',
                    $entry->expires_at?->toDateTimeString() ?? '',
                    $entry->is_active ? 'Yes' : 'No',
                    $entry->created_at->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
