<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merge duplicate PayGro customer records.
 *
 * PayGro frequently issues more than one customer record (a new SrlNo) for the
 * same physical person — identical name and phone. Because our sync keys
 * customers on account_number (PG-{SrlNo}), each SrlNo became its own row.
 * Units/payments are then matched back to customers by name string only, so a
 * duplicated name resolves to multiple rows and the sync silently skips it
 * (findCustomerForPayGroName returns null). The result is customers showing
 * fewer assets than PayGro, or none at all.
 *
 * This command collapses true duplicates (same normalized name AND same phone)
 * into the oldest record, repointing every child row that references the
 * duplicate, then soft-deletes the extras.
 */
class PayGroDedupeCustomersCommand extends Command
{
    protected $signature = 'paygro:dedupe-customers
        {--apply : Persist the merge. Without this flag the command only reports what it would do.}';

    protected $description = 'Merge duplicate PayGro customer records (same name + phone) into one canonical record.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $childTables = $this->childTablesWithCustomerId();
        $this->line('Child tables to repoint: '.implode(', ', $childTables));

        $groups = $this->duplicateGroups();

        if ($groups->isEmpty()) {
            $this->info('No duplicate customers found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info($groups->count().' duplicate group(s) found.'.($apply ? '' : ' (dry run — re-run with --apply to merge)'));

        $mergedCustomers = 0;
        $repointedRows = 0;

        foreach ($groups as $group) {
            /** @var \Illuminate\Support\Collection<int, Customer> $rows */
            $rows = $group['rows'];
            $canonical = $rows->first();
            $duplicates = $rows->slice(1);

            $this->line('');
            $this->line(sprintf(
                'KEEP  #%d %s [%s] phone=%s assets=%d',
                $canonical->id,
                $canonical->full_name,
                $canonical->account_number,
                $canonical->phone,
                $canonical->assets()->count(),
            ));

            foreach ($duplicates as $dup) {
                $counts = [];
                foreach ($childTables as $table) {
                    $n = DB::table($table)->where('customer_id', $dup->id)->count();
                    if ($n > 0) {
                        $counts[$table] = $n;
                    }
                }

                $this->line(sprintf(
                    '  MERGE #%d %s [%s] assets=%d -> %s',
                    $dup->id,
                    $dup->full_name,
                    $dup->account_number,
                    $dup->assets()->count(),
                    $counts === [] ? '(no child rows)' : json_encode($counts),
                ));

                if ($apply) {
                    $repointedRows += $this->mergeCustomer($canonical, $dup, $childTables);
                    $mergedCustomers++;
                }
            }
        }

        $this->line('');

        if (! $apply) {
            $this->warn('Dry run complete. No changes were made. Re-run with --apply to merge.');

            return self::SUCCESS;
        }

        $this->info("Merged {$mergedCustomers} duplicate customer(s); repointed {$repointedRows} child row(s).");
        $this->info('Run `php artisan paygro:sync` to backfill any assets that were previously skipped.');

        return self::SUCCESS;
    }

    /**
     * Duplicate groups keyed by normalized name + phone, ordered so the oldest
     * record is first (the canonical record we keep).
     *
     * @return \Illuminate\Support\Collection<int, array{key: string, rows: \Illuminate\Support\Collection<int, Customer>}>
     */
    protected function duplicateGroups()
    {
        return Customer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Customer $c) => $this->identityKey($c))
            ->filter(fn ($rows) => $rows->count() > 1)
            ->map(fn ($rows, $key) => ['key' => $key, 'rows' => $rows->values()])
            ->values();
    }

    protected function identityKey(Customer $c): string
    {
        $name = strtolower(trim(preg_replace('/\s+/', ' ', $c->first_name.' '.($c->last_name ?? ''))));
        $phone = strtolower(trim((string) $c->phone));

        return $name.'|'.$phone;
    }

    /**
     * Move every child row from $dup onto $canonical, fold useful metadata onto
     * the canonical record, then soft-delete the duplicate. Returns the number
     * of child rows repointed.
     *
     * @param  array<int, string>  $childTables
     */
    protected function mergeCustomer(Customer $canonical, Customer $dup, array $childTables): int
    {
        return DB::transaction(function () use ($canonical, $dup, $childTables) {
            $repointed = 0;

            foreach ($childTables as $table) {
                if ($table === 'customer_list_members') {
                    $repointed += $this->repointListMemberships($canonical, $dup);

                    continue;
                }

                $repointed += DB::table($table)
                    ->where('customer_id', $dup->id)
                    ->update(['customer_id' => $canonical->id]);
            }

            $this->foldMetadata($canonical, $dup);
            $dup->delete();

            return $repointed;
        });
    }

    /**
     * The pivot has a unique (customer_list_id, customer_id) constraint, so drop
     * memberships the canonical already has before repointing the rest.
     */
    protected function repointListMemberships(Customer $canonical, Customer $dup): int
    {
        $canonicalListIds = DB::table('customer_list_members')
            ->where('customer_id', $canonical->id)
            ->pluck('customer_list_id')
            ->all();

        DB::table('customer_list_members')
            ->where('customer_id', $dup->id)
            ->whereIn('customer_list_id', $canonicalListIds)
            ->delete();

        return DB::table('customer_list_members')
            ->where('customer_id', $dup->id)
            ->update(['customer_id' => $canonical->id]);
    }

    protected function foldMetadata(Customer $canonical, Customer $dup): void
    {
        $meta = is_array($canonical->meta) ? $canonical->meta : [];

        $altAccounts = (array) ($meta['paygro_merged_account_numbers'] ?? []);
        if ($dup->account_number && ! in_array($dup->account_number, $altAccounts, true)) {
            $altAccounts[] = $dup->account_number;
        }

        $altSrls = (array) ($meta['paygro_merged_srl_nos'] ?? []);
        $dupSrl = is_array($dup->meta) ? ($dup->meta['paygro_srl_no'] ?? null) : null;
        if ($dupSrl !== null && ! in_array($dupSrl, $altSrls, true)) {
            $altSrls[] = $dupSrl;
        }

        $meta['paygro_merged_account_numbers'] = array_values($altAccounts);
        $meta['paygro_merged_srl_nos'] = array_values($altSrls);

        $canonical->update(['meta' => $meta]);
    }

    /**
     * Every table (other than customers itself) that carries a customer_id FK.
     *
     * @return array<int, string>
     */
    protected function childTablesWithCustomerId(): array
    {
        $database = DB::getDatabaseName();

        $rows = DB::select(
            'SELECT TABLE_NAME AS t FROM information_schema.COLUMNS WHERE COLUMN_NAME = ? AND TABLE_SCHEMA = ?',
            ['customer_id', $database],
        );

        return collect($rows)
            ->pluck('t')
            ->reject(fn ($table) => $table === 'customers')
            ->filter(fn ($table) => Schema::hasTable($table))
            ->values()
            ->all();
    }
}
