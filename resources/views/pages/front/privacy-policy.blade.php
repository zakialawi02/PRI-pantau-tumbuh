@section('title', 'Privacy Policy | ' . config('app.name'))

@section('meta_description', 'Privacy Policy for PantauTumbuh.id - Learn how we collect, use, and protect your personal information.')
@section('meta_keywords', 'privacy policy, data protection, personal information, pantautumbuh, pantautumbuh.id')

@section('og_title', 'Privacy Policy | PantauTumbuh.id')
@section('og_description', 'Learn how we collect, use, and protect your personal information at PantauTumbuh.id.')

<x-app-front-layout>
    <section class="bg-background py-16">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto mb-12 text-center">
                <h1 class="text-foreground text-3xl font-bold tracking-tight sm:text-4xl">Privacy Policy</h1>
                <p class="text-foreground/80 mt-4 text-lg">
                    Last updated: {{ date('F d, Y') }}
                </p>
            </div>

            <div class="bg-card text-card-foreground rounded-lg p-6 shadow-lg md:p-8">
                <div class="prose prose-lg max-w-none">
                    <h2 class="text-foreground text-xl font-semibold">Introduction</h2>
                    <p class="text-foreground/80 mt-4">
                        Welcome to PantauTumbuh.id. We are committed to protecting your personal information and your right to privacy.
                        This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you visit our
                        website and use our services.
                    </p>
                    <p class="text-foreground/80 mt-4">
                        Please read this privacy policy carefully. If you do not agree with the terms of this privacy policy,
                        please do not access the site or use our services.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Information We Collect</h2>
                    <p class="text-foreground/80 mt-4">
                        We may collect personal information that you voluntarily provide to us when you register on the site,
                        express an interest in obtaining information about us or our products and services, participate in activities
                        on the site, or otherwise contact us.
                    </p>
                    <p class="text-foreground/80 mt-4">
                        The personal information we collect may include:
                    </p>
                    <ul class="text-foreground/80 mt-4 list-disc space-y-2 pl-6">
                        <li>Name</li>
                        <li>Email address</li>
                        <li>Phone number</li>
                        <li>Mailing address</li>
                        <li>Company/organization name</li>
                        <li>Payment information</li>
                        <li>Geographic location data</li>
                        <li>Usage data and preferences</li>
                    </ul>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">How We Use Your Information</h2>
                    <p class="text-foreground/80 mt-4">
                        We use personal information collected via our website for a variety of business purposes described below:
                    </p>
                    <ul class="text-foreground/80 mt-4 list-disc space-y-2 pl-6">
                        <li>To facilitate account creation and logon process</li>
                        <li>To manage user accounts</li>
                        <li>To send administrative information</li>
                        <li>To fulfill and manage purchases of products and services</li>
                        <li>To respond to user inquiries and offer support</li>
                        <li>To send marketing and promotional communications</li>
                        <li>To protect our services</li>
                        <li>To comply with applicable laws and regulations</li>
                    </ul>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Cookies and Tracking Technologies</h2>
                    <p class="text-foreground/80 mt-4">
                        We may use cookies and similar tracking technologies to access or store information.
                        Cookies help us provide you with a better experience and improve our services.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Third-Party Services</h2>
                    <p class="text-foreground/80 mt-4">
                        We may share your information with third-party vendors, service providers, contractors, or agents
                        who perform services for us or on our behalf and require access to such information to do that work.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Data Security</h2>
                    <p class="text-foreground/80 mt-4">
                        We use administrative, technical, and physical security measures to help protect your personal information.
                        While we have taken reasonable steps to secure the personal information you provide to us,
                        please be aware that despite our efforts, no security measures are perfect or impenetrable.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Data Retention</h2>
                    <p class="text-foreground/80 mt-4">
                        We will retain your personal information only for as long as is necessary for the purposes set out in this
                        Privacy Policy, unless a longer retention period is required or permitted by law.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Your Privacy Rights</h2>
                    <p class="text-foreground/80 mt-4">
                        You have certain rights regarding your personal information, including the right to access, correct,
                        update, or delete your information. If you would like to exercise any of these rights,
                        please contact us using the contact information provided below.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Changes to This Privacy Policy</h2>
                    <p class="text-foreground/80 mt-4">
                        We may update this Privacy Policy from time to time. The updated version will be indicated by an updated
                        "Revised" date and the updated version will be effective as soon as it is accessible.
                    </p>

                    <h2 class="text-foreground mt-8 text-xl font-semibold">Contact Us</h2>
                    <p class="text-foreground/80 mt-4">
                        If you have questions or comments about this policy, you may email us at
                        <a class="text-primary hover:underline" href="mailto:{{ config('app-constants.info_email') }}">{{ config('app-constants.info_email') }}</a>
                        or by post to:
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
