<?php

namespace Tests\Feature;

use App\Livewire\Customers\SegmentManager;
use App\Models\Customer;
use App\Models\Segment;
use App\Models\User;
use App\Services\Campaign\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SegmentManagerTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane'.Str::random(6).'@test.com',
            'phone' => '+25470'.random_int(1000000, 9999999), 'account_number' => 'A'.Str::random(4),
            'payment_status' => 'current', 'lifecycle_stage' => 'active',
        ], $attrs));
    }

    private function user(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('x'), 'is_active' => true]);
    }

    public function test_segment_manager_page_renders(): void
    {
        $this->actingAs($this->user());

        Livewire::test(SegmentManager::class)
            ->assertSee('Segments')
            ->assertSee('New Segment');
    }

    public function test_segment_can_be_created_with_rules_and_persists_count(): void
    {
        $this->actingAs($this->user());

        $this->customer(['payment_status' => 'overdue', 'location' => 'Nairobi']);
        $this->customer(['payment_status' => 'overdue', 'location' => 'Kisumu']);
        $this->customer(['payment_status' => 'current', 'location' => 'Nairobi']);

        Livewire::test(SegmentManager::class)
            ->set('name', 'Overdue Nairobi')
            ->set('match', 'all')
            ->set('conditions.0.field', 'payment_status')
            ->set('conditions.0.operator', 'equals')
            ->set('conditions.0.value', 'overdue')
            ->call('addCondition')
            ->set('conditions.1.field', 'location')
            ->set('conditions.1.operator', 'equals')
            ->set('conditions.1.value', 'Nairobi')
            ->call('createSegment');

        $segment = Segment::where('name', 'Overdue Nairobi')->first();

        $this->assertNotNull($segment);
        $this->assertSame(1, $segment->customers_count);
        $this->assertSame('all', $segment->rules['match']);
        $this->assertCount(2, $segment->rules['conditions']);
    }

    public function test_contains_operator_filters_by_substring(): void
    {
        $this->customer(['product_type' => 'Solar Home Premium']);
        $this->customer(['product_type' => 'Battery Pack']);

        $service = app(CampaignService::class);
        $rules = ['match' => 'all', 'conditions' => [
            ['field' => 'product_type', 'operator' => 'contains', 'value' => 'Solar'],
        ]];

        $this->assertSame(1, $service->previewSegmentCount($rules));
    }

    public function test_any_match_combines_conditions_with_or(): void
    {
        $this->customer(['payment_status' => 'overdue', 'lifecycle_stage' => 'active']);
        $this->customer(['payment_status' => 'current', 'lifecycle_stage' => 'at_risk']);
        $this->customer(['payment_status' => 'current', 'lifecycle_stage' => 'active']);

        $service = app(CampaignService::class);
        $rules = ['match' => 'any', 'conditions' => [
            ['field' => 'payment_status', 'operator' => 'equals', 'value' => 'overdue'],
            ['field' => 'lifecycle_stage', 'operator' => 'equals', 'value' => 'at_risk'],
        ]];

        $this->assertSame(2, $service->previewSegmentCount($rules));
    }

    public function test_between_operator_on_numeric_field(): void
    {
        $this->customer(['outstanding_balance' => 100]);
        $this->customer(['outstanding_balance' => 500]);
        $this->customer(['outstanding_balance' => 1000]);

        $service = app(CampaignService::class);
        $rules = ['match' => 'all', 'conditions' => [
            ['field' => 'outstanding_balance', 'operator' => 'between', 'value' => [200, 800]],
        ]];

        $this->assertSame(1, $service->previewSegmentCount($rules));
    }

    public function test_in_operator_with_array_value(): void
    {
        $this->customer(['lifecycle_stage' => 'at_risk']);
        $this->customer(['lifecycle_stage' => 'loyal']);
        $this->customer(['lifecycle_stage' => 'new']);

        $service = app(CampaignService::class);
        $rules = ['match' => 'all', 'conditions' => [
            ['field' => 'lifecycle_stage', 'operator' => 'in', 'value' => ['at_risk', 'loyal']],
        ]];

        $this->assertSame(2, $service->previewSegmentCount($rules));
    }

    public function test_campaign_segment_audience_resolves_via_estimate(): void
    {
        $this->customer(['payment_status' => 'overdue']);
        $this->customer(['payment_status' => 'current']);

        $segment = Segment::create([
            'name' => 'Overdue',
            'rules' => ['match' => 'all', 'conditions' => [
                ['field' => 'payment_status', 'operator' => 'equals', 'value' => 'overdue'],
            ]],
            'customers_count' => 1,
        ]);

        $service = app(CampaignService::class);
        $count = $service->estimateAudienceCount('segment', ['segment_id' => $segment->id], true);

        $this->assertSame(1, $count);
    }

    public function test_segment_can_be_edited_and_deleted(): void
    {
        $this->actingAs($this->user());

        $segment = Segment::create([
            'name' => 'Test Segment',
            'rules' => ['match' => 'all', 'conditions' => [
                ['field' => 'payment_status', 'operator' => 'equals', 'value' => 'overdue'],
            ]],
            'customers_count' => 0,
        ]);

        Livewire::test(SegmentManager::class)
            ->call('editSegment', $segment->id)
            ->assertSet('name', 'Test Segment')
            ->set('name', 'Renamed Segment')
            ->call('createSegment');

        $this->assertSame('Renamed Segment', $segment->fresh()->name);

        Livewire::test(SegmentManager::class)
            ->call('confirmDelete', $segment->id)
            ->call('deleteSegment');

        $this->assertNull(Segment::find($segment->id));
    }

    public function test_campaign_editor_shows_segment_dropdown_with_counts(): void
    {
        $this->actingAs($this->user());

        Segment::create([
            'name' => 'VIP Customers',
            'rules' => ['match' => 'all', 'conditions' => [
                ['field' => 'lifecycle_stage', 'operator' => 'equals', 'value' => 'loyal'],
            ]],
            'customers_count' => 5,
        ]);

        Livewire::test(\App\Livewire\Campaigns\CampaignEditor::class)
            ->set('currentStep', 3)
            ->set('audience_type', 'segment')
            ->assertSee('VIP Customers')
            ->assertSee('5 customers')
            ->assertSee('Manage segments');
    }
}
