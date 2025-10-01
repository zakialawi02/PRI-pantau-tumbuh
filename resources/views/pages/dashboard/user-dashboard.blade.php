@section('title', $data['title'] ?? 'User Dashboard' . ' | ' . config('app.name'))
@section('meta_description', 'User dashboard with your subscriptions, payments, and field areas.')

<x-app-layout>
    <section class="p-1 md:px-4">
        <div class="py-2">
            <h1 class="text-2xl font-semibold">User Dashboard</h1>
            <p class="text-foreground/70">Welcome back, {{ Auth::user()->name }}!</p>
        </div>

    </section>
</x-app-layout>
