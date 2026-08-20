# Queue worker (systemd)

Панель крутит DeployTheme / Provision v2 через `QUEUE_CONNECTION=database`.
Worker должен работать постоянно под `www-root`.

## Установка на passport-stage

```bash
APP_DIR=/var/www/www-root/data/www/passport-stage.narniapanel.top

# 1) unit из репозитория
sudo cp "$APP_DIR/deploy/passport-queue.service" /etc/systemd/system/passport-queue.service
sudo systemctl daemon-reload
sudo systemctl enable --now passport-queue.service

# 2) проверка
systemctl status passport-queue.service --no-pager
sudo -u www-root tail -n 30 "$APP_DIR/storage/logs/queue-worker.log"
```

## После git pull кода панели

```bash
sudo systemctl restart passport-queue.service
```

Worker держит PHP-код в памяти; без restart новые фиксы в Jobs/Services не подхватятся.

## Полезные команды

```bash
sudo systemctl status passport-queue
sudo systemctl restart passport-queue
sudo journalctl -u passport-queue -n 50 --no-pager
```

`--max-time=3600` в unit — раз в час процесс сам выходит, systemd поднимает снова (свежая память / код после частичных деплоев). При полном деплое всё равно делайте `restart`.
