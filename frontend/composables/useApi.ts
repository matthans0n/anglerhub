import type { UseFetchOptions } from 'nuxt/app'

type ApiResponse<T> = {
  data: T
  message?: string
  success: boolean
}

export const useApi = <T>(
  url: string | (() => string),
  options: UseFetchOptions<T> = {}
) => {
  const config = useRuntimeConfig()
  
  return useFetch(url, {
    baseURL: config.public.apiBase,
    credentials: 'include',
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    },
    onRequest({ request, options }) {
      // Add auth token if available
      const { token } = useAuth()
      if (token.value) {
        options.headers = {
          ...options.headers,
          'Authorization': `Bearer ${token.value}`
        }
      }
    },
    onRequestError({ request, error }) {
      console.error('Request error:', error)
    },
    onResponseError({ request, response }) {
      console.error('Response error:', response.status, response._data)
      
      // Handle common HTTP errors
      if (response.status === 401) {
        // Unauthorized - redirect to login
        const { logout } = useAuth()
        logout()
        navigateTo('/auth/login')
      }
    },
    ...options
  })
}

// Specific API methods for different resources
export const useAuthApi = () => ({
  login: (credentials: { email: string; password: string }) =>
    $fetch<ApiResponse<{ user: any; token: string }>>('/auth/login', {
      method: 'POST',
      body: credentials,
      baseURL: useRuntimeConfig().public.apiBase
    }),
    
  register: (data: { name: string; email: string; password: string; password_confirmation: string }) =>
    $fetch<ApiResponse<{ user: any; token: string }>>('/auth/register', {
      method: 'POST', 
      body: data,
      baseURL: useRuntimeConfig().public.apiBase
    }),
    
  logout: () => {
    const { token } = useAuth()
    return $fetch<ApiResponse<null>>('/auth/logout', {
      method: 'POST',
      baseURL: useRuntimeConfig().public.apiBase,
      headers: token.value ? { 'Authorization': `Bearer ${token.value}` } : {}
    })
  },
  
  getUser: () => useApi<{ user: any }>('/auth/user'),
  
  updateProfile: (data: any) =>
    $fetch<ApiResponse<{ user: any }>>('/auth/profile', {
      method: 'PUT',
      body: data,
      baseURL: useRuntimeConfig().public.apiBase,
      headers: {
        'Authorization': `Bearer ${useAuth().token.value}`
      }
    })
})

export const useCatchesApi = () => ({
  list: (params?: { 
    species?: string
    location?: string
    start_date?: string
    end_date?: string
    limit?: number
    page?: number
  }) => useApi<{ catches: any[]; meta: any }>('/catches', { 
    query: params 
  }),
  
  show: (id: string | number) => useApi<{ catch: any }>(`/catches/${id}`),
  
  create: (data: any) =>
    $fetch<ApiResponse<{ catch: any }>>('/catches', {
      method: 'POST',
      body: data,
      baseURL: useRuntimeConfig().public.apiBase,
      headers: {
        'Authorization': `Bearer ${useAuth().token.value}`
      }
    }),
    
  update: (id: string | number, data: any) =>
    $fetch<ApiResponse<{ catch: any }>>(`/catches/${id}`, {
      method: 'PUT',
      body: data,
      baseURL: useRuntimeConfig().public.apiBase,
      headers: {
        'Authorization': `Bearer ${useAuth().token.value}`
      }
    }),
    
  delete: (id: string | number) =>
    $fetch<ApiResponse<null>>(`/catches/${id}`, {
      method: 'DELETE',
      baseURL: useRuntimeConfig().public.apiBase,
      headers: {
        'Authorization': `Bearer ${useAuth().token.value}`
      }
    }),
    
  getStatistics: () => useApi<{ statistics: any }>('/catches/statistics'),
  
  getNearby: (lat: number, lng: number, radius?: number) =>
    useApi<{ catches: any[] }>('/catches/nearby', {
      query: { lat, lng, radius }
    })
})

export const useGoalsApi = () => ({
  list: () => useApi<{ goals: any[] }>('/goals'),
  
  show: (id: string | number) => useApi<{ goal: any }>(`/goals/${id}`),
  
  create: (data: any) =>
    $fetch<ApiResponse<{ goal: any }>>('/goals', {
      method: 'POST',
      body: data,
      baseURL: useRuntimeConfig().public.apiBase,
      headers: {
        'Authorization': `Bearer ${useAuth().token.value}`
      }
    }),
    
  update: (id: string | number, data: any) =>
    $fetch<ApiResponse<{ goal: any }>>(`/goals/${id}`, {
      method: 'PUT',
      body: data,
      baseURL: useRuntimeConfig().public.apiBase,
      headers: {
        'Authorization': `Bearer ${useAuth().token.value}`
      }
    }),
    
  delete: (id: string | number) =>
    $fetch<ApiResponse<null>>(`/goals/${id}`, {
      method: 'DELETE',
      baseURL: useRuntimeConfig().public.apiBase,
      headers: {
        'Authorization': `Bearer ${useAuth().token.value}`
      }
    }),
    
  complete: (id: string | number) =>
    $fetch<ApiResponse<{ goal: any }>>(`/goals/${id}/complete`, {
      method: 'POST',
      baseURL: useRuntimeConfig().public.apiBase,
      headers: {
        'Authorization': `Bearer ${useAuth().token.value}`
      }
    }),
    
  pause: (id: string | number) =>
    $fetch<ApiResponse<{ goal: any }>>(`/goals/${id}/pause`, {
      method: 'POST',
      baseURL: useRuntimeConfig().public.apiBase,
      headers: {
        'Authorization': `Bearer ${useAuth().token.value}`
      }
    }),
    
  resume: (id: string | number) =>
    $fetch<ApiResponse<{ goal: any }>>(`/goals/${id}/resume`, {
      method: 'POST',
      baseURL: useRuntimeConfig().public.apiBase,
      headers: {
        'Authorization': `Bearer ${useAuth().token.value}`
      }
    }),
    
  getStatistics: () => useApi<{ statistics: any }>('/goals/statistics'),
  
  refreshProgress: () =>
    $fetch<ApiResponse<null>>('/goals/refresh-progress', {
      method: 'POST',
      baseURL: useRuntimeConfig().public.apiBase,
      headers: {
        'Authorization': `Bearer ${useAuth().token.value}`
      }
    })
})