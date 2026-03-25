import { ref, onUnmounted } from 'vue'

export function useWebSocket() {
  const lastEvent = ref(null)

  let ws = null
  let reconnectAttempts = 0
  let reconnectTimer = null
  let isDestroyed = false
  const eventHandlers = new Map()
  const subscribedChannels = new Set()

  function getWsUrl() {
    if (typeof window !== 'undefined') {
      const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:'
      const host = window.location.host // includes port if non-standard
      return `${protocol}//${host}/ws`
    }
    return 'ws://localhost:8081'
  }

  function connect() {
    if (isDestroyed) return

    const url = getWsUrl()
    ws = new WebSocket(url)

    ws.onopen = () => {
      reconnectAttempts = 0
      // Re-subscribe to channels after reconnect
      subscribedChannels.forEach(channel => {
        sendMessage({ action: 'subscribe', channel })
      })
    }

    ws.onmessage = (event) => {
      try {
        const data = JSON.parse(event.data)
        lastEvent.value = data

        // Server sends {event: "...", channel: "...", data: {...}}
        const eventType = data.event || data.type
        const handlers = eventHandlers.get(eventType)
        if (handlers) {
          handlers.forEach(callback => callback(data.data || data))
        }
        // Also fire a catch-all handler
        const allHandlers = eventHandlers.get('*')
        if (allHandlers) {
          allHandlers.forEach(callback => callback(data))
        }
      } catch {
        // Ignore malformed messages
      }
    }

    ws.onclose = () => {
      if (!isDestroyed) {
        scheduleReconnect()
      }
    }

    ws.onerror = () => {
      if (ws) {
        ws.close()
      }
    }
  }

  function scheduleReconnect() {
    if (isDestroyed || reconnectTimer) return

    const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000)
    reconnectAttempts++

    reconnectTimer = setTimeout(() => {
      reconnectTimer = null
      connect()
    }, delay)
  }

  function sendMessage(data) {
    if (ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify(data))
    }
  }

  function subscribe(channel) {
    subscribedChannels.add(channel)
    sendMessage({ action: 'subscribe', channel })
  }

  function unsubscribe(channel) {
    subscribedChannels.delete(channel)
    sendMessage({ action: 'unsubscribe', channel })
  }

  function onEvent(eventType, callback) {
    if (!eventHandlers.has(eventType)) {
      eventHandlers.set(eventType, new Set())
    }
    eventHandlers.get(eventType).add(callback)

    // Return cleanup function
    return () => {
      const handlers = eventHandlers.get(eventType)
      if (handlers) {
        handlers.delete(callback)
        if (handlers.size === 0) {
          eventHandlers.delete(eventType)
        }
      }
    }
  }

  function disconnect() {
    isDestroyed = true
    if (reconnectTimer) {
      clearTimeout(reconnectTimer)
      reconnectTimer = null
    }
    if (ws) {
      ws.close()
      ws = null
    }
    eventHandlers.clear()
    subscribedChannels.clear()
  }

  // Auto-connect
  connect()

  // Auto-cleanup on unmount
  onUnmounted(() => {
    disconnect()
  })

  return {
    lastEvent,
    subscribe,
    unsubscribe,
    onEvent,
    disconnect
  }
}
