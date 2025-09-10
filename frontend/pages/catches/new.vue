<template>
  <div class="container mx-auto px-4 py-6 pb-20 sm:pb-6">
    <div class="max-w-2xl mx-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Log New Catch</h1>
          <p class="text-gray-600 mt-1">Record your fishing success</p>
        </div>
        
        <!-- Offline indicator -->
        <UBadge v-if="!isOnline" color="orange" variant="soft">
          <UIcon name="i-heroicons-wifi-slash" class="w-4 h-4 mr-1" />
          Offline Mode
        </UBadge>
      </div>

      <!-- Catch Form -->
      <form @submit.prevent="handleSubmit" class="space-y-6">
        <!-- Species -->
        <UFormGroup label="Species" name="species" required>
          <UInput
            v-model="form.species"
            placeholder="e.g., Largemouth Bass"
            required
            size="lg"
            icon="i-heroicons-fish"
          />
        </UFormGroup>

        <!-- Weight and Length -->
        <div class="grid grid-cols-2 gap-4">
          <UFormGroup label="Weight (lbs)" name="weight">
            <UInput
              v-model="form.weight"
              type="number"
              step="0.1"
              placeholder="2.5"
              size="lg"
            />
          </UFormGroup>
          
          <UFormGroup label="Length (in)" name="length">
            <UInput
              v-model="form.length"
              type="number"
              step="0.1"
              placeholder="15.5"
              size="lg"
            />
          </UFormGroup>
        </div>

        <!-- Location -->
        <UFormGroup label="Location" name="location" required>
          <div class="space-y-3">
            <UInput
              v-model="form.location"
              placeholder="e.g., Lake Michigan"
              required
              size="lg"
              icon="i-heroicons-map-pin"
            />
            
            <!-- GPS coordinates -->
            <div class="flex items-center justify-between text-sm">
              <div v-if="currentPosition" class="text-gray-600">
                {{ formatCoordinates(currentPosition.latitude, currentPosition.longitude) }}
                <UBadge color="green" variant="soft" size="xs" class="ml-2">
                  GPS Active
                </UBadge>
              </div>
              
              <UButton
                v-if="!currentPosition"
                variant="soft"
                size="xs"
                icon="i-heroicons-map-pin"
                :loading="isLoadingLocation"
                @click="getCurrentLocation"
              >
                Get GPS Location
              </UButton>
            </div>
          </div>
        </UFormGroup>

        <!-- Water Body Type -->
        <UFormGroup label="Water Body" name="water_body">
          <USelect
            v-model="form.water_body"
            :options="waterBodyOptions"
            placeholder="Select water body type"
            size="lg"
          />
        </UFormGroup>

        <!-- Date and Time -->
        <UFormGroup label="Caught At" name="caught_at">
          <UInput
            v-model="form.caught_at"
            type="datetime-local"
            size="lg"
          />
        </UFormGroup>

        <!-- Bait/Lure -->
        <UFormGroup label="Bait/Lure" name="bait_lure">
          <UInput
            v-model="form.bait_lure"
            placeholder="e.g., Spinnerbait, Live Worm"
            size="lg"
          />
        </UFormGroup>

        <!-- Technique -->
        <UFormGroup label="Technique" name="technique">
          <UInput
            v-model="form.technique"
            placeholder="e.g., Cast and retrieve"
            size="lg"
          />
        </UFormGroup>

        <!-- Weather Conditions -->
        <UFormGroup label="Weather" name="weather_conditions">
          <UInput
            v-model="form.weather_conditions"
            placeholder="e.g., Partly cloudy, light breeze"
            size="lg"
          />
        </UFormGroup>

        <!-- Water and Air Temperature -->
        <div class="grid grid-cols-2 gap-4">
          <UFormGroup label="Water Temp (°C)" name="water_temp">
            <UInput
              v-model="form.water_temp"
              type="number"
              step="0.1"
              placeholder="18.5"
              size="lg"
            />
          </UFormGroup>
          
          <UFormGroup label="Air Temp (°C)" name="air_temp">
            <UInput
              v-model="form.air_temp"
              type="number"
              step="0.1"
              placeholder="22.0"
              size="lg"
            />
          </UFormGroup>
        </div>

        <!-- Release Status -->
        <UFormGroup label="Release Status" name="is_released">
          <div class="flex items-center space-x-4">
            <URadio
              v-model="form.is_released"
              :value="true"
              label="Released"
            />
            <URadio
              v-model="form.is_released"
              :value="false"
              label="Kept"
            />
          </div>
        </UFormGroup>

        <!-- Notes -->
        <UFormGroup label="Notes" name="notes">
          <UTextarea
            v-model="form.notes"
            placeholder="Additional details about your catch..."
            :rows="3"
            size="lg"
          />
        </UFormGroup>

        <!-- Submit Buttons -->
        <div class="flex flex-col sm:flex-row gap-3 pt-6">
          <UButton
            type="submit"
            color="emerald"
            size="lg"
            block
            :loading="isSubmitting"
            icon="i-heroicons-check"
          >
            {{ isOnline ? 'Save Catch' : 'Save Offline' }}
          </UButton>
          
          <UButton
            to="/catches"
            variant="soft"
            size="lg"
            block
            icon="i-heroicons-x-mark"
          >
            Cancel
          </UButton>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
// Page meta
definePageMeta({
  title: 'Log New Catch',
  middleware: 'auth'
})

// Composables
const { isOnline } = useNetwork()
const { getCurrentPosition, currentPosition, formatCoordinates, isLoading: isLoadingLocation } = useGeolocation()
const { storeCatchOffline } = useOfflineStorage()
const { create: createCatch } = useCatchesApi()

// Form state
const isSubmitting = ref(false)

// Initialize form with current time
const now = new Date()
now.setMinutes(now.getMinutes() - now.getTimezoneOffset()) // Adjust for timezone
const form = reactive({
  species: '',
  weight: null,
  length: null,
  location: '',
  latitude: null,
  longitude: null,
  water_body: '',
  caught_at: now.toISOString().slice(0, 16),
  bait_lure: '',
  technique: '',
  water_temp: null,
  air_temp: null,
  weather_conditions: '',
  notes: '',
  is_released: true
})

// Water body options
const waterBodyOptions = [
  'Lake',
  'River',
  'Stream',
  'Pond',
  'Ocean',
  'Bay',
  'Reservoir'
]

// Get current location
const getCurrentLocation = async () => {
  try {
    const position = await getCurrentPosition()
    form.latitude = position.latitude
    form.longitude = position.longitude
  } catch (error) {
    console.error('Failed to get location:', error)
  }
}

// Form submission
const handleSubmit = async () => {
  isSubmitting.value = true
  
  try {
    // Include GPS coordinates if available
    if (currentPosition.value) {
      form.latitude = currentPosition.value.latitude
      form.longitude = currentPosition.value.longitude
    }
    
    // Convert form data for API
    const catchData = {
      ...form,
      weight: form.weight ? parseFloat(form.weight) : null,
      length: form.length ? parseFloat(form.length) : null,
      water_temp: form.water_temp ? parseFloat(form.water_temp) : null,
      air_temp: form.air_temp ? parseFloat(form.air_temp) : null,
      caught_at: form.caught_at + ':00Z' // Add seconds and timezone
    }
    
    if (isOnline.value) {
      // Online: save directly to API
      const response = await createCatch(catchData)
      if (response.success) {
        await navigateTo('/catches')
      } else {
        throw new Error(response.message || 'Failed to save catch')
      }
    } else {
      // Offline: store locally for sync later
      storeCatchOffline(catchData)
      await navigateTo('/catches')
    }
  } catch (error) {
    console.error('Failed to save catch:', error)
    // TODO: Show error notification
  } finally {
    isSubmitting.value = false
  }
}

// Auto-get location on mount if permission is granted
onMounted(() => {
  if (process.client && navigator.geolocation) {
    getCurrentLocation()
  }
})
</script>