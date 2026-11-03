<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Medicine;
use Illuminate\Database\Seeder;

class MedicineSeeder extends Seeder
{
    /**
     * Seed the catalogue.
     *
     * Six products are out of stock so the stock assistant has something to
     * do. Each scenario it can be tested with:
     *
     *  Advil          -> Motrin IB (same tablet), Nurofen (capsule)
     *  Claritin       -> Perrigo loratadine, Alavert. Never Zyrtec or Allegra,
     *                    which treat the same thing with a different ingredient.
     *  Pepcid AC      -> Teva famotidine
     *  Benadryl       -> nothing in stock; the assistant says so
     *  Lipitor (Rx)   -> Sandoz atorvastatin; Maria can add it, guests cannot
     *  Zithromax (Rx) -> Sandoz azithromycin; still blocked for Maria
     */
    public function run(): void
    {
        $otc = Category::where('slug', Category::OTC)->value('id');
        $rx = Category::where('slug', Category::PRESCRIPTION)->value('id');

        $catalogue = [
            // Pain & Fever
            [$otc, 'Advil Pain Reliever', 'Advil', 'Ibuprofen', '200 mg', 'Tablet', '24 tablets', 'Pain & Fever', 8.49, 0],
            [$otc, 'Motrin IB', 'Motrin', 'Ibuprofen', '200 mg', 'Tablet', '24 tablets', 'Pain & Fever', 7.99, 18],
            [$otc, 'Nurofen Express', 'Nurofen', 'Ibuprofen', '200 mg', 'Liquid capsule', '16 capsules', 'Pain & Fever', 6.79, 9],
            [$otc, 'Tylenol Extra Strength', 'Tylenol', 'Acetaminophen', '500 mg', 'Caplet', '24 caplets', 'Pain & Fever', 9.29, 25],
            [$otc, 'Panadol Advance', 'Panadol', 'Acetaminophen', '500 mg', 'Tablet', '24 tablets', 'Pain & Fever', 7.49, 30],
            [$otc, 'Aleve All Day Strong', 'Aleve', 'Naproxen Sodium', '220 mg', 'Tablet', '24 tablets', 'Pain & Fever', 8.99, 4],
            [$otc, 'Bayer Aspirin', 'Bayer', 'Aspirin', '325 mg', 'Tablet', '100 tablets', 'Pain & Fever', 7.19, 15],

            // Allergy
            [$otc, 'Claritin 24-Hour', 'Claritin', 'Loratadine', '10 mg', 'Tablet', '10 tablets', 'Allergy', 12.99, 0],
            [$otc, 'Loratadine Tablets', 'Perrigo', 'Loratadine', '10 mg', 'Tablet', '30 tablets', 'Allergy', 9.99, 14],
            [$otc, 'Alavert 24-Hour', 'Alavert', 'Loratadine', '10 mg', 'Dissolving tablet', '12 tablets', 'Allergy', 10.49, 11],
            [$otc, 'Zyrtec Allergy', 'Zyrtec', 'Cetirizine', '10 mg', 'Tablet', '14 tablets', 'Allergy', 13.49, 14],
            [$otc, 'Allegra Allergy 24HR', 'Allegra', 'Fexofenadine', '180 mg', 'Tablet', '15 tablets', 'Allergy', 15.99, 7],
            [$otc, 'Benadryl Allergy', 'Benadryl', 'Diphenhydramine', '25 mg', 'Tablet', '24 tablets', 'Allergy', 6.99, 0],

            // Cold & Flu
            [$otc, 'Mucinex 12 Hour', 'Mucinex', 'Guaifenesin', '600 mg', 'Extended-release tablet', '20 tablets', 'Cold & Flu', 14.99, 10],
            [$otc, 'Robitussin Chest Congestion', 'Robitussin', 'Guaifenesin', '100 mg/5 mL', 'Syrup', '118 mL', 'Cold & Flu', 9.49, 6],
            [$otc, 'Sudafed PE', 'Sudafed', 'Phenylephrine', '10 mg', 'Tablet', '18 tablets', 'Cold & Flu', 6.49, 11],

            // Digestive
            [$otc, 'Pepcid AC Maximum Strength', 'Pepcid', 'Famotidine', '20 mg', 'Tablet', '25 tablets', 'Digestive', 11.99, 0],
            [$otc, 'Famotidine Tablets', 'Teva', 'Famotidine', '20 mg', 'Tablet', '30 tablets', 'Digestive', 6.99, 22],
            [$otc, 'Imodium A-D', 'Imodium', 'Loperamide', '2 mg', 'Caplet', '12 caplets', 'Digestive', 7.99, 8],
            [$otc, 'Tums Extra Strength', 'Tums', 'Calcium Carbonate', '750 mg', 'Chewable tablet', '96 tablets', 'Digestive', 6.49, 20],
            [$otc, 'Prilosec OTC', 'Prilosec', 'Omeprazole', '20 mg', 'Delayed-release tablet', '14 tablets', 'Digestive', 13.99, 5],

            // Skin Care
            [$otc, 'Cortizone-10 Maximum Strength', 'Cortizone-10', 'Hydrocortisone', '1%', 'Cream', '28 g', 'Skin Care', 7.49, 9],

            // Prescription: Heart & Blood Pressure
            [$rx, 'Lipitor', 'Lipitor', 'Atorvastatin', '20 mg', 'Tablet', '30 tablets', 'Heart & Blood Pressure', 42.00, 0],
            [$rx, 'Atorvastatin Calcium', 'Sandoz', 'Atorvastatin', '20 mg', 'Tablet', '30 tablets', 'Heart & Blood Pressure', 12.50, 40],
            [$rx, 'Norvasc', 'Norvasc', 'Amlodipine', '5 mg', 'Tablet', '30 tablets', 'Heart & Blood Pressure', 28.00, 22],
            [$rx, 'Zestril', 'Zestril', 'Lisinopril', '10 mg', 'Tablet', '30 tablets', 'Heart & Blood Pressure', 18.00, 30],

            // Prescription: Diabetes
            [$rx, 'Glucophage', 'Glucophage', 'Metformin', '500 mg', 'Tablet', '60 tablets', 'Diabetes', 15.00, 50],
            [$rx, 'Metformin Hydrochloride', 'Teva', 'Metformin', '500 mg', 'Tablet', '60 tablets', 'Diabetes', 6.00, 60],

            // Prescription: Infections
            [$rx, 'Amoxil', 'Amoxil', 'Amoxicillin', '500 mg', 'Capsule', '21 capsules', 'Infections', 16.00, 25],
            [$rx, 'Zithromax Z-Pak', 'Zithromax', 'Azithromycin', '250 mg', 'Tablet', '6 tablets', 'Infections', 24.00, 0],
            [$rx, 'Azithromycin', 'Sandoz', 'Azithromycin', '250 mg', 'Tablet', '6 tablets', 'Infections', 11.00, 12],

            // Prescription: Respiratory
            [$rx, 'Ventolin HFA', 'Ventolin', 'Albuterol', '90 mcg/dose', 'Inhaler', '200 doses', 'Respiratory', 38.00, 6],
        ];

        foreach ($catalogue as [$category, $name, $brand, $ingredient, $strength, $form, $pack, $class, $price, $stock]) {
            Medicine::create([
                'category_id' => $category,
                'name' => $name,
                'brand' => $brand,
                'active_ingredient' => $ingredient,
                'strength' => $strength,
                'dosage_form' => $form,
                'pack_size' => $pack,
                'therapeutic_class' => $class,
                'price' => $price,
                'stock' => $stock,
            ]);
        }
    }
}
