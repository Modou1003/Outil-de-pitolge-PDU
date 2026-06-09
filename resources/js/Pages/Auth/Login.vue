<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Connexion" />

    <div class="relative h-screen overflow-hidden bg-[#1d4038]">
        <div
            class="absolute inset-0 bg-cover bg-center bg-no-repeat"
            style="background-image: url('/images/login/background-droite.png');"
        ></div>
        <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(14,74,64,0.55)_0%,rgba(16,89,75,0.50)_50%,rgba(22,36,46,0.10)_100%)]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_75%_18%,rgba(255,255,255,0.18),transparent_36%)]"></div>
        <div class="absolute inset-y-0 left-1/2 hidden w-px bg-white/20 lg:block"></div>

        <!-- Barre de logos en haut : armoiries à gauche, logo ministère à droite -->
        <div class="absolute top-0 left-0 right-0 z-30 hidden lg:grid grid-cols-2 py-5">
            <div class="flex items-center justify-center">
                <img src="/images/login/logo-armoiries.png" alt="Armoiries" class="h-[120px] w-[120px] object-contain drop-shadow-md" />
            </div>
            <div class="flex items-center justify-center">
                <div class="flex items-center gap-3 rounded-md bg-white/80 px-4 py-2 shadow-md">
                    <img src="/images/login/logo-ministere.png" alt="Logo ministere" class="h-[66px] object-contain" />
                </div>
            </div>
        </div>

        <div class="relative z-10 grid h-screen lg:grid-cols-2">
            <section class="hidden lg:flex flex-col px-8 py-7 text-white xl:px-10">
                <div class="mt-[140px] max-w-[500px]">
                    <h1 class="text-[57px] font-bold leading-[1.06] tracking-[-0.01em]">
                        Programme de
                        <br />
                        Decentralisation des Universites
                        <br />
                        
                    </h1>
                    <p class="mt-5 max-w-[470px] text-[17px] leading-[1.38] text-white/74">
                        Plateforme de pilotage et de suivi-evaluation des projets d'infrastructures universitaires du MESRS de Cote d'Ivoire
                    </p>
                </div>
            </section>

            <section class="relative flex items-center justify-center px-4 py-6 sm:px-8 lg:px-12 lg:pt-[140px]">

                <div class="w-full max-w-[520px] rounded-xl border border-orange-400/30 bg-[rgba(255,165,0,0.25)] px-6 py-6 shadow-[0_16px_40px_rgba(0,0,0,0.22)] backdrop-blur-[6px] sm:px-7">
                    <h2 class="text-[32px] font-semibold leading-none tracking-[-0.015em] text-black">Connexion</h2>
                    <p class="mt-1.5 text-[18px] leading-[1.13] text-black/78">Accedez a la plateforme avec vos identifiants.</p>

                    <div v-if="status" class="mt-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700">
                        {{ status }}
                    </div>

                    <form class="mt-5 space-y-3" @submit.prevent="submit">
                        <div>
                            <label for="email" class="block text-[15px] font-medium leading-none text-black/85">Adresse e-mail</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="nom@pdu-tracker.local"
                                class="mt-1.5 block h-[44px] w-full rounded-[8px] border border-black/10 bg-white/98 px-4 text-[15px] text-black/80 placeholder:text-black/35 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-300"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div>
                            <label for="password" class="block text-[15px] font-medium leading-none text-black/85">Mot de passe</label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="........"
                                class="mt-1.5 block h-[44px] w-full rounded-[8px] border border-black/10 bg-white/98 px-4 text-[15px] text-black/80 placeholder:text-black/35 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-300"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-between gap-3 pt-1 text-[14px]">
                            <label class="flex items-center text-black/80">
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                    name="remember"
                                    class="h-3.5 w-3.5 rounded-[2px] border border-gray-300 text-emerald-700 focus:ring-emerald-400"
                                />
                                <span class="ms-2">Se souvenir de moi</span>
                            </label>

                            <Link
                                v-if="canResetPassword"
                                :href="route('password.request')"
                                class="text-black/80 hover:text-black hover:underline"
                            >
                                Mot de passe oublie ?
                            </Link>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="mt-1 flex h-[46px] w-full items-center justify-center gap-2 rounded-[9px] bg-[#25824d] text-[16px] font-medium text-white shadow-[0_8px_20px_rgba(6,95,70,0.35)] transition hover:bg-[#1f7445] focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:opacity-70"
                        >
                            <span>{{ form.processing ? 'Connexion...' : 'Se connecter' }}</span>
                            <span aria-hidden="true">→</span>
                        </button>
                    </form>

                    <p class="mt-4 text-[13px] text-black/75">Accès reservé aux utilisateurs autorises.</p>
                </div>
            </section>
        </div>

        <!-- Logo PDU au centre (au-dessus du fond) -->
        <div class="absolute left-[calc(50%-30px)] top-[50%] z-20 hidden lg:block -translate-x-1/2 -translate-y-1/2">
            <img src="/images/login/logo-pdu.png" alt="Logo PDU" class="h-[92px] w-[160px] rounded-full object-contain drop-shadow-md" />
        </div>
    </div>
</template>
