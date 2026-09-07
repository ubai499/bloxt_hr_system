<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
        $this->admin = User::factory()->create(['name' => 'Document Administrator']);
        $this->admin->assignRole('admin');
        $this->employee = User::factory()->create(['name' => 'Own Employee']);
        $this->employee->assignRole('employee');
        $this->other = User::factory()->create(['name' => 'Other Employee']);
        $this->other->assignRole('employee');
        Storage::fake('local');
        $this->actingAs($this->admin);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Employment evidence', 'employee_id' => $this->employee->id,
            'category' => 'Identity', 'access_classification' => 'Highly Confidential',
            'retention_category' => 'Standard (6 years)', 'issue_date' => '2026-01-01',
            'expiry_date' => '2027-01-01', 'retention_until' => '2033-01-01',
            'retention_reason' => 'Internal policy', 'notes' => 'Original evidence recorded.',
        ], $overrides);
    }

    private function seedDocument(array $overrides = [], bool $file = false): Document
    {
        return app(DocumentStorage::class)->create($this->payload($overrides), $this->admin,
            $file ? UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\n%%EOF") : null);
    }

    public function test_documents_defaults_to_all_categories_and_shares_contract_records(): void
    {
        $contract = $this->seedDocument(['category' => 'Employment Contract']);
        $this->seedDocument(['title' => 'Qualification', 'category' => 'Qualifications']);
        $this->get(route('admin.documents.index'))->assertOk()->assertViewHas('filters', fn ($filters) => $filters['category'] === 'all')
            ->assertSee('document-library.js')->assertSee('Retention metadata')->assertSee('All categories');
        $this->get(route('admin.contracts.index'))->assertOk()->assertViewHas('filters', fn ($filters) => $filters['category'] === 'Employment Contract');
        $this->getJson(route('admin.documents.index'))->assertJsonCount(2, 'rows')->assertJsonPath('rows.0.id', $contract->id);
        $csv = $this->get(route('admin.documents.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Qualification', $csv);
        $this->assertStringContainsString('Employment Contract', $csv);
        $this->assertDatabaseCount('documents', 2);
    }

    public function test_admin_can_store_every_prototype_category_with_metadata_and_private_files(): void
    {
        foreach (Document::CATEGORIES as $category) {
            $this->postJson(route('admin.documents.store'), $this->payload(['category' => $category]))->assertCreated();
        }
        $this->assertDatabaseCount('documents', count(Document::CATEGORIES));
        $this->assertDatabaseCount('document_activities', count(Document::CATEGORIES));
        $this->post(route('admin.documents.store'), $this->payload(['attachment' => UploadedFile::fake()->createWithContent('evidence.pdf', "%PDF-1.4\n%%EOF")]))
            ->assertRedirect(route('admin.documents.index', ['category' => 'Identity']))->assertSessionHas('toast_title', 'Document saved');
        $document = Document::latest('id')->first();
        $this->assertStringStartsWith('documents/', $document->file_path);
        $this->get(route('admin.documents.download', $document))->assertOk()->assertDownload('evidence.pdf');
    }

    public function test_employee_list_export_search_and_download_are_scoped_to_own_records(): void
    {
        $own = $this->seedDocument(['title' => 'Own evidence'], true);
        $other = $this->seedDocument(['title' => 'Other evidence', 'employee_id' => $this->other->id], true);
        $company = $this->seedDocument(['title' => 'Company evidence', 'employee_id' => null], true);
        $this->actingAs($this->employee);
        $this->get(route('employee.documents.index'))->assertOk()->assertDontSee('Other evidence')->assertDontSee('Company evidence');
        $this->getJson(route('employee.documents.index', ['employee' => $this->other->id]))->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.id', $own->id);
        $csv = $this->get(route('employee.documents.export', ['employee' => $this->other->id]))->assertOk()->streamedContent();
        $this->assertStringContainsString('Own evidence', $csv);
        $this->assertStringNotContainsString('Other evidence', $csv);
        $this->assertStringNotContainsString('Company evidence', $csv);
        $this->getJson(route('employee.documents.search', ['q' => 'evidence']))->assertJsonCount(1, 'documents')->assertJsonPath('documents.0.title', 'Own evidence');
        $this->get(route('employee.documents.download', $own))->assertOk();
        foreach ([$other, $company] as $document) {
            $this->get(route('employee.documents.download', $document))->assertNotFound();
        }
        $this->get(route('admin.documents.download', $own))->assertForbidden();
    }

    public function test_employee_upload_is_owned_by_the_authenticated_employee_and_cannot_target_others(): void
    {
        $this->actingAs($this->employee);
        foreach ([$this->other->id, null, $this->admin->id] as $id) {
            $this->postJson(route('employee.documents.store'), $this->payload(['employee_id' => $id]))->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        }
        $payload = $this->payload(['uploaded_by' => $this->admin->id, 'file_path' => 'private.txt']);
        unset($payload['employee_id']);
        $this->postJson(route('employee.documents.store'), $payload)->assertCreated();
        $this->assertDatabaseHas('documents', ['employee_id' => $this->employee->id, 'uploaded_by' => $this->employee->id, 'file_path' => null]);
        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseHas('document_activities', ['actor_id' => $this->employee->id]);
    }

    public function test_employee_without_documents_does_not_fall_back_to_company_wide_records(): void
    {
        $this->seedDocument(['employee_id' => null]);
        $this->actingAs($this->employee)->getJson(route('employee.documents.index'))->assertJsonCount(0, 'rows');
        $this->getJson(route('employee.documents.search', ['q' => 'Employment']))->assertJsonCount(0, 'documents');
    }

    public function test_profile_upload_uses_profile_employee_and_displays_safe_document_cards(): void
    {
        $payload = $this->payload(['employee_id' => $this->other->id, 'title' => '<img src=x onerror=alert(1)>', 'attachment' => UploadedFile::fake()->createWithContent('profile.pdf', "%PDF-1.4\n%%EOF")]);
        unset($payload['retention_category']);
        $this->post(route('admin.employees.documents.store', $this->employee), $payload)
            ->assertRedirect(route('admin.employees.show', ['employee' => $this->employee, 'tab' => 'documents']))->assertSessionHas('toast_title', 'Document saved');
        $document = Document::firstOrFail();
        $this->assertEquals($this->employee->id, $document->employee_id);
        $this->assertSame('Standard (6 years)', $document->retention_category);
        $this->get(route('admin.employees.show', ['employee' => $this->employee, 'tab' => 'documents']))->assertOk()
            ->assertSee('id="employeeDocuments"', false)->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x', false)->assertSee(route('admin.documents.download', $document), false);
        $this->get(route('admin.employees.show', ['employee' => $this->other, 'tab' => 'documents']))->assertOk()->assertSee('No documents on file');
    }

    public function test_profile_validation_reopens_modal_with_original_values(): void
    {
        $url = route('admin.employees.show', ['employee' => $this->employee, 'tab' => 'documents']);
        $this->from($url)->post(route('admin.employees.documents.store', $this->employee), $this->payload(['expiry_date' => '2025-01-01']))
            ->assertRedirect($url)->assertSessionHasErrors('expiry_date')->assertSessionHasInput('title', 'Employment evidence');
        $this->get($url)->assertOk()->assertSee('value="Employment evidence"', false)->assertSee('new bootstrap.Modal(modal).show()', false);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_onboarding_document_titles_are_persisted_in_the_same_library(): void
    {
        $department = Department::create(['name' => 'Operations', 'status' => 'Active']);
        $this->post(route('admin.employees.store'), [
            'name' => 'Onboarded Employee', 'email' => 'onboarded@example.test', 'job_title' => 'Coordinator',
            'department_id' => $department->id, 'employment_type' => 'Full-time', 'status' => 'Active',
            'document_titles' => ['Signed agreement', '', 'Qualification evidence'],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $employee = User::where('email', 'onboarded@example.test')->firstOrFail();
        $this->assertEquals(['Signed agreement', 'Qualification evidence'], $employee->documents()->orderBy('id')->pluck('title')->all());
        $this->assertDatabaseHas('documents', ['employee_id' => $employee->id, 'category' => 'Recruitment', 'access_classification' => 'Confidential']);
        $this->assertDatabaseCount('document_activities', 2);
        $this->get(route('admin.employees.show', ['employee' => $employee, 'tab' => 'documents']))->assertSee('Signed agreement')->assertSee('Qualification evidence');
    }

    public function test_invalid_onboarding_titles_do_not_create_a_partial_employee(): void
    {
        $this->post(route('admin.employees.store'), ['document_titles' => [str_repeat('a', 256)]])->assertSessionHasErrors('document_titles.0');
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_document_search_limits_results_and_links_to_highlighted_records(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->seedDocument(['title' => 'Evidence '.$i]);
        }
        $this->getJson(route('admin.documents.search', ['q' => 'evidence']))->assertOk()->assertJsonCount(4, 'documents')
            ->assertJsonPath('documents.0.url', route('admin.documents.index', ['highlight' => Document::first()->id]));
        $this->getJson(route('admin.documents.search', ['q' => 'x']))->assertUnprocessable();
        $this->get(route('admin.documents.create', ['employee' => $this->employee->id]))
            ->assertRedirect(route('admin.documents.index', ['new' => 1, 'employee' => (string) $this->employee->id]));
    }

    public function test_other_roles_cannot_use_document_endpoints(): void
    {
        $document = $this->seedDocument();
        foreach (['auditor', 'manager'] as $role) {
            Role::findOrCreate($role);
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);
            foreach (['admin', 'employee'] as $area) {
                foreach (['index', 'create', 'export', 'search'] as $action) {
                    $this->get(route($area.'.documents.'.$action))->assertForbidden();
                }
                $this->postJson(route($area.'.documents.store'), $this->payload())->assertForbidden();
                $this->get(route($area.'.documents.download', $document))->assertForbidden();
            }
            $this->post(route('admin.employees.documents.store', $this->employee), $this->payload())->assertForbidden();
        }
    }
}
