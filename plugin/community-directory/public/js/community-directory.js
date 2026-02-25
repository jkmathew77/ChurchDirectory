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
            this.errorMessage = '';
            this.loading = true;

            try {
                const result = await cdApi.get('/auth/google');

                if (result.data && result.data.auth_url) {
                    window.location.href = result.data.auth_url;
                } else {
                    this.errorMessage = 'Configuration error: No Auth URL returned.';
                    this.loading = false;
                }
            } catch (err) {
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
                households: [],
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

                // Officer admin state
                isOfficer: !!(cdConfig && cdConfig.isOfficer),
                activeTab: 'directory',
                adminSection: 'dashboard',
                pendingAppCount: 0,

                // Dashboard stats
                dashStats: null,
                dashLoading: false,
                dashError: '',

                // Applications state
                applications: [],
                appsLoading: false,
                appsError: '',
                appsPage: 1,
                appsTotalPages: 1,
                appStatusFilter: '',
                appCounts: { all: 0, new: 0, under_review: 0, on_hold: 0, approved: 0, not_approved: 0 },
                expandedAppId: null,
                appActionNotes: '',
                appActioning: false,
                appActionSuccess: '',
                appActionError: '',

                // Registrations state
                registrations: [],
                regsLoading: false,
                regsError: '',
                regCounts: {},
                regActioning: null,

                // Deletion requests state
                deletionRequests: [],
                delReqLoading: false,
                delReqError: '',
                delReqActioning: null,

                // Household merge requests state
                householdRequests: [],
                hhReqLoading: false,
                hhReqError: '',
                hhReqActioning: null,

                // WhatsApp management state
                waGroups: [],
                waLoading: false,
                waError: '',
                waEditing: null,
                waForm: { name: '', description: '', invite_url: '', icon: '', display_order: 0, visibility: 'all', visibility_tag: '', is_active: true },
                waSaving: false,
                waShowForm: false,

                // Directory preferences state
                showSettingsModal: false,
                dirPrefs: { default_view: 'adults_only', sort_order: 'last_name', search_sections: ['all', 'households'] },
                settingsForm: { default_view: 'adults_only', sort_order: 'last_name', search_sections: ['all', 'households'] },
                prefsLoading: false,
                prefsSaving: false,

                init() {
                    // Safety: Force loading to stop after 5s if API hangs
                    setTimeout(() => {
                        if (this.loading) {
                            console.warn('CD: Loading timed out, forcing UI state');
                            this.loading = false;
                        }
                    }, 5000);

                    // Restore tab/section from URL hash (e.g. #admin/registrations)
                    const hash = window.location.hash.replace('#', '');
                    if (hash) {
                        const parts = hash.split('/');
                        if (parts[0] && ['directory', 'profile', 'admin'].indexOf(parts[0]) !== -1) {
                            this.activeTab = parts[0];
                        }
                        if (parts[1] && ['dashboard', 'applications', 'registrations', 'requests', 'whatsapp'].indexOf(parts[1]) !== -1) {
                            this.adminSection = parts[1];
                        }
                    }

                    this.loadPreferences().then(() => this.loadMembers());
                    this.loadWhatsAppGroups();

                    // Officers: fetch pending count + stats on init
                    if (this.isOfficer) {
                        this.loadPendingAppCount();
                        this.loadDashboardStats();
                        // If restored to a specific admin section, load its data
                        if (this.activeTab === 'admin' && this.adminSection !== 'dashboard') {
                            this.switchAdminSection(this.adminSection);
                        }
                    }
                },

                async loadMembers() {
                    this.loading = true;
                    try {
                        // Build API params based on preferences
                        var prefs = this.dirPrefs;
                        var sortBy = prefs.sort_order || 'last_name';
                        var memberFilter = 'all';
                        var viewMode = 'members';

                        if (!this.searchQuery) {
                            // Default view determines filter
                            switch (prefs.default_view) {
                                case 'all':
                                    memberFilter = 'all'; break;
                                case 'adults_only':
                                    memberFilter = 'adults'; break;
                                case 'children_only':
                                    memberFilter = 'children'; break;
                                case 'primary_only':
                                    memberFilter = 'primary'; break;
                                case 'household_view':
                                    viewMode = 'households'; break;
                            }
                        }

                        let url = '/directory?page=' + this.page + '&per_page=' + this.perPage;
                        url += '&sort_by=' + sortBy + '&member_filter=' + memberFilter + '&view_mode=' + viewMode;
                        if (this.searchQuery) {
                            url += '&search=' + encodeURIComponent(this.searchQuery);
                        }
                        if (this.filterCity) url += '&city=' + encodeURIComponent(this.filterCity);
                        if (this.filterState) url += '&state=' + encodeURIComponent(this.filterState);
                        if (this.filterOccupation) url += '&occupation=' + encodeURIComponent(this.filterOccupation);
                        if (this.filterEmployer) url += '&employer=' + encodeURIComponent(this.filterEmployer);

                        const result = await cdApi.get(url);
                        this.members = result.data.members || [];
                        this.households = result.data.households || [];

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
                        this.households = [];
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
                },

                // ── Officer Admin Methods ──

                switchTab(tab) {
                    this.activeTab = tab;
                    if (tab === 'admin') {
                        if (!this.dashStats) this.loadDashboardStats();
                        window.location.hash = tab + '/' + this.adminSection;
                    } else {
                        window.location.hash = tab;
                    }
                },

                switchAdminSection(section) {
                    this.adminSection = section;
                    window.location.hash = 'admin/' + section;
                    if (section === 'applications' && this.applications.length === 0) this.loadApplications();
                    if (section === 'registrations' && this.registrations.length === 0) this.loadRegistrations();
                    if (section === 'requests' && this.deletionRequests.length === 0) { this.loadDeletionRequests(); this.loadHouseholdRequests(); }
                    if (section === 'whatsapp' && this.waGroups.length === 0) this.loadWAGroups();
                },

                async loadDashboardStats() {
                    this.dashLoading = true;
                    this.dashError = '';
                    try {
                        const result = await cdApi.get('/admin/stats');
                        this.dashStats = result.data || null;
                        if (!this.dashStats) {
                            this.dashError = 'No stats data returned.';
                        }
                    } catch (err) {
                        this.dashStats = null;
                        this.dashError = err.message || 'Failed to load dashboard stats.';
                        console.error('CD Dashboard stats error:', err);
                    } finally {
                        this.dashLoading = false;
                    }
                },

                async loadPendingAppCount() {
                    try {
                        const result = await cdApi.get('/admin/applications?status=new&per_page=1');
                        if (result.data && result.data.counts) {
                            var counts = result.data.counts;
                            this.pendingAppCount = (counts.new || 0) + (counts.under_review || 0) + (counts.on_hold || 0);
                        }
                    } catch (err) {
                        // Silently fail
                    }
                },

                // ── Applications ──

                async loadApplications() {
                    this.appsLoading = true;
                    this.appsError = '';
                    this.appActionSuccess = '';
                    this.appActionError = '';
                    try {
                        var url = '/admin/applications?page=' + this.appsPage + '&per_page=20';
                        if (this.appStatusFilter) {
                            url += '&status=' + encodeURIComponent(this.appStatusFilter);
                        }
                        const result = await cdApi.get(url);
                        this.applications = result.data.applications || [];
                        this.appCounts = result.data.counts || {};
                        if (result.meta) {
                            this.appsTotalPages = result.meta.pages || 1;
                        }
                        this.pendingAppCount = (this.appCounts.new || 0) + (this.appCounts.under_review || 0) + (this.appCounts.on_hold || 0);
                    } catch (err) {
                        this.appsError = err.message;
                        this.applications = [];
                    } finally {
                        this.appsLoading = false;
                    }
                },

                toggleAppDetail(appId) {
                    if (this.expandedAppId === appId) {
                        this.expandedAppId = null;
                    } else {
                        this.expandedAppId = appId;
                        this.appActionNotes = '';
                        this.appActionSuccess = '';
                        this.appActionError = '';
                    }
                },

                async appAction(appId, action) {
                    if (action === 'reject' && !confirm('Are you sure you want to reject this application?')) return;
                    if (action === 'approve' && !confirm('Approve this application? An invite email will be sent.')) return;

                    this.appActioning = true;
                    this.appActionSuccess = '';
                    this.appActionError = '';
                    try {
                        await cdApi.put('/admin/applications/' + appId, {
                            action: action,
                            notes: this.appActionNotes || '',
                        });
                        var labels = { approve: 'Application approved.', reject: 'Application rejected.', hold: 'Application placed on hold.', request_info: 'Information requested from applicant.' };
                        this.appActionSuccess = labels[action] || 'Action completed.';
                        this.appActionNotes = '';
                        setTimeout(() => {
                            this.loadApplications();
                            this.expandedAppId = null;
                            this.loadDashboardStats();
                        }, 1200);
                    } catch (err) {
                        this.appActionError = err.message;
                    } finally {
                        this.appActioning = false;
                    }
                },

                // ── Registrations ──

                async loadRegistrations() {
                    this.regsLoading = true;
                    this.regsError = '';
                    try {
                        const result = await cdApi.get('/admin/registrations');
                        this.registrations = result.data.registrations || [];
                        this.regCounts = result.data.counts || {};
                    } catch (err) {
                        this.regsError = err.message;
                        this.registrations = [];
                    } finally {
                        this.regsLoading = false;
                    }
                },

                async regResendVerification(id) {
                    if (!confirm('Resend verification email for this registration?')) return;
                    this.regActioning = id;
                    try {
                        await cdApi.post('/admin/registrations/' + id + '/resend-verification', {});
                        alert('Verification email resent.');
                        this.loadRegistrations();
                    } catch (err) {
                        alert('Error: ' + err.message);
                    } finally {
                        this.regActioning = null;
                    }
                },

                async regRemove(id) {
                    if (!confirm('Remove this registration? This cannot be undone.')) return;
                    this.regActioning = id;
                    try {
                        await cdApi.request('/admin/members/' + id, { method: 'DELETE' });
                        alert('Registration removed.');
                        this.loadRegistrations();
                        this.loadDashboardStats();
                    } catch (err) {
                        alert('Error: ' + err.message);
                    } finally {
                        this.regActioning = null;
                    }
                },

                async regResendInvite(id) {
                    if (!confirm('Resend invite email to this member?')) return;
                    this.regActioning = id;
                    try {
                        await cdApi.post('/admin/members/' + id + '/resend-invite', {});
                        alert('Invite email resent.');
                        this.loadRegistrations();
                    } catch (err) {
                        alert('Error: ' + err.message);
                    } finally {
                        this.regActioning = null;
                    }
                },

                regStatusLabel(status) {
                    var map = { unverified: 'Unverified', new: 'New', under_review: 'Under Review', on_hold: 'On Hold', approved: 'Approved', not_approved: 'Rejected', invited: 'Invited', active: 'Active' };
                    return map[status] || status;
                },

                regStatusClass(status) {
                    var map = { unverified: 'cd-status-warning', new: 'cd-status-info', under_review: 'cd-status-warning', on_hold: 'cd-status-muted', approved: 'cd-status-success', not_approved: 'cd-status-danger', invited: 'cd-status-info', active: 'cd-status-success' };
                    return map[status] || '';
                },

                // ── Deletion Requests ──

                async loadDeletionRequests() {
                    this.delReqLoading = true;
                    this.delReqError = '';
                    try {
                        const result = await cdApi.get('/admin/deletion-requests');
                        this.deletionRequests = result.data.requests || [];
                    } catch (err) {
                        this.delReqError = err.message;
                        this.deletionRequests = [];
                    } finally {
                        this.delReqLoading = false;
                    }
                },

                async delReqAction(id, action) {
                    var msg = action === 'approve' ? 'Approve this deletion request? The member will be deactivated.' : 'Deny this deletion request?';
                    if (!confirm(msg)) return;
                    this.delReqActioning = id;
                    try {
                        await cdApi.put('/admin/deletion-requests/' + id, { action: action });
                        alert(action === 'approve' ? 'Deletion approved.' : 'Deletion denied.');
                        this.loadDeletionRequests();
                        this.loadDashboardStats();
                    } catch (err) {
                        alert('Error: ' + err.message);
                    } finally {
                        this.delReqActioning = null;
                    }
                },

                // ── Household Merge Requests ──

                async loadHouseholdRequests() {
                    this.hhReqLoading = true;
                    this.hhReqError = '';
                    try {
                        const result = await cdApi.get('/admin/household-requests');
                        this.householdRequests = result.data.requests || [];
                    } catch (err) {
                        this.hhReqError = err.message;
                        this.householdRequests = [];
                    } finally {
                        this.hhReqLoading = false;
                    }
                },

                async hhReqAction(id, action) {
                    var msg = action === 'approve' ? 'Approve this household merge request?' : 'Deny this household merge request?';
                    if (!confirm(msg)) return;
                    this.hhReqActioning = id;
                    try {
                        await cdApi.put('/admin/household-requests/' + id, { action: action });
                        alert(action === 'approve' ? 'Merge approved.' : 'Merge denied.');
                        this.loadHouseholdRequests();
                    } catch (err) {
                        alert('Error: ' + err.message);
                    } finally {
                        this.hhReqActioning = null;
                    }
                },

                // ── WhatsApp Group Management ──

                async loadWAGroups() {
                    this.waLoading = true;
                    this.waError = '';
                    try {
                        const result = await cdApi.get('/admin/whatsapp-groups');
                        this.waGroups = result.data.groups || [];
                    } catch (err) {
                        this.waError = err.message;
                        this.waGroups = [];
                    } finally {
                        this.waLoading = false;
                    }
                },

                waNewGroup() {
                    this.waEditing = null;
                    this.waForm = { name: '', description: '', invite_url: '', icon: '', display_order: 0, visibility: 'all', visibility_tag: '', is_active: true };
                    this.waShowForm = true;
                },

                waEditGroup(group) {
                    this.waEditing = group.id;
                    this.waForm = {
                        name: group.name,
                        description: group.description || '',
                        invite_url: group.invite_url,
                        icon: group.icon || '',
                        display_order: group.display_order || 0,
                        visibility: group.visibility || 'all',
                        visibility_tag: group.visibility_tag || '',
                        is_active: group.is_active !== false
                    };
                    this.waShowForm = true;
                },

                waCancelForm() {
                    this.waShowForm = false;
                    this.waEditing = null;
                },

                async waSaveGroup() {
                    if (!this.waForm.name || !this.waForm.invite_url) {
                        alert('Name and Invite URL are required.');
                        return;
                    }
                    this.waSaving = true;
                    try {
                        if (this.waEditing) {
                            await cdApi.put('/admin/whatsapp-groups/' + this.waEditing, this.waForm);
                        } else {
                            await cdApi.post('/admin/whatsapp-groups', this.waForm);
                        }
                        this.waShowForm = false;
                        this.waEditing = null;
                        this.loadWAGroups();
                        this.loadWhatsAppGroups(); // Refresh directory view too
                    } catch (err) {
                        alert('Error: ' + err.message);
                    } finally {
                        this.waSaving = false;
                    }
                },

                async waDeleteGroup(id) {
                    if (!confirm('Delete this WhatsApp group?')) return;
                    try {
                        await cdApi.request('/admin/whatsapp-groups/' + id, { method: 'DELETE' });
                        this.loadWAGroups();
                        this.loadWhatsAppGroups();
                    } catch (err) {
                        alert('Error: ' + err.message);
                    }
                },

                // ── Directory Preferences ──

                async loadPreferences() {
                    try {
                        const result = await cdApi.get('/members/me');
                        var prefs = result.data.directory_preferences;
                        if (prefs && prefs.default_view) {
                            // Migrate old all_first_name/all_last_name values
                            var dv = prefs.default_view;
                            var so = prefs.sort_order || 'last_name';
                            if (dv === 'all_first_name') { dv = 'all'; so = 'first_name'; }
                            if (dv === 'all_last_name') { dv = 'all'; so = 'last_name'; }
                            this.dirPrefs = {
                                default_view: dv,
                                sort_order: so,
                                search_sections: Array.isArray(prefs.search_sections) ? prefs.search_sections : ['all', 'households']
                            };
                        }
                    } catch (err) {
                        console.warn('CD: Could not load preferences', err);
                    }
                },

                async savePreferences() {
                    this.prefsSaving = true;
                    try {
                        await cdApi.put('/members/me', {
                            directory_preferences: {
                                default_view: this.settingsForm.default_view,
                                sort_order: this.settingsForm.sort_order,
                                search_sections: this.settingsForm.search_sections
                            }
                        });
                        // Apply to live state
                        this.dirPrefs = {
                            default_view: this.settingsForm.default_view,
                            sort_order: this.settingsForm.sort_order,
                            search_sections: [...this.settingsForm.search_sections]
                        };
                        this.showSettingsModal = false;
                        this.page = 1;
                        this.loadMembers();
                    } catch (err) {
                        alert('Error saving preferences: ' + err.message);
                    } finally {
                        this.prefsSaving = false;
                    }
                },

                openSettings() {
                    this.settingsForm = {
                        default_view: this.dirPrefs.default_view,
                        sort_order: this.dirPrefs.sort_order || 'last_name',
                        search_sections: [...this.dirPrefs.search_sections]
                    };
                    this.showSettingsModal = true;
                },

                viewLabel() {
                    var labels = {
                        all: 'All Members',
                        adults_only: 'Adults Only',
                        children_only: 'Children & Others',
                        primary_only: 'Primary Members Only',
                        household_view: 'Household View'
                    };
                    var sortLabels = { first_name: 'First Name A-Z', last_name: 'Last Name A-Z' };
                    var view = labels[this.dirPrefs.default_view] || 'Directory';
                    var sort = sortLabels[this.dirPrefs.sort_order] || '';
                    return sort ? view + ' \u00B7 ' + sort : view;
                },

                sectionLabel(section) {
                    var labels = {
                        all: 'All Members',
                        adults: 'Adults Only',
                        children: 'Children & Others',
                        households: 'Households'
                    };
                    return labels[section] || section;
                },

                getVisibleSections() {
                    if (this.searchQuery) {
                        // During search, use search_sections preference
                        return this.dirPrefs.search_sections || ['all', 'households'];
                    }
                    // Default view: show single section
                    var view = this.dirPrefs.default_view;
                    if (view === 'household_view') return ['households'];
                    return ['all'];
                },

                getMembersForSection(section) {
                    if (section === 'households') return []; // handled by households array
                    if (!this.searchQuery) return this.members; // API already filtered
                    // During search, filter client-side by section
                    if (section === 'all') return this.members;
                    if (section === 'adults') {
                        return this.members.filter(function (m) {
                            return !m.household_role || m.household_role === 'head' || m.household_role === 'spouse';
                        });
                    }
                    if (section === 'children') {
                        return this.members.filter(function (m) {
                            return m.household_role === 'child' || m.household_role === 'other';
                        });
                    }
                    return this.members;
                },

                sectionTitle(section) {
                    return this.sectionLabel(section);
                },

                moveSection(idx, direction) {
                    var arr = this.settingsForm.search_sections;
                    var newIdx = idx + direction;
                    if (newIdx < 0 || newIdx >= arr.length) return;
                    var temp = arr[idx];
                    arr[idx] = arr[newIdx];
                    arr[newIdx] = temp;
                    this.settingsForm.search_sections = [...arr];
                },

                removeSection(section) {
                    this.settingsForm.search_sections = this.settingsForm.search_sections.filter(function (s) { return s !== section; });
                },

                addSection(section) {
                    if (!section) return;
                    if (this.settingsForm.search_sections.indexOf(section) === -1) {
                        this.settingsForm.search_sections = [...this.settingsForm.search_sections, section];
                    }
                },

                availableSections() {
                    var all = ['all', 'adults', 'children', 'households'];
                    var current = this.settingsForm.search_sections;
                    return all.filter(function (s) { return current.indexOf(s) === -1; });
                },

                // ── Shared Helpers ──

                formatAppStatus(status) {
                    var map = { new: 'New', under_review: 'Under Review', on_hold: 'On Hold', approved: 'Approved', not_approved: 'Rejected' };
                    return map[status] || status;
                },

                formatDate(dateStr) {
                    if (!dateStr) return '';
                    try {
                        var d = new Date(dateStr);
                        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    } catch (e) {
                        return dateStr;
                    }
                }
            }; // End returned object
        } catch (e) {
            console.error('CD: Critical Error in cdDirectory', e);
            return {
                init() { },
                loading: false,
                members: [],
                households: [],
                searchQuery: '',
                page: 1,
                totalPages: 0,
                whatsappGroups: [],
                isOfficer: false,
                activeTab: 'directory',
                adminSection: 'dashboard',
                pendingAppCount: 0,
                applications: [],
                appsLoading: false,
                appCounts: {},
                dashStats: null,
                dashLoading: false,
                registrations: [],
                regsLoading: false,
                regCounts: {},
                deletionRequests: [],
                delReqLoading: false,
                householdRequests: [],
                hhReqLoading: false,
                waGroups: [],
                waLoading: false,
                waShowForm: false,
                waForm: {},
                showSettingsModal: false,
                dirPrefs: { default_view: 'adults_only', sort_order: 'last_name', search_sections: ['all', 'households'] },
                settingsForm: { default_view: 'adults_only', sort_order: 'last_name', search_sections: ['all', 'households'] },
                prefsSaving: false,
                getError() { return 'Application error: ' + e.message; },
                displayName() { return 'Error'; },
                getAvatarColor() { return '#ccc'; },
                getInitials() { return '!!'; },
                hasActiveFilters() { return false; },
                switchTab() { },
                switchAdminSection() { },
                formatAppStatus() { return ''; },
                formatDate() { return ''; },
                regStatusLabel() { return ''; },
                regStatusClass() { return ''; },
                viewLabel() { return 'Directory'; },
                getVisibleSections() { return ['all']; },
                getMembersForSection() { return []; },
                sectionTitle() { return ''; },
                sectionLabel() { return ''; },
                openSettings() { },
                savePreferences() { },
                moveSection() { },
                removeSection() { },
                addSection() { },
                availableSections() { return []; }
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
            emergency_contact_member_id: null,
            privacy_settings: {
                email: 'visible',
                phone: 'visible',
                address: 'visible',
                social: 'visible',
                date_of_birth: 'visible',
                wedding_anniversary: 'visible',
                baptism_date: 'visible',
                name_day: 'visible',
                occupation: 'visible',
                education: 'visible',
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
        ecSearchQuery: '',
        ecResults: [],
        hasDifferentAddress: false,

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
        showEditMember: false,
        editMemberLoading: false,
        editMemberSaving: false,
        editMemberForm: { member_id: null, first_name: '', last_name: '', salutation: '', email: '', phone: '', date_of_birth: '', occupation: '', employer: '', avatar_url: '' },
        editMemberPhotoUploading: false,

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

        // Photo position editor state
        hhPhotoEditor: {
            open: false,
            url: null,
            fx: 50,
            fy: 50,
            zoom: 1.0,
            saving: false,
            _dragging: false,
            _lastX: 0,
            _lastY: 0,
        },

        // Image cropper state
        cropModal: {
            show: false,
            cropper: null,
            imageUrl: '',
            aspectRatio: 1,
            uploadContext: null,
            memberId: null,
            uploading: false,
            error: '',
            zoomLevel: 0,
            originalFile: null,
            maxOutputSize: 1200,
        },

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
                this.form.emergency_contact_member_id = data.emergency_contact_member_id || null;

                // Household role (determines which fields to show)
                this.householdRole = result.data.household_role || null;

                // Load privacy settings with defaults
                const defaults = { email: 'visible', phone: 'visible', address: 'visible', social: 'visible', date_of_birth: 'visible', wedding_anniversary: 'visible', baptism_date: 'visible', name_day: 'visible', occupation: 'visible', education: 'visible' };
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

        // ── Image Crop Methods ──

        openCropModal(file, context, options) {
            options = options || {};
            if (this.cropModal.imageUrl) {
                URL.revokeObjectURL(this.cropModal.imageUrl);
            }
            if (this.cropModal.cropper) {
                this.cropModal.cropper.destroy();
                this.cropModal.cropper = null;
            }
            this.cropModal.imageUrl = URL.createObjectURL(file);
            this.cropModal.uploadContext = context;
            this.cropModal.aspectRatio = options.aspectRatio !== undefined ? options.aspectRatio : 1;
            this.cropModal.memberId = options.memberId || null;
            this.cropModal.uploading = false;
            this.cropModal.error = '';
            this.cropModal.zoomLevel = 0;
            this.cropModal.originalFile = file;
            this.cropModal.show = true;
        },

        initCropper() {
            var img = this.$refs.cropImage;
            if (!img) return;
            if (this.cropModal.cropper) {
                this.cropModal.cropper.destroy();
            }
            this.cropModal.cropper = new Cropper(img, {
                aspectRatio: this.cropModal.aspectRatio,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.85,
                responsive: true,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
                rotatable: true,
                scalable: true,
                zoomable: true,
                zoomOnTouch: true,
                zoomOnWheel: true,
                wheelZoomRatio: 0.1,
                minContainerWidth: 200,
                minContainerHeight: 200,
            });
        },

        cancelCrop() {
            if (this.cropModal.cropper) {
                this.cropModal.cropper.destroy();
                this.cropModal.cropper = null;
            }
            if (this.cropModal.imageUrl) {
                URL.revokeObjectURL(this.cropModal.imageUrl);
            }
            this.cropModal.show = false;
            this.cropModal.imageUrl = '';
            this.cropModal.originalFile = null;
            this.cropModal.error = '';
        },

        async confirmCrop() {
            if (!this.cropModal.cropper) return;
            this.cropModal.uploading = true;
            this.cropModal.error = '';

            try {
                var maxSize = this.cropModal.maxOutputSize;
                var cropData = this.cropModal.cropper.getData();
                var outputWidth = Math.round(cropData.width);
                var outputHeight = Math.round(cropData.height);

                if (outputWidth > maxSize || outputHeight > maxSize) {
                    var scale = maxSize / Math.max(outputWidth, outputHeight);
                    outputWidth = Math.round(outputWidth * scale);
                    outputHeight = Math.round(outputHeight * scale);
                }

                var canvas = this.cropModal.cropper.getCroppedCanvas({
                    width: outputWidth,
                    height: outputHeight,
                    imageSmoothingEnabled: true,
                    imageSmoothingQuality: 'high',
                    fillColor: '#ffffff',
                });

                if (!canvas) {
                    this.cropModal.error = 'Failed to create cropped image.';
                    this.cropModal.uploading = false;
                    return;
                }

                var blob = await new Promise(function (resolve, reject) {
                    canvas.toBlob(
                        function (b) { b ? resolve(b) : reject(new Error('Failed to create image.')); },
                        'image/jpeg', 0.9
                    );
                });

                var originalName = (this.cropModal.originalFile && this.cropModal.originalFile.name) || 'photo.jpg';
                var fileName = originalName.replace(/\.[^.]+$/, '') + '-cropped.jpg';
                var formData = new FormData();
                formData.append('file', blob, fileName);

                var ctx = this.cropModal.uploadContext;
                var url;
                if (ctx === 'avatar' || ctx === 'camera') {
                    url = cdConfig.apiUrl + '/members/avatar';
                } else if (ctx === 'household') {
                    url = cdConfig.apiUrl + '/members/me/household/photo';
                } else if (ctx === 'editMember') {
                    url = cdConfig.apiUrl + '/members/me/household/members/' + this.cropModal.memberId + '/avatar';
                }

                var response = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': cdConfig.nonce },
                    credentials: 'same-origin',
                    body: formData,
                });

                var responseData = await response.json();
                if (!response.ok) {
                    throw new Error(responseData.message || responseData.error?.message || 'Upload failed (HTTP ' + response.status + ')');
                }

                // Route success handling by context
                if (ctx === 'avatar' || ctx === 'camera') {
                    if (responseData.data && responseData.data.url) {
                        this.form.avatar_url = responseData.data.url;
                        this.successMessage = ctx === 'camera' ? 'Photo captured and uploaded.' : 'Avatar uploaded successfully.';
                    }
                } else if (ctx === 'household') {
                    if (responseData.data && responseData.data.photos) {
                        this.household.photos = responseData.data.photos;
                        this.household.photo_url = responseData.data.photos[0] || '';
                        this.householdMessage = responseData.data.message || 'Family photo uploaded.';
                    }
                } else if (ctx === 'editMember') {
                    if (responseData.data && responseData.data.url) {
                        this.editMemberForm.avatar_url = responseData.data.url;
                        this.householdMessage = responseData.data.message || 'Photo uploaded.';
                    }
                }

                this.cancelCrop();
            } catch (err) {
                this.cropModal.error = err.message || 'Upload failed. Please try again.';
            } finally {
                this.cropModal.uploading = false;
            }
        },

        cropZoom(delta) {
            if (this.cropModal.cropper) this.cropModal.cropper.zoom(delta);
        },

        cropZoomTo(value) {
            if (this.cropModal.cropper) this.cropModal.cropper.zoomTo(Math.pow(2, parseFloat(value)));
        },

        cropRotate(degrees) {
            if (this.cropModal.cropper) this.cropModal.cropper.rotate(degrees);
        },

        cropReset() {
            if (this.cropModal.cropper) {
                this.cropModal.cropper.reset();
                this.cropModal.zoomLevel = 0;
            }
        },

        // ── Photo Upload Entry Points ──

        uploadAvatar(event) {
            const file = event.target.files[0];
            if (!file) return;
            event.target.value = '';
            this.openCropModal(file, 'avatar', { aspectRatio: 1 });
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

            if (!video.videoWidth || !video.videoHeight) {
                this.errorMessage = 'Camera not ready yet. Please wait a moment and try again.';
                return;
            }

            // Capture full frame — let crop modal handle framing
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, video.videoWidth, video.videoHeight);

            this.stopCamera();

            canvas.toBlob((blob) => {
                if (!blob) {
                    this.errorMessage = 'Failed to capture photo. Please try again.';
                    return;
                }
                this.openCropModal(blob, 'camera', { aspectRatio: 1 });
            }, 'image/jpeg', 0.92);
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

        // Emergency contact directory search
        async searchEmergencyContact() {
            if (this.ecSearchQuery.length < 2) {
                this.ecResults = [];
                return;
            }
            try {
                const result = await cdApi.get('/directory?search=' + encodeURIComponent(this.ecSearchQuery) + '&per_page=8');
                this.ecResults = (result.data.members || []).map(m => ({
                    member_id: m.id || m.member_id,
                    first_name: m.first_name,
                    last_name: m.last_name,
                    phone: m.phone || '',
                }));
            } catch (err) {
                this.ecResults = [];
            }
        },

        selectEmergencyContact(ec) {
            this.form.emergency_contact_name = ec.first_name + ' ' + ec.last_name;
            this.form.emergency_contact_phone = ec.phone || '';
            this.form.emergency_contact_member_id = ec.member_id;
            this.ecSearchQuery = '';
            this.ecResults = [];
        },

        setManualEmergencyContact() {
            this.form.emergency_contact_name = this.ecSearchQuery;
            this.form.emergency_contact_member_id = null;
            this.ecSearchQuery = '';
            this.ecResults = [];
        },

        clearEmergencyContact() {
            this.form.emergency_contact_name = '';
            this.form.emergency_contact_phone = '';
            this.form.emergency_contact_member_id = null;
            this.ecSearchQuery = '';
            this.ecResults = [];
        },

        // Household photo upload (multi-photo: opens crop modal with free aspect)
        uploadHouseholdPhoto(event) {
            const file = event.target.files[0];
            if (!file) return;
            event.target.value = '';

            if (this.household.photos && this.household.photos.length >= 10) {
                this.householdError = 'Maximum 10 photos allowed. Delete one to add another.';
                return;
            }

            this.openCropModal(file, 'household', { aspectRatio: NaN });
        },

        async deleteHouseholdPhoto(photoUrl) {
            if (!confirm('Remove this family photo?')) return;
            this.uploadingHouseholdPhoto = true;
            this.householdError = '';
            try {
                await cdApi.request('/members/me/household/photo', {
                    method: 'DELETE',
                    body: JSON.stringify({ photo_url: photoUrl }),
                });
                // Remove from local array (handle new object format)
                if (this.household.photos) {
                    this.household.photos = this.household.photos.filter(p =>
                        (typeof p === 'object' ? p.url : p) !== photoUrl
                    );
                    const firstPhoto = this.household.photos[0];
                    this.household.photo_url = firstPhoto
                        ? (typeof firstPhoto === 'object' ? firstPhoto.url : firstPhoto)
                        : '';
                }
                this.householdMessage = 'Family photo removed.';
            } catch (err) {
                this.householdError = err.message;
            } finally {
                this.uploadingHouseholdPhoto = false;
            }
        },

        // Photo position editor
        openPhotoEditor(photo) {
            // photo may be an object {url, fx, fy, zoom} or legacy string
            const p = typeof photo === 'object' ? photo : { url: photo, fx: 50, fy: 50, zoom: 1.0 };
            this.hhPhotoEditor.url = p.url;
            this.hhPhotoEditor.fx = p.fx ?? 50;
            this.hhPhotoEditor.fy = p.fy ?? 50;
            this.hhPhotoEditor.zoom = p.zoom ?? 1.0;
            this.hhPhotoEditor.saving = false;
            this.hhPhotoEditor.open = true;
        },

        hhEditorDragStart(e) {
            this.hhPhotoEditor._dragging = true;
            const pos = e.touches ? e.touches[0] : e;
            this.hhPhotoEditor._lastX = pos.clientX;
            this.hhPhotoEditor._lastY = pos.clientY;
        },

        hhEditorDragMove(e) {
            if (!this.hhPhotoEditor._dragging) return;
            const pos = e.touches ? e.touches[0] : e;
            const dx = pos.clientX - this.hhPhotoEditor._lastX;
            const dy = pos.clientY - this.hhPhotoEditor._lastY;
            this.hhPhotoEditor._lastX = pos.clientX;
            this.hhPhotoEditor._lastY = pos.clientY;

            const el = document.getElementById('cd-photo-editor-preview');
            if (!el) return;
            const rect = el.getBoundingClientRect();

            // Convert pixel delta to percentage of the container, reversed (drag right → focal moves right)
            const pctX = (dx / rect.width) * 100;
            const pctY = (dy / rect.height) * 100;

            this.hhPhotoEditor.fx = Math.max(0, Math.min(100, this.hhPhotoEditor.fx - pctX));
            this.hhPhotoEditor.fy = Math.max(0, Math.min(100, this.hhPhotoEditor.fy - pctY));
        },

        hhEditorDragEnd() {
            this.hhPhotoEditor._dragging = false;
        },

        async savePhotoPosition() {
            this.hhPhotoEditor.saving = true;
            this.householdError = '';
            try {
                const result = await cdApi.request('/members/me/household/photo-position', {
                    method: 'PATCH',
                    body: JSON.stringify({
                        url: this.hhPhotoEditor.url,
                        fx: parseFloat(this.hhPhotoEditor.fx),
                        fy: parseFloat(this.hhPhotoEditor.fy),
                        zoom: parseFloat(this.hhPhotoEditor.zoom),
                    }),
                });
                // Update local household photos array with new positions
                if (result.data && result.data.photos) {
                    this.household.photos = result.data.photos;
                    const first = this.household.photos[0];
                    this.household.photo_url = first ? (typeof first === 'object' ? first.url : first) : '';
                }
                this.hhPhotoEditor.open = false;
                this.householdMessage = 'Photo position saved.';
            } catch (err) {
                this.householdError = err.message || 'Failed to save position.';
            } finally {
                this.hhPhotoEditor.saving = false;
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
                if (this.household) {
                    this.hasDifferentAddress = this.household.has_different_address || false;
                    // Ensure photos is always an array; don't coerce objects to strings
                    if (!Array.isArray(this.household.photos)) {
                        this.household.photos = this.household.photo_url
                            ? [{ url: this.household.photo_url, fx: 50, fy: 50, zoom: 1.0 }]
                            : [];
                    }
                }
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

        async openEditMember(memberId) {
            this.editMemberLoading = true;
            this.showEditMember = true;
            this.householdError = '';
            try {
                const result = await cdApi.get('/members/me/household/members/' + memberId);
                const m = result.data.member;
                this.editMemberForm = {
                    member_id: m.member_id,
                    first_name: m.first_name || '',
                    last_name: m.last_name || '',
                    salutation: m.salutation || '',
                    email: m.primary_email || '',
                    phone: m.phone || '',
                    date_of_birth: m.date_of_birth || '',
                    occupation: m.occupation || '',
                    employer: m.employer || '',
                    avatar_url: m.avatar_url || '',
                };
            } catch (err) {
                this.householdError = err.message;
                this.showEditMember = false;
            } finally {
                this.editMemberLoading = false;
            }
        },

        async saveEditMember() {
            const f = this.editMemberForm;
            if (!f.first_name.trim() || !f.last_name.trim()) {
                this.householdError = 'First name and last name are required.';
                return;
            }
            this.editMemberSaving = true;
            this.householdError = '';
            try {
                const result = await cdApi.request('/members/me/household/members/' + f.member_id, {
                    method: 'PUT',
                    body: JSON.stringify({
                        first_name: f.first_name.trim(),
                        last_name: f.last_name.trim(),
                        salutation: f.salutation.trim(),
                        email: f.email.trim(),
                        phone: f.phone.trim(),
                        date_of_birth: f.date_of_birth,
                        occupation: f.occupation.trim(),
                        employer: f.employer.trim(),
                    }),
                });
                this.householdMessage = result.data.message || 'Member details updated.';
                this.showEditMember = false;
                await this.loadHousehold();
            } catch (err) {
                this.householdError = err.message;
            } finally {
                this.editMemberSaving = false;
            }
        },

        uploadEditMemberPhoto(event) {
            const file = event.target.files[0];
            if (!file) return;
            const memberId = this.editMemberForm.member_id;
            if (!memberId) return;
            event.target.value = '';
            this.openCropModal(file, 'editMember', { aspectRatio: 1, memberId: memberId });
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

            const isHeadOrSpouse = this.householdRole === 'head' || this.householdRole === 'spouse';

            // Validate email — required for head/spouse only
            const validEmails = this.form.emails.filter(e => e.value.trim() !== '');
            if (isHeadOrSpouse && validEmails.length === 0) {
                this.errorMessage = 'Primary membership holders and spouses must provide at least one email address.';
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

            // Validate phone — required for head/spouse only
            const validPhones = this.form.phones.filter(p => p.value.trim() !== '');
            if (isHeadOrSpouse && validPhones.length === 0) {
                this.errorMessage = 'Primary membership holders and spouses must provide at least one phone number.';
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            this.saving = true;

            try {
                const payload = {
                    ...this.form,
                    emails: validEmails,
                    phones: this.form.phones.filter(p => p.value.trim() !== ''),
                    social_links: this.form.social_links.filter(s => s.url.trim() !== ''),
                };

                // Include address inheritance flag if in a household
                if (this.household) {
                    payload.has_different_address = this.hasDifferentAddress;
                }

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
