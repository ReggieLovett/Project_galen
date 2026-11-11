<x-layouts.staff title="Add medicine" section="Inventory">
    <x-slot:actions>
        <a href="{{ route('admin.medicines.index') }}" class="btn btn-quiet"><x-icon name="arrow-left" />Back to inventory</a>
    </x-slot:actions>

    <main class="mx-auto w-full max-w-4xl px-6 py-8 pb-16">
        <h1 class="text-3xl font-bold">Add medicine</h1>
        <p class="mt-2 text-lg text-ink-muted">New medicines appear on the kiosk as soon as they are saved.</p>
        <form method="POST" action="{{ route('admin.medicines.store') }}" class="mt-8">
            @csrf
            @include('admin.medicines.form')
        </form>
    </main>
</x-layouts.staff>
