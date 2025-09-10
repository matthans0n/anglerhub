interface OfflineRecord {
  id: string
  type: 'catch' | 'goal' | 'profile_update'
  data: any
  timestamp: number
  synced: boolean
  retry_count: number
}

export const useOfflineStorage = () => {
  const isOnline = useOnline()
  const pendingRecords = ref<OfflineRecord[]>([])
  
  // Storage keys
  const OFFLINE_STORAGE_KEY = 'anglerhub_offline_data'
  const LAST_SYNC_KEY = 'anglerhub_last_sync'

  // Initialize offline storage
  const initializeStorage = () => {
    if (process.client) {
      const stored = localStorage.getItem(OFFLINE_STORAGE_KEY)
      if (stored) {
        try {
          pendingRecords.value = JSON.parse(stored)
        } catch (error) {
          console.error('Failed to parse offline storage:', error)
          pendingRecords.value = []
        }
      }
    }
  }

  // Save to local storage
  const saveToStorage = () => {
    if (process.client) {
      localStorage.setItem(OFFLINE_STORAGE_KEY, JSON.stringify(pendingRecords.value))
    }
  }

  // Generate unique ID for offline records
  const generateId = () => {
    return `offline_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`
  }

  // Add record to offline storage
  const addOfflineRecord = (type: OfflineRecord['type'], data: any) => {
    const record: OfflineRecord = {
      id: generateId(),
      type,
      data,
      timestamp: Date.now(),
      synced: false,
      retry_count: 0
    }
    
    pendingRecords.value.push(record)
    saveToStorage()
    
    // Try to sync immediately if online
    if (isOnline.value) {
      nextTick(() => syncPendingRecords())
    }
    
    return record.id
  }

  // Remove synced record
  const removeRecord = (id: string) => {
    const index = pendingRecords.value.findIndex(record => record.id === id)
    if (index !== -1) {
      pendingRecords.value.splice(index, 1)
      saveToStorage()
    }
  }

  // Mark record as synced
  const markAsSynced = (id: string) => {
    const record = pendingRecords.value.find(r => r.id === id)
    if (record) {
      record.synced = true
      removeRecord(id)
    }
  }

  // Increment retry count
  const incrementRetryCount = (id: string) => {
    const record = pendingRecords.value.find(r => r.id === id)
    if (record) {
      record.retry_count++
      saveToStorage()
    }
  }

  // Sync individual record
  const syncRecord = async (record: OfflineRecord): Promise<boolean> => {
    try {
      switch (record.type) {
        case 'catch': {
          const { create: createCatch } = useCatchesApi()
          await createCatch(record.data)
          break
        }
        case 'goal': {
          const { create: createGoal } = useGoalsApi()
          await createGoal(record.data)
          break
        }
        case 'profile_update': {
          const { updateProfile } = useAuth()
          await updateProfile(record.data)
          break
        }
        default:
          console.warn('Unknown record type:', record.type)
          return false
      }
      
      markAsSynced(record.id)
      return true
    } catch (error) {
      console.error('Failed to sync record:', record.id, error)
      incrementRetryCount(record.id)
      
      // Remove records that have failed too many times
      if (record.retry_count >= 3) {
        console.warn('Removing record after max retries:', record.id)
        removeRecord(record.id)
      }
      
      return false
    }
  }

  // Sync all pending records
  const syncPendingRecords = async () => {
    if (!isOnline.value) return false
    
    const unsynced = pendingRecords.value.filter(r => !r.synced)
    if (unsynced.length === 0) return true
    
    console.log(`Syncing ${unsynced.length} offline records...`)
    
    let successCount = 0
    
    for (const record of unsynced) {
      const success = await syncRecord(record)
      if (success) successCount++
    }
    
    // Update last sync timestamp
    if (process.client) {
      localStorage.setItem(LAST_SYNC_KEY, Date.now().toString())
    }
    
    console.log(`Synced ${successCount}/${unsynced.length} records`)
    return successCount === unsynced.length
  }

  // Store catch offline
  const storeCatchOffline = (catchData: any) => {
    return addOfflineRecord('catch', catchData)
  }

  // Store goal offline  
  const storeGoalOffline = (goalData: any) => {
    return addOfflineRecord('goal', goalData)
  }

  // Store profile update offline
  const storeProfileUpdateOffline = (profileData: any) => {
    return addOfflineRecord('profile_update', profileData)
  }

  // Get last sync time
  const getLastSyncTime = () => {
    if (process.client) {
      const timestamp = localStorage.getItem(LAST_SYNC_KEY)
      return timestamp ? new Date(parseInt(timestamp)) : null
    }
    return null
  }

  // Clear all offline data
  const clearOfflineData = () => {
    pendingRecords.value = []
    if (process.client) {
      localStorage.removeItem(OFFLINE_STORAGE_KEY)
      localStorage.removeItem(LAST_SYNC_KEY)
    }
  }

  // Get offline data statistics
  const getOfflineStats = () => {
    return {
      totalRecords: pendingRecords.value.length,
      unsynced: pendingRecords.value.filter(r => !r.synced).length,
      catches: pendingRecords.value.filter(r => r.type === 'catch').length,
      goals: pendingRecords.value.filter(r => r.type === 'goal').length,
      lastSync: getLastSyncTime()
    }
  }

  // Watch for online status changes
  watch(isOnline, (online) => {
    if (online && pendingRecords.value.length > 0) {
      console.log('Connection restored, syncing offline data...')
      setTimeout(() => syncPendingRecords(), 1000)
    }
  })

  // Initialize storage on composable creation
  initializeStorage()

  return {
    // State
    pendingRecords: readonly(pendingRecords),
    isOnline: readonly(isOnline),
    
    // Methods
    storeCatchOffline,
    storeGoalOffline,
    storeProfileUpdateOffline,
    syncPendingRecords,
    getOfflineStats,
    getLastSyncTime,
    clearOfflineData
  }
}