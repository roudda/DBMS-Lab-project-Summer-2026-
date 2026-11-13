# MediCare+ Hospital Management System (PHP backend)

A centralized hospital DBMS covering Patient Management, Doctor & Department,
Appointment & Treatment, and Billing & Pharmacy — 18 normalized MySQL tables
behind a plain-PHP REST API, with a role-based login page and a dashboard
for each of the five staff roles.

This is the **PHP port** of the original Node.js/Express backend. The
database schema is unchanged; the backend language changed from Node.js to
PHP (no framework, no Composer dependencies), and the frontend has grown
from just the login page into a full login → dashboard flow.

## Folder structure

```
database/   medicare_plus_schema.sql   -> full MySQL DDL (18 tables)
            sample_data.sql            -> demo rows for all 18 tables (optional)
backend/    Plain PHP + PDO API        -> auth, patients, appointments, inventory, bills, doctors
frontend/   login.html                 -> role-based login page
            dashboard/*.html            -> one dashboard per role
```

```
backend/
  index.php              front controller: routes every request to a controller
  .htaccess               Apache/XAMPP rewrite rules (routes /api/... through index.php)
  config/db.php           PDO connection (getDb())
  helpers/env.php          tiny .env file loader
  helpers/http.php         jsonResponse() helper
  helpers/jwt.php          self-contained HS256 JWT encode/decode
  middleware/auth.php      requireAuth() / requireRole()
  controllers/*.php        one file per resource
  seed/seed.php            creates one demo login per role
```

```
frontend/
  config.js                   the ONE place API_BASE_URL is defined — edit this, nothing else
  login.html                 role-based sign-in — redirects into dashboard/<role>.html on success
  dashboard/shared.css        design tokens + layout shared by all 5 dashboards
  dashboard/shared.js         session handling, apiFetch() wrapper, small UI helpers
  dashboard/admin.html        patients (full CRUD) + appointments + inventory + billing, overview stats
  dashboard/doctor.html       own appointments (filtered by doctor_id) + read-only patient lookup
  dashboard/receptionist.html patients (register/edit, no delete) + appointments (book/update)
  dashboard/pharmacist.html   inventory + low-stock filter
  dashboard/cashier.html      bills list/create + record payments
```

## Added Phase-2 Features

This version extends the original CRUD demo into a more complete hospital workflow:

- **Admin live KPI/report panel:** patients, doctors, today's scheduled appointments, occupied rooms, low stock, expiring medicines, outstanding bills, and collected revenue.
- **Clinical records:** doctors can create treatment/diagnosis notes for visits.
- **Prescriptions:** doctors can prescribe medicines with dosage, duration, and quantity; inventory is reduced transactionally and insufficient stock is rejected.
- **Medical history API:** authorized clinical staff can read/add patient medical records (allergies, chronic conditions, notes).
- **Admissions & discharge:** reception can assign available rooms, admit patients, discharge them, and automatically release the room.
- **Appointment safeguards:** past appointments are rejected; when a doctor has a configured `Schedule`, bookings outside working hours are rejected; the existing unique constraint still prevents double booking.
- **Pharmacy expiry alerts:** pharmacist dashboard flags medicines that are expired or within 30 days of expiry, in addition to low-stock alerts.
- **Role-based clinical access:** new endpoints are protected by the existing JWT + role middleware.



 XAMPP (Apache + MariaDB + PHP)

1. Install XAMPP, open the control panel, click **Start** next to **Apache** and **MySQL**.
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) → **Import** → choose `database/medicare_plus_schema.sql` → Go.
   Repeat with `database/sample_data.sql` if you want demo data (see step 2 above).
3. Copy this whole project folder into XAMPP's `htdocs/`, e.g.:
   `C:\xampp\htdocs\medicare-plus-php\` (Windows) or `/Applications/XAMPP/htdocs/medicare-plus-php/` (Mac).
4. In `backend/`, copy `.env.example` to `.env`. XAMPP's MySQL defaults are usually:
   ```
   DB_HOST=localhost
   DB_USER=root
   DB_PASSWORD=
   DB_NAME=medicare_plus
   ```
   (If your `root` account has a password, or you created a dedicated MySQL user
   in phpMyAdmin instead, use those credentials here.)
5. Seed the demo logins — either run `php seed/seed.php` from a terminal (using XAMPP's
   bundled PHP), or open `http://localhost/medicare-plus-php/backend/seed/seed.php`
   in your browser once.
6. Your API is now served by Apache at:
   `http://localhost/medicare-plus-php/backend/api/...`
   (e.g. health check: `http://localhost/medicare-plus-php/backend/api/health`)
7. Update `API_BASE_URL` in **one** file — `frontend/config.js` — to match your XAMPP path:
   ```js
   const API_BASE_URL = 'http://localhost/medicare-plus-php/backend/api';
   ```
   (If you copied the project to a different folder name, use that name instead
   of `medicare-plus-php` in the path.) Both `login.html` and every dashboard
   page load this same file, so this one edit is all that's needed.
8. Open `http://localhost/medicare-plus-php/frontend/login.html` in your browser.

`backend/.htaccess` handles the URL rewriting needed for this (requires
`mod_rewrite`, which XAMPP enables by default) — no other Apache config is
needed. `index.php` also detects its own folder automatically, so routing
works correctly no matter what you name the project folder.

Demo logins created by the seed script (same for both options):

| Username           | Role         | Password  |
|--------------------|--------------|-----------|
| demo.admin         | Admin        | demo1234  |
| demo.doctor        | Doctor       | demo1234  |
| demo.receptionist  | Receptionist | demo1234  |
| demo.pharmacist    | Pharmacist   | demo1234  |
| demo.cashier       | Cashier      | demo1234  |

## 4. Open the frontend

Open `frontend/login.html` in a browser (or via Apache under XAMPP — see
above). Pick a role tab, sign in with the matching demo account, and you'll
be redirected straight into that role's dashboard
(`dashboard/admin.html`, `dashboard/doctor.html`, etc.).

The session (JWT + user info) is kept in `sessionStorage` — not
`localStorage` — so it's cleared automatically when the browser tab
closes, and each dashboard checks it on load: no session (or the wrong
role's session) bounces you back to the login page. See the comment at
the top of `dashboard/shared.js` for the security tradeoffs of this vs. a
server-set httpOnly cookie, which is what a production build should use
instead.

### What each dashboard does

| Role | Dashboard | Can do |
|---|---|---|
| Admin | `admin.html` | Everything: register/edit/delete patients, book appointments & update status, view inventory, view all bills, overview stats |
| Doctor | `doctor.html` | View **own** appointments only (filtered by the `doctor_id` in their token), update status, read-only patient lookup |
| Receptionist | `receptionist.html` | Register/edit patients (no delete — Admin only), book appointments, update status |
| Pharmacist | `pharmacist.html` | View inventory, filter to low-stock items only |
| Cashier | `cashier.html` | View/filter bills, create a new bill, record a payment (bill status auto-recalculates: Unpaid → Partially Paid → Paid) |

## API overview

| Method | Route                          | Auth                          | Purpose |
|--------|---------------------------------|--------------------------------|---------|
| POST   | /api/auth/login                 | none                            | Sign in, returns a JWT |
| GET    | /api/auth/me                    | any logged-in user              | Return decoded token info |
| GET    | /api/patients                   | any logged-in user              | List / search patients |
| GET    | /api/patients/:id                | any logged-in user              | Get one patient |
| POST   | /api/patients                   | Admin, Receptionist             | Register a patient |
| PUT    | /api/patients/:id                | Admin, Receptionist             | Update a patient |
| DELETE | /api/patients/:id                | Admin                           | Delete a patient |
| GET    | /api/doctors                     | any logged-in user              | List doctors (for dropdowns) |
| GET    | /api/appointments                | any logged-in user              | List appointments (filter by doctor_id/patient_id/date) |
| POST   | /api/appointments                | Admin, Receptionist             | Book an appointment (rejects conflicts via DB constraint) |
| PATCH  | /api/appointments/:id/status     | Admin, Receptionist, Doctor     | Update appointment status |
| GET    | /api/inventory                   | Admin, Pharmacist               | List medicine stock |
| GET    | /api/inventory/low-stock         | Admin, Pharmacist               | Stock-alert list (qty <= reorder_level) |
| GET    | /api/bills                       | Admin, Cashier                  | List bills (filter by status/patient_id) |
| GET    | /api/bills/:id                    | Admin, Cashier                  | Get one bill with its payment history |
| POST   | /api/bills                       | Admin, Cashier, Receptionist    | Create a bill against an appointment or admission |
| POST   | /api/bills/:id/payments           | Admin, Cashier                  | Record a payment; recalculates the bill's status |

`patientController.php` / `appointmentController.php` / `billController.php`
are meant as copy-able templates — the same controller -> route entry ->
requireAuth()/requireRole() pattern in `index.php` applies to the tables not
yet covered: Address, Emergency_Contact, Medical_Record, Department,
Schedule, Admission, Treatment, Prescription, and Medicine.

## Troubleshooting

**Login succeeds and redirects to a dashboard, but it instantly bounces back
to the login page.** This means the dashboard's very first API call got
rejected — almost always because `frontend/config.js`'s `API_BASE_URL`
doesn't point at the same backend your login just succeeded against (e.g.
you're running under XAMPP but `config.js` still has the `php -S` default,
or vice versa). Since dashboard v1, this is a single file to check — if
you're seeing this on an older copy of this project where `login.html` and
`dashboard/shared.js` each had their own separate `API_BASE_URL` line, make
sure both were updated to the same value, or better, pull the version of
this project that uses the shared `config.js` file so this can't happen again.

To confirm this is what's happening: open the browser's DevTools (F12) →
Network tab → log in → watch the very next request after the redirect. If
it shows a failed/401 response, or the login page's banner says "Signed
out: a dashboard request failed — Rejected by ...", the URL it names is
exactly what `config.js` is currently pointing at — compare that against
where your backend is actually running.

**"Database connection failed."** See `backend/config/db.php`'s error —
check MySQL is actually running, and that `backend/.env`'s `DB_*` values
match a real MySQL user/password on your machine (XAMPP's default `root`
has no password unless you set one yourself).

**Login gives "Invalid username, password, or role."** Either a genuine
typo, or `seed/seed.php` was never successfully run (e.g. it silently
failed earlier while `.env` had bad credentials) — check the `User_Login`
table has rows in phpMyAdmin, and re-run the seed script if it's empty.

## What changed vs. the Node.js version (and what didn't)

| | Node.js version | PHP version |
|---|---|---|
| Language/runtime | Node.js + Express | PHP 8 (no framework) |
| DB driver | mysql2/promise | PDO (pdo_mysql) |
| Password hashing | bcryptjs | PHP `password_hash()` / `password_verify()` (same bcrypt format — hashes are interchangeable) |
| JWT | jsonwebtoken package | Small custom HS256 implementation in `helpers/jwt.php` (no Composer dependency needed) |
| Routing | Express Router (routes/*.js) | Single front controller (`index.php`) matching method + path |
| Frontend | login page only | Login page + a dashboard per role |
| **Database schema** | 18-table MySQL schema | **Unchanged** |

Every endpoint was tested end-to-end against a real MySQL/MariaDB instance
(including under Apache the way XAMPP serves it): login + JWT issuance,
`/auth/me`, patient CRUD, appointment booking with duplicate-slot rejection
(409), role-based 403s, inventory/low-stock, and the full billing flow
(create bill → record payment → status recalculates).

## What's still open (per the Day 1-10 timeline)

- Remaining CRUD routes for the tables not yet covered above (Department,
  Schedule, Admission, Treatment, Prescription, Medicine — Address,
  Emergency_Contact, and Medical_Record specifically)
- Views, triggers, and the INSERT/UPDATE/DELETE/JOIN/GROUP BY query set each
  member owes for the report
- Viva prep notes

## Doctor prescriptions: multiple medicines per treatment

A single treatment can now contain multiple prescription rows. In the Doctor dashboard, select a saved treatment, click **+ Add medicine**, enter dosage/duration/quantity for each medicine, and click **Save prescription**. The backend validates all requested stock first and then inserts/deducts everything inside one database transaction. If any medicine is out of stock, the whole prescription is rejected and no stock is changed.

The existing `Prescription` table already supports this relationship (`treatment_id` is not unique), so no database schema migration is required.
