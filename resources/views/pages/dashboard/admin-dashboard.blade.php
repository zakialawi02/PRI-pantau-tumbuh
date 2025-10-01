@section('title', $data['title'] ?? 'Admin Dashboard' . ' | ' . config('app.name'))
@section('meta_description', 'Admin dashboard with system statistics and management overview.')

<x-app-layout>
    <section class="p-1 md:px-4">
        <div class="py-2">
            <h1 class="text-2xl font-semibold">Admin Dashboard</h1>
            <p class="text-foreground/70">Welcome back, {{ Auth::user()->name }}!</p>
        </div>

    </section>
</x-app-layout>
