<x-filament-panels::page>
    <form wire:submit.prevent="submit" data-testid="web-to-lead" class="space-y-6">
        {{ $this->form }}

        @if(config('web_to_lead.captcha.site_key'))
            <div
                x-data
                x-init="window.dovraWebToLeadCaptcha = token => $wire.set('data.captcha', token)"
                class="cf-turnstile"
                data-sitekey="{{ config('web_to_lead.captcha.site_key') }}"
                data-callback="dovraWebToLeadCaptcha"
            ></div>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @else
            <x-filament::section>
                <p class="text-sm text-danger-600 dark:text-danger-400">
                    La captura está deshabilitada hasta configurar la verificación CAPTCHA.
                </p>
            </x-filament::section>
        @endif

        <x-filament::button type="submit" :disabled="! config('web_to_lead.captcha.site_key')">
            Guardar contacto
        </x-filament::button>
    </form>
</x-filament-panels::page>
