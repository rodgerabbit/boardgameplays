<template>
    <div class="mx-auto mt-20 max-w-md">
        <h2 class="mb-8 text-center text-xl font-medium text-text-dark">Login</h2>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Email -->
            <label for="email" class="block text-sm font-medium text-text-dark">Email</label>
            <input
                id="email"
                v-model="form.email"
                type="email"
                required
                autofocus
                autocomplete="email"
                class="w-full rounded-lg border border-surface-darker bg-surface-dark px-3 py-2 text-text-dark placeholder-text-muted-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            />
            <p v-if="form.errors.email" class="text-sm text-primary">
                {{ form.errors.email }}
            </p>

            <!-- Password -->
            <label for="password" class="block text-sm font-medium text-text-dark">Password</label>
            <input
                id="password"
                v-model="form.password"
                type="password"
                required
                autocomplete="current-password"
                class="w-full rounded-lg border border-surface-darker bg-surface-dark px-3 py-2 text-text-dark placeholder-text-muted-dark focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            />
            <p v-if="form.errors.password" class="text-sm text-primary">
                {{ form.errors.password }}
            </p>

            <!-- Remember Me -->
            <label class="flex items-center gap-2 text-sm text-text-dark">
                <input
                    v-model="form.remember"
                    type="checkbox"
                    class="rounded border-surface-darker bg-surface-dark text-primary focus:ring-primary"
                />
                <span>Remember me</span>
            </label>

            <!-- Submit Button -->
            <button
                type="submit"
                :disabled="form.processing"
                class="mt-6 w-full rounded-lg border border-border bg-primary py-2.5 font-medium text-text-primary shadow-cartoon disabled:cursor-not-allowed disabled:opacity-50 hover:bg-primary-hover"
            >
                {{ form.processing ? 'Logging in...' : 'Login' }}
            </button>
        </form>

        <p v-if="form.errors.message" class="mt-4 text-center text-sm text-primary">
            {{ form.errors.message }}
        </p>

        <p class="mt-6 text-center text-sm text-text-muted-dark">
            Don't have an account?
            <Link :href="route('register')" class="text-accent underline hover:no-underline">
                Register here
            </Link>
        </p>
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
