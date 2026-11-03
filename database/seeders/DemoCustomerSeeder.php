<?php

namespace Database\Seeders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
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
     *   Never bought: Amoxil, Zithromax, Norvasc, Zestril, Ventolin
     *
     * Daniel Okafor (card 100318)
     *   Has bought: Ventolin HFA (prescription), Zyrtec
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
            'Glucophage' => 1,
            'Tylenol Extra Strength' => 1,
        ]);
        $this->pastOrder($maria, 'C260906-0031', now()->subMonths(2), [
            'Glucophage' => 1,
            'Lipitor' => 1,
        ]);
        $this->pastOrder($maria, 'C261007-0022', now()->subMonth(), [
            'Glucophage' => 1,
            'Lipitor' => 1,
            'Claritin 24-Hour' => 1,
        ]);

        $daniel = User::factory()->create([
            'member_number' => '100318',
            'name' => 'Daniel Okafor',
            'email' => 'daniel.okafor@example.test',
        ]);

        $this->pastOrder($daniel, 'C260921-0009', now()->subWeeks(6), [
            'Ventolin HFA' => 1,
            'Zyrtec Allergy' => 2,
        ]);
    }

    /**
     * @param  array<string, int>  $lines  medicine name => quantity
     */
    private function pastOrder(User $customer, string $number, Carbon $date, array $lines): void
    {
        $medicines = Medicine::whereIn('name', array_keys($lines))->get()->keyBy('name');

        $order = $customer->orders()->create([
            'order_number' => $number,
            'channel' => OrderChannel::Counter,
            'status' => OrderStatus::Completed,
            'item_count' => array_sum($lines),
            'total' => $medicines->sum(fn (Medicine $medicine) => $medicine->price * $lines[$medicine->name]),
            'completed_at' => $date,
        ]);

        foreach ($lines as $name => $quantity) {
            $medicine = $medicines[$name];

            $order->items()->create([
                'medicine_id' => $medicine->id,
                'name' => $medicine->name,
                'brand' => $medicine->brand,
                'strength' => $medicine->strength,
                'unit_price' => $medicine->price,
                'quantity' => $quantity,
                'line_total' => $medicine->price * $quantity,
            ]);
        }

        $order->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
    }
}
