<?php

namespace App\Http\Controllers;

use App\Support\DataImports\DataImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DataImportController extends Controller
{
    public function index(Request $request, DataImportService $imports)
    {
        $user = Auth::user();

        return view('admin.imports.index', [
            'datasets' => $imports->datasets(),
            'preview' => $imports->currentPreview($user, $request->session()),
            'importSummary' => session('import_summary'),
            'clientName' => $user->client?->name ?? 'No Client',
            'branchName' => $user->branch?->name ?? 'No Branch',
        ]);
    }

    public function downloadTemplate(string $dataset, DataImportService $imports)
    {
        return $imports->templateResponse($dataset);
    }

    public function preview(Request $request, DataImportService $imports)
    {
        $request->validate([
            'dataset' => ['required', 'string'],
            'import_file' => ['required', 'file', 'max:10240'],
        ]);

        $imports->storePreview(
            Auth::user(),
            (string) $request->input('dataset'),
            $request->file('import_file'),
            $request->session()
        );

        return redirect()
            ->route('admin.imports.index')
            ->with('success', 'Preview prepared. Review the rows below before running the import.');
    }

    public function store(Request $request, DataImportService $imports)
    {
        $summary = $imports->importCurrent(Auth::user(), $request->session());

        return redirect()
            ->route('admin.imports.index')
            ->with('success', $summary['label'] . ' import completed successfully.')
            ->with('import_summary', $summary);
    }

    public function clear(Request $request, DataImportService $imports)
    {
        $imports->forgetPreview($request->session());

        return redirect()
            ->route('admin.imports.index')
            ->with('success', 'Import preview cleared.');
    }
}
