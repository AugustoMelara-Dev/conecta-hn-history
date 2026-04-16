import { type Locator, type Page } from '@playwright/test';

function firstVisible(locator: Locator): Locator {
    return locator.first();
}

export function loginSubmitButton(page: Page): Locator {
    return firstVisible(
        page
            .getByTestId('login-submit')
            .or(page.getByRole('button', { name: /iniciar sesi[oó]n|iniciar sesion|login|sign in/i })),
    );
}

export function registerNextStepButton(page: Page): Locator {
    return firstVisible(
        page
            .getByTestId('register-next-step')
            .or(page.getByRole('button', { name: /continuar|siguiente|next/i })),
    );
}

export function registerSubmitButton(page: Page): Locator {
    return firstVisible(
        page
            .getByTestId('register-submit')
            .or(page.getByRole('button', { name: /crear negocio|crear cuenta|create account|create business/i })),
    );
}

export function registerTermsCheckbox(page: Page): Locator {
    return firstVisible(
        page
            .getByTestId('register-terms-checkbox')
            .or(page.locator('input[type="checkbox"]').first()),
    );
}

export function forgotPasswordHeading(page: Page): Locator {
    return firstVisible(
        page
            .getByTestId('forgot-password-heading')
            .or(page.getByRole('heading', { name: /forgot your password|olvidaste tu contrase[nñ]a|restablece tu contrase[nñ]a/i })),
    );
}

export function forgotPasswordSubmitButton(page: Page): Locator {
    return firstVisible(
        page
            .getByTestId('forgot-password-submit')
            .or(page.getByRole('button', { name: /send reset link|enviar enlace|restablecer/i })),
    );
}

export function forgotPasswordFeedback(page: Page): Locator {
    return firstVisible(
        page
            .getByTestId('forgot-password-feedback')
            .or(page.getByText(/password reset link|enlace para restablecer|no encontramos ning[uú]n usuario/i)),
    );
}
