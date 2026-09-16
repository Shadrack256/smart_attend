# Smart Attend — Live Demo Script

**Duration:** approximately three minutes.

**Setup before starting:**

- Laptop open with the login page loaded.
- Phone in hand, already logged in as a test student.
- ngrok tunnel running with a stable URL.
- Backup plan ready. See contingency table at the end.

---

## 0:00 — Opening

> "Thank you for your time. I'd like to show you Smart Attend, a QR-and-GPS verified attendance system for modern classrooms. Everything you'll see works live. Let me walk you through three roles: a student, a lecturer, and an administrator."

---

## 0:15 — Show the landing page

Open the public URL on the laptop.

> "This is the public landing page, branded for the institution. Live statistics on the right show real numbers from the database. Anyone can register, sign in, or reach the documentation from here."

Briefly point to the features strip, the three role cards, and the demo request form. Keep this to fifteen seconds.

---

## 0:30 — Sign in as a lecturer

Click **Sign in** and log in as the lecturer.

> "Signing in as a lecturer. The system knows my role and takes me to my dashboard."

Landing on the lecturer dashboard:

> "Everything here is scoped to my courses. I see only what I teach. No other lecturers' data. No admin functions."

Point to the course list, the recent sessions table with present, late, and absent counts, and the **Start session** button.

---

## 0:50 — Start a session

Click **Start session**. Select a course. Set radius to 300 metres. Allow location. Click **Start session**.

> "One click starts a session. The system records my location, which becomes the geofence for the class. The QR code appears immediately."

Landing on the QR display:

> "The QR refreshes every twenty seconds. That means a screenshot shared on WhatsApp is already useless by the time anyone tries to use it. It is the first line of defence against proxy attendance."

Point to the countdown, the QR, and the **View live attendance** button.

---

## 1:10 — Hand over to the student

Have the student open their phone. They should already be logged in and on the Scan page.

> "Now, my student here is going to mark their attendance. They open Smart Attend on their phone and tap **Scan QR**."

The student points the phone at the laptop's QR. A green success message appears.

> "The system just verified three things. That the student is enrolled in this course. That the QR code is currently valid. And that the phone is physically inside the classroom radius. Attendance is captured."

---

## 1:35 — Verify on the lecturer side

On the laptop, click **View live attendance** or note that the live widget updates automatically.

> "Within a few seconds, the student appears on my live roster. Present, timestamped, with their measured distance from the classroom."

Point to the student's name and registration number, the **Present** badge, and the distance figure.

> "The lecturer sees this in real time. No paper, no name-calling, no disputes at the end of the term."

---

## 1:55 — End the session

Click **End session** and confirm.

> "Ending a session automatically marks every enrolled student who did not scan as absent. That closes the loop. Attendance records are complete."

Optionally, click through to the session in the list to show the present and absent breakdown.

---

## 2:10 — Show the student's own record

Have the student refresh their dashboard.

> "The student sees their updated percentage immediately. And if they want an official record, they can download a PDF scoped to just them."

Point to the **Download my report** link on the student's dashboard.

---

## 2:25 — Sign in as admin

Log out, then log in as admin.

> "Now the administrator view. This is where a department head or registrar would work."

Landing on the admin dashboard:

> "Live system stats. Recent activity across all courses."

Navigate to **Reports**:

> "Reports can filter by attendance threshold, for example everyone below seventy-five percent, and by date range."

Click **Export Register**.

> "This produces a traditional academic register. One row per student, one column per session, with ticks and crosses. It carries the institution's letterhead and signatures. Ready for the dean's office or an accreditation review."

Open the PDF briefly to show it.

---

## 2:50 — Close

> "Everything you've just seen runs on a laptop using PHP and MySQL. No specialised hardware. No third-party subscriptions. It can be deployed to a real server in an hour. Thank you."

---

## Contingency Plan

Live demos fail. Do not panic. Here is how to handle common failures.

| If this fails | What to say | What to do |
|---------------|-------------|------------|
| QR does not render | "Let me switch to the backup QR I prepared earlier." | Open a pre-generated QR PNG from a tab. Students can still scan it. |
| Student's phone cannot reach the URL | "The demo phone is on a different network. Let me show you the recorded video." | Have a short screen recording ready. |
| Camera will not open on the phone | "Permissions are being fussy on this phone. Let me use my own." | Have a second phone ready, already tested. |
| Geofence rejects the scan | "GPS on this phone is slightly off. Let me widen the radius." | Start a new session with a 1000-metre radius. |
| Email confirmation does not arrive | "The email system is set to a local test server for the demo." | Open MailHog and show the captured email. |
| Page loads slowly | "The tunnel is a bit congested. Bear with me." | Wait. Do not reload, which resets the QR. |
| Total failure | "Let me walk you through the architecture instead." | Have a backup PDF with screenshots of every screen. |

**Golden rule:** Never debug live. If something does not work after one try, switch to the backup.

---

## Pre-Demo Checklist

One hour before:

- XAMPP running. Apache and MySQL green.
- ngrok tunnel started. URL copied and tested.
- Laptop browser has the login page loaded.
- Phone charged, on mobile data, logged in as a test student.
- Student account is enrolled in a course.
- Backup QR PNG in a separate tab.
- Backup screen recording ready.
- Second phone charged and tested.
- Backup PDF of screenshots on the desktop.
- MailHog running if demoing email.
- This script printed or on a second screen.