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
                     <div class="bg-primary/10 text-primary flex items-center space-x-1 rounded-full px-3 py-1 text-xs font-medium">
                         <i class="ri-coins-line mr-1"></i>
                         <p><span id="current-myCredits">{{ Number::format(Auth::user()->current_credits, 2, locale: app()->getLocale()) }}</span> <span>Credit Points</span></p>
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
