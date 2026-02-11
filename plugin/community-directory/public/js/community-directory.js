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

/* ─── Email Obfuscation Helper ─── */
function cdDecodeEmail(encoded) {
    if (!encoded) return '';
    try { return atob(encoded); } catch (e) { return encoded; }
}

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
                    'invalid_grant': 'Google sign-in session expired. Please click "Sign in with Google" to try again.',
                    'token_exchange_failed': 'Could not connect to Google. Please try again.',
                };
                this.errorMessage = errorMessages[error] || 'Sign-in error: ' + error;
            }

            // Handle logged_out message
            if (params.get('logged_out')) {
                this.successMessage = 'You have been logged out.';
            }

            // Handle PWA session expiry
            if (params.get('expired') === '1' || window.cdSessionExpired) {
                this.errorMessage = 'Your session has expired. Please sign in again.';
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
                var loginData = {
                    email: this.email,
                    password: this.password,
                };

                // In PWA context, auto-enable remember me for 30-day sessions
                if (window.cdIsPwa) {
                    loginData.remember = true;
                    loginData.pwa = true;
                }

                const result = await cdApi.post('/auth/login', loginData);

                // Deep link preservation: if we came from session expiry, return to original page
                const params = new URLSearchParams(window.location.search);
                const returnUrl = params.get('return');

                if (returnUrl && returnUrl.startsWith('/')) {
                    window.location.href = returnUrl;
                } else if (result.data && result.data.redirect) {
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

        async signUpWithGoogle() {
            this.errorMessage = '';
            this.creating = true;

            const params = new URLSearchParams(window.location.search);
            const token = params.get('token');

            try {
                const result = await cdApi.get(
                    '/auth/google?invite_token=' + encodeURIComponent(token) +
                    '&invite_email=' + encodeURIComponent(this.email)
                );
                if (result.data && result.data.auth_url) {
                    window.location.href = result.data.auth_url;
                } else {
                    this.errorMessage = 'Google sign-in is not configured. Please create a password instead.';
                    this.creating = false;
                }
            } catch (err) {
                this.errorMessage = err.message;
                this.creating = false;
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

    /* ─── Directory ─── */
    Alpine.data('cdDirectory', () => {
        try {
            return {
                searchQuery: '',
                members: [],
                loading: false,
                page: 1,
                perPage: 24,
                totalPages: 1,
                totalMembers: 0,

                // Advanced filters
                showFilters: false,
                filterCity: '',
                filterState: '',
                filterOccupation: '',
                filterEmployer: '',

                // WhatsApp groups
                whatsappGroups: [],
                whatsappLoading: false,

                init() {
                    // Safety: Force loading to stop after 5s if API hangs
                    setTimeout(() => {
                        if (this.loading) {
                            console.warn('CD: Loading timed out, forcing UI state');
                            this.loading = false;
                        }
                    }, 5000);

                    this.loadMembers();
                    this.loadWhatsAppGroups();
                },

                async loadMembers() {
                    this.loading = true;
                    try {
                        let url = '/directory?page=' + this.page + '&per_page=' + this.perPage;
                        if (this.searchQuery) {
                            url += '&search=' + encodeURIComponent(this.searchQuery);
                        }
                        if (this.filterCity) url += '&city=' + encodeURIComponent(this.filterCity);
                        if (this.filterState) url += '&state=' + encodeURIComponent(this.filterState);
                        if (this.filterOccupation) url += '&occupation=' + encodeURIComponent(this.filterOccupation);
                        if (this.filterEmployer) url += '&employer=' + encodeURIComponent(this.filterEmployer);

                        const result = await cdApi.get(url);
                        this.members = result.data.members || [];

                        // Decode obfuscated emails
                        if (result.data.email_obfuscated) {
                            this.members.forEach(m => { if (m.email) m.email = cdDecodeEmail(m.email); });
                        }

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

                async loadWhatsAppGroups() {
                    this.whatsappLoading = true;
                    try {
                        const result = await cdApi.get('/whatsapp-groups');
                        this.whatsappGroups = result.data.groups || [];
                    } catch (err) {
                        this.whatsappGroups = [];
                    } finally {
                        this.whatsappLoading = false;
                    }
                },

                search() {
                    this.page = 1;
                    this.loadMembers();
                },

                applyFilters() {
                    this.page = 1;
                    this.loadMembers();
                },

                clearFilters() {
                    this.filterCity = '';
                    this.filterState = '';
                    this.filterOccupation = '';
                    this.filterEmployer = '';
                    this.page = 1;
                    this.loadMembers();
                },

                hasActiveFilters() {
                    return this.filterCity || this.filterState || this.filterOccupation || this.filterEmployer;
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

                // Display name with optional salutation
                displayName(member) {
                    var prefix = member.salutation ? member.salutation + ' ' : '';
                    return prefix + member.first_name + ' ' + member.last_name;
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
                    return ((first || '').charAt(0) + (last || '').charAt(0)).toUpperCase();
                }
            }; // End returned object
        } catch (e) {
            console.error('CD: Critical Error in cdDirectory', e);
            return {
                init() { },
                loading: false,
                members: [],
                searchQuery: '',
                page: 1,
                totalPages: 0,
                whatsappGroups: [],
                getError() { return 'Application error: ' + e.message; },
                displayName() { return 'Error'; },
                getAvatarColor() { return '#ccc'; },
                getInitials() { return '!!'; },
                hasActiveFilters() { return false; }
            };
        }
    });

    /* ─── Member Profile View ─── */
    Alpine.data('cdMemberProfile', () => ({
        member: null,
        loading: true,
        errorMessage: '',
        isOwnProfile: false,
        household: null,

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
                this.household = result.data.household || null;

                // Decode obfuscated emails
                if (result.data.email_obfuscated && this.member.emails) {
                    this.member.emails = this.member.emails.map(e => ({ ...e, value: cdDecodeEmail(e.value) }));
                }
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.loading = false;
            }
        },

        displayName() {
            if (!this.member) return '';
            var prefix = this.member.salutation ? this.member.salutation + ' ' : '';
            return prefix + this.member.first_name + ' ' + this.member.last_name;
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
                return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
            }
            return value;
        },
    }));

    /* ─── Edit Profile ─── */
    Alpine.data('cdEditProfile', () => ({
        form: {
            salutation: '',
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
            // Child/student fields
            school_type: '',
            school_name: '',
            graduation_date: '',
            major_studies: '',
            minor_studies: '',
            sunday_school_teacher_id: null,
            sunday_school_teacher_name: '',
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
        uploadingHouseholdPhoto: false,
        showCamera: false,
        cameraStream: null,
        showPrivacyModal: false,
        householdRole: null,
        teacherSearchQuery: '',
        teacherResults: [],

        // Household state
        household: null,
        householdLoading: true,
        householdSaving: false,
        householdMessage: '',
        householdError: '',
        showCreateHousehold: false,
        showEditHousehold: false,
        showAddMember: false,
        newHouseholdName: '',
        newHouseholdAddr: { line_1: '', line_2: '', city: '', state: '', zip: '' },
        hhInheritAddress: false,
        editHouseholdName: '',
        editHouseholdAddr: { line_1: '', line_2: '', city: '', state: '', zip: '' },
        addMemberForm: { first_name: '', last_name: '', email: '', role: 'child' },

        // Lifecycle state
        showLeaveConfirm: false,
        showTransferHead: false,
        showSpinOff: false,
        showMergeRequest: false,
        showDeletionRequest: false,
        transferTargetId: '',
        spinOffForm: { name: '', line_1: '', city: '', state: '', zip: '', bring_children: [] },
        mergeSearchQuery: '',
        mergeSearchResults: [],
        mergeTargetHouseholdId: '',
        mergeSearching: false,
        deletionReason: '',

        async init() {
            const uuid = cdConfig.currentMemberUuid;
            if (!uuid) {
                this.errorMessage = 'Could not identify member profile.';
                this.loading = false;
                this.householdLoading = false;
                return;
            }

            // Load profile and household in parallel
            this.loadHousehold();

            try {
                const result = await cdApi.get('/members/' + uuid);
                const data = result.data.member;

                // Populate form
                this.form.salutation = data.salutation || '';
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

                // Child/student fields
                this.form.school_type = data.school_type || '';
                this.form.school_name = data.school_name || '';
                this.form.graduation_date = data.graduation_date || '';
                this.form.major_studies = data.major_studies || '';
                this.form.minor_studies = data.minor_studies || '';
                this.form.sunday_school_teacher_id = data.sunday_school_teacher_id || null;
                this.form.sunday_school_teacher_name = data.sunday_school_teacher_name || '';

                // Household role (determines which fields to show)
                this.householdRole = result.data.household_role || null;

                // Load privacy settings with defaults
                const defaults = { email: 'visible', phone: 'visible', address: 'visible', social: 'hidden', date_of_birth: 'hidden', wedding_anniversary: 'hidden' };
                const saved = (typeof data.privacy_settings === 'object' && data.privacy_settings) ? data.privacy_settings : {};
                this.form.privacy_settings = { ...defaults, ...saved };

                // Ensure emails/phones/socials are arrays
                this.form.emails = Array.isArray(data.emails) ? data.emails : [];
                this.form.phones = Array.isArray(data.phones) ? data.phones : [];
                this.form.social_links = Array.isArray(data.social_links) ? data.social_links : [];

                // Decode obfuscated emails from API
                if (result.data.email_obfuscated) {
                    this.form.emails = this.form.emails.map(e => ({ ...e, value: cdDecodeEmail(e.value) }));
                }

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

        async startCamera() {
            this.errorMessage = '';

            // getUserMedia requires HTTPS (secure context)
            if (window.isSecureContext === false) {
                this.errorMessage = 'Camera requires a secure (HTTPS) connection. Please access this site via HTTPS.';
                return;
            }

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.errorMessage = 'Camera is not supported on this device or browser. Make sure you are using HTTPS.';
                return;
            }

            const attachStream = (stream) => {
                this.cameraStream = stream;
                this.showCamera = true;
                // Use requestAnimationFrame to ensure DOM is visible before attaching
                requestAnimationFrame(() => {
                    const video = this.$refs.cameraVideo;
                    if (video) {
                        video.srcObject = stream;
                        video.play().catch(() => { });
                    }
                });
            };

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 640 } },
                    audio: false,
                });
                attachStream(stream);
            } catch (err) {
                console.error('CD Camera Error:', err.name, err.message);
                if (err.name === 'NotAllowedError') {
                    this.errorMessage = 'Camera access was denied. Please allow camera access in your browser settings and try again.';
                } else if (err.name === 'NotFoundError') {
                    this.errorMessage = 'No camera found on this device.';
                } else if (err.name === 'NotReadableError') {
                    this.errorMessage = 'Camera is already in use by another application.';
                } else if (err.name === 'OverconstrainedError') {
                    // Try again without constraints
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                        attachStream(stream);
                        return;
                    } catch (e2) {
                        this.errorMessage = 'Could not access camera: ' + e2.message;
                    }
                } else {
                    this.errorMessage = 'Could not access camera: ' + (err.message || err.name);
                }
            }
        },

        capturePhoto() {
            const video = this.$refs.cameraVideo;
            const canvas = this.$refs.cameraCanvas;
            if (!video || !canvas) {
                this.errorMessage = 'Camera not ready. Please try again.';
                return;
            }

            // Ensure video has actual dimensions (stream is playing)
            if (!video.videoWidth || !video.videoHeight) {
                this.errorMessage = 'Camera not ready yet. Please wait a moment and try again.';
                return;
            }

            // Square crop: use the smaller dimension
            const size = Math.min(video.videoWidth, video.videoHeight);
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');
            const sx = (video.videoWidth - size) / 2;
            const sy = (video.videoHeight - size) / 2;
            ctx.drawImage(video, sx, sy, size, size, 0, 0, size, size);

            // Stop camera AFTER drawing to canvas (canvas is outside x-if now, so it persists)
            this.stopCamera();

            // Convert canvas to blob and upload
            canvas.toBlob((blob) => {
                if (!blob) {
                    this.errorMessage = 'Failed to capture photo. Please try again.';
                    return;
                }
                this.uploadingAvatar = true;
                const formData = new FormData();
                formData.append('file', blob, 'camera-photo.jpg');

                const url = cdConfig.apiUrl + '/members/avatar';
                fetch(url, {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': cdConfig.nonce },
                    credentials: 'same-origin',
                    body: formData,
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.data && data.data.url) {
                            this.form.avatar_url = data.data.url;
                            this.successMessage = 'Photo captured and uploaded.';
                        } else {
                            throw new Error(data.message || data.error?.message || 'Upload failed');
                        }
                    })
                    .catch(err => {
                        this.errorMessage = err.message;
                    })
                    .finally(() => {
                        this.uploadingAvatar = false;
                    });
            }, 'image/jpeg', 0.9);
        },

        stopCamera() {
            if (this.cameraStream) {
                this.cameraStream.getTracks().forEach(track => track.stop());
                this.cameraStream = null;
            }
            this.showCamera = false;
        },

        // Teacher search for Sunday School
        async searchTeachers() {
            if (this.teacherSearchQuery.length < 2) {
                this.teacherResults = [];
                return;
            }
            try {
                const result = await cdApi.get('/directory?search=' + encodeURIComponent(this.teacherSearchQuery) + '&per_page=8');
                this.teacherResults = (result.data.members || []).map(m => ({
                    member_id: m.id || m.member_id,
                    first_name: m.first_name,
                    last_name: m.last_name,
                }));
            } catch (err) {
                this.teacherResults = [];
            }
        },

        selectTeacher(teacher) {
            this.form.sunday_school_teacher_id = teacher.member_id;
            this.form.sunday_school_teacher_name = teacher.first_name + ' ' + teacher.last_name;
            this.teacherSearchQuery = '';
            this.teacherResults = [];
        },

        // Household photo upload
        uploadHouseholdPhoto(event) {
            const file = event.target.files[0];
            if (!file) return;

            this.uploadingHouseholdPhoto = true;
            this.householdError = '';

            const formData = new FormData();
            formData.append('file', file);

            fetch(cdConfig.apiUrl + '/members/me/household/photo', {
                method: 'POST',
                headers: { 'X-WP-Nonce': cdConfig.nonce },
                credentials: 'same-origin',
                body: formData,
            })
                .then(r => r.json())
                .then(data => {
                    if (data.data && data.data.url) {
                        this.household.photo_url = data.data.url;
                        this.householdMessage = data.data.message || 'Family photo uploaded.';
                    } else {
                        throw new Error(data.message || 'Upload failed');
                    }
                })
                .catch(err => {
                    this.householdError = err.message;
                })
                .finally(() => {
                    this.uploadingHouseholdPhoto = false;
                });
        },

        async deleteHouseholdPhoto() {
            if (!confirm('Remove the family photo?')) return;
            this.uploadingHouseholdPhoto = true;
            this.householdError = '';
            try {
                await cdApi.request('/members/me/household/photo', { method: 'DELETE' });
                this.household.photo_url = '';
                this.householdMessage = 'Family photo removed.';
            } catch (err) {
                this.householdError = err.message;
            } finally {
                this.uploadingHouseholdPhoto = false;
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

        // ── Household Methods ──

        async loadHousehold() {
            this.householdLoading = true;
            try {
                const result = await cdApi.get('/members/me/household');
                this.household = result.data.household || null;
            } catch (err) {
                // Not critical — just means no household
                this.household = null;
            } finally {
                this.householdLoading = false;
            }
        },

        async createHousehold() {
            if (!this.newHouseholdName.trim()) {
                this.householdError = 'Please enter a household name.';
                return;
            }
            this.householdSaving = true;
            this.householdError = '';
            try {
                // Build address — either inherit from profile or use typed values
                let addr = this.newHouseholdAddr;
                if (this.hhInheritAddress) {
                    addr = {
                        line_1: this.form.address_line_1 || '',
                        line_2: this.form.address_line_2 || '',
                        city: this.form.city || '',
                        state: this.form.state || '',
                        zip: this.form.zip || '',
                    };
                }
                const result = await cdApi.post('/members/me/household', {
                    name: this.newHouseholdName.trim(),
                    address: addr,
                });
                this.householdMessage = result.data.message;
                this.showCreateHousehold = false;
                this.newHouseholdName = '';
                this.newHouseholdAddr = { line_1: '', line_2: '', city: '', state: '', zip: '' };
                this.hhInheritAddress = false;
                await this.loadHousehold();
            } catch (err) {
                this.householdError = err.message;
            } finally {
                this.householdSaving = false;
            }
        },

        async updateHousehold() {
            this.householdSaving = true;
            this.householdError = '';
            try {
                await cdApi.put('/members/me/household', {
                    name: this.editHouseholdName.trim(),
                    address: this.editHouseholdAddr,
                });
                this.householdMessage = 'Household updated.';
                this.showEditHousehold = false;
                await this.loadHousehold();
            } catch (err) {
                this.householdError = err.message;
            } finally {
                this.householdSaving = false;
            }
        },

        async addHouseholdMember() {
            const f = this.addMemberForm;
            if (!f.first_name.trim() || !f.last_name.trim()) {
                this.householdError = 'First name and last name are required.';
                return;
            }
            this.householdSaving = true;
            this.householdError = '';
            try {
                const result = await cdApi.post('/members/me/household/members', {
                    first_name: f.first_name.trim(),
                    last_name: f.last_name.trim(),
                    email: f.email.trim(),
                    role: f.role,
                });
                this.householdMessage = result.data.message;
                this.showAddMember = false;
                this.addMemberForm = { first_name: '', last_name: '', email: '', role: 'spouse' };
                await this.loadHousehold();
            } catch (err) {
                this.householdError = err.message;
            } finally {
                this.householdSaving = false;
            }
        },

        async removeHouseholdMember(memberId, firstName) {
            if (!confirm('Remove ' + firstName + ' from your household?')) return;
            this.householdError = '';
            try {
                await cdApi.request('/members/me/household/members/' + memberId, { method: 'DELETE' });
                this.householdMessage = firstName + ' has been removed.';
                await this.loadHousehold();
            } catch (err) {
                this.householdError = err.message;
            }
        },

        // ── Lifecycle Methods ──

        async leaveHousehold() {
            this.householdError = '';
            this.householdSaving = true;
            try {
                const result = await cdApi.post('/members/me/household/leave', {});
                this.householdMessage = result.data.message || 'You have left the household.';
                this.showLeaveConfirm = false;
                this.household = null;
                await this.loadHousehold();
            } catch (err) {
                this.householdError = err.message;
            } finally {
                this.householdSaving = false;
            }
        },

        async transferHead() {
            if (!this.transferTargetId) {
                this.householdError = 'Please select a member to transfer leadership to.';
                return;
            }
            this.householdError = '';
            this.householdSaving = true;
            try {
                const result = await cdApi.post('/members/me/household/transfer-head', {
                    target_member_id: parseInt(this.transferTargetId),
                });
                this.householdMessage = result.data.message || 'Leadership transferred.';
                this.showTransferHead = false;
                this.transferTargetId = '';
                await this.loadHousehold();
            } catch (err) {
                this.householdError = err.message;
            } finally {
                this.householdSaving = false;
            }
        },

        async submitSpinOff() {
            if (!this.spinOffForm.name.trim()) {
                this.householdError = 'Please enter a household name.';
                return;
            }
            this.householdError = '';
            this.householdSaving = true;
            try {
                const result = await cdApi.post('/members/me/household/spin-off', {
                    household_name: this.spinOffForm.name.trim(),
                    address: {
                        line_1: this.spinOffForm.line_1,
                        city: this.spinOffForm.city,
                        state: this.spinOffForm.state,
                        zip: this.spinOffForm.zip,
                    },
                    bring_children: this.spinOffForm.bring_children,
                });
                this.householdMessage = result.data.message || 'New household created.';
                this.showSpinOff = false;
                this.spinOffForm = { name: '', line_1: '', city: '', state: '', zip: '', bring_children: [] };
                await this.loadHousehold();
            } catch (err) {
                this.householdError = err.message;
            } finally {
                this.householdSaving = false;
            }
        },

        async searchHouseholds() {
            if (this.mergeSearchQuery.length < 2) {
                this.mergeSearchResults = [];
                return;
            }
            this.mergeSearching = true;
            try {
                const result = await cdApi.get('/households/search?q=' + encodeURIComponent(this.mergeSearchQuery));
                this.mergeSearchResults = result.data.households || [];
            } catch (err) {
                this.mergeSearchResults = [];
            } finally {
                this.mergeSearching = false;
            }
        },

        async submitMergeRequest() {
            if (!this.mergeTargetHouseholdId) {
                this.householdError = 'Please select a target household.';
                return;
            }
            this.householdError = '';
            this.householdSaving = true;
            try {
                const result = await cdApi.post('/members/me/household/merge-request', {
                    target_household_id: parseInt(this.mergeTargetHouseholdId),
                });
                this.householdMessage = result.data.message || 'Merge request submitted for admin approval.';
                this.showMergeRequest = false;
                this.mergeSearchQuery = '';
                this.mergeSearchResults = [];
                this.mergeTargetHouseholdId = '';
            } catch (err) {
                this.householdError = err.message;
            } finally {
                this.householdSaving = false;
            }
        },

        async submitDeletionRequest() {
            this.errorMessage = '';
            this.saving = true;
            try {
                const result = await cdApi.post('/members/me/deletion-request', {
                    reason: this.deletionReason,
                });
                this.successMessage = result.data.message || 'Your deletion request has been submitted.';
                this.showDeletionRequest = false;
                this.deletionReason = '';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.saving = false;
            }
        },

        // Populate edit household modal when opened
        $watch: {
            showEditHousehold(val) {
                if (val && this.household) {
                    this.editHouseholdName = this.household.name || '';
                    const a = this.household.address || {};
                    this.editHouseholdAddr = {
                        line_1: a.line_1 || '',
                        line_2: a.line_2 || '',
                        city: a.city || '',
                        state: a.state || '',
                        zip: a.zip || '',
                    };
                }
            },
        },

        // ── Profile Save ──

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

/* ─── PWA: Install Prompt, Update Banner, Session Management ─── */
(function () {
    'use strict';

    // Only run on community pages
    if (typeof cdConfig === 'undefined') return;

    var isPwa = window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true;

    /* ── Install Prompt (Android) ── */
    var deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e;

        // Don't show if already in PWA or recently dismissed
        if (isPwa) return;
        var dismissed = localStorage.getItem('cd-pwa-dismissed');
        if (dismissed && (Date.now() - parseInt(dismissed, 10)) < 30 * 24 * 60 * 60 * 1000) return;

        showInstallBanner();
    });

    function showInstallBanner() {
        // Don't show duplicate
        if (document.getElementById('cd-pwa-install-banner')) return;

        var banner = document.createElement('div');
        banner.id = 'cd-pwa-install-banner';
        banner.className = 'cd-pwa-install-banner';
        banner.innerHTML =
            '<div class="cd-pwa-install-content">' +
            '<span class="cd-pwa-install-text">Install this app for quick access</span>' +
            '<div class="cd-pwa-install-actions">' +
            '<button class="cd-pwa-install-btn" id="cd-pwa-install-accept">Install</button>' +
            '<button class="cd-pwa-install-dismiss" id="cd-pwa-install-dismiss" aria-label="Dismiss">&times;</button>' +
            '</div>' +
            '</div>';

        document.body.appendChild(banner);

        // Slide in
        requestAnimationFrame(function () {
            banner.classList.add('cd-pwa-install-visible');
        });

        document.getElementById('cd-pwa-install-accept').addEventListener('click', function () {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function (result) {
                    deferredPrompt = null;
                    removeInstallBanner();
                    if (result.outcome === 'dismissed') {
                        localStorage.setItem('cd-pwa-dismissed', Date.now().toString());
                    }
                });
            }
        });

        document.getElementById('cd-pwa-install-dismiss').addEventListener('click', function () {
            localStorage.setItem('cd-pwa-dismissed', Date.now().toString());
            removeInstallBanner();
        });
    }

    function removeInstallBanner() {
        var banner = document.getElementById('cd-pwa-install-banner');
        if (banner) {
            banner.classList.remove('cd-pwa-install-visible');
            setTimeout(function () { banner.remove(); }, 300);
        }
    }

    /* ── iOS Install Instructions ── */
    var isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    if (isIos && !isPwa) {
        var iosDismissed = localStorage.getItem('cd-pwa-ios-dismissed');
        var shouldShowIos = !iosDismissed || (Date.now() - parseInt(iosDismissed, 10)) > 30 * 24 * 60 * 60 * 1000;

        if (shouldShowIos && !sessionStorage.getItem('cd-pwa-ios-shown')) {
            sessionStorage.setItem('cd-pwa-ios-shown', '1');

            // Delay slightly so page loads first
            setTimeout(function () {
                var overlay = document.createElement('div');
                overlay.id = 'cd-pwa-ios-overlay';
                overlay.className = 'cd-pwa-ios-overlay';
                overlay.innerHTML =
                    '<div class="cd-pwa-ios-card">' +
                    '<button class="cd-pwa-ios-close" id="cd-pwa-ios-close" aria-label="Close">&times;</button>' +
                    '<div class="cd-pwa-ios-icon">&#x2B06;&#xFE0F;</div>' +
                    '<h3>Install This App</h3>' +
                    '<p>Tap the <strong>Share</strong> button <span style="font-size:1.2em;">&#x1F4E4;</span> in Safari, then select <strong>"Add to Home Screen"</strong></p>' +
                    '<button class="cd-pwa-ios-got-it" id="cd-pwa-ios-got-it">Got It</button>' +
                    '</div>';

                document.body.appendChild(overlay);
                requestAnimationFrame(function () { overlay.classList.add('cd-pwa-ios-visible'); });

                function closeIosOverlay() {
                    localStorage.setItem('cd-pwa-ios-dismissed', Date.now().toString());
                    overlay.classList.remove('cd-pwa-ios-visible');
                    setTimeout(function () { overlay.remove(); }, 300);
                }

                document.getElementById('cd-pwa-ios-close').addEventListener('click', closeIosOverlay);
                document.getElementById('cd-pwa-ios-got-it').addEventListener('click', closeIosOverlay);
            }, 2000);
        }
    }

    /* ── Update Banner ── */
    window.addEventListener('cd-sw-update', function (e) {
        // Don't show duplicate
        if (document.getElementById('cd-pwa-update-banner')) return;

        var reg = e.detail.registration;
        var banner = document.createElement('div');
        banner.id = 'cd-pwa-update-banner';
        banner.className = 'cd-pwa-update-banner';
        banner.innerHTML =
            '<div class="cd-pwa-update-content">' +
            '<span>A new version is available.</span>' +
            '<button class="cd-pwa-update-btn" id="cd-pwa-update-btn">Tap to update</button>' +
            '<button class="cd-pwa-update-dismiss" id="cd-pwa-update-dismiss" aria-label="Dismiss">&times;</button>' +
            '</div>';

        document.body.appendChild(banner);
        requestAnimationFrame(function () { banner.classList.add('cd-pwa-update-visible'); });

        document.getElementById('cd-pwa-update-btn').addEventListener('click', function () {
            window._cdSwUpdating = true;
            if (reg.waiting) {
                reg.waiting.postMessage({ type: 'SKIP_WAITING' });
            }
        });

        document.getElementById('cd-pwa-update-dismiss').addEventListener('click', function () {
            banner.classList.remove('cd-pwa-update-visible');
            setTimeout(function () { banner.remove(); }, 300);
        });
    });

    /* ── PWA Session Management ── */
    if (isPwa && cdConfig.isLoggedIn) {
        var lastSessionCheck = Date.now();
        var SESSION_CHECK_INTERVAL = 5 * 60 * 1000; // 5 minutes

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState !== 'visible') return;
            if ((Date.now() - lastSessionCheck) < SESSION_CHECK_INTERVAL) return;

            lastSessionCheck = Date.now();

            fetch(cdConfig.apiUrl + '/auth/session-check', {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': cdConfig.nonce }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.data && data.data.valid === false) {
                        var returnPath = encodeURIComponent(window.location.pathname + window.location.search);
                        window.location.href = cdConfig.baseUrl + '/login/?expired=1&return=' + returnPath;
                    }
                })
                .catch(function () {
                    // Network error — don't redirect, user might be offline
                });
        });
    }

    /* ── Handle session expiry on login page ── */
    if (window.location.search.indexOf('expired=1') !== -1) {
        // The Alpine cdLogin component will pick up the expired param
        // Set a flag for the login component to show the message
        window.cdSessionExpired = true;
    }

    /* ── PWA login: auto-set remember + pwa flag ── */
    if (isPwa) {
        window.cdIsPwa = true;
    }
})();
