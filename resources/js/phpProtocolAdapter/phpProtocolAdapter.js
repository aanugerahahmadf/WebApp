export default function phpProtocolAdapter(config) {
  return new Promise((resolve, reject) => {
    const method = config.method?.toUpperCase() || 'GET'

    fetch(config.url, {
      method,
      body: method !== 'GET' ? config.data : null,
      headers: config.headers,
    })
      .then(async (response) => {
        const responseData = await response.text()

        resolve({
          data: responseData,
          status: response.status,
          statusText: response.statusText,
          headers: response.headers,
          config,
          request: null,
        })
      })
      .catch((error) => {
        reject(error)
      })
  })
}
