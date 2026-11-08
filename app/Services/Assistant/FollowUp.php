<?php

namespace App\Services\Assistant;

use App\Enums\Packaging;
use App\Models\Medicine;
use App\Support\Money;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Answers to questions about specific medicines ("are they the same
 * dosage?", "which is cheaper?", "what is it for?"), written only from
 * catalogue facts: ingredients, strength, dosage form, prices, prescription
 * status and the plain-language description. Nothing is generated, so an
 * answer can't drift into medical advice. How much to take stays with the
 * pharmacist.
 */
final class FollowUp
{
    public const ASK_PHARMACIST_DOSE = 'For how much to take and how often, please ask our pharmacist.';

    public const ASK_PHARMACIST_SWITCH = 'Please check with our pharmacist before switching.';

    /**
     * @param  Medicine  $subject  the medicine the customer asked about
     * @param  Collection<int, Medicine>  $others  medicines to compare it with, e.g. the alternatives just suggested
     */
    public static function answer(Topic $topic, Medicine $subject, Collection $others): string
    {
        $all = collect([$subject])->concat($others)->values();

        return match ($topic) {
            Topic::Dose => self::dose($subject, $others),
            Topic::Same => self::same($subject, $others),
            Topic::Price => self::price($all),
            Topic::Prescription => self::prescription($all),
            Topic::Packaging => self::packaging($all),
            Topic::Uses => self::uses($all),
        };
    }

    /**
     * @param  Collection<int, Medicine>  $others
     */
    private static function dose(Medicine $subject, Collection $others): string
    {
        if ($others->isEmpty()) {
            return "{$subject->fullName()} has ".self::doseOf($subject).'. '.self::ASK_PHARMACIST_DOSE;
        }

        [$same, $different] = $others->partition(fn (Medicine $other) => self::sameDose($subject, $other));

        $sentences = collect();

        if ($same->isNotEmpty()) {
            $sentences->push(($different->isEmpty() ? 'Yes. ' : '').self::names($same).' '.self::verb($same, 'has', 'have')." the same dose as {$subject->fullName()}: ".self::doseOf($subject).'.');
        }

        $different->each(fn (Medicine $other) => $sentences->push(self::doseDifference($subject, $other)));

        $sentences->push($different->isEmpty() ? self::ASK_PHARMACIST_DOSE : self::ASK_PHARMACIST_SWITCH);

        return $sentences->join(' ');
    }

    /**
     * @param  Collection<int, Medicine>  $others
     */
    private static function same(Medicine $subject, Collection $others): string
    {
        if ($others->isEmpty()) {
            return $subject->isInStock()
                ? "Which medicine should I compare {$subject->fullName()} with? Type both names, for example “Is Allerta the same as Claritin?”"
                : 'We have no other brand of '.self::ingredientsOf($subject)." in stock to compare {$subject->fullName()} with. Our pharmacist can help at the counter.";
        }

        $sentences = $others->map(fn (Medicine $other) => match (true) {
            self::sameDose($subject, $other) => "{$other->fullName()} is the same medicine as {$subject->fullName()}: ".self::doseOf($subject).'. Only the brand and the price differ: '.Money::format($other->piece_price).' a '.$other->pieceName(1).', compared with '.Money::format($subject->piece_price).'.',
            self::sameIngredients($subject, $other) => "{$other->fullName()} has the same active ".Str::plural('ingredient', count($subject->ingredients())).' as '.$subject->fullName().', '.self::ingredientsOf($subject).', but '.($other->strength === $subject->strength
                ? 'it is a '.Str::lower($other->dosage_form).', not a '.Str::lower($subject->dosage_form).'.'
                : "it is {$other->strength}, not {$subject->strength}."),
            default => self::doseDifference($subject, $other),
        });

        if ($others->contains(fn (Medicine $other) => ! self::sameDose($subject, $other))) {
            $sentences->push(self::ASK_PHARMACIST_SWITCH);
        }

        return $sentences->join(' ');
    }

    /**
     * @param  Collection<int, Medicine>  $medicines
     */
    private static function price(Collection $medicines): string
    {
        $sentences = $medicines->map(fn (Medicine $medicine) => $medicine->fullName()
            .($medicine->isInStock() ? '' : ' (out of stock)')
            .' is '.Money::format($medicine->piece_price).' a '.$medicine->pieceName(1).', or '.Money::format($medicine->box_price)
            .' for a box of '.$medicine->piecesPerBox().'.');

        $inStock = $medicines->filter(fn (Medicine $medicine) => $medicine->isInStock());

        if ($inStock->count() > 1) {
            $cheapest = $inStock->sortBy(fn (Medicine $medicine) => (float) $medicine->piece_price)->first();

            $sentences->push("{$cheapest->fullName()} is the cheapest per ".$cheapest->pieceName(1).'.');
        }

        return $sentences->join(' ');
    }

    /**
     * @param  Collection<int, Medicine>  $medicines
     */
    private static function prescription(Collection $medicines): string
    {
        [$needed, $free] = $medicines->partition(fn (Medicine $medicine) => $medicine->requiresPrescription());

        $refill = 'Members who have bought '.($needed->count() === 1 ? 'it' : 'them').' with us before can add a refill after signing in. Otherwise, please ask at the pharmacy counter.';

        return match (true) {
            $needed->isEmpty() => 'No. '.self::names($free).' '.self::verb($free, "doesn't", "don't").' need a prescription. You can add '.($free->count() === 1 ? 'it' : 'them').' to your basket straight away.',
            $free->isEmpty() => 'Yes. '.self::names($needed).' '.self::verb($needed, 'needs', 'need').' a prescription. '.$refill,
            default => self::names($needed).' '.self::verb($needed, 'needs', 'need').' a prescription, but '.self::names($free).' '.self::verb($free, "doesn't", "don't").'. '.$refill,
        };
    }

    /**
     * @param  Collection<int, Medicine>  $medicines
     */
    private static function packaging(Collection $medicines): string
    {
        return $medicines->take(3)
            ->map(fn (Medicine $medicine) => "{$medicine->fullName()} is sold by the ".$medicine->pieceName(1)
                .' for '.Money::format($medicine->piece_price).' each, or in a '.Str::lcfirst($medicine->packDescription(Packaging::Box))
                .' for '.Money::format($medicine->box_price).'.')
            ->push('Use the + and − buttons on a medicine to choose how many you want.')
            ->join(' ');
    }

    /**
     * Medicines with the same description (same ingredient) share one sentence.
     *
     * @param  Collection<int, Medicine>  $medicines
     */
    private static function uses(Collection $medicines): string
    {
        return $medicines->groupBy(fn (Medicine $medicine) => (string) $medicine->description)
            ->map(fn (Collection $group, string $description) => self::names($group).' ('.self::ingredientsOf($group->first()).'): '
                .($description !== '' ? $description : 'our pharmacist can tell you what it is for.'))
            ->values()
            ->push('Our pharmacist can tell you whether it is right for you.')
            ->join(' ');
    }

    /**
     * "Medicol Advance is a softgel capsule, not ..." when two medicines differ.
     */
    private static function doseDifference(Medicine $subject, Medicine $other): string
    {
        return match (true) {
            ! self::sameIngredients($subject, $other) => "{$other->fullName()} is a different medicine: it has ".self::ingredientsOf($other).', not '.self::ingredientsOf($subject).'.',
            $other->strength === $subject->strength => "{$other->fullName()} has the same {$subject->strength}, but it is a ".Str::lower($other->dosage_form).', not a '.Str::lower($subject->dosage_form).'.',
            default => "{$other->fullName()} is {$other->strength} in each ".Str::lower($other->dosage_form).", while {$subject->fullName()} is {$subject->strength}, so the dose is different.",
        };
    }

    /**
     * "loratadine 10 mg in each tablet"
     */
    private static function doseOf(Medicine $medicine): string
    {
        return self::ingredientsOf($medicine)." {$medicine->strength} in each ".Str::lower($medicine->dosage_form);
    }

    /**
     * "loratadine", or "phenylephrine, chlorphenamine and paracetamol"
     */
    private static function ingredientsOf(Medicine $medicine): string
    {
        return Arr::join(array_map(Str::lower(...), $medicine->ingredients()), ', ', ' and ');
    }

    private static function sameIngredients(Medicine $a, Medicine $b): bool
    {
        $normalised = fn (Medicine $medicine) => collect($medicine->ingredients())
            ->map(fn (string $ingredient) => mb_strtolower($ingredient))
            ->sort()
            ->values()
            ->all();

        return $normalised($a) === $normalised($b);
    }

    private static function sameDose(Medicine $a, Medicine $b): bool
    {
        return self::sameIngredients($a, $b) && $a->strength === $b->strength && $a->dosage_form === $b->dosage_form;
    }

    /**
     * "Allerta", "RiteMed Loratadine and Allerta"
     *
     * @param  Collection<int, Medicine>  $medicines
     */
    private static function names(Collection $medicines): string
    {
        return Arr::join($medicines->map(fn (Medicine $medicine) => $medicine->fullName())->values()->all(), ', ', ' and ');
    }

    /**
     * @param  Collection<int, Medicine>  $medicines
     */
    private static function verb(Collection $medicines, string $one, string $many): string
    {
        return $medicines->count() === 1 ? $one : $many;
    }
}
