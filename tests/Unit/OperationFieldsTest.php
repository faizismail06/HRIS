<?php

namespace Tests\Unit;

use App\Support\OperationFields;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationFieldsTest extends TestCase
{
    public function test_money_and_reference_values_preserve_precision_and_ignore_extra_keys(): void
    {
        $fields = [OperationFields::field('amount', 'Nominal', 'money'), OperationFields::field('employee_id', 'Karyawan', 'lookup')];
        $this->assertSame(['amount' => 999999999999999, 'employee_id' => 21], OperationFields::normalize($fields, ['amount' => '9999999999999.99', 'employee_id' => '21', 'is_admin' => true]));
        $this->assertSame(-101, OperationFields::normalize($fields, ['amount' => '-1.01'])['amount']);
    }

    public function test_optional_empty_groups_are_omitted_but_false_and_zero_survive(): void
    {
        $fields = [OperationFields::field('calendar', 'Kalender', 'group', false, ['fields' => [OperationFields::field('days', 'Hari', 'lines')]]), OperationFields::field('flag', 'Pilihan', 'boolean'), OperationFields::field('version', 'Versi', 'profile-version')];
        $this->assertSame(['flag' => false, 'version' => 0], OperationFields::normalize($fields, ['flag' => '0', 'version' => '0', 'calendar' => ['days' => '']]));
    }

    public function test_instant_is_converted_from_selected_timezone_and_invalid_dates_rejected(): void
    {
        $fields = [OperationFields::field('at', 'Waktu', 'instant')];
        $this->assertSame('2026-10-19T11:00:00+08:00', OperationFields::normalize($fields, ['at' => '2026-10-19T11:00'], 'Asia/Makassar')['at']);
        $this->expectException(ValidationException::class);
        OperationFields::normalize($fields, ['at' => '2026-02-30T11:00']);
    }

    public function test_nested_array_in_scalar_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        OperationFields::normalize([OperationFields::field('reason', 'Alasan')], ['reason' => ['forged']]);
    }
}
