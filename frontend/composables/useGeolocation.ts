interface Position {
  latitude: number
  longitude: number
  accuracy: number
  altitude?: number
  altitudeAccuracy?: number
  heading?: number
  speed?: number
  timestamp: number
}

interface LocationError {
  code: number
  message: string
}

export const useGeolocation = () => {
  const currentPosition = ref<Position | null>(null)
  const error = ref<LocationError | null>(null)
  const isLoading = ref(false)
  const isSupported = ref(false)
  const watchId = ref<number | null>(null)

  // Check if geolocation is supported
  const checkSupport = () => {
    if (process.client) {
      isSupported.value = 'geolocation' in navigator
    }
    return isSupported.value
  }

  // Get current position
  const getCurrentPosition = (options: PositionOptions = {}) => {
    return new Promise<Position>((resolve, reject) => {
      if (!checkSupport()) {
        const err = {
          code: 0,
          message: 'Geolocation is not supported by this browser'
        }
        error.value = err
        reject(err)
        return
      }

      isLoading.value = true
      error.value = null

      const defaultOptions: PositionOptions = {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 300000, // 5 minutes
        ...options
      }

      navigator.geolocation.getCurrentPosition(
        (position) => {
          const pos: Position = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            altitude: position.coords.altitude || undefined,
            altitudeAccuracy: position.coords.altitudeAccuracy || undefined,
            heading: position.coords.heading || undefined,
            speed: position.coords.speed || undefined,
            timestamp: position.timestamp
          }
          
          currentPosition.value = pos
          isLoading.value = false
          resolve(pos)
        },
        (err) => {
          const locationError = {
            code: err.code,
            message: getErrorMessage(err.code)
          }
          
          error.value = locationError
          isLoading.value = false
          reject(locationError)
        },
        defaultOptions
      )
    })
  }

  // Start watching position
  const watchPosition = (options: PositionOptions = {}) => {
    if (!checkSupport()) {
      error.value = {
        code: 0,
        message: 'Geolocation is not supported by this browser'
      }
      return
    }

    if (watchId.value !== null) {
      navigator.geolocation.clearWatch(watchId.value)
    }

    const defaultOptions: PositionOptions = {
      enableHighAccuracy: true,
      timeout: 15000,
      maximumAge: 60000, // 1 minute
      ...options
    }

    watchId.value = navigator.geolocation.watchPosition(
      (position) => {
        currentPosition.value = {
          latitude: position.coords.latitude,
          longitude: position.coords.longitude,
          accuracy: position.coords.accuracy,
          altitude: position.coords.altitude || undefined,
          altitudeAccuracy: position.coords.altitudeAccuracy || undefined,
          heading: position.coords.heading || undefined,
          speed: position.coords.speed || undefined,
          timestamp: position.timestamp
        }
        error.value = null
      },
      (err) => {
        error.value = {
          code: err.code,
          message: getErrorMessage(err.code)
        }
      },
      defaultOptions
    )
  }

  // Stop watching position
  const clearWatch = () => {
    if (watchId.value !== null) {
      navigator.geolocation.clearWatch(watchId.value)
      watchId.value = null
    }
  }

  // Get error message based on error code
  const getErrorMessage = (code: number): string => {
    switch (code) {
      case 1:
        return 'Location access denied by user'
      case 2:
        return 'Location information unavailable'
      case 3:
        return 'Location request timed out'
      default:
        return 'An unknown error occurred while retrieving location'
    }
  }

  // Calculate distance between two points (in kilometers)
  const calculateDistance = (
    lat1: number,
    lon1: number,
    lat2: number,
    lon2: number
  ): number => {
    const R = 6371 // Radius of the Earth in kilometers
    const dLat = toRadians(lat2 - lat1)
    const dLon = toRadians(lon2 - lon1)
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos(toRadians(lat1)) *
        Math.cos(toRadians(lat2)) *
        Math.sin(dLon / 2) *
        Math.sin(dLon / 2)
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
    return R * c
  }

  // Convert degrees to radians
  const toRadians = (degrees: number): number => {
    return degrees * (Math.PI / 180)
  }

  // Format coordinates for display
  const formatCoordinates = (lat: number, lng: number, precision = 6): string => {
    const latDir = lat >= 0 ? 'N' : 'S'
    const lngDir = lng >= 0 ? 'E' : 'W'
    return `${Math.abs(lat).toFixed(precision)}°${latDir}, ${Math.abs(lng).toFixed(precision)}°${lngDir}`
  }

  // Request permission for location access
  const requestPermission = async (): Promise<boolean> => {
    if (!checkSupport()) return false

    try {
      if ('permissions' in navigator) {
        const permission = await navigator.permissions.query({ name: 'geolocation' })
        return permission.state === 'granted'
      }
      
      // Fallback: try to get position to check permission
      await getCurrentPosition({ timeout: 5000 })
      return true
    } catch (err) {
      return false
    }
  }

  // Get location name from coordinates (reverse geocoding)
  const getLocationName = async (lat: number, lng: number): Promise<string> => {
    try {
      // This would typically use a geocoding service
      // For now, return formatted coordinates
      return formatCoordinates(lat, lng)
    } catch (error) {
      console.error('Reverse geocoding failed:', error)
      return formatCoordinates(lat, lng)
    }
  }

  // Check if position is accurate enough for fishing
  const isAccurateEnough = (accuracy: number, threshold = 100): boolean => {
    return accuracy <= threshold
  }

  // Clean up on unmount
  onUnmounted(() => {
    clearWatch()
  })

  // Initialize support check
  checkSupport()

  return {
    // State
    currentPosition: readonly(currentPosition),
    error: readonly(error),
    isLoading: readonly(isLoading),
    isSupported: readonly(isSupported),
    
    // Methods
    getCurrentPosition,
    watchPosition,
    clearWatch,
    calculateDistance,
    formatCoordinates,
    requestPermission,
    getLocationName,
    isAccurateEnough
  }
}