# Prioritized Optimization Plan: 9B Agent Loop on Snapdragon 865 (Termux)

## Top 3 Experiments (Ranked by Expected Gain ÷ Effort)

Your baseline **3.71 t/s prefill** on 4× Cortex-A77 @ 2.22 GHz is **3–6× below expected throughput** for a 9B model on Snapdragon 865. Before touching exotic backends, isolate why prefill is nearly as slow as decode (2.4–2.7 t/s).

| Rank | Experiment | Effort | Expected Gain | Metric to Watch | Falsification Criterion |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **1** | **Prune agent tool schemas (~13.6k → ~1–2k tokens) + verify prefix cache hit** `[documented]` | Low (harness config) | **5–10×** wall-clock latency drop per turn; fixes `400 exceed_context_size_error` | `timings.prompt_ms` & `cached_tokens` | Wall-clock time doesn't drop proportionally to token count (indicates cache misses). |
| **2** | **Audit CPU ISA flags (`+dotprod`), test without `--lora`, and benchmark `llama-bench`** `[hypothesis to test]` | Low (10 min) | **2–4×** prefill if `+dotprod` was missing or LoRA is forcing slow F32 matmuls | `pp512` (t/s) | `llama-bench` without LoRA and with verified `+dotprod` still prints `< 6 t/s` on `pp512`. |
| **3** | **Switch from `Q3_K_M` to `Q4_0` with online repacking (`GGML_CPU_REPACK=ON`)** `[measured elsewhere]` | Medium (download/quantize 1 file) | **1.5–3×** prefill, **1.2–1.5×** decode on ARMv8.2-A `dotprod` | `pp512` & `tg128` | `Q4_0` `pp512` is within 15% of `Q3_K_M` `pp512` on the same build. |

### Immediate Diagnostic Command (Run This First)

```bash
# 1. Verify CPU features visible to llama.cpp and isolate LoRA + server overhead
taskset -c 4-7 ./build/bin/llama-bench \
  -m ~/models/brain.gguf \
  -t 4 -p 512 -n 128 -fa 1 -ctk q8_0 -ctv q8_0 -r 3
```

Check the startup log line for `NEON = 1 | ARM_FMA = 1 | FP16_VA = 1 | DOTPROD = 1`. If `DOTPROD = 0`, your build is missing `-march=armv8.2-a+dotprod+fp16`.

---

## 1. Is 3.71 t/s Prefill Normal for 9B Q3_K_M on 4× Cortex-A77?

**No — it indicates a bottleneck or misconfiguration** `[measured elsewhere]`.

- **Expected baseline:** On Snapdragon 865/870 (4× Cortex-A77, LPDDR5 ~44 GB/s), a properly compiled 7B–9B model typically achieves **12–25 t/s** on `pp512` and **3.5–5.5 t/s** on `tg128` `[measured elsewhere]`.
- **Why your decode (2.4–2.7 t/s) is plausible but low:** At 5.23 GB model size and ~25–30 GB/s real-world sustained RAM bandwidth, the theoretical memory-bound ceiling is ~4.8–5.7 t/s. Losing half of that to `Q3_K` dequantization overhead, LoRA, and a 2.22 GHz cap is realistic.
- **Why your prefill (3.71 t/s) is abnormally slow:** When `pp ≈ tg`, compute (not memory bandwidth) is stalling. Check these four suspects in order `[hypothesis to test]`:
  1. **Missing `DOTPROD` at compile time:** Termux/Android CMake builds sometimes default to generic `armv8-a` without `+dotprod`.
  2. **Runtime LoRA (`--lora adapter.gguf`):** Applying a LoRA dynamically adds extra matrix multiplications per layer on every prompt token. Test `llama-bench` without `--lora` (or merge the LoRA into the base GGUF offline).
  3. **`Q3_K_M` (`UD-Q3_K_XL`) bit-unpacking on ARM:** 3-bit K-quants lack fast `sdot` repacked GEMM kernels on ARMv8.2-A.
  4. **KV cache quantization + Flash Attention overhead on prefill:** Test `-ctk f16 -ctv f16` vs `q8_0` in `llama-bench`.

### Rebuild Command (Ensure `dotprod` + `fp16` + Online Repacking)

```bash
cmake -B build \
  -DCMAKE_BUILD_TYPE=Release \
  -DGGML_NATIVE=OFF \
  -DGGML_CPU_REPACK=ON \
  -DCMAKE_C_FLAGS="-march=armv8.2-a+dotprod+fp16 -mtune=cortex-a77 -O3" \
  -DCMAKE_CXX_FLAGS="-march=armv8.2-a+dotprod+fp16 -mtune=cortex-a77 -O3"
cmake --build build --config Release -j4 --target llama-bench llama-server
```

---

## 2. Quantization for ARM: `Q3_K_M` vs. Repacked `Q4_0`

- **Mechanism `[documented]`:** Modern `llama.cpp` replaced static `Q4_0_4_4` files with **online repacking** (`-DGGML_CPU_REPACK=ON`, default ON). At model load, `Q4_0` (and `IQ4_NL`) weights are repacked in RAM into `4×4` interleaved blocks tailored for ARM `sdot` instructions.
- **Expected gain `[measured elsewhere]`:** Switching a 7B–9B model from `Q3_K_M` to `Q4_0` with online repacking typically yields **1.5–3× faster prefill** and **1.2–1.5× faster decode** on Cortex-A77/A78.
- **Tradeoff:** A 9B `Q4_0` GGUF is ~5.5–5.8 GB (vs. 5.23 GB for your `UD-Q3_K_XL`), which still fits in an 8 GB Mi 10T with `q8_0` KV cache.
- **Test command:**

```bash
taskset -c 4-7 ./build/bin/llama-bench \
  -m ~/models/brain-q4_0.gguf -t 4 -p 512 -n 128 -fa 1 -ctk q8_0 -ctv q8_0
```

- **Falsification:** If `Q4_0` `pp512` is `< 1.2×` faster than `Q3_K_M`, repacking is not engaging (check load logs for `repack` messages).

---

## 3. KleidiAI (`GGML_CPU_KLEIDIAI=ON`) on Cortex-A77

- **Reality on SD865 `[documented]` / `[measured elsewhere]`:** Cortex-A77 has `dotprod` (`sdot`) and `fp16`, but **no `i8mm`** (introduced in ARMv8.6-A / Cortex-A78+). KleidiAI's largest headline gains (2–5×) target `i8mm` and `SME`.
- **Is it worth it for prefill?** Only if you switch to **`Q4_0`** (KleidiAI's microkernels primarily accelerate `Q4_0`, not `Q3_K_M`). On `dotprod`-only chips with `Q4_0`, expect a modest **10–30% prefill gain** over `llama.cpp`'s built-in `GGML_CPU_REPACK` kernels `[hypothesis to test]`.

---

## 4. BLAS (OpenBLAS) for Prompt Processing

- **Mechanism `[documented]`:** `-DGGML_BLAS=ON -DGGML_BLAS_VENDOR=OpenBLAS` offloads matrix multiplications to OpenBLAS `sgemm` when prompt batch size `> 32` (dequantizing weights on the fly).
- **Expected gain `[measured elsewhere]`:** For `Q3_K_M` (which lacks fast native ARM GEMM kernels), OpenBLAS can push 9B `pp512` from ~4 t/s to **10–18 t/s**. For repacked `Q4_0`, native `sdot` kernels usually match or beat OpenBLAS.
- **Batch sizing (`-b` / `-ub`) `[hypothesis to test]`:** On 4× A77 (512 KB L2/core, 4 MB L3), test `-b 512 -ub 512` vs `-b 1024 -ub 256`. Oversized `-ub` thrashes L3 cache; `-ub 256` or `512` is usually optimal.
- **Experiment:**

```bash
OPENBLAS_NUM_THREADS=4 taskset -c 4-7 ./build/bin/llama-bench \
  -m ~/models/brain.gguf -t 4 -p 512 -b 512,1024,2048 -ub 256,512 -n 0
```

---

## 5. KV Cache Quantization (`-ctk q8_0 -ctv q8_0`) vs. Prefill

- **Impact `[measured elsewhere]` / `[documented]`:** Quantizing K and V to `q8_0` requires Flash Attention (`-fa on`) and cuts KV cache RAM roughly in half (critical for 16k+ context on a phone). On CPU without `i8mm`, the extra dequantization costs **~5–15% prefill speed** at short contexts (512–2048 tokens), but can be neutral or faster at 16k context due to reduced RAM bandwidth pressure.
- **Experiment:**

```bash
taskset -c 4-7 ./build/bin/llama-bench \
  -m ~/models/brain.gguf -t 4 -p 512,2048 -n 64 -fa 1 \
  -ctk f16,q8_0 -ctv f16,q8_0
```

- **Falsification:** If `f16` is `> 20%` faster on `pp2048` and fits in RAM at your target `n_ctx`, switch to `-ctk f16 -ctv f16` (or hybrid `-ctk q8_0 -ctv f16`).

---

## 6. Speculative Decoding On-Device

- **Verdict `[hypothesis to test]`:** **Do not enable it yet.** Speculative decoding verifies $k$ draft tokens in a single prefill pass. At your current 3.71 t/s prefill vs. 2.5 t/s decode, verification is nearly as slow as normal decoding, yielding **no net speedup (or a slowdown)**.
- **When it works `[measured elsewhere]`:** Once prefill is **≥ 4× faster than decode** (e.g., ≥ 15 t/s prefill), pairing a 9B model with a 0.5B draft model (same tokenizer family) yields **1.3–1.8× decode speedup** on structured tool-call JSON.
- **Flags `[documented]`:**

```bash
llama-server -m brain.gguf -md draft-0.5b.gguf --draft-max 8 --draft-min 2
```

---

## 7. Hexagon NPU on Snapdragon 865

- **Verdict `[documented]`:** **Unsupported — do not pursue.**
- Snapdragon 865 uses the **Hexagon 698** DSP (**v66** architecture). `llama.cpp`'s Hexagon HTP backend (`libggml-htp-v73/v75/v79/v81.so`) requires **Hexagon Tensor Processor v73+** (Snapdragon 8 Gen 2 and newer). There is no working HTP backend path for Hexagon 698.

---

## 8. Agent-Side Optimizations (Highest Real-World Leverage)

Your harness currently sends **~13,590 tokens of tool JSON schemas + 1,786 system prompt** (~21k total, triggering `400 exceed_context_size_error` at `n_ctx: 16384`). Even at 20 t/s prefill, 15k uncached tokens take **12.5 minutes** on a cold start.

1. **Tool-schema pruning / lazy loading `[documented]`:** Replace the 13.6k-token dump with a ~1–2k-token core toolset (or a 2-step router where the model calls a `load_tool(name)` meta-tool). **Expected gain: 5–10× faster cold turns**, and eliminates the 16k context overflow.
2. **Strict prefix caching `[documented]`:** Keep `system prompt + tool schemas` byte-identical at the start of every request (no dynamic timestamps in the system prompt). Keep `--cache-reuse 256` and ensure `total_slots = 1` so the single slot isn't evicted. Watch `cached_tokens` in server logs.
3. **Keep thinking disabled for routine tool calls `[measured elsewhere]`:** You already measured a **21× latency drop** (`47 s` → `2.2 s`) with `enable_thinking: false`. Reserve thinking only for planning/recovery turns.
4. **3B–4B model as the agent driver `[hypothesis to test]`:** A Qwen-class 3B–4B `Q4_0` model runs **~2–2.5× faster** in both prefill and decode than a 9B, fits 32k context comfortably in RAM, and handles simple tool routing well. Test a 4B model on your actual tool-call eval suite before committing to 9B.

---

## 9. Thermal Reality (24/7 Fan-Cooled Phone)

- Your current setup (4× A77 pinned via `taskset -c 4-7`, capped at 2.22 GHz, dropping to 1.8 GHz > 47 °C) is already close to optimal `[documented]`.
- **One tweak to test `[hypothesis to test]`:** Cortex-A77 efficiency falls off sharply above ~2.0 GHz. Capping at **1.9–2.0 GHz** instead of 2.22 GHz often cuts heat by 15–25% while losing `< 10%` throughput, preventing the harsher drop to 1.8 GHz (or `SIGSTOP`) during long prefills.

---

## 10. Alternatives to Reject Explicitly

- **Vulkan on Adreno 650:** **Reject.** Adreno 650 lacks modern cooperative matrix/fp16 throughput and suffers from driver instability/memory-copy overhead under Turnip/proprietary Vulkan; it rarely beats a properly tuned 4× A77 `dotprod` CPU build on 865-class hardware.
- **OpenCL:** **Reject** (already established as unsupported/dead end on A6xx).
- **OnePlus Nord N10 (Snapdragon 690, 2× A77 + 6× A55):** **Reject.** Strictly worse than the Mi 10T's Snapdragon 865 (half the big cores, slower RAM bandwidth).
- **Offloading to a PC GPU over LAN:** **Accept if allowed, reject only if phone-only is a hard constraint.** Even an older 8–12 GB desktop GPU (e.g., RTX 3060) delivers **500–1,500 t/s prefill** and **50–80 t/s decode** — a **50–200×** speedup over the phone.

---

## What I Could Not Verify

- **Live November 2026 web/GitHub benchmarks:** No live web searches were performed in this run, so exact 2026 `llama.cpp` PR numbers, commit hashes, or newly published `llama-bench` tables for Qwen3.5-9B specifically are not cited here.
- **Exact KleidiAI kernel coverage in late-2026 `llama.cpp`:** Whether KleidiAI added `Q3_K` or `Q4_K` `sdot`-only kernels recently should be verified in `ggml/src/ggml-cpu/kleidiai/` in your local checkout.
- **Your exact current `CMakeCache.txt` and `llama-server` startup logs:** Confirming whether `DOTPROD=1` is currently active and whether `--lora` is the primary prefill bottleneck requires running the diagnostic `llama-bench` command above.