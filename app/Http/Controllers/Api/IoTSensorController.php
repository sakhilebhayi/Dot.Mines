<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IoTSensor;
use App\Services\IoTSensorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IoTSensorController extends Controller
{
    public function __construct(protected IoTSensorService $service) {}

    /**
     * Get sensor details with health check
     */
    public function show(IoTSensor $sensor): JsonResponse
    {
        $this->authorize('view', $sensor);

        $health = $this->service->checkSensorHealth($sensor);
        $stats = $this->service->getReadingStats($sensor, 7);

        return response()->json([
            'sensor' => $sensor,
            'health' => $health,
            'statistics' => $stats,
        ]);
    }

    /**
     * Record sensor reading
     */
    public function recordReading(Request $request, IoTSensor $sensor): JsonResponse
    {
        $this->authorize('update', $sensor);
        $validated = $request->validate([
            'value' => 'required|numeric',
            'unit' => 'required|string|max:50',
            'timestamp' => 'nullable|date_format:Y-m-d H:i:s',
            'quality_score' => 'nullable|numeric|between:0,1',
        ]);

        $reading = $this->service->recordReading($sensor, $validated);

        return response()->json($reading, Response::HTTP_CREATED);
    }

    /**
     * Get readings for sensor
     */
    public function readings(Request $request, IoTSensor $sensor): JsonResponse
    {
        $this->authorize('view', $sensor);

        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'type' => 'nullable|string|max:100|alpha_dash',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $days = $validated['days'] ?? 7;
        $type = $validated['type'] ?? 'all';

        $query = $sensor->readings()
            ->whereDate('timestamp', '>=', now()->subDays($days))
            ->orderBy('timestamp', 'desc');

        if ($type !== 'all') {
            $query->where('sensor_type', $type);
        }

        $readings = $query->paginate($validated['per_page'] ?? 50);

        return response()->json($readings);
    }

    /**
     * Get reading statistics
     */
    public function statistics(Request $request, IoTSensor $sensor): JsonResponse
    {
        $this->authorize('view', $sensor);

        $validated = $request->validate(['days' => 'nullable|integer|min:1|max:365']);
        $days = $validated['days'] ?? 7;
        $stats = $this->service->getReadingStats($sensor, $days);

        return response()->json($stats);
    }

    /**
     * Deactivate sensor
     */
    public function deactivate(IoTSensor $sensor): JsonResponse
    {
        $this->authorize('update', $sensor);

        $this->service->deactivateSensor($sensor);

        return response()->json(['message' => 'Sensor deactivated']);
    }

    /**
     * Export sensor data
     */
    public function export(Request $request, IoTSensor $sensor): JsonResponse|Response
    {
        $this->authorize('view', $sensor);

        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'format' => 'nullable|string|in:csv,json',
        ]);

        $days = $validated['days'] ?? 30;
        $format = $validated['format'] ?? 'csv';

        $readings = $sensor->readings()
            ->whereDate('timestamp', '>=', now()->subDays($days))
            ->orderBy('timestamp')
            ->limit(50000)
            ->get();

        if ($format === 'csv') {
            $csv = "Timestamp,Value,Unit,Quality Score\n";
            foreach ($readings as $reading) {
                $csv .= sprintf("%s,%s,%s,%s\n",
                    $reading->timestamp->format('Y-m-d H:i:s'),
                    $reading->value,
                    $reading->unit,
                    $reading->quality_score
                );
            }

            return response($csv, Response::HTTP_OK, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"sensor-{$sensor->id}.csv\"",
            ]);
        }

        return response()->json($readings);
    }
}
