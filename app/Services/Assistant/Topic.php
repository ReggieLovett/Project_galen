<?php

namespace App\Services\Assistant;

/**
 * What a typed question asks about a medicine, so the assistant can answer
 * it from catalogue facts. Patterns cover plain English and a few common
 * Filipino words. The first match wins, in the order of the cases.
 */
enum Topic: string
{
    /** "Which one is cheaper?", "Magkano?" */
    case Price = 'price';

    /** "Do they need a prescription?", "May reseta ba?" */
    case Prescription = 'prescription';

    /** "Are they the same dosage?", "Is it the same mg?" */
    case Dose = 'dose';

    /** "How many are in a box?", "How many tablets?" */
    case Packaging = 'packaging';

    /** "Is it the same as Claritin?", "What's the difference?" */
    case Same = 'same';

    /** "What is it for?", "Para saan ito?" */
    case Uses = 'uses';

    /**
     * Questions about what to take, how much, or whether it is safe for
     * someone. Those are for the pharmacist, never the kiosk.
     */
    public const ADVICE = '/\b(should i|can i (take|give|use|drink)|how (many|much|often) (should|do|can|to)|times a day|side effects?|safe|pregnan\w*|breastfeed\w*|my (child|baby|son|daughter|kid)|for (kids|children|a child|a baby|babies)|allergic to|interact\w*|overdose|ilang beses|pwede ba)\b/u';

    public static function in(string $question): ?self
    {
        $text = mb_strtolower($question);

        foreach (self::cases() as $topic) {
            if (preg_match($topic->pattern(), $text) === 1) {
                return $topic;
            }
        }

        return null;
    }

    public static function asksForAdvice(string $question): bool
    {
        return preg_match(self::ADVICE, mb_strtolower($question)) === 1;
    }

    /**
     * Does answering it mean comparing medicines with each other?
     */
    public function compares(): bool
    {
        return in_array($this, [self::Dose, self::Same], true);
    }

    private function pattern(): string
    {
        return match ($this) {
            self::Price => '/\b(price|prices|cost|costs|cheap|cheaper|cheapest|expensive|how much|magkano|mura|presyo)\b/u',
            self::Prescription => '/\b(prescriptions?|rx|reseta|doctor)\b/u',
            self::Dose => '/\b(dose|doses|dosage|dosages|strength|strengths|stronger|weaker|mg|milligrams?)\b/u',
            self::Packaging => '/\b(box|boxes|strip|strips|banig|pieces|how many (are|tablets|capsules|caplets|pieces|in))\b/u',
            self::Same => '/\b(same|different|difference|differ|compare|similar|equivalent|identical|pareho|parehas|kapareho|magkapareho)\b/u',
            self::Uses => '/(\bpara saan\b|\bused? for\b|\bwhat (is|are|\'s)\b.*\bfor\b|\bwhat does\b.*\bdo\b|\bgood for\b|\btreats?\b|\bpurpose\b)/u',
        };
    }
}
