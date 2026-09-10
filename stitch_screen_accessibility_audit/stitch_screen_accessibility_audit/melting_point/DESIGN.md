---
name: Melting Point
colors:
  surface: '#f9f9f9'
  surface-dim: '#dadada'
  surface-bright: '#f9f9f9'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f4f3f3'
  surface-container: '#eeeeee'
  surface-container-high: '#e8e8e8'
  surface-container-highest: '#e2e2e2'
  on-surface: '#1a1c1c'
  on-surface-variant: '#4c4546'
  inverse-surface: '#2f3131'
  inverse-on-surface: '#f1f1f1'
  outline: '#7e7576'
  outline-variant: '#cfc4c5'
  surface-tint: '#5e5e5e'
  primary: '#000000'
  on-primary: '#ffffff'
  primary-container: '#1b1b1b'
  on-primary-container: '#848484'
  inverse-primary: '#c6c6c6'
  secondary: '#a73a00'
  on-secondary: '#ffffff'
  secondary-container: '#fc6c2b'
  on-secondary-container: '#5a1c00'
  tertiary: '#000000'
  on-tertiary: '#ffffff'
  tertiary-container: '#1e1c00'
  on-tertiary-container: '#898658'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#e2e2e2'
  primary-fixed-dim: '#c6c6c6'
  on-primary-fixed: '#1b1b1b'
  on-primary-fixed-variant: '#474747'
  secondary-fixed: '#ffdbce'
  secondary-fixed-dim: '#ffb599'
  on-secondary-fixed: '#370e00'
  on-secondary-fixed-variant: '#7f2b00'
  tertiary-fixed: '#e9e5b0'
  tertiary-fixed-dim: '#cdc996'
  on-tertiary-fixed: '#1e1c00'
  on-tertiary-fixed-variant: '#4a4821'
  background: '#f9f9f9'
  on-background: '#1a1c1c'
  surface-variant: '#e2e2e2'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 72px
    fontWeight: '900'
    lineHeight: '1.0'
    letterSpacing: -0.04em
  headline-xl:
    fontFamily: Inter
    fontSize: 48px
    fontWeight: '800'
    lineHeight: '1.1'
    letterSpacing: -0.02em
  headline-xl-mobile:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '800'
    lineHeight: '1.1'
  headline-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '800'
    lineHeight: '1.2'
  body-lg:
    fontFamily: Hanken Grotesk
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.5'
  label-caps:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '700'
    lineHeight: '1.0'
    letterSpacing: 0.1em
  handwritten-lg:
    fontFamily: Caveat
    fontSize: 24px
    fontWeight: '400'
    lineHeight: '1.2'
  handwritten-sm:
    fontFamily: Caveat
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.1'
spacing:
  grid-margin: 2rem
  grid-gutter: 1rem
  bento-padding: 1.5rem
  stack-sm: 0.5rem
  stack-md: 1rem
  stack-lg: 2rem
---

## Brand & Style

The design system is built on a **Bento-Sketch Hybrid** aesthetic, merging the structural efficiency of grid-based layouts with the raw, expressive energy of Brutalism. It targets a creative, technical audience—innovators who appreciate the intersection of industrial precision and artistic spontaneity. 

The UI should feel like a high-end digital whiteboard or a designer's physical workbench. It utilizes heavy borders, "tape" textures, and hand-drawn accents to humanize a rigid grid. The emotional response is one of organized chaos: functional enough for productivity, but tactile enough to inspire exploration. 

**Core Style Principles:**
- **Structured Brutalism:** Grid lines are visible and heavy, emphasizing the "Bento" containerization.
- **Analogue Metaphors:** Digital elements mimic physical desk items like sticky notes, masking tape, and cardboard.
- **Dynamic Sketching:** Use vector illustrations that look hand-inked, providing a human "hand-crafted" contrast to sharp digital typography.

## Colors

The palette is rooted in a high-contrast monochromatic base, punctuated by functional, high-energy accents.

- **High-Contrast Black (#000000) & White (#FFFFFF):** Used for the primary structural grid, borders, and main body text.
- **Melting Orange (#fc6c2b):** Reserved for highlights, primary calls to action, and "active" states.
- **Sticky Yellow (#fef9c3):** Used exclusively for note-style components and callouts.
- **Widget Peach (#ffeddb):** A soft background for interactive dashboard elements to distinguish them from static content.
- **Cardboard Grey (#d1cfc7):** Applied to full-width containers or manifesto sections to provide a tactile, grounded texture.

## Typography

The typography system relies on a sharp contrast between industrial sans-serifs and organic handwriting.

- **Headlines:** Use **Inter Extra Bold** in uppercase for all article and section headers. Tight letter spacing is required to give headers a blocky, architectural feel.
- **Brand & Body:** **Hanken Grotesk** serves as the logo font and primary body typeface. It provides a contemporary, clean counter-balance to the aggressive headers.
- **Annotations:** Use **Caveat** for slogans, tooltips, or "scribbled" notes over cards. These should often be rotated slightly (±2-3 degrees) to enhance the sketch-like quality.

## Layout & Spacing

This design system utilizes a **12-column Bento Grid** model. Content is organized into modular "bento boxes" of varying heights and widths.

- **Bento Cells:** Every card or container must align to the underlying 12-column grid. On mobile, this collapses into a 1-column or 2-column stack.
- **Visual Gaps:** Maintain a consistent 16px (1rem) gutter between all bento cells to ensure the "hard shadow" effects do not overlap messily.
- **Margins:** Page margins are generous (2rem+) to allow the "sketch" elements (tape, handwritten notes) to bleed into the whitespace without cluttering the content.

## Elevation & Depth

Depth in this system is strictly **mechanical**, not optical. There are no soft shadows or blurs.

- **Hard Shadows:** Cards use a 6px offset shadow (down and right) with #000000 at 100% opacity. 
- **The Shift:** Interactive elements (cards, buttons) must physically shift 4px down and 4px right on hover/active states, while the shadow shrinks to 2px, simulating the object being "pressed" into the surface.
- **Layering:** Use "Tape" accents (small, semi-transparent rectangular overlays at card corners) to simulate items being stuck onto the grid.

## Shapes

The shape language is primarily **Sharp (0px)** to maintain a brutalist, industrial look.

- **Borders:** All cards and containers feature a 3px solid black border.
- **Exceptions:** Buttons may use a "Pill" shape (fully rounded) to provide a distinct hit area that stands out against the sharp rectangular grid.
- **Accents:** Tape strips should have irregular, "torn" edges if possible via masking, or simple sharp rectangular cuts.

## Components

### Buttons
- **Primary:** Black pill-shaped containers with White Hanken Grotesk text. 
- **Secondary:** Framed boxes (3px border) with no fill.
- **Interaction:** On hover, the background fills with Melting Orange (#fc6c2b).

### Cards
- **Structure:** 3px black border, 6px hard shadow.
- **Variants:**
    - *Sticky Card:* Background color #fef9c3, slight rotation.
    - *Widget Card:* Background color #ffeddb.
    - *Manifesto Card:* Background color #d1cfc7, usually spans 12 columns.
- **Accents:** Place a "Tape" graphic across at least one corner of featured cards.

### Input Fields
- **Style:** Rectangular, 3px black bottom-border only for a "sketchbook" feel, or full box with 3px border.
- **Focus:** Border changes to Melting Orange.

### Icons & Visuals
- **Sketch Style:** Icons must look hand-drawn with variable line weights.
- **Hover Transition:** Icons start in grayscale/outline. On hover of the parent card, the icon "fills" with a sketch-style splash of color (Orange or Peach).

### Chips & Tags
- **Style:** Small 2px bordered boxes with uppercase Inter Bold text. No rounded corners.