const { mkdirSync, writeFileSync } = require('node:fs');
const { execFileSync } = require('node:child_process');
const { resolve } = require('node:path');
const lessons = require('./gt-1-4-content.cjs');

const root = resolve(__dirname, '../..');
const ink = '#16352e';
const paper = '#fcfaf4';
const accents = ['#215b4e', '#8d422d', '#285579', '#4d612b'];
const tints = ['#def0e7', '#f8e6db', '#e1eef7', '#eaf0d9'];
const xml = value => String(value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' }[ch]));
const rect = (x, y, w, h, fill, stroke = 'none', r = 14) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${r}" fill="${fill}" stroke="${stroke}" stroke-width="2"/>`;
const line = (x1, y1, x2, y2, arrow = false) => `<path d="M${x1} ${y1}L${x2} ${y2}" fill="none" stroke="${ink}" stroke-width="3"${arrow ? ' marker-end="url(#arrow)"' : ''}/>`;
const circle = (x, y, r, fill) => `<circle cx="${x}" cy="${y}" r="${r}" fill="${fill}" stroke="${ink}" stroke-width="2.5"/>`;
const label = (value, x, y, size = 24, weight = 400, anchor = 'start', fill = ink) => `<text x="${x}" y="${y}" font-size="${size}" font-weight="${weight}" text-anchor="${anchor}" fill="${fill}">${xml(value)}</text>`;

function wrap(value, limit) {
    const lines = [''];
    for (const word of value.split(' ')) {
        const last = lines.length - 1;
        if (lines[last] && lines[last].length + word.length + 1 > limit) lines.push(word);
        else lines[last] += (lines[last] ? ' ' : '') + word;
    }
    return lines;
}

function paragraph(value, x, y, width, size, weight = 400, anchor = 'start') {
    const rows = wrap(value, Math.floor(width / (size * 0.57)));
    return [rows.map((row, i) => label(row, x, y + i * (size + 8), size, weight, anchor)).join(''), rows.length * (size + 8)];
}

function symbol(name, x, y, accent, tint, scale = 1) {
    const path = (d, fill = 'none') => `<path d="${d}" fill="${fill}" stroke="${ink}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>`;
    const cell = (cx, cy, r) => circle(cx, cy, r, tint) + circle(cx, cy, r * .35, accent);
    const chr = (cx, cy, color) => `<path d="M${cx - 11} ${cy - 21}Q${cx} ${cy} ${cx + 11} ${cy + 21}M${cx + 11} ${cy - 21}Q${cx} ${cy} ${cx - 11} ${cy + 21}" fill="none" stroke="${color}" stroke-width="8" stroke-linecap="round"/>`;
    const person = (cx, cy, s) => `<g transform="translate(${cx} ${cy}) scale(${s})">${circle(0, -27, 11, tint)}${path('M0 -15V20M-18 0H18M0 20L-14 43M0 20L14 43')}</g>`;
    const dna = () => path('M14 8C14 30 86 30 86 50S14 69 14 92M86 8C86 30 14 30 14 50S86 69 86 92') + [15, 33, 50, 67, 85].map((v, i) => line(i % 2 ? 36 : 19, v, i % 2 ? 64 : 81, v)).join('');
    const icons = {
        cell: () => cell(50, 50, 36),
        plantCell: () => rect(10, 12, 80, 76, tint, ink, 10) + rect(16, 18, 68, 64, '#fff', ink, 7) + rect(39, 28, 36, 43, '#d5eafa', ink, 9) + circle(29, 49, 8, accent) + [27, 43, 64].map(v => `<ellipse cx="${v}" cy="25" rx="5" ry="3.5" fill="#437545"/>`).join(''),
        tissue: () => [22, 50, 78].map(cx => [29, 55, 81].map(cy => cell(cx, cy, 12)).join('')).join(''),
        organ: () => path('M50 86C12 64 4 38 19 22C29 11 43 17 50 29C57 17 71 11 81 22C96 38 88 64 50 86Z', tint),
        person: () => person(50, 43, 1),
        growth: () => person(28, 53, .62) + person(72, 43, .94),
        leaf: () => path('M16 75C20 22 48 12 89 11C86 55 62 88 16 75Z', tint) + path('M9 88L73 27M34 65L31 41M52 50L71 51'),
        flower: () => path('M50 51V91M50 77Q22 78 20 61Q45 61 50 77', tint) + [0, 72, 144, 216, 288].map(a => `<ellipse cx="50" cy="24" rx="12" ry="18" fill="${tint}" stroke="${ink}" stroke-width="2" transform="rotate(${a} 50 42)"/>`).join('') + circle(50, 42, 10, accent),
        dna,
        chromosome: () => chr(33, 50, accent) + chr(67, 50, ink),
        copy: () => cell(50, 50, 42) + chr(36, 50, accent) + chr(65, 50, ink),
        divide: () => cell(25, 50, 21) + cell(75, 50, 21),
        four: () => [25, 75].map(cx => [25, 75].map(cy => cell(cx, cy, 19)).join('')).join(''),
        sperm: () => `<ellipse cx="30" cy="33" rx="13" ry="18" transform="rotate(-30 30 33)" fill="${tint}" stroke="${ink}" stroke-width="3"/>` + path('M40 47C46 74 76 43 87 73'),
        egg: () => circle(50, 50, 34, tint) + circle(50, 50, 12, accent),
        uterus: () => path('M19 26Q35 19 42 32Q50 39 58 32Q65 19 81 26M31 30Q30 55 44 66L44 86H56V66Q70 55 69 30', tint) + circle(16, 29, 9, tint) + circle(84, 29, 9, tint),
        implant: () => path('M10 23Q39 18 48 48Q61 22 89 23V86H10Z', tint) + cell(49, 49, 13),
        placenta: () => `<ellipse cx="37" cy="47" rx="22" ry="32" fill="${tint}" stroke="${ink}" stroke-width="3"/>` + path('M37 22V70M37 40L25 31M37 49L51 40M37 58L25 50M58 48C86 36 64 77 87 78'),
        clock: () => circle(50, 50, 37, tint) + path('M50 23V51L70 62'),
        speech: () => path('M12 20H88V68H46L28 85V68H12Z', tint) + path('M25 37H75M25 51H62'),
        shield: () => path('M50 8L86 24V51Q80 75 50 92Q20 75 14 51V24Z', tint) + path('M32 48L45 61L70 36'),
        pill: () => `<g transform="rotate(-39 50 50)">${rect(16, 34, 68, 32, tint, ink, 16)}${line(50, 35, 50, 65)}</g>`,
        search: () => circle(42, 40, 26, tint) + path('M61 60L85 85M30 40H54M42 28V52'),
        lock: () => path('M30 43V30A20 20 0 0 1 70 30V43') + rect(20, 43, 60, 43, tint, ink) + path('M50 58V70'),
        sun: () => circle(50, 50, 22, tint) + Array.from({ length: 8 }, (_, i) => `<path d="M50 11V21" stroke="${ink}" stroke-width="3" transform="rotate(${i * 45} 50 50)"/>`).join(''),
        mutation: () => dna() + circle(51, 50, 12, '#f9c680') + label('!', 51, 56, 17, 700, 'middle'),
        population: () => [22, 50, 78].map((cx, i) => [26, 53, 80].map((cy, j) => circle(cx, cy, 8, (i + j) % 3 ? tint : accent)).join('')).join(''),
        tree: () => path('M50 88V62H23V18M50 62H77V42H61V18M77 42H91V18') + [23, 61, 91].map(cx => circle(cx, 16, 5, tint)).join(''),
        fossil: () => path('M13 18L84 9L94 79L28 91L9 55Z', tint) + path('M69 35C57 19 31 29 31 49C31 72 66 70 68 52C70 37 47 33 42 47C38 57 53 61 57 51'),
        plasmid: () => circle(50, 50, 34, tint) + circle(50, 50, 23, '#fff') + `<path d="M50 16A34 34 0 0 1 82 38" fill="none" stroke="${accent}" stroke-width="9"/>`,
        bacteria: () => `<g transform="rotate(-24 50 50)">${rect(12, 25, 76, 50, tint, ink, 24)}${path('M29 51C32 29 65 66 70 42C75 68 35 29 29 51Z')}${path('M88 50C101 41 99 68 108 61')}</g>`,
        fungus: () => path('M16 47C20 8 80 8 84 47Z', tint) + path('M43 47L39 80H61L57 47M9 88L39 80M38 93L49 81M90 87L61 80'),
        arthropod: () => `<ellipse cx="50" cy="60" rx="20" ry="26" fill="${tint}" stroke="${ink}" stroke-width="3"/>` + circle(50, 30, 13, tint) + path('M39 20L26 8M61 20L74 8M33 46L14 39M30 60L12 60M33 73L16 84M67 46L86 39M70 60L88 60M67 73L84 84'),
        fish: () => path('M10 50Q40 12 75 42L92 25V75L75 58Q40 88 10 50Z', tint) + circle(28, 44, 3, ink),
        spine: () => [16, 33, 50, 67, 84].map((cy, i) => rect(36 + (i % 2) * 4, cy - 7, 25, 13, tint, ink, 5)).join(''),
    };
    if (!icons[name]) throw new Error(`Unknown symbol: ${name}`);
    return `<g transform="translate(${x} ${y}) scale(${scale})">${icons[name]()}</g>`;
}

function cards(lesson, accent, tint) {
    const gap = lesson.flow ? 38 : 16;
    const w = (1192 - gap * (lesson.cards.length - 1)) / lesson.cards.length;
    const compact = lesson.cards.length === 5;
    return lesson.cards.map(([title, icon, detail], i) => {
        const x = 44 + i * (w + gap);
        const [heading, height] = paragraph(title, x + w / 2, 385, w - 32, compact ? 21 : 28, 700, 'middle');
        const [copy, copyHeight] = paragraph(detail, x + 20, 389 + height, w - 40, compact ? 19 : 23);
        if (389 + height + copyHeight - (compact ? 27 : 31) > 592) throw new Error(`Card overflow: ${lesson.id} ${title}`);
        return rect(x, 198, w, 414, '#fff', '#b7c9c0')
            + rect(x + 16, 216, w - 32, 132, tint)
            + symbol(icon, x + w / 2 - 48, 233, accent, '#fff', .96)
            + heading + copy
            + (lesson.flow && i < lesson.cards.length - 1 ? line(x + w + 3, 285, x + w + gap - 7, 285, true) : '');
    }).join('');
}

function special(lesson, accent, tint) {
    const panel = rect(44, 198, 1192, 414, '#fff', '#b7c9c0');
    if (lesson.layout === 'cells') {
        return panel + line(630, 225, 630, 583)
            + label('Dierlijke cel', 325, 257, 30, 700, 'middle')
            + label('Plantaardige cel', 940, 257, 30, 700, 'middle')
            + circle(321, 408, 108, tint) + circle(321, 408, 35, accent)
            + line(150, 331, 240, 335, true) + label('Celmembraan', 73, 312, 21, 700)
            + line(156, 408, 280, 408, true) + label('Celkern', 73, 414, 21, 700)
            + line(423, 494, 389, 462, true) + label('Cytoplasma', 423, 521, 21, 700)
            + rect(816, 295, 249, 228, tint, ink, 23) + rect(826, 305, 229, 208, '#fff', ink, 16)
            + rect(926, 351, 105, 125, '#d5eafa', ink, 20) + circle(869, 421, 22, accent)
            + [861, 903, 945, 987, 1029].map(cx => `<ellipse cx="${cx}" cy="331" rx="12" ry="7" fill="#437545" stroke="${ink}" stroke-width="2"/>`).join('')
            + line(750, 308, 811, 313, true) + label('Celwand', 657, 314, 21, 700)
            + line(748, 421, 840, 421, true) + label('Celkern', 657, 427, 21, 700)
            + line(1135, 330, 1041, 330, true) + label('Bladgroenkorrels', 1211, 287, 20, 700, 'end')
            + line(1125, 418, 1031, 418, true) + label('Vacuole', 1127, 424, 21, 700)
            + line(941, 558, 940, 513, true) + label('Celmembraan', 942, 585, 21, 700, 'middle');
    }
    if (lesson.layout === 'punnett') {
        const rows = [['Aa x Aa', 'A', 'a'], ['A', 'AA', 'Aa'], ['a', 'Aa', 'aa']];
        const cells = rows.map((row, r) => row.map((value, c) => rect(76 + c * 154, 230 + r * 101, 144, 91, r && c ? (value === 'aa' ? '#f9e2c9' : tint) : paper, '#c5d3cb', 10) + label(value, 148 + c * 154, 287 + r * 101, 32, 700, 'middle')).join('')).join('');
        return panel + cells + lesson.cards.map(([title, , detail], i) => label(title, 600, 260 + i * 112, 28, 700) + paragraph(detail, 600, 295 + i * 112, 575, 23)[0]).join('');
    }
    if (lesson.layout === 'pedigree') {
        return panel + line(205, 305, 387, 305) + line(296, 305, 296, 439) + line(154, 439, 438, 439) + line(154, 439, 154, 473) + line(438, 439, 438, 473)
            + rect(157, 273, 64, 64, '#fff', ink, 0) + circle(403, 305, 32, '#fff')
            + rect(122, 473, 64, 64, accent, ink, 0) + circle(438, 505, 32, '#fff')
            + label('Aa', 189, 375, 26, 700, 'middle') + label('Aa', 403, 375, 26, 700, 'middle')
            + label('aa', 154, 579, 26, 700, 'middle') + label('AA of Aa', 438, 579, 26, 700, 'middle')
            + lesson.cards.map(([title, , detail], i) => label(title, 568, 258 + i * 112, 28, 700) + paragraph(detail, 568, 294 + i * 112, 612, 23)[0]).join('');
    }
    if (lesson.layout === 'key') {
        const box = (x, y, w, text, fill) => rect(x, y, w, 66, fill, '#b7c9c0', 10) + label(text, x + w / 2, y + 42, 25, 700, 'middle');
        return panel + box(437, 225, 406, 'Heeft het dier veren?', tint)
            + line(545, 291, 270, 364, true) + line(735, 291, 896, 364, true)
            + label('Ja', 371, 326, 22, 700) + label('Nee', 828, 326, 22, 700)
            + box(124, 372, 250, 'Vogel', '#eaf0d9') + box(681, 372, 432, 'Heeft het dier zes poten?', tint)
            + line(793, 438, 629, 517, true) + line(1000, 438, 1068, 517, true)
            + label('Ja', 667, 463, 22, 700) + label('Nee', 1075, 475, 22, 700)
            + box(488, 528, 280, 'Insect', '#eaf0d9') + box(902, 528, 305, 'Andere groep', paper);
    }
    if (lesson.layout === 'tree') {
        return panel + line(305, 536, 305, 448) + line(131, 448, 419, 448) + line(131, 448, 131, 314) + line(419, 448, 419, 380)
            + line(330, 380, 507, 380) + line(330, 380, 330, 314) + line(507, 380, 507, 314)
            + circle(305, 448, 7, accent) + circle(419, 380, 7, accent)
            + ['A', 'B', 'C'].map((name, i) => circle([131, 330, 507][i], 278, 28, tint) + label(name, [131, 330, 507][i], 288, 26, 700, 'middle')).join('')
            + label('Gemeenschappelijke voorouder', 305, 574, 24, 700, 'middle')
            + lesson.cards.map(([title, , detail], i) => label(title, 605, 260 + i * 112, 27, 700) + paragraph(detail, 605, 295 + i * 112, 570, 23)[0]).join('');
    }
    throw new Error(`Unknown layout: ${lesson.layout}`);
}

function illustration(lesson) {
    const theme = Number(lesson.id[1]);
    const accent = accents[theme - 1], tint = tints[theme - 1];
    const titleSize = lesson.title.length > 34 ? 44 : 52;
    const [note, noteHeight] = paragraph(lesson.note, 222, 671, 970, 23);
    if (noteHeight > 62) throw new Error(`Note overflow: ${lesson.id}`);
    const art = lesson.layout ? special(lesson, accent, tint) : cards(lesson, accent, tint);
    return `<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="740" viewBox="0 0 1280 740" role="img" aria-label="${xml(lesson.title)}">
<defs><marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M0 0L10 5L0 10Z" fill="${ink}"/></marker></defs>
<g font-family="DejaVu Sans, Helvetica, sans-serif">
${rect(0, 0, 1280, 740, paper, 'none', 0)}
${rect(0, 0, 1280, 170, accent, 'none', 0)}
${label(`BIOLOGIE GT  /  THEMA ${theme}  /  BASISSTOF ${lesson.id.split('b')[1]}`, 44, 55, 23, 700, 'start', '#fff')}
${label(lesson.title, 44, 125, titleSize, 700, 'start', '#fff')}
${art}
${rect(44, 636, 1192, 80, tint)}${label('Onthoud', 67, 671, 25, 700)}${note}
</g></svg>`;
}

if (process.argv.includes('--manifest')) {
    process.stdout.write(JSON.stringify(lessons));
} else {
    const output = resolve(root, 'media/gt-thema-1-4');
    mkdirSync(output, { recursive: true });
    for (const lesson of lessons) {
        const file = resolve(output, `${lesson.id}-uitleg.png`);
        const png = execFileSync('rsvg-convert', { input: illustration(lesson), maxBuffer: 4 * 1024 * 1024 });
        writeFileSync(file, png);
    }
    process.stdout.write(`${lessons.length} uitlegafbeeldingen gegenereerd.\n`);
}
