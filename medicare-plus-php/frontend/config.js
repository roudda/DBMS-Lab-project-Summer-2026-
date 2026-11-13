// frontend/config.js
// Automatically targets the backend folder beside the frontend when running
// under XAMPP/Apache. This prevents login and dashboards from drifting onto
// different API URLs. If you intentionally run `php -S localhost:5000`, set
// window.MEDICARE_API_BASE_URL before this script loads.
(function () {
  if (window.MEDICARE_API_BASE_URL) return;
  const path = window.location.pathname.replace(/\/frontend(?:\/.*)?$/, '/backend/api');
  window.MEDICARE_API_BASE_URL = window.location.origin + path;
})();

const API_BASE_URL = window.MEDICARE_API_BASE_URL || 'http://localhost/medicare-plus-php/backend/api';
