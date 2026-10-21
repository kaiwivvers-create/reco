<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Criterion;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\EvaluationService;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'kai@example.com'],
            ['name' => 'Kai', 'role' => 'admin', 'is_active' => true, 'password' => '250510']
        );
        User::updateOrCreate(
            ['email' => 'chips@example.com'],
            ['name' => 'Chips', 'role' => 'analyst', 'is_active' => true, 'password' => '250510']
        );
        User::updateOrCreate(
            ['email' => 'tweek@example.com'],
            ['name' => 'Tweek', 'role' => 'viewer', 'is_active' => true, 'password' => '250510']
        );

        $defaults = [
            'analyst' => [
                'dashboard' => [1, 0],
                'suppliers' => [1, 1],
                'criteria' => [1, 1],
                'evaluations' => [1, 1],
                'reports' => [1, 1],
                'activity' => [1, 1],
                'settings' => [0, 0],
                'permissions' => [0, 0],
                'database' => [0, 0],
            ],
            'viewer' => [
                'dashboard' => [1, 0],
                'suppliers' => [1, 0],
                'criteria' => [1, 0],
                'evaluations' => [1, 0],
                'reports' => [1, 0],
                'activity' => [0, 0],
                'settings' => [0, 0],
                'permissions' => [0, 0],
                'database' => [0, 0],
            ],
        ];

        foreach (array_keys(Permissions::MODULES) as $module) {
            RolePermission::updateOrCreate(
                ['role' => 'admin', 'module' => $module],
                ['can_view' => true, 'can_manage' => true]
            );

            foreach ($defaults as $role => $modules) {
                [$view, $manage] = $modules[$module] ?? [0, 0];
                RolePermission::updateOrCreate(
                    ['role' => $role, 'module' => $module],
                    ['can_view' => (bool) $view, 'can_manage' => (bool) $manage]
                );
            }
        }

        $settings = [
            'app_name' => 'SuppliRank',
            'app_tagline' => 'Rekomendasi Supplier dengan MCDM & Explainable AI',
            'company_name' => 'PT Nusantara Procurement',
            'brand_color' => '#4f46e5',
            'default_method' => 'saw',
            'report_footer' => 'Dokumen dihasilkan otomatis oleh SuppliRank — keputusan akhir tetap berada pada tim pengadaan.',
            'ai_enabled' => '0',
            'ai_provider' => 'openai',
            'ai_base_url' => 'https://api.openai.com/v1',
            'ai_model' => 'gpt-4o-mini',
        ];

        foreach ($settings as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => 'general']);
        }

        $criteriaSeed = [
            ['code' => 'C01', 'name' => 'Harga Penawaran', 'type' => 'cost', 'weight' => 25, 'unit' => 'Rp', 'description' => 'Total harga penawaran untuk paket pengadaan. Semakin rendah semakin baik.', 'sort_order' => 1],
            ['code' => 'C02', 'name' => 'Kualitas Produk', 'type' => 'benefit', 'weight' => 30, 'unit' => 'skor 1-5', 'description' => 'Penilaian kualitas barang berdasarkan sampel dan sertifikasi.', 'sort_order' => 2],
            ['code' => 'C03', 'name' => 'Waktu Pengiriman', 'type' => 'cost', 'weight' => 30, 'unit' => 'hari', 'description' => 'Estimasi waktu pengiriman dalam hari kerja. Semakin cepat semakin baik.', 'sort_order' => 3],
            ['code' => 'C04', 'name' => 'Keandalan Supplier', 'type' => 'benefit', 'weight' => 15, 'unit' => 'skor 1-5', 'description' => 'Rekam jejak ketepatan, respons komunikasi, dan kondisi keuangan supplier.', 'sort_order' => 4],
        ];

        foreach ($criteriaSeed as $row) {
            Criterion::firstOrCreate(['code' => $row['code']], $row);
        }

        $supplierSeed = [
            ['code' => 'SUP-001', 'name' => 'PT Sinar Office Nusantara', 'contact_person' => 'Rangga Wijaya', 'email' => 'sales@sinaroffice.co.id', 'phone' => '021-5550101', 'address' => 'Jl. Industri Raya No. 12, Jakarta Timur', 'scores' => ['C01' => 11000000, 'C02' => 5, 'C03' => 7, 'C04' => 5]],
            ['code' => 'SUP-002', 'name' => 'CV Mitra Kantor Sejahtera', 'contact_person' => 'Dewi Anggraini', 'email' => 'penawaran@mitrakantor.co.id', 'phone' => '021-5550202', 'address' => 'Jl. Gatot Subroto No. 88, Bandung', 'scores' => ['C01' => 12000000, 'C02' => 5, 'C03' => 3, 'C04' => 3]],
            ['code' => 'SUP-003', 'name' => 'PT Global Furnitur Prima', 'contact_person' => 'Hendra Kusuma', 'email' => 'corporate@globalfurnitur.co.id', 'phone' => '031-5550303', 'address' => 'Jl. Rungkut Industri No. 45, Surabaya', 'scores' => ['C01' => 18000000, 'C02' => 3, 'C03' => 4, 'C04' => 5]],
        ];

        foreach ($supplierSeed as $row) {
            $scores = $row['scores'];
            unset($row['scores']);
            $supplier = Supplier::firstOrCreate(['code' => $row['code']], array_merge($row, ['is_active' => true]));

            foreach ($scores as $code => $value) {
                $criterion = Criterion::where('code', $code)->first();
                if ($criterion) {
                    $supplier->scores()->updateOrCreate(['criterion_id' => $criterion->id], ['value' => $value]);
                }
            }
        }

        if (\App\Models\Evaluation::count() === 0) {
            $criteria = Criterion::active()->ordered()->get()->map(fn (Criterion $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'type' => $c->type,
                'weight' => $c->weight,
                'unit' => $c->unit,
            ])->all();

            app(EvaluationService::class)->persist([
                'name' => 'Pengadaan Peralatan Kantor Q1 2026',
                'method' => 'saw',
                'notes' => 'Evaluasi pengadaan meja, kursi, dan lemari arsip untuk kantor pusat. Data penawaran diterima 12 Januari 2026.',
                'criteria' => $criteria,
                'supplier_ids' => Supplier::pluck('id')->all(),
            ], $admin->id);
        }

        if (ActivityLog::count() === 0) {
            $supplierNames = Supplier::orderBy('id')->get();
            $evaluation = \App\Models\Evaluation::latest('id')->first();

            foreach ($supplierNames as $supplier) {
                ActivityLog::create([
                    'user_id' => $admin->id,
                    'user_name' => $admin->name,
                    'action' => 'created',
                    'model_type' => 'supplier',
                    'model_id' => $supplier->id,
                    'label' => $supplier->name,
                    'new_values' => [
                        'code' => $supplier->code,
                        'name' => $supplier->name,
                        'contact_person' => $supplier->contact_person,
                        'email' => $supplier->email,
                        'is_active' => true,
                    ],
                    'description' => 'Data supplier awal dimasukkan',
                    'ip_address' => '127.0.0.1',
                    'created_at' => now()->subDays(3),
                ]);
            }

            if ($evaluation) {
                ActivityLog::create([
                    'user_id' => $admin->id,
                    'user_name' => $admin->name,
                    'action' => 'created',
                    'model_type' => 'evaluation',
                    'model_id' => $evaluation->id,
                    'label' => $evaluation->name,
                    'new_values' => ['name' => $evaluation->name, 'method' => 'SAW'],
                    'description' => 'Evaluasi supplier dijalankan',
                    'ip_address' => '127.0.0.1',
                    'created_at' => now()->subDay(),
                ]);
            }
        }
    }
}
