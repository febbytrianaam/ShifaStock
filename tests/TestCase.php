<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function opnameVersion(\App\Models\StockOpnameSession $session): string
    {
        $html = $this->get(route('preview.opname', ['period' => $session->period, 'session' => $session->id]))
            ->assertOk()->getContent();
        $this->assertSame(1, preg_match('/name="version" value="([^"]+)"/', $html, $matches));

        return $matches[1];
    }

    protected function nightlyObservedRevision(int $checkId): int
    {
        return $this->get(route('nightly.show', $checkId))
            ->assertOk()->viewData('observedRevision');
    }

    /** Loads the full dashboard page, then returns the Livewire component so its view data can be asserted. */
    protected function dashboard(): \Livewire\Features\SupportTesting\Testable
    {
        $this->get(route('dashboard'))->assertOk()->assertSeeLivewire(\App\Livewire\Dashboard::class);

        return \Livewire\Livewire::test(\App\Livewire\Dashboard::class);
    }

    /** Loads Monitoring Stok with the given query string, then returns the component for view-data assertions. */
    protected function monitoring(array $query = []): \Livewire\Features\SupportTesting\Testable
    {
        $this->get(route('shortages.index', $query))->assertOk()->assertSeeLivewire(\App\Livewire\StockMonitoring::class);

        return \Livewire\Livewire::withQueryParams($query)->test(\App\Livewire\StockMonitoring::class);
    }
}
