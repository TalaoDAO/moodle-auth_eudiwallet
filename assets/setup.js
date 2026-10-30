// SPDX-License-Identifier: GPL-3.0-or-later
// Browser-only review; POST credentials only when the administrator saves.
document.addEventListener('DOMContentLoaded', () => {
    const file = document.getElementById('wallet-file');
    if (!file) { return; }
    const value = document.getElementById('wallet-configuration');
    const status = document.getElementById('wallet-status');
    const review = document.getElementById('wallet-review');
    const save = document.getElementById('wallet-save');
    const form = document.getElementById('wallet-setup');
    let revision = 0;
    file.addEventListener('change', async () => {
        const current = ++revision;
        value.value = ''; review.textContent = ''; save.disabled = true;
        try {
            const selected = file.files[0];
            if (!selected || selected.size > 16384) { throw new Error('invalid'); }
            const raw = await selected.text();
            if (current !== revision) { return; }
            const data = JSON.parse(raw);
            const scopes = typeof data.scope === 'string' ? data.scope.trim().split(/\s+/).sort().join(' ') : '';
            const issuer = new URL(data.issuer);
            if (data.verification_purpose !== 'identity' || typeof data.client_id !== 'string' ||
                !/^[A-Za-z0-9._-]{1,128}$/.test(data.client_id) ||
                typeof data.client_secret !== 'string' || data.client_secret.length < 16 || data.client_secret.length > 4096 ||
                issuer.protocol !== 'https:' || issuer.username || issuer.password || issuer.pathname !== '/' ||
                issuer.search || issuer.hash || issuer.port || data.issuer !== issuer.origin ||
                JSON.stringify(data.redirect_uris) !== JSON.stringify([form.dataset.callback]) ||
                scopes !== 'email openid profile' || data.response_type !== 'code' || data.code_challenge_method !== 'S256' ||
                !['client_secret_basic', 'client_secret_post'].includes(data.token_endpoint_auth_method)) { throw new Error('invalid'); }
            value.value = raw;
            review.textContent = JSON.stringify({issuer: data.issuer, client_id: data.client_id,
                redirect_uris: data.redirect_uris, scope: data.scope, token_endpoint_auth_method: data.token_endpoint_auth_method}, null, 2);
            status.textContent = status.dataset.success;
            save.disabled = false;
        } catch (_) {
            if (current === revision) { status.textContent = status.dataset.error; }
        }
    });
    window.addEventListener('pagehide', () => { revision++; value.value = ''; save.disabled = true; });
});
