<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $this->filtersFromRequest($request);
        $scopedQuery = $this->scopedQueryForUser($user);
        $filteredQuery = $this->applyFilters(clone $scopedQuery, $filters);

        $entries = $filteredQuery
            ->with(['user:id,name', 'client:id,name', 'branch:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        $today = Carbon::today(config('app.timezone'));

        return view('admin.audit.index', [
            'entries' => $entries,
            'filters' => $filters,
            'clientName' => $user->client?->name ?? ($user->isSuperAdmin() ? 'Owner Workspace' : 'No Client'),
            'branchName' => $user->branch?->name ?? ($user->isSuperAdmin() ? 'All Branches' : 'No Branch'),
            'moduleOptions' => (clone $scopedQuery)
                ->select('module')
                ->distinct()
                ->orderBy('module')
                ->pluck('module'),
            'actorOptions' => $this->actorOptions(clone $scopedQuery),
            'clientOptions' => $user->isSuperAdmin()
                ? $this->clientOptions(clone $scopedQuery)
                : collect(),
            'branchOptions' => $this->branchOptions(clone $scopedQuery, $filters['client_id']),
            'totalEntries' => (clone $filteredQuery)->count(),
            'todayEntries' => (clone $filteredQuery)->whereDate('created_at', $today->toDateString())->count(),
            'moduleCount' => (clone $filteredQuery)->distinct('module')->count('module'),
            'actorCount' => (clone $filteredQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    private function scopedQueryForUser(User $user): Builder
    {
        $query = AuditLog::query();

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query
            ->where('client_id', $user->client_id)
            ->where(function (Builder $builder) use ($user) {
                $builder
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $user->branch_id);
            });
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['from_date'], fn (Builder $builder, string $fromDate) => $builder->whereDate('created_at', '>=', $fromDate))
            ->when($filters['to_date'], fn (Builder $builder, string $toDate) => $builder->whereDate('created_at', '<=', $toDate))
            ->when($filters['module'], fn (Builder $builder, string $module) => $builder->where('module', $module))
            ->when($filters['actor_id'], fn (Builder $builder, int $actorId) => $builder->where('user_id', $actorId))
            ->when($filters['client_id'], fn (Builder $builder, int $clientId) => $builder->where('client_id', $clientId))
            ->when($filters['branch_id'], fn (Builder $builder, int $branchId) => $builder->where('branch_id', $branchId))
            ->when($filters['search'], function (Builder $builder, string $search) {
                $builder->where(function (Builder $innerQuery) use ($search) {
                    $innerQuery
                        ->where('summary', 'like', '%' . $search . '%')
                        ->orWhere('reason', 'like', '%' . $search . '%')
                        ->orWhere('subject_label', 'like', '%' . $search . '%')
                        ->orWhere('event_key', 'like', '%' . $search . '%')
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('client', fn (Builder $clientQuery) => $clientQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('branch', fn (Builder $branchQuery) => $branchQuery->where('name', 'like', '%' . $search . '%'));
                });
            });
    }

    private function filtersFromRequest(Request $request): array
    {
        $clientId = (int) $request->query('client_id', 0);
        $branchId = (int) $request->query('branch_id', 0);
        $actorId = (int) $request->query('actor_id', 0);

        return [
            'from_date' => filled($request->query('from_date')) ? (string) $request->query('from_date') : null,
            'to_date' => filled($request->query('to_date')) ? (string) $request->query('to_date') : null,
            'module' => trim((string) $request->query('module', '')) ?: null,
            'actor_id' => $actorId > 0 ? $actorId : null,
            'client_id' => $clientId > 0 ? $clientId : null,
            'branch_id' => $branchId > 0 ? $branchId : null,
            'search' => trim((string) $request->query('search', '')) ?: null,
        ];
    }

    private function actorOptions(Builder $scopedQuery)
    {
        $actorIds = (clone $scopedQuery)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->filter();

        if ($actorIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $actorIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function clientOptions(Builder $scopedQuery)
    {
        $clientIds = (clone $scopedQuery)
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id')
            ->filter();

        if ($clientIds->isEmpty()) {
            return collect();
        }

        return Client::query()
            ->whereIn('id', $clientIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function branchOptions(Builder $scopedQuery, ?int $selectedClientId)
    {
        $branchIds = (clone $scopedQuery)
            ->when($selectedClientId, fn (Builder $builder, int $clientId) => $builder->where('client_id', $clientId))
            ->whereNotNull('branch_id')
            ->distinct()
            ->pluck('branch_id')
            ->filter();

        if ($branchIds->isEmpty()) {
            return collect();
        }

        return Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->get(['id', 'client_id', 'name']);
    }
}
