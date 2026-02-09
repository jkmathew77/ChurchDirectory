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

/* ─── Login Page ─── */
function cdLogin() {
    return {
        email: '',
        password: '',
        loading: false,
        errorMessage: '',
        successMessage: '',
        showForgotPassword: false,
        showForgotEmail: false,
        resetEmail: '',
        resetSent: false,
        lookupName: '',
        lookupPhone: '',
        emailLookupSent: false,

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
                window.location.href = cdConfig.baseUrl + '/directory/';
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
                // Get the Google OAuth URL from our API
                const result = await cdApi.get('/auth/google');
                if (result.data && result.data.auth_url) {
                    window.location.href = result.data.auth_url;
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
                // Don't reveal whether the email exists — always show success
            } finally {
                this.resetSent = true;
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
    };
}

/* ─── Application Form ─── */
function cdApplication() {
    return {
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
    };
}

/* ─── Email Verification ─── */
function cdVerify() {
    return {
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
    };
}

/* ─── Invite Acceptance ─── */
function cdInvite() {
    return {
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
                await cdApi.post('/invites/accept', {
                    token: token,
                    email: this.email,
                    password: this.password,
                });

                this.success = true;
                // Redirect to directory after brief delay
                setTimeout(() => {
                    window.location.href = cdConfig.baseUrl + '/directory/';
                }, 2000);
            } catch (err) {
                this.errorMessage = err.message;
            } finally {
                this.creating = false;
            }
        },
    };
}

/* ─── Directory (Phase 3 stub) ─── */
function cdDirectory() {
    return {
        searchQuery: '',
        members: [],
        loading: false,

        search() {
            // Phase 3 implementation
        },

        logout() {
            // Use WP logout URL
            window.location.href = cdConfig.baseUrl + '/login/?logged_out=1';
        },
    };
}

/* ─── Member Profile View (Phase 3 stub) ─── */
function cdMemberProfile() {
    return {
        member: null,
        loading: true,

        async init() {
            // Phase 3: fetch member by UUID from window.cdMemberUuid
            this.loading = false;
        },
    };
}

/* ─── Edit Profile (Phase 3 stub) ─── */
function cdEditProfile() {
    return {
        form: {},
        loading: true,

        async init() {
            // Phase 3: load current user's profile for editing
            this.loading = false;
        },
    };
}
