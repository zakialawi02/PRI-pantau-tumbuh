@section('title', 'Contact | ' . config('app.name'))

@section('meta_description', 'PantauTumbuh.id adalah sistem informasi berbasis citra satelit untuk mendeteksi stres tanaman menggunakan nilai Photochemical Reflectance Index (PRI). Sistem ini membantu petani, peneliti, dan pemangku kepentingan dalam memantau kesehatan tanaman secara efisien dan akurat.')
@section('meta_keywords', 'PRI, photochemical reflectance index, stres tanaman, citra satelit, pantautumbuh, pantautumbuh.id, kesehatan tanaman, webgis pertanian, remote sensing, sentinel-2, deep learning, pertanian presisi')

@section('og_title', 'PantauTumbuh.id - WebGIS Stres Tanaman Berbasis PRI')
@section('og_description', 'PantauTumbuh.id memanfaatkan citra satelit dan model deep learning untuk menghitung nilai Photochemical Reflectance Index (PRI), memberikan informasi spasial tentang tingkat stres tanaman secara akurat bagi petani, peneliti, dan pengambil keputusan.')


<x-app-front-layout>
    <section class="bg-background py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">Contact Us</h2>
                <p class="text-foreground/80 mt-4 text-lg leading-8">
                    Have questions about PantauTumbuh.id? We'd love to hear from you. Send us a message and we'll respond as soon as possible.
                </p>
            </div>

            <div class="mx-auto mt-16 max-w-xl">
                <form class="space-y-6" id="contactForm" method="POST">
                    @csrf

                    <div>
                        <x-input-label for="full_name" :value="__('Full Name')" />
                        <x-text-input class="block w-full" id="full_name" name="full_name" type="text" :value="old('full_name')" required autocomplete="full_name" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input class="block w-full" id="email" name="email" type="email" :value="old('email')" required autocomplete="email" />
                    </div>

                    <div>
                        <x-input-label for="phone" :value="__('Phone Number')" />
                        <x-text-input class="block w-full" id="phone" name="phone" type="text" :value="old('phone')" required autocomplete="phone" />
                    </div>

                    <div>
                        <x-input-label for="message" :value="__('Message')" />
                        <x-textarea-input id="message" name="message" rows="4" required />
                    </div>

                    <div>
                        <x-button-primary type="submit">
                            {{ __('Send Message') }}
                        </x-button-primary>
                    </div>
                </form>
            </div>

            <!-- Contact Information -->
            <div class="mx-auto mt-16 max-w-2xl text-center">
                <h3 class="text-foreground mb-6 text-xl font-semibold">Other Ways to Reach Us</h3>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div class="text-center">
                        <div class="bg-muted mx-auto flex h-12 w-12 items-center justify-center rounded-lg">
                            <svg class="text-background h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                            </svg>
                        </div>
                        <h4 class="text-foreground mt-4 text-lg font-medium">Email</h4>
                        <p class="text-foreground/80 mt-2 text-sm">info@pantautumbuh.id</p>
                    </div>

                    <div class="text-center">
                        <div class="bg-muted mx-auto flex h-12 w-12 items-center justify-center rounded-lg">
                            <svg class="text-background h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                            </svg>
                        </div>
                        <h4 class="text-foreground mt-4 text-lg font-medium">Phone</h4>
                        <p class="text-foreground/80 mt-2 text-sm">+62 897 4884 990</p>
                    </div>

                    <div class="text-center">
                        <div class="bg-muted mx-auto flex h-12 w-12 items-center justify-center rounded-lg">
                            <svg class="text-background h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                            </svg>
                        </div>
                        <h4 class="text-foreground mt-4 text-lg font-medium">Address</h4>
                        <p class="text-foreground/80 mt-2 text-sm">Surabaya, Indonesia</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="">
        <div class="h-96 w-full overflow-hidden rounded-lg">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d10129.962532174066!2d112.79374264927627!3d-7.278667019250665!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2dd7fa13c4642591%3A0x894902aff3849275!2sDepartemen%20Teknik%20Geomatika!5e1!3m2!1sen!2sid!4v1731222375082!5m2!1sen!2sid" style="border:0;" width="100%" height="100%" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </section>

    @push('javascript')
        <script>
            document.getElementById("contactForm").addEventListener("submit", function(e) {
                e.preventDefault();

                let button = e.target.querySelector("button[type='submit']");
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';

                let data = {
                    nama: e.target.full_name.value,
                    email: e.target.email.value,
                    telepon: e.target.phone.value,
                    pesan: e.target.message.value,
                };


                fetch("{{ env('CONTACT_FORM_APP_SCRIPT') ?? 'https://script.google.com/macros/s/# ?>' }}", {
                        method: "POST",
                        body: JSON.stringify(data),
                    })
                    .then((res) => res.json())
                    .then((response) => {
                        alert("Message sent successfully");
                        e.target.reset();
                    })
                    .catch((err) => {
                        alert("Something went wrong. Please try again later.");
                        console.error(err);
                    })
                    .finally(() => {
                        button.disabled = false;
                        button.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Send Message';
                    });
            });
        </script>
    @endpush
</x-app-front-layout>
