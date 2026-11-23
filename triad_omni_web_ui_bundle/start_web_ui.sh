#!/usr/bin/env bash
echo '========================================================'
echo '   Triad-Omni Edge AI Cluster - Web Cyberdeck v5.0'
echo '========================================================'
echo '[1/2] Запуск Master Router на http://0.0.0.0:8000 ...'
python3 router.py &
ROUTER_PID=$!
sleep 2
echo '[2/2] Сервер запущен (PID: '')'
echo 'Откройте в браузере: http://localhost:8000'
which xdg-open >/dev/null 2>&1 && xdg-open 'http://localhost:8000' || which open >/dev/null 2>&1 && open 'http://localhost:8000' || termux-open-url 'http://localhost:8000' 2>/dev/null
wait 
