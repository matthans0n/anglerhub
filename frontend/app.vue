<template>
  <div>
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
// App-level configuration and meta
useHead({
  titleTemplate: '%s - AnglerHub',
  meta: [
    { name: 'description', content: 'Track your fishing catches, set goals, and enhance your angling experience.' }
  ]
})

// PWA and mobile optimizations
if (process.client) {
  // Prevent double-tap zoom on iOS
  let lastTouchEnd = 0
  document.addEventListener('touchend', (event) => {
    const now = (new Date()).getTime()
    if (now - lastTouchEnd <= 300) {
      event.preventDefault()
    }
    lastTouchEnd = now
  }, false)
  
  // Handle viewport height for mobile browsers
  const setVh = () => {
    const vh = window.innerHeight * 0.01
    document.documentElement.style.setProperty('--vh', `${vh}px`)
  }
  
  setVh()
  window.addEventListener('resize', setVh)
  window.addEventListener('orientationchange', setVh)
}
</script>

<style>
/* Mobile-first responsive design */
html {
  height: 100%;
  height: calc(var(--vh, 1vh) * 100);
}

body {
  min-height: 100%;
  min-height: calc(var(--vh, 1vh) * 100);
}
</style>