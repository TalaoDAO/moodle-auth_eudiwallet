# EUDI Wallet Login — Moodle

Version 0.1.4 alpha. Publisher: Web3 Digital Wallet (Talao).
Contact: contact@talao.io. Licence: GPL-3.0-or-later (see LICENSE).

Install this directory as `auth/eudiwallet`, or upload the ZIP using Moodle's
plugin installer. Target: Moodle 4.5 LTS / PHP 8.1+, with Sodium, OpenSSL and cURL.
Initial isolated tests use Moodle 4.5.15+ / PHP 8.3 / MariaDB. A complete real Wallet
login on Moodle and other Moodle/PHP versions still require validation.

In Authentication settings, open EUDI Wallet Login and its configuration screen.
Copy the displayed callback, create a free Identity client at
https://login.openid4vc-hub.com/register?purpose=pid with `openid profile email`
and `email_verified` authorized, then import its private JSON and save.
Enable EUDI Wallet Login in Manage authentication. Keep a local administrator.
Account creation is off by default. Courses, roles and enrolments are not granted
by an identity presentation. Existing manual accounts can link from Preferences
after confirming their local Moodle password. No email-based account merging.

The contact email is separately verified by the bridge, not certified by the
identity Wallet. The secret is encrypted with Moodle core encryption; protect
and back up its private key outside the web root. Transactions are encrypted,
expire after fifteen minutes, and are purged by Moodle cron every ten minutes.
Persistent associations retain only a hash of issuer/subject, user ID and date.
Only authorized first/last name and verified email enter the Moodle profile.
Tokens, codes and PID documents are not stored in the plugin's tables.
The Privacy API supports export/deletion; deleting a Moodle user removes bindings.

The external bridge receives client ID, callback, scopes, state, nonce and PKCE
challenge; credentials and the returned code are exchanged server-side over TLS.
Local Moodle passwords, courses and grades are not sent to the bridge.
No shared credentials, remote executable code, refresh tokens or provider logout.
Configure access logs to omit callback query strings and exclude these pages from
caches. The browser-binding cookie is Secure/HttpOnly/SameSite=Lax.
Current free defaults are 50 starts/day per callback hostname and three concurrent
transactions; see registration for effective quotas. Privacy and terms:
https://talao.io/privacy.html and https://talao.io/cgu.html.

Documentation: https://openid4vc-hub.com/api/docs/bridge.html#89-moodle-setup
Sources and build instructions:
https://github.com/TalaoDAO/openid4vc-hub/tree/main/plugins/moodle

This plugin authenticates students; learning-certificate issuance into a Wallet
is planned separately and is not included. It is not yet approved or published
in Moodle's plugin directory.

## Returning students

An active Moodle session requires no new Wallet or email check. At a new login,
the student presents their Wallet identity again. The bridge reuses a verified
contact email for the same identity and sector for up to 30 days from verification;
logins do not extend that period. After expiry, removal of the stored contact,
or a requested email change, a new email code is needed.

Moodle associates the account by `(issuer, sub)`, never by email. A changed email
does not create a new account while this binding remains unchanged, and does not
automatically overwrite the existing Moodle profile. Subject continuity remains
subject to the bridge's documented identity/sector limits.

Hosted Pro: 99 EUR/month, 300 attempts per UTC day, 30 concurrent sign-ins,
120 starts/minute/client and 60 starts/minute/IP/client. Custom is on request.
All limits are provider-side; configuration JSON and client credentials do not
change when the operator updates the plan. Failed/abandoned attempts count.
