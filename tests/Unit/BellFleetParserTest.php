<?php

namespace Tests\Unit;

use App\Services\Integration\BellFleetParser;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Direct fixture tests for the pure parser extracted in refactor R2 --
 * the live-API traps BellService learned the hard way, now pinned
 * without HTTP or cache in the loop.
 */
class BellFleetParserTest extends TestCase
{
    private BellFleetParser $parser;

    protected function setUp(): void
    {
        $this->parser = new BellFleetParser;
    }

    private function equipment(string $inner): SimpleXMLElement
    {
        return new SimpleXMLElement("<Equipment><EquipmentHeader><EquipmentID>ASA B50E#1</EquipmentID></EquipmentHeader>{$inner}</Equipment>");
    }

    public function test_colliding_section_child_names_stay_section_scoped(): void
    {
        $metric = $this->parser->buildCurrentMetric($this->equipment(
            '<CumulativeIdleHours datetime="2026-06-02T11:00:00Z"><Hour>111.5</Hour></CumulativeIdleHours>'
            .'<CumulativeOperatingHours datetime="2026-06-02T11:00:00Z"><Hour>999.25</Hour></CumulativeOperatingHours>'
        ));

        $this->assertSame(999.25, $metric['operating_hours']);
        $this->assertSame(111.5, $metric['idle_hours']);
    }

    public function test_fuel_ratio_normalises_to_percent_and_invalid_percent_is_rejected(): void
    {
        $ratio = $this->parser->buildCurrentMetric($this->equipment(
            '<FuelRemaining datetime="2026-06-02T11:00:00Z"><Percent>0.61</Percent></FuelRemaining>'
        ));
        $this->assertSame(61.0, $ratio['fuel_level']);

        $overflow = $this->parser->buildCurrentMetric($this->equipment(
            '<FuelRemaining datetime="2026-06-02T11:00:00Z"><Percent>340</Percent></FuelRemaining>'
        ));
        $this->assertNull($overflow['fuel_level']);
    }

    public function test_out_of_range_coordinates_never_become_a_location(): void
    {
        $machine = $this->parser->parseEquipmentNode($this->equipment(
            '<Location datetime="2026-06-02T11:00:00Z"><Latitude>-95.5</Latitude><Longitude>28.9</Longitude></Location>'
        ));

        $this->assertNull($machine['last_location']);
    }

    public function test_engine_state_maps_to_app_status_vocabulary(): void
    {
        $running = $this->parser->parseEquipmentNode($this->equipment(
            '<EngineStatus datetime="2026-06-02T11:00:00Z"><Running>true</Running></EngineStatus>'
        ));
        $idle = $this->parser->parseEquipmentNode($this->equipment(
            '<EngineStatus datetime="2026-06-02T11:00:00Z"><Running>false</Running></EngineStatus>'
        ));
        $unknown = $this->parser->parseEquipmentNode($this->equipment(''));

        $this->assertSame('active', $running['status']);
        $this->assertSame('idle', $idle['status']);
        $this->assertSame('unknown', $unknown['status']);
    }
}
