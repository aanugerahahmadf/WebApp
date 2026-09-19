// Firebase Auth login module — Google sign-in without Firebase's
// authorized-domain restriction.
//
// Why not signInWithPopup/signInWithRedirect?
//   Those flows are rejected with `auth/unauthorized-domain` unless the
//   current page origin is whitelisted in the Firebase console.
//   Instead we obtain the Google credential via Google Identity Services
//   (GIS) and exchange it with signInWithCredential(). That path does the
//   OAuth dance against Google (using the OAuth client's javascript_origins)
//   and then swaps the Google ID token for a Firebase session directly —
//   no Firebase authorized-domain check is involved.
//
//   Web:     GIS popup (always shows the Google account chooser)
//   Mobile:  signInWithRedirect + getRedirectResult (NativePHP WebView)

import app from '../firebase/firebase';
import {
    getAuth,
    GoogleAuthProvider,
    signInWithCredential,
    signInWithRedirect,
    getRedirectResult,
} from 'firebase/auth';

const auth = getAuth(app);
const provider = new GoogleAuthProvider();
provider.addScope('email');
provider.addScope('profile');
provider.setCustomParameters({ prompt: 'select_account' });

const CLIENT_ID = app.options.clientId || '';

function isNativeMobile() {
    return !!(window.AppPlatform && window.AppPlatform.isNativeMobile);
}

function csrfToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') : '';
}

async function sendTokenToBackend(idToken) {
    const res = await fetch('/auth/firebase/callback', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ id_token: idToken }),
    });

    let data = {};
    try {
        data = await res.json();
    } catch (e) {
        /* ignore parse errors */
    }

    if (!res.ok) {
        throw new Error(data.message || 'Login gagal. Silakan coba lagi.');
    }

    return data;
}

async function completeLogin(user) {
    const idToken = await user.getIdToken();
    const data = await sendTokenToBackend(idToken);

    if (data.redirect) {
        window.location.href = data.redirect;
        return;
    }

    window.location.href = '/user';
}

// ── Google Identity Services (web) ────────────────────────────────────

let gisLoaded = null;

function loadGisScript() {
    if (window.google && window.google.accounts) {
        return Promise.resolve();
    }
    if (gisLoaded) {
        return gisLoaded;
    }

    gisLoaded = new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = 'https://accounts.google.com/gsi/client';
        s.async = true;
        s.defer = true;
        s.onload = () => resolve();
        s.onerror = () => {
            gisLoaded = null;
            reject(new Error('Gagal memuat Google Identity Services.'));
        };
        document.head.appendChild(s);
    });

    return gisLoaded;
}

/**
 * Open the Google account chooser via GIS token client.
 * Resolves once the Firebase sign-in + backend login has completed.
 */
async function gisPopupLogin() {
    await loadGisScript();

    return new Promise((resolve, reject) => {
        const tokenClient = google.accounts.oauth2.initTokenClient({
            client_id: CLIENT_ID,
            scope: 'openid email profile',
            ux_mode: 'popup',
            callback: async (response) => {
                try {
                    console.log('[FirebaseAuth] GIS callback response keys:', response ? Object.keys(response) : response);

                    if (response && response.error) {
                        reject(new Error(
                            response.error === 'user_closed_popup' || response.error === 'popup_closed'
                                ? 'Login Google dibatalkan.'
                                : 'Login Google gagal.'
                        ));
                        return;
                    }

                    // Google may return either an id_token or an access_token.
                    const idToken = response && (response.id_token || response.credential);
                    const accessToken = response && response.access_token;

                    let credential;
                    if (idToken) {
                        credential = GoogleAuthProvider.credential(idToken);
                    } else if (accessToken) {
                        // Fallback: exchange the Google access token for a Firebase session.
                        credential = GoogleAuthProvider.credential(null, accessToken);
                    } else {
                        console.error('[FirebaseAuth] no id_token or access_token in response:', response);
                        reject(new Error('Google tidak mengembalikan ID token.'));
                        return;
                    }

                    // Swap the Google credential for a Firebase session.
                    const result = await signInWithCredential(auth, credential);
                    await completeLogin(result.user);
                    resolve();
                } catch (e) {
                    reject(e);
                }
            },
        });

        tokenClient.requestAccessToken();
    });
}

/**
 * Entry point used by the social-buttons / welcome Google button.
 * Returns a promise so the caller can toggle the loading state.
 */
export async function firebaseGoogleLogin() {
    if (isNativeMobile()) {
        // Redirect flow works inside NativePHP WebView (no popup support).
        await signInWithRedirect(auth, provider);
        return;
    }

    return gisPopupLogin();
}

// Mobile WebView: on return from the Google redirect page, finish the login.
getRedirectResult(auth)
    .then(async (result) => {
        if (result && result.user) {
            await completeLogin(result.user);
        }
    })
    .catch((error) => {
        console.error('[FirebaseAuth] redirect result error:', error);
    });

window.FirebaseAuthLogin = firebaseGoogleLogin;