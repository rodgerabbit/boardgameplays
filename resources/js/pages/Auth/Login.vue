<template>
    <div class="container" style="max-width: 500px; margin-top: 5rem;">
        <div class="row">
            <div class="twelve columns">
                <h2 style="text-align: center; margin-bottom: 2rem;">Login</h2>

                <form @submit.prevent="submit">
                    <!-- Email -->
                    <label for="email">Email</label>
                    <input
                        class="u-full-width"
                        type="email"
                        id="email"
                        v-model="form.email"
                        required
                        autofocus
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
                        autocomplete="current-password"
                    />
                    <div v-if="form.errors.password" style="color: #d32f2f; margin-top: 0.5rem;">
                        {{ form.errors.password }}
                    </div>

                    <!-- Remember Me -->
                    <label style="margin-top: 1rem;">
                        <input
                            type="checkbox"
                            v-model="form.remember"
                        />
                        <span class="label-body">Remember me</span>
                    </label>

                    <!-- Submit Button -->
                    <button
                        class="button-primary u-full-width"
                        type="submit"
                        style="margin-top: 1.5rem;"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Logging in...' : 'Login' }}
                    </button>
                </form>

                <!-- General Errors -->
                <div v-if="form.errors.message" style="color: #d32f2f; margin-top: 1rem; text-align: center;">
                    {{ form.errors.message }}
                </div>

                <!-- Register Link -->
                <div style="text-align: center; margin-top: 1.5rem;">
                    <p style="color: #555555;">
                        Don't have an account?
                        <Link :href="route('register')" style="color: #000000; text-decoration: underline;">
                            Register here
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
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

