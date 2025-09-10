<template>
  <div class="container mx-auto px-4 py-6 pb-20 sm:pb-6">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-emerald-500 to-blue-500 rounded-lg p-6 text-white mb-6">
      <h1 class="text-2xl font-bold mb-2">Welcome to AnglerHub</h1>
      <p class="text-emerald-100 mb-4">Track your catches, set goals, and improve your fishing game</p>
      
      <!-- Quick stats -->
      <div class="grid grid-cols-3 gap-4">
        <div class="text-center">
          <div class="text-2xl font-bold">{{ stats.totalCatches }}</div>
          <div class="text-sm text-emerald-100">Total Catches</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold">{{ stats.activeGoals }}</div>
          <div class="text-sm text-emerald-100">Active Goals</div>
        </div>
        <div class="text-center">
          <div class="text-2xl font-bold">{{ stats.personalBests }}</div>
          <div class="text-sm text-emerald-100">Personal Bests</div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 gap-4 mb-6">
      <UButton
        to="/catches/new"
        color="emerald"
        variant="solid"
        size="lg"
        block
        icon="i-heroicons-plus"
        class="h-20 flex-col"
      >
        <span class="text-lg font-medium">Log Catch</span>
        <span class="text-sm opacity-90">Record new catch</span>
      </UButton>
      
      <UButton
        to="/weather"
        color="blue"
        variant="soft"
        size="lg"
        block
        icon="i-heroicons-cloud"
        class="h-20 flex-col"
      >
        <span class="text-lg font-medium">Weather</span>
        <span class="text-sm opacity-90">Check conditions</span>
      </UButton>
    </div>

    <!-- Recent Activity -->
    <div class="space-y-6">
      <!-- Recent Catches -->
      <section>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-semibold text-gray-900">Recent Catches</h2>
          <NuxtLink to="/catches" class="text-emerald-600 hover:text-emerald-700 text-sm font-medium">
            View All
          </NuxtLink>
        </div>
        
        <div v-if="recentCatches.length > 0" class="space-y-3">
          <div
            v-for="catch_ in recentCatches"
            :key="catch_.id"
            class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-sm transition-shadow"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <h3 class="font-medium text-gray-900">{{ catch_.species }}</h3>
                <div class="text-sm text-gray-600 space-y-1 mt-1">
                  <div class="flex items-center space-x-4">
                    <span v-if="catch_.weight" class="flex items-center">
                      <UIcon name="i-heroicons-scale" class="w-4 h-4 mr-1" />
                      {{ catch_.weight }} lbs
                    </span>
                    <span v-if="catch_.length" class="flex items-center">
                      <UIcon name="i-heroicons-ruler" class="w-4 h-4 mr-1" />
                      {{ catch_.length }}"
                    </span>
                  </div>
                  <div class="flex items-center text-gray-500">
                    <UIcon name="i-heroicons-map-pin" class="w-4 h-4 mr-1" />
                    {{ catch_.location }}
                  </div>
                </div>
              </div>
              <div class="text-right">
                <div class="text-sm text-gray-500">{{ formatDate(catch_.caught_at) }}</div>
                <UBadge v-if="catch_.is_personal_best" color="yellow" variant="soft" size="xs" class="mt-1">
                  PB
                </UBadge>
              </div>
            </div>
          </div>
        </div>
        
        <div v-else class="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-8 text-center">
          <UIcon name="i-heroicons-trophy" class="w-12 h-12 text-gray-400 mx-auto mb-3" />
          <h3 class="text-lg font-medium text-gray-900 mb-2">No catches yet</h3>
          <p class="text-gray-600 mb-4">Start logging your catches to see them here</p>
          <UButton to="/catches/new" color="emerald" icon="i-heroicons-plus">
            Log First Catch
          </UButton>
        </div>
      </section>

      <!-- Active Goals -->
      <section>
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-semibold text-gray-900">Active Goals</h2>
          <NuxtLink to="/goals" class="text-emerald-600 hover:text-emerald-700 text-sm font-medium">
            View All
          </NuxtLink>
        </div>
        
        <div v-if="activeGoals.length > 0" class="space-y-3">
          <div
            v-for="goal in activeGoals"
            :key="goal.id"
            class="bg-white rounded-lg border border-gray-200 p-4"
          >
            <div class="flex items-start justify-between mb-3">
              <div>
                <h3 class="font-medium text-gray-900">{{ goal.title }}</h3>
                <p class="text-sm text-gray-600">{{ goal.description }}</p>
              </div>
              <UBadge :color="getGoalStatusColor(goal.progress_percentage)" variant="soft">
                {{ Math.round(goal.progress_percentage) }}%
              </UBadge>
            </div>
            
            <!-- Progress bar -->
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div 
                class="bg-emerald-500 h-2 rounded-full transition-all duration-300"
                :style="{ width: `${Math.min(goal.progress_percentage, 100)}%` }"
              />
            </div>
            
            <div class="flex items-center justify-between text-sm text-gray-500 mt-2">
              <span>{{ goal.current_value }} / {{ goal.target_value }}</span>
              <span>{{ formatDate(goal.target_date) }}</span>
            </div>
          </div>
        </div>
        
        <div v-else class="bg-gray-50 rounded-lg border border-dashed border-gray-300 p-8 text-center">
          <UIcon name="i-heroicons-target" class="w-12 h-12 text-gray-400 mx-auto mb-3" />
          <h3 class="text-lg font-medium text-gray-900 mb-2">No active goals</h3>
          <p class="text-gray-600 mb-4">Set fishing goals to track your progress</p>
          <UButton to="/goals/new" color="blue" icon="i-heroicons-plus">
            Create First Goal
          </UButton>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
// Page meta
definePageMeta({
  title: 'Dashboard'
})

// Sample data - will be replaced with API calls
const stats = ref({
  totalCatches: 0,
  activeGoals: 0,
  personalBests: 0
})

const recentCatches = ref([
  // Will be populated from API
])

const activeGoals = ref([
  // Will be populated from API
])

// Helper functions
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  })
}

const getGoalStatusColor = (percentage: number) => {
  if (percentage >= 90) return 'green'
  if (percentage >= 70) return 'yellow' 
  if (percentage >= 40) return 'orange'
  return 'red'
}

// TODO: Implement API calls to load real data
onMounted(async () => {
  try {
    // Load dashboard data from API
    // const dashboardData = await $fetch('/api/dashboard')
    // stats.value = dashboardData.stats
    // recentCatches.value = dashboardData.recentCatches
    // activeGoals.value = dashboardData.activeGoals
    
    console.log('Dashboard loaded - API integration pending')
  } catch (error) {
    console.error('Failed to load dashboard data:', error)
  }
})
</script>