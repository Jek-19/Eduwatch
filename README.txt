# EduWatch

**Academic Early-Warning and Dropout Prediction System: working prototype (PHP + MySQL)**

This is the system from your design diagram (`docs/EduWatch_System_Flow_v2.svg`), built as real, working pages.
Students see their own grades, charts, risk level and an insight message. Teachers manage grades, see who is at
risk across the class, and every grade change is recorded in an audit log.

**This version is split into a FRONTEND and a BACKEND:**

- `frontend/` = what the browser shows: plain **.html** pages, one **.css** file and **.js** files. No PHP in it.
- `backend/`  = the server side: **PHP** files that talk to the two MySQL databases and answer in **JSON**. No HTML in it.

The frontend asks the backend for data with `fetch()` (for example `backend/api/student_dashboard.php`), and the
backend only ever answers with JSON. That is the whole connection between the two.

---

## 1. Quick start (XAMPP on Windows)

1. Install **XAMPP** and start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy the whole `eduwatch` folder into `C:\xampp\htdocs\` so you have `C:\xampp\htdocs\eduwatch\frontend\login.html`
   and `C:\xampp\htdocs\eduwatch\backend\api\login.php`. **Keep `frontend` and `backend` side by side** in the same folder.
3. Open **http://localhost/phpmyadmin** → **Import** and import these two files, **in this order**:
   1. `backend/database/01_eduwatch_auth.sql` (creates the login database `eduwatch_auth`)
   2. `backend/database/02_eduwatch_academic.sql` (creates the grades database `eduwatch_academic`)
4. Open **http://localhost/eduwatch/** (it forwards you to `frontend/index.html`, then to the login page).

That is all. The default `backend/config.php` already matches a fresh XAMPP (MySQL user `root`, empty password).
If your MySQL root has a password, put it in `backend/config.php`.

**Requirements:** PHP 8.0 or newer (built and tested on 8.3) with `pdo_mysql` and `mbstring` (both are on in XAMPP);
MySQL 5.7+ / MariaDB 10.3+. The browser needs JavaScript turned on (the pages are drawn by the .js files).

### Demo accounts (made-up data)

| Role | Username | Password |
|---|---|---|
| Teacher (staff) | `admin` | `Admin@12345` |
| Teacher (staff) | `prof.lopez` | `Teacher@123` |
| Student | any ID below, e.g. `224-00001` | `Student@123` |

Sample students: `224-00001` Santos, `124-00002` Reyes, `224-00003` Cruz, `124-00004` Garcia, `224-00005` Dela Cruz,
`124-00006` Mendoza, `224-00007` Ramos, `124-00008` Torres, `224-00009` Villanueva, `124-00010` Navarro,
`224-00011` Aquino, `124-00012` Bautista (no grades yet).

They were chosen to show every risk level: Ramos and Dela Cruz are **High**; Cruz, Garcia, Torres and Aquino are
**Medium**; the rest are **Low**; Bautista shows **No data yet**.
**Change or delete these demo accounts before using real data.**

---

## 2. How the frontend and backend fit together

```
Browser                                   Server (XAMPP)
-------                                   --------------
frontend/login.html  --- assets/js/pages/login.js ---fetch---> backend/api/login.php ---> MySQL (auth DB)
frontend/student.html --- pages/student.js ---fetch---> backend/api/student_dashboard.php ---> MySQL (academic DB)
                          assets/js/charts.js draws the charts (Chart.js)   <--- JSON ---
```

- Every page is a static `.html` file with its own `assets/js/pages/<page>.js`.
- `assets/js/api.js` is the ONLY file that talks to the backend (adds the CSRF token, handles "logged out" answers).
- `assets/js/config.js` holds the one setting that says where the backend is (`API_BASE`, default `../backend/api`).
- The backend and frontend must be on the same site (same origin) so the login cookie is sent with each request.

### The API (all answers are JSON: `{"ok": true, ...}` or `{"ok": false, "error": "...", "code": "..."}`)

| Endpoint (`backend/api/`) | Method | Who | What it does |
|---|---|---|---|
| `session.php` | GET | anyone | Who is logged in + the CSRF token |
| `login.php` | POST | anyone | Log in (ID/username + password) |
| `logout.php` | POST | logged in | Log out |
| `forgot.php` | POST | anyone | Email a one-time reset link |
| `reset.php` | GET / POST | anyone with the link | Check the link / save the new password |
| `settings.php` | GET / POST | logged in | Dark mode and tone |
| `change_password.php` | POST | logged in | Change password (also the forced first-login change) |
| `student_dashboard.php` | GET | student | Own grades, risk level, insight message |
| `teacher_dashboard.php` | GET | teacher | Student list (`?q=` search), at-risk list, counts |
| `predict.php` | POST | teacher | Recalculate everyone's risk |
| `grades.php` | GET / POST | teacher | Read, add, edit, delete grades; makeup flag |
| `student_create.php` | POST | teacher | Add a student + login account |
| `student_info.php` | GET | teacher | Name behind a student ID (QR page) |
| `audit.php` | GET | teacher | Audit log (`?student=` filter) |

Status codes: `401` not logged in / session timed out, `403` wrong role or must change password first,
`400`/`422` bad input, `429` too many failed logins.

### How the pages match your design diagram

| Diagram box | Frontend page (HTML + JS) | Backend (PHP) | Database used |
|---|---|---|---|
| Homepage / Login | `login.html`, `pages/login.js` | `api/login.php`, `includes/auth.php` | Auth DB (`users`, `login_attempts`) |
| Credentials valid? → error, lock after too many tries | `login.html` | `api/login.php`, `includes/auth.php` | Auth DB |
| Check role in users table | `assets/js/layout.js` | `api/session.php` | Auth DB |
| QR Scan (optional) | `qr_scan.html`, `pages/qr_scan.js` | none | none |
| Forgot Password (email → one-time link → new password) | `forgot.html`, `reset.html` | `api/forgot.php`, `api/reset.php`, `includes/mailer.php` | Auth DB (`password_resets`) |
| Student Dashboard | `student.html`, `pages/student.js` | `api/student_dashboard.php` | Academic DB (read-only) + settings from the session |
| Teacher Dashboard (student list, at-risk list) | `teacher.html`, `pages/teacher.js` | `api/teacher_dashboard.php` | Academic DB |
| Grade create / edit / delete | `grades.html`, `pages/grades.js` | `api/grades.php` | Academic DB (`grades`, `grade_audit_log`) |
| Risk Prediction Module | (button on `teacher.html`) | `includes/risk.php`, `api/predict.php` | Academic DB (`risk_results`) |
| Settings (dark mode, tone, change password) | `settings.html`, `pages/settings.js` | `api/settings.php`, `api/change_password.php` | Auth DB (`user_settings`, `users`) |
| Log-out | "Log out" in the header | `api/logout.php` | none |

Extras that support the design: `student_new.html` (add a student + login account), `student_qr.html` (print a QR code
for a student ID), `audit.html` (view the audit log), `backend/tools/create_teacher.php` (create staff accounts).
The Academic DB also has a small `semesters` table (not drawn in the diagram) so grades belong to a semester.

**The two databases are linked only by the student ID** (`users.username` = `students.student_id`). Passwords and
grades never sit in the same database.

### Third-party component for data visualization (SIA101 Deliverable 10)

Charts use **Chart.js** (third-party, MIT, bundled in `frontend/assets/js/vendor/`). Following Component-based Development,
the pages never call Chart.js directly: they hand plain data to the wrapper `frontend/assets/js/charts.js`
(`EW.charts.subjectBar`, `EW.charts.trendLine`, `EW.charts.riskDoughnut`). To change the chart library, only that one file changes,
and if it is removed the rest of EduWatch still works (only the charts disappear).

---

## 3. How the risk level works

`backend/includes/risk.php` uses a simple, explainable rule-based score. For each subject it uses the average of the terms
recorded so far (Prelim, Midterm, Pre-final, Final):

| Rule | Points |
|---|---|
| Subject average **below the passing grade** (75) | +30 |
| Subject average **within 5 points above** passing (75 to 79.99) | +15 |
| Latest term **dropped 5 or more points** compared with the term before it | +10 |
| Student has **makeup grades** on record | +10 (once) |

**Level:** 40 or more points = **High**, 15 to 39 = **Medium**, below 15 = **Low**, no grades = **No data yet**.
Points are capped at 100. Gender and the ID number are never used.

- The passing grade is `passing_grade` in `backend/config.php`.
- The score is recalculated automatically every time a teacher adds, changes or deletes a grade (or changes the
  makeup flag), and on demand with **Recalculate all risk**.
- It says "at risk", not "will fail": it is an early-warning estimate, not a verdict.
- To use a trained model later, replace `risk_compute()` in `backend/includes/risk.php`. Nothing else has to change.

### Insight messages (formal or humor)

The wording on the student dashboard lives in `backend/includes/insights.php`, grouped by tone and risk level. You can edit
or add lines freely. `{name}` is the first name and `{subjects}` the subjects that need attention. Students pick
the tone in **Settings**.

---

## 4. Forgot password and email

Passwords are stored hashed, so they can never be "looked up", only reset. The flow: enter email → a one-time link
(valid 30 minutes, works once) → choose a new password. Only a hash of the link token is stored.

Set the mode in `backend/config.php` (`mail` → `mode`):

| Mode | What happens | Use it for |
|---|---|---|
| `screen` (default) | No email is sent; the reset link is shown on the page | Demos without internet |
| `log` | The email text is written to `backend/storage/mail_log.txt` | Testing |
| `smtp` | A real email is sent through PHPMailer | Real use |

**Gmail example** (`smtp` mode): `host` `smtp.gmail.com`, `port` `587`, `secure` `tls`, `username` your Gmail address,
`password` a Google **App Password** (Google Account → Security → 2-Step Verification → App passwords).
The demo accounts use `@example.com` addresses; put a real email on an account to receive mail.

> Do not leave `screen` mode on for real users, because it shows the reset link on the page.

---

## 5. QR codes

- A teacher opens **Student list → QR** to print a QR code for a student. **The QR contains only the student ID**,
  so it is safe on an ID card.
- On the login page, **Scan QR code** opens the camera, reads the ID and fills it in. **The password is still required.**
- Browsers only allow the camera on **https://** pages or on **localhost**.

---

## 6. Adding people

- **Students:** teacher dashboard → **Add student**. It creates the student record and a login with a one-time
  temporary password (shown once). The student must choose a new password at first login.
- **Teachers:** each teacher should have their **own** account so the audit log shows who changed a grade.
  Open a terminal in this folder and run (on XAMPP, use `C:\xampp\php\php.exe`):

  ```
  php backend/tools/create_teacher.php prof.santos "Prof. Ana Santos" ana.santos@school.edu
  ```
- **Subjects and semesters** are managed in phpMyAdmin (tables `subjects` and `semesters`; set `is_current = 1` on
  the semester in use).

---

## 7. Security features included

- Passwords hashed with bcrypt (`password_hash`); minimum 8 characters, at least one letter and one number.
- Login lock after 5 wrong passwords in 15 minutes (per username); "Forgot password" clears the lock.
- Same error message for "unknown user" and "wrong password"; session ID renewed at login; auto log-out after
  30 minutes of inactivity.
- Every change request (POST) must carry the CSRF token in the `X-CSRF-Token` header; every database query is a prepared statement; the pages insert text with `textContent` (never as raw HTML).
- Role checks on every API endpoint on the server (a student cannot call teacher endpoints, whatever the page does).
- Reset links are random, one-time, expire, and only their hash is stored; a new request cancels older links;
  the "email sent" message is identical whether or not the email exists.
- Two separate databases, optionally with two separate MySQL users (`backend/database/00_optional_db_users.sql`), so the
  grades side cannot read password hashes.
- Content-Security-Policy, no inline scripts (every script is a file), `.htaccess` rules that block direct access to `backend/includes/`,
  `backend/database/`, `backend/storage/`, `backend/config.php` and `.sql` files.
- Every grade change is written to the audit log (who, what, old value → new value).

For a real deployment also use HTTPS, set `debug` to `false` (the default), change all demo passwords, and keep
`backend/config.php` private.

---

## 8. Folder map

```
eduwatch/
├─ index.html                     forwards to frontend/index.html
├─ frontend/                      HTML + CSS + JS only (what the browser shows)
│  ├─ index.html  login.html  forgot.html  reset.html  qr_scan.html
│  ├─ student.html  settings.html
│  ├─ teacher.html  grades.html  student_new.html  student_qr.html  audit.html
│  ├─ .htaccess                   security headers (Content-Security-Policy, camera for the QR page)
│  └─ assets/
│     ├─ css/style.css            all the styling (light + dark theme)
│     └─ js/
│        ├─ config.js             where the backend is (API_BASE)
│        ├─ theme.js              applies dark mode before the page is drawn
│        ├─ api.js                the only code that talks to the backend
│        ├─ ui.js  layout.js      small helpers, shared header/footer, login/role checks
│        ├─ charts.js             Chart.js wrapper component
│        ├─ pages/                one script per page (login.js, student.js, teacher.js, ...)
│        └─ vendor/               third-party libraries: Chart.js, html5-qrcode, qrcode-generator
├─ backend/                       PHP only (JSON answers, no HTML)
│  ├─ api/                        the endpoints listed above
│  ├─ includes/                   bootstrap, auth, api helpers, risk module, insights, mailer
│  ├─ config.php                  settings (database, mail, passing grade)
│  ├─ database/                   01 auth.sql, 02 academic.sql, 00 optional mysql users
│  ├─ tools/create_teacher.php    command-line staff account creator
│  ├─ vendor/phpmailer/           PHPMailer 6.9.3 (LGPL) for real email
│  └─ storage/                    mail log (log mode)
└─ docs/                          your system diagrams (v0 original, v1, v2), open them in draw.io
```

---

## 9. Troubleshooting

- **Pages stay blank or say "Could not reach the server":** make sure Apache and MySQL are running, and that the
  `backend` folder sits next to the `frontend` folder (or change `API_BASE` in `frontend/assets/js/config.js`).
- **You are sent back to the login page every time:** open the site through the same address every time
  (for example always `http://localhost/eduwatch/`), not by double-clicking the .html files. The pages need Apache.
- **"Something went wrong" page:** MySQL is not running, or `backend/config.php` has the wrong user/password. To see the
  real error, set `'debug' => true` temporarily.
- **Login says invalid for a demo account:** make sure you imported `01_eduwatch_auth.sql`; passwords are
  case-sensitive.
- **Camera does not start:** use `http://localhost/...` or https, and allow camera permission in the browser.
- **No email arrives (`smtp` mode):** check host, port, secure and the App Password; errors are written to
  PHP's error log.
- **Wrong times in the audit log:** change `timezone` in `backend/config.php`.

---

## 10. Not included (ideas for later)

- A trained machine-learning model (the rule-based module is built to be swapped).
- An AI chatbot, and an admin screen for editing subjects and semesters (use phpMyAdmin for now).
- Two-factor login and a "log out other devices" option.

Third-party code bundled in this folder: Chart.js (MIT), html5-qrcode (Apache-2.0), qrcode-generator (MIT),
PHPMailer (LGPL-2.1).
