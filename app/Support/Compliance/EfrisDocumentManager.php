<?php

namespace App\Support\Compliance;

use App\Models\Client;
use App\Models\ClientSetting;
use App\Models\EfrisDocument;
use App\Models\Sale;
use App\Support\ClientFeatureAccess;
class EfrisDocumentManager
{
    public static function syncApprovedSale(Sale $sale): ?EfrisDocument
    {
        $sale->loadMissing(['customer', 'items.product', 'items.batch']);

        $settings = self::settingsForSale($sale);
        $existingDocument = EfrisDocument::query()->where('sale_id', $sale->id)->first();

        if (!$existingDocument && !ClientFeatureAccess::efrisEnabled($settings)) {
            return null;
        }

        return EfrisDocument::query()->updateOrCreate(
            ['sale_id' => $sale->id],
            [
                'client_id' => $sale->client_id,
                'branch_id' => $sale->branch_id,
                'environment' => $settings->efris_environment ?: 'sandbox',
                'document_kind' => self::resolveDocumentKind($sale),
                'status' => 'ready',
                'next_action' => 'submit_sale',
                'reference_number' => self::resolveReferenceNumber($sale),
                'attempt_count' => 0,
                'prepared_at' => now(),
                'last_attempt_at' => null,
                'submitted_at' => null,
                'accepted_at' => null,
                'reversal_required_at' => null,
                'last_error_message' => null,
                'payload_snapshot' => self::payloadSnapshot($sale, $settings),
                'response_snapshot' => null,
            ]
        );
    }

    public static function markReversalRequired(Sale $sale, ?string $reason = null): ?EfrisDocument
    {
        $sale->loadMissing(['customer', 'items.product', 'items.batch']);

        $settings = self::settingsForSale($sale);
        $existingDocument = EfrisDocument::query()->where('sale_id', $sale->id)->first();

        if (!$existingDocument && !ClientFeatureAccess::efrisEnabled($settings)) {
            return null;
        }

        $snapshot = self::payloadSnapshot($sale, $settings);
        if ($reason !== null && $reason !== '') {
            $snapshot['cancellation_reason'] = $reason;
        }

        return EfrisDocument::query()->updateOrCreate(
            ['sale_id' => $sale->id],
            [
                'client_id' => $sale->client_id,
                'branch_id' => $sale->branch_id,
                'environment' => $settings->efris_environment ?: 'sandbox',
                'document_kind' => self::resolveDocumentKind($sale),
                'status' => 'ready',
                'next_action' => 'submit_reversal',
                'reference_number' => self::resolveReferenceNumber($sale),
                'attempt_count' => 0,
                'prepared_at' => now(),
                'last_attempt_at' => null,
                'submitted_at' => null,
                'accepted_at' => null,
                'reversal_required_at' => now(),
                'last_error_message' => null,
                'payload_snapshot' => $snapshot,
                'response_snapshot' => null,
            ]
        );
    }

    private static function settingsForSale(Sale $sale): ClientSetting
    {
        $businessMode = Client::query()
            ->whereKey($sale->client_id)
            ->value('business_mode') ?: 'both';

        return ClientSetting::query()->firstOrCreate(
            ['client_id' => $sale->client_id],
            ['business_mode' => $businessMode] + ClientFeatureAccess::defaultSettingValues()
        );
    }

    private static function resolveDocumentKind(Sale $sale): string
    {
        if ($sale->payment_type === 'credit' || (float) $sale->balance_due > 0) {
            return 'invoice';
        }

        return 'receipt';
    }

    private static function resolveReferenceNumber(Sale $sale): ?string
    {
        return $sale->receipt_number ?: $sale->invoice_number;
    }

    private static function payloadSnapshot(Sale $sale, ClientSetting $settings): array
    {
        return [
            'environment' => $settings->efris_environment ?: 'sandbox',
            'tin' => $settings->efris_tin ?: $settings->tax_number,
            'legal_name' => $settings->efris_legal_name,
            'business_name' => $settings->efris_business_name,
            'branch_code' => $settings->efris_branch_code,
            'device_serial' => $settings->efris_device_serial,
            'sale' => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'receipt_number' => $sale->receipt_number,
                'sale_type' => $sale->sale_type,
                'status' => $sale->status,
                'payment_type' => $sale->payment_type,
                'payment_method' => $sale->payment_method,
                'sale_date' => optional($sale->sale_date)->toDateString(),
                'approved_at' => $sale->approved_at ? $sale->approved_at->toIso8601String() : null,
                'subtotal' => (float) $sale->subtotal,
                'discount_amount' => (float) $sale->discount_amount,
                'tax_amount' => (float) $sale->tax_amount,
                'total_amount' => (float) $sale->total_amount,
                'amount_paid' => (float) $sale->amount_paid,
                'amount_received' => (float) $sale->amount_received,
                'balance_due' => (float) $sale->balance_due,
            ],
            'customer' => $sale->customer ? [
                'id' => $sale->customer->id,
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone ?? null,
                'email' => $sale->customer->email ?? null,
            ] : null,
            'items' => $sale->items->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'batch_number' => $item->batch?->batch_number,
                    'expiry_date' => optional($item->batch?->expiry_date)->toDateString(),
                    'quantity' => (float) $item->quantity,
                    'purchase_price' => (float) $item->purchase_price,
                    'unit_price' => (float) $item->unit_price,
                    'discount_amount' => (float) $item->discount_amount,
                    'total_amount' => (float) $item->total_amount,
                ];
            })->values()->all(),
        ];
    }
}
