# NSBM StudyCircle

A student community platform for NSBM Green University, developed for
Project Topic 4: Student Community for Notes and Study Sharing.

## Technology

- HTML, CSS, and JavaScript
- PHP with PDO
- MySQL
- Green-and-white responsive interface

Local development environment: Windows, WampServer, PHP 8.3, and MySQL 8.4.

## Features

### Students

- Register, log in, and log out
- Upload PDF study materials for admin approval
- Browse, search, and filter approved materials
- Download approved resources
- Edit and delete personal uploads
- Rate resources and post comments
- Ask academic questions and post answers
- Create, join, and leave study groups
- Schedule group study sessions

### Administrators

- Approve or reject study materials
- Manage subjects and resource categories
- Search student accounts and block or reactivate access
- Moderate forum questions, answers, and resource comments
- View resource usage and community engagement reports
- Print reports or save them as PDF through the browser

## Local Installation

### 1. Copy the project

Download or clone this repository.

Place the application files in:

```text
C:\wamp64\www\nsbm_studycircle
```

The `index.php` file should be directly inside this folder.

Start WampServer.

### 2. Create the database

Open phpMyAdmin and create an empty database named:

```text
nsbm_studycircle
```

Use the collation:

```text
utf8mb4_unicode_ci
```

Select the new database and import:

```text
database/schema.sql
```

IMPORTANT: The schema contains DROP TABLE statements.
Import it only into a new, empty database. Do not import it over an
existing installation containing data.

### 3. Configure the database connection

Copy:

```text
config/db.example.php
```

to:

```text
config/db.php
```

Set the database host, port, name, username, and password for your environment.

Do not commit your actual configuration file to GitHub.

### 4. Configure private PDF storage

Create a writable folder outside the public website directory, for example:

```text
C:\wamp64\studycircle_private
```

Copy:

```text
config/storage.example.php
```

to:

```text
config/storage.php
```

Set the absolute storage path in this file.

Uploads require PHP's Fileinfo extension. The application also uses
PDO MySQL and mbstring.

### 5. Open the application

For the project's local Apache port of 8080, open:

```text
http://localhost:8080/nsbm_studycircle/
```

Use your own Apache port if different.

### 6. Create accounts

Register a student account through the application.

To create the first administrator:

1. Register a separate account.
2. Open the `users` table in phpMyAdmin.
3. Identify that account by its email address.
4. Change only its `role` from `student` to `admin`.
5. Log in with that account.

There are no default administrator credentials.

### 7. Add initial content

Sign in as admin and create at least one subject and one resource category.

Sign in as a student to upload a PDF, then use the admin account to approve it.

## Application Behaviour

- PDF uploads are limited to 1 MiB.
- PDFs are stored outside the public website directory.
- Students can download only approved materials.
- Editing material details returns the submission to Pending.
- Study-session times use Asia/Colombo.
- Download reports count recorded student requests, not confirmed completed downloads.
- Admin review downloads are excluded from download statistics.

## Main Pages

| File | Purpose |
|---|---|
| index.php | Homepage |
| dashboard.php | Student dashboard |
| admin_dashboard.php | Admin dashboard |
| materials.php | Resource browser |
| my_uploads.php | Personal upload management |
| forum.php | Academic discussions |
| groups.php | Study groups |
| admin_reports.php | Usage and engagement reports |

## Backup

A complete backup requires:

1. Application source and private configuration
2. A database export containing structure and data
3. The private PDF storage folder

The public repository contains the database structure, not account records
or uploaded PDFs.

## Deployment Status

Local implementation is complete based on development testing.
Production configuration and live deployment are still in progress.

## Coursework Deliverables

The final submission will include:

- This source-code repository
- A working hosted application
- A project report
- A demonstration video
- Team member names, roles, and contributions
