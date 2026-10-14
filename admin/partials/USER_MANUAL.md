# Smart Attend — User Manual

**Version 1.0**

A QR-and-GPS verified attendance system for modern classrooms.

---

## Table of Contents

1. Introduction
2. Getting Started
3. For Students
4. For Lecturers
5. For Administrators
6. Troubleshooting
7. Frequently Asked Questions
8. Support

---

## 1. Introduction

### What is Smart Attend?

Smart Attend replaces paper sign-in sheets with a secure, digital attendance system. Lecturers display a rotating QR code on a screen. Students scan it with their phones. Attendance is captured instantly, verified by location, time, and identity.

### Why it matters

- No more paper sheets that get lost, forged, or filled in by a friend.
- No more name-calling that wastes five to ten minutes of every class.
- No more ambiguity. Every attendance record is timestamped and location-verified.
- Reliable records for exams, funding, and accreditation.

### How it works

1. The lecturer starts a session. The system records the time and location, and generates a QR code.
2. The QR code refreshes every twenty seconds. A screenshot becomes useless by the time anyone shares it.
3. Students open the app on their phone, log in, and scan the QR.
4. The server verifies the student is enrolled and physically inside the classroom radius.
5. Attendance is recorded. Both the lecturer and the student see the update instantly.

---

## 2. Getting Started

### What you need

- A modern browser — Chrome, Edge, Firefox, or Safari, updated within the last year.
- An internet connection — Wi-Fi or mobile data.
- For students, a phone with a camera and GPS, to scan QR codes.
- Your registration number or staff ID, provided by your institution.

### Logging in

1. Open the Smart Attend URL provided by your institution.
2. Enter your email address and password.
3. Click **Sign in**.
4. You land on your dashboard, tailored to your role.

### Forgot your password?

1. On the login page, click **Forgot password?**.
2. Enter the email address tied to your account.
3. Check your inbox. You will receive a link within a minute.
4. Click the link and set a new password.
5. You are logged in automatically.

The reset link expires in sixty minutes and can only be used once.

---

## 3. For Students

### Your dashboard

When you log in, you see:

- Your profile photo. Click it to change your picture.
- Your enrolled courses, each with an attendance percentage bar.
- A **Scan QR** button to mark your attendance during a live session.
- A **Sign out** button.

The colors mean:

- Green — seventy-five percent or higher. Safe.
- Amber — fifty to seventy-four percent. Warning.
- Red — below fifty percent. At risk.

### Marking attendance

When your lecturer starts a session:

1. Tap **Scan QR** on your dashboard.
2. Allow camera and location access when your browser asks.
3. Point your phone at the QR code on the lecturer's screen.
4. Hold steady for one to two seconds.
5. You will see a green message: **Attendance marked as present**.

The message also shows your distance from the classroom centre. Typically five to one hundred metres if you are in the room.

### Why your scan might be rejected

| Message | What it means |
|---------|--------------|
| You are X metres from the class | You are outside the classroom radius. Move closer or ask your lecturer to widen the radius. |
| You are not enrolled in this course | You are not registered for this course. Contact your administrator. |
| Attendance already marked | You already scanned for this session. No action needed. |
| QR expired. Scan again. | The QR code rotated before the server could verify. Just scan again. |
| Session not found | The session ended. Ask your lecturer to start a new one. |
| Location required | Your browser blocked location access. Enable it in browser settings and reload. |

### Checking your attendance

- On your dashboard, each course card shows your current percentage.
- Click **Download my report** on any course card to get a PDF record of every session.

### Uploading a profile photo

1. Click your photo or name at the top of the dashboard.
2. On the profile page, click the upload area or drag a photo into it.
3. Choose a JPG, PNG, or WebP under two megabytes.
4. Click **Save photo**.

Your photo then appears in rosters, live attendance views, and reports.

### Your rights

- You can view all your attendance records at any time.
- You can download your own PDF record without admin involvement.
- You can update your own profile photo.
- Your password is encrypted. Nobody, not even administrators, can read it.

---

## 4. For Lecturers

### Your dashboard

When you log in, you see:

- Your courses, each with enrolled student count.
- A live session widget, which appears at the top when a session is active.
- Recent sessions, with present, late, and absent counts.
- A **Start session** button.
- A **Sign out** button.

### Starting a session

1. Click **Start session**.
2. Select the course from the dropdown.
3. Set the geofence radius. The allowed distance from the classroom centre. Recommended: one hundred metres. Use three hundred metres if GPS accuracy is poor indoors.
4. Allow location access when your browser asks.
5. Click **Start session**.

You land on the live QR display.

Tip: Use a phone to start sessions, not a laptop. Phone GPS is accurate to about five metres. Laptop GPS can be off by five hundred metres or more.

### The live QR display

- The QR code refreshes every twenty seconds. Earlier screenshots will not work.
- A countdown shows when the next refresh occurs.
- **End session** closes the session and marks non-scanners as absent.
- **View live attendance** shows who has scanned in real time.

### Viewing live attendance

Click **View live attendance** on the QR page, or the live widget on your dashboard. You will see:

- Present — students who scanned within ten minutes of session start.
- Late — students who scanned ten or more minutes after start.
- Waiting — enrolled students who have not yet scanned.
- Absent — set automatically when the session ends.

The page refreshes every five seconds. No manual reload needed.

### Ending a session

1. Click **End session** on the QR page.
2. Confirm the dialog.
3. Every enrolled student who did not scan is marked absent automatically.
4. The session becomes closed. No more scans accepted.

Sessions also close automatically after three hours if forgotten.

### Downloading a register PDF

1. On your dashboard, find any of your courses.
2. Click **Register**.
3. A PDF downloads with a matrix. One row per student, one column per session. A tick for present, L for late, and a cross for absent.

The PDF carries the institution letterhead, signatures, and stamp area. Ready for the dean's office.

### Managing your students

Click **Manage students** on any of your courses to:

- Enroll new students. Tick the checkboxes and click Enroll.
- Remove students. Click the Remove button next to any name.
- Search the enrolled roster. Type in the search box.

Important: you cannot remove a student who already has attendance records for the course. This protects your historical data.

### Session lifecycle

Start session, then QR live, then students scan, then end session, then absent marking, then report ready.

---

## 5. For Administrators

### Your dashboard

The admin dashboard gives a system-wide overview:

- Students — total registered.
- Lecturers — total registered.
- Courses — total created.
- Sessions — total held across the system.
- Records — total attendance rows.

Below the stats is a recent attendance activity table showing the last ten records.

### Managing users

Go to **Admin** and then **Users**.

- Search by name, email, or registration number.
- Filter by role. Student, lecturer, or admin.
- Create new users via the **New user** button.
- Edit any user via the pencil icon.
- Delete any user except yourself.

For each user, you can set:

- Full name, email, role.
- Registration number for students, or staff ID for lecturers.
- Password.
- Profile photo.

### Managing courses

Go to **Admin** and then **Courses**.

- Create a course with code, name, and lecturer.
- Assign or reassign a lecturer.
- Delete a course, which removes its sessions and attendance history.

### Enrolling students

Go to **Admin** and then **Enrollments**.

Pick a course and manage its roster:

- Add students. Tick the checkboxes and click Enroll.
- Remove students. Click Remove on any enrolled student.
- Bulk enroll via CSV. Click **Upload** to enroll a whole class at once.

CSV format: the first row is a header with any name. One registration number per line. Preview before committing. Invalid or duplicate rows are flagged, not enrolled.

### Reports

Go to **Admin** and then **Reports**.

Pick a course, then optionally:

- Filter by attendance percentage. All students, below seventy-five percent, below fifty percent, or at-risk below sixty-five percent.
- Restrict by date, using the From and To fields.
- Export CSV. Raw data for spreadsheets.
- Export PDF (summary). Official letterhead document.
- Export Register. Matrix format with ticks, L, and crosses per session.
- Notify. Send email warnings to at-risk students.

All three exports respect the current filters.

### Notifying at-risk students

1. Filter to below seventy-five percent or any threshold.
2. Click **Notify**.
3. Confirm the dialog.
4. Every listed student receives a personalised email within seconds.

The email includes the student's name, their current percentage, and a request to contact their lecturer.

### System settings

Go to **Admin** and then **Settings**. You can customise:

Branding:

- System name and tagline.
- Logo. JPG, PNG, WebP, or SVG.
- Favicon. ICO, PNG, or SVG.
- Primary color. Recolors every button, link, and focus ring.
- Login page background image and overlay darkness.

Footer:

- Custom footer text shown on every page.

Institution details:

- Institution name, address, phone, email, and website.
- Report title and signer label. These appear on exported PDFs.

Attendance rules:

- Toggle geofencing on or off.
- Default geofence radius in metres.

Maintenance:

- Run backup now. Dumps the entire database and uploads folder.
- Restore from backup. Pick a backup from the list and type RESTORE to confirm.

Documentation:

- Rebuild PDFs from markdown sources.
- Download current guides.

### Backing up

From the UI:

1. Go to Settings, then Maintenance, then **Run backup now**.
2. Wait a few seconds and refresh the page.
3. The new backup appears in the restore dropdown with its size and timestamp.

From the desktop, double-click **backup_db.bat** in the project folder.

Backups are saved to **backups/db/** and **backups/uploads/**. The system keeps the last thirty backups automatically.

### Restoring

1. Go to Settings, then Maintenance. Choose a backup.
2. Type RESTORE in all caps into the confirmation field.
3. The Restore button becomes active.
4. Click **Restore this backup**.
5. You are logged out automatically.
6. Log in again. The database is now at the state of that backup.

Warning: restoring replaces the current database. Anything created after the backup is lost.

---

## 6. Troubleshooting

### Login problems

| Problem | Solution |
|---------|----------|
| Invalid email or password | Check your spelling. The email is case-insensitive. The password is case-sensitive. |
| Cannot access login page | Confirm the URL is correct and your internet works. |
| Password reset email never arrives | Check spam. Try again after one minute. Contact your admin if it still fails. |
| Reset link says expired | The link lasts sixty minutes. Request a new one. |

### Scanning problems for students

| Problem | Solution |
|---------|----------|
| Camera does not open | Check that the URL starts with https and that you have granted camera permission. |
| Location is not detected | Enable location services in your phone settings and in the browser. |
| You are X metres from the class | Move closer to the lecturer's device. |
| QR expired | Rescan. The QR rotates every twenty seconds. |

### Session problems for lecturers

| Problem | Solution |
|---------|----------|
| QR code does not appear | Refresh the page. If the problem continues, contact your admin. |
| Students are being rejected as too far | Widen the geofence radius when starting the session, or start the session from a phone. |
| End session button missing | It only appears on the live QR page while the session is active. |
| Cannot remove a student | They have attendance records for the course. This is intentional to preserve history. |

### Admin problems

| Problem | Solution |
|---------|----------|
| Bulk CSV upload says zero rows parsed | The file has no data rows, or the wrong delimiter. Use the template. |
| Backup button does nothing | Check that MySQL is running and that the backup script exists. |
| Restore fails | Check that the SQL file is not empty and MySQL is running. |
| Reports page shows no data | Verify the course has sessions and enrolled students. |

---

## 7. Frequently Asked Questions

**Do I need to install an app?**

No. Smart Attend runs entirely in your browser.

**What if my phone has no GPS?**

Every modern smartphone has GPS. If it is disabled, enable location services for your browser.

**Can I scan for a friend?**

No. The system verifies that the scanning student is enrolled and physically inside the class radius. Scanning from outside the room is rejected.

**What happens if I forget to scan?**

Your lecturer ends the session, and you are marked absent automatically.

**Can I see my attendance history?**

Yes. Download your PDF report from any course card on your dashboard.

**Who can see my attendance?**

You, your lecturer for their courses, and administrators. No other students can see your record.

**Can I change my photo?**

Yes. Click your avatar to open your profile page.

**What if I change my phone?**

No problem. Smart Attend has no device binding. Log in on any phone.

**Is my data safe?**

Passwords are hashed with bcrypt. All database queries use prepared statements. File uploads are sandboxed. The administrator can create backups at any time.

**Can I use this offline?**

No. Smart Attend requires an internet connection because attendance is verified server-side.

---

## 8. Support

If you encounter a problem not covered here:

1. Note the exact error message. A screenshot helps.
2. Note what you clicked just before the problem.
3. Check the FAQ above.
4. Contact your administrator with those details.

For technical issues such as a page failing to load or a database error, contact your system administrator.

---

**Smart Attend · Version 1.0**

Built with PHP and MySQL.