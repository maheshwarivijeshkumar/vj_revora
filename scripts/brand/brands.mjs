/**
 * Brand definitions for the eight candidate identities in
 * docs/AI_Omnichannel_Lead_Generation_CRM_SaaS_Master_System_Prompt_UPDATED.md (§105).
 *
 * Every mark is authored in a 100x100 viewBox and must stay legible at 16px, so
 * each one is built from a handful of heavy shapes rather than fine detail.
 *
 * `mark(P, A, K)` returns the SVG body. `P` is the primary paint, `A` the accent
 * paint — either may be a solid colour or a `url(#...)` gradient reference, so
 * the same geometry serves the app icon and both favicons. `K` is the knockout
 * colour, i.e. whatever reads as "background" for the variant being rendered;
 * only marks that cut detail out of a solid silhouette need it.
 */

export const brands = [
    {
        key: 'leadora',
        name: 'Leadora',
        tagline: 'Turn Every Lead Into Opportunity.',
        primary: '#4F46E5',
        secondary: '#2563EB',
        accent: '#06B6D4',
        // Abstract L with a forward-moving foot, plus an AI spark highlight.
        mark: (P, A) => `
    <path d="M34,22 V60 a10,10 0 0 0 10,10 H68"
          fill="none" stroke="${P}" stroke-width="16"
          stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M76,14 L79.54,24.46 L90,28 L79.54,31.54 L76,42 L72.46,31.54 L62,28 L72.46,24.46 Z"
          fill="${A}"/>`,
    },
    {
        key: 'leadpulse',
        name: 'LeadPulse',
        tagline: 'More Leads. Higher Conversions.',
        primary: '#0F766E',
        secondary: '#14B8A6',
        accent: '#2563EB',
        // Heartbeat waveform: lead activity as a real-time signal.
        mark: (P) => `
    <path d="M12,54 H32 L40,28 L50,74 L60,42 L68,54 H88"
          fill="none" stroke="${P}" stroke-width="12"
          stroke-linecap="round" stroke-linejoin="round"/>`,
    },
    {
        key: 'leadminds',
        name: 'LeadMinds',
        tagline: 'Smarter Leads. Better Decisions.',
        primary: '#4F46E5',
        secondary: '#7C3AED',
        accent: '#A855F7',
        // Solid head silhouette with the network knocked out of it. An outlined
        // head with inner dots collapses into a face at 16px; a filled mass with
        // high-contrast holes keeps its silhouette instead. Three large nodes
        // only — four render as noise once downsampled.
        mark: (P, A, K) => `
    <path d="M50,10 C72,10 88,26 88,47 C88,57 84,63 80,68 L80,79
             C80,86 75,91 68,91 L40,91 C33,91 28,86 28,79 L28,73
             C17,66 11,56 11,45 C11,25 28,10 50,10 Z" fill="${P}"/>
    <g stroke="${K}" stroke-width="5" stroke-linecap="round">
      <path d="M38,40 L62,35"/><path d="M62,35 L55,62"/><path d="M55,62 L38,40"/>
    </g>
    <g fill="${K}">
      <circle cx="38" cy="40" r="7"/><circle cx="62" cy="35" r="7"/>
      <circle cx="55" cy="62" r="7"/>
    </g>`,
    },
    {
        key: 'leadflow',
        name: 'LeadFlow',
        tagline: 'Capture. Nurture. Convert.',
        primary: '#2563EB',
        secondary: '#0EA5E9',
        accent: '#10B981',
        // Three staggered streaks: capture -> nurture -> convert. Rows are pushed
        // to the edges of the canvas and the strokes thinned, otherwise the three
        // arrows fuse into one solid block at favicon sizes.
        mark: (P, A) => `
    <g stroke="${P}" stroke-width="10" stroke-linecap="round" fill="none">
      <path d="M20,22 H50"/><path d="M14,50 H58"/><path d="M20,78 H46"/>
    </g>
    <g fill="${A}" stroke="${A}" stroke-width="4" stroke-linejoin="round">
      <path d="M52,13 L72,22 L52,31 Z"/>
      <path d="M60,41 L82,50 L60,59 Z"/>
      <path d="M48,69 L68,78 L48,87 Z"/>
    </g>`,
    },
    {
        key: 'revora',
        name: 'Revora',
        tagline: 'Grow Faster. Generate More Revenue.',
        primary: '#059669',
        secondary: '#10B981',
        accent: '#2563EB',
        // Rising revenue bars with a trend arrow clearing the bar tops, so the
        // two elements never overlap and muddy the shape at small sizes.
        mark: (P, A) => `
    <g fill="${P}">
      <rect x="12" y="60" width="17" height="28" rx="8"/>
      <rect x="37" y="48" width="17" height="40" rx="8"/>
      <rect x="62" y="36" width="17" height="52" rx="8"/>
    </g>
    <g fill="none" stroke="${A}" stroke-width="9"
       stroke-linecap="round" stroke-linejoin="round">
      <path d="M14,54 L36,42 L54,48 L84,20"/>
      <path d="M68,20 H84 V36"/>
    </g>`,
    },
    {
        key: 'leadforge',
        name: 'LeadForge',
        tagline: 'Turn Interest Into Impact.',
        primary: '#EA580C',
        secondary: '#F97316',
        accent: '#EF4444',
        // Slanted geometric F: raw interest forged into qualified opportunity.
        // Corners are rounded by stroking the path in its own paint.
        mark: (P) => `
    <g transform="skewX(-10) translate(8,0)">
      <path d="M28,14 H80 V34 H50 V46 H74 V66 H50 V88 H28 Z"
            fill="${P}" stroke="${P}" stroke-width="7" stroke-linejoin="round"/>
    </g>`,
    },
    {
        key: 'leadorbit',
        name: 'LeadOrbit',
        tagline: 'Connect. Engage. Grow.',
        primary: '#6D28D9',
        secondary: '#7C3AED',
        accent: '#4F46E5',
        // Opportunity node inside an orbital ring: channels in continuous motion.
        // A centred node close in size to the ring reads as an eye, so the node
        // is kept small and the ring flattened until it reads as an orbit.
        mark: (P, A) => `
    <circle cx="50" cy="50" r="14" fill="${P}"/>
    <ellipse cx="50" cy="50" rx="43" ry="13" fill="none"
             stroke="${A}" stroke-width="7" transform="rotate(-25 50 50)"/>
    <circle cx="89" cy="32" r="6.5" fill="${A}"/>`,
    },
    {
        key: 'leadnest',
        name: 'LeadNest',
        tagline: 'Your Leads. Our Priority.',
        primary: '#059669',
        secondary: '#0D9488',
        accent: '#14B8A6',
        // Abstract leaf-cradle holding three ascending leads. Deliberately not a
        // bird. The cradle tapers to a point rather than curving into a bowl: a
        // round-bottomed vessel with nodes above it reads as a smiley face, and
        // the ascending (rather than symmetric) nodes break that read further.
        mark: (P, A) => `
    <path d="M10,36 Q10,54 44,88 Q50,93 56,88 Q90,54 90,36
             C76,50 63,54 50,54 C37,54 24,50 10,36 Z" fill="${P}"/>
    <circle cx="27" cy="43" r="6.5" fill="${A}"/>
    <circle cx="50" cy="31" r="7.5" fill="${A}"/>
    <circle cx="74" cy="19" r="8.5" fill="${A}"/>`,
    },
];

/** Surfaces the favicon plates sit on, per §106.2 / §123 (never pure black). */
export const SURFACE_LIGHT = '#F1F5F9';
export const SURFACE_DARK = '#0B1220';
