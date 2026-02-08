# Church Community Directory

## WordPress Plugin — Product Requirements Document

| Field | Value |
|-------|-------|
| **Target site** | sttheklachurch.org |
| **Hosting** | Bluehost (WordPress + MySQL) |
| **Deliverable** | Installable WordPress plugin |
| **Nav label** | Community (configurable) |
| **Version** | 2.1 — February 2026 |

---

## 1. Executive Summary

Build a secure, members-only Community Directory inside an otherwise public church website. The plugin will:

- Provide a Community entry point with member login (email + password), Google social login, and a "Not a member / Want to join?" application flow.
- Render a modern, mobile-friendly membership application form and save submissions to the WordPress MySQL database on Bluehost.
- Provide an Applications queue for the Secretary/Approver to review and Approve / Not Approved.
- On approval, automatically send an invite to the applicant to activate their directory profile.
- Support households/family groups with inheritance (shared home address) plus "spin-off" when members become independent.
- Send notification emails to a configurable list of officers whenever a new application is submitted.
- Include a complete forgot password / password reset flow.
- Offer an optional PWA "appify" experience so members can install the directory to their phone home screen (no App Store).

> **KEY CHANGE FROM v1.0**
>
> All data is now stored in the WordPress MySQL database on Bluehost. Airtable has been removed as a dependency. This eliminates external API rate limits, reduces operational complexity, and keeps all data under direct control.
>
> Authentication simplified to: Email + Password (WordPress native) and Google OAuth only. Facebook and Instagram social login have been removed to reduce third-party maintenance burden.

> **CHANGES IN v2.1**
>
> This revision incorporates implementation-level specifics identified during technical review:
> - Frontend architecture and API communication strategy (Section 14)
> - Application form field specification (Section 5.11)
> - Member deactivation/removal lifecycle (Section 5.10)
> - Google OAuth account-linking edge cases (Section 5.2)
> - Rejection and re-application workflow (Section 5.4)
> - Household role taxonomy (Section 5.8)
> - Schema versioning and migration strategy (Section 6.5)
> - Background processing / wp_cron strategy (Section 6.6)
> - Hosting and MySQL version requirements (Section 15)
> - Rate limiting implementation details (Section 10)
> - PWA session management and branding (Section 8)
> - Avatar handling specification (Section 7)
> - Parent/adult management of child profiles (Section 5.8, Section 7)
> - Directory UX enhancements for member experience (Section 16)
> - PWA home screen icon and branding (Section 8)
> - Google Contacts sync — import and export contacts (Section 5.12)
> - Google Contact card creation on member approval (Section 5.4, Section 5.12)
> - Admin toggle to show/hide Community menu (Section 5.1)
> - Expanded member profile fields for church communication needs (Section 5.7, Section 6.2)
> - Comprehensive PII protection: no PII in URLs, encrypted sensitive fields, anti-scraping, child data protection, HTTP security headers, EXIF stripping (Section 10)
> - WordPress hardening security checklist in Diagnostics (Section 10.6)

---

## 2. Guiding Principles

- **Security-first:** directory data never visible without authorization; passwords hashed via WordPress native auth; no sensitive data in external systems.
- **Simple for members:** clear paths (login vs. apply), guided profile setup, mobile-first UI, password reset when needed.
- **Simple for leadership:** secretary has a clean approvals queue; officer notifications are configurable year-to-year.
- **Data ownership:** all application, member, household, and audit data lives in your Bluehost MySQL database alongside WordPress — one backup, one system.
- **WordPress-native:** plugin uses WordPress roles/capabilities, admin menus, `$wpdb` for database access, and page/block patterns so it's maintainable.
- **Minimal external dependencies:** only Google OAuth requires external service configuration; everything else runs on your existing Bluehost + WordPress stack.

---

## 3. Roles and Permissions

### WordPress Roles (Plugin Capabilities)

| Role | Capabilities |
|------|-------------|
| **WP Administrator** | Full plugin setup and configuration (database, security, email templates, Google OAuth, PWA settings). Manages Church Officers Group. |
| **Directory Admin** | Manage members, households, invites, and moderate records. |
| **Church Officer** | Members of the Church Officers Group for the current year. Default view is the directory. Has a **Member Administration** tab to review and approve/reject new member applications. Rotates annually. |
| **Secretary / Approver** | Review applications, mark Approved / Not Approved, resend invites, view relevant logs. |
| **Member** | View directory, edit own profile, manage household details within permissions. |
| **Public** | Can access Community landing and application form only; cannot access directory. |

### Core Permission Rules

- Only Admin/Secretary/**Church Officers** can approve applications.
- Only approved + activated members can access the directory.
- Members can edit their own profile fields (as allowed by admin policy).
- Admin/Secretary can edit any member profile (with audit logging).
- Church Officers can view and approve/reject applications but **cannot** edit other members' profiles, export data, or modify plugin settings.

---

## 4. User Journeys

### A) Member Access (Community Landing)

When a visitor clicks Community, they see three clear options:

1. **Log in with Email + Password** — Email is the username. "Forgot password?" link is prominently available.
2. **Continue with Google** — Google OAuth for members who prefer social login.
3. **Not a member / Looking to join?** — "Apply for Membership" opens the membership application form.

### B) Forgot Password Flow

1. Member clicks "Forgot password?" on the login screen.
2. Enters their email address; plugin validates it exists in the system.
3. WordPress sends a password reset email with a secure, time-limited token.
4. Member clicks the link, sets a new password, and is redirected to log in.
5. The reset token is single-use and expires after a configurable period (default: 24 hours).

> **IMPLEMENTATION NOTE**
>
> The forgot password flow uses WordPress's built-in password reset mechanism (`wp_lostpassword_url`, `retrieve_password` hooks) but with custom-branded email templates and a styled reset page that matches the Community directory theme.

### C) New Member Application → Email Verification → Secretary Review

1. Applicant completes the application form (see Section 5.11 for field specification) and submits.
2. Submission saves into the `wp_cd_applications` table with status `pending_verification`.
3. Plugin sends a **verification email** to the applicant's provided email address with a secure, single-use, time-limited verification link.
4. Applicant clicks the verification link → email is confirmed → application status changes to `new` (verified).
5. The application now appears in the approval queue (WP Admin → Community → Applications). Officers are notified.
6. Secretary/Officer reviews and marks: **Approved** (triggers invite) or **Not Approved** (see Section 5.4 for full rejection workflow).

> **NOTE:** Applications with status `pending_verification` are **not** visible in the officer/secretary approval queue. They appear only in the Admin → Registrations view (see Section 5.3.1). This prevents officers from reviewing applications from unverified email addresses.

### D) Invite → Account Creation → Directory Profile Activation

1. Applicant receives email invite with a secure link.
2. They choose: Email/password (WordPress account creation) or Google social login.
3. System validates they match an approved application/member record.
4. Member is activated and redirected to "Complete Your Profile" wizard.
5. Member gains directory access.

### E) Family/Households Lifecycle

- Members can be grouped into a household.
- Household provides shared home address inheritance.
- Individual members can override their address (e.g., college).
- "Spin off" creates a new household while preserving relationship history.

### F) Member Deactivation (New in v2.1)

1. Admin/Secretary navigates to the member's profile in the admin panel.
2. Selects "Deactivate Member" with a required reason (moved away, requested removal, deceased, other).
3. System confirmation prompt: "This will remove [Name] from the directory. Their data will be archived for [retention period]. Continue?"
4. On confirmation: member status set to `inactive`, directory profile hidden, household membership updated (see Section 5.10).
5. Audit log records the deactivation with actor, reason, and timestamp.

---

## 5. Functional Requirements

### 5.1 Community Menu Item

- On plugin activation: create a top-level navigation entry labeled **Community** by default.
- In plugin settings, admin can configure: menu label, target landing page, and whether visible to public (recommended: yes, but gated).

**Menu Visibility Toggle (New in v2.1):**

- In WP Admin → Community → Settings → General, a prominent **toggle switch** controls whether the "Community" menu item is visible on the public-facing site navigation.
- **Toggle ON (default):** The menu item appears in the site's primary navigation. Visitors can see and click it (login/application form is gated).
- **Toggle OFF:** The menu item is hidden from all site navigation menus. Members can still access the directory by navigating directly to the URL (e.g., `/community/`). Useful during initial setup, maintenance, or if the church wants to soft-launch before making it publicly visible.
- Toggling visibility does **not** deactivate the plugin or affect data — it only controls the nav menu link.
- The toggle state is shown with a clear label: "Community menu is **Visible** / **Hidden** on your site."
- Change is immediate (no cache delay) — uses WordPress `wp_nav_menu` filter to conditionally include/exclude the item.

### 5.2 Authentication

**Supported methods:**

- Email + password (WordPress native authentication)
- Google OAuth 2.0

**Forgot password flow:**

- Prominent "Forgot password?" link on login page.
- Member enters their email address.
- Plugin validates the email exists as an active member.
- Custom-branded password reset email sent using `wp_mail()` with church branding. Contains:
  - A friendly message: "We received a request to reset your password for the St. Thekla Community Directory."
  - A secure, single-use reset link with a hashed token. Expires after configurable period (default: 24 hours).
  - "If you did not request this, you can safely ignore this email."
- Member clicks the link → styled reset password page matching Community theme.
- Member enters and confirms new password. Validation: minimum 8 characters, at least one letter and one number.
- Success confirmation redirects to login with a "Password updated successfully" message.
- If the email is not found: the form shows a **generic** message "If an account exists for this email, you will receive a reset link." (Does not reveal whether the email is registered — prevents account enumeration.)

**"Can't remember your email?" flow (New in v2.1):**

Below the login form and "Forgot password?" link, a secondary link: **"Can't remember which email you used?"**

1. Member enters their **first name, last name, and phone number.**
2. System searches `wp_cd_members` and `wp_cd_directory_profiles` for a matching record (name match + phone match in any phone field).
3. **If a match is found:** The system sends a hint to **all email addresses** on the matching member's profile: "We found a directory account matching your name. Your login email is: j***@gmail.com. Please use this email to log in or reset your password."
   - The email shows a partially masked version of the login email (first letter + asterisks + domain).
   - The hint email does **not** include a login link or reset link — only the email hint.
4. **If no match is found:** "We couldn't find an account matching that information. Please contact the church office at [configured phone/email]."
5. **Rate limit:** 3 lookup attempts per IP per hour. After the limit: "Too many attempts. Please wait and try again, or contact the church office."

**Deactivated member login experience (New in v2.1):**

When a `self_deactivated`, `inactive`, or `suspended` member attempts to log in:

- Instead of a generic "login failed" error, show a specific message:
  - `self_deactivated`: "Your directory profile is currently hidden. You can reactivate it from your profile settings after signing in." (Allow login — they can reactivate themselves.)
  - `inactive`: "Your directory account is currently inactive. If you'd like to rejoin, please contact the church office at [phone/email]."
  - `suspended`: "Your directory account has been temporarily suspended. Please contact the church office at [phone/email]."
  - `deletion_requested`: "Your account deletion request is being processed. Please contact the church office if you'd like to cancel this request."

**Security requirements:**

- Passwords are stored only in WordPress (hashed via `wp_hash_password`); never stored in plaintext or external systems.
- Only applicants approved by Secretary can activate member access.
- Google OAuth must use a verified email that matches the approved record.
- Rate limiting on login attempts (configurable; default 5 attempts per 15 minutes).
- Rate limiting on password reset requests (default 3 per hour per email).

**Google OAuth Account Linking Rules (New in v2.1):**

The following edge cases must be handled explicitly:

| Scenario | Behavior |
|----------|----------|
| Member registered with email/password (e.g., their Gmail address as username), later clicks "Continue with Google" with the **same email** | Accounts are **automatically linked**. The `google_id` field on `wp_cd_members` is populated. Member can now use **either** login method (email/password or Google SSO) interchangeably. A confirmation message is shown: "Your Google account has been linked. You can now sign in with either your password or Google." No data is lost; the existing profile, household, and history remain intact. |
| Member initially used Google SSO, later wants to set a password | Member can set a WordPress password via "Set Password" on their profile page (or "Forgot Password" on the login screen). Once set, both login methods work. |
| Member registered with a **non-Gmail** email/password, later clicks "Continue with Google" with a **different** Gmail address | The Google email does not match the member's registered email. The system prompts: "Your Google account email (gmail@...) does not match your registered email (other@...). Would you like to link this Google account to your existing membership?" If confirmed, `google_id` is stored and the Google email is added as a secondary email on the profile. |
| Member clicks "Continue with Google" but their Google email **does not match** any approved member record and no linking is confirmed | Login is denied. Error message: "No approved membership found for this email address. If you applied with a different email, please log in with that email instead, or contact the church office." |
| Two different people: one approved with email A (email/password), another approved with email B (Google OAuth), but Google account's email is A | The Google login matches to email A's record. Email B's member must use their own Google account or email/password. No cross-linking occurs. |
| Member changes their Google account email after linking | Plugin validates against the stored `google_id` (Google's immutable subject ID), not the email. Login continues to work. |
| Admin/Secretary needs to unlink a Google account from a member | Admin panel provides an "Unlink Google Account" action on the member's profile, which clears the `google_id` field. Audit logged. |

**Account Matching Logic (Login Flow):**

When a user clicks "Continue with Google," the system checks in this order:

1. **Check `google_id`:** Does any `wp_cd_members` record have this Google subject ID stored? → If yes, log them in (existing linked account).
2. **Check email match:** Does the Google account's email match any `wp_cd_members` primary email or WordPress `user_email`? → If yes, auto-link the `google_id` and log them in. Show "Your Google account has been linked" confirmation.
3. **Check secondary emails:** Does the Google email match any email in the member's `emails` JSON array? → If yes, auto-link and log in.
4. **No match found:** Deny login with a helpful error message.

This ensures that a member who signs up with their Gmail as a username/password can seamlessly switch to Google SSO at any time — the system recognizes the matching email and merges the authentication methods onto the same account.

### 5.3 Application Form

- Modern, mobile-friendly wizard UI (see Section 5.11 for complete field specification).
- Sectioned steps with progress indicator.
- Validation: email, phone formatting, required fields, etc.
- Every submission saved to `wp_cd_applications` table with initial status `pending_verification`.

**Post-Submission Email Verification Flow (New in v2.1):**

After the applicant clicks "Submit":

1. **Confirmation screen:** The applicant sees a success page: "Thank you for your application! We've sent a verification email to **[email]**. Please check your inbox and click the verification link to complete your submission. If you don't see it, check your spam folder."
2. **Verification email sent** via `wp_mail()` with church branding. Contains:
   - A friendly message: "Welcome! Please verify your email address to complete your application to the St. Thekla Community Directory."
   - A **verification link** with a secure, hashed, single-use token.
   - Link expiration: configurable (default: **48 hours**).
   - "If you did not submit this application, you can safely ignore this email."
3. **Applicant clicks the link:**
   - Token is validated (not expired, not already used, matches the application).
   - Application status changes from `pending_verification` → `new`.
   - Applicant sees a confirmation page: "Your email has been verified! Your application is now under review. You'll receive an email when a decision has been made."
   - Officer notification emails are sent at this point (not on initial submission).
4. **If the link expires** without being clicked:
   - Application remains in `pending_verification` status.
   - Admin can resend the verification email from the Registrations view (Section 5.3.1).
   - After a configurable period (default: 30 days), unverified applications are auto-archived by a scheduled task (see Section 6.6).
5. **Duplicate email check:** If someone submits a new application with the same email while a `pending_verification` application exists, the old application is replaced with the new submission and a fresh verification email is sent.

**Verification token security:**
- Token is a cryptographically random string (32 bytes, hex-encoded).
- Only a SHA-256 hash of the token is stored in the database (`wp_cd_applications.verification_token_hash`).
- Token is single-use: marked as consumed on successful verification.
- Rate limit: max 5 verification email sends per email address per 24 hours (prevents abuse).

#### 5.3.1 Registrations View (Admin)

**WP Admin → Community → Registrations**

This view shows **all** application submissions, including those still awaiting email verification. It is separate from the Applications approval queue (Section 5.4), which only shows verified applications.

| Column | Description |
|--------|-------------|
| **Name** | Applicant first + last name |
| **Email** | Email address provided |
| **Status** | `pending_verification` / `new` (verified) / `under_review` / `approved` / `not_approved` |
| **Submitted** | Date/time of form submission |
| **Verified** | Date/time email was verified, or "Pending" with time remaining |
| **Actions** | Resend verification email (for `pending_verification` only), View application details |

**Features:**
- **Filter by status:** Tabs or dropdown to filter by `pending_verification`, `new`, or all.
- **Resend verification email:** Admin can click to resend the verification email for any `pending_verification` application. A confirmation toast shows "Verification email resent to [email]." Rate limit applies (max 5 per email per 24 hours).
- **Bulk actions:** Select multiple `pending_verification` applications and resend verification emails in bulk, or archive stale unverified applications.
- **Auto-archive indicator:** Applications approaching the 30-day unverified expiry show a warning badge.

### 5.4 Applications Queue (Secretary Admin + Church Officers)

**WP Admin → Community → Applications** (for Secretary/Admin)
**Directory → Member Administration tab** (for Church Officers — see Section 5.13)

- Default view shows Status = New.
- Secretary or Church Officer can: view full application, mark Approved / Not Approved, add internal notes.
- Optional: request more info (sends email to applicant).
- Approving triggers invite workflow automatically.
- **On approval, a Google Contact card is created** in the church's Google Workspace Contacts (see Section 5.12 for full details).

**Rejection and Re-application Workflow (New in v2.1):**

When a Secretary marks an application as "Not Approved":

1. **Reason required:** Secretary must select a reason category (Incomplete information, Not recognized as community member, Duplicate application, Other) and may add free-text notes.
2. **Notification email:** An optional rejection email is sent to the applicant. Admin can configure whether this is automatic or manual. The email template includes:
   - A respectful, configurable message (default: "Thank you for your interest. At this time, we were unable to approve your application. If you believe this is an error, please contact the church office.")
   - No specific rejection reason is shared with the applicant (internal only).
3. **Re-application policy:**
   - A rejected applicant **may re-apply** after a configurable cooling-off period (default: 30 days).
   - If an applicant submits a new application while within the cooling-off period, the form accepts the submission but flags it in the Secretary's queue as "Re-application (previously not approved on [date])."
   - There is no limit on the number of re-applications, but each is flagged with history.
4. **Application statuses:** Pending Verification → New (verified) → Under Review → Approved | Not Approved. Secretary can also set "On Hold" for applications needing follow-up. Only `new` and later statuses appear in the officer/secretary approval queue.

### 5.5 Invite Workflow

- Invites sent to approved applicants with: unique token (hashed in database), single use, configurable expiration (default 14 days).
- Resend capability for Secretary.
- Invite acceptance links the WordPress user to the member record in the database.
- On approval (before invite is sent), the plugin creates a contact card in Google Workspace Contacts with the member's name, email, phone, and address from the application (see Section 5.12). If the Google Contacts API is not configured, this step is skipped with an admin notice.

### 5.6 Officer Notification Emails

- When a new application is **verified** (applicant clicked the email verification link), send notification to all active Church Officers (from `wp_cd_officers` table) plus any additional configured recipients. Notifications are **not** sent on initial form submission — only after email verification confirms the applicant owns the email address.
- Include summary + link to the Member Administration tab in the directory (for officers) or WP Admin applications queue (for Secretary/Admin).
- Configurable: enable/disable, additional recipient list (beyond auto-included officers), subject/body templates with placeholders, test email button.

### 5.7 Directory Experience (Members-Only)

- **Directory list view:** search by name, pagination, optional filters (household, ministry).
- **Profile view:** avatar, name, household association, contact details with privacy controls, family/household section.
- **Member profile edit:** guided profile completion, privacy toggles per field, social links, avatar management (see Section 7 for avatar specification).

**Member Profile Contact Fields (New in v2.1):**

The directory profile must capture the full range of contact information a church needs to communicate with and connect its members. Each field has an independent privacy toggle (visible to all members / hidden / visible to admin only).

| Field | Type | Notes |
|-------|------|-------|
| **First Name** | Text | Always visible in directory (required) |
| **Last Name** | Text | Always visible in directory (required) |
| **Email Addresses** | Email (repeatable) | Members can add **multiple email addresses**. The first entry is the primary (used for login and official communication). Additional entries are labeled by the member (e.g., "Personal," "Work," "Church"). Each email has its own privacy toggle. Displayed as a list on the profile with labels. Minimum 1 required, no hard maximum (UI supports up to 5 with an "+ Add email" button). |
| **Phone Numbers** | Tel (repeatable) | Members can add **multiple phone numbers**. Each entry has a label selected by the member: Mobile, Home, Work, or custom text. The first entry is the primary (used for one-tap call/text — Section 16.2). Each phone has its own privacy toggle. Displayed as a list on the profile with labels and icons (phone/text). Minimum 1 required, no hard maximum (UI supports up to 5 with an "+ Add phone" button). |
| **Home Address** | Address | Inherited from household if applicable; supports override |
| **Mailing Address** | Address | Optional, if different from home (e.g., PO Box) |
| **Date of Birth** | Date | Privacy-toggleable; used for birthday widget (Section 16.4) |
| **Name Day / Patron Saint** | Text / Date | Optional; relevant for Orthodox parishes. Admin can provide a saint calendar lookup. |
| **Member Since** | Date | Auto-populated from approval date; displayed on profile as "Member since [Month Year]" |
| **Baptism / Chrismation Date** | Date | Optional; from application or manually entered |
| **Wedding Anniversary** | Date | Optional; privacy-toggleable |
| **Ministry Involvement** | Multi-select tags | Which ministries/groups the member belongs to (e.g., Choir, Sunday School, Parish Council). Managed from admin-configured list. Displayed as badges on profile card. |
| **Occupation / Profession** | Text | Optional; helps members connect professionally |
| **Employer / Business** | Text | Optional |
| **Bio / About Me** | Textarea | Short personal bio (max 500 chars) |
| **Facebook** | URL | Validated as facebook.com link; displayed as icon |
| **Instagram** | URL | Validated as instagram.com link; displayed as icon |
| **Twitter / X** | URL | Validated as x.com or twitter.com link; displayed as icon |
| **LinkedIn** | URL | Validated as linkedin.com link; displayed as icon |
| **Personal Website** | URL | Any valid URL |
| **Emergency Contact Name** | Text | Visible to admin/secretary only (not in directory) |
| **Emergency Contact Phone** | Tel | Visible to admin/secretary only (not in directory) |
| **Preferred Contact Method** | Select | Phone / Email / Text — displayed on profile so others know how best to reach them |
| **Preferred Language** | Select | English / Arabic / Greek / Spanish / Other (configurable list). Helps in multilingual parishes. |

**Privacy defaults (admin-configurable):**
- Phone, email, address: visible to members by default (member can hide)
- Social media, occupation: hidden by default (member can opt in)
- Emergency contact: admin/secretary only (not toggleable by member)
- Member Since and Ministry Involvement: always visible (non-toggleable)

### 5.8 Households and Relationships

Members are individual records; household is a grouping entity.

**Household Role Taxonomy (New in v2.1):**

The `role` field in `wp_cd_household_members` uses the following defined values:

| Role | Description | Permissions |
|------|-------------|-------------|
| `head` | Head of household (one per household) | Can edit household name, primary address, and invite/remove members. Receives household-level notifications. |
| `spouse` | Spouse/partner | Can edit household primary address. Can view all household member profiles. |
| `adult_child` | Adult child (18+) | Can view household members. Can override their own address ("spin-off" eligible). |
| `child` | Minor child (under 18) | Profile managed by `head` or `spouse`. Not directly visible in directory search (shown only within household view). Privacy protections apply. |
| `other` | Other household member (e.g., parent, relative) | Can view household members. Can override their own address. |

**Rules:**
- Every household must have exactly one `head`. If the head is removed or deactivated, the Secretary is prompted to assign a new head.
- Both `head` and `spouse` have **full control** over the household profile: can edit household name, primary address, add/remove members, and manage all child profiles.
- A `child` member's profile fields are managed by the `head` or `spouse` until the child's record is updated to `adult_child` or spun off.
- Role changes are audit-logged.

**Member-Initiated Household Changes (New in v2.1):**

Members can initiate household changes directly from the directory without requiring admin intervention:

| Action | Who Can Initiate | Approval Required? |
|--------|-----------------|-------------------|
| **Add a child** (new baby, etc.) | Head or Spouse | No — immediate |
| **Add existing member as spouse** | Head | Yes — the other member must confirm ("Accept household invitation?"), then admin-configurable: instant or admin approval |
| **Request spin-off** | Adult Child (for themselves) | Admin-configurable: instant or admin approval |
| **Leave household** | Any member except `child` | No — immediate. If the member is `head`, they must transfer head role first |
| **Transfer head of household** | Current Head | The new head (spouse or adult_child) must confirm. Audit-logged. |
| **Request household merge** (e.g., after marriage) | Head of either household | Both heads must confirm, then admin approval required |

When admin approval is required, the request appears in **WP Admin → Community → Household Requests** with one-click approve/deny.

**Parent/Adult Management of Child Profiles (New in v2.1):**

Parents (`head` and `spouse` roles) can add and fully manage child contacts within their household. This allows families to maintain complete household listings in the directory.

*Adding a child:*
1. From the household management screen, parent clicks "Add Family Member."
2. Selects role: "Child" (under 18) or "Adult Child" (18+).
3. Fills in the child's profile information:
   - First name, last name (required)
   - Date of birth (optional, used to determine age-based role transitions)
   - Phone number (optional — typically for teens/adult children)
   - Email address (optional — required only if the child will have their own login as adult_child)
   - Bio / interests (optional)
4. **Profile photo upload:** Parent can upload a photo for the child. Photo follows the same smart image processing pipeline as adult avatars (see Section 7):
   - Any size up to 10 MB, JPEG/PNG/WebP/HEIC accepted
   - Square crop tool is presented on upload; system handles any aspect ratio gracefully
   - Thumbnail stored as 300x300px, full-res (max 2048px) stored for lightbox viewing
   - Stored in `wp-content/uploads/community-directory/avatars/` (path in `wp_cd_directory_profiles.avatar_url`)
   - EXIF data (including GPS) stripped automatically
   - If no photo is uploaded, the initials fallback is used
5. Parent sets privacy for the child's listing:
   - Default: child visible only within the household view (not in main directory search)
   - Optional: make child visible in directory (e.g., for teens active in youth ministry)
6. Submission creates a `wp_cd_members` record (status: `active`, no `wp_user_id` for minor children) and a `wp_cd_directory_profiles` record.

*Editing a child's profile:*
- Parents can update any field on their child's profile, including replacing the photo.
- Changes are audit-logged with the parent as the actor.

*Children without email addresses:*
- Minor children typically do not have email addresses. The email field is **optional** for `child` role members.
- Children without email have no login — their profile exists in the directory only within the household view, fully managed by parents.
- When the parent later adds an email for the child (e.g., teen gets their first email), the system offers to send an invite so the child can create their own login.

*Transitioning a child to independent member (growing up):*
- When a child turns 18 (or manually by admin/parent), the parent or Secretary can:
  1. Update role from `child` to `adult_child`.
  2. If the child has an email, send an invite so they can create their own login credentials.
  3. If the child does **not** have an email, the parent must add one first before the invite can be sent.
  4. Once the child has their own login, they manage their own profile (parent edit access is removed).
  5. The adult child can later be "spun off" into their own household (via self-service — see Member-Initiated Household Changes above).
- **Age-based prompt:** If a `child` member's date of birth is set and they turn 18, the system prompts the parent: "Your child [Name] has turned 18. Would you like to update their role to Adult Child and send them an invite to manage their own profile?"

*Child starts their own family:*
- When an `adult_child` spins off into their own household, they automatically become the `head` of the new household.
- Their spouse (if already a member) can be added to the new household and assigned the `spouse` role.
- The original household retains a note: "[Name] spun off to their own household on [date]."
- The family tree connection is preserved in the audit log but the member is fully independent.

**Address inheritance and override:**
- Shared home address with inheritance rules.
- Member-specific override address capability.
- "Spin off" creates a new household (member becomes `head` of the new household) while preserving relationship history in the audit log.

**Admin tools:** create household, add/remove members, merge/split households, move member/spin-off.

### 5.9 Audit Logging and Diagnostics

**Tracked events:**

- Application submissions, verification email sent/resent, email verified, approve/reject decisions, invite sent/resend/revoke.
- Password reset requests and completions.
- Household changes (member added/removed, role changes, spin-off, merge/split).
- Admin profile edits, login attempts (success/failure).
- Member deactivation/reactivation.
- Google account linking/unlinking.
- Google Contacts sync runs (import/export), including contacts imported, created, and errors.
- Menu visibility toggle changes.
- Church Officers Group changes: officer added, officer removed, annual rotation, officer application approval/rejection (includes which officer took the action).
- Self-service deactivation/reactivation by members.
- Deletion requests: submitted, acknowledged, processed, cancelled.
- Member-initiated household changes: requests submitted, approved, denied.
- Profile merge operations: who merged, which profiles, field-level decisions.
- Data imports: source, record count, who initiated.
- WhatsApp group changes: added, updated, removed.
- Push notification subscription/unsubscription.
- Bulk operations: action type, affected member IDs, who initiated.
- Undo actions: what was undone, by whom, within grace period.

**Diagnostics page:**

- Database connection test, email delivery test, Google OAuth status check.
- Last sync/job status, error logs.
- wp_cron health check (see Section 6.6).

### 5.10 Member Deactivation and Removal (New in v2.1)

Members leave churches. The plugin must support the full offboarding lifecycle.

**Member Statuses:**

| Status | Description | Directory Visible | Can Log In |
|--------|-------------|-------------------|------------|
| `active` | Current, active member | Yes | Yes |
| `inactive` | Deactivated by admin | No | No |
| `suspended` | Temporarily suspended | No | No |
| `archived` | Data retained but member fully removed | No | No |

**Deactivation Flow:**

1. Admin/Secretary selects "Deactivate Member" from the member's admin profile.
2. Required: select reason (Moved away, Requested removal, Deceased, Disciplinary, Other) and optional notes.
3. System sets member status to `inactive`.
4. Member's directory profile is immediately hidden from all directory views.
5. Member's WordPress user role is downgraded (loses `member` capability; cannot access directory).
6. Household impact:
   - If the member was `head` of a household, the Secretary is prompted to assign a new head or deactivate the household.
   - If the member was the last member of a household, the household status is set to `inactive`.
   - The member's record remains in `wp_cd_household_members` with a `left_at` timestamp (new column).
7. The member's data is **retained** for the configured retention period (default: 2 years) for audit and potential reactivation.
8. Audit log records: actor, target member, reason, timestamp, household changes.

**Reactivation:**

- Admin/Secretary can reactivate an `inactive` or `suspended` member. The member regains directory access and their profile becomes visible again.
- `archived` members cannot be reactivated — a new application is required.

**Data Retention and Deletion:**

- After the retention period expires, a scheduled task (see Section 6.6) flags `archived` records for permanent deletion.
- Admin must manually confirm permanent deletion (no auto-purge of member data without confirmation).
- Permanent deletion removes: directory profile, household membership records, and personal data from the application record. The audit log entry is retained with anonymized references.

### 5.11 Application Form Field Specification (New in v2.1)

The application form is presented as a multi-step wizard with a progress indicator. Fields below should be confirmed during Phase 0 Discovery against the church's existing paper/PDF application.

**Step 1: Personal Information**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| First Name | Text | Yes | Min 2 chars, alpha + hyphens/apostrophes |
| Last Name | Text | Yes | Min 2 chars, alpha + hyphens/apostrophes |
| Email Address | Email | Yes | Valid email format, unique (not already in system) |
| Phone Number | Tel | Yes | US phone format (10 digits), stored as digits only |
| Date of Birth | Date | No | Must be a past date, age > 13 |
| Gender | Select | No | Male / Female / Prefer not to say |

**Step 2: Address**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Street Address | Text | Yes | Min 5 chars |
| Address Line 2 | Text | No | — |
| City | Text | Yes | Alpha + spaces/hyphens |
| State | Select | Yes | US states dropdown |
| ZIP Code | Text | Yes | 5-digit or ZIP+4 format |

**Step 3: Church Information**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| How long have you been attending? | Select | Yes | Less than 6 months / 6–12 months / 1–3 years / 3+ years |
| Are you a baptized/chrismated member? | Select | Yes | Yes / No / In process |
| Ministry interests | Multi-select checkboxes | No | Configurable list managed in admin settings |
| How did you hear about us? | Select | No | Friend/family / Website / Social media / Other |
| Additional notes | Textarea | No | Max 500 chars |

**Step 4: Family/Household (Optional)**

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Would you like to register as a household? | Toggle | No | — |
| Spouse first name | Text | Conditional (if household = yes) | Alpha + hyphens/apostrophes |
| Spouse last name | Text | Conditional | Alpha + hyphens/apostrophes |
| Spouse email | Email | Conditional | Valid email format |
| Spouse phone | Tel | No | US phone format |
| Number of children in household | Number | No | 0–20 |
| Children (repeater) | First name, last name, date of birth, email (optional) | No | Name: alpha; Age derived from DOB |

**What happens on approval (household application flow):**

1. The primary applicant is approved and receives their invite (normal flow).
2. **Spouse handling:**
   - If the spouse's email matches an existing active member → admin is prompted to link them into the household (no new application needed).
   - If the spouse's email matches a `pending_verification` or `new` application → the applications are linked and approved together.
   - If the spouse's email does **not** match any existing record → a `pending_profile` member record is created for the spouse. The spouse receives their own invite email to create login credentials and complete their profile. They do **not** need to fill out a separate application.
3. **Children handling:**
   - All children listed on the application are automatically created as `child` member records in the household, managed by the parents.
   - Children **with** an email address: an optional invite can be sent (configurable, default: do not send for children under 13).
   - Children **without** an email address: their profile exists in the household only, managed by parents. No invite is sent.
4. **Secretary approval screen** clearly shows: "Approving this application will also create accounts for: [Spouse Name] (invite will be sent to [email]) and [N] children (managed by parents)."

**Step 5: Review and Submit**

- Read-only summary of all entered data.
- Checkbox: "I confirm the information above is accurate."
- Checkbox: "I understand my information will be shared in the members-only church directory." (required)
- Submit button.

> **ADMIN CONFIGURABILITY**
>
> The admin settings panel allows customization of:
> - Which fields are shown/hidden (per step)
> - Which fields are required vs. optional
> - The ministry interests list
> - Custom fields (admin can add up to 5 additional text/select fields per step)
> - Form intro text and confirmation message

### 5.12 Google Contacts Integration (New in v2.1)

The plugin integrates with the **Google People API** (Google Contacts) to keep the church's Google Workspace contact list in sync with the directory. This serves two purposes: (1) import existing church contacts into the directory, and (2) export approved members back to Google Contacts.

#### 5.12.1 Import: Sync from Google Contacts (Admin Action)

**Trigger:** A manual "Sync from Google Contacts" button in WP Admin → Community → Google Sync.

**Purpose:** The admin can pull contacts from the church's Google Workspace shared contacts (or a designated Google account's contacts) into the directory. This is useful for initial population of the directory with existing church contact data.

**Sync flow:**

1. Admin clicks "Sync from Google Contacts" in the admin panel.
2. Plugin authenticates with Google People API using the configured service account or OAuth token (see Section 12.4).
3. Plugin retrieves all contacts from the configured Google Contacts source (default: the authenticated account's contacts; optionally: a Google Workspace Directory or shared contact group).
4. For each Google contact, the plugin runs **smart matching** to determine if the contact already exists in the directory:

**Smart Matching Algorithm:**

| Match Level | Criteria | Result |
|-------------|----------|--------|
| **Exact match** | Email address matches an existing member's primary or alternate email | Skip import (contact already exists). Optionally update fields if Google data is newer. |
| **Strong match** | First name + last name match AND (phone number matches OR email matches) | Flag as "Likely duplicate — review before importing." |
| **Weak match** | First name + last name match only (no email/phone match) | Flag as "Possible match — needs review." |
| **No match** | No name, email, or phone overlap with any existing member | Queue for import as a new pre-member record. |

5. Plugin presents a **sync preview screen** showing:
   - **Already in directory** (exact matches) — shown greyed out with a checkmark.
   - **Likely duplicates** (strong matches) — shown with a warning icon. Admin can choose: skip, merge with existing, or import as new.
   - **Possible matches** (weak matches) — shown with a question mark. Admin reviews and decides.
   - **New contacts** (no match) — shown ready to import. Admin can select/deselect individually or use "Select All."

6. Admin reviews and confirms. For contacts marked for import:
   - A `wp_cd_members` record is created with status `pending_profile` (the contact has been imported but hasn't set up their own login yet).
   - A `wp_cd_directory_profiles` record is populated with available data from Google (name, email, phone, address, photo).
   - Optionally, the admin can trigger an invite email to the imported contacts to activate their accounts.

**Fields synced from Google Contacts:**

| Google Contact Field | Maps to Directory Field |
|---------------------|----------------------|
| `names[0].givenName` | First Name |
| `names[0].familyName` | Last Name |
| `emailAddresses[0].value` | Email Address (primary) |
| `emailAddresses[1].value` | Email Address (alternate) |
| `phoneNumbers[0].value` | Mobile Phone |
| `phoneNumbers[1].value` | Home Phone |
| `addresses[0]` | Home Address (street, city, state, ZIP) |
| `organizations[0].name` | Employer / Business |
| `organizations[0].title` | Occupation / Profession |
| `biographies[0].value` | Bio / About Me |
| `photos[0].url` | Avatar (downloaded and stored locally) |
| `birthdays[0].date` | Date of Birth |
| `memberships` (group labels) | Ministry Involvement (best-effort mapping) |

**Admin controls:**

- **Sync source:** Configure which Google account or contact group to sync from.
- **Auto-sync schedule (optional):** Admin can enable a recurring sync (e.g., weekly) via wp_cron. Default: manual only.
- **Conflict resolution policy:** When a synced contact has updated data in Google, admin can choose: "Google wins" (overwrite directory), "Directory wins" (keep local), or "Flag for review."
- **Sync history log:** A table showing past sync runs with: date, contacts found, imported, skipped, errors.

#### 5.12.2 Export: Create Google Contact on Member Approval

**Trigger:** Automatic — when a Secretary/Admin approves an application.

**Purpose:** Ensure the church's Google Workspace contact list always reflects the current membership. When a new member is approved, a corresponding contact card is automatically created in Google Contacts.

**Export flow:**

1. Secretary approves an application in the admin queue.
2. Plugin checks if Google Contacts export is enabled (admin setting; default: enabled).
3. Plugin calls the Google People API to create a new contact with the following fields from the application and member profile:

| Directory Field | Google Contact Field |
|----------------|---------------------|
| First Name | `names[0].givenName` |
| Last Name | `names[0].familyName` |
| Email (primary) | `emailAddresses[0]` |
| Mobile Phone | `phoneNumbers[0]` (type: mobile) |
| Home Phone | `phoneNumbers[1]` (type: home) |
| Home Address | `addresses[0]` (type: home) |
| Occupation | `organizations[0].title` |
| Employer | `organizations[0].name` |

4. The new Google contact is added to a configurable contact group/label (default: "St. Thekla Members"). This label is auto-created if it doesn't exist.
5. The Google contact `resourceName` (unique ID) is stored in `wp_cd_members.google_contact_id` for future sync reference.
6. If the API call fails (e.g., quota, auth issue), the plugin logs the error, shows an admin notice, and queues the contact creation for retry (see Section 6.6).

**Ongoing sync (profile updates):**

- When a member updates their profile in the directory (e.g., new phone number, address change), the plugin optionally pushes the update to the corresponding Google Contact.
- Admin setting: "Auto-sync profile changes to Google Contacts" (default: enabled). Can be disabled if the church prefers one-way import only.
- Sync is per-field: only changed fields are updated in Google.

**Member deactivation:**

- When a member is deactivated (Section 5.10), the corresponding Google Contact is **not deleted** but is moved to a "Former Members" contact group/label. This preserves the contact data in Google while keeping the active members list clean.

> **IMPORTANT: Google API Quotas**
>
> The Google People API has a default quota of 90,000 requests/day for Google Workspace accounts. For a church directory of a few hundred members, this is more than sufficient. The plugin batches sync operations and implements exponential backoff on rate limit errors.

#### 5.12.3 Admin Sync Dashboard

WP Admin → Community → Google Sync displays:

| Element | Description |
|---------|-------------|
| **Connection status** | Green/red indicator showing if Google API is authenticated and accessible |
| **Sync from Google** button | Manual trigger with progress bar during sync |
| **Last sync** | Timestamp and summary of last import (e.g., "Jan 15, 2026 — 12 imported, 3 skipped, 0 errors") |
| **Auto-sync toggle** | Enable/disable recurring import sync + frequency selector |
| **Export to Google toggle** | Enable/disable auto-creation of Google Contacts on approval |
| **Profile sync toggle** | Enable/disable pushing profile updates to Google Contacts |
| **Contact group name** | Configurable label for the Google Contacts group (default: "St. Thekla Members") |
| **Sync history** | Table of past sync runs with details |
| **Pending retries** | Count of failed Google Contact creations queued for retry |

### 5.13 Church Officers Group (New in v2.1)

Church officers rotate annually. The WP Administrator manages the Officers Group from the admin panel.

#### 5.13.1 Admin Management

**WP Admin → Community → Officers Group**

| Element | Description |
|---------|-------------|
| **Officers list** | Table showing current officers: name, email, role/title (e.g., "President," "Treasurer," "Board Member"), date added |
| **Add Officer** | Enter email address of an existing approved member. The member's account is elevated to Church Officer role. If the email does not match an existing member, a warning is shown. |
| **Remove Officer** | Removes the Church Officer role from the member. They revert to standard Member permissions. |
| **Officer title** | Optional free-text field (e.g., "President," "Vice President," "Secretary," "Treasurer," "Board Member") for display purposes. Does not affect permissions — all officers have the same capabilities. |
| **Year/term label** | Configurable label for the current group (e.g., "2026 Officers," "2025-2026 Board"). Informational only. |
| **Bulk replace** | "New Year Rotation" button: clears all current officers and allows adding the new year's officers. Previous officers are logged in audit history and reverted to Member role. |

#### 5.13.2 Officer Front-End Experience

When a Church Officer logs into the directory:

1. **Default view:** The community directory (same as any member) — officers are members first.
2. **Member Administration tab:** An additional tab/button is visible in the directory navigation (not in WP Admin) that shows:
   - **Pending applications** count badge
   - **Applications list:** New and Under Review applications with applicant name, email, submission date, and status
   - **Application detail:** Click to view full application details, internal notes from other officers
   - **Actions:** Approve, Not Approved (with reason), Request More Info, Place On Hold — same workflow as Secretary (Section 5.4)
   - **Recent activity:** Last 10 approval/rejection actions taken by any officer
3. **No WP Admin access required:** Officers manage applications entirely from the front-end directory interface. They do not need WP Admin panel access.

#### 5.13.3 Officer Permissions (Boundaries)

Officers **can:**
- View and search the directory (same as any member)
- View pending member applications
- Approve or reject applications (triggers invite workflow + Google Contact creation)
- Add internal notes to applications
- View their own approval/rejection history

Officers **cannot:**
- Edit other members' profiles
- Export member data
- Manage households (beyond their own)
- Access plugin settings, email templates, or Google sync configuration
- Add/remove other officers (Admin only)
- Access WP Admin plugin pages

#### 5.13.4 Annual Rotation Workflow

1. Admin clicks **"New Year Rotation"** in WP Admin → Community → Officers Group.
2. System displays the current officers list and asks for confirmation: "This will remove Officer permissions from all current officers. Continue?"
3. On confirm: all current officers are reverted to Member role. The change is audit-logged with the year/term label.
4. Admin adds the new year's officers by email.
5. New officers receive an email notification: "You have been added as a Church Officer for [year/term]. You now have access to the Member Administration tab in the directory."
6. Removed officers receive an email: "Your Church Officer term for [previous year/term] has ended. Thank you for your service."

### 5.14 Data Import: Google Sheets, Google Contacts, and CSV (New in v2.1)

The WP Administrator needs a one-time bulk import to onboard existing parishioners without requiring each person to submit an application form. The import must also be re-runnable without creating duplicates.

**WP Admin → Community → Import Members**

#### 5.14.1 Import Sources

| Source | Method | Notes |
|--------|--------|-------|
| **Google Contacts** | OAuth token (Section 12.4) connects to church Google Workspace account. Retrieves contacts from a specified contact group or all contacts. | Already partially defined in Section 5.12.1 — this extends it as a full onboarding tool. |
| **Google Sheets** | Admin pastes a Google Sheets URL or Sheet ID. Plugin authenticates via the same OAuth token and reads the sheet using the Google Sheets API (requires `spreadsheets.readonly` scope — added to OAuth configuration). | Ideal when the parish roster is maintained as a spreadsheet. |
| **CSV file upload** | Admin uploads a `.csv` file (max 5 MB). | For parish lists exported from Excel, ParishSOFT, Breeze, or other systems. |

#### 5.14.2 Import Flow (Same for All Sources)

1. **Connect / Upload:** Admin selects the source and connects or uploads.
2. **Column Mapping:** The system displays the first 5 rows as a preview. For Google Sheets and CSV, admin maps columns to directory fields:
   - First Name, Last Name (required)
   - Email (required — used as the unique identifier for deduplication)
   - Phone, Address, Date of Birth, etc. (optional)
   - Unmapped columns are ignored.
   - Google Contacts uses automatic field mapping (names, emails, phones, addresses are standard).
3. **Smart Deduplication:** The system runs the **same smart matching algorithm** from Section 5.12.1 against all existing members in the database:
   - **Exact match (email):** Row is marked "Already exists — skip." The existing member's record is not modified.
   - **Strong match (name + phone or name + address):** Row is flagged "Likely duplicate of [Existing Member Name]." Admin decides: skip or import as new.
   - **Weak match (name only):** Row is flagged "Possible match." Admin decides.
   - **No match:** Row is queued for import.
4. **Preview Screen:** Shows all rows categorized by match level with counts:
   - "42 new members to import, 15 already exist (will be skipped), 3 need your review"
   - Admin can click into flagged rows to resolve them.
5. **Import Execution:** Admin clicks "Import [N] Members."
   - Each imported record is created as a `wp_cd_members` record with status `pending_profile` and a `wp_cd_directory_profiles` record populated with available data.
   - **No verification email is sent during import** — these are trusted existing members being onboarded by the admin.
   - Optionally, admin can choose to **send bulk invite emails** to all imported members so they can create their own login credentials and complete their profiles.
6. **Import Summary:** Shows results: imported, skipped, errors. Full log saved to `wp_cd_audit_log`.

#### 5.14.3 Re-Import Safety

If the admin runs the import again (e.g., after updating the spreadsheet with new members):

- The deduplication logic ensures **existing members are never duplicated.** Only genuinely new rows (no email match) are imported.
- A "Changes Detected" column shows if any imported data differs from the existing member's profile (e.g., phone number changed). Admin can choose to update existing records or skip.
- Re-import is audit-logged with a comparison summary.

#### 5.14.4 Google Sheets API Scope

Add `https://www.googleapis.com/auth/spreadsheets.readonly` to the OAuth scopes (Section 12.4) when Google Sheets import is enabled. This scope provides read-only access to Google Sheets — the plugin never modifies the source spreadsheet.

### 5.15 Duplicate Detection, Notification, and Profile Merge (New in v2.1)

#### 5.15.1 Automatic Duplicate Detection

The system proactively detects potential duplicates at these trigger points:

| Trigger | Check | Action |
|---------|-------|--------|
| **Application verified** | Run smart matching against all existing members | Flag in approval queue: "Possible duplicate of [Name]" |
| **Import preview** | Run smart matching on all import rows | Flag in import preview (Section 5.14) |
| **Profile update** | If a member changes their primary email to one that matches another member | Block the change with warning: "This email is already associated with another account." |
| **Google Contacts sync** | During import sync | Flag in sync preview (Section 5.12) |

#### 5.15.2 User-Facing Duplicate Notification

When a new applicant submits an application and the system detects that their email already exists in the directory:

1. The application form shows a clear message: **"An account with this email address already exists in the directory. If you already have an account, please log in instead. If you need help accessing your account, please contact the church office at [configured email/phone]."**
2. The form does **not** reveal any details about the existing member (name, status, etc.) — only that the email is in use.
3. If the applicant believes this is an error, they can still submit (the application is created with a "duplicate_flagged" indicator for the admin to review).

#### 5.15.3 Admin Profile Merge Tool

**WP Admin → Community → Members → Merge Profiles**

When an admin identifies duplicate member records (either from duplicate flags or manual discovery):

1. Admin selects two member records to merge.
2. System displays a **side-by-side comparison** of all fields from both profiles.
3. For each field, admin selects which value to keep (or enters a manual override). Default: prefer the more recently updated value.
4. Admin selects which record is the **"survivor"** (primary record that will be kept). The other record is the "duplicate" to be merged into the survivor.
5. **Merge actions:**
   - All data from the duplicate is merged into the survivor per the admin's field selections.
   - The duplicate's audit log entries are re-linked to the survivor.
   - The duplicate's household memberships are transferred to the survivor (if not already a member of those households).
   - The duplicate's WordPress user account (if any) is linked to the survivor's member record, or deleted if the survivor already has a WP user.
   - The duplicate's `wp_cd_members` record is marked `merged_into: [survivor_id]` and status set to `archived`.
   - The duplicate's Google Contact (if any) is flagged for admin review.
6. The merge is audit-logged with full before/after details.
7. A confirmation screen shows the merged result before committing.

### 5.16 WhatsApp Group Links (New in v2.1)

Church group communication happens over WhatsApp. The directory provides a centralized hub for members to discover and join parish WhatsApp groups.

#### 5.16.1 Admin Management

**WP Admin → Community → WhatsApp Groups**

| Element | Description |
|---------|-------------|
| **Group list** | Table of configured WhatsApp groups: name, description, invite link, icon/emoji, visibility, display order |
| **Add Group** | Admin enters: group name (e.g., "Men's Fellowship," "Sunday School Parents," "Youth Group"), optional description, WhatsApp invite link (`https://chat.whatsapp.com/...`), optional icon/emoji, and visibility (All Members / Officers Only / specific ministry tag) |
| **Edit / Remove** | Admin can update the link (e.g., when a group's invite link is refreshed) or remove a group |
| **Reorder** | Drag-and-drop to set display order |

#### 5.16.2 Member-Facing Experience

A **"WhatsApp Groups"** section is displayed on the directory landing page (below the member search, or as a dedicated tab):

- Each group is shown as a card with: name, description, and a **"Join on WhatsApp"** button.
- **Mobile behavior (PWA and browser):**
  - If WhatsApp is installed: tapping the button opens the WhatsApp app directly to the group invite page using the `https://chat.whatsapp.com/XXXXX` deep link (WhatsApp intercepts these URLs and opens the app).
  - If WhatsApp is **not** installed: the same URL opens in the browser, and WhatsApp's web page prompts the user to download the app from the App Store or Google Play.
- **Desktop behavior:** Opens the WhatsApp Web interface or prompts to open the WhatsApp desktop app.
- Groups are filtered by visibility: members only see groups they're eligible for (based on their ministry tags or role).
- A small note below the section: "These groups are managed by church leadership. Contact the church office to request a new group."

#### 5.16.3 Data Model

| Table | Fields |
|-------|--------|
| `wp_cd_whatsapp_groups` | `id`, `name`, `description`, `invite_url`, `icon` (emoji or image), `visibility` (all / officers / ministry tag), `visibility_tag` (if ministry-specific), `display_order`, `is_active`, `created_by`, `created_at`, `updated_at` |

### 5.17 Self-Service Account Deactivation and Deletion (New in v2.1)

Members have control over their own directory presence. Two distinct options are provided.

#### 5.17.1 Self-Service Deactivation

A member can deactivate their own account from **Profile Settings → Account → Deactivate My Account.**

**Flow:**

1. Member clicks "Deactivate My Account."
2. Confirmation screen explains: "This will hide your profile from the directory. Other members will no longer be able to find or contact you. You can request reactivation at any time by contacting the church office."
3. Optional: reason selection (Taking a break, Moving away, Privacy concerns, Other).
4. Member confirms.
5. **Immediate effect:**
   - Member status set to `self_deactivated` (a new status distinct from admin-initiated `inactive`).
   - Profile hidden from all directory views and search.
   - Member can still log in to view the directory but their profile is not visible to others.
   - Data is **retained** — nothing is deleted.
   - Only **WP Administrators** can see the member's profile in the admin panel.
6. Secretary/Officers receive a notification: "[Name] has deactivated their directory profile. Reason: [reason]."
7. Audit-logged with the member as the actor.

**Reactivation:** The member can reactivate themselves from Profile Settings → Account → "Reactivate My Profile" (instantly restores visibility). Or they can contact the church office.

#### 5.17.2 Self-Service Deletion Request

A member can request full data deletion from **Profile Settings → Account → Request Account Deletion.**

**Flow:**

1. Member clicks "Request Account Deletion."
2. Warning screen explains: "This will permanently remove all your data from the directory, including your profile, photos, and household associations. This action cannot be undone. A church leader must acknowledge your request before it is processed."
3. Member types "DELETE" to confirm (prevents accidental clicks).
4. Member confirms.
5. **What happens:**
   - Member status set to `deletion_requested`.
   - Profile is immediately hidden from the directory (same as deactivation).
   - A **deletion request notification** is sent to all active Church Officers and the WP Administrator.
   - The request appears in **WP Admin → Community → Deletion Requests** with: member name, request date, reason (if provided).
6. **Leadership acknowledgment required:**
   - A Church Officer or WP Admin must click "Acknowledge and Process Deletion" on the request.
   - On acknowledgment: all member data is permanently deleted (profile, photos, household memberships, personal data from application record). Audit log entry is retained with anonymized references.
   - The member receives an email: "Your directory account and data have been permanently deleted as requested."
7. **If no acknowledgment within 30 days:** The system sends a reminder to Officers/Admin. The member's data remains hidden but is not auto-deleted — leadership must explicitly acknowledge.

**Member Statuses (updated):**

| Status | Description | Directory Visible | Can Log In |
|--------|-------------|-------------------|------------|
| `active` | Current, active member | Yes | Yes |
| `self_deactivated` | Deactivated by member themselves | No | Yes (view-only) |
| `inactive` | Deactivated by admin | No | No |
| `suspended` | Temporarily suspended | No | No |
| `deletion_requested` | Member requested full deletion, awaiting leadership ack | No | No |
| `archived` | Data retained but member fully removed | No | No |

---

## 6. Data Architecture

### 6.1 System Overview

All data is stored in the WordPress MySQL database on Bluehost. The plugin creates custom tables with the `wp_` prefix (or the site's configured prefix). WordPress handles authentication, and the plugin's custom tables handle application-specific data.

### 6.2 Database Tables

The plugin creates the following custom tables on activation:

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `wp_cd_applications` | Membership applications | `id`, `email`, `first_name`, `last_name`, `form_data` (JSON), `status` (pending_verification / new / under_review / approved / not_approved / on_hold), `verification_token_hash`, `verification_sent_at`, `verified_at`, `submitted_at`, `reviewed_by`, `reviewed_at`, `notes`, `rejection_reason`, `previous_application_id` |
| `wp_cd_members` | Approved members linked to WP users | `id`, `uuid` (public-facing, non-sequential), `wp_user_id`, `application_id`, `status` (active/self_deactivated/inactive/suspended/deletion_requested/archived), `activated_at`, `deactivated_at`, `deactivation_reason`, `google_id`, `google_contact_id`, `passkey_credential_id`, `member_since` |
| `wp_cd_directory_profiles` | Visible directory profile data | `id`, `member_id`, `emails` (JSON array: `[{value, label, is_primary, privacy}]`), `phones` (JSON array: `[{value, label, type, is_primary, privacy}]`), `address_home`, `address_mailing`, `bio`, `avatar_url`, `avatar_source`, `date_of_birth`, `name_day`, `baptism_date`, `wedding_anniversary`, `occupation`, `employer`, `preferred_contact_method`, `preferred_language`, `emergency_contact_name`, `emergency_contact_phone`, `social_links` (JSON), `ministry_tags` (JSON), `privacy_settings` (JSON) |
| `wp_cd_households` | Household/family groups | `id`, `name`, `primary_address`, `status`, `created_by`, `created_at` |
| `wp_cd_household_members` | Join table: members ↔ households | `id`, `household_id`, `member_id`, `role`, `address_override`, `joined_at`, `left_at` |
| `wp_cd_invites` | Activation invites for approved applicants | `id`, `application_id`, `email`, `token_hash`, `expires_at`, `used_at`, `created_at` |
| `wp_cd_audit_log` | All sensitive actions | `id`, `event_type`, `actor_id`, `target_id`, `details` (JSON), `ip_address`, `created_at` |
| `wp_cd_google_sync_log` | Google Contacts sync history | `id`, `sync_type` (import/export), `status`, `contacts_found`, `contacts_imported`, `contacts_skipped`, `contacts_errored`, `started_at`, `completed_at`, `details` (JSON) |
| `wp_cd_officers` | Church Officers Group (new) | `id`, `member_id` (FK to wp_cd_members), `email`, `title` (e.g., "President"), `term_label` (e.g., "2026 Officers"), `added_by` (admin user ID), `added_at`, `removed_at`, `is_active` (boolean) |
| `wp_cd_push_subscriptions` | Web Push notification subscriptions (new) | `id`, `member_id`, `endpoint`, `p256dh_key`, `auth_key`, `user_agent`, `created_at`, `last_used_at` |
| `wp_cd_whatsapp_groups` | WhatsApp group links (new) | `id`, `name`, `description`, `invite_url`, `icon`, `visibility` (all/officers/ministry), `visibility_tag`, `display_order`, `is_active`, `created_by`, `created_at`, `updated_at` |
| `wp_cd_household_requests` | Member-initiated household change requests (new) | `id`, `type` (add_spouse/spin_off/merge/transfer_head), `requesting_member_id`, `target_member_id`, `household_id`, `status` (pending/approved/denied), `reviewed_by`, `reviewed_at`, `created_at` |
| `wp_cd_deletion_requests` | Member self-service deletion requests (new) | `id`, `member_id`, `reason`, `requested_at`, `acknowledged_by`, `acknowledged_at`, `status` (pending/processed/cancelled) |
| `wp_cd_schema_versions` | Schema migration tracking (new) | `id`, `version`, `applied_at`, `description` |

### 6.3 Key Data Rules

- Application submission always creates a `wp_cd_applications` record.
- Approval creates/updates `wp_cd_members` and triggers `wp_cd_invites`.
- Directory profile fields live in `wp_cd_directory_profiles`.
- Households and member relationships are modeled through `wp_cd_households` and `wp_cd_household_members`.
- All tables use InnoDB engine with proper foreign key constraints.
- `form_data`, `social_links`, `privacy_settings`, and audit `details` are stored as JSON columns (see Section 15 for MySQL version requirement). If the hosting environment runs MySQL < 5.7.8, these columns must use `LONGTEXT` with application-level JSON serialization/deserialization via `json_encode()` / `json_decode()`.
- The `audit_log` table should have an index on `event_type` and `created_at` for efficient querying; retention policy enforced via scheduled task (see Section 6.6).
- Re-applications link to previous submissions via `previous_application_id` for Secretary context.

### 6.4 Performance Considerations

- Add database indexes on frequently queried columns (`email`, `status`, `wp_user_id`, `household_id`).
- Use WordPress transients for caching directory search results (short TTL, e.g., 5 minutes).
- Paginate all list views (default 20 per page).
- Batch write audit log entries where possible.

### 6.5 Schema Versioning and Migration (New in v2.1)

The plugin must support safe schema evolution across updates.

**Approach:**

- The plugin stores a `db_version` option in `wp_options` (e.g., `cd_db_version`).
- On plugin activation and on `plugins_loaded` hook, the plugin compares the stored version against the code's current version.
- If the stored version is behind, the plugin runs incremental migration functions in order.
- All schema changes use WordPress's `dbDelta()` function for safe, idempotent table creation and modification.
- Each migration is recorded in `wp_cd_schema_versions` with a timestamp.

**Migration file structure:**

```
/includes/migrations/
  001-initial-schema.php
  002-add-rejection-fields.php
  003-add-household-left-at.php
  ...
```

**Rules:**

- Migrations are **forward-only** (no rollback). If a migration fails, the plugin displays an admin notice with the error and halts further migrations.
- Migrations must be idempotent — running the same migration twice should not cause errors.
- Data migrations (backfilling columns) run in batches to avoid timeout on shared hosting.
- The plugin must never drop tables or columns without a major version bump and explicit admin confirmation.

### 6.6 Background Processing and Scheduled Tasks (New in v2.1)

The plugin uses WordPress's `wp_cron` system for recurring maintenance tasks.

**Scheduled Tasks:**

| Task | Frequency | Description |
|------|-----------|-------------|
| `cd_expire_invites` | Twice daily | Mark expired invites (past `expires_at`) as `expired` status |
| `cd_audit_log_cleanup` | Weekly | Archive or delete audit log entries older than the configured retention period (default: 2 years). Requires admin confirmation for first run. |
| `cd_expire_reset_tokens` | Hourly | Clean up expired password reset tokens (handled natively by WordPress, but plugin clears any custom tracking) |
| `cd_data_retention_check` | Monthly | Flag `archived` member records past the retention period for admin review/deletion |
| `cd_archive_unverified` | Daily | Auto-archive applications stuck in `pending_verification` for longer than the configured period (default: 30 days). Archived applications are logged and no longer count toward duplicate email checks. |
| `cd_transient_cleanup` | Daily | Clear stale directory search cache transients |
| `cd_google_contact_retry` | Hourly | Retry failed Google Contact creation/update operations (exponential backoff, max 3 retries) |
| `cd_google_sync_auto` | Configurable (default: disabled) | If admin enables auto-sync, run Google Contacts import on configured schedule (weekly/daily) |

**wp_cron Reliability on Shared Hosting:**

WordPress's pseudo-cron relies on site traffic to trigger scheduled events. On a low-traffic site, tasks may be delayed. Mitigation options (documented in admin settings):

1. **Recommended:** Configure a real server-side cron job via Bluehost cPanel to hit `wp-cron.php` every 15 minutes, and disable WordPress's built-in pseudo-cron (`DISABLE_WP_CRON`).
2. **Alternative:** Use a free external cron service (e.g., cron-job.org) to ping the site's `wp-cron.php` URL on a schedule.
3. **Fallback:** Accept that on very low-traffic days, tasks may run late. The plugin should handle this gracefully (no duplicate runs, idempotent operations).

The Diagnostics page (Section 5.9) should display: next scheduled run time for each task, last successful run time, and any errors from the last run.

---

## 7. UI/UX Requirements

### Design System

- Modern "card + drawer" interaction patterns: cards for member/household/application lists; right-side drawer (desktop) or full-screen modal (mobile) for details/edit.
- Mobile-first layout and accessibility: large touch targets, clear labels, minimal jargon, inline validation, step-by-step forms.

### Login and Password Reset UX

- Login page is clean and uncluttered: email field, password field, "Forgot password?" link, "Continue with Google" button, and "Apply for Membership" link.
- Forgot password flow is a simple single-page form: enter email, receive confirmation message, check email, set new password.
- All auth pages are visually consistent with the Community directory theme.

### Household/Relationship UX

- Household screen shows members as cards with role badges (Head / Spouse / Adult Child / Child / Other).
- Inheritance controls: "Use household home address" toggle and "Use a different address" toggle.
- "Spin off" is a guided action with confirmation: "Create new household for [Name]. They will become Head of the new household."

### Avatar / Profile Photo Handling (New in v2.1)

#### Resolution Order (Default Source)

Member avatars follow this resolution order:

1. **Custom upload** — member-uploaded photo takes highest priority.
2. **Google OAuth profile photo** — synced on first login, used as the **default** if no custom upload exists. This means members who sign in with Google get a profile photo automatically without any effort.
3. **Initials fallback** — generated SVG with member's initials on a colored background (color deterministic based on name hash). Used only when no photo is available from either source.

#### Smart Image Processing

Members may upload photos of any size or aspect ratio. The system handles this gracefully:

**Thumbnail (used in directory list, cards, household views):**

| Setting | Value |
|---------|-------|
| Max file size | 10 MB (original upload) |
| Accepted formats | JPEG, PNG, WebP, HEIC (iPhone photos auto-converted to JPEG) |
| Thumbnail dimensions | 300x300px square (auto-generated from original) |
| Storage location | `wp-content/uploads/community-directory/avatars/` |
| Cache busting | Avatar URL includes a version query param updated on change |

**Processing pipeline on upload:**

1. **Validate** — MIME type check (server-side, not just extension), file size ≤ 10 MB.
2. **EXIF strip** — remove all metadata including GPS coordinates (critical for privacy — see Section 10).
3. **Auto-orient** — correct rotation based on EXIF orientation flag (before stripping) so phone photos display correctly.
4. **Crop UI** — present a square crop tool (client-side, using Cropper.js) so the member can choose which portion of their image to use for the thumbnail. The crop area is overlaid on the full image with drag-to-reposition and pinch/scroll-to-zoom.
5. **Generate thumbnail** — crop and resize to 300x300px square JPEG (quality 85%). This is the version used throughout the directory UI.
6. **Store original** — keep the full-resolution original (resized to max 2048px on longest edge to save disk space) for lightbox viewing. Stored alongside the thumbnail with a `_full` suffix.

**Aspect ratio handling (layout protection):**

- All directory views (list, cards, grid, household) use the **300x300 thumbnail** inside a fixed-size container with `object-fit: cover`. This guarantees consistent layout regardless of the original image dimensions.
- The CSS container enforces a 1:1 aspect ratio so the layout never breaks — even if the thumbnail generation fails, the container holds its shape and shows the initials fallback.

#### Lightbox Viewer

When a user clicks/taps on any profile photo in the directory:

1. A **fullscreen lightbox overlay** opens displaying the full-resolution image (the `_full` version, up to 2048px).
2. **Lightbox features:**
   - Dark semi-transparent backdrop
   - Image centered and scaled to fit the viewport (maintains aspect ratio — tall photos, wide photos, and square photos all display correctly)
   - **Pinch-to-zoom** on mobile, **scroll-to-zoom** on desktop
   - **Close button** (X) in the top-right corner
   - **Tap/click backdrop** to close
   - **Escape key** to close (desktop)
   - **Swipe down** to dismiss (mobile)
   - Member's name displayed below the image
3. **Implementation:** Lightweight vanilla JS lightbox (no heavy library). Approximately 2-3KB. Lazy-loads the full image only when the lightbox opens (the directory UI only loads thumbnails).
4. **Accessibility:** Lightbox traps focus, supports keyboard navigation (Escape to close, Tab to cycle controls), and includes `aria-label` on the close button.

#### Admin settings:
- Enable/disable custom avatar uploads (default: enabled).
- Enable/disable Google avatar sync (default: enabled).
- Default avatar style for initials fallback (configurable colors or use a single brand color).
- Max upload file size (default: 10 MB, configurable).

---

## 8. PWA "Appify Community Directory"

### Goal

Allow members on iOS/Android to install the directory as a home-screen app-like experience (no App Store).

### Requirements

- Android: show install prompt when eligible. iOS: show "Add to Home Screen" instructions.
- PWA scope restricted to `/community/` with `start_url` of `/community/` or `/community/login/`.
- Cache only app shell and static assets; do not cache sensitive directory data for offline access.
- Graceful re-authentication when session expires in PWA context (redirect to login, not a broken state).

### Home Screen Icon and Branding (New in v2.1)

When a member installs the PWA to their phone's home screen, the experience must feel like a native app with proper church branding.

**Web App Manifest (`manifest.json`) requirements:**

| Field | Value |
|-------|-------|
| `name` | "St. Thekla Directory" |
| `short_name` | "St. Thekla" |
| `description` | "St. Thekla Church Community Directory" |
| `display` | `standalone` |
| `orientation` | `portrait` |
| `theme_color` | Church brand primary color (configurable in plugin settings) |
| `background_color` | White (`#FFFFFF`) or configurable |
| `start_url` | `/community/` |
| `scope` | `/community/` |

**Icon requirements:**

The home screen icon must use the **church site logo** (not a generic icon). The plugin must generate or accept icons in the following sizes:

| Size | Purpose |
|------|---------|
| 192x192 | Android home screen, Chrome install prompt |
| 512x512 | Android splash screen, PWA install dialog |
| 180x180 | iOS home screen (`apple-touch-icon`) |
| 152x152 | iPad home screen |
| 120x120 | iPhone home screen (older devices) |
| 32x32 | Browser favicon fallback |

**Icon configuration in admin settings:**

- Admin uploads a single high-resolution logo (minimum 512x512, PNG with transparency recommended).
- Plugin auto-generates all required sizes on upload using WordPress's `wp_get_image_editor()`.
- Icons are stored in `wp-content/uploads/community-directory/pwa/`.
- A preview is shown in admin: "This is how your directory will appear on a member's phone home screen."
- The `<link rel="apple-touch-icon">` and `<link rel="manifest">` tags are injected into the `<head>` of all `/community/` pages.

**Splash screen (Android):**

- Uses the 512x512 icon centered on the `background_color`.
- `name` field ("St. Thekla Directory") is displayed below the icon.

**iOS-specific:**

- `<meta name="apple-mobile-web-app-capable" content="yes">`
- `<meta name="apple-mobile-web-app-status-bar-style" content="default">`
- `<meta name="apple-mobile-web-app-title" content="St. Thekla Directory">`

### Session Management in PWA Context (New in v2.1)

PWA apps can remain "open" on a device for extended periods. The plugin must handle session lifecycle gracefully:

**Session configuration:**

| Setting | Default | Notes |
|---------|---------|-------|
| Session duration | 30 days | Uses WordPress auth cookies (`LOGGED_IN_COOKIE`). After expiry, the member is redirected to the login page within the PWA shell. |
| "Remember me" behavior | Enabled by default in PWA | PWA context sets `remember=true` on login to avoid frequent re-authentication. |
| Session validation | On each page load | Plugin checks cookie validity via `wp_validate_auth_cookie()`. If invalid, redirect to `/community/login/` (not the WordPress default `wp-login.php`). |

**Offline behavior:**

- The service worker caches the app shell (HTML skeleton, CSS, JS, icons) only.
- When offline, the PWA shows a branded "You're offline" message with a retry button. No directory data is cached or shown offline (security requirement).
- When connectivity returns, the app automatically refreshes.

**Session expiry UX:**

- When a session expires mid-use, the PWA shows the login screen within the app frame (no browser redirect, no broken state).
- After re-login, the member is returned to the page they were viewing (deep link preservation).

### Push Notifications (New in v2.1)

The PWA uses the **Web Push API** with VAPID keys to deliver native OS notifications through the browser — no native app required. This works on Android (Chrome, Edge, Firefox) and iOS (Safari 16.4+).

#### Technical Setup

- The plugin generates a VAPID key pair (public + private) on first activation. Stored encrypted in `wp_options`.
- The existing service worker (required for PWA) listens for `push` events and displays native OS notifications.
- Push subscription endpoints are stored in `wp_cd_push_subscriptions` (new table): `id`, `member_id`, `endpoint`, `p256dh_key`, `auth_key`, `user_agent`, `created_at`, `last_used_at`.
- Server-side push is sent via PHP using the `web-push-php` library (Composer dependency) or a lightweight built-in implementation.

#### Subscription Flow

1. When a member installs the PWA and logs in, the app prompts **once**: "Would you like to receive notifications for new announcements and updates?" with Allow / Not Now.
2. If allowed, the browser's native permission dialog appears.
3. On grant, the push subscription is registered and stored server-side.
4. Members can manage notifications in Profile Settings → Notifications (see Section 16.9).

#### Notification Types

| Notification | Recipients | Default |
|-------------|------------|---------|
| New application received | Officers | On (push + email) |
| Application approved / account active | Applicant | On (email only) |
| Directory-wide announcement | All members | On (push, configurable) |
| Upcoming birthday/name day | All members | Off (opt-in) |
| Household change (member added/removed) | Affected household | On (push) |
| Deletion request received | Officers + Admin | On (push + email) |
| Profile deactivation notification | Officers | On (email only) |

#### Battery and Performance

- **No polling.** Push notifications are server-initiated — the device receives them passively via the OS push service (Google FCM for Android/Chrome, Apple APNs for iOS/Safari). Zero battery drain from the directory app when idle.
- Notifications are batched where sensible (e.g., multiple birthday reminders are grouped into a single notification).
- The service worker processes push events in milliseconds and does not keep the app awake.

#### Admin Controls (WP Admin → Community → Settings → Notifications)

- Enable/disable push notifications globally.
- Configure which notification types are sent via push vs. email vs. both.
- Test push: send a test notification to the admin's own device.

### App Badge for Officers (New in v2.1)

Officers see a **badge count** on the PWA icon showing pending applications.

- Uses the Badging API (`navigator.setAppBadge(count)`).
- Badge count = number of applications with status `new` + `under_review`.
- Updated when: a push notification arrives (service worker updates badge), or the officer opens the app.
- Badge is cleared (`navigator.clearAppBadge()`) when the officer views the applications queue and no pending items remain.
- Falls back gracefully on browsers/OS that don't support the Badging API (no badge, no error).

### Native Share Sheet (New in v2.1)

Replace the clipboard-copy "Share my profile" with the **Web Share API** as the primary mechanism:

```javascript
navigator.share({
  title: 'John Smith - St. Thekla Directory',
  text: 'Look me up in the St. Thekla Community Directory',
  url: 'https://sttheklachurch.org/community/member/uuid/'
})
```

- Opens the native OS share sheet: WhatsApp, Messages, Email, social media, AirDrop, etc.
- Falls back to clipboard copy with a "Link copied!" toast on browsers that don't support Web Share API (primarily desktop).
- Available on: "Share my profile" button, household sharing, and any other shareable content.

### Pull-to-Refresh (New in v2.1)

In PWA standalone mode, the browser's refresh button is hidden. Members need a native-feeling way to refresh content.

- **Pull-to-refresh gesture** on: directory list view, member profile, officer applications queue, WhatsApp groups page.
- Uses `touch` events with a custom implementation (~50 lines of JS). Shows a loading spinner at the top during refresh.
- CSS `overscroll-behavior-y: contain` prevents the pull-to-refresh from triggering the browser's default behavior.
- Desktop equivalent: a visible "Refresh" button in the directory toolbar.

### Background Sync for Profile Updates (New in v2.1)

When a member saves a profile change and the API call fails due to poor connectivity:

1. The UI shows the update optimistically (Section 16.10).
2. The failed request is queued using the **Background Sync API**: `registration.sync.register('profile-update')`.
3. The queued request is stored in IndexedDB with: endpoint, method, body, timestamp.
4. **Server-side processing:** When the service worker fires the sync event (connectivity restored), it retries the API call. The server processes it normally — no special handling needed. The member does **not** need to have the app open.
5. On success: a subtle notification is sent (if push is enabled): "Your profile update has been saved."
6. On failure after 3 retries: notification: "Your profile change couldn't be saved. Please try again."
7. **Fallback (Safari < 16.4):** If Background Sync is unavailable, show a warning inline: "Couldn't save — please try again when you have a connection." The optimistic UI update is reverted.

This is entirely server-initiated on retry — **no battery-draining polling.** The OS manages when the sync event fires based on connectivity state.

### Biometric / Passkey Authentication (New in v2.1)

When sessions expire, members can re-authenticate via biometrics instead of retyping their password.

**Setup flow:**

1. After initial login (email/password or Google OAuth), the app offers: "Enable Face ID / Fingerprint login for faster access?" with Enable / Skip.
2. If enabled, the app registers a **WebAuthn passkey** using the Web Authentication API.
3. The passkey credential ID is stored in `wp_cd_members.passkey_credential_id`.

**Re-authentication flow:**

1. Session expires → the PWA shows the login screen.
2. If a passkey is registered, the login screen shows a "Sign in with Face ID / Fingerprint" button prominently above the email/password fields.
3. Tapping it triggers the native biometric prompt (Face ID, Touch ID, fingerprint, Windows Hello).
4. On success, a new session is created. The member is returned to their previous page.

**Compatibility:** Supported on iOS Safari 16+, Android Chrome 108+, desktop Chrome/Edge/Firefox. Falls back to email/password on unsupported browsers.

### PWA Update Strategy (New in v2.1)

When the plugin is updated and the service worker changes:

1. On each app launch, the browser checks for service worker updates (default browser behavior).
2. If a new version is found, it installs in the background while the current version continues serving.
3. On next navigation, the app shows a non-intrusive banner at the top: **"A new version is available. Tap to update."**
4. Tapping calls `skipWaiting()` and `clients.claim()`, then reloads the page with the new version.
5. **Critical security updates:** The plugin can set a `FORCE_UPDATE` flag in the service worker that triggers automatic `skipWaiting()` without waiting for user action.
6. The service worker version is logged in the diagnostics page (Section 5.9).

---

## 9. Email and Deliverability

### Sending Mail

Plugin uses WordPress `wp_mail()` for all outbound email. Church Gmail/Workspace sending is handled via site SMTP configuration.

### Email Types

| Email | Trigger | Recipients |
|-------|---------|------------|
| Email verification | Application submitted | Applicant |
| Application notification | Application verified | Officers + configured list |
| Application received confirmation | Application verified | Applicant |
| Invite email | Application approved | Approved applicant |
| Spouse invite | Household application approved | Spouse listed on application |
| Password reset | Member request | Requesting member |
| Email lookup hint | "Can't remember email" request | All emails on matched member |
| Rejection notification (optional) | Application not approved | Applicant |
| Request more info (optional) | Secretary action | Applicant |
| Self-deactivation notification | Member self-deactivates | Officers + Secretary |
| Deletion request notification | Member requests deletion | Officers + Admin |
| Deletion confirmation | Deletion processed | Former member |
| Reactivation notification | Deactivated member requests reactivation | Officers + Secretary |
| Officer added notification | Admin adds officer | New officer |
| Officer removed notification | Admin removes officer / rotation | Former officer |
| Household invite | Head adds spouse via directory | Invited member |
| Child transition prompt | Child turns 18 | Parent (head/spouse) |

> **SMTP CONFIGURATION**
>
> For reliable email delivery from sttheklachurch.org, configure a WordPress SMTP plugin (e.g., WP Mail SMTP) to route through Google Workspace. See Section 12: External Service Setup Instructions for step-by-step details.

---

## 10. Security and Privacy Requirements

> **GUIDING PRINCIPLE**
>
> This directory holds the personal information of church members and their families — names, addresses, phone numbers, emails, photos of children. Even though the church is not subject to GDPR, HIPAA, or CCPA, we treat this data as if we were. Members trust the church with their information. A breach, a scraping incident, or a spam harvest would damage that trust. Every design decision defaults to the protective option.

### 10.1 Core Security Rules

- Directory pages require authentication + authorization (approved member role).
- Invite tokens: stored hashed (SHA-256), single-use, time-limited.
- Password reset tokens: WordPress native (single-use, time-limited).
- Privacy controls: field-level visibility toggles (phone/address/email), default visibility policy configured by admin.
- Data minimization: store only necessary fields.
- Audit logging for all sensitive actions (see Section 5.9).
- All custom database queries use WordPress `$wpdb` prepared statements to prevent SQL injection.
- Nonce verification on all form submissions and AJAX requests.

### 10.2 PII Protection Best Practices (New in v2.1)

#### 10.2.1 No PII in URLs

Personal data must never appear in URLs — not in query strings, not in path segments, not in fragments. URLs are logged by web servers, proxy servers, browser history, analytics tools, and CDN providers.

| Rule | Implementation |
|------|----------------|
| **Member IDs in URLs must be opaque** | Use non-sequential, random UUIDs (v4) or hashed slugs for member profile URLs, not auto-increment database IDs. Example: `/community/member/a3f8c2e1/` not `/community/member/42/`. This prevents enumeration attacks (incrementing IDs to scrape all profiles). |
| **No PII in query parameters** | Search queries go in `POST` request bodies, not `GET` query strings. The directory search endpoint uses `POST /community-directory/v1/directory/search` with the search term in the JSON body, not `GET /directory?name=John+Smith`. |
| **No tokens in URLs where avoidable** | Invite acceptance and password reset links must use short-lived, single-use tokens only. After the token is consumed, the URL becomes inert. Tokens in URLs should be the minimum viable length and never include any PII (no email in the URL). |
| **No email in password reset URL** | Reset link format: `/community/reset-password/?token=abc123` — never `/community/reset-password/?email=john@example.com&token=abc123`. |
| **Referrer policy** | All Community pages set `<meta name="referrer" content="no-referrer">` and the `Referrer-Policy: no-referrer` HTTP header. This prevents the current page URL (which may contain a token) from leaking to external sites via the Referer header. |
| **Canonical URLs for profiles** | Profile pages use a generic `<title>` tag ("Community Directory — Profile") and `<meta name="robots" content="noindex, nofollow">` to prevent search engine indexing. No member names or data in `<title>`, `<meta description>`, or Open Graph tags. |

#### 10.2.2 Database Protection

| Rule | Implementation |
|------|----------------|
| **No direct database exposure** | All data access goes through the WordPress REST API endpoints defined in Section 14. There is no phpMyAdmin-style direct query interface exposed by the plugin. |
| **Database table prefix randomization** | The plugin respects and uses the site's configured `$table_prefix`. Documentation recommends changing the default `wp_` prefix to a random string (e.g., `wp_x7k2_`) to deter automated SQL injection scanners targeting default table names. |
| **Sensitive fields encrypted at rest** | The following fields are encrypted in the database using AES-256-CBC (same mechanism as OAuth secrets — see Section 10.4): emergency contact name, emergency contact phone, date of birth, home address, mailing address. These are decrypted only in PHP memory when needed for display or API response. |
| **Database backups** | Admin documentation recommends encrypted backups. The plugin's Diagnostics page (Section 5.9) includes a reminder: "Your database contains personal member data. Ensure backups are stored encrypted and access-restricted." |
| **No PII in WordPress debug logs** | The plugin never passes member data to `error_log()`, `wp_die()` messages, or `WP_DEBUG_LOG`. All error logging uses anonymized references (member ID only, never names/emails/phones). |
| **Prepared statements only** | Every SQL query uses `$wpdb->prepare()`. No string concatenation in queries. This is enforced by code review and static analysis (if available). |

#### 10.2.3 API Key and Secret Protection

| Rule | Implementation |
|------|----------------|
| **No secrets in client-side code** | Google OAuth Client ID is exposed to the browser (required for the login flow), but the Client Secret, service account key, and reCAPTCHA Secret Key are **never** sent to the client. All secret-dependent operations happen server-side. |
| **No secrets in HTML source** | Plugin settings pages render secret fields as `type="password"` with the value masked. The actual secret is never written into HTML source, `data-` attributes, or inline `<script>` blocks. When viewing page source, secrets show as `••••••••`. |
| **No secrets in REST API responses** | Admin settings endpoints (`GET /admin/settings`) return `"google_client_secret": "configured"` (boolean presence indicator), never the actual value. Secrets are write-only via the API. |
| **No secrets in version control** | Plugin documentation explicitly warns: never commit `wp-config.php`, the Google service account JSON key, or database exports to Git. The plugin includes a `.gitignore` template for common secret file patterns. |
| **Encrypted storage** | All secrets are encrypted at rest in `wp_options` using AES-256-CBC (see Section 10.4). Plaintext secrets exist only in PHP memory during active use. |
| **Environment separation** | Plugin supports defining secrets via `wp-config.php` constants (e.g., `define('CD_GOOGLE_CLIENT_SECRET', '...')`) as an alternative to database storage. This allows secrets to be managed via server environment variables on hosting platforms that support it, keeping them out of the database entirely. |

#### 10.2.4 Anti-Scraping and Spam Prevention

The directory is a high-value target for scrapers — it's a concentrated list of names, emails, and phone numbers. These protections make bulk extraction impractical:

| Protection | Implementation |
|-----------|----------------|
| **Authentication wall** | No directory data is accessible without a valid, authenticated member session. There are zero public API endpoints that return member data. The application form submission endpoint returns only a confirmation message, never member data. |
| **No bulk export for members** | Regular members cannot download or export the directory as CSV, vCard, or any bulk format. Only Admin/Secretary roles can export, and exports are audit-logged. |
| **Pagination with limits** | The directory API returns a maximum of 20 results per page (configurable by admin, maximum cap of 50). There is no "return all members" option, even for authenticated users. |
| **Rate limiting on directory search** | In addition to login rate limits, the directory search API is rate-limited: 30 searches per minute per user. This prevents automated scraping of the full directory by iterating through the alphabet. |
| **No full-list enumeration** | The A-Z quick-jump feature (Section 16.1) loads results on-demand per letter, not the entire directory at once. Combined with pagination, a scraper would need hundreds of authenticated, rate-limited requests to extract the full directory. |
| **Bot detection** | The plugin checks for common bot signals on directory API requests: missing `Referer` header, non-browser `User-Agent`, impossibly fast sequential requests. Suspicious patterns trigger a temporary block and an audit log entry. |
| **Avatar hotlink protection** | Avatar images in `wp-content/uploads/community-directory/avatars/` are served through a PHP handler that checks authentication, not directly via the file URL. This prevents unauthenticated access to member photos and blocks hotlinking from external sites. The avatar directory has a `.htaccess` rule: `Deny from all` (Apache) or equivalent nginx rule, forcing all access through the authenticated handler. |
| **Email obfuscation in HTML** | When email addresses are rendered in profile views, they are obfuscated in the HTML source (e.g., using HTML entities or a lightweight JavaScript decode) to defeat simple scraping tools that parse raw HTML. The `mailto:` link is assembled client-side. |
| **CAPTCHA on public forms** | The application form (the only public-facing data submission point) supports optional reCAPTCHA v3 (Section 12.3) to prevent automated spam submissions. |

#### 10.2.5 Child Data Protection

Minor children's data requires extra care, even without specific regulatory requirements:

| Rule | Implementation |
|------|----------------|
| **Children not searchable by default** | Members with role `child` do not appear in directory search results. They are visible only within their household's profile view (Section 5.8). Admin can optionally allow teens to appear in search. |
| **No direct contact for children** | Phone and email fields for `child` role members are hidden from all non-household members and non-admin users, regardless of privacy settings. Only the parent (`head`/`spouse`) and admin/secretary can see a child's contact details. |
| **Photo access restricted** | Child avatar photos follow the same authenticated handler as adult photos, with an additional check: the requesting user must be a household member, admin, or secretary. |
| **No children in "New Members" widget** | The "New Members" highlight section (Section 16.6) never displays members with role `child`. |
| **Parental consent on record** | The household management screen where a parent adds a child includes a consent checkbox: "I am the parent/legal guardian of this child and consent to their information being stored in the church directory." This consent is timestamped and stored in the audit log. |

#### 10.2.6 Session and Cookie Security

| Rule | Implementation |
|------|----------------|
| **Secure cookies only** | All authentication cookies are set with `Secure` flag (HTTPS only), `HttpOnly` flag (no JavaScript access), and `SameSite=Strict` attribute (no cross-site transmission). |
| **Session fingerprinting** | On login, the plugin records a hash of the user's IP + User-Agent. If a subsequent request uses the same session cookie but from a different fingerprint, the session is invalidated and the user must re-authenticate. This prevents session hijacking via stolen cookies. |
| **No "remember me" token in URL** | The remember-me mechanism uses a secure cookie only. There is no URL-based session persistence (no `?session=abc` patterns). |
| **Automatic session expiry** | Inactive sessions expire after 30 days (configurable). Active sessions are validated on every page load (see Section 8 PWA session management). |
| **Concurrent session limit** | A member can have a maximum of 3 active sessions (e.g., phone, tablet, desktop). Creating a 4th session automatically invalidates the oldest. Admin/Secretary can view and revoke a member's active sessions. |

#### 10.2.7 HTTP Security Headers

The plugin injects the following security headers on all `/community/` pages:

| Header | Value | Purpose |
|--------|-------|---------|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' https://apis.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://*.googleusercontent.com; connect-src 'self' https://accounts.google.com; frame-src https://accounts.google.com;` | Prevents XSS by restricting which scripts, styles, and resources can load. Only allows the page's own domain, Google OAuth, and Google reCAPTCHA. |
| `X-Content-Type-Options` | `nosniff` | Prevents MIME-type sniffing attacks. |
| `X-Frame-Options` | `DENY` | Prevents the directory from being embedded in an iframe on another site (clickjacking protection). |
| `Referrer-Policy` | `no-referrer` | Prevents URL (potentially containing tokens) from leaking via Referer header. |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Disables unnecessary browser APIs that could be exploited. |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Forces HTTPS for all future visits (HSTS). |
| `X-XSS-Protection` | `1; mode=block` | Legacy XSS filter for older browsers. |
| `Cache-Control` | `no-store, no-cache, must-revalidate, private` | Prevents browser/proxy caching of directory pages containing PII. A shared computer or proxy must not serve cached directory data to another user. |

#### 10.2.8 Data Export and Portability

| Rule | Implementation |
|------|----------------|
| **Admin-only export** | Only WP Administrator and Directory Admin roles can export member data. Secretary cannot export. |
| **Export is audit-logged** | Every data export (CSV, etc.) is recorded in the audit log with: who exported, when, what scope (all members, filtered subset, single member), and from which IP. |
| **No automated/API export** | There is no REST API endpoint for bulk data export. Export is only available as a manual action in the WordPress admin panel with a confirmation dialog: "You are about to export personal data for [N] members. This action is logged." |
| **Export file warnings** | Exported files include a header row: "CONFIDENTIAL — Contains personal information of St. Thekla Church members. Do not share or distribute." |
| **Member data download** | Individual members can download their own data (name, profile, household info) as a summary from their profile settings. This supports data portability without exposing others' data. |

#### 10.2.9 Input Sanitization and Output Encoding

| Rule | Implementation |
|------|----------------|
| **Sanitize all input** | Every field submitted via form or API is sanitized using the appropriate WordPress function: `sanitize_text_field()` for text, `sanitize_email()` for emails, `intval()` for integers, `esc_url()` for URLs, `wp_kses()` for any field allowing limited HTML (bio field). |
| **Escape all output** | Every dynamic value rendered in HTML uses `esc_html()`, `esc_attr()`, or `esc_url()` as appropriate. This prevents stored XSS even if sanitization is somehow bypassed on input. |
| **No `eval()` or dynamic code execution** | The plugin never uses `eval()`, `preg_replace()` with the `/e` flag, or `call_user_func()` on user-supplied data. |
| **File upload validation** | Avatar uploads are validated server-side: MIME type check (not just extension), file size limit enforced, image is re-processed through `wp_get_image_editor()` (which strips EXIF data including GPS location, camera info, and other metadata that could leak sensitive information). |
| **EXIF stripping on photos** | All uploaded photos (avatars, child photos) have EXIF metadata completely removed before storage. This is critical because phone photos often contain GPS coordinates — a member uploading a photo taken at home would inadvertently expose their home location to anyone who downloads the image. WordPress's `wp_get_image_editor()` handles this when re-processing, but the plugin explicitly verifies EXIF removal as a post-processing step. |

### 10.3 Rate Limiting Implementation

WordPress does not provide built-in rate limiting. The plugin implements rate limiting using WordPress transients:

**Mechanism:**

- For each rate-limited action, the plugin stores a transient keyed by action type + identifier (IP address or email).
- Transient value is an array of timestamps for recent attempts.
- On each attempt, expired timestamps are pruned and the count is checked against the limit.
- If the limit is exceeded, the action is denied with a user-friendly message and a `Retry-After` hint.

**Rate limits:**

| Action | Identifier | Limit | Window | Lockout |
|--------|-----------|-------|--------|---------|
| Login attempts | IP address | 5 attempts | 15 minutes | Blocked for 15 minutes after limit reached |
| Password reset requests | Email address | 3 requests | 1 hour | Silently accepted but not sent (prevents email enumeration) |
| Application submissions | IP address | 3 submissions | 1 hour | Error message shown |
| Invite link usage | Token | 1 use | — | Token invalidated on first use |
| Directory search | Authenticated user | 30 requests | 1 minute | Temporary block with message |
| Profile views | Authenticated user | 60 requests | 1 minute | Temporary block (prevents rapid enumeration) |

**Admin controls:**
- Enable/disable rate limiting (default: enabled).
- Customize limits and windows per action type.
- View currently blocked IPs in the admin panel.
- Manually unblock an IP.

**CAPTCHA (optional):**
- CAPTCHA optional on application form and login (admin-configurable).
- Google reCAPTCHA v3 (invisible) recommended. See Section 12.3 for setup.

### 10.4 Secrets Encryption at Rest

All sensitive configuration values are encrypted in the `wp_options` table. This applies to: Google OAuth Client Secret, Google service account JSON key, reCAPTCHA Secret Key.

**Implementation:**

- Encryption uses `openssl_encrypt()` with AES-256-CBC.
- The encryption key is derived from WordPress's `AUTH_KEY` and `AUTH_SALT` constants (defined in `wp-config.php`).
- The IV (initialization vector) is generated per encryption and stored alongside the ciphertext (base64-encoded).
- On retrieval, the value is decrypted in memory and never written to logs, debug output, or error messages.
- If `AUTH_KEY` changes (e.g., after a security reset), the admin must re-enter all secrets in plugin settings.

### 10.5 Sensitive PII Fields Encrypted at Rest

Beyond API secrets, certain member PII fields are encrypted in the database to protect against database-level breaches (e.g., SQL dump stolen from a backup, unauthorized phpMyAdmin access):

**Encrypted fields:**

| Field | Table | Reason |
|-------|-------|--------|
| `address_home` | `wp_cd_directory_profiles` | Home address is high-sensitivity PII |
| `address_mailing` | `wp_cd_directory_profiles` | Mailing address is high-sensitivity PII |
| `date_of_birth` | `wp_cd_directory_profiles` | DOB is used in identity theft |
| `emergency_contact_name` | `wp_cd_directory_profiles` | Third-party PII (not even the member's own) |
| `emergency_contact_phone` | `wp_cd_directory_profiles` | Third-party PII |
| `primary_address` | `wp_cd_households` | Household address |

**Encryption mechanism:** Same AES-256-CBC approach as secrets (Section 10.4). The plugin provides helper methods `cd_encrypt($value)` and `cd_decrypt($value)` used consistently across all database read/write operations for these fields.

**Trade-off:** Encrypted fields cannot be queried with SQL `LIKE` or `WHERE` clauses. Address search (e.g., "find members near me") would need to be implemented at the application level after decryption, or via a separate non-sensitive index (e.g., ZIP code stored unencrypted for proximity search, while full address is encrypted).

### 10.6 WordPress Hardening Recommendations

The plugin's Diagnostics page (Section 5.9) includes a **Security Checklist** that scans the WordPress installation and flags issues:

| Check | Expected State | Action if Failing |
|-------|---------------|-------------------|
| SSL/HTTPS active | Yes | "Your site is not using HTTPS. Member data is transmitted unencrypted. Enable SSL immediately." |
| `WP_DEBUG` disabled in production | `false` | "Debug mode is on. Error messages may expose file paths and database details. Set `WP_DEBUG` to `false` in `wp-config.php`." |
| `WP_DEBUG_DISPLAY` disabled | `false` | "Debug display is on. PHP errors may be shown to visitors." |
| `DISALLOW_FILE_EDIT` set | `true` | "File editing is enabled in WordPress admin. An attacker with admin access could modify plugin code. Add `define('DISALLOW_FILE_EDIT', true)` to `wp-config.php`." |
| Default `wp_` table prefix changed | Non-default | "Your database uses the default table prefix. Consider changing it to reduce automated attack surface." |
| WordPress version up to date | Latest | "Your WordPress version is outdated. Update to receive security patches." |
| PHP version supported | 7.4+ | "Your PHP version is outdated and may have known vulnerabilities." |
| `AUTH_KEY` and salts are non-default | Custom values | "Your WordPress security keys are set to default values. Generate new keys at api.wordpress.org/secret-key." |
| XML-RPC disabled | Recommended | "XML-RPC is enabled. If not needed, disable it to reduce attack surface. The Community Directory does not require XML-RPC." |

---

## 11. Phased Delivery Plan

### Phase 0 — Discovery and Setup (Foundation)

**Outcomes:**

- Confirm application form content and field definitions (validate Section 5.11 against church's existing form).
- Set up Google OAuth credentials (see Section 12.1).
- Set up Google People API / service account for Contacts sync (see Section 12.4).
- Configure SMTP for email delivery (see Section 12.2).
- Define officer email recipients and privacy expectations.
- Confirm URL structure for Community pages.
- Verify MySQL version on Bluehost (see Section 15).
- Select and document frontend approach (see Section 14).

**Deliverables:**

- Final database schema (SQL migration file).
- Form blueprint (sections, fields, validations) — confirmed against Section 5.11.
- Plugin skeleton with settings shell, database table creation on activation, schema versioning system, Google OAuth configuration page, and Google Sync settings page.

### Phase 1 — Community Landing + Core Gating + Applications Intake

**Build:**

- Community landing page with: email/password login, Google OAuth login, "Forgot password?" flow, "Apply for Membership" link.
- Admin menu visibility toggle (Section 5.1) — allows admin to hide/show the Community nav item.
- Application form UI (wizard) per Section 5.11.
- Save submissions to `wp_cd_applications` table.
- Admin Applications queue (view-only initially).
- Officer notification emails on submission.
- Rate limiting on login and password reset.

**Acceptance criteria:**

- Application submits successfully and appears in admin portal.
- Officers receive notification email per configuration.
- Password reset emails send and complete successfully.
- Directory remains locked down.
- Rate limiting blocks excessive login attempts.
- All HTTP security headers are served on `/community/` pages.
- No PII appears in any URL, server log, or HTML source code.
- Security checklist in Diagnostics page is functional.

### Phase 2 — Secretary Approvals + Automated Invites + Account Activation + Google Contacts

**Build:**

- Secretary approval actions: Approve / Not Approved + notes + rejection workflow (Section 5.4).
- Invite generation + email sending.
- Invite acceptance flow: create/link WordPress user, link to `wp_cd_members` record.
- Google OAuth account linking (Section 5.2 edge cases).
- Member profile completion wizard (minimum fields).
- **Google Contacts export:** Auto-create Google Contact card on member approval (Section 5.12.2).
- **Google Contacts import:** Admin "Sync from Google Contacts" with smart matching and preview screen (Section 5.12.1).
- Google Sync admin dashboard (Section 5.12.3).

**Acceptance criteria:**

- Approving an application triggers an invite automatically.
- Approving an application also creates a Google Contact card in the configured contact group.
- Invite allows account activation and directory access after linking.
- Rejection workflow sends appropriate notifications and tracks re-application history.
- Google OAuth linking works for all specified edge cases.
- Admin can sync from Google Contacts with smart duplicate detection (name + email + phone matching).
- Import preview correctly identifies exact, strong, and weak matches.

### Phase 3 — Full Directory + Profiles + Privacy Controls

**Build:**

- Directory list and search with instant type-ahead and alphabetical quick-jump (Section 16.1).
- Photo grid / yearbook view toggle (Section 16.5).
- One-tap contact actions: call, text, email, directions (Section 16.2).
- Member profile pages with household card display (Section 16.3).
- Profile edit experience with completion progress bar (Section 16.6).
- Avatar handling: Google sync, custom upload with crop tool, initials fallback (Section 7).
- Privacy toggles per field.
- New member welcome wizard and optional "New Members" highlight section (Section 16.6).
- Accessibility compliance: WCAG 2.1 AA, touch targets, screen reader support (Section 16.7).

**Acceptance criteria:**

- Members can find people quickly via search, filters, and alphabetical jump.
- One-tap contact actions work on mobile (call, text, email, directions).
- Photo view and list view both render correctly.
- Avatars resolve correctly through the priority chain.
- Privacy toggles correctly hide/show fields in the directory.
- Profile completion wizard guides new members through setup.

### Phase 4 — Households, Inheritance, and Lifecycle Management

**Build:**

- Household creation and management with defined role taxonomy (Section 5.8).
- Parent/adult management of child profiles with photo upload (Section 5.8).
- Relationship roles and display.
- Address inheritance + override.
- Spin-off workflow and child → adult_child transitions.
- Admin merge/split tools.
- Member deactivation/removal flow (Section 5.10).
- Household contact actions ("Contact the household") (Section 16.3).
- Member notification preferences (Section 16.9).

**Acceptance criteria:**

- Household organization is intuitive and supports real-life changes over time.
- Parents can add children, fill in their info, and upload profile photos.
- Child → adult_child transition and spin-off work correctly.
- Deactivation correctly hides members and handles household impact.
- All household and member lifecycle changes are audit-logged.

### Phase 5 — PWA "Appify" + Hardening

**Build:**

- PWA manifest with church logo icon and "St. Thekla Directory" branding (Section 8).
- Service worker for `/community/` scope with app shell caching.
- Android install prompt + iOS add-to-home-screen instructions.
- PWA session management — 30-day sessions, graceful re-auth (Section 8).
- Offline-friendly UX: skeleton screens, retry on failure, optimistic updates (Section 16.10).
- Hardening: rate limiting/CAPTCHA options, audit log UI, diagnostics and monitoring, caching improvements.
- Admin dashboard summary (Section 16.11).
- wp_cron task setup and diagnostics (Section 6.6).
- Birthday/name day awareness widget (optional) (Section 16.4).

**Acceptance criteria:**

- Members can install the directory to their phone — home screen shows the church logo icon labeled "St. Thekla Directory."
- PWA opens in standalone mode with church branding (splash screen, theme color).
- Session expiry is handled gracefully in PWA context (login within app frame).
- Skeleton loading screens appear instead of blank pages on slow connections.
- Admin dashboard shows system health and key metrics.
- All scheduled tasks are running and visible in diagnostics.

---

## 12. External Service Setup Instructions

This section provides step-by-step instructions for configuring the two external services required by the plugin: Google OAuth (for social login) and SMTP (for email delivery).

### 12.1 Google OAuth Setup (for "Continue with Google" Login)

Since you have Google Workspace associated with sttheklachurch.org, you can create OAuth credentials directly from your Google Cloud Console.

**Step 1: Access Google Cloud Console**

- Go to console.cloud.google.com and sign in with your sttheklachurch.org Google Workspace admin account.
- If you don't have a Google Cloud project yet, click "Select a project" → "New Project." Name it something like "St Thekla Community Directory."
- Select the project once created.

**Step 2: Enable the Google Identity API**

- In the left sidebar, go to APIs & Services → Library.
- Search for "Google Identity" or "Google+ API" (the People API is often used for profile info).
- Click Enable.

**Step 3: Configure the OAuth Consent Screen**

- Go to APIs & Services → OAuth consent screen.
- User Type: select External (this allows any Google account to log in, not just your Workspace domain).
- Fill in: App name (e.g., "St Thekla Community"), User support email, Authorized domain: sttheklachurch.org, Developer contact email.
- Scopes: add `openid`, `email`, and `profile`.
- If Google shows a "Testing" status, you can add test users initially. For production, click "Publish App" once ready. Since you're only requesting basic scopes (email/profile), Google typically does not require a full verification review.

**Step 4: Create OAuth 2.0 Credentials**

- Go to APIs & Services → Credentials.
- Click Create Credentials → OAuth client ID.
- Application type: Web application.
- Name: "Community Directory Login" (or similar).
- Authorized JavaScript origins: add `https://sttheklachurch.org`
- Authorized redirect URIs: add `https://sttheklachurch.org/wp-admin/admin-ajax.php?action=cd_google_callback` (the plugin will provide this exact URL in its settings page).
- Click Create. Google will display your Client ID and Client Secret.

**Step 5: Enter Credentials in the Plugin**

- In WordPress Admin → Community → Settings → Authentication, enter the Client ID and Client Secret.
- The plugin stores the Client Secret encrypted in the WordPress options table (see Section 10 for encryption details).
- Click "Test Google Login" to verify the connection.

> **SECURITY NOTE**
>
> The Client Secret should be treated like a password. The plugin encrypts it at rest (AES-256-CBC, see Section 10). Never commit it to version control or share it in plaintext. If compromised, regenerate it in Google Cloud Console immediately.

### 12.2 SMTP Configuration (for Email Delivery)

WordPress's default mail function often lands in spam. Since you have Google Workspace, route all email through Gmail SMTP for reliable delivery.

**Option A: WP Mail SMTP Plugin with Google Workspace (Recommended)**

1. Install and activate the "WP Mail SMTP" plugin (free version is sufficient).
2. Go to WP Mail SMTP → Settings.
3. From Email: set to a real Google Workspace address (e.g., community@sttheklachurch.org or secretary@sttheklachurch.org).
4. Mailer: select "Google / Gmail."
5. Follow the plugin's guided setup to authenticate with your Google Workspace account. This uses OAuth (separate from the login OAuth above) so you don't need to enable "Less secure app access."
6. Send a test email to verify delivery.

**Option B: Manual SMTP Configuration**

If you prefer manual SMTP settings or use a different SMTP plugin:

| Setting | Value |
|---------|-------|
| SMTP Host | smtp.gmail.com |
| SMTP Port | 587 |
| Encryption | TLS |
| Authentication | Yes |
| Username | your-address@sttheklachurch.org |
| Password | App-specific password (see below) |

To generate an app-specific password: Go to myaccount.google.com → Security → 2-Step Verification → App passwords. Generate a password for "Mail" on "Other (WordPress)". Use this password in the SMTP settings (not your regular Google password).

> **DELIVERABILITY TIP**
>
> Ensure your domain's DNS has proper SPF, DKIM, and DMARC records. Google Workspace typically configures these for you, but verify at admin.google.com → Apps → Google Workspace → Gmail → Authenticate email. This prevents your Community emails from landing in spam.

### 12.4 Google People API Setup (for Google Contacts Sync) (New in v2.1)

The Google Contacts sync feature (Section 5.12) requires access to the Google People API using the same Google Cloud project created for OAuth login. Two authentication methods are supported — choose whichever works for your organization.

**Step 1: Enable the People API**

- In Google Cloud Console → APIs & Services → Library, search for "People API."
- Click **Enable**. (If you already enabled it for OAuth profile info, this step is done.)

**Step 2: Add the Contacts Scope**

Update the OAuth consent screen to include the Contacts scope:

- Go to APIs & Services → OAuth consent screen → Edit App.
- Add the scope: `https://www.googleapis.com/auth/contacts` (read/write access to Google Contacts).
- Save.

**Step 3: Choose an Authentication Method**

#### Option A: OAuth 2.0 with Admin Consent (Recommended)

This uses the **same OAuth client** already created for login (Section 12.1). No service account or key files needed. This is the simpler approach and avoids organization policy restrictions on service account keys.

1. In the plugin settings (WP Admin → Community → Settings → Google Sync), click **"Connect Google Contacts."**
2. The plugin initiates an OAuth 2.0 authorization flow. The admin is redirected to Google and asked to sign in with the Google Workspace account that manages the church's contacts (e.g., `community@sttheklachurch.org`).
3. Google asks the admin to grant the plugin permission to **read and write contacts** on their behalf. The admin clicks "Allow."
4. Google redirects back to the plugin settings page with an authorization code.
5. The plugin exchanges the code for an **access token** and a **refresh token**.
6. The refresh token is stored **encrypted** in `wp_options` (AES-256-CBC, Section 10.4). The access token is short-lived (1 hour) and refreshed automatically.
7. The plugin shows a green "Connected" status with the authenticated email address.

**How it works at runtime:**
- When the plugin needs to access Google Contacts (sync, create contact on approval), it uses the stored refresh token to obtain a fresh access token.
- If the refresh token is revoked (e.g., admin changes Google password or revokes access in Google Account settings), the plugin shows a "Google Contacts disconnected — re-authorize" admin notice.
- The admin can click "Disconnect Google Contacts" at any time to revoke the token and clear the stored credentials.

**OAuth redirect URI:** The plugin uses the same redirect URI pattern as login:
`https://sttheklachurch.org/wp-admin/admin-ajax.php?action=cd_google_contacts_callback`
(Add this as an additional Authorized redirect URI in Google Cloud Console → Credentials → your OAuth client.)

#### Option B: Service Account with Domain-Wide Delegation

This approach uses a service account with a downloadable JSON key file. It is more suitable for automated, unattended server-to-server access, but **requires organization policy permissions** to create service account keys.

> **NOTE:** If your Google Workspace organization has the `iam.disableServiceAccountKeyCreation` policy enabled (common in organizations using Google's "Secure by Default" settings), you will not be able to create a service account key. In that case:
> - **Preferred:** Use **Option A** (OAuth with admin consent) instead — it does not require service account keys.
> - **Alternative:** An Organization Policy Administrator (`roles/orgpolicy.policyAdmin`) can temporarily disable the policy at IAM & Admin → Organization Policies → `iam.disableServiceAccountKeyCreation` → Override parent's policy → Allow All. **Re-enable the policy immediately after downloading the key.**

If you can create service account keys:

1. Go to APIs & Services → Credentials → Create Credentials → **Service Account**.
2. Name: "Community Directory Sync" (or similar).
3. Grant the service account **no project-level roles** (it only needs People API access).
4. Click Done. Then click on the service account → Keys → Add Key → Create new key → **JSON**. Download the key file.
5. In Google Workspace Admin (admin.google.com) → Security → API Controls → **Domain-wide Delegation**, add the service account's Client ID with the scope `https://www.googleapis.com/auth/contacts`.
6. Set the **impersonated user** to the Google Workspace email that owns the contacts you want to sync (e.g., `community@sttheklachurch.org`).

**Step 4: Configure in the Plugin**

- **If using Option A:** Click "Connect Google Contacts" and follow the OAuth flow. No files to upload.
- **If using Option B:** Upload the service account JSON key file and enter the impersonated email address.
- Click **"Test Connection"** to verify the plugin can read contacts from the configured Google account.

> **SECURITY NOTES**
>
> - **Option A (OAuth):** The refresh token is stored encrypted. If the admin's Google account is compromised, revoke the token at myaccount.google.com → Security → Third-party apps → Remove access for "St Thekla Community Directory."
> - **Option B (Service Account):** The key file grants access to the church's Google Contacts. Store it encrypted, never commit it to version control, and restrict access to WordPress admins only. If compromised, delete the key in Google Cloud Console and generate a new one.
> - Both options: the plugin stores credentials encrypted using AES-256-CBC (Section 10.4). Plaintext credentials exist only in PHP memory during active API calls.

### 12.3 Optional: reCAPTCHA Setup

If you enable CAPTCHA on the application form or login page:

1. Go to google.com/recaptcha and sign in with your Google account.
2. Register a new site: Label: "St Thekla Community", reCAPTCHA type: v3 (invisible, recommended) or v2 (checkbox), Domain: sttheklachurch.org.
3. Copy the Site Key and Secret Key into WordPress Admin → Community → Settings → Security.

---

## 13. Appendix: Technology Stack Summary

| Component | Technology |
|-----------|-----------|
| Hosting | Bluehost (shared/VPS WordPress hosting) |
| CMS | WordPress |
| Database | MySQL 5.7.8+ (Bluehost-provided, accessed via WordPress `$wpdb`) |
| Authentication | WordPress native (email + password) + Google OAuth 2.0 |
| Password Reset | WordPress native (`retrieve_password` / `password_reset` hooks) |
| Email | `wp_mail()` routed through Google Workspace SMTP |
| Frontend | Alpine.js for interactivity + WordPress REST API (see Section 14) |
| PWA | Web App Manifest + Service Worker (scoped to `/community/`) |
| CAPTCHA (optional) | Google reCAPTCHA v3 |
| Contacts Sync | Google People API v1 (for Google Contacts import/export) |
| External Dependencies | Google OAuth + Google People API (all member data stays on Bluehost) |

---

## 14. Frontend Architecture and API Strategy (New in v2.1)

### Frontend Framework

The plugin uses **Alpine.js** as its frontend interactivity layer.

**Rationale:**

- Lightweight (~15KB gzipped) — minimal impact on page load.
- Declarative, HTML-attribute-driven — works naturally with WordPress PHP templates (no build step required).
- Sufficient for the plugin's interactivity needs: form wizards, search/filter, card/drawer UI, modals, toggle controls.
- No Node.js build toolchain required, simplifying development and deployment on WordPress.
- Easy for WordPress PHP developers to maintain (HTML stays readable; logic is inline or in small `<script>` blocks).

**Alternatives considered:**

| Option | Reason for not selecting |
|--------|------------------------|
| Vanilla JS | Verbose for reactive UI patterns (search, filters, wizard state). Would require manual DOM management. |
| React/Preact | Requires build toolchain (webpack/vite), increases complexity for a WordPress plugin, harder for PHP-oriented maintainers. |
| Vue.js | Similar benefits to Alpine but heavier (~33KB). Alpine covers all required use cases at half the size. |
| jQuery | Already bundled with WordPress, but encourages imperative patterns. Alpine's declarative model is more maintainable for component-like UI. |

### API Communication Strategy

All frontend-to-backend communication uses the **WordPress REST API** with custom endpoints registered under a plugin namespace.

**Namespace:** `community-directory/v1`

**Endpoints:**

| Method | Endpoint | Auth Required | Description |
|--------|----------|---------------|-------------|
| `POST` | `/applications` | No (public + nonce + optional CAPTCHA) | Submit a new membership application (status: pending_verification) |
| `GET` | `/applications/verify/{token}` | No (public) | Verify applicant email address. Validates token, marks application as `new`. |
| `GET` | `/admin/registrations` | Admin | List all applications including `pending_verification` (Registrations view) |
| `POST` | `/admin/registrations/{id}/resend-verification` | Admin | Resend verification email for a `pending_verification` application |
| `POST` | `/directory/search` | Member | Search/list directory members (paginated). Uses POST to keep search terms out of URLs/server logs (PII protection — Section 10.2.1) |
| `GET` | `/members/{uuid}` | Member | Get a member's profile (respects privacy settings). Uses opaque UUID, not auto-increment ID (Section 10.2.1) |
| `PUT` | `/members/{uuid}` | Member (own) or Admin | Update a member's profile |
| `GET` | `/households/{id}` | Member | Get household details and members |
| `PUT` | `/households/{id}` | Head/Spouse or Admin | Update household details |
| `GET` | `/admin/applications` | Secretary/Admin | List applications with filters |
| `PUT` | `/admin/applications/{id}` | Secretary/Admin | Approve/reject an application |
| `POST` | `/admin/invites/{id}/resend` | Secretary/Admin | Resend an invite |
| `POST` | `/auth/google` | No | Handle Google OAuth callback |
| `POST` | `/households/{id}/members` | Head/Spouse or Admin | Add a child/family member to household |
| `PUT` | `/households/{id}/members/{mid}` | Head/Spouse or Admin | Update a child's profile within household |
| `POST` | `/members/{id}/avatar` | Member (own) or Head/Spouse (child) or Admin | Upload/replace a profile photo |
| `DELETE` | `/members/{id}/avatar` | Member (own) or Head/Spouse (child) or Admin | Remove a profile photo (reverts to fallback) |
| `GET` | `/members/{uuid}/vcard` | Member | Generate and download a vCard (.vcf) for the member (respects privacy settings) |
| `GET` | `/households/{id}/vcard` | Member | Generate a combined vCard for all visible adults in a household |
| `POST` | `/admin/google-sync/import` | Admin | Trigger Google Contacts import sync |
| `GET` | `/admin/google-sync/preview` | Admin | Preview import results (matches, new, duplicates) |
| `POST` | `/admin/google-sync/confirm` | Admin | Confirm and execute reviewed import |
| `GET` | `/admin/google-sync/status` | Admin | Get sync connection status and history |
| `GET` | `/officers/applications` | Church Officer | List pending/recent applications (front-end Member Administration tab) |
| `GET` | `/officers/applications/{id}` | Church Officer | View full application details |
| `PUT` | `/officers/applications/{id}` | Church Officer | Approve/reject an application (same workflow as Secretary) |
| `POST` | `/officers/applications/{id}/notes` | Church Officer | Add internal notes to an application |
| `GET` | `/admin/officers` | Admin | List current and past officers |
| `POST` | `/admin/officers` | Admin | Add a member to the Officers Group |
| `DELETE` | `/admin/officers/{id}` | Admin | Remove a member from the Officers Group |
| `POST` | `/admin/officers/rotate` | Admin | Annual rotation: clear current officers |
| `POST` | `/admin/import` | Admin | Upload CSV or connect Google Sheets for member import |
| `GET` | `/admin/import/preview` | Admin | Preview import results with deduplication analysis |
| `POST` | `/admin/import/execute` | Admin | Execute reviewed import |
| `POST` | `/admin/members/merge` | Admin | Merge two duplicate member profiles |
| `GET` | `/admin/whatsapp-groups` | Admin | List configured WhatsApp groups |
| `POST` | `/admin/whatsapp-groups` | Admin | Add a WhatsApp group link |
| `PUT` | `/admin/whatsapp-groups/{id}` | Admin | Update a WhatsApp group |
| `DELETE` | `/admin/whatsapp-groups/{id}` | Admin | Remove a WhatsApp group |
| `GET` | `/whatsapp-groups` | Member | List WhatsApp groups visible to the member |
| `GET` | `/admin/deletion-requests` | Admin/Officer | List pending deletion requests |
| `POST` | `/admin/deletion-requests/{id}/acknowledge` | Admin/Officer | Acknowledge and process a deletion request |
| `POST` | `/members/me/deactivate` | Member (own) | Self-service deactivation |
| `POST` | `/members/me/reactivate` | Member (own, self_deactivated only) | Self-service reactivation |
| `POST` | `/members/me/request-deletion` | Member (own) | Request account deletion |
| `POST` | `/households/{id}/requests` | Head/Spouse/Adult Child | Initiate a household change request |
| `GET` | `/admin/household-requests` | Admin | List pending household change requests |
| `PUT` | `/admin/household-requests/{id}` | Admin | Approve/deny a household change request |
| `POST` | `/push/subscribe` | Member | Register a push notification subscription |
| `DELETE` | `/push/subscribe` | Member | Unregister push subscription |
| `GET` | `/admin/reports/{type}` | Admin/Officer | Get report data (membership, pipeline, engagement, etc.) |
| `GET` | `/auth/email-lookup` | No (public, rate-limited) | "Can't remember your email?" lookup by name + phone |

**Security:**

- All endpoints use WordPress's `permission_callback` for authorization checks.
- Public endpoints (application submission) require a WordPress nonce passed via `X-WP-Nonce` header.
- Member endpoints verify the requesting user has the `cd_member` capability.
- Admin endpoints verify `cd_secretary` or `cd_admin` capabilities.
- Officer endpoints (`/officers/*`) verify `cd_officer` capability (assigned when added to Officers Group, removed on rotation).
- All input is sanitized using `sanitize_text_field()`, `sanitize_email()`, etc.
- All database queries use `$wpdb->prepare()`.

**Response format:**

All endpoints return JSON with a consistent envelope:

```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 142
  }
}
```

Error responses:

```json
{
  "success": false,
  "error": {
    "code": "rate_limited",
    "message": "Too many attempts. Please try again in 15 minutes."
  }
}
```

---

## 15. Hosting and MySQL Requirements (New in v2.1)

### MySQL Version

The plugin requires **MySQL 5.7.8 or later** (or MariaDB 10.2.7+) for native JSON column support.

**Verification during Phase 0:**

1. Log in to Bluehost cPanel.
2. Go to phpMyAdmin → click on any database → check the server version displayed at the top.
3. Alternatively, run the query: `SELECT VERSION();`

**Fallback if MySQL < 5.7.8:**

If the hosting environment does not support JSON columns, the plugin's installation routine will:
1. Detect the MySQL version.
2. Automatically use `LONGTEXT` instead of `JSON` for the affected columns.
3. Application-level JSON handling (`json_encode()` / `json_decode()`) will be used in all cases regardless, so functionality is unaffected.
4. An admin notice will recommend upgrading MySQL for better query performance on JSON fields.

### PHP Version

The plugin requires **PHP 7.4 or later** (for typed properties, arrow functions, and `openssl_encrypt` support). PHP 8.0+ is recommended.

### WordPress Version

The plugin requires **WordPress 5.9 or later** (for full REST API support, block patterns, and modern admin UI components).

### Bluehost-Specific Notes

- **File upload limits:** Bluehost's default PHP `upload_max_filesize` is typically 64MB (more than sufficient for 2MB avatar uploads). If lower, it can be adjusted via cPanel → MultiPHP INI Editor.
- **Cron jobs:** Configure a real cron job via cPanel → Cron Jobs to run `wget -q -O /dev/null https://sttheklachurch.org/wp-cron.php` every 15 minutes (see Section 6.6).
- **SSL:** Bluehost includes free SSL via Let's Encrypt. Ensure it's active — all Community pages and API endpoints must run over HTTPS.

---

## 16. Recommended UX Enhancements for Directory Experience (New in v2.1)

The following requirements are recommended to ensure the directory provides a genuinely positive, useful experience that members will actually use. These address common friction points in church directories and are designed to help members find the information they need quickly.

### 16.1 Smart Search and Discovery

**Instant search with type-ahead:**
- As a member types in the directory search bar, results filter in real-time (debounced, 300ms delay).
- Search matches against: first name, last name, household name, phone number, and email (if privacy allows).
- Results are ranked by relevance: exact match first, then starts-with, then contains.
- Recent searches are remembered (stored locally in browser/PWA) for quick re-access.

**Alphabetical quick-jump:**
- An A–Z sidebar (desktop) or horizontal letter bar (mobile) allows members to jump directly to a letter.
- Letters with no matching members are shown greyed out.

**Filter by ministry/group:**
- Members can filter the directory by ministry involvement (e.g., "Youth Ministry," "Choir," "Sunday School Teachers").
- This helps members find who to contact for a specific ministry question.

### 16.2 One-Tap Contact Actions and Native OS Integration

When viewing a member's profile, contact actions should be immediately accessible and trigger native OS behaviors — especially critical in the PWA standalone mode where the app feels like a native app.

**Contact action buttons:**

| Action | Mobile (PWA & Browser) | Desktop |
|--------|----------------------|---------|
| **Call** | `tel:` link → opens native phone dialer | Click-to-copy phone number with toast confirmation |
| **Text** | `sms:` link → opens native SMS/Messages app with number pre-filled | Click-to-copy phone number |
| **Email** | `mailto:` link → opens default email client (Gmail, Outlook, Apple Mail, etc.) with "To" pre-filled | `mailto:` link → opens default email client |
| **Directions** | `geo:` URI or Maps deep link → opens Google Maps (Android) / Apple Maps (iOS) with member's address | Opens Google Maps in new tab |

**Implementation requirements:**

- All phone numbers rendered as `<a href="tel:+1XXXXXXXXXX">` links so the OS intercepts them for the native dialer. The `+1` country code prefix is added automatically for US numbers.
- All email addresses rendered as `<a href="mailto:user@example.com">` links so the OS opens the default mail client.
- SMS links use `<a href="sms:+1XXXXXXXXXX">` (no body text pre-filled — just the number).
- Directions use `<a href="https://maps.google.com/?q=ADDRESS">` which both Android and iOS intercept to open their respective native maps app.
- In **PWA standalone mode**, these links open the respective native app (dialer, SMS, email, maps) and return to the directory app when the user is done. The PWA does not navigate away — the OS handles the handoff.
- These action buttons appear as a row of circular icon buttons (phone, message, envelope, map pin) below the member's name on the profile card.
- Actions only appear for fields the member has made visible (respects privacy toggles).
- When a member has multiple phone numbers or emails, tapping the action icon shows a quick picker to select which number/email to use.

#### 16.2.1 Save to Device Contacts

At the bottom of a member's profile view (mobile and PWA), a prominent button allows the user to save the member as a contact on their device:

**Button:** "Save to Contacts" with a contact-card icon (visible on both mobile and desktop, but most useful on mobile).

**How it works:**

1. User taps "Save to Contacts" on a member's profile.
2. The system generates a **vCard (.vcf) file** on the fly containing all of the member's visible contact information:
   - Full name (first + last)
   - All visible phone numbers with labels (CELL, HOME, WORK)
   - All visible email addresses with labels
   - Home address (if visible)
   - Organization: "St. Thekla Church"
   - Photo: the member's profile thumbnail (embedded in the vCard as base64)
   - Note: "St. Thekla Community Directory"
3. The vCard is delivered as a file download (`Content-Type: text/vcard`).
4. **Native OS behavior:**
   - **iOS (Safari/PWA):** iOS automatically opens the Contacts app with a "Create New Contact" or "Add to Existing Contact" prompt pre-filled with the vCard data.
   - **Android (Chrome/PWA):** Android opens the Contacts app with the same pre-filled prompt.
   - **Desktop:** Downloads the `.vcf` file which can be opened in Outlook, Apple Contacts, Google Contacts, etc.
5. **Privacy:** The vCard only includes fields the member has made visible in their privacy settings. Hidden fields are omitted entirely — they are never included in the vCard even at the data level.

**API endpoint:** `GET /members/{uuid}/vcard` (Member auth required). Returns a `.vcf` file. The endpoint respects the viewed member's privacy settings and only includes visible fields.

**Household shortcut:** On the household view, a "Save All" button generates a single vCard file containing all visible adult members of the household.

### 16.3 Household View

**Family card display:**
- When viewing a member's profile, their household is shown as a visual group: all family members displayed as small avatar cards with names and roles.
- Tapping any family member navigates to their profile.
- The shared household address is displayed once (not repeated per member).

**"Contact the household" action:**
- A single action to email or text all adults in a household (useful for event invitations, coordination).

### 16.4 Birthday and Name Day Awareness

**Upcoming birthdays/name days (optional, admin-configurable):**
- A small "Upcoming Celebrations" widget on the directory home page showing members with birthdays or name days in the next 7 days.
- Only shows for members who have opted in to sharing their birthday.
- For Orthodox churches: an optional "Name Day" field tied to the patron saint calendar. Admin can upload or configure the name day list.

### 16.5 Member Photo Directory View

**Photo grid / yearbook view:**
- An alternative directory view showing member photos in a grid layout (like a yearbook).
- Each cell shows: avatar, first name, last name.
- Tapping a photo opens the full profile.
- This view makes the directory feel personal and helps newer members put faces to names.
- Toggle between "List view" and "Photo view" with a simple switch.

### 16.6 New Member Welcome Experience

**Profile completion encouragement:**
- After first login, the member sees a friendly "Welcome to the St. Thekla Community Directory!" message with a guided profile completion wizard.
- Progress bar shows how complete their profile is (e.g., "Your profile is 60% complete — add a photo to help others recognize you!").
- Gentle, non-intrusive nudges on subsequent logins if profile is incomplete (dismissable, max 3 times).

**"New Members" section (optional):**
- Admin can enable a "New Members" highlight section on the directory landing page.
- Shows recently activated members (last 30 days) with their photo and name.
- Helps the congregation welcome and recognize newcomers.
- Members can opt out of being shown in this section.

### 16.7 Accessibility and Inclusivity

**Baseline requirements:**
- All interactive elements meet WCAG 2.1 AA standards.
- Minimum touch target size: 44x44px on mobile.
- Color contrast ratios meet accessibility guidelines.
- Screen reader support: proper ARIA labels on all interactive elements, avatar images include alt text with member names.
- Keyboard navigation support for all directory views and forms.
- Font size respects system/browser zoom settings (use relative units, not fixed px for text).

**User-Selectable Accessibility Controls (New in v2.1):**

A small **"Aa"** accessibility icon in the directory header (always visible, no navigation required) opens a quick-settings panel:

| Control | Options | Behavior |
|---------|---------|----------|
| **Text size** | Normal / Large (+25%) / Extra Large (+50%) | Adjusts base font size via a CSS custom property on the root element. Persisted in the member's profile (or `localStorage` for pre-login). |
| **High-contrast mode** | Toggle on/off | Switches to a high-contrast color scheme: pure black text on white background, no subtle grays, bolder borders, underlined links. Uses a CSS class on the root element that overrides theme color variables. |
| **Reduced motion** | Toggle on/off | Disables all animations and transitions. Respects `prefers-reduced-motion` OS setting by default, but also provides a manual toggle for users who don't know how to change their OS setting. |

These settings are also accessible from **Profile Settings → Accessibility.**

**Privacy Presets (New in v2.1):**

Instead of requiring members to configure 15+ individual privacy toggles, the profile edit page offers three simple presets:

| Preset | What's Visible |
|--------|---------------|
| **Share Everything** | All profile fields visible to directory members |
| **Share Basics** (default) | Name, primary phone, primary email visible. Everything else hidden. |
| **Maximum Privacy** | Only name and profile photo visible. All contact details hidden. |

- Presets set all privacy toggles at once with a single click.
- After selecting a preset, the member can **customize individual fields** if they want more granular control.
- The preset selection is shown prominently at the top of the privacy settings page, above the per-field toggles.
- A "What will others see?" preview shows how the member's profile appears to other directory members with the current settings.

### 16.8 Quick Profile Sharing

**Share your profile link:**
- Each member has a unique, shareable profile URL (within the authenticated directory).
- A "Share my profile" button uses the **Web Share API** (see Section 8 — Native Share Sheet) to open the OS share sheet on mobile (WhatsApp, Messages, email, AirDrop, etc.). Falls back to clipboard copy on desktop.
- Useful for: "Hey, look me up in the directory" conversations.

### 16.9 Notification Preferences for Members

Members can configure their own notification preferences from **Profile Settings → Notifications.** Each notification type can be set to: Email only, Push only, Both, or Off.

| Notification | Default | Configurable | Push Eligible |
|-------------|---------|--------------|--------------|
| Welcome email on activation | On (email) | No (always sent) | No |
| Password reset confirmation | On (email) | No (always sent) | No |
| Email verification | On (email) | No (always sent) | No |
| Household changes (member added/removed) | On (both) | Yes | Yes |
| Directory-wide announcements | On (both) | Yes | Yes |
| Birthday/name day reminders | Off | Yes (opt-in) | Yes |
| New application received (Officers only) | On (both) | Yes | Yes |
| Deletion request received (Officers only) | On (both) | Yes | Yes |
| Child turning 18 prompt | On (email) | Yes | Yes |

### 16.10 Offline-Friendly UX (PWA)

While directory data is not cached offline for security (Section 8), the UX should handle poor connectivity gracefully:

- **Loading states:** Skeleton screens (placeholder cards) while data loads, rather than blank screens or spinners.
- **Retry on failure:** If an API call fails due to network issues, show a clear "Couldn't load. Tap to retry" message instead of a generic error.
- **Optimistic updates:** When a member saves a profile change, show the update immediately in the UI while the API call processes in the background. If it fails, revert and notify.

### 16.11 Admin Dashboard Summary

The admin landing page for the Community plugin should show a quick-glance dashboard:

| Widget | Description |
|--------|-------------|
| **Total Members** | Count of active members |
| **Pending Applications** | Count of applications with status = New (click to go to queue) |
| **Pending Verifications** | Count of unverified applications (click to go to Registrations) |
| **Deletion Requests** | Count of pending deletion requests awaiting leadership acknowledgment |
| **Recent Activity** | Last 10 audit log entries (condensed view) |
| **Households** | Total active households |
| **Incomplete Profiles** | Count of members with < 80% profile completion |
| **System Health** | Green/yellow/red indicators for: database, email, Google OAuth, push notifications, cron jobs |

### 16.12 Bulk Member Operations (New in v2.1)

**WP Admin → Community → Members** — available to WP Administrators only.

**Bulk selection:**
- Checkbox on each member row.
- "Select All on Page" and "Select All Matching Filter" options.
- Count indicator: "12 members selected."

**Bulk actions dropdown:**

| Action | Description |
|--------|-------------|
| **Bulk deactivate** | Deactivate all selected members with a shared reason. Confirmation dialog shows all affected names. |
| **Bulk reactivate** | Reactivate all selected inactive members. |
| **Bulk add ministry tag** | Add a ministry tag to all selected members. Select from admin-configured list. |
| **Bulk remove ministry tag** | Remove a ministry tag from all selected members. |
| **Bulk send invite** | Send invite emails to all selected `pending_profile` members (from imports). |
| **Bulk resend verification** | Resend verification emails to selected `pending_verification` applications. |

- Each bulk action requires a confirmation dialog showing the action and affected member count.
- All bulk actions are audit-logged as a single event with the list of affected member IDs.
- Bulk actions process in the background with a progress indicator if more than 20 members are affected.

### 16.13 Reporting and Analytics (New in v2.1)

**WP Admin → Community → Reports** — available to WP Administrators and Church Officers.

| Report | Description |
|--------|-------------|
| **Membership Overview** | Total active, self-deactivated, inactive, archived members. Growth chart: new members per month over last 12 months. Net change (activations minus deactivations) per month. |
| **Application Pipeline** | Applications submitted, verified, approved, rejected per month. Average days from submission to verification. Average days from verification to approval. Approval rate percentage. |
| **Member Engagement** | Members who logged in within last 30/60/90 days. Members who have **never** logged in since activation (clickable list). Members with incomplete profiles and what's missing (clickable list). |
| **Ministry Participation** | Member count per ministry tag, displayed as a bar chart or sortable table. |
| **Household Statistics** | Total households, average household size, households with children, single-member households. |
| **Officer Activity** | Per-officer: total actions (approvals, rejections, notes added), last login to directory, last action date. Queue metrics: average review time, current queue age (oldest unreviewed application). |
| **Geographic Distribution** | Member count by city or ZIP code (uses unencrypted city/ZIP from address). Displayed as a sortable table. |

**Features:**
- Date range filter on all reports (default: last 12 months).
- All report tables are exportable to CSV (Admin only — audit-logged per Section 10.2.8).
- Church Officers see a simplified view (membership overview, application pipeline, officer activity). They do **not** see engagement, geographic, or export capabilities.

### 16.14 Email Template Preview and Testing (New in v2.1)

**WP Admin → Community → Settings → Email Templates** — available to WP Administrators and Church Officers.

| Feature | Description |
|---------|-------------|
| **Template list** | All email types (verification, invite, approval, rejection, password reset, officer notification, deletion confirmation, officer rotation) with current status (default / customized). |
| **Preview** | Click "Preview" on any template → renders in a modal/iframe with sample placeholder data (e.g., "John Smith," "john@example.com," "January 15, 2026"). Shows exactly what the recipient will see. |
| **Send Test** | Click "Send Test" → sends the rendered email to the current admin's email address. Confirmation toast: "Test email sent to [your email]." |
| **Edit Template** | Opens an editor with the template content. Available placeholder tokens are listed in a sidebar (e.g., `{{member_name}}`, `{{church_name}}`, `{{verification_link}}`, `{{approval_date}}`). |
| **Reset to Default** | Restores the factory template for any customized email. Confirmation required. |

Church Officers can preview and send test emails but **cannot** edit templates (Admin only).

### 16.15 Undo / Grace Period for Consequential Actions (New in v2.1)

For actions that are hard to reverse, the system provides a **60-second grace period** with an undo option.

| Action | Grace Period Behavior |
|--------|----------------------|
| **Approve application** | "Application approved. Invite will be sent in 60 seconds. [Undo]" — The invite email and Google Contact creation are queued with a 60-second delay. If the admin clicks "Undo" within 60 seconds, the approval is reversed and no email is sent. |
| **Deactivate member** | "Member deactivated. [Undo within 60 seconds]" — Profile is hidden immediately but the status change is reversible within the grace period. |
| **Officer rotation** | "Officers rotated. [Undo within 60 seconds]" — Previous officers' roles are marked for removal but not committed until the grace period expires. |
| **Process deletion** | "Deletion processing. Data will be permanently removed in 60 seconds. [Undo]" — Data deletion is queued, not immediate. |

**UX:**
- A **countdown bar** is shown at the top of the admin page, clearly showing time remaining and a prominent "Undo" button.
- The countdown is visible and unmissable — animated progress bar counting down from 60 to 0.
- After 60 seconds: the action is committed, the bar disappears, and a confirmation message shows "Action completed."
- If the admin navigates away before the grace period expires: the action is committed (navigating away = implicit confirmation).

---

*Document version: 2.1 — February 2026*
*Previous version: 2.0 — February 2026*
