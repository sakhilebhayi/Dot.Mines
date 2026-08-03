<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\MineArea;
use App\Models\FuelMonthlyAllocation;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Backfill existing FuelMonthlyAllocation rows that have NULL mine_area_id
     * by assigning a sensible default per team. If the team has no MineArea,
     * create a "General" Mine Area and associate allocations with it.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $allocations = FuelMonthlyAllocation::whereNull('mine_area_id')->get();

            foreach ($allocations as $allocation) {
                $teamId = $allocation->team_id;

                $mineArea = MineArea::where('team_id', $teamId)->first();

                if (!$mineArea) {
                    $mineArea = MineArea::create([
                        'team_id' => $teamId,
                        'name' => 'General',
                        'description' => 'Auto-created default mine area for legacy allocations',
                        'status' => 'active',
                    ]);
                }

                $allocation->mine_area_id = $mineArea->id;
                $allocation->save();
            }
        });
    }

    /**
     * Reverse the migrations.
     * This will set mine_area_id back to NULL for allocations that reference
     * a MineArea created by this script (name = 'General' and matching description),
     * and will not touch allocations that were intentionally associated.
     */
    public function down(): void
    {
        DB::transaction(function () {
            $generalAreas = MineArea::where('name', 'General')
                ->where('description', 'like', '%Auto-created default mine area%')
                ->pluck('id')
                ->toArray();

            if (!empty($generalAreas)) {
                FuelMonthlyAllocation::whereIn('mine_area_id', $generalAreas)
                    ->update(['mine_area_id' => null]);
            }
        });
    }
};
