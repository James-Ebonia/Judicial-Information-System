# Judicial Information System (J.I.S)

A web-based legal case management system that connects judges, prosecutors, lawyers,
investigators and court clerks on one platform. A judge can open an assigned case and see
the parties, witnesses, police blotter, evidence, hearing history and pending actions in one place.

> Built as a school project for BS Information Systems. It uses **sample data only**.
> Do not use it with real confidential case information.

## Features

**Case management**
- Case profiles with parties, classification, court, assigned personnel and a full summary
- Case status tracking: Pending, Under Investigation, Under Prosecution, Awaiting Hearing, Ongoing, Resolved, Archived
- Search and filter by reference number, title, parties, witnesses, blotter number, personnel, filing date and status

**Case records**
- Witness and persons-involved records, with contact details encrypted (AES-256-GCM)
- Police blotter entries that are never overwritten; corrections are saved as new linked entries
- Document and evidence repository with file validation, versioning and access-controlled downloads
- Hearing scheduling, outcomes, and a calendar view
- Legal notes and a per-case activity timeline

**Users and roles**
- Six system roles: Administrator, Lawyer, Prosecutor, Judge, Investigator, Clerk of Court
- 42 lawyer specializations (litigation, corporate, family, IP, tax, and more)
- Login requires the correct role; a wrong role selection is rejected
- Lawyer registration with Bar roll number, approved by an administrator before the account is active
- Searchable role dropdown and show/hide password on the login page

**Security**
- Password hashing (`password_hash`), prepared statements, CSRF tokens, output escaping
- Role-based visibility: judges, lawyers, prosecutors and investigators only see cases assigned to them
- Login lockout after 3 failed attempts, with a security alert sent to administrators
- 30-minute session expiry, login history, and an audit log of important actions

## Tech stack

HTML5, CSS3, JavaScript, PHP 8+, MySQL / MariaDB

## Installation

1. Install [XAMPP](https://www.apachefriends.org/) (or any PHP 8+ and MySQL host).
2. Copy this project folder into `htdocs`.
3. Copy `inc/config.example.php` to `inc/config.php` and set your database login
   and a long random `key` (used to encrypt witness contact details).
4. Start Apache and MySQL, then open `http://localhost/<folder>/install.php` once.
   It creates the database, tables and sample data.
5. **Delete `install.php`** after installing.
6. Open `http://localhost/<folder>/` and log in.

## Sample accounts

All sample accounts use the password `Passw0rd!Demo`. Change it before any real use.

| Username | Role |
|---|---|
| admin | Administrator |
| lawyer | Lawyer (Civil Litigator) |
| prosecutor | Prosecutor |
| judge | Judge |
| investigator | Investigator |
| clerk | Clerk of Court |

## Project structure

```
├── assets/style.css      Stylesheet
├── inc/
│   ├── bootstrap.php     Database, sessions, security helpers, page layout
│   ├── config.php        Database settings (not committed)
│   └── roles.php         Role and lawyer-type lists
├── storage/              Uploaded files (blocked from direct access)
├── index.php             Login
├── register.php          Lawyer registration
├── dashboard.php         Role-based dashboard
├── cases.php             Case list, search and filters
├── case_edit.php         Create and edit cases
├── case_view.php         Full case profile
├── hearings.php          Hearing calendar
├── users.php             User management (admin)
├── audit.php             Audit log and login history (admin)
├── download.php          Protected file downloads
├── schema.sql            Database tables
└── install.php           One-time installer
```

## Known limitations

- Notifications appear on the dashboard only; there is no email or SMS alert
- Printing uses the browser print stylesheet, not generated PDF reports
- No internal messaging between users
- Case archiving is a status only, with no extra archive rules
- No automatic backups; use `mysqldump` and copy the `storage/` folder
- Bar roll numbers are not verified automatically; an administrator must check them

## Security note

Use HTTPS in production. On Nginx, block direct access to `storage/` (the `.htaccess` only works on Apache).
The system is designed with the Philippine Data Privacy Act of 2012 in mind, but it has not been
audited or certified for compliance.

## Author

James Emmanuel C. Ebonia
BS Information Systems, Makati Science Technological Institute of the Philippines