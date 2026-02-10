/**
 * Community Directory — Alpine.js Application Logic
 *
 * All front-end interactivity powered by Alpine.js.
 * Talks to the WordPress REST API via cdConfig (localized by PHP).
 */

/* ─── API Helper ─── */
const cdApi = {
    /**
     * Make an API request to the Community Directory REST endpoints.
     */
    async request(endpoint, options = {}) {
        const url = cdConfig.apiUrl + endpoint;
        const headers = {
            'Content-Type': 'application/json',
            'X-WP-Nonce': cdConfig.nonce,
        };

        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                ...headers,
                ...(options.headers || {}),
            },
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || data.data?.message || 'An error occurred.');
        }

        return data;
    },

    get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },

    post(endpoint, body) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(body),
        });
    },

    put(endpoint, body) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(body),
        });
    },
};

/* ─── Initialize Alpine Components ─── */
document.addEventListener('alpine:init', () => {

    /* ─── Login Page ─── */
    Alpine.data('cdLogin', () => ({
        email: '',
        password: '',
        loading: false,
        errorMessage: '',
        successMessage: '',
        showForgotPassword: false,
        showForgotEmail: false,
        showResetConfirm: false,
        resetEmail: '',
        resetSent: false,
        resetToken: '',
        newPassword: '',
        newPasswordConfirm: '',
        resetConfirmed: false,
        lookupName: '',
        lookupPhone: '',
        emailLookupSent: false,

        init() {
            const params = new URLSearchParams(window.location.search);

            // Handle password reset token from email link
            const resetToken = params.get('reset_token');
            if (resetToken) {
                this.resetToken = resetToken;
                this.showResetConfirm = true;
            }

            // Handle Google OAuth errors
            const error = params.get('error');
            if (error) {
                const errorMessages = {
                    'no_account': 'No directory account is associated with this Google account. Please log in with email/password or apply for membership.',
                    'invalid_state': 'Google sign-in session expired. Please try again.',
                    'token_exchange_failed': 'Could not connect to Google. Please try again.',
                };
                this.errorMessage = errorMessages[error] || 'Sign-in error: ' + error;
            }

            // Handle logged_out message
            if (params.get('logged_out')) {
                this.successMessage = 'You have been logged out.';
            }
        },

        async loginWithEmail() {
            this.errorMessage = '';
            this.successMessage = '';

            if (!this.email || !this.password) {
                this.errorMessage = 'Please enter your email and password.';
                return;
            }

            this.loading = true;
            try {
                const result = await cdApi.post('/auth/login', {
                    email: this.email,
                    password: this.password,
                });

                // Redirect to directory on success
                if (result.data && result.data.redirect) {
                    window.location.href = result.data.redirect;
                } else {
                    window.location.href = cdConfig.baseUrl + '/directory/';
                }
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
            }
        },

        async loginWithGoogle() {
            console.log('CD Debug: loginWithGoogle clicked');
            this.errorMessage = '';
            this.loading = true;

            try {
                console.log('CD Debug: Requesting auth URL from /auth/google');
                const result = await cdApi.get('/auth/google');
                console.log('CD Debug: API Response', result);

                if (result.data && result.data.auth_url) {
                    console.log('CD Debug: Redirecting to', result.data.auth_url);
                    window.location.href = result.data.auth_url;
                } else {
                    console.error('CD Debug: No auth_url in response');
                    this.errorMessage = 'Configuration error: No Auth URL returned.';
                    this.loading = false;
                }
            } catch (err) {
                console.error('CD Debug: API Error', err);
                this.errorMessage = err.message;
                this.loading = false;
            }
        },

        async requestPasswordReset() {
            this.loading = true;
            try {
                await cdApi.post('/auth/password-reset', {
                    email: this.resetEmail,
                });
            } catch (err) {
                // Don't reveal whether the email exists
            } finally {
                this.resetSent = true;
                this.loading = false;
            }
        },

        async confirmPasswordReset() {
            this.errorMessage = '';

            if (!this.newPassword || this.newPassword.length < 8) {
                this.errorMessage = 'Password must be at least 8 characters.';
                return;
            }
            if (this.newPassword !== this.newPasswordConfirm) {
                this.errorMessage = 'Passwords do not match.';
                return;
            }

            this.loading = true;
            try {
                await cdApi.post('/auth/password-reset/confirm', {
                    token: this.resetToken,
                    password: this.newPassword,
                });
                this.resetConfirmed = true;
                this.showResetConfirm = false;
                this.successMessage = 'Your password has been reset. You can now log in.';
                // Clean up URL
                window.history.replaceState({}, '', window.location.pathname);
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
            }
        },

        async lookupEmail() {
            this.loading = true;
            try {
                await cdApi.post('/auth/email-lookup', {
                    name: this.lookupName,
                    phone: this.lookupPhone,
                });
            } catch (err) {
                // Don't reveal whether match was found
            } finally {
                this.emailLookupSent = true;
                this.loading = false;
            }
        },
    }));

    /* ─── Application Form ─── */
    Alpine.data('cdApplication', () => ({
        step: 0,
        steps: ['Personal Info', 'Address & Background', 'Family', 'Interests & Review'],
        loading: false,
        submitted: false,
        errorMessage: '',
        hasSpouse: false,
        agreedToTerms: false,
        form: {
            first_name: '',
            middle_initial: '',
            last_name: '',
            email: '',
            phone: '',
            address_line_1: '',
            city: '',
            state: '',
            zip: '',
            date_of_birth: '',
            date_of_baptism: '',
            profession: '',
            prior_parishes: '',
            marital_status: '',
            date_of_marriage: '',
            marriage_registered_at: '',
            spouse_first_name: '',
            spouse_middle_initial: '',
            spouse_last_name: '',
            spouse_email: '',
            spouse_phone: '',
            spouse_date_of_birth: '',
            spouse_date_of_baptism: '',
            spouse_relationship: '',
            children: [],
            ministry_interests: [],
            ministry_other: '',
        },

        nextStep() {
            this.errorMessage = '';

            // Validate current step
            if (this.step === 0) {
                if (!this.form.first_name || !this.form.last_name) {
                    this.errorMessage = 'Please enter your first and last name.';
                    return;
                }
                if (!this.form.email || !this.isValidEmail(this.form.email)) {
                    this.errorMessage = 'Please enter a valid email address.';
                    return;
                }
                if (!this.form.phone) {
                    this.errorMessage = 'Please enter your phone number.';
                    return;
                }
            }

            if (this.step < this.steps.length - 1) {
                this.step++;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        prevStep() {
            if (this.step > 0) {
                this.step--;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        addChild() {
            this.form.children.push({
                first_name: '',
                middle_initial: '',
                last_name: '',
                relationship: '',
                date_of_birth: '',
                date_of_baptism: '',
                email: '',
            });
        },

        removeChild(index) {
            this.form.children.splice(index, 1);
        },

        toggleMinistry(value) {
            const idx = this.form.ministry_interests.indexOf(value);
            if (idx === -1) {
                this.form.ministry_interests.push(value);
            } else {
                this.form.ministry_interests.splice(idx, 1);
            }
        },

        isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },

        async submitApplication() {
            if (!this.agreedToTerms) {
                this.errorMessage = 'Please agree to the terms to continue.';
                return;
            }

            this.errorMessage = '';
            this.loading = true;

            // Build form_data (additional details beyond the core fields)
            const formData = {};
            if (this.form.middle_initial) formData.middle_initial = this.form.middle_initial;
            if (this.form.address_line_1) formData.address_line_1 = this.form.address_line_1;
            if (this.form.city) formData.city = this.form.city;
            if (this.form.state) formData.state = this.form.state;
            if (this.form.zip) formData.zip = this.form.zip;
            if (this.form.date_of_birth) formData.date_of_birth = this.form.date_of_birth;
            if (this.form.date_of_baptism) formData.date_of_baptism = this.form.date_of_baptism;
            if (this.form.profession) formData.profession = this.form.profession;
            if (this.form.prior_parishes) formData.prior_parishes = this.form.prior_parishes;
            if (this.form.marital_status) formData.marital_status = this.form.marital_status;
            if (this.form.date_of_marriage) formData.date_of_marriage = this.form.date_of_marriage;
            if (this.form.marriage_registered_at) formData.marriage_registered_at = this.form.marriage_registered_at;
            if (this.hasSpouse && this.form.spouse_first_name) {
                formData.spouse = {
                    first_name: this.form.spouse_first_name,
                    middle_initial: this.form.spouse_middle_initial,
                    last_name: this.form.spouse_last_name,
                    email: this.form.spouse_email,
                    phone: this.form.spouse_phone,
                    relationship: this.form.spouse_relationship,
                    date_of_birth: this.form.spouse_date_of_birth,
                    date_of_baptism: this.form.spouse_date_of_baptism,
                };
            }
            if (this.form.children.length > 0) {
                formData.children = this.form.children.filter(c => c.first_name);
            }
            if (this.form.ministry_interests.length > 0) {
                formData.ministry_interests = this.form.ministry_interests;
            }
            if (this.form.ministry_other) formData.ministry_other = this.form.ministry_other;

            try {
                await cdApi.post('/applications', {
                    first_name: this.form.first_name,
                    last_name: this.form.last_name,
                    email: this.form.email,
                    phone: this.form.phone,
                    form_data: Object.keys(formData).length > 0 ? formData : undefined,
                });

                this.submitted = true;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
            }
        },
    }));

    /* ─── Email Verification ─── */
    Alpine.data('cdVerify', () => ({
        loading: true,
        success: false,
        errorMessage: '',

        async init() {
            const token = window.cdVerifyToken;
            if (!token) {
                this.loading = false;
                this.errorMessage = 'No verification token provided.';
                return;
            }

            try {
                await cdApi.get('/applications/verify/' + encodeURIComponent(token));
                this.success = true;
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
            }
        },
    }));

    /* ─── Invite Acceptance ─── */
    Alpine.data('cdInvite', () => ({
        loading: true,
        tokenValid: false,
        success: false,
        errorMessage: '',
        applicantName: '',
        email: '',
        password: '',
        passwordConfirm: '',
        creating: false,

        async init() {
            // Read base64-encoded email from URL path
            const encodedEmail = window.cdInviteEmail;
            if (!encodedEmail) {
                this.loading = false;
                this.errorMessage = 'Invalid invitation link.';
                return;
            }

            try {
                this.email = atob(encodedEmail);
            } catch (e) {
                this.loading = false;
                this.errorMessage = 'Invalid invitation link.';
                return;
            }

            // Get token from URL query params
            const params = new URLSearchParams(window.location.search);
            const token = params.get('token');
            if (!token) {
                this.loading = false;
                this.errorMessage = 'No invitation token provided.';
                return;
            }

            // Validate the invite token
            try {
                const result = await cdApi.get(
                    '/invites/validate?token=' + encodeURIComponent(token) +
                    '&email=' + encodeURIComponent(this.email)
                );
                this.applicantName = result.data.name || '';
                this.tokenValid = true;
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
            }
        },

        async createAccount() {
            this.errorMessage = '';

            if (!this.password || this.password.length < 8) {
                this.errorMessage = 'Password must be at least 8 characters.';
                return;
            }
            if (this.password !== this.passwordConfirm) {
                this.errorMessage = 'Passwords do not match.';
                return;
            }

            this.creating = true;

            const params = new URLSearchParams(window.location.search);
            const token = params.get('token');

            try {
                const result = await cdApi.post('/invites/accept', {
                    token: token,
                    email: this.email,
                    password: this.password,
                });

                this.success = true;
                // Redirect to profile edit for completion (or directory as fallback)
                const redirectUrl = (result.data && result.data.redirect)
                    ? result.data.redirect
                    : cdConfig.baseUrl + '/profile/edit/';
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 2000);
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.creating = false;
            }
        },
    }));

    /* ─── Directory (Phase 3 stub) ─── */
    Alpine.data('cdDirectory', () => ({
        searchQuery: '',
        members: [],
        loading: false,
        page: 1,
        perPage: 24,
        totalPages: 1,
        totalMembers: 0,

        init() {
            this.loadMembers();
        },

        async loadMembers() {
            this.loading = true;
            try {
                let url = '/directory?page=' + this.page + '&per_page=' + this.perPage;
                if (this.searchQuery) {
                    url += '&search=' + encodeURIComponent(this.searchQuery);
                }

                const result = await cdApi.get(url);
                this.members = result.data.members || [];

                if (result.meta) {
                    this.totalPages = result.meta.pages;
                    this.totalMembers = result.meta.total;
                }
            } catch (err) {
                console.error('Directory load error:', err);
                this.members = [];
            } finally {
                this.loading = false;
            }
        },

        search() {
            this.page = 1;
            this.loadMembers();
        },

        nextPage() {
            if (this.page < this.totalPages) {
                this.page++;
                this.loadMembers();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        prevPage() {
            if (this.page > 1) {
                this.page--;
                this.loadMembers();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        logout() {
            window.location.href = cdConfig.logoutUrl || (cdConfig.baseUrl + '/login/?logged_out=1');
        },

        // Helper for avatar background color
        getAvatarColor(name) {
            const colors = ['#d32f2f', '#c2185b', '#7b1fa2', '#512da8', '#303f9f', '#1976d2', '#0288d1', '#0097a7', '#00796b', '#388e3c', '#afb42b', '#fbc02d', '#ffa000', '#f57c00', '#e64a19', '#5d4037', '#616161'];
            let hash = 0;
            for (let i = 0; i < name.length; i++) {
                hash = name.charCodeAt(i) + ((hash << 5) - hash);
            }
            return colors[Math.abs(hash) % colors.length];
        },

        getInitials(first, last) {
            return (first.charAt(0) + last.charAt(0)).toUpperCase();
        }
    }));

    /* ─── Member Profile View ─── */
    Alpine.data('cdMemberProfile', () => ({
        member: null,
        loading: true,
        errorMessage: '',
        isOwnProfile: false,

        async init() {
            const uuid = window.cdMemberUuid;
            if (!uuid) {
                this.errorMessage = 'No member specified.';
                this.loading = false;
                return;
            }

            try {
                const result = await cdApi.get('/members/' + uuid);
                this.member = result.data.member;
                this.isOwnProfile = result.data.is_own_profile || false;
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
            }
        },

        getAvatarColor(name) {
            const colors = ['#d32f2f', '#c2185b', '#7b1fa2', '#512da8', '#303f9f', '#1976d2', '#0288d1', '#0097a7', '#00796b', '#388e3c', '#afb42b', '#fbc02d', '#ffa000', '#f57c00', '#e64a19', '#5d4037', '#616161'];
            let hash = 0;
            for (let i = 0; i < name.length; i++) {
                hash = name.charCodeAt(i) + ((hash << 5) - hash);
            }
            return colors[Math.abs(hash) % colors.length];
        },

        getInitials(first, last) {
            return ((first || '').charAt(0) + (last || '').charAt(0)).toUpperCase();
        },

        formatPhone(value) {
            if (!value) return '';
            const digits = value.replace(/\D/g, '');
            if (digits.length === 10) {
                return '(' + digits.slice(0,3) + ') ' + digits.slice(3,6) + '-' + digits.slice(6);
            }
            return value;
        },
    }));

    /* ─── Edit Profile ─── */
    Alpine.data('cdEditProfile', () => ({
        form: {
            first_name: '',
            last_name: '',
            bio: '',
            avatar_url: '',
            emails: [],
            phones: [],
            social_links: [],
            address_line_1: '',
            address_line_2: '',
            address_mailing: '',
            city: '',
            state: '',
            zip: '',
            occupation: '',
            employer: '',
            date_of_birth: '',
            baptism_date: '',
            wedding_anniversary: '',
            name_day: '',
            emergency_contact_name: '',
            emergency_contact_phone: '',
            preferred_contact_method: 'email',
            preferred_language: 'en',
            privacy_settings: {
                email: 'visible',
                phone: 'visible',
                address: 'visible',
                social: 'hidden',
                date_of_birth: 'hidden',
                wedding_anniversary: 'hidden',
            },
        },
        loading: true,
        saving: false,
        errorMessage: '',
        successMessage: '',
        uploadingAvatar: false,
        showPrivacyModal: false,

        async init() {
            const uuid = cdConfig.currentMemberUuid;
            if (!uuid) {
                this.errorMessage = 'Could not identify member profile.';
                this.loading = false;
                return;
            }

            try {
                const result = await cdApi.get('/members/' + uuid);
                const data = result.data.member;

                // Populate form
                this.form.first_name = data.first_name || '';
                this.form.last_name = data.last_name || '';
                this.form.avatar_url = data.avatar_url || '';
                this.form.bio = data.bio || '';

                // Parse address_home into line_1 and line_2
                const addrParts = (data.address_home || '').split('\n');
                this.form.address_line_1 = addrParts[0] || '';
                this.form.address_line_2 = addrParts[1] || '';
                this.form.address_mailing = data.address_mailing || '';

                this.form.city = data.city || '';
                this.form.state = data.state || '';
                this.form.zip = data.zip || '';
                this.form.occupation = data.occupation || '';
                this.form.employer = data.employer || '';
                this.form.date_of_birth = data.date_of_birth || '';
                this.form.baptism_date = data.baptism_date || '';
                this.form.wedding_anniversary = data.wedding_anniversary || '';
                this.form.name_day = data.name_day || '';
                this.form.emergency_contact_name = data.emergency_contact_name || '';
                this.form.emergency_contact_phone = data.emergency_contact_phone || '';
                this.form.preferred_contact_method = data.preferred_contact_method || 'email';
                this.form.preferred_language = data.preferred_language || 'en';

                // Load privacy settings with defaults
                const defaults = { email: 'visible', phone: 'visible', address: 'visible', social: 'hidden', date_of_birth: 'hidden', wedding_anniversary: 'hidden' };
                const saved = (typeof data.privacy_settings === 'object' && data.privacy_settings) ? data.privacy_settings : {};
                this.form.privacy_settings = { ...defaults, ...saved };

                // Ensure emails/phones/socials are arrays
                this.form.emails = Array.isArray(data.emails) ? data.emails : [];
                this.form.phones = Array.isArray(data.phones) ? data.phones : [];
                this.form.social_links = Array.isArray(data.social_links) ? data.social_links : [];

                // Minimum 1 empty slot
                if (this.form.emails.length === 0) {
                    this.form.emails.push({ type: 'personal', value: '' });
                }
                if (this.form.phones.length === 0) {
                    this.form.phones.push({ type: 'mobile', value: '' });
                }

            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
            }
        },

        addEmail() {
            this.form.emails.push({ type: 'personal', value: '' });
        },

        removeEmail(index) {
            this.form.emails.splice(index, 1);
        },

        addPhone() {
            this.form.phones.push({ type: 'mobile', value: '' });
        },

        removePhone(index) {
            this.form.phones.splice(index, 1);
        },

        addSocial() {
            this.form.social_links.push({ platform: 'facebook', url: '' });
        },

        removeSocial(index) {
            this.form.social_links.splice(index, 1);
        },

        uploadAvatar(event) {
            const file = event.target.files[0];
            if (!file) return;

            this.uploadingAvatar = true;
            const formData = new FormData();
            formData.append('file', file);

            // Use fetch directly for file upload to handle FormData correctly if cdApi wrapper doesn't support it easily
            // But cdApi should support it if we pass body as FormData.
            // Let's use cdApi.request with custom options

            const url = cdConfig.apiUrl + '/members/avatar';

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': cdConfig.nonce,
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.data && data.data.url) {
                        this.form.avatar_url = data.data.url;
                        this.successMessage = 'Avatar uploaded successfully.';
                    } else {
                        throw new Error(data.message || 'Upload failed');
                    }
                })
                .catch(err => {
                    this.errorMessage = err.message;
                })
                .finally(() => {
                    this.uploadingAvatar = false;
                });
        },

        async deleteAvatar() {
            if (!confirm('Are you sure you want to remove your profile picture?')) return;

            this.uploadingAvatar = true;
            try {
                await cdApi.request('/members/avatar', { method: 'DELETE' });
                this.form.avatar_url = '';
                this.successMessage = 'Avatar removed.';
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.uploadingAvatar = false;
            }
        },

        // Avatar helpers
        getAvatarColor(name) {
            const colors = ['#d32f2f', '#c2185b', '#7b1fa2', '#512da8', '#303f9f', '#1976d2', '#0288d1', '#0097a7', '#00796b', '#388e3c', '#afb42b', '#fbc02d', '#ffa000', '#f57c00', '#e64a19', '#5d4037', '#616161'];
            let hash = 0;
            for (let i = 0; i < (name || '').length; i++) {
                hash = name.charCodeAt(i) + ((hash << 5) - hash);
            }
            return colors[Math.abs(hash) % colors.length];
        },

        getInitials(first, last) {
            return ((first || '').charAt(0) + (last || '').charAt(0)).toUpperCase();
        },

        // Privacy Modals
        togglePrivacy(field) {
            const current = this.form.privacy_settings[field];
            this.form.privacy_settings[field] = (current === 'visible') ? 'hidden' : 'visible';
        },

        getPrivacyIcon(field) {
            return (this.form.privacy_settings[field] === 'visible') ? 'dashicons-visibility' : 'dashicons-hidden';
        },

        getPrivacyLabel(field) {
            // Using generic English here, assuming localization is handled via PHP or simple maps if needed in JS.
            // But since this is inside Alpine, standard wp.i18n isn't always available without setup.
            return (this.form.privacy_settings[field] === 'visible') ? 'Visible to Members' : 'Hidden';
        },

        async saveProfile() {
            this.errorMessage = '';
            this.successMessage = '';

            // Validate at least one email
            const validEmails = this.form.emails.filter(e => e.value.trim() !== '');
            if (validEmails.length === 0) {
                this.errorMessage = 'Please provide at least one email address.';
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            // Validate email formats
            for (const e of validEmails) {
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e.value)) {
                    this.errorMessage = 'Please enter a valid email address: ' + e.value;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return;
                }
            }

            this.saving = true;

            try {
                const payload = {
                    ...this.form,
                    emails: validEmails,
                    phones: this.form.phones.filter(p => p.value.trim() !== ''),
                    social_links: this.form.social_links.filter(s => s.url.trim() !== ''),
                };

                await cdApi.put('/members/me', payload);

                this.successMessage = 'Profile updated successfully.';
                window.scrollTo({ top: 0, behavior: 'smooth' });

            } catch (err) {
                this.errorMessage = err.message;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } finally {
                this.saving = false;
            }
        },
    }));

});
