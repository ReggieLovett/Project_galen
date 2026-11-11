<x-layouts.kiosk title="Edit medicine" noindex>
    <main class="mx-auto w-full max-w-4xl px-6 py-8">
        <a href="{{ route('admin.medicines.index') }}" class="focus-ring rounded-lg font-semibold text-brand">Back to inventory</a>
        <h1 class="mt-5 text-3xl font-bold">Edit {{ $medicine->fullName() }}</h1>
        <p class="mt-2 text-ink-muted">Changes to stock are reflected on the kiosk immediately after saving.</p>
        <form method="POST" action="{{ route('admin.medicines.update', $medicine) }}" class="mt-8">
            @csrf
            @method('PUT')
            @include('admin.medicines.form')
        </form>
    </main>
</x-layouts.kiosk>
