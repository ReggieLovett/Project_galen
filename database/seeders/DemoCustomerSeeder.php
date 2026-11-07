<?php

namespace Database\Seeders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\Packaging;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoCustomerSeeder extends Seeder
{
    /**
     * Seed loyalty members with past counter purchases.
     *
     * Maria Santos (card 100245)
     *   Has bought: Glucophage 500 mg and Lipitor 20 mg (prescription)
     *   Never bought: Amoxil, Augmentin, Norvasc, Cozaar
     *
     * Daniel Okafor (card 100318)
     *   Has bought: Cozaar 50 mg (prescription), Virlix
     *   Never bought: anything else on prescription
     */
    public function run(): void
    {
        $maria = User::factory()->create([
            'member_number' => '100245',
            'name' => 'Maria Santos',
            'email' => 'maria.santos@example.test',
        ]);

        $this->pastOrder($maria, 'C260805-0014', now()->subMonths(3), [
            'Glucophage' => [Packaging::Box, 1],
            'Biogesic' => [Packaging::Strip, 2],
        ]);
        $this->pastOrder($maria, 'C260906-0031', now()->subMonths(2), [
            'Glucophage' => [Packaging::Box, 1],
            'Lipitor' => [Packaging::Box, 1],
        ]);
        $this->pastOrder($maria, 'C261007-0022', now()->subMonth(), [
            'Glucophage' => [Packaging::Box, 1],
            'Lipitor' => [Packaging::Box, 1],
            'Claritin' => [Packaging::Strip, 1],
        ]);

        $daniel = User::factory()->create([
            'member_number' => '100318',
            'name' => 'Daniel Okafor',
            'email' => 'daniel.okafor@example.test',
        ]);

        $this->pastOrder($daniel, 'C260921-0009', now()->subWeeks(6), [
            'Cozaar' => [Packaging::Box, 1],
            'Virlix' => [Packaging::Strip, 2],
        ]);
    }

    /**
     * @param  array<string, array{Packaging, int}>  $lines  medicine name => [packaging, quantity]
     */
    private function pastOrder(User $customer, string $number, Carbon $date, array $lines): void
    {
        $medicines = Medicine::whereIn('name', array_keys($lines))->get()->keyBy('name');

        $items = collect($lines)->map(function (array $line, string $name) use ($medicines) {
            [$packaging, $quantity] = $line;
            $medicine = $medicines[$name];

            return [
                'medicine_id' => $medicine->id,
                'name' => $medicine->name,
                'brand' => $medicine->brand,
                'strength' => $medicine->strength,
                'packaging' => $packaging,
                'unit_price' => $medicine->priceOf($packaging),
                'quantity' => $quantity,
                'strip_count' => $quantity * $medicine->stripsPer($packaging),
                'line_total' => $medicine->priceOf($packaging) * $quantity,
            ];
        })->values();

        $order = $customer->orders()->create([
            'order_number' => $number,
            'channel' => OrderChannel::Counter,
            'status' => OrderStatus::Completed,
            'item_count' => $items->sum('quantity'),
            'total' => $items->sum('line_total'),
            'completed_at' => $date,
        ]);

        $order->items()->createMany($items->all());

        $order->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
    }
}
