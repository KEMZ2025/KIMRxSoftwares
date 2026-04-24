<?php

namespace App\Http\Controllers;

use App\Support\InventoryExpiryAlerts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryAlertController extends Controller
{
    public function expiryReminder(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['available' => false]);
        }

        $warning = InventoryExpiryAlerts::pullDueWarning($request, $user);

        if (!$warning) {
            return response()->json([
                'available' => false,
                'reminder_hours' => InventoryExpiryAlerts::reminderHours(),
            ]);
        }

        return response()->json([
            'available' => true,
            'warning' => $warning,
            'reminder_hours' => InventoryExpiryAlerts::reminderHours(),
        ]);
    }
}
