// Extracts the hard-coded COURSE_DATA + GLOBAL_NOTICE from the legacy single-file app
// into JSON, so migration seeds carry the exact original Bengali text (no transcription).
import { readFileSync, writeFileSync } from 'node:fs';

const src = readFileSync(process.argv[2], 'utf8');

function sliceArrayLiteral(text, marker) {
  const start = text.indexOf(marker);
  if (start === -1) throw new Error(`marker not found: ${marker}`);
  const open = text.indexOf('[', start);
  let depth = 0, inStr = null, esc = false;
  for (let i = open; i < text.length; i++) {
    const ch = text[i];
    if (inStr) {
      if (esc) { esc = false; continue; }
      if (ch === '\\') { esc = true; continue; }
      if (ch === inStr) inStr = null;
      continue;
    }
    if (ch === '"' || ch === "'" || ch === '`') { inStr = ch; continue; }
    if (ch === '[') depth++;
    else if (ch === ']') { depth--; if (depth === 0) return text.slice(open, i + 1); }
  }
  throw new Error('unterminated array literal');
}

const courseData = eval(sliceArrayLiteral(src, 'const COURSE_DATA'));
const notice = src.match(/const GLOBAL_NOTICE\s*=\s*"([^"]*)"/)?.[1] ?? null;

// Mirror the legacy quiz-slug derivation (Bengali numerals -> latin, per category prefix)
const bnToEn = (s) => String(s).replace(/[০-৯]/g, (d) => '০১২৩৪৫৬৭৮৯'.indexOf(d));
// NFC-normalise both sides: Bengali য়/ড়/ঢ় have precomposed and decomposed forms that
// look identical but compare unequal. Sheet-authored text may use either.
const slugFor = (title) => {
  const t = title.normalize('NFC');
  for (const [pattern, prefix] of [
    ['সীরাত', 'seerat'],
    ['তাফসির', 'tafseer'],
    ['মহিলাদের হালাকাহ', 'halakah'],
    ['জুমার বয়ান', 'jummah'],
  ]) {
    const re = new RegExp(`${pattern.normalize('NFC')}-([০-৯]+)`);
    const m = t.match(re);
    if (m) return `${prefix}-${parseInt(bnToEn(m[1]), 10)}`;
  }
  return null;
};

const lessons = courseData.map((l) => ({
  legacy_id: l.id,
  category: l.category,
  date_label: l.date,
  duration_label: l.duration,
  drive_link: l.driveLink ?? null,
  title: l.content.title,
  description: l.content.description ?? null,
  summary: Array.isArray(l.content.summary) ? l.content.summary
         : (l.content.summary ? [l.content.summary] : []),
  syllabus: l.content.syllabus && l.content.syllabus !== '#' ? l.content.syllabus : null,
  resources: (l.content.resources ?? []).map((r) => ({ label: r.label, url: r.url })),
  has_family_tree: !!l.content.hasFamilyTree,
  quiz_slug: slugFor(l.content.title),
}));

const byCat = lessons.reduce((a, l) => ((a[l.category] = (a[l.category] || 0) + 1), a), {});
const out = {
  extracted_at: new Date().toISOString(),
  source_file: process.argv[2],
  global_notice: notice,
  counts: { total: lessons.length, by_category: byCat },
  lessons,
};

writeFileSync(process.argv[3], JSON.stringify(out, null, 2), 'utf8');
console.log('total lessons:', lessons.length);
console.log('by category  :', JSON.stringify(byCat));
console.log('with syllabus:', lessons.filter((l) => l.syllabus).length);
console.log('with summary :', lessons.filter((l) => l.summary.length).length);
console.log('drive links  :', lessons.filter((l) => l.drive_link).length);
console.log('quiz slugs   :', lessons.filter((l) => l.quiz_slug).length);
console.log('placeholder resource urls (#):',
  lessons.flatMap((l) => l.resources).filter((r) => r.url === '#').length);
