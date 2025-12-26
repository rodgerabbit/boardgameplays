<template>
    <div class="container" style="max-width: 500px; margin-top: 5rem;">
        <div class="row">
            <div class="twelve columns">
                <h2 style="text-align: center; margin-bottom: 2rem;">Register</h2>

                <form @submit.prevent="submit">
                    <!-- Name -->
                    <label for="name">Name</label>
                    <input
                        class="u-full-width"
                        type="text"
                        id="name"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                    />
                    <div v-if="form.errors.name" style="color: #d32f2f; margin-top: 0.5rem;">
                        {{ form.errors.name }}
                    </div>

                    <!-- Email -->
                    <label for="email" style="margin-top: 1rem;">Email</label>
                    <input
                        class="u-full-width"
                        type="email"
                        id="email"
                        v-model="form.email"
                        required
                        autocomplete="email"
                    />
                    <div v-if="form.errors.email" style="color: #d32f2f; margin-top: 0.5rem;">
                        {{ form.errors.email }}
                    </div>

                    <!-- Password -->
                    <label for="password" style="margin-top: 1rem;">Password</label>
                    <input
                        class="u-full-width"
                        type="password"
                        id="password"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                    />
                    <div v-if="form.errors.password" style="color: #d32f2f; margin-top: 0.5rem;">
                        {{ form.errors.password }}
                    </div>

                    <!-- Password Confirmation -->
                    <label for="password_confirmation" style="margin-top: 1rem;">Confirm Password</label>
                    <input
                        class="u-full-width"
                        type="password"
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                    />
                    <div v-if="form.errors.password_confirmation" style="color: #d32f2f; margin-top: 0.5rem;">
                        {{ form.errors.password_confirmation }}
                    </div>

                    <!-- Submit Button -->
                    <button
                        class="button-primary u-full-width"
                        type="submit"
                        style="margin-top: 1.5rem;"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Registering...' : 'Register' }}
                    </button>
                </form>

                <!-- General Errors -->
                <div v-if="form.errors.message" style="color: #d32f2f; margin-top: 1rem; text-align: center;">
                    {{ form.errors.message }}
                </div>

                <!-- Login Link -->
                <div style="text-align: center; margin-top: 1.5rem;">
                    <p style="color: #555555;">
                        Already have an account?
                        <Link :href="route('login')" style="color: #000000; text-decoration: underline;">
                            Login here
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

