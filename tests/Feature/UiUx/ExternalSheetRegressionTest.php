<?php

namespace Tests\Feature\UiUx;

use App\Services\BankLookupService;
use App\Services\BillingManagementService;
use App\Support\ShopJobApplicationView;
use PHPUnit\Framework\TestCase;

/** DBを使わず、外部検証表で見つかった境界値の回帰を確認する。 */
class ExternalSheetRegressionTest extends TestCase
{
    public function test_invalid_bank_values_are_not_silently_truncated_or_defaulted(): void
    {
        $service = new BillingManagementService(new BankLookupService());
        $normalized = $service->normalizeBankAccountData([
            'bank_code' => '12345',
            'branch_code' => '9876',
            'account_number' => '123456789',
            'account_type' => 'invalid',
        ]);

        $this->assertSame('12345', $normalized['bank_code']);
        $this->assertSame('9876', $normalized['branch_code']);
        $this->assertSame('123456789', $normalized['account_number']);
        $this->assertSame('invalid', $normalized['account_type']);
    }

    public function test_hired_wage_uses_legacy_column_when_new_column_is_absent(): void
    {
        $row = (object) ['hourly_wage_regular' => '4500'];

        $this->assertSame('4500', ShopJobApplicationView::wageAtHire($row));
    }

    public function test_hired_wage_prefers_the_hire_time_snapshot(): void
    {
        $row = (object) [
            'hired_regular_hourly_wage' => '5200',
            'hourly_wage_regular' => '4500',
        ];

        $this->assertSame('5200', ShopJobApplicationView::wageAtHire($row));
    }
}
