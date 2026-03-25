<template>
  <AppLayout>
    <div class="max-w-lg mx-auto">
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
        <div class="flex items-center gap-3 mb-6">
          <div class="p-2 bg-brand-100 rounded-lg">
            <KeyRound class="w-6 h-6 text-brand-600" />
          </div>
          <div>
            <h2 class="text-xl font-semibold text-gray-800">Changer le mot de passe</h2>
            <p class="text-sm text-gray-500">Choisissez un mot de passe securise</p>
          </div>
        </div>

        <!-- Success message -->
        <Transition name="fade">
          <div
            v-if="successMessage"
            class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg flex items-start gap-2"
          >
            <CheckCircle2 class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" />
            <p class="text-sm text-green-700">{{ successMessage }}</p>
          </div>
        </Transition>

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

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <!-- Old password -->
          <div>
            <label for="old-password" class="block text-sm font-medium text-gray-700 mb-1.5">
              Mot de passe actuel
            </label>
            <input
              id="old-password"
              v-model="form.oldPassword"
              type="password"
              required
              autocomplete="current-password"
              :disabled="isLoading"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-colors disabled:bg-gray-100"
            />
          </div>

          <!-- New password -->
          <div>
            <label for="new-password" class="block text-sm font-medium text-gray-700 mb-1.5">
              Nouveau mot de passe
            </label>
            <input
              id="new-password"
              v-model="form.newPassword"
              type="password"
              required
              autocomplete="new-password"
              :disabled="isLoading"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-colors disabled:bg-gray-100"
            />
            <!-- Validation rules -->
            <div class="mt-2 space-y-1">
              <div
                v-for="rule in validationRules"
                :key="rule.label"
                class="flex items-center gap-2 text-xs"
              >
                <CheckCircle2
                  v-if="rule.valid"
                  class="w-3.5 h-3.5 text-green-500"
                />
                <XCircle
                  v-else
                  class="w-3.5 h-3.5 text-gray-300"
                />
                <span :class="rule.valid ? 'text-green-600' : 'text-gray-500'">
                  {{ rule.label }}
                </span>
              </div>
            </div>
          </div>

          <!-- Confirm password -->
          <div>
            <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-1.5">
              Confirmer le nouveau mot de passe
            </label>
            <input
              id="confirm-password"
              v-model="form.confirmPassword"
              type="password"
              required
              autocomplete="new-password"
              :disabled="isLoading"
              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-colors disabled:bg-gray-100"
            />
            <p
              v-if="form.confirmPassword && !passwordsMatch"
              class="mt-1 text-xs text-red-500 flex items-center gap-1"
            >
              <AlertCircle class="w-3 h-3" />
              Les mots de passe ne correspondent pas
            </p>
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="isLoading || !isFormValid"
            class="w-full flex items-center justify-center gap-2 bg-brand-500 hover:bg-brand-600 text-white font-medium py-2.5 px-4 rounded-lg transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Loader2 v-if="isLoading" class="w-4 h-4 animate-spin" />
            <span>{{ isLoading ? 'Modification en cours...' : 'Changer le mot de passe' }}</span>
          </button>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import AppLayout from '@/layouts/AppLayout.vue'
import {
  KeyRound,
  CheckCircle2,
  XCircle,
  AlertCircle,
  Loader2
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()
const notifications = useNotificationStore()

const form = reactive({
  oldPassword: '',
  newPassword: '',
  confirmPassword: ''
})

const isLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const validationRules = computed(() => [
  {
    label: 'Au moins 8 caracteres',
    valid: form.newPassword.length >= 8
  },
  {
    label: 'Au moins une lettre majuscule',
    valid: /[A-Z]/.test(form.newPassword)
  },
  {
    label: 'Au moins un chiffre',
    valid: /\d/.test(form.newPassword)
  }
])

const allRulesValid = computed(() => validationRules.value.every(r => r.valid))

const passwordsMatch = computed(() => form.newPassword === form.confirmPassword)

const isFormValid = computed(() => {
  return form.oldPassword.length > 0
    && allRulesValid.value
    && passwordsMatch.value
    && form.confirmPassword.length > 0
})

async function handleSubmit() {
  if (isLoading.value || !isFormValid.value) return

  isLoading.value = true
  errorMessage.value = ''
  successMessage.value = ''

  try {
    await authStore.changePassword(form.oldPassword, form.newPassword)
    successMessage.value = 'Mot de passe modifie avec succes.'
    notifications.success('Mot de passe modifie avec succes')

    // Reset form
    form.oldPassword = ''
    form.newPassword = ''
    form.confirmPassword = ''

    // Redirect after short delay
    setTimeout(() => {
      router.push('/dashboard')
    }, 1500)
  } catch (err) {
    errorMessage.value = err.message || 'Erreur lors du changement de mot de passe'
  } finally {
    isLoading.value = false
  }
}
</script>
