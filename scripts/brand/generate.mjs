/**
 * Generates the brand icon system for all eight candidate identities.
 *
 * Per-brand output mirrors the supplied brand board exactly: a horizontal logo
 * lockup, an App Icon, a Light Favicon and a Dark Favicon — nothing else.
 *
 *   resources/brand/<key>/   SVG masters (vector source of truth)
 *   public/brand/<key>/      PNG + WebP rasters and .ico bundles
 *
 * Run: node scripts/brand/generate.mjs
 */

import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';
import { brands, SURFACE_LIGHT, SURFACE_DARK } from './brands.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const svgOut = join(root, 'resources', 'brand');
const rasterOut = join(root, 'public', 'brand');

/** Sizes from §105.3, filtered to the artifacts the brand board actually shows. */
const APP_ICON_SIZES = [1024, 512, 192, 180];
const FAVICON_SIZES = [16, 32, 48, 64];
const ICO_SIZES = [16, 32, 48];

const TEXT_ON_LIGHT = '#0F172A';
const TEXT_ON_DARK = '#F8FAFC';
const MUTED_ON_LIGHT = '#64748B';
const MUTED_ON_DARK = '#94A3B8';

const gradient = (id, from, to) =>
    `<linearGradient id="${id}" x1="0" y1="0" x2="1" y2="1">` +
    `<stop offset="0" stop-color="${from}"/><stop offset="1" stop-color="${to}"/>` +
    `</linearGradient>`;

/**
 * Symbol master: the mark alone on a transparent canvas, in brand gradient.
 * This is the file every other asset is conceptually derived from.
 */
const symbolSvg = (
    b,
) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100" role="img" aria-label="${b.name}">
  <defs>${gradient('g', b.primary, b.secondary)}${gradient('a', b.secondary, b.accent)}</defs>
${b.mark('url(#g)', 'url(#a)', '#FFFFFF')}
</svg>`;

/** App Icon: full-bleed brand gradient squircle with a white knockout mark. */
const appIconSvg = (
    b,
) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100" role="img" aria-label="${b.name} app icon">
  <defs>${gradient('g', b.primary, b.secondary)}</defs>
  <rect width="100" height="100" rx="23" fill="url(#g)"/>
  <g transform="translate(16,16) scale(0.68)">
${b.mark('#FFFFFF', 'rgba(255,255,255,0.92)', b.primary)}
  </g>
</svg>`;

/**
 * Favicon plate. The dark variant shifts the gradient toward the lighter
 * secondary/accent stops so the mark keeps contrast against deep navy (§123).
 */
const faviconSvg = (b, mode) => {
    const surface = mode === 'dark' ? SURFACE_DARK : SURFACE_LIGHT;
    const [from, to] =
        mode === 'dark' ? [b.secondary, b.accent] : [b.primary, b.secondary];
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100" role="img" aria-label="${b.name} favicon (${mode})">
  <defs>${gradient('g', from, to)}${gradient('a', to, from)}</defs>
  <rect width="100" height="100" rx="20" fill="${surface}"/>
  <g transform="translate(14,14) scale(0.72)">
${b.mark('url(#g)', 'url(#a)', surface)}
  </g>
</svg>`;
};

/**
 * Horizontal lockup: symbol + wordmark + tagline. Names prefixed with "Lead"
 * are split two-tone the way the brand board renders them.
 */
const logoSvg = (b, mode) => {
    const text = mode === 'dark' ? TEXT_ON_DARK : TEXT_ON_LIGHT;
    const muted = mode === 'dark' ? MUTED_ON_DARK : MUTED_ON_LIGHT;
    const brandInk = mode === 'dark' ? b.secondary : b.primary;

    // Two-tone only for true Lead+Word compounds (LeadPulse, LeadFlow, ...).
    // "Leadora" is a portmanteau and the brand board sets it in a single ink.
    const compound = /^Lead[A-Z]/.test(b.name);
    const wordmark = compound
        ? `<tspan fill="${text}">Lead</tspan><tspan fill="${brandInk}">${b.name.slice(4)}</tspan>`
        : `<tspan fill="${text}">${b.name}</tspan>`;

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 500 150" width="500" height="150" role="img" aria-label="${b.name} — ${b.tagline}">
  <defs>${gradient('g', b.primary, b.secondary)}${gradient('a', b.secondary, b.accent)}</defs>
  <g transform="translate(16,30) scale(0.9)">
${b.mark('url(#g)', 'url(#a)', mode === 'dark' ? SURFACE_DARK : '#FFFFFF')}
  </g>
  <text x="128" y="86" font-family="Inter, 'Segoe UI', system-ui, sans-serif"
        font-size="52" font-weight="700" letter-spacing="-1">${wordmark}</text>
  <text x="130" y="117" font-family="Inter, 'Segoe UI', system-ui, sans-serif"
        font-size="19" font-weight="400" fill="${muted}">${b.tagline}</text>
</svg>`;
};

/**
 * Minimal ICO container. An .ico is just a directory of embedded PNGs, so the
 * already-rendered PNG buffers are reused verbatim.
 */
function buildIco(pngs) {
    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0); // reserved
    header.writeUInt16LE(1, 2); // type: icon
    header.writeUInt16LE(pngs.length, 4);

    let offset = 6 + pngs.length * 16;
    const entries = pngs.map(({ size, data }) => {
        const e = Buffer.alloc(16);
        e.writeUInt8(size >= 256 ? 0 : size, 0); // width
        e.writeUInt8(size >= 256 ? 0 : size, 1); // height
        e.writeUInt8(0, 2); // palette size
        e.writeUInt8(0, 3); // reserved
        e.writeUInt16LE(1, 4); // colour planes
        e.writeUInt16LE(32, 6); // bits per pixel
        e.writeUInt32LE(data.length, 8);
        e.writeUInt32LE(offset, 12);
        offset += data.length;
        return e;
    });

    return Buffer.concat([header, ...entries, ...pngs.map((p) => p.data)]);
}

/**
 * Rasterises once at high density, then downsamples. Rendering each small size
 * directly from the SVG produces noticeably worse antialiasing at 16px.
 */
async function rasterise(svg, sizes, dir, base) {
    const master = await sharp(Buffer.from(svg), { density: 2048 })
        .resize(1024, 1024)
        .png()
        .toBuffer();

    const pngs = [];
    for (const size of sizes) {
        const png = await sharp(master)
            .resize(size, size, { kernel: 'lanczos3' })
            .png({ compressionLevel: 9 })
            .toBuffer();
        await writeFile(join(dir, `${base}-${size}.png`), png);
        await writeFile(
            join(dir, `${base}-${size}.webp`),
            await sharp(png).webp({ quality: 95, effort: 6 }).toBuffer(),
        );
        pngs.push({ size, data: png });
    }
    return pngs;
}

let files = 0;
const write = async (path, data) => {
    await writeFile(path, data);
    files += 1;
};

for (const b of brands) {
    const sDir = join(svgOut, b.key);
    const rDir = join(rasterOut, b.key);
    await mkdir(sDir, { recursive: true });
    await mkdir(rDir, { recursive: true });

    // --- SVG masters -------------------------------------------------------
    const assets = {
        [`${b.key}-symbol.svg`]: symbolSvg(b),
        [`${b.key}-app-icon.svg`]: appIconSvg(b),
        [`${b.key}-favicon-light.svg`]: faviconSvg(b, 'light'),
        [`${b.key}-favicon-dark.svg`]: faviconSvg(b, 'dark'),
        [`${b.key}-logo-light.svg`]: logoSvg(b, 'light'),
        [`${b.key}-logo-dark.svg`]: logoSvg(b, 'dark'),
    };
    for (const [name, svg] of Object.entries(assets)) {
        await write(join(sDir, name), svg);
    }
    // Scalable favicon for <link rel="icon" type="image/svg+xml">.
    await write(join(rDir, 'favicon.svg'), symbolSvg(b));

    // --- Rasters -----------------------------------------------------------
    await rasterise(
        assets[`${b.key}-app-icon.svg`],
        APP_ICON_SIZES,
        rDir,
        'app-icon',
    );
    const light = await rasterise(
        assets[`${b.key}-favicon-light.svg`],
        FAVICON_SIZES,
        rDir,
        'favicon-light',
    );
    const dark = await rasterise(
        assets[`${b.key}-favicon-dark.svg`],
        FAVICON_SIZES,
        rDir,
        'favicon-dark',
    );
    files += (APP_ICON_SIZES.length + FAVICON_SIZES.length * 2) * 2;

    // --- ICO bundles -------------------------------------------------------
    const pick = (set) => set.filter((p) => ICO_SIZES.includes(p.size));
    await write(join(rDir, 'favicon.ico'), buildIco(pick(light)));
    await write(join(rDir, 'favicon-dark.ico'), buildIco(pick(dark)));

    console.log(`  ${b.name.padEnd(10)} ${b.primary} → ${b.secondary}`);
}

console.log(`\nDone. ${files} files across ${brands.length} brands.`);
console.log(`  SVG masters: resources/brand/<brand>/`);
console.log(`  Rasters:     public/brand/<brand>/`);
