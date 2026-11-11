<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MedicineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'active_ingredient' => ['required', 'string', 'max:255'],
            'strength' => ['required', 'string', 'max:255'],
            'dosage_form' => ['required', 'string', 'max:255'],
            'pieces_per_strip' => ['required', 'integer', 'min:1', 'max:255'],
            'strips_per_box' => ['required', 'integer', 'min:1', 'max:255'],
            'therapeutic_class' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'piece_price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'box_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }
}
