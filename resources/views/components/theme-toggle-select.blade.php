<!-- Switch/Toggle -->
<div {{ $attributes->merge(['class' => 'hs-dropdown']) }}>
    <button class="hs-dropdown-toggle hs-dark-mode focus:outline-hidden text-foreground/60 hover:text-secondary focus:text-secondary group flex items-center font-medium" id="hs-dropdown-dark-mode" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Dropdown">
        <svg class="hs-dark-mode-active:hidden block size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"></path>
        </svg>
        <svg class="hs-dark-mode-active:block hidden size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2"></path>
            <path d="M12 20v2"></path>
            <path d="m4.93 4.93 1.41 1.41"></path>
            <path d="m17.66 17.66 1.41 1.41"></path>
            <path d="M2 12h2"></path>
            <path d="M20 12h2"></path>
            <path d="m6.34 17.66-1.41 1.41"></path>
            <path d="m19.07 4.93-1.41 1.41"></path>
        </svg>
    </button>

    <div class="hs-dropdown-menu hs-dropdown-open:opacity-100 bg-neutral z-10 mb-2 mt-2 hidden origin-bottom-left space-y-0.5 rounded-lg p-1 opacity-0 shadow-md transition-[margin,opacity] duration-300" id="selectThemeDropdown" role="menu" aria-orientation="vertical" aria-labelledby="hs-dropdown-dark-mode">
        <button class="focus:outline-hidden text-foreground/80 hover:bg-background/20 focus:bg-background/20 flex w-full items-center gap-x-3.5 rounded-lg px-3 py-2 text-sm" data-hs-theme-click-value="default" type="button">
            Default (Light)
        </button>
        <button class="focus:outline-hidden text-foreground/80 hover:bg-background/20 focus:bg-background/20 flex w-full items-center gap-x-3.5 rounded-lg px-3 py-2 text-sm" data-hs-theme-click-value="dark" type="button">
            Dark
        </button>
        <button class="focus:outline-hidden text-foreground/80 hover:bg-background/20 focus:bg-background/20 flex w-full items-center gap-x-3.5 rounded-lg px-3 py-2 text-sm" data-hs-theme-click-value="auto" type="button">
            Auto (System)
        </button>
    </div>
</div>
