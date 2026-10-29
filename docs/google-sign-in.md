# Google Sign-In

Disabled unless `GOOGLE_CLIENT_ID` is configured. Existing email/password login remains available.
No users are registered by Google sign-in. Existing roles, passwords and commission memberships are preserved.

## Google Console

1. Create/select a project in https://console.cloud.google.com/.
2. Open Google Auth Platform. Set the application name, support email and contact email in Branding.
3. Configure Audience. Use External if personal Gmail accounts must sign in. While in testing, add the intended test accounts where required by Google.
4. Create a client in Clients, type Web application.
5. Add Authorized JavaScript origins, without paths:
    - `http://localhost`
    - `http://localhost:8020`
    - `https://vacancy.atu.kz`
6. This integration uses a JavaScript callback, so no redirect URI or Client Secret is needed.
7. Copy the public client ID ending in `.apps.googleusercontent.com`.

Only basic identity information is used. Do not enable Gmail scopes or use SMTP app passwords.
Use `localhost:8020`, not a raw IP, for local Google testing. One Tap availability depends on HTTPS,
the browser, Google session and consent/cooldown settings. The Google button is the fallback on `/login`.

## Local Activation

Run from the project directory after installing dependencies:

```powershell
composer install
php artisan migrate
# Set GOOGLE_CLIENT_ID in .env, then:
php artisan optimize:clear
npm.cmd run build
php artisan serve --host=localhost --port=8020
```

If another dev server already uses 8020, use that server rather than starting a duplicate;
choose another port and register that exact localhost origin if necessary.

## Production Activation

Deploy through the normal reviewed release process, with a database backup first.
Set `GOOGLE_CLIENT_ID` in the private production environment, install locked Composer dependencies,
build frontend assets, run the additive migration and recreate/cache configuration for app/queue.
Do not use `migrate:fresh` or seed user passwords. Code and environment changes alone do not update an already built Docker image.

## Rules And Verification

- The server validates Google signature, audience, issuer, expiry, verified email and a one-time nonce bound to an HttpOnly SameSite cookie.
- First linking by email is restricted to Gmail or Google Workspace accounts whose mailbox ownership Google can establish. Other Google accounts must use email/password.
- Later identification uses Google's immutable `sub`, with a matching local email required. Confirming a local email change clears the former Google link.
- Technical `@hr-ai.invalid` accounts cannot use Google. Change to the actual email first.
- Unknown accounts are rejected, never registered automatically.
- Google endpoints are excluded from Telescope collection to avoid storing credentials and bearer tokens.
- The SDK is loaded only when configured and only for guests. Signing out disables Google auto-selection; automatic sign-in is not enabled.

Before production enablement, test with real configured Google accounts: known account success,
unknown account rejection, wrong audience, normal password login, logout, email change and both languages.
Automated tests use local RSA keys and a mocked Google public-key endpoint; they do not replace real Google consent testing.

References:

- https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid
- https://developers.google.com/identity/gsi/web/guides/verify-google-id-token
