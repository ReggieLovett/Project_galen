<x-layouts.staff title="Edit medicine" section="Inventory">
    <x-slot:actions>
        <a href="{{ route('admin.medicines.index') }}" class="btn btn-quiet"><x-icon name="arrow-left" />Back to inventory</a>
    </x-slot:actions>

    <main class="mx-auto w-full max-w-4xl px-6 py-8 pb-16">
        <h1 class="text-3xl font-bold">Edit {{ $medicine->fullName() }}</h1>
        <p class="mt-2 text-lg text-ink-muted">Changes, including stock, show on the kiosk as soon as they are saved.</p>
        <form method="POST" action="{{ route('admin.medicines.update', $medicine) }}" class="mt-8">
            @csrf
            @method('PUT')
            @include('admin.medicines.form')
        </form>
    </main>
</x-layouts.staff>
