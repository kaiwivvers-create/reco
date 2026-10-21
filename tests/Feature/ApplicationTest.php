<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DatabaseService;
use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    protected function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_guests_cannot_access_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_demo_accounts_can_log_in_with_the_shared_password(): void
    {
        $accounts = [
            'kai@example.com' => 'admin',
            'chips@example.com' => 'analyst',
            'tweek@example.com' => 'viewer',
        ];

        foreach ($accounts as $email => $role) {
            $this->post(route('login.attempt'), [
                'email' => $email,
                'password' => '250510',
            ])->assertRedirect(route('dashboard'));

            $this->assertAuthenticatedAs(User::where('email', $email)->firstOrFail());
            $this->assertSame($role, User::where('email', $email)->firstOrFail()->role);

            $this->post(route('logout'));
            $this->assertGuest();
        }
    }

    public function test_admin_can_open_all_main_pages(): void
    {
        $this->actingAs($this->admin());

        $pages = [
            route('dashboard'),
            route('suppliers.index'),
            route('suppliers.create'),
            route('suppliers.show', 1),
            route('criteria.index'),
            route('evaluations.index'),
            route('evaluations.create'),
            route('evaluations.show', 1),
            route('reports.index'),
            route('reports.show', 1),
            route('reports.print', 1),
            route('activity.index'),
            route('settings.index'),
            route('permissions.index'),
            route('database.index'),
        ];

        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_viewer_is_restricted_from_management_pages(): void
    {
        $viewer = User::where('email', 'tweek@example.com')->firstOrFail();

        $this->actingAs($viewer);
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('suppliers.index'))->assertOk();
        $this->get(route('suppliers.create'))->assertForbidden();
        $this->get(route('activity.index'))->assertForbidden();
        $this->get(route('settings.index'))->assertForbidden();
        $this->get(route('permissions.index'))->assertForbidden();
        $this->get(route('database.index'))->assertForbidden();
    }

    public function test_supplier_crud_revert_and_restore_flow(): void
    {
        $this->actingAs($this->admin());
        $criteria = Criterion::active()->orderBy('id')->get();
        $scores = [];
        foreach ($criteria as $criterion) {
            $scores[$criterion->id] = 4;
        }
        $scores[$criteria[0]->id] = 12000000;

        $this->post(route('suppliers.store'), [
            'code' => 'SUP-999',
            'name' => 'Supplier Uji',
            'contact_person' => 'Tester',
            'email' => 'uji@example.test',
            'phone' => '021-000',
            'scores' => $scores,
            'is_active' => '1',
        ])->assertRedirect(route('suppliers.index'));

        $supplier = Supplier::where('code', 'SUP-999')->firstOrFail();
        $this->assertSame(4, $supplier->scores()->count());
        $this->assertDatabaseHas('activity_logs', [
            'model_type' => 'supplier',
            'model_id' => $supplier->id,
            'action' => 'created',
        ]);

        $this->put(route('suppliers.update', $supplier), [
            'code' => 'SUP-999',
            'name' => 'Supplier Uji Diubah',
            'scores' => $scores,
            'is_active' => '1',
        ])->assertRedirect(route('suppliers.show', $supplier));

        $supplier->refresh();
        $this->assertSame('Supplier Uji Diubah', $supplier->name);

        $updateLog = ActivityLog::where('model_type', 'supplier')
            ->where('model_id', $supplier->id)
            ->where('action', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->post(route('activity.revert', $updateLog))->assertRedirect();
        $supplier->refresh();
        $this->assertSame('Supplier Uji', $supplier->name);
        $this->assertTrue($updateLog->fresh()->is_reverted);

        $this->delete(route('suppliers.destroy', $supplier))->assertRedirect(route('suppliers.index'));
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);

        $deleteLog = ActivityLog::where('model_type', 'supplier')
            ->where('model_id', $supplier->id)
            ->where('action', 'deleted')
            ->latest('id')
            ->firstOrFail();

        $this->post(route('activity.restore', $deleteLog))->assertRedirect();
        $this->assertNotSoftDeleted('suppliers', ['id' => $supplier->id]);

        $this->delete(route('suppliers.destroy', $supplier));
        $secondDelete = ActivityLog::where('model_type', 'supplier')
            ->where('model_id', $supplier->id)
            ->where('action', 'deleted')
            ->latest('id')
            ->firstOrFail();

        $this->post(route('activity.force-delete', $secondDelete))->assertRedirect();
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'force_deleted']);
    }

    public function test_creating_an_evaluation_stores_ranking_and_explanations(): void
    {
        $this->actingAs($this->admin());
        $criteria = Criterion::active()->orderBy('id')->get();

        $payload = [
            'name' => 'Evaluasi Uji Otomatis',
            'method' => 'topsis',
            'notes' => 'Dibuat oleh test',
            'supplier_ids' => Supplier::pluck('id')->all(),
            'criteria_ids' => $criteria->pluck('id')->all(),
            'weights' => $criteria->mapWithKeys(fn ($c) => [$c->id => $c->weight])->all(),
        ];

        $response = $this->post(route('evaluations.store'), $payload);
        $response->assertRedirect();

        $evaluation = Evaluation::where('name', 'Evaluasi Uji Otomatis')->firstOrFail();
        $this->assertSame('topsis', $evaluation->method);
        $this->assertCount(3, $evaluation->results);
        $this->assertNotNull($evaluation->explanation);
        $this->assertSame('CV Mitra Kantor Sejahtera', $evaluation->explanation['winner']['name']);
        $this->assertSame(1, $evaluation->results->first()->rank);

        $this->get(route('evaluations.show', $evaluation))
            ->assertOk()
            ->assertSee('Analisis kontrafaktual')
            ->assertSee('Sensitivitas bobot');
    }

    public function test_reports_can_be_exported_as_pdf_excel_and_printed(): void
    {
        $this->actingAs($this->admin());
        $evaluation = Evaluation::firstOrFail();

        $pdf = $this->get(route('reports.pdf', $evaluation));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringContainsString('%%EOF', $pdf->getContent());

        $excel = $this->get(route('reports.excel', $evaluation));
        $excel->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $excel->headers->get('content-type')
        );
        $this->assertStringStartsWith('PK', $excel->getContent());

        $this->get(route('reports.print', $evaluation))
            ->assertOk()
            ->assertSee('Cetak / Simpan PDF');
    }

    public function test_database_can_be_exported_and_imported(): void
    {
        $this->actingAs($this->admin());

        $json = $this->get(route('database.export', 'json'));
        $json->assertOk();
        $decoded = json_decode($json->getContent(), true);
        $this->assertArrayHasKey('tables', $decoded);
        $this->assertArrayHasKey('suppliers', $decoded['tables']);

        $this->get(route('database.export', 'sql'))->assertOk();
        $this->get(route('database.export-table', 'suppliers'))->assertOk();
        $this->get(route('database.table', 'suppliers'))
            ->assertOk()
            ->assertSee('PT Sinar Office Nusantara');
        $this->get(route('database.table', 'migrations'))->assertNotFound();

        $path = tempnam(sys_get_temp_dir(), 'sr-backup').'.json';
        file_put_contents($path, $json->getContent());
        $file = new UploadedFile($path, 'backup.json', 'application/json', null, true);

        $this->post(route('database.import'), ['file' => $file, 'mode' => 'merge'])
            ->assertRedirect(route('database.index'));

        $this->assertDatabaseHas('activity_logs', ['action' => 'imported']);
        @unlink($path);
    }

    public function test_settings_can_be_updated_and_are_logged(): void
    {
        $this->actingAs($this->admin());

        $this->put(route('settings.update'), [
            'app_name' => 'SuppliRank Uji',
            'app_tagline' => 'Tagline uji',
            'company_name' => 'PT Uji',
            'brand_color' => '#0ea5e9',
            'default_method' => 'topsis',
            'report_footer' => 'Footer uji',
            'ai_enabled' => '0',
            'ai_provider' => 'openai',
            'ai_base_url' => 'https://api.openai.com/v1',
            'ai_model' => 'gpt-4o-mini',
        ])->assertRedirect(route('settings.index'));

        $this->assertSame('SuppliRank Uji', Settings::get('app_name'));
        $this->assertSame('#0ea5e9', Settings::get('brand_color'));
        $this->assertDatabaseHas('activity_logs', ['model_type' => 'setting']);

        $this->postJson(route('evaluations.ai', Evaluation::firstOrFail()))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Fitur narasi AI belum aktif. Aktifkan di Pengaturan Aplikasi dan isi API key.');
    }

    public function test_setting_changes_can_be_reverted_from_activity_history(): void
    {
        $this->actingAs($this->admin());
        $original = Settings::get('app_name');

        $this->put(route('settings.update'), [
            'app_name' => 'Nama Sementara',
            'app_tagline' => 'Tagline uji',
            'company_name' => 'PT Uji',
            'brand_color' => '#0ea5e9',
            'default_method' => 'saw',
            'report_footer' => 'Footer uji',
            'ai_enabled' => '0',
            'ai_provider' => 'openai',
            'ai_base_url' => 'https://api.openai.com/v1',
            'ai_model' => 'gpt-4o-mini',
        ])->assertRedirect(route('settings.index'));

        $this->assertSame('Nama Sementara', Settings::get('app_name'));

        $log = ActivityLog::where('model_type', 'setting')->where('action', 'updated')->latest('id')->firstOrFail();

        $this->post(route('activity.revert', $log))->assertRedirect();

        $this->assertSame($original, Settings::get('app_name'));
        $this->assertTrue($log->fresh()->is_reverted);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'reverted',
            'model_type' => 'setting',
        ]);
    }

    public function test_permission_changes_can_be_reverted_from_activity_history(): void
    {
        $viewer = User::where('email', 'tweek@example.com')->firstOrFail();
        $admin = $this->admin();

        $matrix = [];
        foreach (RolePermission::where('role', '!=', 'admin')->get() as $row) {
            $matrix[$row->role][$row->module] = [
                'view' => $row->can_view ? 1 : 0,
                'manage' => $row->can_manage ? 1 : 0,
            ];
        }
        $baseline = $matrix;
        unset($matrix['viewer']['reports']);

        $this->actingAs($admin)->put(route('permissions.update'), ['permissions' => $matrix])
            ->assertRedirect(route('permissions.index'));

        Permissions::flush();
        $this->actingAs($viewer)->get(route('reports.index'))->assertForbidden();

        $log = ActivityLog::where('model_type', 'permission')->latest('id')->firstOrFail();

        $this->actingAs($admin)->post(route('activity.revert', $log))->assertRedirect();

        Permissions::flush();
        $this->assertTrue(RolePermission::where('role', 'viewer')->where('module', 'reports')->firstOrFail()->can_view);
        $this->actingAs($viewer)->get(route('reports.index'))->assertOk();
        $this->assertTrue($log->fresh()->is_reverted);
        $this->assertSame($baseline['viewer']['reports']['view'], 1);
    }

    public function test_permission_matrix_controls_role_access(): void
    {
        $viewer = User::where('email', 'tweek@example.com')->firstOrFail();
        $this->actingAs($viewer);
        $this->get(route('reports.index'))->assertOk();

        $this->actingAs($this->admin());

        $matrix = [];
        foreach (RolePermission::where('role', '!=', 'admin')->get() as $row) {
            $matrix[$row->role][$row->module] = [
                'view' => $row->can_view ? 1 : 0,
                'manage' => $row->can_manage ? 1 : 0,
            ];
        }
        unset($matrix['viewer']['reports']);

        $this->put(route('permissions.update'), ['permissions' => $matrix])
            ->assertRedirect(route('permissions.index'));

        Permissions::flush();

        $this->actingAs($viewer);
        $this->get(route('reports.index'))->assertForbidden();

        $entry = RolePermission::where('role', 'viewer')->where('module', 'reports')->firstOrFail();
        $this->assertFalse($entry->can_view);
        $this->assertFalse($entry->can_manage);
    }

    public function test_evaluation_rejects_incomplete_supplier_scores(): void
    {
        $this->actingAs($this->admin());
        $criteria = Criterion::active()->orderBy('id')->get();
        $suppliers = Supplier::all();

        $suppliers->first()->scores()->where('criterion_id', $criteria->first()->id)->delete();

        $this->post(route('evaluations.store'), [
            'name' => 'Evaluasi Data Tidak Lengkap',
            'method' => 'saw',
            'supplier_ids' => $suppliers->pluck('id')->all(),
            'criteria_ids' => $criteria->pluck('id')->all(),
            'weights' => $criteria->mapWithKeys(fn ($c) => [$c->id => $c->weight])->all(),
        ])->assertSessionHasErrors('supplier_ids');

        $this->assertDatabaseMissing('evaluations', ['name' => 'Evaluasi Data Tidak Lengkap']);
    }

    public function test_evaluation_requires_at_least_two_suppliers(): void
    {
        $this->actingAs($this->admin());
        $criteria = Criterion::active()->take(2)->get();
        $supplier = Supplier::firstOrFail();

        $this->post(route('evaluations.store'), [
            'name' => 'Evaluasi Tidak Valid',
            'method' => 'saw',
            'supplier_ids' => [$supplier->id],
            'criteria_ids' => $criteria->pluck('id')->all(),
            'weights' => $criteria->mapWithKeys(fn ($c) => [$c->id => $c->weight])->all(),
        ])->assertSessionHasErrors('supplier_ids');

        $this->assertDatabaseMissing('evaluations', ['name' => 'Evaluasi Tidak Valid']);
    }
}
