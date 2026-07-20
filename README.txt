MIST ATTENDANCE — RUNS LOCALLY (XAMPP) AND LIVE (InfinityFree)
==================================================================
This is one codebase that works in both places without any manual
editing. db.php and webauthn_config.php automatically detect
whether they're running on your local XAMPP server or on the live
InfinityFree domain, and use the right settings for each.

============================================================
⚠ IMPORTANT — MISSING BACKGROUND IMAGES
============================================================
This package contains all PHP/JS/CSS code and the corrected logo.png,
but does NOT include these background/decoration images already
sitting on your LIVE server (I was never given copies of them
directly in our conversation):

    bg1.jpg
    image2.jpg
    image3.jpg
    image9.jpg
    picture1.jpg

Before running this locally, copy those 5 files from your live
InfinityFree htdocs (download via FileZilla) into this same folder.
Without them, the homepage's rotating background will show broken
image icons, but everything else will work fine.

============================================================
RUNNING LOCALLY (XAMPP)
============================================================
1. Copy this whole "MIST_Attendance" folder into:
     C:\xampp\htdocs\
   (rename the folder to whatever you like, e.g. "attendance")

2. Add the 5 missing background images (see warning above) into
   the same folder.

3. Start Apache and MySQL from the XAMPP Control Panel.

4. Open http://localhost/phpmyadmin, create a new database named
   exactly:  mist_attendance

5. Select it, open the SQL tab, paste the contents of
   sql/database_schema.sql, click Go. Then repeat with
   sql/webauthn_credentials.sql.

6. Visit http://localhost/attendance/ (or whatever you named the
   folder) in your browser. db.php will automatically detect
   "localhost" and connect using root / no password — no editing
   needed.

7. Fingerprint/Face ID will also work locally — WebAuthn treats
   "localhost" as a secure context even without HTTPS, and
   webauthn_config.php automatically switches to matching settings.
   (If your XAMPP uses a non-standard port, e.g. localhost:8080,
   open webauthn_config.php and change 'http://localhost' to
   'http://localhost:8080' in the $isLocal branch.)

============================================================
RUNNING LIVE (InfinityFree) — mist-attendance.rf.gd
============================================================
No changes needed — this is the same code already live on your
site. If you're re-uploading after making local edits, just drag
the changed files into /htdocs via FileZilla as usual. db.php and
webauthn_config.php will automatically detect the live domain and
use your InfinityFree credentials, exactly as before.

============================================================
HOW THE AUTO-DETECTION WORKS
============================================================
Both db.php and webauthn_config.php check $_SERVER['SERVER_NAME'].
If it's "localhost" or "127.0.0.1", local settings are used.
Otherwise, the live InfinityFree settings are used. This means:

  - You can keep ONE set of files and move freely between testing
    locally and uploading live, with zero manual credential swaps.
  - If you ever change your live domain (e.g. move to a custom
    domain), update ONLY the "else" branch in both files — nothing
    else in the codebase needs to change.

============================================================
FILE LIST
============================================================
All 24 PHP files, script.js, style.css, sw.js, manifest.json, and
the corrected logo.png (icon + MIST wordmark, evenly centered) —
this is the complete, current version of every file we've built
together, including the fingerprint/Face ID (WebAuthn) system and
the Edit Student feature.

sql/database_schema.sql       — run first on a fresh local database
sql/webauthn_credentials.sql  — run second, adds fingerprint support
