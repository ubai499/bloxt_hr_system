<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-06 12:00:00'));
        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
        $this->admin = User::factory()->create(['name' => 'Contract Administrator']);
        $this->admin->assignRole('admin');
        $this->employee = User::factory()->create(['name' => 'Contract Employee', 'status' => 'Active']);
        $this->employee->assignRole('employee');
        $this->actingAs($this->admin);
        Storage::fake('local');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Signed employment contract', 'employee_id' => $this->employee->id,
            'category' => 'Employment Contract', 'access_classification' => 'Confidential',
            'issue_date' => '2026-09-01', 'expiry_date' => '2027-09-01',
            'retention_category' => 'Standard (6 years)', 'retention_until' => '2033-09-01',
            'retention_reason' => 'Company retention policy', 'notes' => 'Signed by both parties.',
        ], $overrides);
    }

    private function document(array $overrides = []): Document
    {
        return Document::create([
            ...$this->payload(), 'uploaded_by' => $this->admin->id, 'uploader_name' => $this->admin->name,
            'employee_name' => $this->employee->name, 'upload_date' => today(), ...$overrides,
        ]);
    }

    public function test_page_preserves_prototype_controls_and_opens_the_creation_modal(): void
    {
        $this->get(route('admin.contracts.index'))->assertOk()
            ->assertSee('Documents')->assertSee('Upload Document')->assertSee('Retention metadata')
            ->assertSee('id="documentsTable"', false)->assertSee('id="docKpiRow"', false)
            ->assertSee('Uploaded By')->assertSee('Classification')->assertSee('Document file')
            ->assertSee('document-library.js')->assertDontSee('assets/js/storage.js')->assertDontSee('HR.db')
            ->assertViewHas('filters', fn ($filters) => $filters['category'] === 'Employment Contract');
        $this->get(route('admin.contracts.create'))->assertRedirect(route('admin.contracts.index', ['new' => 1]));
    }

    public function test_metadata_and_audit_are_saved_with_server_owned_attribution(): void
    {
        $response = $this->postJson(route('admin.contracts.store'), $this->payload([
            'uploaded_by' => $this->employee->id, 'uploader_name' => 'Forged name', 'status' => 'Archived',
            'archive_status' => 'Archived', 'version' => 99, 'file_path' => 'secret.txt',
        ]))->assertCreated()->assertJsonPath('message', 'Signed employment contract has been added to the library.')
            ->assertSessionMissing('success');
        $document = Document::findOrFail($response->json('id'));
        foreach ($this->payload() as $field => $value) {
            $stored = $document->getAttribute($field);
            $this->assertEquals($value, $stored instanceof \DateTimeInterface ? $stored->format('Y-m-d') : $stored);
        }
        $this->assertEquals($this->admin->id, $document->uploaded_by);
        $this->assertEquals('Valid', $document->status);
        $this->assertEquals('Active', $document->archive_status);
        $this->assertEquals(1, $document->version);
        $this->assertNull($document->file_path);
        $this->assertDatabaseHas('document_activities', ['document_id' => $document->id, 'actor_id' => $this->admin->id, 'action' => 'Document created']);
        $this->assertSame($document->title, json_decode(DB::table('document_activities')->value('metadata'), true)['title']);
        $this->assertEquals($document->id, $this->employee->documents()->first()->id);
    }

    public function test_company_wide_document_and_redirect_toast_work_without_an_attachment(): void
    {
        $this->post(route('admin.contracts.store'), $this->payload(['employee_id' => null, 'issue_date' => null, 'expiry_date' => null, 'retention_until' => null]))
            ->assertRedirect(route('admin.contracts.index', ['category' => 'Employment Contract']))
            ->assertSessionHas('toast_title', 'Document saved')->assertSessionHas('success');
        $this->get(route('admin.contracts.index'))->assertOk()->assertSee('HR.toast({', false);
        $this->getJson(route('admin.contracts.index'))->assertJsonPath('rows.0.employee', 'Company-wide')->assertJsonPath('rows.0.download_url', null);
        $this->get(route('admin.contracts.download', Document::first()))->assertNotFound();
    }

    public function test_pdf_is_stored_privately_and_downloaded_only_through_the_admin_route(): void
    {
        $contents = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF";
        $this->postJson(route('admin.contracts.store'), $this->payload(['attachment' => UploadedFile::fake()->createWithContent('signed-contract.pdf', $contents)]))->assertCreated();
        $document = Document::firstOrFail();
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertStringStartsWith('contracts/', $document->file_path);
        $this->assertNotSame('contracts/signed-contract.pdf', $document->file_path);
        $this->get(route('admin.contracts.download', $document))->assertOk()->assertDownload('signed-contract.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($contents);
        $this->getJson(route('admin.contracts.index'))->assertJsonPath('rows.0.download_url', route('admin.contracts.download', $document))
            ->assertDontSee($document->file_path)->assertDontSee('file_path');
        Storage::disk('local')->delete($document->file_path);
        $this->get(route('admin.contracts.download', $document))->assertNotFound();
    }

    public function test_unsupported_disguised_and_oversized_files_are_rejected(): void
    {
        $disguisedPath = tempnam(sys_get_temp_dir(), 'contract-file-test-');
        file_put_contents($disguisedPath, '<?php echo "bad";');
        try {
            foreach ([
                new UploadedFile($disguisedPath, 'script.pdf', null, null, true),
                UploadedFile::fake()->createWithContent('script.php', "%PDF-1.4\n%%EOF"),
                UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
            ] as $file) {
                $this->postJson(route('admin.contracts.store'), $this->payload(['attachment' => $file]))
                    ->assertUnprocessable()->assertJsonValidationErrors('attachment');
            }
        } finally {
            unlink($disguisedPath);
        }
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_validation_rejects_invalid_dates_enums_and_non_employee_associations(): void
    {
        foreach ([
            ['title' => '   '], ['employee_id' => $this->admin->id], ['employee_id' => 99999],
            ['category' => 'Invalid'], ['access_classification' => 'Public'], ['retention_category' => 'Invalid'],
            ['expiry_date' => '2026-08-31'], ['retention_until' => '2026-08-31'], ['issue_date' => '2026-02-30'],
        ] as $invalid) {
            $this->postJson(route('admin.contracts.store'), $this->payload($invalid))->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_activities', 0);
    }

    public function test_redirect_validation_preserves_form_values(): void
    {
        $this->from(route('admin.contracts.index'))->post(route('admin.contracts.store'), $this->payload(['expiry_date' => '2026-08-01']))
            ->assertRedirect(route('admin.contracts.index'))->assertSessionHasErrors('expiry_date')
            ->assertSessionHasInput('title', 'Signed employment contract')->assertSessionMissing('success');
        $this->get(route('admin.contracts.index'))->assertOk()->assertSee('Signed employment contract');
    }

    public function test_expiry_boundaries_reviews_and_archival_use_one_consistent_status(): void
    {
        foreach ([-1 => 'Expired', 0 => 'Expiring Soon', 14 => 'Expiring Soon', 15 => 'Review Due', 90 => 'Review Due', 91 => 'Valid'] as $days => $expected) {
            $document = $this->document(['expiry_date' => today()->addDays($days)]);
            $this->assertSame($expected, $document->displayStatus());
        }
        $this->assertSame('Review Due', $this->document(['expiry_date' => null, 'review_date' => today()])->displayStatus());
        $this->assertSame('Valid', $this->document(['expiry_date' => null, 'review_date' => today()->addDay()])->displayStatus());
        $this->assertSame('Review Due', $this->document(['expiry_date' => null, 'status' => 'Review Due'])->displayStatus());
        $this->assertSame('Archived', $this->document(['expiry_date' => today()->subDay(), 'archive_status' => 'Archived'])->displayStatus());
        $this->assertSame('Archived', $this->document(['expiry_date' => today(), 'status' => 'Archived'])->displayStatus());
    }

    public function test_export_applies_category_status_employee_search_and_sort(): void
    {
        $this->document(['title' => 'Beta contract', 'expiry_date' => today()->addDays(30)]);
        $this->document(['title' => 'Alpha contract', 'expiry_date' => today()->addDays(31)]);
        $this->document(['title' => 'Exclude category', 'category' => 'Identity']);
        $this->document(['title' => 'Exclude employee', 'employee_id' => null]);
        $this->document(['title' => 'Exclude status', 'expiry_date' => today()->subDay()]);
        $csv = $this->get(route('admin.contracts.export', ['category' => 'Employment Contract', 'status' => 'Review Due', 'employee' => $this->employee->id, 'search' => 'contract', 'sort' => 0, 'direction' => 'desc']))
            ->assertOk()->assertDownload('documents.csv')->streamedContent();
        $this->assertStringContainsString('Beta contract', $csv);
        $this->assertStringContainsString('Alpha contract', $csv);
        $this->assertStringNotContainsString('Exclude', $csv);
        $this->assertLessThan(strpos($csv, 'Alpha contract'), strpos($csv, 'Beta contract'));
    }

    public function test_csv_neutralises_formulas_and_preserves_quotes(): void
    {
        $this->document(['title' => '=HYPERLINK("bad","link")']);
        $csv = $this->get(route('admin.contracts.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString('""bad""', $csv);
    }

    public function test_failed_database_save_cleans_up_uploaded_file(): void
    {
        Document::creating(fn () => throw new \RuntimeException('Simulated database failure'));
        try {
            $this->postJson(route('admin.contracts.store'), $this->payload(['attachment' => UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\n%%EOF")]))->assertStatus(500);
        } finally {
            Document::flushEventListeners();
        }
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('document_activities', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_deleting_an_employee_does_not_relabel_their_contract_as_company_wide(): void
    {
        $document = $this->document();
        $this->employee->delete();
        $this->assertNull($document->fresh()->employee_id);
        $this->getJson(route('admin.contracts.index'))->assertJsonPath('rows.0.employee', 'Contract Employee');
    }

    public function test_non_admins_cannot_read_export_upload_or_download_documents(): void
    {
        $document = $this->document();
        foreach (['employee', 'auditor', 'manager'] as $role) {
            Role::findOrCreate($role);
            $user = User::factory()->create();
            $user->assignRole($role);
            $this->actingAs($user);
            foreach (['index', 'create', 'export'] as $action) {
                $this->get(route('admin.contracts.'.$action))->assertForbidden();
            }
            $this->postJson(route('admin.contracts.store'), $this->payload())->assertForbidden();
            $this->get(route('admin.contracts.download', $document))->assertForbidden();
        }
    }
}
