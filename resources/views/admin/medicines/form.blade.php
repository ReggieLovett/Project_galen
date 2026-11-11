<div class="space-y-6 rounded-2xl border-2 border-line bg-surface p-6">
    @if ($errors->any())
        <x-alert type="error">Please correct the highlighted fields.</x-alert>
    @endif

    <div class="grid gap-5 md:grid-cols-2">
        <x-input-field label="Product name" name="name" :value="$medicine->name" required />
        <x-input-field label="Brand" name="brand" :value="$medicine->brand" required />
        <x-input-field label="Active ingredient" name="active_ingredient" :value="$medicine->active_ingredient" required />
        <x-input-field label="Strength" name="strength" :value="$medicine->strength" required />
        <x-input-field label="Dosage form" name="dosage_form" :value="$medicine->dosage_form" required />
        <x-input-field label="Therapeutic class" name="therapeutic_class" :value="$medicine->therapeutic_class" required />
    </div>

    <div>
        <label for="category_id" class="mb-1.5 block font-display font-semibold">Medicine type</label>
        <select id="category_id" name="category_id" class="focus-ring h-14 w-full rounded-xl border-2 border-line bg-canvas px-4 text-lg" required>
            <option value="">Select a type</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $medicine->category_id) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('category_id')<p class="mt-1.5 font-semibold text-danger">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-5 md:grid-cols-3">
        <x-input-field label="Pieces per strip" name="pieces_per_strip" type="number" :value="$medicine->pieces_per_strip ?: 10" min="1" required />
        <x-input-field label="Strips per box" name="strips_per_box" type="number" :value="$medicine->strips_per_box ?: 10" min="1" required />
        <x-input-field label="Stock in pieces" name="stock" type="number" :value="$medicine->stock" min="0" required />
    </div>

    <div class="grid gap-5 md:grid-cols-2">
        <x-input-field label="Price per piece (PHP)" name="piece_price" type="number" step="0.01" :value="$medicine->piece_price" min="0" required />
        <x-input-field label="Price per box (PHP)" name="box_price" type="number" step="0.01" :value="$medicine->box_price" min="0" required />
    </div>

    <div>
        <label for="description" class="mb-1.5 block font-display font-semibold">What is it for? <span class="font-normal text-ink-muted">(optional)</span></label>
        <textarea id="description" name="description" rows="3" class="focus-ring w-full rounded-xl border-2 border-line bg-canvas px-4 py-3 text-lg">{{ old('description', $medicine->description) }}</textarea>
        @error('description')<p class="mt-1.5 font-semibold text-danger">{{ $message }}</p>@enderror
    </div>

    <x-input-field label="Image path (optional, relative to public/)" name="image_path" :value="$medicine->image_path" />

    <div class="flex flex-wrap justify-end gap-3 border-t-2 border-line pt-5">
        <a href="{{ route('admin.medicines.index') }}" class="btn btn-quiet">Cancel</a>
        <button type="submit" class="btn btn-primary"><x-icon name="check" />Save medicine</button>
    </div>
</div>
