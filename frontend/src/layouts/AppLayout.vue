<template>
  <div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside
      class="w-64 bg-brand-500 text-white flex flex-col flex-shrink-0 overflow-y-auto"
    >
      <!-- Logo -->
      <div class="flex items-center gap-3 px-5 py-6 border-b border-brand-400/30">
        <div class="p-2 bg-brand-400/30 rounded-lg">
          <ShieldCheck class="w-7 h-7 text-white" />
        </div>
        <div>
          <h1 class="text-lg font-bold leading-tight tracking-tight">Sophishticated</h1>
          <p class="text-[11px] text-brand-200 leading-tight">Phishing awareness platform</p>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-3 py-4 space-y-1">
        <!-- Tableau de bord -->
        <router-link
          to="/dashboard"
          :class="linkClass('/dashboard')"
        >
          <LayoutDashboard class="w-5 h-5" />
          <span>Tableau de bord</span>
        </router-link>

        <!-- Destinataires -->
        <div>
          <button
            @click="toggleMenu('recipients')"
            :class="[parentLinkClass('/recipients'), 'w-full']"
          >
            <div class="flex items-center gap-3 flex-1">
              <Users class="w-5 h-5" />
              <span>Destinataires</span>
            </div>
            <ChevronDown
              class="w-4 h-4 transition-transform duration-200"
              :class="{ 'rotate-180': openMenus.recipients }"
            />
          </button>
          <Transition name="submenu">
            <div v-if="openMenus.recipients" class="ml-8 mt-1 space-y-0.5">
              <router-link to="/recipients" :class="subLinkClass('/recipients', true)">
                Liste
              </router-link>
              <router-link to="/recipients/groups" :class="subLinkClass('/recipients/groups')">
                Groupes
              </router-link>
            </div>
          </Transition>
        </div>

        <!-- Templates -->
        <div>
          <button
            @click="toggleMenu('templates')"
            :class="[parentLinkClass('/templates'), 'w-full']"
          >
            <div class="flex items-center gap-3 flex-1">
              <Mail class="w-5 h-5" />
              <span>Templates</span>
            </div>
            <ChevronDown
              class="w-4 h-4 transition-transform duration-200"
              :class="{ 'rotate-180': openMenus.templates }"
            />
          </button>
          <Transition name="submenu">
            <div v-if="openMenus.templates" class="ml-8 mt-1 space-y-0.5">
              <router-link to="/templates" :class="subLinkClass('/templates', true)">
                Mails
              </router-link>
              <router-link to="/templates/pages" :class="subLinkClass('/templates/pages')">
                Pages
              </router-link>
              <router-link to="/templates/packs" :class="subLinkClass('/templates/packs')">
                Packs
              </router-link>
            </div>
          </Transition>
        </div>

        <!-- Campagnes -->
        <router-link
          to="/campaigns"
          :class="linkClass('/campaigns')"
        >
          <Target class="w-5 h-5" />
          <span>Campagnes</span>
        </router-link>

        <!-- Statistiques -->
        <router-link
          to="/stats"
          :class="linkClass('/stats')"
        >
          <BarChart3 class="w-5 h-5" />
          <span>Statistiques</span>
        </router-link>

        <!-- Administration -->
        <router-link
          v-if="isAdmin"
          to="/admin/users"
          :class="linkClass('/admin')"
        >
          <Shield class="w-5 h-5" />
          <span>Administration</span>
        </router-link>
      </nav>

      <!-- User info -->
      <div class="border-t border-brand-400/30 px-4 py-4">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-9 h-9 rounded-full bg-brand-400/40 flex items-center justify-center text-sm font-bold uppercase">
            {{ userInitials }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate">{{ user?.full_name || user?.username || 'Utilisateur' }}</p>
            <span
              :class="[
                'inline-block text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded mt-0.5',
                roleBadgeClass
              ]"
            >
              {{ user?.role || 'user' }}
            </span>
          </div>
        </div>
        <button
          @click="handleLogout"
          class="flex items-center gap-2 w-full text-sm text-brand-200 hover:text-white px-2 py-1.5 rounded-md hover:bg-brand-600 transition-colors"
        >
          <LogOut class="w-4 h-4" />
          <span>Deconnexion</span>
        </button>
      </div>
    </aside>

    <!-- Main content area -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Header bar -->
      <header class="h-14 bg-white border-b border-gray-200 flex items-center justify-between px-6 flex-shrink-0">
        <nav class="flex items-center gap-1 text-sm text-gray-500">
          <router-link to="/dashboard" class="hover:text-brand-500 transition-colors">
            <Home class="w-4 h-4" />
          </router-link>
          <template v-for="(crumb, i) in breadcrumbs" :key="i">
            <ChevronRight class="w-3.5 h-3.5 text-gray-300" />
            <router-link
              v-if="crumb.to"
              :to="crumb.to"
              class="hover:text-brand-500 transition-colors"
            >
              {{ crumb.label }}
            </router-link>
            <span v-else class="text-gray-800 font-medium">{{ crumb.label }}</span>
          </template>
        </nav>
        <h2 class="text-lg font-semibold text-gray-800">{{ pageTitle }}</h2>
      </header>

      <!-- Page content -->
      <main class="flex-1 overflow-y-auto bg-gray-50 p-6">
        <slot />
      </main>
    </div>

    <!-- Toast notifications -->
    <ToastContainer />
  </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAuth } from '@/composables/useAuth'
import ToastContainer from '@/components/ui/ToastContainer.vue'
import {
  ShieldCheck,
  LayoutDashboard,
  Users,
  Mail,
  Target,
  BarChart3,
  Shield,
  ChevronDown,
  ChevronRight,
  LogOut,
  Home
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const { user, isAdmin } = useAuth()

// Collapsible sub-menus state
const openMenus = reactive({
  recipients: false,
  templates: false
})

// Auto-open sub-menus based on current route
watch(
  () => route.path,
  (path) => {
    if (path.startsWith('/recipients')) openMenus.recipients = true
    if (path.startsWith('/templates')) openMenus.templates = true
  },
  { immediate: true }
)

function toggleMenu(menu) {
  openMenus[menu] = !openMenus[menu]
}

const userInitials = computed(() => {
  const name = user.value?.full_name || user.value?.username || ''
  return name
    .split(' ')
    .map(w => w[0])
    .join('')
    .slice(0, 2)
})

const roleBadgeClass = computed(() => {
  const role = user.value?.role
  if (role === 'admin') return 'bg-red-500/30 text-red-200'
  if (role === 'manager') return 'bg-amber-500/30 text-amber-200'
  return 'bg-brand-400/30 text-brand-100'
})

function isActive(path) {
  return route.path === path || route.path.startsWith(path + '/')
}

function isExactActive(path) {
  return route.path === path
}

function linkClass(path) {
  const base = 'flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-150'
  if (isActive(path)) {
    return `${base} bg-brand-600 text-white border-l-[3px] border-white -ml-px`
  }
  return `${base} text-brand-100 hover:bg-brand-600/60 hover:text-white`
}

function parentLinkClass(path) {
  const base = 'flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-medium transition-colors duration-150 cursor-pointer'
  if (isActive(path)) {
    return `${base} bg-brand-600/40 text-white`
  }
  return `${base} text-brand-100 hover:bg-brand-600/60 hover:text-white`
}

function subLinkClass(path, exact = false) {
  const active = exact ? isExactActive(path) : isActive(path)
  const base = 'block text-sm py-1.5 px-3 rounded-md transition-colors duration-150'
  if (active) {
    return `${base} bg-brand-600 text-white font-medium`
  }
  return `${base} text-brand-200 hover:text-white hover:bg-brand-600/40`
}

// Breadcrumb configuration
const breadcrumbMap = {
  '/dashboard': [{ label: 'Tableau de bord' }],
  '/recipients': [{ label: 'Destinataires' }],
  '/recipients/groups': [
    { label: 'Destinataires', to: '/recipients' },
    { label: 'Groupes' }
  ],
  '/templates': [{ label: 'Templates' }],
  '/templates/pages': [
    { label: 'Templates', to: '/templates' },
    { label: 'Pages' }
  ],
  '/templates/packs': [
    { label: 'Templates', to: '/templates' },
    { label: 'Packs' }
  ],
  '/templates/create': [
    { label: 'Templates', to: '/templates' },
    { label: 'Nouveau template' }
  ],
  '/templates/pages/create': [
    { label: 'Templates', to: '/templates' },
    { label: 'Pages', to: '/templates/pages' },
    { label: 'Nouvelle page' }
  ],
  '/campaigns': [{ label: 'Campagnes' }],
  '/campaigns/create': [
    { label: 'Campagnes', to: '/campaigns' },
    { label: 'Nouvelle campagne' }
  ],
  '/stats': [{ label: 'Statistiques' }],
  '/admin/users': [{ label: 'Administration' }],
  '/change-password': [{ label: 'Changer le mot de passe' }]
}

const breadcrumbs = computed(() => {
  const path = route.path

  if (breadcrumbMap[path]) return breadcrumbMap[path]

  // Dynamic route patterns
  if (path.match(/^\/recipients\/\d+/)) {
    return [
      { label: 'Destinataires', to: '/recipients' },
      { label: 'Detail' }
    ]
  }
  if (path.match(/^\/templates\/\d+/)) {
    return [
      { label: 'Templates', to: '/templates' },
      { label: 'Edition' }
    ]
  }
  if (path.match(/^\/templates\/pages\/\d+/)) {
    return [
      { label: 'Templates', to: '/templates' },
      { label: 'Pages', to: '/templates/pages' },
      { label: 'Edition' }
    ]
  }
  if (path.match(/^\/campaigns\/\d+/)) {
    return [
      { label: 'Campagnes', to: '/campaigns' },
      { label: 'Suivi' }
    ]
  }
  if (path.match(/^\/stats\/campaign\/\d+/)) {
    return [
      { label: 'Statistiques', to: '/stats' },
      { label: 'Campagne' }
    ]
  }
  if (path.match(/^\/stats\/group\/\d+/)) {
    return [
      { label: 'Statistiques', to: '/stats' },
      { label: 'Groupe' }
    ]
  }

  return [{ label: route.name || '' }]
})

const pageTitle = computed(() => {
  const last = breadcrumbs.value[breadcrumbs.value.length - 1]
  return last?.label || ''
})

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.submenu-enter-active,
.submenu-leave-active {
  transition: all 0.2s ease;
  overflow: hidden;
}
.submenu-enter-from,
.submenu-leave-to {
  opacity: 0;
  max-height: 0;
  transform: translateY(-4px);
}
.submenu-enter-to,
.submenu-leave-from {
  opacity: 1;
  max-height: 200px;
}
</style>
