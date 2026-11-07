<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Medicine;
use Illuminate\Database\Seeder;

class MedicineSeeder extends Seeder
{
    /**
     * Product photos stored in public/Images.
     *
     * @var array<string, string>
     */
    private const IMAGE_PATHS = [
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

    /**
     * What each active ingredient is for, in plain words. Every brand with
     * the same ingredient gets the same text. These are label-style uses for
     * the customer, never dosing advice: that stays with the pharmacist.
     *
     * @var array<string, string>
     */
    private const USES = [
        'Paracetamol' => 'For fever and mild to moderate pain, such as headache, toothache, body aches and menstrual cramps.',
        'Ibuprofen' => 'For pain, swelling and fever, such as headache, toothache, muscle pain and menstrual cramps.',
        'Ibuprofen + Paracetamol' => 'Two pain relievers in one, for body and muscle pain, headache and toothache.',
        'Mefenamic Acid' => 'For mild to moderate pain, such as toothache, headache and menstrual cramps.',
        'Phenylephrine + Chlorphenamine + Paracetamol' => 'For colds and flu. Relieves a blocked or runny nose, sneezing, fever, headache and body aches.',
        'Carbocisteine' => 'Loosens thick phlegm, so a cough with phlegm is easier to clear.',
        'Lagundi Leaf' => 'A herbal medicine made from lagundi leaves, for cough.',
        'Loratadine' => 'For allergies. Relieves sneezing, a runny nose, itchy or watery eyes and itchy skin.',
        'Cetirizine' => 'For allergies. Relieves sneezing, a runny nose, itchy eyes and itchy skin rashes (hives).',
        'Aluminum Hydroxide + Magnesium Hydroxide + Simeticone' => 'An antacid for heartburn, acid indigestion, an upset stomach and gas pain.',
        'Loperamide' => 'For sudden diarrhea. It slows down loose, watery stools.',
        'Hyoscine Butylbromide' => 'For stomach cramps and tummy pain caused by spasms in the stomach or intestines.',
        'Ascorbic Acid' => 'Vitamin C, to help keep your immune system healthy.',
        'Sodium Ascorbate' => 'Vitamin C in a non-acidic form that is gentler on the stomach, to help keep your immune system healthy.',
        'Amoxicillin' => 'An antibiotic for infections caused by bacteria, such as some ear, throat, chest and urinary infections. It does not work on colds or flu.',
        'Amoxicillin + Clavulanic Acid' => 'An antibiotic combination for infections caused by bacteria, including some that amoxicillin alone cannot treat. It does not work on colds or flu.',
        'Amlodipine' => 'Lowers high blood pressure and helps prevent chest pain (angina). A maintenance medicine for long-term use.',
        'Losartan' => 'Lowers high blood pressure and helps protect the kidneys. A maintenance medicine for long-term use.',
        'Atorvastatin' => 'Lowers "bad" cholesterol to help prevent heart attack and stroke. A maintenance medicine for long-term use.',
        'Metformin' => 'Helps control blood sugar in type 2 diabetes. A maintenance medicine for long-term use.',
    ];

    /**
     * Seed the catalogue: brands sold in Philippine drugstores, priced in
     * pesos. Prices are sample figures for the demo, not a price list.
     *
     * Customers buy any number of single pieces (tablets, capsules), cut
     * from the blister strip, or whole boxes. Each row says how the pack is
     * made up (pieces per strip, strips per box) and prices one piece and
     * one box. Stock is counted in pieces.
     *
     * Prescription (Rx) medicines are the ones the Philippine FDA registers
     * as prescription drugs: antibiotics, blood pressure, cholesterol and
     * diabetes maintenance medicines.
     *
     * Seven products are out of stock so the stock assistant has something
     * to do. Each scenario it can be tested with:
     *
     *  Advil            -> Medicol Advance (same softgel), RiteMed Ibuprofen
     *                      (tablet). Never Alaxan FR, which adds paracetamol.
     *  Claritin         -> RiteMed Loratadine, Allerta. Never Virlix, which
     *                      treats the same thing with a different ingredient.
     *  Bioflu           -> Neozep Forte (same three ingredients)
     *  Imodium          -> Diatabs
     *  Buscopan         -> nothing in stock; the assistant says so
     *  Lipitor (Rx)     -> RiteMed Atorvastatin; Maria can add it, guests cannot
     *  Augmentin (Rx)   -> RiteMed Co-Amoxiclav; still blocked for Maria
     *
     * Medicol Advance (40 capsules) and Ascof Lagundi (80 tablets) have too
     * few left for a full box of 100, so only single pieces can be chosen.
     */
    public function run(): void
    {
        $otc = Category::where('slug', Category::OTC)->value('id');
        $rx = Category::where('slug', Category::PRESCRIPTION)->value('id');

        $catalogue = [
            // Pain & Fever
            [$otc, 'Biogesic', 'Biogesic', 'Paracetamol', '500 mg', 'Tablet', 10, 10, 'Pain & Fever', 5.00, 480.00, 1500],
            [$otc, 'RiteMed Paracetamol', 'RiteMed', 'Paracetamol', '500 mg', 'Tablet', 10, 10, 'Pain & Fever', 2.00, 190.00, 800],
            [$otc, 'Panadol', 'Panadol', 'Paracetamol', '500 mg', 'Caplet', 10, 10, 'Pain & Fever', 7.00, 670.00, 450],
            [$otc, 'Advil', 'Advil', 'Ibuprofen', '200 mg', 'Softgel capsule', 10, 10, 'Pain & Fever', 10.00, 960.00, 0],
            [$otc, 'Medicol Advance', 'Medicol', 'Ibuprofen', '200 mg', 'Softgel capsule', 10, 10, 'Pain & Fever', 8.00, 770.00, 40],
            [$otc, 'RiteMed Ibuprofen', 'RiteMed', 'Ibuprofen', '200 mg', 'Tablet', 10, 10, 'Pain & Fever', 3.50, 330.00, 600],
            [$otc, 'Alaxan FR', 'Alaxan', 'Ibuprofen + Paracetamol', '200 mg / 325 mg', 'Capsule', 10, 10, 'Pain & Fever', 9.00, 860.00, 700],
            [$otc, 'Dolfenal', 'Dolfenal', 'Mefenamic Acid', '500 mg', 'Tablet', 10, 10, 'Pain & Fever', 18.00, 1720.00, 300],

            // Cough & Colds
            [$otc, 'Neozep Forte', 'Neozep', 'Phenylephrine + Chlorphenamine + Paracetamol', '10 mg / 2 mg / 500 mg', 'Tablet', 10, 10, 'Cough & Colds', 6.50, 620.00, 900],
            [$otc, 'Bioflu', 'Bioflu', 'Phenylephrine + Chlorphenamine + Paracetamol', '10 mg / 2 mg / 500 mg', 'Tablet', 10, 10, 'Cough & Colds', 8.00, 760.00, 0],
            [$otc, 'Solmux', 'Solmux', 'Carbocisteine', '500 mg', 'Capsule', 10, 10, 'Cough & Colds', 10.00, 950.00, 400],
            [$otc, 'RiteMed Carbocisteine', 'RiteMed', 'Carbocisteine', '500 mg', 'Capsule', 10, 10, 'Cough & Colds', 5.00, 470.00, 350],
            [$otc, 'Ascof Lagundi', 'Ascof', 'Lagundi Leaf', '600 mg', 'Tablet', 10, 10, 'Cough & Colds', 7.00, 660.00, 80],

            // Allergy
            [$otc, 'Claritin', 'Claritin', 'Loratadine', '10 mg', 'Tablet', 10, 10, 'Allergy', 35.00, 3400.00, 0],
            [$otc, 'Allerta', 'Allerta', 'Loratadine', '10 mg', 'Tablet', 10, 10, 'Allergy', 12.00, 1150.00, 400],
            [$otc, 'RiteMed Loratadine', 'RiteMed', 'Loratadine', '10 mg', 'Tablet', 10, 10, 'Allergy', 5.00, 470.00, 300],
            [$otc, 'Virlix', 'Virlix', 'Cetirizine', '10 mg', 'Tablet', 10, 10, 'Allergy', 20.00, 1900.00, 250],

            // Stomach & Digestion
            [$otc, 'Kremil-S', 'Kremil-S', 'Aluminum Hydroxide + Magnesium Hydroxide + Simeticone', '178 mg / 233 mg / 30 mg', 'Chewable tablet', 10, 10, 'Stomach & Digestion', 9.00, 860.00, 500],
            [$otc, 'Diatabs', 'Diatabs', 'Loperamide', '2 mg', 'Capsule', 10, 10, 'Stomach & Digestion', 8.00, 760.00, 450],
            [$otc, 'Imodium', 'Imodium', 'Loperamide', '2 mg', 'Capsule', 10, 10, 'Stomach & Digestion', 14.00, 1350.00, 0],
            [$otc, 'Buscopan', 'Buscopan', 'Hyoscine Butylbromide', '10 mg', 'Tablet', 10, 10, 'Stomach & Digestion', 18.00, 1750.00, 0],

            // Vitamins
            [$otc, 'Poten-Cee', 'Poten-Cee', 'Ascorbic Acid', '500 mg', 'Tablet', 10, 10, 'Vitamins', 7.00, 670.00, 600],
            [$otc, 'RiteMed Ascorbic Acid', 'RiteMed', 'Ascorbic Acid', '500 mg', 'Tablet', 10, 10, 'Vitamins', 2.50, 240.00, 500],
            [$otc, 'Fern-C', 'Fern-C', 'Sodium Ascorbate', '568.18 mg', 'Capsule', 10, 10, 'Vitamins', 8.00, 760.00, 400],

            // Prescription: Antibiotics
            [$rx, 'Amoxil', 'Amoxil', 'Amoxicillin', '500 mg', 'Capsule', 10, 10, 'Antibiotics', 22.00, 2100.00, 300],
            [$rx, 'RiteMed Amoxicillin', 'RiteMed', 'Amoxicillin', '500 mg', 'Capsule', 10, 10, 'Antibiotics', 6.00, 570.00, 500],
            [$rx, 'Augmentin', 'Augmentin', 'Amoxicillin + Clavulanic Acid', '500 mg / 125 mg', 'Tablet', 7, 2, 'Antibiotics', 85.00, 1170.00, 0],
            [$rx, 'RiteMed Co-Amoxiclav', 'RiteMed', 'Amoxicillin + Clavulanic Acid', '500 mg / 125 mg', 'Tablet', 7, 2, 'Antibiotics', 35.00, 480.00, 140],

            // Prescription: Heart & Blood Pressure
            [$rx, 'Norvasc', 'Norvasc', 'Amlodipine', '5 mg', 'Tablet', 10, 3, 'Heart & Blood Pressure', 45.00, 1330.00, 240],
            [$rx, 'RiteMed Amlodipine', 'RiteMed', 'Amlodipine', '5 mg', 'Tablet', 10, 10, 'Heart & Blood Pressure', 8.00, 760.00, 500],
            [$rx, 'Cozaar', 'Cozaar', 'Losartan', '50 mg', 'Tablet', 10, 3, 'Heart & Blood Pressure', 50.00, 1480.00, 210],
            [$rx, 'RiteMed Losartan', 'RiteMed', 'Losartan', '50 mg', 'Tablet', 10, 10, 'Heart & Blood Pressure', 10.00, 950.00, 400],

            // Prescription: Cholesterol
            [$rx, 'Lipitor', 'Lipitor', 'Atorvastatin', '20 mg', 'Tablet', 10, 3, 'Cholesterol', 70.00, 2070.00, 0],
            [$rx, 'RiteMed Atorvastatin', 'RiteMed', 'Atorvastatin', '20 mg', 'Tablet', 10, 10, 'Cholesterol', 15.00, 1430.00, 400],

            // Prescription: Diabetes
            [$rx, 'Glucophage', 'Glucophage', 'Metformin', '500 mg', 'Tablet', 10, 10, 'Diabetes', 8.00, 760.00, 600],
            [$rx, 'RiteMed Metformin', 'RiteMed', 'Metformin', '500 mg', 'Tablet', 10, 10, 'Diabetes', 3.00, 285.00, 600],
        ];

        foreach ($catalogue as [$category, $name, $brand, $ingredient, $strength, $form, $piecesPerStrip, $stripsPerBox, $class, $piecePrice, $boxPrice, $stock]) {
            Medicine::create([
                'category_id' => $category,
                'name' => $name,
                'brand' => $brand,
                'active_ingredient' => $ingredient,
                'strength' => $strength,
                'dosage_form' => $form,
                'pieces_per_strip' => $piecesPerStrip,
                'strips_per_box' => $stripsPerBox,
                'therapeutic_class' => $class,
                'description' => self::USES[$ingredient] ?? null,
                'piece_price' => $piecePrice,
                'box_price' => $boxPrice,
                'image_path' => self::IMAGE_PATHS[$name] ?? null,
                'stock' => $stock,
            ]);
        }
    }
}
