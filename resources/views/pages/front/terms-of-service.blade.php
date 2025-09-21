@section('title', 'Terms of Service | ' . config('app.name'))

@section('meta_description', 'Terms of Service for PantauTumbuh.id - Learn about the terms and conditions that govern your use of our services.')
@section('meta_keywords', 'terms of service, terms and conditions, pantautumbuh, pantautumbuh.id')

@section('og_title', 'Terms of Service | PantauTumbuh.id')
@section('og_description', 'Learn about the terms and conditions that govern your use of our services at PantauTumbuh.id.')

<x-app-front-layout>
    <section class="bg-background py-16">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto mb-12 text-center">
                <h1 class="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">Terms of Service</h1>
                <p class="text-foreground/80 mt-4 text-lg">
                    Last updated: {{ date('F d, Y') }}
                </p>
            </div>

            <div class="bg-card text-card-foreground rounded-lg p-6 shadow-lg md:p-8">
                <div class="prose prose-lg max-w-none">
                    <h2 class="text-foreground text-xl font-semibold">Introduction</h2>
                    <p class="text-foreground/80 mt-4">
                        Welcome to PantauTumbuh.id. These Terms of Service ("Terms") govern your access to and use of our
                        website and services. By accessing or using our services, you agree to be bound by these Terms and
                        our Privacy Policy.
                    </p>
                    <p class="text-foreground/80 mt-4">
                        Please read these Terms carefully before accessing or using our services. If you do not agree to
                        these Terms, you must not access or use our services.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Services Description</h2>
                    <p class="text-foreground/80 mt-4">
                        PantauTumbuh.id provides vegetation monitoring services using satellite imagery and geospatial
                        technology. Our platform offers tools for analyzing and visualizing vegetation health and growth
                        patterns through our proprietary algorithms and data processing capabilities.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Eligibility</h2>
                    <p class="text-foreground/80 mt-4">
                        You must be at least 18 years old to use our services. By using our services, you represent and
                        warrant that you are at least 18 years old and have the legal capacity to enter into these Terms.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Account Registration</h2>
                    <p class="text-foreground/80 mt-4">
                        To access certain features of our services, you may be required to create an account. You agree to
                        provide accurate, current, and complete information during registration and to update such
                        information as necessary.
                    </p>
                    <p class="text-foreground/80 mt-4">
                        You are responsible for maintaining the confidentiality of your account credentials and for all
                        activities that occur under your account. You agree to notify us immediately of any unauthorized
                        use of your account.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">User Responsibilities</h2>
                    <p class="text-foreground/80 mt-4">
                        You agree to use our services only for lawful purposes and in accordance with these Terms. You
                        agree not to:
                    </p>
                    <ul class="text-foreground/80 mt-4 list-disc space-y-2 pl-6">
                        <li>Use our services in any way that violates applicable laws or regulations</li>
                        <li>Engage in unauthorized access to our systems or networks</li>
                        <li>Interfere with or disrupt the integrity or performance of our services</li>
                        <li>Attempt to gain unauthorized access to our services or related systems</li>
                        <li>Use our services to transmit viruses or other harmful code</li>
                        <li>Use our services for any fraudulent or malicious activities</li>
                    </ul>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Intellectual Property</h2>
                    <p class="text-foreground/80 mt-4">
                        All content, features, and functionality on our platform, including but not limited to text,
                        graphics, logos, icons, images, audio clips, digital downloads, data compilations, and software,
                        are the exclusive property of PantauTumbuh.id or its licensors and are protected by international
                        copyright, trademark, patent, trade secret, and other intellectual property or proprietary rights laws.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Subscription and Payments</h2>
                    <p class="text-foreground/80 mt-4">
                        Some features of our services may require payment of fees. All fees are stated in Indonesian Rupiah
                        (IDR) or US Dollar (USD) and are non-refundable unless otherwise specified. You agree to pay all fees associated with
                        and are non-refundable unless otherwise specified. You agree to pay all fees associated with
                        your use of our services in accordance with the pricing and payment terms in effect at the time of purchase.
                    </p>
                    <p class="text-foreground/80 mt-4">
                        We reserve the right to change our pricing and payment terms at any time. Any changes will be
                        effective immediately upon posting on our website.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Disclaimer of Warranties</h2>
                    <p class="text-foreground/80 mt-4">
                        Our services are provided "as is" and "as available" without warranties of any kind, either express
                        or implied. We do not warrant that our services will be uninterrupted, secure, or error-free.
                    </p>
                    <p class="text-foreground/80 mt-4">
                        We make no warranties or representations about the accuracy or completeness of the content available
                        through our services.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Limitation of Liability</h2>
                    <p class="text-foreground/80 mt-4">
                        To the fullest extent permitted by law, PantauTumbuh.id shall not be liable for any indirect,
                        incidental, special, consequential, or punitive damages, or any loss of profits or revenues,
                        whether incurred directly or indirectly, or any loss of data, use, goodwill, or other intangible
                        losses resulting from:
                    </p>
                    <ul class="text-foreground/80 mt-4 list-disc space-y-2 pl-6">
                        <li>Your access to or use of or inability to access or use our services</li>
                        <li>Any conduct or content of any third party on our services</li>
                        <li>Any content obtained from our services</li>
                        <li>Unauthorized access, use, or alteration of your transmissions or content</li>
                    </ul>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Termination</h2>
                    <p class="text-foreground/80 mt-4">
                        We may terminate or suspend your access to our services immediately, without prior notice or
                        liability, for any reason whatsoever, including without limitation if you breach these Terms.
                    </p>
                    <p class="text-foreground/80 mt-4">
                        Upon termination, your right to use our services will immediately cease. If you wish to terminate
                        your account, you may simply discontinue using our services.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Governing Law</h2>
                    <p class="text-foreground/80 mt-4">
                        These Terms shall be governed and construed in accordance with the laws of Indonesia, without
                        regard to its conflict of law provisions.
                    </p>
                    <p class="text-foreground/80 mt-4">
                        Our failure to enforce any right or provision of these Terms will not be considered a waiver of
                        those rights. If any provision of these Terms is held to be invalid or unenforceable by a court,
                        the remaining provisions of these Terms will remain in effect.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Changes to These Terms</h2>
                    <p class="text-foreground/80 mt-4">
                        We reserve the right, at our sole discretion, to modify or replace these Terms at any time. If a
                        revision is material, we will provide at least 30 days' notice prior to any new terms taking effect.
                    </p>
                    <p class="text-foreground/80 mt-4">
                        By continuing to access or use our services after those revisions become effective, you agree to
                        be bound by the revised terms.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Contact Us</h2>
                    <p class="text-foreground/80 mt-4">
                        If you have any questions about these Terms, please contact us at:
                    </p>
                    <p class="text-foreground/80 mt-4">
                        <a class="text-primary hover:underline" href="mailto:info@pantautumbuh.id">info@pantautumbuh.id</a>
                    </p>
                    <p class="text-foreground/80 mt-4">
                        Departemen Teknik Geomatika<br>
                        Institut Teknologi Sepuluh Nopember (ITS)<br>
                        Surabaya, Indonesia
                    </p>
                </div>
            </div>
        </div>
    </section>
</x-app-front-layout>
