/**
 * Format a date to DD/MM/YYYY
 * @param {string|Date} date
 * @returns {string}
 */
export function formatDate(date) {
  if (!date) return ''
  const d = new Date(date)
  if (isNaN(d.getTime())) return ''
  return d.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

/**
 * Format a date to DD/MM/YYYY HH:mm
 * @param {string|Date} date
 * @returns {string}
 */
export function formatDateTime(date) {
  if (!date) return ''
  const d = new Date(date)
  if (isNaN(d.getTime())) return ''
  return d.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

/**
 * Return a human-readable relative time string
 * @param {string|Date} date
 * @returns {string}
 */
export function timeAgo(date) {
  if (!date) return ''
  const d = new Date(date)
  if (isNaN(d.getTime())) return ''

  const now = new Date()
  const seconds = Math.floor((now - d) / 1000)

  if (seconds < 60) return 'il y a quelques secondes'

  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `il y a ${minutes} minute${minutes > 1 ? 's' : ''}`

  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `il y a ${hours} heure${hours > 1 ? 's' : ''}`

  const days = Math.floor(hours / 24)
  if (days < 30) return `il y a ${days} jour${days > 1 ? 's' : ''}`

  const months = Math.floor(days / 30)
  if (months < 12) return `il y a ${months} mois`

  const years = Math.floor(months / 12)
  return `il y a ${years} an${years > 1 ? 's' : ''}`
}

/**
 * Truncate a string to a given length
 * @param {string} str
 * @param {number} length
 * @returns {string}
 */
export function truncate(str, length = 50) {
  if (!str) return ''
  if (str.length <= length) return str
  return str.substring(0, length) + '...'
}

/**
 * Debounce a function
 * @param {Function} fn
 * @param {number} delay
 * @returns {Function}
 */
export function debounce(fn, delay = 300) {
  let timer = null
  return function (...args) {
    clearTimeout(timer)
    timer = setTimeout(() => {
      fn.apply(this, args)
    }, delay)
  }
}
