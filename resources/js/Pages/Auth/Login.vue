<template>
    <div class="flex min-h-screen items-center justify-center bg-zinc-50 dark:bg-zinc-950">
        <div class="w-full max-w-sm space-y-6 px-4">
            <div class="space-y-2 text-center">
                <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50">
                    Fondeks Feed
                </h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Yönetim paneline giriş yapın
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-2">
                    <label for="email" class="text-sm font-medium text-zinc-900 dark:text-zinc-50">
                        E-posta
                    </label>
                    <InputText
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="w-full"
                        placeholder="ornek@email.com"
                        :invalid="!!form.errors.email"
                        autocomplete="email"
                    />
                    <small v-if="form.errors.email" class="text-red-500 text-xs">
                        {{ form.errors.email }}
                    </small>
                </div>

                <div class="space-y-2">
                    <label for="password" class="text-sm font-medium text-zinc-900 dark:text-zinc-50">
                        Şifre
                    </label>
                    <Password
                        id="password"
                        v-model="form.password"
                        :feedback="false"
                        toggleMask
                        class="w-full"
                        inputClass="w-full"
                        :invalid="!!form.errors.password"
                        autocomplete="current-password"
                    />
                    <small v-if="form.errors.password" class="text-red-500 text-xs">
                        {{ form.errors.password }}
                    </small>
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.remember" :binary="true" inputId="remember" />
                    <label for="remember" class="text-sm text-zinc-600 dark:text-zinc-400">
                        Beni hatırla
                    </label>
                </div>

                <Button
                    type="submit"
                    label="Giriş Yap"
                    class="w-full"
                    :loading="form.processing"
                />
            </form>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>
