---
name: awesome-rea
description: "Search and explore the curated Awesome-REA catalog of 1,265+ reverse engineering tools, 442+ case studies, and 235+ research papers across Android apps, native binaries, firmware, communication protocols, and games. Use when researching reverse engineering methodologies, looking up specialized decompilation tools, finding protocol reconstruction examples, or investigating hardware/firmware targets."
---

# Awesome-REA Skill

Use this skill when researching tools, case studies, academic papers, or prior art for reverse engineering Android applications, firmware, native binaries, protocols, or web applications.

The local repository is located at `d:/для one plus nord n10/Awesome-REA`.

## Capabilities & Data Sources

The Awesome-REA catalog contains:
* **1,265+ Curated Tools:** JADX, Ghidra, Frida, Rizin, Cutter, IDA, JEB, Angr, binary analysis and memory hook frameworks.
* **442+ Real Case Studies:** Documented investigations of mobile apps, Bluetooth protocols, IoT firmware, game assets, and proprietary formats.
* **235+ Research Papers:** Peer-reviewed papers on binary deobfuscation, symbolic execution, protocol synthesis, and malware analysis.

---

## How to Query the Local Catalog

You can query the catalog directly via the Python CLI tool:

### 1. General Search
```powershell
python "d:/для one plus nord n10/Awesome-REA/scripts/catalog.py" "<keyword>"
```

### 2. Search by Target Type
Filter targets by `app`, `binary`, `firmware`, `protocol`, `game`, or `website`:
```powershell
# Search Android application tools & cases
python "d:/для one plus nord n10/Awesome-REA/scripts/catalog.py" "android" --target app

# Search firmware analysis tools
python "d:/для one plus nord n10/Awesome-REA/scripts/catalog.py" "mediatek" --target firmware

# Search protocol reverse engineering cases
python "d:/для one plus nord n10/Awesome-REA/scripts/catalog.py" "bluetooth" --target protocol
```

### 3. Search Research Papers
```powershell
python "d:/для one plus nord n10/Awesome-REA/scripts/catalog.py" "decompilation" --papers
```

### 5. Query via Triad-Omni Cluster API (Redmi Note 4 Deck)
When the cluster is active, the catalog is also hosted in RAM on the hardware deck (`192.168.1.203:8000` or local forward `127.0.0.1:8000`):
```bash
# Instant JSON lookup across 1,131 tools (<4ms)
curl -s "http://127.0.0.1:8000/api/rea/search?q=ghidra&cat=all"
```

---

## When to Use

1. **Android APK & Native Library Analysis:** Finding specialized scripts for unpackers, packers (SecShell, Bangcle), Frida scripts, or Qualcomm Chromatix reverse engineering.
2. **Firmware Extraction:** Finding memory dumps, Little Kernel (LK) loaders, eMMC dump parsers, and partition decoders.
3. **Protocol RE:** Reversing BLE/Wi-Fi IoT devices, ADB over Wi-Fi, or proprietary serial protocols.
