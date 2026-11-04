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
}
