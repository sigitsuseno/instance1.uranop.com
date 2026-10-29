<?php

namespace Tests\Feature;

use App\Modules\Supervisor\Models\SpvLedgerCuti;
use App\Modules\Supervisor\Models\SpvRequestCuti;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpvCutiTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_spv_ledger_cuti_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('spv_ledger_cuti'));

        foreach ([
            'id', 'uuid', 'employee_id', 'leave_type_id', 'leave_period_id', 'reference_id',
            'transaction_type', 'amount', 'description',
            'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('spv_ledger_cuti', $column),
                "Kolom {$column} tidak ada di spv_ledger_cuti."
            );
        }
    }

    public function test_spv_request_cuti_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('spv_request_cuti'));

        foreach ([
            'id', 'uuid', 'employee_id', 'leave_type_id', 'leave_period_id',
            'start_date', 'end_date', 'days_requested', 'sisa_cuti', 'reason', 'note',
            'status', 'approved_by', 'approved_at', 'rejection_reason',
            'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('spv_request_cuti', $column),
                "Kolom {$column} tidak ada di spv_request_cuti."
            );
        }
    }

    public function test_models_use_dedicated_tables_and_cast_attributes(): void
    {
        $ledger = new SpvLedgerCuti();
        $this->assertSame('spv_ledger_cuti', $ledger->getTable());
        $this->assertContains('transaction_type', $ledger->getFillable());

        $request = new SpvRequestCuti([
            'note' => ['catatan' => 'ok'],
            'start_date' => '2026-10-01',
        ]);
        $this->assertSame('spv_request_cuti', $request->getTable());
        $this->assertSame(['catatan' => 'ok'], $request->note);
        $this->assertSame('2026-10-01', $request->start_date->format('Y-m-d'));
    }
}
