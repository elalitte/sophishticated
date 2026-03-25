<template>
  <div class="min-h-screen bg-brand-700 flex items-center justify-center px-4">
    <div class="w-full max-w-md">
      <!-- Logo & Title -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand-500 mb-4 shadow-lg">
          <ShieldCheck class="w-9 h-9 text-white" />
        </div>
        <h1 class="text-3xl font-bold text-white tracking-tight">Sophishticated</h1>
        <p class="text-brand-200 mt-1 text-sm">Phishing awareness platform</p>
      </div>

      <!-- Login Card -->
      <div class="bg-white rounded-2xl shadow-2xl p-8">
        <h2 class="text-xl font-semibold text-gray-800 mb-6">Connexion</h2>

        <!-- Error message -->
        <Transition name="fade">
          <div
            v-if="errorMessage"
            class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2"
          >
            <AlertCircle class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
            <p class="text-sm text-red-700">{{ errorMessage }}</p>
          </div>
        </Transition>

        <!-- Rate limit warning -->
        <Transition name="fade">
          <div
            v-if="isRateLimited"
            class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-2"
          >
            <ShieldAlert class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
            <p class="text-sm text-amber-700">
              Trop de tentatives echouees. Veuillez patienter avant de reessayer.
            </p>
          </div>
        </Transition>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <!-- Username -->
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700 mb-1.5">
              Nom d'utilisateur
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <User class="w-4 h-4 text-gray-400" />
              </div>
              <input
                id="username"
                v-model="form.username"
                type="text"
                required
                autocomplete="username"
                :disabled="isLoading || isRateLimited"
                placeholder="Votre identifiant"
                class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed"
              />
            </div>
          </div>

          <!-- Password -->
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">
              Mot de passe
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <Lock class="w-4 h-4 text-gray-400" />
              </div>
              <input
                id="password"
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                :disabled="isLoading || isRateLimited"
                placeholder="Votre mot de passe"
                class="w-full pl-10 pr-10 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-colors disabled:bg-gray-100 disabled:cursor-not-allowed"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
              >
                <EyeOff v-if="showPassword" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="isLoading || isRateLimited || !form.username || !form.password"
            class="w-full flex items-center justify-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-medium py-2.5 px-4 rounded-lg transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Loader2 v-if="isLoading" class="w-4 h-4 animate-spin" />
            <LogIn v-else class="w-4 h-4" />
            <span>{{ isLoading ? 'Connexion en cours...' : 'Se connecter' }}</span>
          </button>
        </form>
      </div>

      <!-- Footer -->
      <p class="text-center text-brand-300 text-xs mt-6">
        Sophishticated &mdash; Phishing simulation platform
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  ShieldCheck,
  User,
  Lock,
  Eye,
  EyeOff,
  LogIn,
  Loader2,
  AlertCircle,
  ShieldAlert
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const form = reactive({
  username: '',
  password: ''
})

const isLoading = ref(false)
const showPassword = ref(false)
const errorMessage = ref('')
const failureCount = ref(0)

const isRateLimited = computed(() => failureCount.value >= 5)

async function handleSubmit() {
  if (isLoading.value || isRateLimited.value) return

  isLoading.value = true
  errorMessage.value = ''

  try {
    const data = await authStore.login(form.username, form.password)
    failureCount.value = 0

    // Redirect based on user state
    if (data.user?.must_change_password) {
      router.push('/change-password')
    } else {
      const redirect = route.query.redirect || '/dashboard'
      router.push(redirect)
    }
  } catch (err) {
    failureCount.value++
    errorMessage.value = err.message || 'Identifiants incorrects'

    if (isRateLimited.value) {
      // Auto-reset rate limit after 60 seconds
      setTimeout(() => {
        failureCount.value = 0
      }, 60000)
    }
  } finally {
    isLoading.value = false
  }
}
</script>
