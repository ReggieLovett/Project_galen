<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Medicine;
use Illuminate\Database\Seeder;

class MedicineSeeder extends Seeder
{
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
     * Every medicine comes in blister strips, sold one strip at a time or
     * by the box. Stock is counted in strips.
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
     * Medicol Advance (4 strips) and Ascof Lagundi (8 strips) have too few
     * strips left for a full box, so only the strip can be chosen.
     */
    public function run(): void
    {
        $otc = Category::where('slug', Category::OTC)->value('id');
        $rx = Category::where('slug', Category::PRESCRIPTION)->value('id');

        $catalogue = [
            // Pain & Fever
            [$otc, 'Biogesic', 'Biogesic', 'Paracetamol', '500 mg', 'Tablet', 10, 10, 'Pain & Fever', 50.00, 480.00, 150],
            [$otc, 'RiteMed Paracetamol', 'RiteMed', 'Paracetamol', '500 mg', 'Tablet', 10, 10, 'Pain & Fever', 20.00, 190.00, 80],
            [$otc, 'Panadol', 'Panadol', 'Paracetamol', '500 mg', 'Caplet', 10, 10, 'Pain & Fever', 70.00, 670.00, 45],
            [$otc, 'Advil', 'Advil', 'Ibuprofen', '200 mg', 'Softgel capsule', 10, 10, 'Pain & Fever', 100.00, 960.00, 0],
            [$otc, 'Medicol Advance', 'Medicol', 'Ibuprofen', '200 mg', 'Softgel capsule', 10, 10, 'Pain & Fever', 80.00, 770.00, 4],
            [$otc, 'RiteMed Ibuprofen', 'RiteMed', 'Ibuprofen', '200 mg', 'Tablet', 10, 10, 'Pain & Fever', 35.00, 330.00, 60],
            [$otc, 'Alaxan FR', 'Alaxan', 'Ibuprofen + Paracetamol', '200 mg / 325 mg', 'Capsule', 10, 10, 'Pain & Fever', 90.00, 860.00, 70],
            [$otc, 'Dolfenal', 'Dolfenal', 'Mefenamic Acid', '500 mg', 'Tablet', 10, 10, 'Pain & Fever', 180.00, 1720.00, 30],

            // Cough & Colds
            [$otc, 'Neozep Forte', 'Neozep', 'Phenylephrine + Chlorphenamine + Paracetamol', '10 mg / 2 mg / 500 mg', 'Tablet', 10, 10, 'Cough & Colds', 65.00, 620.00, 90],
            [$otc, 'Bioflu', 'Bioflu', 'Phenylephrine + Chlorphenamine + Paracetamol', '10 mg / 2 mg / 500 mg', 'Tablet', 10, 10, 'Cough & Colds', 80.00, 760.00, 0],
            [$otc, 'Solmux', 'Solmux', 'Carbocisteine', '500 mg', 'Capsule', 10, 10, 'Cough & Colds', 100.00, 950.00, 40],
            [$otc, 'RiteMed Carbocisteine', 'RiteMed', 'Carbocisteine', '500 mg', 'Capsule', 10, 10, 'Cough & Colds', 50.00, 470.00, 35],
            [$otc, 'Ascof Lagundi', 'Ascof', 'Lagundi Leaf', '600 mg', 'Tablet', 10, 10, 'Cough & Colds', 70.00, 660.00, 8],

            // Allergy
            [$otc, 'Claritin', 'Claritin', 'Loratadine', '10 mg', 'Tablet', 10, 10, 'Allergy', 350.00, 3400.00, 0],
            [$otc, 'Allerta', 'Allerta', 'Loratadine', '10 mg', 'Tablet', 10, 10, 'Allergy', 120.00, 1150.00, 40],
            [$otc, 'RiteMed Loratadine', 'RiteMed', 'Loratadine', '10 mg', 'Tablet', 10, 10, 'Allergy', 50.00, 470.00, 30],
            [$otc, 'Virlix', 'Virlix', 'Cetirizine', '10 mg', 'Tablet', 10, 10, 'Allergy', 200.00, 1900.00, 25],

            // Stomach & Digestion
            [$otc, 'Kremil-S', 'Kremil-S', 'Aluminum Hydroxide + Magnesium Hydroxide + Simeticone', '178 mg / 233 mg / 30 mg', 'Chewable tablet', 10, 10, 'Stomach & Digestion', 90.00, 860.00, 50],
            [$otc, 'Diatabs', 'Diatabs', 'Loperamide', '2 mg', 'Capsule', 10, 10, 'Stomach & Digestion', 80.00, 760.00, 45],
            [$otc, 'Imodium', 'Imodium', 'Loperamide', '2 mg', 'Capsule', 10, 10, 'Stomach & Digestion', 140.00, 1350.00, 0],
            [$otc, 'Buscopan', 'Buscopan', 'Hyoscine Butylbromide', '10 mg', 'Tablet', 10, 10, 'Stomach & Digestion', 180.00, 1750.00, 0],

            // Vitamins
            [$otc, 'Poten-Cee', 'Poten-Cee', 'Ascorbic Acid', '500 mg', 'Tablet', 10, 10, 'Vitamins', 70.00, 670.00, 60],
            [$otc, 'RiteMed Ascorbic Acid', 'RiteMed', 'Ascorbic Acid', '500 mg', 'Tablet', 10, 10, 'Vitamins', 25.00, 240.00, 50],
            [$otc, 'Fern-C', 'Fern-C', 'Sodium Ascorbate', '568.18 mg', 'Capsule', 10, 10, 'Vitamins', 80.00, 760.00, 40],

            // Prescription: Antibiotics
            [$rx, 'Amoxil', 'Amoxil', 'Amoxicillin', '500 mg', 'Capsule', 10, 10, 'Antibiotics', 220.00, 2100.00, 30],
            [$rx, 'RiteMed Amoxicillin', 'RiteMed', 'Amoxicillin', '500 mg', 'Capsule', 10, 10, 'Antibiotics', 60.00, 570.00, 50],
            [$rx, 'Augmentin', 'Augmentin', 'Amoxicillin + Clavulanic Acid', '500 mg / 125 mg', 'Tablet', 7, 2, 'Antibiotics', 595.00, 1170.00, 0],
            [$rx, 'RiteMed Co-Amoxiclav', 'RiteMed', 'Amoxicillin + Clavulanic Acid', '500 mg / 125 mg', 'Tablet', 7, 2, 'Antibiotics', 245.00, 480.00, 20],

            // Prescription: Heart & Blood Pressure
            [$rx, 'Norvasc', 'Norvasc', 'Amlodipine', '5 mg', 'Tablet', 10, 3, 'Heart & Blood Pressure', 450.00, 1330.00, 24],
            [$rx, 'RiteMed Amlodipine', 'RiteMed', 'Amlodipine', '5 mg', 'Tablet', 10, 10, 'Heart & Blood Pressure', 80.00, 760.00, 50],
            [$rx, 'Cozaar', 'Cozaar', 'Losartan', '50 mg', 'Tablet', 10, 3, 'Heart & Blood Pressure', 500.00, 1480.00, 21],
            [$rx, 'RiteMed Losartan', 'RiteMed', 'Losartan', '50 mg', 'Tablet', 10, 10, 'Heart & Blood Pressure', 100.00, 950.00, 40],

            // Prescription: Cholesterol
            [$rx, 'Lipitor', 'Lipitor', 'Atorvastatin', '20 mg', 'Tablet', 10, 3, 'Cholesterol', 700.00, 2070.00, 0],
            [$rx, 'RiteMed Atorvastatin', 'RiteMed', 'Atorvastatin', '20 mg', 'Tablet', 10, 10, 'Cholesterol', 150.00, 1430.00, 40],

            // Prescription: Diabetes
            [$rx, 'Glucophage', 'Glucophage', 'Metformin', '500 mg', 'Tablet', 10, 10, 'Diabetes', 80.00, 760.00, 60],
            [$rx, 'RiteMed Metformin', 'RiteMed', 'Metformin', '500 mg', 'Tablet', 10, 10, 'Diabetes', 30.00, 285.00, 60],
        ];

        foreach ($catalogue as [$category, $name, $brand, $ingredient, $strength, $form, $piecesPerStrip, $stripsPerBox, $class, $stripPrice, $boxPrice, $stock]) {
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
                'strip_price' => $stripPrice,
                'box_price' => $boxPrice,
                'stock' => $stock,
            ]);
        }
    }
}
