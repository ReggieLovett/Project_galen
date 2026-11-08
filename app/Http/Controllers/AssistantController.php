<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Services\Assistant\Conversation;
use App\Services\Assistant\PharmacyAssistant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    /**
     * ASK — about a product the customer tapped, or a typed question (or a
     * tapped suggestion such as "Are they the same dosage?"). A question
     * that names no medicine is about the ones the last reply was about.
     * The reply is kept in the session and shown in the assistant drawer.
     */
    public function store(Request $request, PharmacyAssistant $assistant, Conversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'medicine_id' => ['required_without:question', 'nullable', 'integer'],
            'question' => ['required_without:medicine_id', 'nullable', 'string', 'max:200'],
        ]);

        if (isset($validated['medicine_id'])) {
            $medicine = Medicine::findOrFail($validated['medicine_id']);

            $conversation->add("{$medicine->name} is out of stock. What else can I get?", $assistant->suggestFor($medicine));
        } else {
            $conversation->add($validated['question'], $assistant->answer($validated['question'], $conversation->context()));
        }

        return back()->with('panel', 'assistant');
    }
}
