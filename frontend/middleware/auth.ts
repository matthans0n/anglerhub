export default defineNuxtRouteMiddleware((to) => {
  const { isAuthenticated } = useAuth()
  
  // If not authenticated, redirect to login
  if (!isAuthenticated.value) {
    return navigateTo('/auth/login')
  }
})