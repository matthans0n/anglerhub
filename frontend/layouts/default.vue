<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Mobile-first navigation header -->
    <header class="sticky top-0 z-50 bg-white shadow-sm border-b border-gray-200 safe-area-inset-top">
      <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
          <!-- Logo -->
          <NuxtLink to="/" class="flex items-center space-x-2">
            <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center">
              <span class="text-white font-bold text-sm">AH</span>
            </div>
            <span class="font-semibold text-gray-900 hidden sm:block">AnglerHub</span>
          </NuxtLink>
          
          <!-- Navigation actions -->
          <div class="flex items-center space-x-4">
            <!-- Quick catch button -->
            <UButton
              to="/catches/new"
              color="emerald"
              variant="solid"
              size="sm"
              icon="i-heroicons-plus"
              class="hidden sm:flex"
            >
              Quick Catch
            </UButton>
            
            <!-- Mobile menu button -->
            <UButton
              variant="ghost"
              size="sm"
              icon="i-heroicons-bars-3"
              class="sm:hidden"
              @click="showMobileMenu = !showMobileMenu"
            />
            
            <!-- User menu -->
            <UDropdown :items="userMenuItems" :popper="{ placement: 'bottom-end' }">
              <UAvatar
                src="/default-avatar.png"
                alt="User avatar"
                size="sm"
                class="cursor-pointer"
              />
            </UDropdown>
          </div>
        </div>
      </div>
      
      <!-- Mobile navigation menu -->
      <div v-if="showMobileMenu" class="sm:hidden border-t border-gray-200 bg-white">
        <nav class="px-4 py-3 space-y-2">
          <NuxtLink
            v-for="item in navigationItems"
            :key="item.to"
            :to="item.to"
            class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-100"
            @click="showMobileMenu = false"
          >
            <UIcon :name="item.icon" class="w-5 h-5 mr-3 inline" />
            {{ item.label }}
          </NuxtLink>
        </nav>
      </div>
    </header>

    <!-- Main content -->
    <main class="flex-1">
      <slot />
    </main>

    <!-- Bottom navigation for mobile -->
    <nav class="sm:hidden fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 safe-area-inset-bottom">
      <div class="flex items-center justify-around py-2">
        <NuxtLink
          v-for="item in bottomNavItems"
          :key="item.to"
          :to="item.to"
          class="flex flex-col items-center py-2 px-3 text-xs font-medium text-gray-600 hover:text-emerald-600"
          active-class="text-emerald-600"
        >
          <UIcon :name="item.icon" class="w-6 h-6 mb-1" />
          {{ item.label }}
        </NuxtLink>
      </div>
    </nav>
  </div>
</template>

<script setup lang="ts">
const showMobileMenu = ref(false)

// Navigation items
const navigationItems = [
  { to: '/', label: 'Dashboard', icon: 'i-heroicons-home' },
  { to: '/catches', label: 'Catches', icon: 'i-heroicons-trophy' },
  { to: '/goals', label: 'Goals', icon: 'i-heroicons-target' },
  { to: '/locations', label: 'Locations', icon: 'i-heroicons-map-pin' },
  { to: '/statistics', label: 'Statistics', icon: 'i-heroicons-chart-bar' }
]

// Bottom navigation for mobile
const bottomNavItems = [
  { to: '/', label: 'Home', icon: 'i-heroicons-home' },
  { to: '/catches', label: 'Catches', icon: 'i-heroicons-trophy' },
  { to: '/catches/new', label: 'Add', icon: 'i-heroicons-plus-circle' },
  { to: '/goals', label: 'Goals', icon: 'i-heroicons-target' },
  { to: '/profile', label: 'Profile', icon: 'i-heroicons-user' }
]

// User menu items
const userMenuItems = [
  [{
    label: 'Profile',
    icon: 'i-heroicons-user',
    to: '/profile'
  }, {
    label: 'Settings', 
    icon: 'i-heroicons-cog-6-tooth',
    to: '/settings'
  }, {
    label: 'Help',
    icon: 'i-heroicons-question-mark-circle',
    to: '/help'
  }],
  [{
    label: 'Sign Out',
    icon: 'i-heroicons-arrow-right-on-rectangle',
    click: () => {
      // TODO: Implement logout
      console.log('Sign out clicked')
    }
  }]
]

// Close mobile menu when route changes
const route = useRoute()
watch(() => route.path, () => {
  showMobileMenu.value = false
})
</script>

<style scoped>
.router-link-active {
  @apply text-emerald-600 bg-emerald-50;
}
</style>