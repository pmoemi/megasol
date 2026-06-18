<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Import Customers')]
class CustomerImport extends Component
{
    use WithFileUploads;

    /** How many sample rows from the file to use for mapping preview. */
    private const SAMPLE_ROWS = 5;

    /** How many failure reasons to keep for display. */
    private const MAX_ERROR_SAMPLES = 10;

    public $csvFile;

    /** Current step: 1 = upload, 2 = map columns, 3 = preview & confirm, 4 = results. */
    public int $step = 1;

    /** @var array<int, string> */
    public array $headers = [];

    /** @var array<int, array<int, string>> First few data rows, used for mapping preview. */
    public array $sampleRows = [];

    public int $totalRows = 0;

    public string $delimiter = ',';

    /** @var array<string, string> */
    public array $mapping = [
        'account_number' => '',
        'first_name' => '',
        'last_name' => '',
        'phone' => '',
        'email' => '',
        'product_type' => '',
        'location' => '',
        'payment_status' => '',
        'next_payment_date' => '',
        'outstanding_balance' => '',
        'lifecycle_stage' => '',
    ];

    public int $imported = 0;

    public int $failed = 0;

    /** @var array<int, array{row: int, reason: string}> */
    public array $errorSamples = [];

    /**
     * Target fields available for mapping, with display label, whether
     * required, common header synonyms (for auto-mapping) and a short hint.
     *
     * @var array<string, array{label: string, required: bool, synonyms: array<int, string>, hint?: string}>
     */
    protected function fieldDefinitions(): array
    {
        return [
            'first_name' => [
                'label' => 'First Name', 'required' => true,
                'synonyms' => ['first name', 'firstname', 'fname', 'given name', 'name'],
                'hint' => 'Required for every customer.',
            ],
            'last_name' => [
                'label' => 'Last Name', 'required' => false,
                'synonyms' => ['last name', 'lastname', 'lname', 'surname'],
            ],
            'phone' => [
                'label' => 'Phone Number', 'required' => true,
                'synonyms' => ['phone', 'phone number', 'mobile', 'mobile number', 'msisdn', 'tel', 'telephone', 'contact'],
                'hint' => 'Required. Used to match existing customers when no account number is mapped.',
            ],
            'email' => [
                'label' => 'Email', 'required' => false,
                'synonyms' => ['email', 'email address', 'e-mail'],
            ],
            'account_number' => [
                'label' => 'Account Number', 'required' => false,
                'synonyms' => ['account number', 'account no', 'acc no', 'contract number', 'meter number', 'customer number'],
                'hint' => 'If mapped, used to match existing customers instead of phone.',
            ],
            'product_type' => [
                'label' => 'Product Type', 'required' => false,
                'synonyms' => ['product', 'product type', 'package', 'plan'],
            ],
            'location' => [
                'label' => 'Location', 'required' => false,
                'synonyms' => ['location', 'address', 'area', 'region', 'town', 'site'],
            ],
            'payment_status' => [
                'label' => 'Payment Status', 'required' => false,
                'synonyms' => ['payment status', 'status', 'account status'],
            ],
            'next_payment_date' => [
                'label' => 'Next Payment Date', 'required' => false,
                'synonyms' => ['next payment date', 'due date', 'next due date', 'next due'],
                'hint' => 'Accepts most common date formats (e.g. 2026-06-30, 30/06/2026).',
            ],
            'outstanding_balance' => [
                'label' => 'Outstanding Balance', 'required' => false,
                'synonyms' => ['balance', 'outstanding balance', 'amount due', 'arrears'],
                'hint' => 'Currency symbols and commas are stripped automatically.',
            ],
            'lifecycle_stage' => [
                'label' => 'Lifecycle Stage', 'required' => false,
                'synonyms' => ['lifecycle stage', 'stage', 'lifecycle'],
            ],
        ];
    }

    public function updatedCsvFile(): void
    {
        $this->resetErrorBag();
        $this->validate(['csvFile' => 'required|file|mimes:csv,txt|max:10240']);

        $this->headers = [];
        $this->sampleRows = [];
        $this->totalRows = 0;

        $contents = file_get_contents($this->csvFile->getRealPath());

        if ($contents === false || trim($contents) === '') {
            $this->addError('csvFile', 'This file appears to be empty.');

            return;
        }

        // Strip a UTF-8 byte-order mark if present (common with Excel exports).
        $contents = preg_replace('/^\x{EF}\x{BB}\x{BF}/u', '', $contents) ?? $contents;

        $lines = array_values(array_filter(
            preg_split("/\r\n|\r|\n/", $contents),
            fn ($line) => trim($line) !== ''
        ));

        if (count($lines) < 1) {
            $this->addError('csvFile', 'This file appears to be empty.');

            return;
        }

        $this->delimiter = $this->detectDelimiter($lines[0]);
        $this->headers = array_map('trim', str_getcsv($lines[0], $this->delimiter));

        if (count($this->headers) < 2) {
            $this->addError('csvFile', 'Could not detect column headers. Please check the file is a valid comma-separated CSV.');
            $this->headers = [];

            return;
        }

        $dataLines = array_slice($lines, 1);
        $this->totalRows = count($dataLines);

        if ($this->totalRows === 0) {
            $this->addError('csvFile', 'This file has headers but no data rows.');
            $this->headers = [];

            return;
        }

        foreach (array_slice($dataLines, 0, self::SAMPLE_ROWS) as $line) {
            $this->sampleRows[] = str_getcsv($line, $this->delimiter);
        }

        $this->autoMapHeaders();
        $this->step = 2;
    }

    /**
     * Detect the most likely column delimiter by checking which candidate
     * splits the header line into the most fields.
     */
    protected function detectDelimiter(string $headerLine): string
    {
        $best = ',';
        $bestCount = 1;

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = count(str_getcsv($headerLine, $candidate));

            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    protected function autoMapHeaders(): void
    {
        foreach ($this->fieldDefinitions() as $field => $def) {
            $this->mapping[$field] = $this->guessHeaderFor($def['synonyms']);
        }
    }

    /**
     * @param  array<int, string>  $synonyms
     */
    protected function guessHeaderFor(array $synonyms): string
    {
        $normalizedHeaders = [];
        foreach ($this->headers as $header) {
            $normalizedHeaders[$header] = strtolower(trim(preg_replace('/[\s_-]+/', ' ', $header)));
        }

        // Exact match first.
        foreach ($normalizedHeaders as $header => $normalized) {
            if (in_array($normalized, $synonyms, true)) {
                return $header;
            }
        }

        // Fall back to a partial / contains match.
        foreach ($normalizedHeaders as $header => $normalized) {
            foreach ($synonyms as $synonym) {
                if (str_contains($normalized, $synonym) || str_contains($synonym, $normalized)) {
                    return $header;
                }
            }
        }

        return '';
    }

    /**
     * Live preview of how the first few rows of the file will be imported
     * given the current column mapping.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function previewRows(): array
    {
        $headerIndex = array_flip($this->headers);
        $rows = [];

        foreach ($this->sampleRows as $row) {
            $mapped = [];

            foreach ($this->mapping as $field => $header) {
                $mapped[$field] = ($header !== '' && isset($headerIndex[$header]))
                    ? trim((string) ($row[$headerIndex[$header]] ?? ''))
                    : null;
            }

            $rows[] = $mapped;
        }

        return $rows;
    }

    /**
     * Mapping fields that are pointed at the same source column, keyed by
     * target field => duplicated header name.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function duplicateMappings(): array
    {
        $used = array_filter($this->mapping, fn ($header) => $header !== '');
        $counts = array_count_values($used);

        $duplicates = [];
        foreach ($used as $field => $header) {
            if (($counts[$header] ?? 0) > 1) {
                $duplicates[$field] = $header;
            }
        }

        return $duplicates;
    }

    public function backToUpload(): void
    {
        $this->reset(['headers', 'sampleRows', 'totalRows', 'mapping', 'csvFile', 'step']);
        $this->resetErrorBag();
        $this->step = 1;
    }

    public function backToMapping(): void
    {
        $this->step = 2;
    }

    public function proceedToPreview(): void
    {
        $this->resetErrorBag();
        $this->validateMapping();

        if (! $this->getErrorBag()->isEmpty()) {
            return;
        }

        $this->step = 3;
    }

    protected function validateMapping(): void
    {
        foreach ($this->fieldDefinitions() as $field => $def) {
            if (($def['required'] ?? false) && $this->mapping[$field] === '') {
                $this->addError("mapping.{$field}", $def['label'].' must be mapped to a column.');
            }
        }

        foreach ($this->duplicateMappings() as $field => $header) {
            $this->addError("mapping.{$field}", "\"{$header}\" is already mapped to another field.");
        }
    }

    public function import(): void
    {
        $this->resetErrorBag();
        $this->validate(['csvFile' => 'required|file|mimes:csv,txt|max:10240']);
        $this->validateMapping();

        if (! $this->getErrorBag()->isEmpty()) {
            $this->step = 2;

            return;
        }

        $path = $this->csvFile->store('imports');
        $contents = file_get_contents(Storage::path($path));
        $contents = preg_replace('/^\x{EF}\x{BB}\x{BF}/u', '', (string) $contents) ?? (string) $contents;

        $lines = array_values(array_filter(
            preg_split("/\r\n|\r|\n/", $contents),
            fn ($line) => trim($line) !== ''
        ));

        $headers = array_map('trim', str_getcsv($lines[0], $this->delimiter));
        $headerIndex = array_flip($headers);

        $this->imported = 0;
        $this->failed = 0;
        $this->errorSamples = [];

        foreach (array_slice($lines, 1) as $i => $line) {
            $rowNumber = $i + 2; // +1 for header row, +1 for 1-based numbering

            try {
                $row = str_getcsv($line, $this->delimiter);
                $data = [];

                foreach ($this->mapping as $field => $header) {
                    if ($header === '' || ! isset($headerIndex[$header])) {
                        continue;
                    }

                    $value = $row[$headerIndex[$header]] ?? null;
                    $value = is_string($value) ? trim($value) : $value;
                    $data[$field] = $value !== '' ? $value : null;
                }

                if (empty($data['phone']) || empty($data['first_name'])) {
                    $this->failed++;
                    $this->addErrorSample($rowNumber, 'Missing required Phone Number or First Name.');

                    continue;
                }

                if (! empty($data['outstanding_balance'])) {
                    $data['outstanding_balance'] = (float) preg_replace('/[^0-9.\-]/', '', (string) $data['outstanding_balance']);
                }

                if (! empty($data['next_payment_date'])) {
                    $date = $this->parseDate((string) $data['next_payment_date']);

                    if ($date === null) {
                        $this->failed++;
                        $this->addErrorSample($rowNumber, "Unrecognised date \"{$data['next_payment_date']}\" for Next Payment Date.");

                        continue;
                    }

                    $data['next_payment_date'] = $date;
                }

                $lookup = ! empty($data['account_number'])
                    ? ['account_number' => $data['account_number']]
                    : ['phone' => $data['phone']];

                Customer::updateOrCreate($lookup, $data);
                $this->imported++;
            } catch (\Throwable $e) {
                $this->failed++;
                $this->addErrorSample($rowNumber, 'Unexpected error: '.$e->getMessage());
            }
        }

        Storage::delete($path);

        $this->step = 4;
    }

    protected function addErrorSample(int $row, string $reason): void
    {
        if (count($this->errorSamples) < self::MAX_ERROR_SAMPLES) {
            $this->errorSamples[] = ['row' => $row, 'reason' => $reason];
        }
    }

    protected function parseDate(string $value): ?string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public function importAnother(): void
    {
        $this->reset(['headers', 'sampleRows', 'totalRows', 'mapping', 'csvFile', 'imported', 'failed', 'errorSamples', 'step']);
        $this->resetErrorBag();
        $this->step = 1;
    }

    public function downloadTemplate()
    {
        $columns = array_map(fn ($def) => $def['label'], $this->fieldDefinitions());

        $csv = implode(',', $columns)."\n";

        return response()->streamDownload(
            fn () => print($csv),
            'customer_import_template.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    public function render()
    {
        return view('livewire.customers.customer-import', [
            'fieldDefinitions' => $this->fieldDefinitions(),
        ]);
    }
}
