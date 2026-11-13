// frontend/dashboard/shared.js
// Loaded by every role dashboard. Handles the session handoff from
// login.html, all API calls, and small UI helpers (theme, sidebar,
// banners, badges) shared across pages.
//
// API_BASE_URL comes from ../config.js, loaded before this file in every
// dashboard page — see that file to change the backend URL. Do not
// redefine API_BASE_URL here; a second copy is exactly what caused it to
// drift out of sync with login.html in the past.

const SESSION_KEY = 'medicareplus_session';

/**
 * The session (token + user) is kept in sessionStorage — NOT localStorage —
 * so it's automatically cleared when the tab/browser closes, and never
 * persists indefinitely on a shared computer. It still isn't as safe as a
 * server-set httpOnly cookie (a real production build should use one
 * instead of handing the JWT to client-side JS at all), but it's a
 * reasonable middle ground for a coursework demo that needs the login
 * page and dashboard pages to be separate URLs.
 */
function getSession() {
  try {
    const raw = sessionStorage.getItem(SESSION_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch (e) {
    return null;
  }
}

function clearSession() {
  sessionStorage.removeItem(SESSION_KEY);
}

/**
 * Call at the top of every dashboard page. Redirects to the login page
 * if there's no session, and redirects to the correct dashboard if the
 * logged-in user's role doesn't match the page they're on (e.g. a
 * Pharmacist somehow landing on admin.html).
 */
function requireSession(expectedRole) {
  const session = getSession();
  if (!session || !session.token || !session.user) {
    window.location.href = '../login.html?reason=no-session';
    return null;
  }
  if (expectedRole && session.user.role !== expectedRole) {
    window.location.href = session.user.role.toLowerCase() + '.html';
    return null;
  }
  return session;
}

function logout() {
  clearSession();
  window.location.href = '../login.html';
}

/**
 * Wraps fetch() with the auth header, JSON handling, and a single place
 * to react to an expired/invalid token (401 -> back to login).
 */
async function apiFetch(path, options = {}) {
  const session = getSession();
  const headers = Object.assign({}, options.headers || {});
  if (session && session.token) {
    headers['Authorization'] = 'Bearer ' + session.token;
  }
  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }

  let response;
  try {
    response = await fetch(API_BASE_URL + path, Object.assign({}, options, { headers }));
  } catch (err) {
    // fetch() itself threw — almost always means API_BASE_URL is wrong or
    // the backend isn't running/reachable at all (not a 401, so this does
    // NOT bounce back to login; the calling page shows this message instead).
    throw new Error('Could not reach the server at ' + API_BASE_URL + ' — is the backend running, and does config.js point to the right place?');
  }

  if (response.status === 401) {
    // Ask the same backend whether the token is actually invalid. This avoids
    // treating every protected-route failure as a mysterious session expiry.
    let detail = '';
    try {
      const probe = await fetch(API_BASE_URL + '/auth/me', {
        headers: { Authorization: session && session.token ? 'Bearer ' + session.token : '' }
      });
      const probeData = await probe.json().catch(() => ({}));
      detail = probeData.message || ('HTTP ' + probe.status);
    } catch (e) {
      detail = 'backend unreachable';
    }
    clearSession();
    const reason = encodeURIComponent('Rejected by ' + API_BASE_URL + path + ' (' + detail + ')');
    window.location.href = '../login.html?reason=' + reason;
    throw new Error('Session expired or rejected.');
  }

  let data = {};
  try {
    data = await response.json();
  } catch (e) {
    // no/invalid JSON body — fall through with an empty object
  }

  if (!response.ok) {
    throw new Error(data.message || ('Request failed (HTTP ' + response.status + ').'));
  }
  return data;
}

// ---- shared UI bits ----

function toggleTheme() {
  const html = document.documentElement;
  const isDark = html.getAttribute('data-theme') === 'dark';
  html.setAttribute('data-theme', isDark ? 'light' : 'dark');
}

function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  if (sidebar) sidebar.classList.toggle('open');
}

function showBanner(el, type, text) {
  el.className = 'banner show ' + type;
  el.textContent = text;
  if (type === 'success') {
    setTimeout(() => { el.className = 'banner'; }, 3500);
  }
}

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function formatMoney(value) {
  const n = Number(value);
  return isNaN(n) ? value : n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Renders a status string as a colored badge span. */
function statusBadge(status) {
  const map = {
    'Scheduled': 'badge-muted',
    'Completed': 'badge-success',
    'Cancelled': 'badge-danger',
    'No-Show': 'badge-warning',
    'Unpaid': 'badge-danger',
    'Partially Paid': 'badge-warning',
    'Paid': 'badge-success',
    'Available': 'badge-success',
    'Occupied': 'badge-warning',
    'Maintenance': 'badge-danger',
  };
  const cls = map[status] || 'badge-muted';
  return '<span class="badge ' + cls + '">' + escapeHtml(status) + '</span>';
}

/** Fills the sidebar user block + welcome heading once a session exists. */
function renderUserChrome(session) {
  const nameEls = document.querySelectorAll('[data-user-name]');
  nameEls.forEach(el => { el.textContent = session.user.name || session.user.username; });
  const roleEls = document.querySelectorAll('[data-user-role]');
  roleEls.forEach(el => { el.textContent = session.user.role; });
}
