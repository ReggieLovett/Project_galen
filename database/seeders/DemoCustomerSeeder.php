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
     * Seed the one demo loyalty member, with past counter purchases.
     * Everyone else uses the kiosk as a non-member.
     *
     * Maria Santos (card 12345678)
     *   Has bought: Glucophage 500 mg and Lipitor 20 mg (prescription)
     *   Never bought: Amoxil, Augmentin, Norvasc, Cozaar
     */
    public function run(): void
    {
        $maria = User::factory()->create([
            'member_number' => '12345678',
            'name' => 'Maria Santos',
            'email' => 'maria.santos@example.test',
        ]);

        $this->pastOrder($maria, 'C260805-0014', now()->subMonths(3), [
            'Glucophage' => [Packaging::Box, 1],
            'Biogesic' => [Packaging::Piece, 20],
        ]);
        $this->pastOrder($maria, 'C260906-0031', now()->subMonths(2), [
            'Glucophage' => [Packaging::Box, 1],
            'Lipitor' => [Packaging::Box, 1],
        ]);
        $this->pastOrder($maria, 'C261007-0022', now()->subMonth(), [
            'Glucophage' => [Packaging::Box, 1],
            'Lipitor' => [Packaging::Box, 1],
            'Claritin' => [Packaging::Piece, 10],
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
                'piece_count' => $quantity * $medicine->piecesPer($packaging),
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
