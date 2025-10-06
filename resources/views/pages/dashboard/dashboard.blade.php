@section('title', $data['title'] ?? 'Dashboard' . ' | ' . config('app.name'))
@section('meta_description', '')

<x-app-layout>
    <section class="p-1 md:px-4">
        <div class="py-2">
            <h1 class="text-2xl font-semibold">Dashboard</h1>
        </div>
    </section>
</x-app-layout>
