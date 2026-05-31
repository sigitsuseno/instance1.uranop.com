const BASE_URL = import.meta.env.VITE_API_URL || ''

export async function api(path, options = {}) {
    const url = `${BASE_URL}${path}`

    const defaultHeaders = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    }

    const token = localStorage.getItem('token')
    if (token) {
        defaultHeaders['Authorization'] = `Bearer ${token}`
    }

    const mergedHeaders = { ...defaultHeaders, ...(options.headers || {}) }

    if (options.body instanceof FormData) {
        delete mergedHeaders['Content-Type'];
    }

    const fetchOptions = {
        ...options,
        headers: mergedHeaders,
    }

    const response = await fetch(url, fetchOptions)

    if (!response.ok) {
        let message = `API error: ${response.status}`
        let responseData = null
        try {
            responseData = await response.json()
            if (responseData.message) {
                message = responseData.message
            } else if (responseData.errors) {
                const first = Object.values(responseData.errors).flat()
                message = first.length ? first[0] : message
            }
        } catch {}
        
        const error = new Error(message)
        error.response = {
            status: response.status,
            data: responseData
        }
        throw error
    }

    return response.json()
}

export function useApi() {
    const get = (path, options = {}) => api(path, { ...options, method: 'GET' })
    const post = (path, body, options = {}) => api(path, { ...options, method: 'POST', body: body instanceof FormData ? body : JSON.stringify(body) })
    const put = (path, body, options = {}) => api(path, { ...options, method: 'PUT', body: body instanceof FormData ? body : JSON.stringify(body) })
    const patch = (path, body, options = {}) => api(path, { ...options, method: 'PATCH', body: body instanceof FormData ? body : JSON.stringify(body) })
    const destroy = (path, options = {}) => api(path, { ...options, method: 'DELETE' })

    return { get, post, put, patch, destroy, api }
}
