# Промпт для внешнего ИИ: оптимизация локального инференса под агентный цикл

**Как пользоваться:** скопировать всё, что ниже разделителя, в сильный ИИ с доступом к вебу
(Gemini / Claude / GPT / Grok с поиском). Промпт намеренно на английском: запросы про llama.cpp,
ARM-ядра и Android-сборки на английском дают на порядок лучшую выдачу по репозиториям, блогам и видео.

**Что уже проверено нами и не требует повторного поиска** (см. `TZ-LOCAL-MODEL-IN-DSH.md`, раздел FIX-5):
KleidiAI (`GGML_CPU_KLEIDIAI=ON`), BLAS-сборка под prompt processing, и то, что OpenCL на Adreno 650 — тупик.
Промпт просит искать **дальше этого**.

---

You are a senior performance engineer specializing in llama.cpp on ARM mobile SoCs, Android/Termux builds, and LLM agent harnesses. You have web access: search GitHub (llama.cpp issues/PRs/discussions, ggml-org, community forks), technical blogs, Reddit r/LocalLLaMA, YouTube talks/benchmarks, and Qualcomm/Arm developer documentation. Current date: November 2026 — prefer recent material, always give dates.

## Your task

Given the measured setup and numbers below, produce a **prioritized optimization plan** to make a phone-hosted 9B model usable as the driver of an agent loop (tool-calling, ~1–20k-token prompts), and to raise raw prefill/decode throughput.

## Environment (all facts are measured unless marked "assumed")

Hardware: Xiaomi Mi 10T, Snapdragon 865 SoC, Android, rooted, running inside Termux. 4 big cores (Cortex-A77) pinned via `taskset -c 4,5,6,7`. Device runs 24/7 with an external fan; CPU max frequency is capped at 2.22 GHz by a thermal guard script (drops to 1.8 GHz above 47 °C; an optional emergency mode can `SIGSTOP` the server above 55 °C). Power budget ~15–18 W for the SoC.

Model: single GGUF at `~/models/brain.gguf` — a Qwen3.5-class 9B, unsloth dynamic quant **Q3_K_M / "UD-Q3_K_XL"**, 9.20 B params, file size 5.23 GB, `n_embd` 4096, `n_vocab` 248320, `n_ctx_train` 262144. One LoRA adapter (`adapter.gguf`, scale 1.0) is loaded on top. Vision is disabled.

Server: `llama-server` built from source for Android arm64. Measured effective configuration: `n_ctx = 16384` (reported by `/props`), `total_slots = 1`, KV cache quantized `q8_0` for both K and V, `--flash-attn`, `--jinja`, `--cache-reuse 256`, batch/ubatch **assumed** 2048/512 (defaults), threads 4, speculative decoding **off** (`"speculative.types":"none"`).

## Measured performance (baseline — treat as ground truth)

- **Prefill: 269 ms per prompt token = 3.71 tokens/s**, linear and stable across prompt sizes 16 → 276 → 1113 tokens. (1113 tokens = 299.7 s of prompt processing.)
- **Decode: 2.4–2.7 tokens/s.**
- Thinking traces on: "What is 2+2?" → 47 s, 103 completion tokens, 360 chars of `reasoning_content`.
- Thinking disabled via `chat_template_kwargs: {enable_thinking: false}`: **2.2 s, 2 completion tokens**, no `reasoning_content`.
- Prompt caching works (`cached_tokens` > 0 on a repeated prefix).
- A 20 905-token prompt is rejected: `400 exceed_context_size_error, n_ctx: 16384`.
- Tool calling works: a request with a `tools` array returned `finish_reason: tool_calls` with correct arguments.

Client-side context: the agent harness prepends ~13 590 tokens of tool JSON schemas plus ~1 786 tokens of system prompt and a skill catalog, so a "standard" agent request is ~21k tokens. A trimmed agent preset could bring it to ~1–2k tokens.

## Questions to answer

1. **Is 3.7 tokens/s prefill plausible for a 9B Q3_K_M on 4× Cortex-A77 @ 2.22 GHz, or does it indicate a misconfiguration?** Find published `llama-bench` numbers for Snapdragon 865 / 870 / 888 class devices on comparable models and quantizations. If our number is 3–10× off the norm, tell us what specifically to check (build flags, backend selection, thread/affinity settings, mmap/mlock, batch sizes, KV quantization overhead on prefill, LoRA overhead on prefill).
2. **Quantization for ARM.** Q3_K quants are known to be CPU-unfriendly relative to repacked Q4_0 (`Q4_0_4_4` / `Q4_0_8_8`, `GGML_CPU_REPACK`, dotprod/i8mm paths). Quantify: what speedup do people report on ARM for 7–9B models switching from Q3_K_M to repacked Q4_0 at similar quality-per-byte, and what exact build/runtime flags enable the repacked path?
3. **KleidiAI** (`GGML_CPU_KLEIDIAI=ON`): what measured gains are reported for Cortex-A77 / Snapdragon 865 class chips (dotprod available, i8mm not)? Is it worth it for prompt processing specifically?
4. **BLAS for prompt processing.** llama.cpp docs state BLAS helps prompt processing at batch > 32. In Termux (OpenBLAS package), what prompt-processing throughput do people actually get for a 9B model, and which batch sizes (`-b`/`-ub`) maximize prefill on a 4-core big cluster with ~44 GB/s memory bandwidth?
5. **KV cache quantization vs prefill.** Does `-ctk q8_0 -ctv q8_0` cost prompt-processing speed relative to f16 in current llama.cpp, and by how much? Any measured guidance for 16k–32k context on phones?
6. **Speculative decoding on-device.** With an MTP/draft head or a small draft model (0.5–1B), what decode-speed multiples are realistic on a phone, and what is the prefill cost of the verification step?
7. **Hexagon NPU.** llama.cpp now ships an experimental Hexagon backend with `libggml-htp-v73/v75/v79/v81.so` builds. Which Hexagon architecture generation does Snapdragon 865 (Hexagon 698) report, and is there any working deployment of this backend on 865-class hardware — or is the minimum supported HTP version higher?
8. **Agent-side optimizations** (equally important as raw speed): how do practitioners run local models in tool-calling agents with long static prefixes? Cover tool-schema pruning, prompt caching/prefix reuse tuning (`--cache-reuse`, `--n-cache-reuse` variants), grammar/JSON-constrained tool calls, and whether a smaller model (3–4B) as the agent driver beats a 9B at these speeds.
9. **Thermal reality.** For sustained 24/7 inference on a fan-cooled phone: what sustained frequency and throughput do people report, and what throttle-aware tricks (frequency caps, core pinning, duty cycling) preserve throughput best?
10. **Alternatives we should consider and reject explicitly:** Vulkan on Adreno 650, OpenCL (we already established A6xx-in-phones is unsupported — do not revisit), running the model on the Nord N10 (Snapdragon 662-class) instead, or offloading to a PC GPU over LAN.

## Hard requirements for your answer

- **Do not invent flags or endpoints.** Every CLI/env/CMake option you name must exist in current llama.cpp; say which version introduced it if it is recent.
- **Separate measured from inferred.** Label each recommendation as [measured elsewhere], [documented], or [hypothesis to test].
- **Give exact commands** for each experiment (build flags, `llama-bench` invocations, server flags) and the metric to watch (pp512 / tg128 / `timings.prompt_per_second`).
- **Estimate expected gain** per experiment, and say what would falsify it.
- **Cite sources with links and dates**: GitHub issues/PRs/discussion numbers, docs pages, blog posts, video titles + channels. Prefer primary sources; flag marketing claims.
- **Rank by (expected gain ÷ effort)**, and put the top 3 experiments in a one-page action list at the top of your answer.
- End with a short "what I could not verify" section.
