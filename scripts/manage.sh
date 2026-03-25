#!/bin/bash
#
# Larrère & Phish - Script de gestion des services
# Usage: ./scripts/manage.sh {start|stop|restart|status}
#

APP_DIR="/var/www/html/larrereEtFish"
PID_DIR="$APP_DIR/storage/pids"
LOG_DIR="$APP_DIR/storage/logs"

mkdir -p "$PID_DIR" "$LOG_DIR"

WORKER_PID="$PID_DIR/worker.pid"
WEBSOCKET_PID="$PID_DIR/websocket.pid"
WORKER_LOG="$LOG_DIR/worker.log"
WEBSOCKET_LOG="$LOG_DIR/websocket.log"

start_worker() {
    if [ -f "$WORKER_PID" ] && kill -0 "$(cat "$WORKER_PID")" 2>/dev/null; then
        echo "[Worker]    Déjà en cours d'exécution (PID $(cat "$WORKER_PID"))"
        return
    fi
    cd "$APP_DIR"
    nohup php worker/worker.php >> "$WORKER_LOG" 2>&1 &
    echo $! > "$WORKER_PID"
    echo "[Worker]    Démarré (PID $!)"
}

start_websocket() {
    if [ -f "$WEBSOCKET_PID" ] && kill -0 "$(cat "$WEBSOCKET_PID")" 2>/dev/null; then
        echo "[WebSocket] Déjà en cours d'exécution (PID $(cat "$WEBSOCKET_PID"))"
        return
    fi
    cd "$APP_DIR"
    nohup php websocket/server.php >> "$WEBSOCKET_LOG" 2>&1 &
    echo $! > "$WEBSOCKET_PID"
    echo "[WebSocket] Démarré (PID $!)"
}

stop_process() {
    local name="$1"
    local pidfile="$2"

    if [ -f "$pidfile" ]; then
        local pid
        pid=$(cat "$pidfile")
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid"
            # Attendre l'arrêt propre (max 5 secondes)
            for i in $(seq 1 10); do
                kill -0 "$pid" 2>/dev/null || break
                sleep 0.5
            done
            # Forcer si toujours actif
            if kill -0 "$pid" 2>/dev/null; then
                kill -9 "$pid" 2>/dev/null
            fi
            echo "[$name] Arrêté (PID $pid)"
        else
            echo "[$name] Processus introuvable (PID $pid déjà terminé)"
        fi
        rm -f "$pidfile"
    else
        echo "[$name] Pas en cours d'exécution"
    fi
}

status_process() {
    local name="$1"
    local pidfile="$2"
    local logfile="$3"

    if [ -f "$pidfile" ] && kill -0 "$(cat "$pidfile")" 2>/dev/null; then
        echo "[$name] En cours (PID $(cat "$pidfile"))"
        if [ -f "$logfile" ]; then
            echo "           Dernière ligne du log :"
            echo "           $(tail -1 "$logfile")"
        fi
    else
        echo "[$name] Arrêté"
        rm -f "$pidfile" 2>/dev/null
    fi
}

case "${1:-}" in
    start)
        echo "=== Démarrage des services Larrère & Phish ==="
        start_worker
        start_websocket
        echo ""
        echo "Logs disponibles dans : $LOG_DIR/"
        ;;
    stop)
        echo "=== Arrêt des services Larrère & Phish ==="
        stop_process "Worker   " "$WORKER_PID"
        stop_process "WebSocket" "$WEBSOCKET_PID"
        ;;
    restart)
        echo "=== Redémarrage des services Larrère & Phish ==="
        stop_process "Worker   " "$WORKER_PID"
        stop_process "WebSocket" "$WEBSOCKET_PID"
        sleep 1
        start_worker
        start_websocket
        ;;
    status)
        echo "=== État des services Larrère & Phish ==="
        status_process "Worker   " "$WORKER_PID" "$WORKER_LOG"
        status_process "WebSocket" "$WEBSOCKET_PID" "$WEBSOCKET_LOG"
        ;;
    logs)
        echo "=== Logs en direct (Ctrl+C pour quitter) ==="
        tail -f "$WORKER_LOG" "$WEBSOCKET_LOG"
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|logs}"
        echo ""
        echo "  start    Démarre le worker et le serveur WebSocket"
        echo "  stop     Arrête les deux services"
        echo "  restart  Redémarre les deux services"
        echo "  status   Affiche l'état des services"
        echo "  logs     Affiche les logs en direct"
        exit 1
        ;;
esac
