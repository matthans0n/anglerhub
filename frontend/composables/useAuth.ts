interface User {
  id: number
  name: string
  email: string
  email_verified_at: string | null
  preferences?: any
  created_at: string
  updated_at: string
}

interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  isLoading: boolean
}

export const useAuth = () => {
  // Reactive auth state
  const user = useState<User | null>('auth.user', () => null)
  const token = useState<string | null>('auth.token', () => null)
  const isLoading = useState<boolean>('auth.loading', () => false)

  // Computed properties
  const isAuthenticated = computed(() => !!user.value && !!token.value)

  // Initialize auth from localStorage on client
  const initializeAuth = () => {
    if (process.client) {
      const storedToken = localStorage.getItem('auth_token')
      const storedUser = localStorage.getItem('auth_user')
      
      if (storedToken && storedUser) {
        try {
          token.value = storedToken
          user.value = JSON.parse(storedUser)
        } catch (error) {
          console.error('Failed to parse stored user data:', error)
          clearAuth()
        }
      }
    }
  }

  // Clear auth state
  const clearAuth = () => {
    user.value = null
    token.value = null
    
    if (process.client) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
    }
  }

  // Store auth state
  const setAuth = (userData: User, authToken: string) => {
    user.value = userData
    token.value = authToken
    
    if (process.client) {
      localStorage.setItem('auth_token', authToken)
      localStorage.setItem('auth_user', JSON.stringify(userData))
    }
  }

  // Login method
  const login = async (credentials: { email: string; password: string }) => {
    isLoading.value = true
    
    try {
      const { login: loginApi } = useAuthApi()
      const response = await loginApi(credentials)
      
      if (response.success && response.data) {
        setAuth(response.data.user, response.data.token)
        return { success: true }
      } else {
        throw new Error(response.message || 'Login failed')
      }
    } catch (error: any) {
      clearAuth()
      return {
        success: false,
        error: error.data?.message || error.message || 'Login failed'
      }
    } finally {
      isLoading.value = false
    }
  }

  // Register method
  const register = async (userData: {
    name: string
    email: string
    password: string
    password_confirmation: string
  }) => {
    isLoading.value = true
    
    try {
      const { register: registerApi } = useAuthApi()
      const response = await registerApi(userData)
      
      if (response.success && response.data) {
        setAuth(response.data.user, response.data.token)
        return { success: true }
      } else {
        throw new Error(response.message || 'Registration failed')
      }
    } catch (error: any) {
      return {
        success: false,
        error: error.data?.message || error.message || 'Registration failed'
      }
    } finally {
      isLoading.value = false
    }
  }

  // Logout method
  const logout = async (callApi = true) => {
    if (callApi && token.value) {
      try {
        const { logout: logoutApi } = useAuthApi()
        await logoutApi()
      } catch (error) {
        console.error('Logout API call failed:', error)
      }
    }
    
    clearAuth()
    await navigateTo('/auth/login')
  }

  // Fetch current user
  const fetchUser = async () => {
    if (!token.value) return null
    
    try {
      const { getUser } = useAuthApi()
      const { data } = await getUser()
      
      if (data?.user) {
        user.value = data.user
        
        if (process.client) {
          localStorage.setItem('auth_user', JSON.stringify(data.user))
        }
      }
      
      return user.value
    } catch (error) {
      console.error('Failed to fetch user:', error)
      clearAuth()
      return null
    }
  }

  // Update user profile
  const updateProfile = async (profileData: Partial<User>) => {
    if (!token.value) throw new Error('Not authenticated')
    
    try {
      const { updateProfile: updateProfileApi } = useAuthApi()
      const response = await updateProfileApi(profileData)
      
      if (response.success && response.data) {
        user.value = response.data.user
        
        if (process.client) {
          localStorage.setItem('auth_user', JSON.stringify(response.data.user))
        }
        
        return { success: true }
      } else {
        throw new Error(response.message || 'Profile update failed')
      }
    } catch (error: any) {
      return {
        success: false,
        error: error.data?.message || error.message || 'Profile update failed'
      }
    }
  }

  // Check if user has specific permission
  const hasPermission = (permission: string) => {
    // TODO: Implement permission checking based on user roles
    return true
  }

  // Require authentication (for middleware)
  const requireAuth = () => {
    if (!isAuthenticated.value) {
      throw createError({
        statusCode: 401,
        statusMessage: 'Authentication required'
      })
    }
  }

  // Initialize auth on composable creation
  initializeAuth()

  return {
    // State
    user: readonly(user),
    token: readonly(token),
    isAuthenticated: readonly(isAuthenticated),
    isLoading: readonly(isLoading),
    
    // Methods
    login,
    register,
    logout,
    fetchUser,
    updateProfile,
    hasPermission,
    requireAuth,
    initializeAuth,
    clearAuth
  }
}