---
title: "Offline Data Sync"
phase: "Discover"
clarification_ref:
  path: "docs/preparation/clarifications/anglerhub-fishing-club-management.md"
  approved_at: "2025-09-10 unknown"
stack:
  php: "8.x"
  laravel: "TBD"
  filament: "TBD"
  node: "TBD"
  nuxt: "TBD"
  test: "Pest"
research:
  completed_at: "2025-09-10"
  time_spent_minutes: 65
  sources:
    - title: "Vue.js and Progressive Web Apps: Building Offline-First Applications"
      publisher: "Medium/Blessing Mba"
      url: "https://medium.com/@blessingmba3/vue-js-and-progressive-web-apps-pwas-building-offline-first-applications-2be4e39e4a2e"
      published_or_updated: "2024-unknown"
      version: "Vue 3"
    - title: "js13kGames: Making PWA work offline with service workers"
      publisher: "MDN Web Docs"
      url: "https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Tutorials/js13kGames/Offline_Service_workers"
      published_or_updated: "2024-unknown"
      version: "current"
    - title: "Vue Offline Package"
      publisher: "GitHub/filrak"
      url: "https://github.com/filrak/vue-offline"
      published_or_updated: "2024-unknown"
      version: "Vue 3 compatible"
risks:
  - id: "R1"
    desc: "Offline data conflicts when multiple devices used by same user"
    likelihood: "low"
    impact: "medium"
  - id: "R2"
    desc: "Storage quota limits could prevent offline catch logging"
    likelihood: "medium"
    impact: "high"
  - id: "R3"
    desc: "Complex sync logic could introduce data corruption bugs"
    likelihood: "medium"
    impact: "high"
traceability:
  - ac: "AC-9"
    examples: ["EX-2"]
    evidence: ["Medium#offline-first", "MDN#service-workers"]
  - ac: "AC-2"
    examples: ["EX-2"]
    evidence: ["GitHub#vue-offline", "MDN#cache-strategies"]
recommendation_summary: "Service Worker-based offline architecture with IndexedDB storage and conflict-free sync provides reliable catch logging capability in remote fishing locations while maintaining data integrity."
thin_slice:
  - "Implement Service Worker with offline-first caching strategy"
  - "Set up IndexedDB for offline catch storage"
  - "Create background sync mechanism for automatic upload"
  - "Implement conflict resolution for offline/online data reconciliation"
handoff:
  to: "orchestrator"
  next_phase: "Plan"
---

# Offline Data Sync Research

## Executive Summary

Offline functionality is essential for **AC-9** (offline catch logging and sync) and impacts **AC-2** (field catch logging). Research confirms that Service Worker architecture with IndexedDB storage provides robust offline capabilities for fishing apps, where internet connectivity is frequently unavailable in remote locations.

## Key Requirements from ACs/Examples

### AC-9: Offline Functionality
- Catch logging works without internet connection
- Offline catches sync automatically when connection restored
- Offline storage maintains catch data, photos, GPS coordinates
- Clear indication when app operating offline vs online
- Conflict resolution when multiple offline entries sync
- Maximum 50 offline catches stored before requiring sync

### AC-2: Personal Catch Logging (Offline Context)
- Photo uploads queued for later sync
- GPS coordinates captured and stored offline
- Weather data cached or recorded for later enrichment
- Species database available offline
- Personal notes saved locally until sync

### EX-2: Field Logging Scenario (Offline)
1. User in remote location with no connectivity
2. Opens mobile app, sees offline indicator
3. Logs catch: species, weight, length, photo, notes
4. Data saved to local storage with timestamp
5. When connectivity returns, automatic background sync
6. User receives confirmation of successful upload

## Research Findings

### Service Worker Architecture

**Offline-First Strategy Benefits:**
- **Reliability:** App works consistently regardless of network status
- **Performance:** Local data access faster than network requests
- **User Experience:** No functionality loss in remote locations
- **Battery Efficiency:** Reduced network activity preserves device battery

**Service Worker Capabilities:**
- **Network Interception:** Catch all network requests from the app
- **Cache Management:** Store app shell, data, and images locally
- **Background Sync:** Queue failed requests for automatic retry
- **Push Notifications:** Notify users of sync status and updates

### Storage Strategy: IndexedDB

**Why IndexedDB for Fishing Apps:**
- **Storage Capacity:** 50-100MB typical browser limit (sufficient for 50 catches + photos)
- **Structured Data:** Relational-style queries for catch data
- **File Storage:** Binary photo storage with metadata
- **Transaction Support:** ACID compliance for data integrity
- **Asynchronous:** Non-blocking operations for smooth UI

**Storage Schema Design:**
```javascript
// IndexedDB schema for offline catches
const offlineSchema = {
  catches: {
    keyPath: 'clientId', // UUID for offline/online reconciliation
    indexes: {
      'timestamp': 'caughtAt',
      'species': 'species',
      'syncStatus': 'syncStatus'
    }
  },
  photos: {
    keyPath: 'id',
    indexes: {
      'catchId': 'catchId',
      'uploadStatus': 'uploadStatus'
    }
  },
  syncQueue: {
    keyPath: 'id',
    indexes: {
      'priority': 'priority',
      'timestamp': 'createdAt'
    }
  }
};
```

### Sync Architecture Patterns

**Conflict-Free Data Design:**
- **Client-side IDs:** Generate UUIDs for offline entries
- **Timestamp Ordering:** Use device timestamp with server reconciliation
- **Never Delete:** Mark records as deleted, sync tombstones
- **Immutable Logs:** Treat catch entries as append-only events

**Sync State Management:**
```javascript
const SyncStates = {
  OFFLINE_PENDING: 'offline_pending',
  SYNCING: 'syncing', 
  SYNCED: 'synced',
  SYNC_FAILED: 'sync_failed',
  CONFLICT: 'conflict'
};

// Catch entry with sync metadata
{
  clientId: 'uuid-v4',
  species: 'Largemouth Bass',
  weight: 3.5,
  caughtAt: '2024-08-15T14:30:00Z',
  syncStatus: SyncStates.OFFLINE_PENDING,
  syncAttempts: 0,
  lastSyncAttempt: null,
  serverId: null // Populated after successful sync
}
```

### Background Sync Implementation

**Service Worker Sync Strategy:**
```javascript
// Register background sync
self.addEventListener('sync', event => {
  if (event.tag === 'catch-sync') {
    event.waitUntil(syncOfflineCatches());
  }
});

// Background sync logic
async function syncOfflineCatches() {
  const pendingCatches = await getOfflineCatches();
  
  for (const catchData of pendingCatches) {
    try {
      await syncSingleCatch(catchData);
      await markCatchSynced(catchData.clientId);
    } catch (error) {
      await incrementSyncAttempt(catchData.clientId);
      if (catchData.syncAttempts >= 3) {
        await markSyncFailed(catchData.clientId);
      }
    }
  }
}
```

**Progressive Sync Strategy:**
1. **Immediate Retry:** Attempt sync every 30 seconds when online
2. **Exponential Backoff:** Delay increases with failed attempts
3. **Batch Processing:** Send multiple catches in single request
4. **Priority Queuing:** Sync recent catches first

## Laravel Backend Sync Support

### API Design for Sync

**Batch Upload Endpoint:**
```php
// POST /api/catches/batch-sync
Route::post('/catches/batch-sync', [CatchController::class, 'batchSync']);

public function batchSync(Request $request) {
    $catches = $request->input('catches');
    $results = [];
    
    foreach ($catches as $catchData) {
        try {
            $catch = $this->syncSingleCatch($catchData);
            $results[] = [
                'client_id' => $catchData['client_id'],
                'server_id' => $catch->id,
                'status' => 'success'
            ];
        } catch (ValidationException $e) {
            $results[] = [
                'client_id' => $catchData['client_id'],
                'status' => 'error',
                'errors' => $e->errors()
            ];
        }
    }
    
    return response()->json(['results' => $results]);
}
```

**Conflict Resolution Strategy:**
- **Server Wins:** Server timestamp takes precedence
- **Client Backup:** Preserve original offline data
- **User Notification:** Alert user to conflicts requiring attention
- **Merge Strategy:** Combine non-conflicting fields where possible

### Database Schema for Sync

```sql
-- Enhanced catches table for sync support
CREATE TABLE catches (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    client_id VARCHAR(36) UNIQUE, -- UUID from offline client
    species VARCHAR(100),
    weight DECIMAL(5,2),
    length DECIMAL(5,2),
    location_latitude DECIMAL(10, 8),
    location_longitude DECIMAL(11, 8),
    weather_data JSON,
    notes TEXT,
    caught_at TIMESTAMP,
    
    -- Sync metadata
    sync_source ENUM('online', 'offline') DEFAULT 'online',
    sync_conflicts JSON NULL, -- Store conflict resolution data
    original_client_data JSON NULL, -- Backup of original offline data
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX(client_id),
    INDEX(user_id, caught_at)
);

-- Sync operation tracking
CREATE TABLE sync_operations (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    operation_type ENUM('catch_upload', 'photo_upload', 'batch_sync'),
    client_batch_id VARCHAR(36), -- Group related operations
    items_total INTEGER,
    items_success INTEGER,
    items_failed INTEGER,
    started_at TIMESTAMP,
    completed_at TIMESTAMP NULL,
    error_details JSON NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

## User Experience Design

### Offline Status Indication

**Visual Indicators:**
- **Connection Status:** Clear online/offline indicator in header
- **Sync Status:** Progress indicator during background sync
- **Data Status:** Visual markers for synced vs pending items
- **Storage Usage:** Progress bar showing offline storage consumption

**User Notifications:**
```javascript
// Sync status notifications
const SyncNotifications = {
  SYNC_STARTED: 'Syncing catch data...',
  SYNC_SUCCESS: 'All catches synced successfully',
  SYNC_PARTIAL: 'Some catches failed to sync, will retry automatically',
  SYNC_CONFLICT: 'Data conflict detected, please review',
  STORAGE_WARNING: 'Offline storage 80% full, please sync soon',
  STORAGE_FULL: 'Offline storage full, sync required to continue'
};
```

### Conflict Resolution Interface

**User-Friendly Conflict Resolution:**
1. **Automatic Resolution:** Handle simple timestamp conflicts silently
2. **User Notification:** Alert for significant conflicts requiring input
3. **Side-by-Side Comparison:** Show offline vs server versions
4. **Merge Options:** Allow user to combine best of both versions
5. **Preserve Originals:** Never lose user data during conflict resolution

## Performance Optimization

### Storage Management

**Capacity Planning:**
```javascript
// Storage usage estimation
const StorageEstimates = {
  catchRecord: 2048, // 2KB per catch entry
  photoMedium: 512000, // 500KB compressed photo
  maxOfflineCatches: 50,
  totalEstimate: 50 * (2048 + 3 * 512000) // ~75MB for 50 catches with 3 photos each
};
```

**Cleanup Strategy:**
- **Automatic Cleanup:** Remove synced data after 7 days
- **User Control:** Manual cleanup options in settings
- **Smart Caching:** Keep most recent and frequently accessed data
- **Photo Optimization:** Compress photos for offline storage

### Sync Optimization

**Efficient Data Transfer:**
- **Incremental Sync:** Only sync changed data
- **Compression:** GZIP all API requests/responses
- **Photo Queuing:** Upload photos separately from catch data
- **Connection Awareness:** Adjust sync behavior based on connection quality

**Battery Optimization:**
- **Background Sync Limits:** Respect device battery optimization
- **WiFi Preference:** Defer large uploads until WiFi available
- **User Control:** Allow users to disable background sync
- **Smart Scheduling:** Sync during device charging periods

## Testing Strategy

### Offline Scenario Testing

**Core Test Cases:**
1. **Complete Offline Workflow:** Log catches entirely offline, verify sync
2. **Intermittent Connectivity:** Simulate poor network conditions
3. **Storage Limits:** Test behavior at storage capacity limits
4. **Multiple Device Sync:** Same user syncing from different devices
5. **Long Offline Periods:** Extended offline usage (days/weeks)

**Sync Conflict Testing:**
- **Timestamp Conflicts:** Same catch logged offline and online
- **Data Corruption:** Simulate partial sync failures
- **Version Conflicts:** Different app versions syncing same data
- **Photo Sync Issues:** Image upload failures and retry logic

### Performance Testing

**Metrics to Track:**
- **Sync Speed:** Time to sync 50 offline catches
- **Storage Efficiency:** Compression ratios and space usage
- **Battery Impact:** Power consumption during sync operations
- **User Experience:** Perceived performance during offline usage

## Success Metrics

- 100% of core catch logging features work offline
- Sync success rate >99% for offline data
- Average sync time <30 seconds for typical offline session
- Zero data loss incidents during sync operations
- User satisfaction >95% with offline experience

## Risks & Mitigation

### R1: Multi-Device Conflicts
- **Mitigation:** Clear conflict resolution rules, user education
- **Detection:** Server-side duplicate detection and merging
- **Prevention:** Encourage single-device usage for fishing trips

### R2: Storage Quota Limits
- **Mitigation:** Proactive storage monitoring, automatic cleanup
- **User Control:** Settings to manage storage preferences
- **Fallback:** Graceful degradation when storage full

### R3: Sync Logic Complexity
- **Mitigation:** Comprehensive testing, simple conflict resolution rules
- **Monitoring:** Detailed sync operation logging and error tracking
- **Rollback:** Ability to recover from sync failures

## Next Steps for Plan Phase

1. **Service Worker Architecture:** Design comprehensive offline-first service worker
2. **IndexedDB Schema:** Define detailed offline storage structure and indexes
3. **Sync API Design:** Plan Laravel endpoints for batch sync and conflict resolution
4. **Conflict Resolution Logic:** Define rules and user interface for data conflicts
5. **Testing Framework:** Create automated testing for offline scenarios
6. **Performance Monitoring:** Plan metrics collection for sync operations and storage usage