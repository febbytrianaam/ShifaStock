<?php

namespace Tests\Unit;

use App\Support\StockSnapshot;
use PHPUnit\Framework\TestCase;

class StockSnapshotTest extends TestCase
{
    public function test_mysql_json_key_order_does_not_change_snapshot_identity(): void
    {
        $saved = ['unit' => 'Tablet', 'quantity' => '10', 'batches' => [4 => '6', 2 => '4'], 'unit_id' => 1];
        $current = ['batches' => [2 => '4', 4 => '6'], 'unit_id' => 1, 'quantity' => '10', 'unit' => 'Tablet'];
        $this->assertTrue(StockSnapshot::matches($saved, $current));
        $current['batches'][4] = '5';
        $this->assertFalse(StockSnapshot::matches($saved, $current));
        $current['batches'][4] = 6;
        $this->assertFalse(StockSnapshot::matches($saved, $current));
    }
}
