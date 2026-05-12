import type { Locator, Page } from '@playwright/test';

export function loginSubmitButton(page: Page): Locator {
  return page.getByTestId('login-submit').first();
}

export function registerNextStepButton(page: Page): Locator {
  return page.getByTestId('register-next-step').first();
}

export function registerSubmitButton(page: Page): Locator {
  return page.getByTestId('register-submit').first();
}

export function registerTermsCheckbox(page: Page): Locator {
  return page.getByTestId('register-terms-checkbox').first();
}
