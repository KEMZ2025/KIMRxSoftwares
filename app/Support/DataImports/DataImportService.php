<?php

namespace App\Support\DataImports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\Printing\CsvDownload;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\Store as SessionStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportService
{
    private const SESSION_KEY = 'data_import.preview_path';

    public function __construct(
        private readonly AuditTrail $auditTrail,
    ) {
    }

    public function datasets(): array
    {
        return [
            'products' => [
                'label' => 'Medicines',
                'description' => 'Create or update medicines, including categories, units, selling prices, and stock settings.',
                'notes' => [
                    'Download the template, paste old-system medicine data into the same columns, then upload as CSV.',
                    'Missing categories and units will be created automatically for this client.',
                    'If a barcode already exists in this branch, that medicine is updated instead of duplicated.',
                ],
                'headers' => [
                    'name',
                    'strength',
                    'barcode',
                    'category_name',
                    'unit_name',
                    'purchase_price',
                    'retail_price',
                    'wholesale_price',
                    'description',
                    'track_batch',
                    'track_expiry',
                    'expiry_alert_days',
                    'is_active',
                ],
                'sample' => [
                    'Paracetamol',
                    '500mg',
                    'PARA500-001',
                    'Tablets',
                    'Box',
                    '1000',
                    '1500',
                    '1400',
                    'Pain relief medicine',
                    'yes',
                    'yes',
                    '90',
                    'yes',
                ],
                'preview_columns' => ['name', 'strength', 'barcode', 'category_name', 'unit_name', 'retail_price', 'operation'],
            ],
            'customers' => [
                'label' => 'Customers',
                'description' => 'Create or update customer records for the client.',
                'notes' => [
                    'Customers are matched by email where available, otherwise by name plus phone.',
                    'If you also need opening receivable invoices, use the Opening Receivables importer below.',
                ],
                'headers' => [
                    'name',
                    'contact_person',
                    'phone',
                    'alt_phone',
                    'email',
                    'address',
                    'credit_limit',
                    'notes',
                    'is_active',
                ],
                'sample' => [
                    'VIP Family',
                    'Sarah',
                    '0772000000',
                    '',
                    'vipfamily@example.com',
                    'Kampala',
                    '500000',
                    'Main credit customer',
                    'yes',
                ],
                'preview_columns' => ['name', 'phone', 'email', 'credit_limit', 'operation'],
            ],
            'opening_receivables' => [
                'label' => 'Opening Receivables',
                'description' => 'Load customer invoices that were already open when this client moved to KIM Rx.',
                'notes' => [
                    'Import only the balance that is still unpaid at go-live, not the original full invoice value from the old system.',
                    'Missing customer accounts will be created automatically using the customer name, phone, and email in the file.',
                    'These imported balances appear in receivables and accounting balances, but they do not count as live sales performance or profit.',
                ],
                'headers' => [
                    'invoice_number',
                    'invoice_date',
                    'customer_name',
                    'customer_phone',
                    'customer_email',
                    'sale_channel',
                    'opening_balance_amount',
                    'notes',
                    'is_active',
                ],
                'sample' => [
                    'INV-OLD-001',
                    '2026-03-31',
                    'VIP Family',
                    '0772000000',
                    'vipfamily@example.com',
                    'retail',
                    '350000',
                    'Imported as opening customer balance from legacy system',
                    'yes',
                ],
                'preview_columns' => ['invoice_number', 'customer_name', 'sale_channel', 'opening_balance_amount', 'operation'],
            ],
            'suppliers' => [
                'label' => 'Suppliers',
                'description' => 'Create or update supplier records for the client.',
                'notes' => [
                    'Suppliers are matched by email where available, otherwise by name plus phone.',
                    'If you also need opening supplier balances, use the Opening Payables importer below.',
                ],
                'headers' => [
                    'name',
                    'contact_person',
                    'phone',
                    'alt_phone',
                    'email',
                    'address',
                    'notes',
                    'is_active',
                ],
                'sample' => [
                    'Cipla Distributor',
                    'Grace',
                    '0700000000',
                    '',
                    'supplies@example.com',
                    'Ndeeba',
                    'Main wholesaler',
                    'yes',
                ],
                'preview_columns' => ['name', 'phone', 'email', 'operation'],
            ],
            'opening_payables' => [
                'label' => 'Opening Payables',
                'description' => 'Load supplier invoices that were still unpaid when this client moved to KIM Rx.',
                'notes' => [
                    'Import only the balance still owed at go-live, not the original purchase value from the old system.',
                    'Missing supplier accounts will be created automatically using the supplier name, phone, and email in the file.',
                    'These imported balances appear in payables and accounting balances, but they do not count as live purchases or inventory value.',
                ],
                'headers' => [
                    'invoice_number',
                    'purchase_date',
                    'supplier_name',
                    'supplier_phone',
                    'supplier_email',
                    'opening_balance_amount',
                    'due_date',
                    'notes',
                    'is_active',
                ],
                'sample' => [
                    'SUP-OLD-001',
                    '2026-03-31',
                    'Cipla Distributor',
                    '0700000000',
                    'supplies@example.com',
                    '480000',
                    '2026-04-15',
                    'Imported as opening supplier balance from legacy system',
                    'yes',
                ],
                'preview_columns' => ['invoice_number', 'supplier_name', 'opening_balance_amount', 'due_date', 'operation'],
            ],
            'opening_stock' => [
                'label' => 'Opening Stock',
                'description' => 'Load opening stock batches for medicines already in the system.',
                'notes' => [
                    'Import medicines first, then opening stock.',
                    'If batch number is blank, the system will generate one for you.',
                    'Existing batches with the same medicine and batch number are blocked to prevent duplicate opening stock.',
                ],
                'headers' => [
                    'product_name',
                    'strength',
                    'barcode',
                    'batch_number',
                    'expiry_date',
                    'quantity',
                    'purchase_price',
                    'retail_price',
                    'wholesale_price',
                    'supplier_name',
                    'is_active',
                ],
                'sample' => [
                    'Paracetamol',
                    '500mg',
                    'PARA500-001',
                    'BATCH-001',
                    '2027-12-31',
                    '120',
                    '1000',
                    '1500',
                    '1400',
                    'Cipla Distributor',
                    'yes',
                ],
                'preview_columns' => ['product_name', 'strength', 'barcode', 'batch_number', 'quantity', 'supplier_name', 'operation'],
            ],
        ];
    }

    public function templateResponse(string $dataset): StreamedResponse
    {
        $definition = $this->dataset($dataset);

        return CsvDownload::make(
            'kimrx-' . $dataset . '-template.csv',
            $definition['headers'],
            [$definition['sample']]
        );
    }

    public function storePreview(User $user, string $dataset, UploadedFile $file, SessionStore $session): array
    {
        $definition = $this->dataset($dataset);
        [$headers, $rows] = $this->readCsvRows($file);

        $missingHeaders = collect($definition['headers'])
            ->filter(fn (string $header) => !array_key_exists($header, $headers))
            ->values()
            ->all();

        $preparedRows = [];
        $errorCount = 0;
        $createCount = 0;
        $updateCount = 0;

        if ($missingHeaders === []) {
            foreach ($rows as $index => $csvRow) {
                if ($this->rowIsEmpty($csvRow)) {
                    continue;
                }

                $rowNumber = $index + 2;
                $mapped = [];

                foreach ($definition['headers'] as $header) {
                    $mapped[$header] = trim((string) ($csvRow[$headers[$header]] ?? ''));
                }

                $prepared = $this->prepareRow($user, $dataset, $mapped, $rowNumber);
                $preparedRows[] = $prepared;

                if ($prepared['errors'] !== []) {
                    $errorCount += count($prepared['errors']);
                }

                if ($prepared['operation'] === 'create') {
                    $createCount++;
                } elseif ($prepared['operation'] === 'update') {
                    $updateCount++;
                }
            }
        }

        $payload = [
            'dataset' => $dataset,
            'label' => $definition['label'],
            'file_name' => $file->getClientOriginalName(),
            'user_id' => $user->id,
            'client_id' => $user->client_id,
            'branch_id' => $user->branch_id,
            'generated_at' => now()->toDateTimeString(),
            'missing_headers' => $missingHeaders,
            'rows' => $preparedRows,
            'summary' => [
                'row_count' => count($preparedRows),
                'valid_rows' => collect($preparedRows)->where('errors', [])->count(),
                'invalid_rows' => collect($preparedRows)->filter(fn (array $row) => $row['errors'] !== [])->count(),
                'error_count' => $errorCount,
                'create_count' => $createCount,
                'update_count' => $updateCount,
            ],
        ];

        $this->forgetPreview($session);

        $path = 'import-previews/' . Str::uuid() . '.json';
        Storage::disk('local')->put($path, json_encode($payload, JSON_PRETTY_PRINT));
        $session->put(self::SESSION_KEY, $path);

        return $this->decoratePreview($payload);
    }

    public function currentPreview(User $user, SessionStore $session): ?array
    {
        $path = $session->get(self::SESSION_KEY);

        if (!is_string($path) || $path === '' || !Storage::disk('local')->exists($path)) {
            return null;
        }

        $payload = json_decode((string) Storage::disk('local')->get($path), true);

        if (!is_array($payload) || (int) ($payload['user_id'] ?? 0) !== (int) $user->id) {
            $this->forgetPreview($session);

            return null;
        }

        if ((int) ($payload['client_id'] ?? 0) !== (int) $user->client_id
            || (int) ($payload['branch_id'] ?? 0) !== (int) $user->branch_id) {
            $this->forgetPreview($session);

            return null;
        }

        return $this->decoratePreview($payload);
    }

    public function importCurrent(User $user, SessionStore $session): array
    {
        $path = $session->get(self::SESSION_KEY);

        if (!is_string($path) || $path === '' || !Storage::disk('local')->exists($path)) {
            abort(422, 'There is no prepared import preview to run.');
        }

        $payload = json_decode((string) Storage::disk('local')->get($path), true);

        if (!is_array($payload) || (int) ($payload['user_id'] ?? 0) !== (int) $user->id) {
            $this->forgetPreview($session);
            abort(422, 'The prepared import preview is no longer available for this user.');
        }

        if ((int) ($payload['client_id'] ?? 0) !== (int) $user->client_id
            || (int) ($payload['branch_id'] ?? 0) !== (int) $user->branch_id) {
            $this->forgetPreview($session);
            abort(422, 'The prepared import preview belongs to a different client or branch context.');
        }

        if (($payload['missing_headers'] ?? []) !== []) {
            abort(422, 'The prepared file is missing required template columns.');
        }

        $invalidRows = collect($payload['rows'] ?? [])->filter(fn (array $row) => ($row['errors'] ?? []) !== []);

        if ($invalidRows->isNotEmpty()) {
            abort(422, 'Fix the preview errors before running the import.');
        }

        $stats = [
            'created' => 0,
            'updated' => 0,
            'categories_created' => 0,
            'customers_created' => 0,
            'units_created' => 0,
            'suppliers_created' => 0,
            'batches_created' => 0,
        ];

        DB::transaction(function () use ($user, $payload, &$stats): void {
            foreach ($payload['rows'] as $row) {
                $normalized = $row['normalized'] ?? [];

                match ($payload['dataset']) {
                    'products' => $this->importProductRow($user, $normalized, $stats),
                    'customers' => $this->importCustomerRow($user, $normalized, $stats),
                    'opening_receivables' => $this->importOpeningReceivableRow($user, $normalized, $stats),
                    'suppliers' => $this->importSupplierRow($user, $normalized, $stats),
                    'opening_payables' => $this->importOpeningPayableRow($user, $normalized, $stats),
                    'opening_stock' => $this->importOpeningStockRow($user, $normalized, $stats),
                    default => abort(404),
                };
            }
        });

        $dataset = (string) $payload['dataset'];
        $label = (string) ($payload['label'] ?? Str::title(str_replace('_', ' ', $dataset)));
        $rowCount = (int) Arr::get($payload, 'summary.row_count', 0);

        $this->auditTrail->recordSafely(
            $user,
            'data_import.' . $dataset . '_imported',
            'Administration',
            'Run Data Import',
            'Imported ' . $rowCount . ' row' . ($rowCount === 1 ? '' : 's') . ' into ' . $label . '.',
            [
                'reason' => 'CSV migration import',
                'context' => [
                    'dataset' => $dataset,
                    'label' => $label,
                    'file_name' => $payload['file_name'] ?? null,
                    'row_count' => $rowCount,
                    'created' => $stats['created'],
                    'updated' => $stats['updated'],
                    'categories_created' => $stats['categories_created'],
                    'customers_created' => $stats['customers_created'],
                    'units_created' => $stats['units_created'],
                    'suppliers_created' => $stats['suppliers_created'],
                    'batches_created' => $stats['batches_created'],
                ],
                'new_values' => $stats,
            ]
        );

        $this->forgetPreview($session);

        return [
            'dataset' => $dataset,
            'label' => $label,
            'file_name' => $payload['file_name'] ?? null,
            'row_count' => $rowCount,
            'stats' => $stats,
        ];
    }

    public function forgetPreview(SessionStore $session): void
    {
        $path = $session->pull(self::SESSION_KEY);

        if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    private function decoratePreview(array $payload): array
    {
        $definition = $this->dataset($payload['dataset']);

        return [
            'dataset' => $payload['dataset'],
            'label' => $payload['label'],
            'file_name' => $payload['file_name'],
            'generated_at' => $payload['generated_at'],
            'missing_headers' => $payload['missing_headers'],
            'summary' => $payload['summary'],
            'notes' => $definition['notes'],
            'preview_columns' => $definition['preview_columns'],
            'rows' => collect($payload['rows'])->take(12)->values()->all(),
        ];
    }

    private function dataset(string $dataset): array
    {
        $datasets = $this->datasets();

        abort_unless(array_key_exists($dataset, $datasets), 404);

        return $datasets[$dataset];
    }

    private function readCsvRows(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            abort(422, 'Unable to read the uploaded CSV file.');
        }

        $headerRow = fgetcsv($handle);

        if (!is_array($headerRow)) {
            fclose($handle);
            abort(422, 'The uploaded CSV file is empty.');
        }

        $headers = [];

        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);

            if ($normalized !== '') {
                $headers[$normalized] = $index;
            }
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return [$headers, $rows];
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return Str::of($header)
            ->lower()
            ->trim()
            ->replace([' ', '-'], '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->toString();
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function prepareRow(User $user, string $dataset, array $row, int $rowNumber): array
    {
        return match ($dataset) {
            'products' => $this->prepareProductRow($user, $row, $rowNumber),
            'customers' => $this->prepareCustomerRow($user, $row, $rowNumber),
            'opening_receivables' => $this->prepareOpeningReceivableRow($user, $row, $rowNumber),
            'suppliers' => $this->prepareSupplierRow($user, $row, $rowNumber),
            'opening_payables' => $this->prepareOpeningPayableRow($user, $row, $rowNumber),
            'opening_stock' => $this->prepareOpeningStockRow($user, $row, $rowNumber),
            default => abort(404),
        };
    }

    private function prepareProductRow(User $user, array $row, int $rowNumber): array
    {
        $errors = [];

        $name = $this->requiredString($row['name'], 'Medicine name is required.', $errors);
        $categoryName = $this->requiredString($row['category_name'], 'Category name is required.', $errors);
        $unitName = $this->requiredString($row['unit_name'], 'Unit name is required.', $errors);
        $purchasePrice = $this->requiredNumber($row['purchase_price'], 'Purchase price is required and must be numeric.', $errors);
        $retailPrice = $this->requiredNumber($row['retail_price'], 'Retail price is required and must be numeric.', $errors);
        $wholesalePrice = $this->requiredNumber($row['wholesale_price'], 'Wholesale price is required and must be numeric.', $errors);
        $trackBatch = $this->booleanValue($row['track_batch'], true, 'Track batch must be yes or no.', $errors);
        $trackExpiry = $this->booleanValue($row['track_expiry'], true, 'Track expiry must be yes or no.', $errors);
        $isActive = $this->booleanValue($row['is_active'], true, 'Active flag must be yes or no.', $errors);
        $expiryAlertDays = $this->integerValue(
            $row['expiry_alert_days'],
            $trackExpiry ? 90 : null,
            'Expiry alert days must be a whole number between 1 and 3650.',
            $errors,
            1,
            3650
        );

        $existing = $this->findProductForImport($user, trim((string) $row['barcode']), $name, trim((string) $row['strength']));

        return [
            'row_number' => $rowNumber,
            'display' => [
                'name' => $name,
                'strength' => trim((string) $row['strength']),
                'barcode' => trim((string) $row['barcode']),
                'category_name' => $categoryName,
                'unit_name' => $unitName,
                'retail_price' => $retailPrice === null ? '' : number_format($retailPrice, 2, '.', ''),
                'operation' => $existing ? 'update' : 'create',
            ],
            'normalized' => [
                'name' => $name,
                'strength' => $this->nullableString($row['strength']),
                'barcode' => $this->nullableString($row['barcode']),
                'category_name' => $categoryName,
                'unit_name' => $unitName,
                'purchase_price' => $purchasePrice,
                'retail_price' => $retailPrice,
                'wholesale_price' => $wholesalePrice,
                'description' => $this->nullableString($row['description']),
                'track_batch' => $trackBatch,
                'track_expiry' => $trackExpiry,
                'expiry_alert_days' => $expiryAlertDays,
                'is_active' => $isActive,
            ],
            'operation' => $existing ? 'update' : 'create',
            'errors' => $errors,
        ];
    }

    private function prepareCustomerRow(User $user, array $row, int $rowNumber): array
    {
        $errors = [];
        $name = $this->requiredString($row['name'], 'Customer name is required.', $errors);
        $creditLimit = $this->nullableNumber($row['credit_limit'], 'Credit limit must be numeric.', $errors, 0);
        $isActive = $this->booleanValue($row['is_active'], true, 'Active flag must be yes or no.', $errors);
        $email = $this->nullableString($row['email']);

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Customer email is not a valid email address.';
        }

        $existing = $this->findCustomerForImport($user, $email, $name, $this->nullableString($row['phone']));

        return [
            'row_number' => $rowNumber,
            'display' => [
                'name' => $name,
                'phone' => trim((string) $row['phone']),
                'email' => $email ?? '',
                'credit_limit' => $creditLimit === null ? '0.00' : number_format($creditLimit, 2, '.', ''),
                'operation' => $existing ? 'update' : 'create',
            ],
            'normalized' => [
                'name' => $name,
                'contact_person' => $this->nullableString($row['contact_person']),
                'phone' => $this->nullableString($row['phone']),
                'alt_phone' => $this->nullableString($row['alt_phone']),
                'email' => $email,
                'address' => $this->nullableString($row['address']),
                'credit_limit' => $creditLimit ?? 0,
                'notes' => $this->nullableString($row['notes']),
                'is_active' => $isActive,
            ],
            'operation' => $existing ? 'update' : 'create',
            'errors' => $errors,
        ];
    }

    private function prepareOpeningReceivableRow(User $user, array $row, int $rowNumber): array
    {
        $errors = [];
        $invoiceNumber = $this->requiredString($row['invoice_number'], 'Invoice number is required.', $errors);
        $customerName = $this->requiredString($row['customer_name'], 'Customer name is required.', $errors);
        $invoiceDate = $this->requiredDate($row['invoice_date'], 'Invoice date must be a valid date like 2026-03-31.', $errors);
        $openingBalanceAmount = $this->requiredNumber($row['opening_balance_amount'], 'Opening balance amount is required and must be numeric.', $errors, 0.01);
        $saleChannel = $this->saleChannelValue($row['sale_channel'], 'retail', $errors);
        $isActive = $this->booleanValue($row['is_active'], true, 'Active flag must be yes or no.', $errors);
        $customerEmail = $this->nullableString($row['customer_email']);
        $customerPhone = $this->nullableString($row['customer_phone']);

        if ($customerEmail !== null && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Customer email is not a valid email address.';
        }

        $existing = $invoiceNumber !== null
            ? $this->findOpeningReceivableForImport($user, $invoiceNumber)
            : null;

        if ($invoiceNumber !== null && $this->hasConflictingLiveSaleInvoice($user, $invoiceNumber, $existing?->id)) {
            $errors[] = 'Invoice number already belongs to a live sale in this branch.';
        }

        if ($existing && $existing->payments()->exists()) {
            $errors[] = 'This opening receivable already has customer collections recorded, so it can no longer be updated from import.';
        }

        return [
            'row_number' => $rowNumber,
            'display' => [
                'invoice_number' => $invoiceNumber,
                'customer_name' => $customerName,
                'sale_channel' => ucfirst($saleChannel),
                'opening_balance_amount' => $openingBalanceAmount === null ? '' : number_format($openingBalanceAmount, 2, '.', ''),
                'operation' => $existing ? 'update' : 'create',
            ],
            'normalized' => [
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $invoiceDate,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
                'sale_type' => $saleChannel,
                'opening_balance_amount' => $openingBalanceAmount,
                'notes' => $this->nullableString($row['notes']),
                'is_active' => $isActive,
            ],
            'operation' => $existing ? 'update' : 'create',
            'errors' => $errors,
        ];
    }

    private function prepareSupplierRow(User $user, array $row, int $rowNumber): array
    {
        $errors = [];
        $name = $this->requiredString($row['name'], 'Supplier name is required.', $errors);
        $isActive = $this->booleanValue($row['is_active'], true, 'Active flag must be yes or no.', $errors);
        $email = $this->nullableString($row['email']);

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Supplier email is not a valid email address.';
        }

        $existing = $this->findSupplierForImport($user, $email, $name, $this->nullableString($row['phone']));

        return [
            'row_number' => $rowNumber,
            'display' => [
                'name' => $name,
                'phone' => trim((string) $row['phone']),
                'email' => $email ?? '',
                'operation' => $existing ? 'update' : 'create',
            ],
            'normalized' => [
                'name' => $name,
                'contact_person' => $this->nullableString($row['contact_person']),
                'phone' => $this->nullableString($row['phone']),
                'alt_phone' => $this->nullableString($row['alt_phone']),
                'email' => $email,
                'address' => $this->nullableString($row['address']),
                'notes' => $this->nullableString($row['notes']),
                'is_active' => $isActive,
            ],
            'operation' => $existing ? 'update' : 'create',
            'errors' => $errors,
        ];
    }

    private function prepareOpeningPayableRow(User $user, array $row, int $rowNumber): array
    {
        $errors = [];
        $invoiceNumber = $this->requiredString($row['invoice_number'], 'Invoice number is required.', $errors);
        $supplierName = $this->requiredString($row['supplier_name'], 'Supplier name is required.', $errors);
        $purchaseDate = $this->requiredDate($row['purchase_date'], 'Purchase date must be a valid date like 2026-03-31.', $errors);
        $openingBalanceAmount = $this->requiredNumber($row['opening_balance_amount'], 'Opening balance amount is required and must be numeric.', $errors, 0.01);
        $dueDate = $this->nullableDate($row['due_date'], 'Due date must be a valid date like 2026-04-15.', $errors);
        $isActive = $this->booleanValue($row['is_active'], true, 'Active flag must be yes or no.', $errors);
        $supplierEmail = $this->nullableString($row['supplier_email']);
        $supplierPhone = $this->nullableString($row['supplier_phone']);

        if ($supplierEmail !== null && !filter_var($supplierEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Supplier email is not a valid email address.';
        }

        $existing = $invoiceNumber !== null
            ? $this->findOpeningPayableForImport($user, $invoiceNumber)
            : null;

        if ($invoiceNumber !== null && $this->hasConflictingLivePurchaseInvoice($user, $invoiceNumber, $existing?->id)) {
            $errors[] = 'Invoice number already belongs to a live purchase in this branch.';
        }

        if ($existing && $existing->supplierPayments()->exists()) {
            $errors[] = 'This opening payable already has supplier payments recorded, so it can no longer be updated from import.';
        }

        return [
            'row_number' => $rowNumber,
            'display' => [
                'invoice_number' => $invoiceNumber,
                'supplier_name' => $supplierName,
                'opening_balance_amount' => $openingBalanceAmount === null ? '' : number_format($openingBalanceAmount, 2, '.', ''),
                'due_date' => $dueDate ?? '',
                'operation' => $existing ? 'update' : 'create',
            ],
            'normalized' => [
                'invoice_number' => $invoiceNumber,
                'purchase_date' => $purchaseDate,
                'supplier_name' => $supplierName,
                'supplier_phone' => $supplierPhone,
                'supplier_email' => $supplierEmail,
                'opening_balance_amount' => $openingBalanceAmount,
                'due_date' => $dueDate,
                'notes' => $this->nullableString($row['notes']),
                'is_active' => $isActive,
            ],
            'operation' => $existing ? 'update' : 'create',
            'errors' => $errors,
        ];
    }

    private function prepareOpeningStockRow(User $user, array $row, int $rowNumber): array
    {
        $errors = [];
        $productName = $this->nullableString($row['product_name']);
        $strength = $this->nullableString($row['strength']);
        $barcode = $this->nullableString($row['barcode']);

        if ($productName === null && $barcode === null) {
            $errors[] = 'Provide either a product name or a barcode to match opening stock.';
        }

        $product = $this->findProductForImport($user, $barcode, $productName, $strength);

        if (!$product) {
            $errors[] = 'Opening stock row does not match any existing medicine in this branch.';
        }

        $quantity = $this->requiredNumber($row['quantity'], 'Quantity is required and must be numeric.', $errors, 0.01);
        $purchasePrice = $this->nullableNumber($row['purchase_price'], 'Purchase price must be numeric.', $errors, 0);
        $retailPrice = $this->nullableNumber($row['retail_price'], 'Retail price must be numeric.', $errors, 0);
        $wholesalePrice = $this->nullableNumber($row['wholesale_price'], 'Wholesale price must be numeric.', $errors, 0);
        $isActive = $this->booleanValue($row['is_active'], true, 'Active flag must be yes or no.', $errors);
        $expiryDate = $this->nullableDate($row['expiry_date'], 'Expiry date must be a valid date like 2027-12-31.', $errors);
        $batchNumber = $this->nullableString($row['batch_number']);

        if ($batchNumber === null && $product) {
            $batchNumber = $this->generatedBatchNumber($product, $rowNumber);
        }

        if ($product && $batchNumber !== null) {
            $existingBatch = ProductBatch::query()
                ->where('client_id', $user->client_id)
                ->where('branch_id', $user->branch_id)
                ->where('product_id', $product->id)
                ->where('batch_number', $batchNumber)
                ->exists();

            if ($existingBatch) {
                $errors[] = 'Batch ' . $batchNumber . ' already exists for this medicine in the current branch.';
            }
        }

        return [
            'row_number' => $rowNumber,
            'display' => [
                'product_name' => $productName ?? '',
                'strength' => $strength ?? '',
                'barcode' => $barcode ?? '',
                'batch_number' => $batchNumber ?? '',
                'quantity' => $quantity === null ? '' : number_format($quantity, 2, '.', ''),
                'supplier_name' => trim((string) $row['supplier_name']),
                'operation' => 'create',
            ],
            'normalized' => [
                'product_id' => $product?->id,
                'product_name' => $productName,
                'strength' => $strength,
                'barcode' => $barcode,
                'batch_number' => $batchNumber,
                'expiry_date' => $expiryDate,
                'quantity' => $quantity,
                'purchase_price' => $purchasePrice,
                'retail_price' => $retailPrice,
                'wholesale_price' => $wholesalePrice,
                'supplier_name' => $this->nullableString($row['supplier_name']),
                'is_active' => $isActive,
            ],
            'operation' => 'create',
            'errors' => $errors,
        ];
    }

    private function importProductRow(User $user, array $row, array &$stats): void
    {
        $category = $this->firstOrCreateCategory($user, $row['category_name']);
        $unit = $this->firstOrCreateUnit($user, $row['unit_name']);

        if ($category->wasRecentlyCreated) {
            $stats['categories_created']++;
        }

        if ($unit->wasRecentlyCreated) {
            $stats['units_created']++;
        }

        $product = $this->findProductForImport($user, $row['barcode'], $row['name'], $row['strength']);

        $payload = [
            'client_id' => $user->client_id,
            'branch_id' => $user->branch_id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => $row['name'],
            'strength' => $row['strength'],
            'barcode' => $row['barcode'],
            'description' => $row['description'],
            'purchase_price' => $row['purchase_price'],
            'retail_price' => $row['retail_price'],
            'wholesale_price' => $row['wholesale_price'],
            'track_batch' => $row['track_batch'],
            'track_expiry' => $row['track_expiry'],
            'expiry_alert_days' => $row['expiry_alert_days'],
            'is_active' => $row['is_active'],
        ];

        if ($product) {
            $product->update($payload);
            $stats['updated']++;
            return;
        }

        Product::query()->create($payload);
        $stats['created']++;
    }

    private function importCustomerRow(User $user, array $row, array &$stats): void
    {
        $customer = $this->findCustomerForImport($user, $row['email'], $row['name'], $row['phone']);

        $payload = [
            'client_id' => $user->client_id,
            'name' => $row['name'],
            'contact_person' => $row['contact_person'],
            'phone' => $row['phone'],
            'alt_phone' => $row['alt_phone'],
            'email' => $row['email'],
            'address' => $row['address'],
            'credit_limit' => $row['credit_limit'],
            'notes' => $row['notes'],
            'is_active' => $row['is_active'],
        ];

        if ($customer) {
            $customer->update($payload);
            $stats['updated']++;
            return;
        }

        Customer::query()->create($payload + ['outstanding_balance' => 0]);
        $stats['created']++;
    }

    private function importOpeningReceivableRow(User $user, array $row, array &$stats): void
    {
        $customer = $this->firstOrCreateCustomerForOpeningBalance(
            $user,
            $row['customer_name'],
            $row['customer_phone'],
            $row['customer_email']
        );

        if ($customer->wasRecentlyCreated) {
            $stats['customers_created']++;
        }

        $sale = $this->findOpeningReceivableForImport($user, $row['invoice_number']);
        $previousCustomerId = $sale?->customer_id;

        $payload = [
            'client_id' => $user->client_id,
            'branch_id' => $user->branch_id,
            'customer_id' => $customer->id,
            'served_by' => null,
            'approved_by' => $user->id,
            'invoice_number' => $row['invoice_number'],
            'receipt_number' => null,
            'source' => Sale::SOURCE_OPENING_BALANCE_IMPORT,
            'sale_type' => $row['sale_type'],
            'status' => 'approved',
            'payment_type' => 'credit',
            'payment_method' => null,
            'subtotal' => $row['opening_balance_amount'],
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $row['opening_balance_amount'],
            'amount_paid' => 0,
            'amount_received' => 0,
            'balance_due' => $row['opening_balance_amount'],
            'sale_date' => $row['invoice_date'],
            'approved_at' => now(),
            'notes' => $this->openingBalanceNote('opening customer balance', $row['notes']),
            'is_active' => $row['is_active'],
        ];

        if ($sale) {
            $sale->update($payload);
            $stats['updated']++;
        } else {
            $sale = Sale::query()->create($payload);
            $stats['created']++;
        }

        if ($previousCustomerId && (int) $previousCustomerId !== (int) $customer->id) {
            $this->syncCustomerOutstandingBalance((int) $previousCustomerId, (int) $user->client_id);
        }

        $this->syncCustomerOutstandingBalance((int) $customer->id, (int) $user->client_id);
    }

    private function importSupplierRow(User $user, array $row, array &$stats): void
    {
        $supplier = $this->findSupplierForImport($user, $row['email'], $row['name'], $row['phone']);

        $payload = [
            'client_id' => $user->client_id,
            'name' => $row['name'],
            'contact_person' => $row['contact_person'],
            'phone' => $row['phone'],
            'alt_phone' => $row['alt_phone'],
            'email' => $row['email'],
            'address' => $row['address'],
            'notes' => $row['notes'],
            'is_active' => $row['is_active'],
        ];

        if ($supplier) {
            $supplier->update($payload);
            $stats['updated']++;
            return;
        }

        Supplier::query()->create($payload);
        $stats['created']++;
    }

    private function importOpeningPayableRow(User $user, array $row, array &$stats): void
    {
        $supplier = $this->firstOrCreateSupplierForOpeningBalance(
            $user,
            $row['supplier_name'],
            $row['supplier_phone'],
            $row['supplier_email']
        );

        if ($supplier->wasRecentlyCreated) {
            $stats['suppliers_created']++;
        }

        $purchase = $this->findOpeningPayableForImport($user, $row['invoice_number']);

        $payload = [
            'client_id' => $user->client_id,
            'branch_id' => $user->branch_id,
            'supplier_id' => $supplier->id,
            'invoice_number' => $row['invoice_number'],
            'source' => Purchase::SOURCE_OPENING_BALANCE_IMPORT,
            'purchase_date' => $row['purchase_date'],
            'subtotal' => $row['opening_balance_amount'],
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => $row['opening_balance_amount'],
            'amount_paid' => 0,
            'balance_due' => $row['opening_balance_amount'],
            'payment_type' => 'credit',
            'payment_status' => 'pending',
            'due_date' => $row['due_date'],
            'invoice_status' => 'closed',
            'notes' => $this->openingBalanceNote('opening supplier balance', $row['notes']),
            'created_by' => $user->id,
            'is_active' => $row['is_active'],
        ];

        if ($purchase) {
            $purchase->update($payload);
            $stats['updated']++;
            return;
        }

        Purchase::query()->create($payload);
        $stats['created']++;
    }

    private function importOpeningStockRow(User $user, array $row, array &$stats): void
    {
        $product = Product::query()->findOrFail($row['product_id']);
        $supplier = $row['supplier_name'] ? $this->firstOrCreateSupplierByName($user, $row['supplier_name']) : null;

        if ($supplier?->wasRecentlyCreated) {
            $stats['suppliers_created']++;
        }

        $batch = ProductBatch::query()->create([
            'client_id' => $user->client_id,
            'branch_id' => $user->branch_id,
            'product_id' => $product->id,
            'supplier_id' => $supplier?->id,
            'batch_number' => $row['batch_number'],
            'expiry_date' => $row['expiry_date'],
            'purchase_price' => $row['purchase_price'] ?? (float) $product->purchase_price,
            'retail_price' => $row['retail_price'] ?? (float) $product->retail_price,
            'wholesale_price' => $row['wholesale_price'] ?? (float) $product->wholesale_price,
            'quantity_received' => $row['quantity'],
            'quantity_available' => $row['quantity'],
            'reserved_quantity' => 0,
            'is_active' => $row['is_active'],
        ]);

        StockMovement::query()->create([
            'client_id' => $user->client_id,
            'branch_id' => $user->branch_id,
            'product_id' => $product->id,
            'product_batch_id' => $batch->id,
            'movement_type' => 'import_opening_in',
            'reference_type' => 'data_import',
            'reference_id' => null,
            'quantity_in' => $row['quantity'],
            'quantity_out' => 0,
            'balance_after' => $row['quantity'],
            'note' => 'Opening stock imported for batch ' . $batch->batch_number . '.',
            'created_by' => $user->id,
        ]);

        $stats['created']++;
        $stats['batches_created']++;
    }

    private function firstOrCreateCategory(User $user, string $name): Category
    {
        $existing = Category::query()
            ->where('client_id', $user->client_id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Category::query()->create([
            'client_id' => $user->client_id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function firstOrCreateUnit(User $user, string $name): Unit
    {
        $existing = Unit::query()
            ->where('client_id', $user->client_id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Unit::query()->create([
            'client_id' => $user->client_id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function firstOrCreateSupplierByName(User $user, string $name): Supplier
    {
        $existing = Supplier::query()
            ->where('client_id', $user->client_id)
            ->whereRaw('LOWER(name) = ?', [Str::lower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Supplier::query()->create([
            'client_id' => $user->client_id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function firstOrCreateCustomerForOpeningBalance(User $user, string $name, ?string $phone, ?string $email): Customer
    {
        $existing = $this->findCustomerForImport($user, $email, $name, $phone);

        if ($existing) {
            return $existing;
        }

        return Customer::query()->create([
            'client_id' => $user->client_id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'is_active' => true,
            'outstanding_balance' => 0,
        ]);
    }

    private function firstOrCreateSupplierForOpeningBalance(User $user, string $name, ?string $phone, ?string $email): Supplier
    {
        $existing = $this->findSupplierForImport($user, $email, $name, $phone);

        if ($existing) {
            return $existing;
        }

        return Supplier::query()->create([
            'client_id' => $user->client_id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'is_active' => true,
        ]);
    }

    private function findProductForImport(User $user, ?string $barcode, ?string $name, ?string $strength): ?Product
    {
        $query = Product::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id);

        if ($barcode) {
            return (clone $query)
                ->where('barcode', $barcode)
                ->first();
        }

        if (!$name) {
            return null;
        }

        $productQuery = (clone $query)->whereRaw('LOWER(name) = ?', [Str::lower($name)]);

        if ($strength) {
            $productQuery->whereRaw('LOWER(COALESCE(strength, \'\')) = ?', [Str::lower($strength)]);
        }

        return $productQuery->first();
    }

    private function findCustomerForImport(User $user, ?string $email, ?string $name, ?string $phone): ?Customer
    {
        $query = Customer::query()->where('client_id', $user->client_id);

        if ($email) {
            return (clone $query)->where('email', $email)->first();
        }

        if (!$name) {
            return null;
        }

        $customerQuery = (clone $query)->whereRaw('LOWER(name) = ?', [Str::lower($name)]);

        if ($phone) {
            $customerQuery->where('phone', $phone);
        }

        return $customerQuery->first();
    }

    private function findSupplierForImport(User $user, ?string $email, ?string $name, ?string $phone): ?Supplier
    {
        $query = Supplier::query()->where('client_id', $user->client_id);

        if ($email) {
            return (clone $query)->where('email', $email)->first();
        }

        if (!$name) {
            return null;
        }

        $supplierQuery = (clone $query)->whereRaw('LOWER(name) = ?', [Str::lower($name)]);

        if ($phone) {
            $supplierQuery->where('phone', $phone);
        }

        return $supplierQuery->first();
    }

    private function findOpeningReceivableForImport(User $user, string $invoiceNumber): ?Sale
    {
        return Sale::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('source', Sale::SOURCE_OPENING_BALANCE_IMPORT)
            ->where('invoice_number', $invoiceNumber)
            ->first();
    }

    private function findOpeningPayableForImport(User $user, string $invoiceNumber): ?Purchase
    {
        return Purchase::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('source', Purchase::SOURCE_OPENING_BALANCE_IMPORT)
            ->where('invoice_number', $invoiceNumber)
            ->first();
    }

    private function hasConflictingLiveSaleInvoice(User $user, string $invoiceNumber, ?int $ignoreSaleId = null): bool
    {
        return Sale::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('invoice_number', $invoiceNumber)
            ->when($ignoreSaleId, fn ($query) => $query->where('id', '!=', $ignoreSaleId))
            ->where(function ($query) {
                $query->whereNull('source')
                    ->orWhere('source', Sale::SOURCE_LIVE);
            })
            ->exists();
    }

    private function hasConflictingLivePurchaseInvoice(User $user, string $invoiceNumber, ?int $ignorePurchaseId = null): bool
    {
        return Purchase::query()
            ->where('client_id', $user->client_id)
            ->where('branch_id', $user->branch_id)
            ->where('invoice_number', $invoiceNumber)
            ->when($ignorePurchaseId, fn ($query) => $query->where('id', '!=', $ignorePurchaseId))
            ->where(function ($query) {
                $query->whereNull('source')
                    ->orWhere('source', Purchase::SOURCE_LIVE);
            })
            ->exists();
    }

    private function generatedBatchNumber(Product $product, int $rowNumber): string
    {
        $base = Str::upper(Str::substr(Str::slug($product->name, ''), 0, 8));

        return 'OPEN-' . ($base !== '' ? $base : $product->id) . '-' . $rowNumber;
    }

    private function syncCustomerOutstandingBalance(int $customerId, int $clientId): void
    {
        $outstandingBalance = (float) Sale::query()
            ->where('client_id', $clientId)
            ->where('customer_id', $customerId)
            ->where('status', 'approved')
            ->where('is_active', true)
            ->sum('balance_due');

        Customer::query()
            ->where('client_id', $clientId)
            ->whereKey($customerId)
            ->update([
                'outstanding_balance' => max(0, $outstandingBalance),
            ]);
    }

    private function openingBalanceNote(string $label, ?string $notes): string
    {
        $base = 'Imported as ' . $label . '.';

        return $notes
            ? $base . ' ' . $notes
            : $base;
    }

    private function requiredString(?string $value, string $message, array &$errors): ?string
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            $errors[] = $message;
        }

        return $normalized;
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function requiredNumber(?string $value, string $message, array &$errors, float $min = 0): ?float
    {
        if ($this->nullableString($value) === null) {
            $errors[] = $message;
            return null;
        }

        return $this->nullableNumber($value, $message, $errors, $min);
    }

    private function nullableNumber(?string $value, string $message, array &$errors, float $min = 0): ?float
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            return null;
        }

        if (!is_numeric($normalized)) {
            $errors[] = $message;
            return null;
        }

        $number = round((float) $normalized, 2);

        if ($number < $min) {
            $errors[] = $message;
            return null;
        }

        return $number;
    }

    private function integerValue(?string $value, ?int $default, string $message, array &$errors, int $min, int $max): ?int
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            return $default;
        }

        if (!preg_match('/^-?\d+$/', $normalized)) {
            $errors[] = $message;
            return $default;
        }

        $number = (int) $normalized;

        if ($number < $min || $number > $max) {
            $errors[] = $message;
            return $default;
        }

        return $number;
    }

    private function requiredDate(?string $value, string $message, array &$errors): ?string
    {
        if ($this->nullableString($value) === null) {
            $errors[] = $message;
            return null;
        }

        return $this->nullableDate($value, $message, $errors);
    }

    private function booleanValue(?string $value, bool $default, string $message, array &$errors): bool
    {
        $normalized = Str::lower((string) $this->nullableString($value));

        if ($normalized === '') {
            return $default;
        }

        return match ($normalized) {
            '1', 'true', 'yes', 'y', 'on' => true,
            '0', 'false', 'no', 'n', 'off' => false,
            default => tap($default, fn () => $errors[] = $message),
        };
    }

    private function saleChannelValue(?string $value, string $default, array &$errors): string
    {
        $normalized = Str::lower((string) $this->nullableString($value));

        if ($normalized === '') {
            return $default;
        }

        if (in_array($normalized, ['retail', 'wholesale'], true)) {
            return $normalized;
        }

        $errors[] = 'Sale channel must be retail or wholesale.';

        return $default;
    }

    private function nullableDate(?string $value, string $message, array &$errors): ?string
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $normalized)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($normalized)->format('Y-m-d');
        } catch (\Throwable) {
            $errors[] = $message;
            return null;
        }
    }
}
