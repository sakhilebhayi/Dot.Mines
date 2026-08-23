<?php

namespace App\Support;

/**
 * The vocabulary that connects a licence to a machine.
 *
 * "Is this operator allowed to drive that?" only has an answer if both sides
 * name the equipment the same way. Machines carry a free-text `machine_type`
 * that has drifted -- production holds `haul_truck` and `other`, the verify
 * database holds `adt`, and MachineController still validates the column
 * against a list of manufacturer names (volvo, cat, komatsu...) that matches
 * neither. So authorisation is decided on a normalised type resolved here,
 * not on the raw column, and an unrecognised value resolves to OTHER rather
 * than silently authorising nothing or everything.
 */
final class EquipmentType
{
    public const ADT = 'adt';

    public const HAUL_TRUCK = 'haul_truck';

    public const EXCAVATOR = 'excavator';

    public const DOZER = 'dozer';

    public const LOADER = 'loader';

    public const GRADER = 'grader';

    public const WATER_TRUCK = 'water_truck';

    public const DRILL_RIG = 'drill_rig';

    public const SUPPORT = 'support';

    public const OTHER = 'other';

    /**
     * Every type an operator can be licensed for, with the label people use.
     *
     * @var array<string, string>
     */
    public const CATALOGUE = [
        self::ADT => 'Articulated Dump Truck',
        self::HAUL_TRUCK => 'Haul Truck',
        self::EXCAVATOR => 'Excavator',
        self::DOZER => 'Dozer',
        self::LOADER => 'Loader',
        self::GRADER => 'Grader',
        self::WATER_TRUCK => 'Water Truck',
        self::DRILL_RIG => 'Drill Rig',
        self::SUPPORT => 'Support Equipment',
        self::OTHER => 'Other',
    ];

    /**
     * Spellings seen in machine.machine_type, mapped to the canonical type.
     *
     * Deliberately generous: these values were entered by people and by
     * manufacturer integrations over several years, and refusing to recognise
     * a machine is worse than recognising it under a slightly different name.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'articulated_dump_truck' => self::ADT,
        'articulated dump truck' => self::ADT,
        'dump_truck' => self::ADT,
        'dumptruck' => self::ADT,
        'adt' => self::ADT,
        'haultruck' => self::HAUL_TRUCK,
        'haul truck' => self::HAUL_TRUCK,
        'rigid_truck' => self::HAUL_TRUCK,
        'truck' => self::HAUL_TRUCK,
        'digger' => self::EXCAVATOR,
        'excavator' => self::EXCAVATOR,
        'bulldozer' => self::DOZER,
        'dozer' => self::DOZER,
        'front_end_loader' => self::LOADER,
        'fel' => self::LOADER,
        'wheel_loader' => self::LOADER,
        'loader' => self::LOADER,
        'motor_grader' => self::GRADER,
        'grader' => self::GRADER,
        'water_cart' => self::WATER_TRUCK,
        'water_truck' => self::WATER_TRUCK,
        'drill' => self::DRILL_RIG,
        'drill_rig' => self::DRILL_RIG,
        'ldv' => self::SUPPORT,
        'bakkie' => self::SUPPORT,
        'light_vehicle' => self::SUPPORT,
        'support' => self::SUPPORT,
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::CATALOGUE);
    }

    public static function exists(string $type): bool
    {
        return array_key_exists($type, self::CATALOGUE);
    }

    public static function label(string $type): string
    {
        return self::CATALOGUE[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * The canonical type for a raw machine_type value.
     */
    public static function normalise(?string $rawType): string
    {
        if ($rawType === null) {
            return self::OTHER;
        }

        $key = strtolower(trim(str_replace([' ', '-'], '_', $rawType)));

        if (self::exists($key)) {
            return $key;
        }

        return self::ALIASES[$key] ?? self::ALIASES[str_replace('_', ' ', $key)] ?? self::OTHER;
    }
}
