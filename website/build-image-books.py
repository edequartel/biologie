"""Build the four A4 lesson illustration books. Requires PHP, Pillow and ReportLab."""

from io import BytesIO
import json
from pathlib import Path
import re
import subprocess
from tempfile import TemporaryDirectory

from PIL import Image
from reportlab.lib.colors import HexColor
from reportlab.lib.pagesizes import A4
from reportlab.lib.utils import ImageReader
from reportlab.pdfbase.pdfmetrics import stringWidth
from reportlab.pdfgen.canvas import Canvas


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "output" / "pdf"
BOOKS = {"b": "BBL", "k": "KBL", "gt": "GT", "havo": "Havo"}

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


def draw_image(pdf, path, cx, cy, max_width, max_height):
    with Image.open(path) as source:
        pixels_w, pixels_h = source.size
        jpeg = BytesIO()
        rgb = Image.new("RGB", source.size, "white")
        alpha = source.convert("RGBA")
        rgb.paste(alpha, mask=alpha.getchannel("A"))
        rgb.save(jpeg, format="JPEG", quality=94, subsampling=0, optimize=True)
        jpeg.seek(0)

    scale = min(max_width / pixels_h, max_height / pixels_w)
    width, height = pixels_w * scale, pixels_h * scale
    pdf.saveState()
    pdf.translate(cx, cy)
    pdf.rotate(90)  # Positive PDF rotation is counterclockwise.
    pdf.drawImage(ImageReader(jpeg), -width / 2, -height / 2, width, height)
    pdf.restoreState()


def page_text(value):
    return re.sub(r"\s+", " ", value).strip().replace("–", "-").replace("—", "-")


def build_book(target, level_name, lessons):
    pdf = Canvas(str(target), pagesize=A4, pageCompression=1)
    pdf.setTitle(f"Biologie {level_name} - Uitlegafbeeldingen")
    pdf.setSubject("Uitlegafbeeldingen per basisstof, 90 graden tegen de klok in gedraaid")
    width, height = A4
    margin = 24
    last_theme = None

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
        pdf.setFont("Helvetica", 10)
        pdf.drawRightString(width - margin, height - 27, theme)
        pdf.setStrokeColor(HexColor("#B7D4E4"))
        pdf.line(margin, height - 35, width - margin, height - 35)

        size = min(16, 16 * (width - 2 * margin) / stringWidth(title, "Helvetica-Bold", 16))
        pdf.setFillColor(HexColor("#122C3B"))
        pdf.setFont("Helvetica-Bold", size)
        pdf.drawString(margin, height - 55, title)

        image_top, image_bottom = height - 68, 33
        draw_image(pdf, lesson["image"], width / 2, (image_top + image_bottom) / 2,
                   width - 2 * margin, image_top - image_bottom)
        pdf.setFillColor(HexColor("#194A63"))
        pdf.setFont("Helvetica", 9)
        pdf.drawString(margin, 17, "Uitlegafbeeldingen")
        pdf.drawRightString(width - margin, 17, f"{number} / {len(lessons)}")
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
            build_book(draft, name, books[level])
            draft.replace(OUTPUT / filename)
            print(f"{filename}: {len(books[level])} pagina's")


if __name__ == "__main__":
    main()
