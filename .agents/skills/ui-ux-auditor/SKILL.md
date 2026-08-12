---
name: ui-ux-auditor
description: Comprehensive UI/UX audit skill incorporating Nielsen Usability Heuristics, WCAG 2.1 AAA Accessibility, Color Psychology & Visual Perception, Cognitive Load & Visual Overload Analysis, and User Journey Validation.
---

# UI/UX Auditor Skill

Use this skill when analyzing, auditing, or scoring web application user interfaces, page layouts, components, and user flows.

**Sources**: Nielsen Norman Group (nngroup.com), Laws of UX (lawsofux.com), WebAIM (webaim.org), WCAG 2.1 W3C.

---

## Section 1: Nielsen's 10 Usability Heuristics (NN/g)
*Source: nngroup.com/articles/ten-usability-heuristics*

Evaluate each heuristic on a scale of 0–3 severity (0 = no problem, 3 = critical):

1. **Visibility of system status** — Does the interface always inform users of what is happening? (loading states, active section, progress indicators)
2. **Match between system and real world** — Does it use language and concepts the target user (engineer/maker) understands, not internal jargon?
3. **User control and freedom** — Can users undo, redo, cancel, and escape easily without penalty?
4. **Consistency and standards** — Are UI patterns, labels, and components identical across all pages?
5. **Error prevention** — Does the design proactively prevent mistakes before they occur (e.g. form validation, disabled states)?
6. **Recognition rather than recall** — Are options visible and contextual? Users shouldn't need to memorize paths.
7. **Flexibility and efficiency of use** — Are there shortcuts and accelerators for experienced users (keyboard nav, quick filters)?
8. **Aesthetic and minimalist design** — Does every element serve a purpose? Irrelevant information competes with relevant information.
9. **Help users recognize, diagnose, and recover from errors** — Are error messages plain language, precise, and constructive?
10. **Help and documentation** — Is contextual help accessible without leaving the workflow?

---

## Section 2: Color Psychology & Visual Perception Audit
*Sources: Laws of UX — Aesthetic-Usability Effect; NN/g Visual Hierarchy*

### 2.1 The 60-30-10 Color Rule
- **60% Dominant/Base color**: Neutral backgrounds, surface colors (deep slate `#1a1a2e`, warm dark `#1e1b18`, clean light `#f5f5f0`, minimal line white `#ffffff`)
- **30% Secondary/Structural color**: Cards, sidebars, borders, containers
- **10% Accent/Action color**: Primary buttons, active states, hover highlights, important alerts

### 2.1.1 Minimal Outline / Blueprint Theme Variant
- **Base Surface**: High-purity white (`#ffffff` or `#fafafa`) for maximum contrast and zero visual noise.
- **Card Fills**: White or transparent (no heavy background color fills on cards).
- **Accents & Hierarchy**: Expressed strictly through crisp outer borders (`border: 2px solid ...`), thin structural grid lines, and subtle hover outlines instead of large color blocks.


### 2.2 Color Psychology for Technical/Engineering Interfaces
| Color Family | Psychological Effect | Correct Use |
|---|---|---|
| **Deep Blues / Slates** | Trust, precision, reliability | Main backgrounds, navigation |
| **Copper / Amber / Warm Gold** | Craft, warmth, expertise | Accent on interactive elements, hero highlights |
| **Steel Gray / Silver** | Technical, modern, neutral | Data tables, borders, secondary text |
| **Red / Danger Tones** | Urgency, risk, critical | Errors, warnings only — never decoration |
| **Green / Success Tones** | Completion, OK, safe | Confirmations, success states |
| **Cyan / Electric Blue** | Active, info, interactive | Links, active tab indicators |

### 2.3 Contrast & Perceptual Fatigue Rules (WebAIM / WCAG 2.1)
- **Normal text** (< 18pt): minimum contrast ratio **4.5:1**
- **Large text** (≥ 18pt bold): minimum contrast ratio **3:1**
- **UI components** (buttons, input borders): minimum contrast ratio **3:1** against adjacent color
- **AAA Target**: contrast ratio **7:1** for body text on critical content pages
- ❌ Avoid neon/over-saturated accent colors on large surface areas — causes eye fatigue during long reading sessions
- ❌ Avoid pure white `#ffffff` text on pure black `#000000` — excessive contrast causes halation
- ✅ Use slightly off-white on slightly off-black for long-form text comfort (e.g. `#e8e6e1` on `#1a1917`)

### 2.4 Aesthetic-Usability Effect (Laws of UX)
> *"Users often perceive aesthetically pleasing design as design that's more usable."*
- Beautiful, consistent UI creates tolerance for minor usability issues.
- Prioritize visual polish on first-impression areas: hero section, navigation, cards.
- Check: does the visual style match the brand personality (engineering precision + maker warmth)?

---

## Section 3: Cognitive Load & Visual Overload Analysis
*Source: NN/g — "Minimize Cognitive Load to Maximize Usability"*

Cognitive load = the total mental processing power needed to use the interface.

### 3.1 Types of Cognitive Load to Assess

| Type | Definition | Audit Focus |
|---|---|---|
| **Intrinsic** | Complexity of the task itself | Is the task inherently complex, or made complex by the UI? |
| **Extraneous** | Unnecessary UI complexity added by design | Clutter, redundant labels, misaligned elements |
| **Germane** | Useful cognitive effort that builds understanding | Progressive disclosure, clear categorization |

### 3.2 Visual Clutter Index — Checks
- [ ] Count the number of distinct font sizes on a single page (should be max 4: hero, h2, body, caption)
- [ ] Count distinct colors used as attention signals (should be max 3-4)
- [ ] Does the page have more than 2-3 primary CTAs visible simultaneously without clear hierarchy?
- [ ] Are decorative elements (icons, dividers, backgrounds) adding meaning or just noise?
- [ ] Is there adequate whitespace between content blocks? (minimum 24–48px between major sections)

### 3.3 Hick's Law — Menu & Choice Complexity
*"The time it takes to make a decision increases with the number and complexity of choices."*
- Navigation menus: **max 7 items** at any single level
- Filter panels: group related filters into collapsible sections (≤ 5-6 per group)
- Dropdown options: if > 10 items, add search/filter within the dropdown
- Dashboard widgets: limit visible data points per card to the most critical 3-5 metrics

### 3.4 Miller's Law — Chunking Information
*"The average person can hold about 7 (±2) items in working memory at one time."*
- Technical specs (e.g. temperature profiles, soldering parameters): chunk into groups of 5–7 values
- Navigation breadcrumbs: show max 3-4 levels
- Step-by-step instructions: number and visually separate each step

### 3.5 Visual Hierarchy Audit (NN/g)
*"A clear visual hierarchy guides the eye to the most important elements."*
- **Size & Scale**: h1 must be visually dominant; secondary content clearly subordinate
- **Contrast**: High contrast = important. Low contrast = secondary/decorative
- **Proximity (Gestalt)**: Related items must be visually close; unrelated items separated
- **Alignment**: Consistent left-alignment for reading-heavy content (F-pattern scan)
- **White Space**: Generous padding signals importance and improves comprehension

### 3.6 Peak-End Rule (Laws of UX)
*"People judge an experience largely based on how they felt at its most intense point and at its end."*
- Ensure the first screen impression (peak) is visually impressive and clear
- Error states and completion states (end) must feel resolved and satisfying — not abrupt
- Empty states (no data) should be helpful and encouraging, not blank

---

## Section 4: WCAG 2.1 Accessibility Audit
*Source: w3.org/WAI/WCAG21; webaim.org*

### 4.1 Perceivable
- All images have meaningful `alt` text or `aria-hidden="true"` for decorative images
- Color is never the ONLY means of conveying information (always pair with icon or text)
- Text can be resized to 200% without loss of content or functionality

### 4.2 Operable
- All interactive elements reachable and usable via keyboard (`Tab`, `Enter`, `Space`, arrow keys)
- Visible `:focus-visible` state on all focusable elements (minimum 2px outline, 3:1 contrast)
- Minimum click/tap target size: **44×44px** (WCAG 2.5.5 AAA) or **24×24px** (AA)
- No content that flashes more than 3 times per second (seizure risk)

### 4.3 Understandable
- Form labels are explicit and adjacent to their inputs
- Error messages identify the specific field and suggest how to fix the issue
- Language attribute set on `<html lang="ru">` for correct screen reader pronunciation

### 4.4 Robust
- Semantic HTML: `<nav>`, `<main>`, `<article>`, `<section>`, `<aside>`, `<header>`, `<footer>` used correctly
- ARIA roles only used where native HTML semantic elements don't suffice
- Form controls have explicit `<label for="">` associations

---

## Section 5: User Journey & Logic Validation

### 5.1 Core User Journeys to Test
For the "Точка Плавления" site, validate these primary flows:
1. **New visitor → understands the site's purpose** in < 5 seconds (hero clarity)
2. **Engineer → finds a soldering temperature profile** (search → filter → result → data)
3. **Reader → discovers and reads an article** (browsing → card → article → related content)
4. **Maker → uses an interactive workbench tool** (tool access → input → output → share/save)

### 5.2 Friction Point Checklist
- [ ] Is the primary navigation self-explanatory without tooltips?
- [ ] Can the user reach any core feature in ≤ 3 clicks from the homepage?
- [ ] Are loading states shown for async content (API calls, search results)?
- [ ] Are empty or error states handled gracefully (not blank white areas)?
- [ ] Does the mobile layout preserve all primary functionality?

---

## Section 6: Audit Report Output Format

When running a full audit, always output in this structured format:

```
## UI/UX Audit Report — [Page/Component Name]

### Scores (out of 10)
| Category | Score | Notes |
|---|---|---|
| Usability Heuristics (NN/g) | X/10 | ... |
| Color & Visual Perception | X/10 | ... |
| Cognitive Load & Clutter | X/10 | ... |
| Accessibility (WCAG 2.1) | X/10 | ... |
| User Journey Logic | X/10 | ... |
| **Overall UX Score** | **X/10** | |

### ✅ Key Strengths
- ...

### ⚠️ Critical Issues (Priority 1 — Fix Immediately)
- ...

### 🔧 Improvements (Priority 2 — Fix Soon)
- ...

### 💡 Enhancements (Priority 3 — Nice to Have)
- ...

### Actionable Code Fixes
[Exact CSS/PHP/HTML snippets for each critical issue]
```

---

## References
- Nielsen Norman Group: https://www.nngroup.com/articles/ten-usability-heuristics/
- Minimize Cognitive Load: https://www.nngroup.com/articles/minimize-cognitive-load/
- Visual Hierarchy in UX: https://www.nngroup.com/articles/visual-hierarchy-ux-definition/
- Laws of UX: https://lawsofux.com/
- Aesthetic-Usability Effect: https://lawsofux.com/aesthetic-usability-effect/
- Peak-End Rule: https://lawsofux.com/peak-end-rule/
- WCAG 2.1 Quick Reference: https://www.w3.org/WAI/WCAG21/quickref/
- WebAIM Contrast Checker: https://webaim.org/resources/contrastchecker/
