 <header class="z-48 lg:ps-65 border-foreground/20 bg-neutral inset-x-0 top-0 flex w-full flex-wrap border-b py-2.5 text-sm md:flex-nowrap md:justify-start">
     <nav class="mx-auto flex w-full basis-full items-center px-4 sm:px-6">
         <div class="me-5 lg:me-0 lg:hidden">
             <!-- Logo -->
             <a class="focus:outline-hidden inline-block flex-none rounded-md text-xl font-semibold focus:opacity-80" href="#" aria-label="Preline">
                 <x-application-logo class="h-auto max-h-7 max-w-28 dark:invert" />
             </a>

             <div class="ms-1 lg:hidden">

             </div>
         </div>

         <div class="ms-auto flex w-full items-center justify-end gap-x-1 md:justify-between md:gap-x-3">

             <div class="hidden md:block">

             </div>

             <div class="flex flex-row items-center justify-end gap-1">
                 @auth
                     <!-- Credit Display for Authenticated Users -->
                     <div class="group relative">
                         <div class="bg-primary/10 text-primary flex cursor-pointer items-center space-x-1 rounded-full px-3 py-1 text-xs font-medium">
                             <i class="ri-coins-line mr-1"></i>
                             <p><span id="current-myCredits">{{ Number::format(Auth::user()->current_credits, 2, locale: app()->getLocale()) }}</span> <span>Credit Points</span></p>
                         </div>

                         <!-- Dropdown CTA for purchasing credit points -->
                         <div class="absolute right-0 z-50 hidden w-64 transition duration-200 ease-in-out group-hover:block">
                             <div class="bg-background rounded-md py-2 shadow-xl">
                                 <div class="text-foreground/50 border-b px-4 py-2 text-xs">Your current balance</div>
                                 <a class="text-foreground/70 hover:bg-foreground/10 block px-4 py-3 text-sm" href="{{ route('admin.purchase-credits') }}">
                                     <div class="flex items-center">
                                         <i class="ri-coins-line mr-3 text-lg"></i>
                                         <div>
                                             <div class="font-medium">Purchase More Credits</div>
                                             <div class="text-foreground/50 text-xs">Get more credit points to access more premium features</div>
                                         </div>
                                     </div>
                                 </a>
                             </div>
                         </div>
                     </div>
                 @else
                     <div class="bg-primary/10 text-primary flex items-center space-x-1 rounded-full px-3 py-1 text-xs font-medium">
                         <i class="ri-coins-line mr-1"></i>
                         <p><span id="current-myCredits">-</span> <span>Credit Points</span></p>
                     </div>
                 @endauth

                 <!-- Theme Toggle -->
                 <x-theme-toggle class="mx-2" />

                 <!-- User Dropdown -->
                 <x-dashboard.account-dropdown />
             </div>
         </div>
     </nav>
 </header>
