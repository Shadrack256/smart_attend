# Smart Attend — Quick Start for Administrators

Manage users, courses, reports, and backups.

---

## Users

**Admin → Users**

- Add, edit, or delete users.
- Assign roles: student, lecturer, or admin.
- Upload profile photos for any user.
- Search by name, email, or registration number.
- Filter by role.

---

## Courses

**Admin → Courses**

- Create a course with code and name.
- Assign a lecturer to each course.
- Delete a course, which also removes its sessions and attendance records.

---

## Enrollments

**Admin → Enrollments**

- Enroll students one by one, or
- Upload a CSV of registration numbers to enroll a whole class at once.

**CSV format:** first row is a header with any name. One registration number per line.

Preview every match before committing. Invalid or duplicate rows are flagged and skipped.

---

## Reports

**Admin → Reports**

Pick a course, then optionally:

- Filter by attendance percentage. All students, below 75%, below 50%, or at-risk below 65%.
- Restrict by date range with the From and To fields.

Export options:

- **CSV** — raw data for spreadsheets.
- **PDF (summary)** — official letterhead document with totals.
- **Register** — traditional matrix with tick, L, and cross per session.

Every export respects the current filters.

**Notify at-risk students** by clicking the amber button after applying a threshold filter. Each listed student receives a personalised email.

---

## Settings

**Admin → Settings** gives you control over:

**Branding**

- System name and tagline.
- Logo. JPG, PNG, WebP, or SVG.
- Favicon. ICO, PNG, or SVG.
- Primary colour. Recolours every button, link, and focus ring.
- Login page background image and overlay darkness.

**Footer**

- Custom footer text shown on every page.

**Institution details**

- Name, address, phone, email, and website.
- Report title and signer label. These appear on exported PDFs.

**Attendance rules**

- Toggle geofencing on or off.
- Default geofence radius in metres.

**Maintenance**

- Run backup now.
- Restore from backup.

**Documentation**

- Rebuild PDFs from markdown sources.

---

## Backing Up

**From the panel:**

1. Go to Settings, then Maintenance.
2. Click **Run backup now**.
3. Wait a few seconds and refresh the page. The new backup appears in the restore dropdown with its size and timestamp.

**From the desktop:**

Double-click **backup_db.bat** in the project folder.

Backups are saved to `backups/db/` and `backups/uploads/`. The system keeps the last 30 automatically.

---

## Restoring from a Backup

1. Go to Settings, then Maintenance.
2. Choose a backup from the dropdown.
3. Type **RESTORE** in the confirmation field.
4. Click **Restore this backup**.
5. You are logged out automatically.
6. Log in again. The database is now at the state of that backup.

> **Warning:** Restoring replaces the current database entirely. Anything created after the backup is lost.

---

## Daily Routine

- Check the dashboard for unusual activity.
- Respond to any reports from lecturers.

---

## Weekly Routine

- Run a backup from the Settings page.
- Copy the backup folder to cloud storage such as OneDrive or Google Drive.

---

## Monthly Routine

- Export registers for every course.
- Review attendance trends across the institution.
- Update branding if necessary.

---

## Need Help?

See the full User Manual for detailed instructions.