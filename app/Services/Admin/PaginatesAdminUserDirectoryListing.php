<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class PaginatesAdminUserDirectoryListing
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function paginate(array $validated): LengthAwarePaginator
    {
        $sortBy = $validated['sort_by'] ?? 'newest';
        $perPage = (int) ($validated['per_page'] ?? 10);
        $sortDirection = $sortBy === 'newest' ? 'desc' : 'asc';
        $searchTerm = filled($validated['search'] ?? null)
            ? '%'.$validated['search'].'%'
            : null;

        $userRows = User::query()
            ->leftJoin('admins', 'admins.email', '=', 'users.email')
            ->leftJoin('user_wallets', 'user_wallets.user_id', '=', 'users.id')
            ->select($this->directorySelectColumns(
                recordType: 'user',
                userIdColumn: 'users.id',
                adminIdColumn: 'admins.id',
                firstNameColumn: 'users.first_name',
                lastNameColumn: 'users.last_name',
                emailColumn: 'users.email',
                phoneColumn: 'users.phone',
                googleIdColumn: 'users.google_id',
                accountStatusColumn: 'users.account_status',
                suspendedUntilColumn: 'users.suspended_until',
                walletAvailableBalanceColumn: 'user_wallets.available_balance',
                walletCurrencyCodeColumn: 'user_wallets.currency_code',
                emailVerifiedAtColumn: 'users.email_verified_at',
                createdAtColumn: 'users.created_at',
                updatedAtColumn: 'users.updated_at',
            ))
            ->when(
                $searchTerm !== null,
                fn (Builder $query) => $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery
                        ->where('users.first_name', 'like', $searchTerm)
                        ->orWhere('users.last_name', 'like', $searchTerm)
                        ->orWhere('users.email', 'like', $searchTerm)
                        ->orWhere('users.phone', 'like', $searchTerm);
                }),
            );

        $adminOnlyRows = Admin::query()
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.email', 'admins.email');
            })
            ->select($this->directorySelectColumns(
                recordType: 'admin',
                userIdColumn: null,
                adminIdColumn: 'admins.id',
                firstNameColumn: 'admins.first_name',
                lastNameColumn: 'admins.last_name',
                emailColumn: 'admins.email',
                phoneColumn: 'admins.phone',
                googleIdColumn: null,
                accountStatusColumn: null,
                suspendedUntilColumn: null,
                walletAvailableBalanceColumn: null,
                walletCurrencyCodeColumn: null,
                emailVerifiedAtColumn: null,
                createdAtColumn: 'admins.created_at',
                updatedAtColumn: 'admins.updated_at',
            ))
            ->when(
                $searchTerm !== null,
                fn (Builder $query) => $query->where(function (Builder $searchQuery) use ($searchTerm): void {
                    $searchQuery
                        ->where('admins.first_name', 'like', $searchTerm)
                        ->orWhere('admins.last_name', 'like', $searchTerm)
                        ->orWhere('admins.email', 'like', $searchTerm)
                        ->orWhere('admins.phone', 'like', $searchTerm);
                }),
            );

        $directoryQuery = DB::query()
            ->fromSub($userRows->unionAll($adminOnlyRows), 'directory_members')
            ->when(
                array_key_exists('has_admin_access', $validated),
                function (QueryBuilder $query) use ($validated): void {
                    if ($validated['has_admin_access']) {
                        $query->whereIn('role', ['admin', 'super_admin']);
                    } else {
                        $query->where('role', 'user');
                    }
                },
            )
            ->orderBy('created_at', $sortDirection);

        return $directoryQuery->paginate($perPage);
    }

    /**
     * @return array<int, mixed>
     */
    private function directorySelectColumns(
        string $recordType,
        ?string $userIdColumn,
        string $adminIdColumn,
        string $firstNameColumn,
        string $lastNameColumn,
        string $emailColumn,
        string $phoneColumn,
        ?string $googleIdColumn,
        ?string $accountStatusColumn,
        ?string $suspendedUntilColumn,
        ?string $walletAvailableBalanceColumn,
        ?string $walletCurrencyCodeColumn,
        ?string $emailVerifiedAtColumn,
        string $createdAtColumn,
        string $updatedAtColumn,
    ): array {
        $roleExpression = $recordType === 'user'
            ? "CASE WHEN admins.is_super_admin = 1 THEN 'super_admin' WHEN admins.id IS NOT NULL THEN 'admin' ELSE 'user' END"
            : "CASE WHEN admins.is_super_admin = 1 THEN 'super_admin' ELSE 'admin' END";

        return [
            DB::raw("'{$recordType}' as record_type"),
            $userIdColumn === null ? DB::raw('NULL as user_id') : DB::raw("{$userIdColumn} as user_id"),
            DB::raw("{$adminIdColumn} as admin_id"),
            DB::raw("{$firstNameColumn} as first_name"),
            DB::raw("{$lastNameColumn} as last_name"),
            DB::raw("{$emailColumn} as email"),
            DB::raw("{$phoneColumn} as phone"),
            $googleIdColumn === null ? DB::raw('NULL as google_id') : DB::raw("{$googleIdColumn} as google_id"),
            $accountStatusColumn === null
                ? DB::raw("'active' as account_status")
                : DB::raw("{$accountStatusColumn} as account_status"),
            $suspendedUntilColumn === null
                ? DB::raw('NULL as suspended_until')
                : DB::raw("{$suspendedUntilColumn} as suspended_until"),
            $walletAvailableBalanceColumn === null
                ? DB::raw('NULL as wallet_available_balance')
                : DB::raw("{$walletAvailableBalanceColumn} as wallet_available_balance"),
            $walletCurrencyCodeColumn === null
                ? DB::raw('NULL as wallet_currency_code')
                : DB::raw("{$walletCurrencyCodeColumn} as wallet_currency_code"),
            $emailVerifiedAtColumn === null ? DB::raw('NULL as email_verified_at') : DB::raw("{$emailVerifiedAtColumn} as email_verified_at"),
            DB::raw("{$roleExpression} as role"),
            DB::raw("{$createdAtColumn} as created_at"),
            DB::raw("{$updatedAtColumn} as updated_at"),
        ];
    }
}
