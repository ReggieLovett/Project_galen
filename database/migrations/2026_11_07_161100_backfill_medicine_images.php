<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Assign the bundled product photos to existing catalogue rows.
     */
    public function up(): void
    {
        foreach ($this->imagePaths() as $name => $path) {
            DB::table('medicines')
                ->where('name', $name)
                ->update(['image_path' => $path]);
        }
    }

    /**
     * Remove only the values introduced by this migration.
     */
    public function down(): void
    {
        DB::table('medicines')
            ->whereIn('name', array_keys($this->imagePaths()))
            ->update(['image_path' => null]);
    }

    /**
     * @return array<string, string>
     */
    private function imagePaths(): array
    {
        return [
            'Biogesic' => 'Images/Biogesic.jpg',
            'RiteMed Paracetamol' => 'Images/RiteMed Paracetamol.jpg',
            'Panadol' => 'Images/Panadol.jpg',
            'Advil' => 'Images/Advil.jpg',
            'Medicol Advance' => 'Images/Medicol Advance.png',
            'RiteMed Ibuprofen' => 'Images/RiteMed Ibuprofen.png',
            'Alaxan FR' => 'Images/Alaxan FR.png',
            'Dolfenal' => 'Images/Dolfenal.png',
            'Neozep Forte' => 'Images/Neozep Forte.png',
            'Bioflu' => 'Images/Bioflu.png',
            'Solmux' => 'Images/Solmux.png',
            'RiteMed Carbocisteine' => 'Images/RiteMed Carbocisteine.png',
            'Ascof Lagundi' => 'Images/Ascof Lagundi.png',
            'Claritin' => 'Images/Claritin.png',
            'Allerta' => 'Images/Allerta.jpg',
            'RiteMed Loratadine' => 'Images/RiteMed Loratadine.png',
            'Virlix' => 'Images/Virlix.png',
            'Kremil-S' => 'Images/Kremil-S.png',
            'Diatabs' => 'Images/Diatabs.png',
            'Imodium' => 'Images/Imodium.png',
            'Buscopan' => 'Images/Buscopan.png',
            'Poten-Cee' => 'Images/Poten-Cee.png',
            'RiteMed Ascorbic Acid' => 'Images/RiteMed Ascorbic Acid.png',
            'Fern-C' => 'Images/Fern-C.png',
            'Amoxil' => 'Images/Amoxil.png',
            'RiteMed Amoxicillin' => 'Images/RiteMed Amoxicillin.png',
            'Augmentin' => 'Images/Augmentin.png',
            'RiteMed Co-Amoxiclav' => 'Images/RiteMed Co-Amoxiclav.png',
            'Norvasc' => 'Images/Norvasc.png',
            'RiteMed Amlodipine' => 'Images/RiteMed Amlodipine.webp',
            'Cozaar' => 'Images/Cozaar.png',
            'RiteMed Losartan' => 'Images/RiteMed Losartan.png',
            'Lipitor' => 'Images/Lipitor.png',
            'RiteMed Atorvastatin' => 'Images/RiteMed Atorvastatin.png',
            'Glucophage' => 'Images/Glucophage.png',
            'RiteMed Metformin' => 'Images/RiteMed Metformin.png',
        ];
    }
};
