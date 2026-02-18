<?php

namespace Tests\Unit;

use App\Jobs\GetInvestmentPrices;
use App\Models\Investment;
use Tests\TestCase;

class GetInvestmentPricesTest extends TestCase
{
    public function test_job_has_retry_configuration(): void
    {
        $job = new GetInvestmentPrices(new Investment());

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff());
    }
}
