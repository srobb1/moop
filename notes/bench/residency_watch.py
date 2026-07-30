#!/usr/bin/env python3
"""Sample page-cache residency of the organism databases over time.

The question this answers: does the cache STAY warm between uses, or does
something reclaim it? That decides whether a prewarm housekeeping task is worth
building, and it is the one thing an inventory of cron jobs cannot prove -- a
scheduled job that reads nothing looks identical to no job at all until you watch.

Sampling is NON-PERTURBING. resident() mmaps and calls mincore(2), which reports
residency without faulting pages in, so watching does not warm what it watches.

Also records sectors read from sdb (the data volume) straight from /proc/diskstats.
A large jump between samples means SOMETHING swept the disk, even if we cannot see
whose process it was -- which matters because the Rapid7 agent's /proc/<pid>/io is
root-owned and unreadable from here.

usage:  residency_watch.py [interval_seconds] [hours]
"""
import os, sys, time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from cache import resident, pct

ORGANISMS = "/var/www/html/moop/organisms"
LOG = os.path.join(os.path.dirname(os.path.abspath(__file__)), "residency.log")

# A representative spread rather than all 75 -- keeps each sample cheap while still
# showing whether reclaim is happening. If these hold, the rest almost certainly do.
WATCH = [
    "Procerodes_sp", "Nematostella_vectensis", "Bipalium_kewense",
    "Antrozous_pallidus", "Bradypodion_ventrale", "Myotis_myotis",
    "Phagocata_velata", "Rousettus_aegyptiacus",
]


def meminfo():
    want = ("Cached:", "MemAvailable:", "MemFree:", "Buffers:", "SReclaimable:")
    out = {}
    with open("/proc/meminfo") as fh:
        for line in fh:
            key = line.split()[0]
            if key in want:
                out[key.rstrip(":")] = int(line.split()[1]) / 1024**2  # GB
    return out


def sdb_sectors_read():
    """Cumulative sectors read from the data volume since boot."""
    with open("/proc/diskstats") as fh:
        for line in fh:
            f = line.split()
            if len(f) > 5 and f[2] == "sdb":
                return int(f[5])
    return 0


def main():
    interval = int(sys.argv[1]) if len(sys.argv) > 1 else 900      # 15 min
    hours = float(sys.argv[2]) if len(sys.argv) > 2 else 20.0
    deadline = time.time() + hours * 3600

    dbs = [(n, os.path.join(ORGANISMS, n, "organism.sqlite")) for n in WATCH]
    dbs = [(n, p) for n, p in dbs if os.path.isfile(p)]

    new = not os.path.exists(LOG)
    with open(LOG, "a") as fh:
        if new:
            fh.write("# timestamp\tcached_GB\tavail_GB\tsdb_MB_read_since_last\t"
                     + "\t".join(n[:18] for n, _ in dbs) + "\n")
        prev_sectors = sdb_sectors_read()
        while time.time() < deadline:
            mi = meminfo()
            sectors = sdb_sectors_read()
            read_mb = (sectors - prev_sectors) * 512 / 1024**2
            prev_sectors = sectors
            pcts = []
            for _, path in dbs:
                try:
                    r, t = resident(path)
                    pcts.append(f"{pct(r, t):.1f}")
                except Exception:
                    pcts.append("err")
            fh.write(f"{time.strftime('%Y-%m-%d %H:%M')}\t{mi.get('Cached',0):.1f}\t"
                     f"{mi.get('MemAvailable',0):.1f}\t{read_mb:.0f}\t"
                     + "\t".join(pcts) + "\n")
            fh.flush()
            time.sleep(interval)


if __name__ == "__main__":
    main()
