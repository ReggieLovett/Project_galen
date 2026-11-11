<x-layouts.kiosk title="Add medicine" noindex>
    <main class="mx-auto w-full max-w-4xl px-6 py-8">
        <a href="{{ route('admin.medicines.index') }}" class="focus-ring rounded-lg font-semibold text-brand">Back to inventory</a>
        <h1 class="mt-5 text-3xl font-bold">Add medicine</h1>
        <p class="mt-2 text-ink-muted">New medicines appear on the kiosk as soon as they are saved.</p>
        <form method="POST" action="{{ route('admin.medicines.store') }}" class="mt-8">
            @csrf
            @include('admin.medicines.form')
        </form>
    </main>
</x-layouts.kiosk>
