<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { onMounted } from 'vue';
import Button from '@/components/ui/Button.vue';
import Checkbox from '@/components/ui/Checkbox.vue';
import FormField from '@/components/ui/FormField.vue';
import TextInput from '@/components/ui/TextInput.vue';
import { email, required, useFormValidation } from '@/lib/validation';
import { useThemeStore } from '@/stores/theme';

const theme = useThemeStore();
onMounted(() => theme.init());

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

// Mirrors app/Http/Requests/Auth/LoginRequest.php.
const v = useFormValidation(form, {
    email: [required('Email address'), email()],
    password: [required('Password')],
});

function submit(): void {
    // Client-side checks catch empty fields without a round-trip. Credentials
    // themselves are only ever verified server-side (§42, §101.16).
    if (!v.validateAndFocus()) {
        return;
    }

    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Sign in" />

    <div class="flex min-h-dvh flex-col bg-page">
        <div class="mx-auto w-full max-w-[78rem] px-5 py-6 lg:px-8">
            <Link
                href="/"
                class="inline-flex items-center gap-1.5 text-[0.9rem] text-muted transition-colors hover:text-strong"
            >
                <ArrowLeft class="size-4" />
                Back to site
            </Link>
        </div>

        <div class="flex flex-1 items-center justify-center px-4 pb-20">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex flex-col items-center text-center">
                    <img
                        src="/brand/revora/app-icon-192.png"
                        alt=""
                        class="mb-4 size-14 rounded-xl"
                    />
                    <h1
                        class="font-display text-[1.6rem] font-bold tracking-[-0.02em] text-strong"
                    >
                        Sign in
                    </h1>
                    <p class="mt-1 text-[0.92rem] text-muted">
                        Welcome back. Pick up where you left off.
                    </p>
                </div>

                <form
                    class="space-y-5 rounded-xl border border-border bg-surface p-6 shadow-card"
                    novalidate
                    @submit.prevent="submit"
                >
                    <FormField
                        id="email"
                        label="Email address"
                        required
                        :error="v.errors.value.email"
                    >
                        <TextInput
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            placeholder="you@company.com"
                            required
                            :invalid="Boolean(v.errors.value.email)"
                            @blur="v.touch('email')"
                            @update:model-value="v.revalidate('email')"
                        />
                    </FormField>

                    <FormField
                        id="password"
                        label="Password"
                        required
                        :error="v.errors.value.password"
                    >
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="••••••••"
                            required
                            :invalid="Boolean(v.errors.value.password)"
                            @blur="v.touch('password')"
                            @update:model-value="v.revalidate('password')"
                        />
                    </FormField>

                    <div class="flex items-center gap-2.5">
                        <Checkbox id="remember" v-model="form.remember" />
                        <label
                            for="remember"
                            class="cursor-pointer text-[0.88rem] text-muted"
                        >
                            Keep me signed in
                        </label>
                    </div>

                    <Button
                        type="submit"
                        variant="brand"
                        size="lg"
                        class="w-full"
                        :loading="form.processing"
                    >
                        Sign in
                    </Button>
                </form>
            </div>
        </div>
    </div>
</template>
