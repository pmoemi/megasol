<?php

namespace Tests\Feature;

use App\Livewire\Customers\CustomerImport;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerImportTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin'.Str::random(4).'@test.com', 'password' => bcrypt('x'), 'is_active' => true]);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('customers.csv', $content);
    }

    public function test_uploading_a_file_auto_maps_columns_and_advances_to_step_two(): void
    {
        $this->actingAs($this->user());

        $csv = $this->csv(
            "First Name,Last Name,Phone Number,Email,Outstanding Balance,Next Payment Date\n".
            "Jane,Doe,+254712345601,jane@test.com,1200.50,2026-07-01\n"
        );

        Livewire::test(CustomerImport::class)
            ->set('csvFile', $csv)
            ->assertSet('step', 2)
            ->assertSet('mapping.first_name', 'First Name')
            ->assertSet('mapping.last_name', 'Last Name')
            ->assertSet('mapping.phone', 'Phone Number')
            ->assertSet('mapping.email', 'Email')
            ->assertSet('mapping.outstanding_balance', 'Outstanding Balance')
            ->assertSet('mapping.next_payment_date', 'Next Payment Date')
            ->assertSet('totalRows', 1);
    }

    public function test_cannot_proceed_without_required_fields_mapped(): void
    {
        $this->actingAs($this->user());

        $csv = $this->csv(
            "Name,Telephone\n".
            "Jane Doe,+254712345602\n"
        );

        $component = Livewire::test(CustomerImport::class)
            ->set('csvFile', $csv)
            ->assertSet('step', 2);

        // Auto-mapping should not have matched "first_name" from "Name" + "Telephone".
        $component->set('mapping.first_name', '')
            ->set('mapping.phone', '')
            ->call('proceedToPreview')
            ->assertSet('step', 2)
            ->assertHasErrors(['mapping.first_name', 'mapping.phone']);
    }

    public function test_full_import_flow_creates_customers_and_reports_results(): void
    {
        $this->actingAs($this->user());

        $csv = $this->csv(
            "First Name,Last Name,Phone Number,Outstanding Balance\n".
            "Jane,Doe,+254712345603,1200.50\n".
            "John,Smith,,500\n" // missing phone -> skipped
        );

        Livewire::test(CustomerImport::class)
            ->set('csvFile', $csv)
            ->assertSet('step', 2)
            ->call('proceedToPreview')
            ->assertSet('step', 3)
            ->call('import')
            ->assertSet('step', 4)
            ->assertSet('imported', 1)
            ->assertSet('failed', 1);

        $this->assertDatabaseHas('customers', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+254712345603',
            'outstanding_balance' => 1200.50,
        ]);

        $this->assertDatabaseMissing('customers', ['first_name' => 'John']);
    }

    public function test_duplicate_mapping_is_rejected(): void
    {
        $this->actingAs($this->user());

        $csv = $this->csv(
            "First Name,Phone Number\n".
            "Jane,+254712345604\n"
        );

        Livewire::test(CustomerImport::class)
            ->set('csvFile', $csv)
            ->assertSet('step', 2)
            ->set('mapping.last_name', 'Phone Number') // same column as phone
            ->call('proceedToPreview')
            ->assertSet('step', 2)
            ->assertHasErrors(['mapping.phone', 'mapping.last_name']);
    }

    public function test_empty_file_shows_error(): void
    {
        $this->actingAs($this->user());

        $csv = $this->csv('');

        Livewire::test(CustomerImport::class)
            ->set('csvFile', $csv)
            ->assertSet('step', 1)
            ->assertHasErrors('csvFile');
    }
}
