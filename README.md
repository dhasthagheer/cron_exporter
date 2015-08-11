# Cron Exporter for Prometheus

This is a simple server that periodically scrapes cron running status and exports them via HTTP for Prometheus
consumption.

To install it:

```bash
sudo apt-get install mercurial
git clone https://github.com/dhasthagheer/cron_exporter.git
cd cron_exporter
make
```

To run it:

```bash
sudo ./cron_exporter [flags]
```

Help on flags:
```bash
./cron_exporter --help
```
