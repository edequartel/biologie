"""Build the four A4 lesson illustration books. Requires PHP, Pillow and ReportLab."""

from io import BytesIO
import json
from pathlib import Path
import re
import subprocess
from tempfile import TemporaryDirectory

from PIL import Image
from reportlab import rl_config
from reportlab.lib.colors import HexColor
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase.pdfmetrics import stringWidth
from reportlab.pdfgen.canvas import Canvas


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "output" / "pdf"
BOOKS = {"b": "BBL", "k": "KBL", "gt": "GT", "havo": "Havo"}
KBL_THEME_NUMBERS = {
    "Organen en cellen": 1,
    "Voortplanting en seksualteit": 2,
    "Ordening": 4,
    "Stevigheid en beweging": 5,
    "Ecologie": 6,
    "Duurzaam leven": 7,
    "Planten": 8,
}
rl_config.useA85 = False

# Use the same rendered links and path rules as the website.
MANIFEST = r"""
require 'website/functions.php';
function document(string $page): DOMXPath {
    $file = contentPath($page);
    if ($file === null) {
        throw new RuntimeException('Ontbrekende pagina: ' . $page);
    }
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . renderMarkdown(file_get_contents($file), $page),
        LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    return new DOMXPath($dom);
}
$books = [];
foreach (['b', 'k', 'gt', 'havo'] as $level) {
    $books[$level] = [];
    $seen = [];
    $theme = '';
    foreach (document("$level/$level.md")->query('//h2 | //h3 | //h4 | //a') as $node) {
        $label = trim($node->textContent);
        if ($node->nodeName !== 'a') {
            $theme = $label;
            continue;
        }
        if (!preg_match('~^Basisstof\b~i', $label)
            || !preg_match('~^index\.php\?page=([^#]+)$~D', $node->getAttribute('href'), $link)) {
            continue;
        }
        $page = rawurldecode($link[1]);
        if (isset($seen[$page])) {
            continue;
        }
        $seen[$page] = true;
        $images = document($page)->query('//h3[starts-with(@id, "afbeelding-")]/following-sibling::p[img][1]/img');
        if ($images->length !== 1) {
            throw new RuntimeException('Verwacht een uitlegafbeelding: ' . $page);
        }
        parse_str(parse_url($images->item(0)->getAttribute('src'), PHP_URL_QUERY) ?? '', $query);
        $image = contentPath($query['file'] ?? '');
        if ($image === null) {
            throw new RuntimeException('Ontbrekende uitlegafbeelding: ' . $page);
        }
        $books[$level][] = ['theme' => $theme, 'title' => $label, 'image' => $image];
    }
    if (!$books[$level]) {
        throw new RuntimeException('Geen basisstoffen in: ' . $level);
    }
}
echo json_encode($books, JSON_THROW_ON_ERROR);
"""


def draw_image(pdf, path, x, y, max_width, max_height):
    with Image.open(path) as source:
        pixels_w, pixels_h = source.size
        jpeg = BytesIO()
        rgb = Image.new("RGB", source.size, "white")
        alpha = source.convert("RGBA")
        rgb.paste(alpha, mask=alpha.getchannel("A"))
        rgb.save(jpeg, format="JPEG", quality=90, subsampling=1, optimize=True)
        jpeg.seek(0)

    scale = min(max_width / pixels_w, max_height / pixels_h)
    width, height = pixels_w * scale, pixels_h * scale
    pdf.drawImage(ImageReader(jpeg), x + (max_width - width) / 2,
                  y + (max_height - height) / 2, width, height)


def page_text(value):
    return re.sub(r"\s+", " ", value).strip().replace("–", "-").replace("—", "-")


def order_lessons(level, lessons):
    for lesson in lessons:
        theme = page_text(lesson["theme"])
        numbered = re.fullmatch(r"Thema (\d+)\s*:?\s+(.+)", theme)
        if numbered:
            number, title = int(numbered[1]), numbered[2]
        elif level == "k" and theme in KBL_THEME_NUMBERS:
            number, title = KBL_THEME_NUMBERS[theme], theme.replace("seksualteit", "seksualiteit")
        else:
            raise ValueError(f"Geen themanummer voor {level}: {theme}")
        basisstof = re.match(r"Basisstof (\d+)", lesson["title"], re.IGNORECASE)
        if not basisstof:
            raise ValueError(f"Geen basisstofnummer: {lesson['title']}")
        lesson["theme"] = f"Thema {number}: {title}"
        lesson["sort"] = (number, int(basisstof[1]))
    return sorted(lessons, key=lambda lesson: lesson["sort"])


def draw_cover(pdf, level_name, lessons, width, height):
    ink = HexColor("#123932")
    pdf.setFillColor(HexColor("#EAF2ED"))
    pdf.rect(0, 0, width, height, fill=1, stroke=0)
    pdf.setFillColor(ink)
    pdf.rect(0, 0, 445, height, fill=1, stroke=0)
    pdf.setFillColor(HexColor("#C3E3D4"))
    pdf.setFont("Helvetica-Bold", 13)
    pdf.drawString(38, height - 57, f"BIOLOGIE  /  {level_name.upper()}")
    pdf.setFillColor(HexColor("#FFFFFF"))
    pdf.setFont("Helvetica-Bold", 37)
    pdf.drawString(38, height - 114, "Uitlegafbeeldingen")
    pdf.setFont("Helvetica", 15)
    pdf.drawString(40, height - 147, f"{len(lessons)} basisstoffen in beeld")
    pdf.setFillColor(HexColor("#FFFFFF"))
    pdf.roundRect(38, 89, 369, 264, 10, fill=1, stroke=0)
    draw_image(pdf, lessons[0]["image"], 47, 98, 351, 246)
    pdf.setFillColor(HexColor("#C3E3D4"))
    pdf.setFont("Helvetica", 10)
    pdf.drawString(40, 54, "EEN AFBEELDING PER BASISSTOF")

    left, right = 478, width - 35
    pdf.setFillColor(ink)
    pdf.setFont("Helvetica-Bold", 25)
    pdf.drawString(left, height - 76, "Inhoud")
    pdf.setFont("Helvetica", 12)
    pdf.drawString(left, height - 100, "Kies een thema om direct te beginnen.")
    y = height - 153
    last_theme = None
    for number, lesson in enumerate(lessons, start=1):
        theme = lesson["theme"]
        if theme == last_theme:
            continue
        last_theme = theme
        pdf.setStrokeColor(HexColor("#A8C3B7"))
        pdf.line(left, y - 12, right, y - 12)
        size = min(12, 12 * (right - left - 29) / stringWidth(theme, "Helvetica-Bold", 12))
        pdf.setFont("Helvetica-Bold", size)
        pdf.drawString(left, y, theme)
        pdf.setFont("Helvetica", 11)
        pdf.drawRightString(right, y, str(number + 1))
        pdf.linkRect("", f"thema-{number}", (left, y - 10, right, y + 14), thickness=0)
        y -= 35
    pdf.showPage()


def build_book(target, level_name, lessons):
    width, height = landscape(A4)
    pdf = Canvas(str(target), pagesize=(width, height), pageCompression=1)
    pdf.setTitle(f"Biologie {level_name} - Uitlegafbeeldingen")
    pdf.setSubject("Uitlegafbeeldingen per basisstof op liggend A4, geordend per thema")
    margin = 24
    last_theme = None
    draw_cover(pdf, level_name, lessons, width, height)

    for number, lesson in enumerate(lessons, start=1):
        theme = page_text(lesson["theme"])
        title = page_text(lesson["title"])
        bookmark = f"basisstof-{number}"
        pdf.bookmarkPage(bookmark)
        if theme != last_theme:
            theme_bookmark = f"thema-{number}"
            pdf.bookmarkPage(theme_bookmark)
            pdf.addOutlineEntry(theme, theme_bookmark, level=0, closed=True)
            last_theme = theme
        pdf.addOutlineEntry(title, bookmark, level=1)

        pdf.setFillColor(HexColor("#194A63"))
        pdf.setFont("Helvetica-Bold", 10)
        pdf.drawString(margin, height - 27, f"BIOLOGIE  /  {level_name.upper()}")
        pdf.setFont("Helvetica-Bold", 12)
        pdf.drawRightString(width - margin, height - 27, theme)
        pdf.setStrokeColor(HexColor("#B7D4E4"))
        pdf.line(margin, height - 35, width - margin, height - 35)

        size = min(16, 16 * (width - 2 * margin) / stringWidth(title, "Helvetica-Bold", 16))
        pdf.setFillColor(HexColor("#122C3B"))
        pdf.setFont("Helvetica-Bold", size)
        pdf.drawString(margin, height - 55, title)

        image_top, image_bottom = height - 68, 33
        draw_image(pdf, lesson["image"], margin, image_bottom,
                   width - 2 * margin, image_top - image_bottom)
        pdf.setFillColor(HexColor("#194A63"))
        pdf.setFont("Helvetica", 9)
        pdf.drawString(margin, 17, "Uitlegafbeeldingen")
        pdf.drawRightString(width - margin, 17, f"{number + 1} / {len(lessons) + 1}")
        pdf.showPage()
    pdf.save()


def main():
    result = subprocess.run(
        ["php", "-r", MANIFEST], cwd=ROOT, check=True, stdout=subprocess.PIPE, text=True
    )
    books = json.loads(result.stdout)
    OUTPUT.mkdir(parents=True, exist_ok=True)
    with TemporaryDirectory(dir=OUTPUT) as folder:
        for level, name in BOOKS.items():
            filename = f"biologie-{name.lower()}-uitlegafbeeldingen.pdf"
            draft = Path(folder) / filename
            build_book(draft, name, order_lessons(level, books[level]))
            draft.replace(OUTPUT / filename)
            print(f"{filename}: {len(books[level]) + 1} pagina's")


if __name__ == "__main__":
    main()
