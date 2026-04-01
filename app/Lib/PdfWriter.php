<?php
declare(strict_types=1);

namespace App\Lib;

/**
 * Lightweight PDF generator using raw PDF format.
 * Supports text, tables, and basic formatting with Helvetica/Helvetica-Bold.
 */
class PdfWriter
{
    /** @var string[] Content streams for each page */
    private array $pageStreams = [];
    private string $stream = '';
    private float $pageW = 595.28;  // A4 width in points
    private float $pageH = 841.89;  // A4 height in points
    private float $margin = 40;
    private float $curY;
    private float $fontSize = 10;

    public function __construct()
    {
        $this->curY = $this->margin;
    }

    /* ── Page management ────────────────────────────────────── */

    private function newPage(): void
    {
        if ($this->stream !== '') {
            $this->pageStreams[] = $this->stream;
        }
        $this->stream = '';
        $this->curY = $this->margin;
    }

    private function needSpace(float $h): void
    {
        if ($this->curY + $h > $this->pageH - $this->margin) {
            $this->newPage();
        }
    }

    /* ── Coordinate helpers ─────────────────────────────────── */

    /** Convert top-down Y to PDF bottom-up Y */
    private function py(float $topY): float
    {
        return $this->pageH - $topY;
    }

    private function contentWidth(): float
    {
        return $this->pageW - 2 * $this->margin;
    }

    /* ── Text helpers ───────────────────────────────────────── */

    private function esc(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    /** Approximate text width for Helvetica at current font size */
    private function textWidth(string $text): float
    {
        return strlen($text) * $this->fontSize * 0.48;
    }

    /* ── Public API ─────────────────────────────────────────── */

    public function setFont(float $size): self
    {
        $this->fontSize = $size;
        return $this;
    }

    public function write(string $text, bool $bold = false, ?float $x = null): self
    {
        $lh = $this->fontSize * 1.4;
        $this->needSpace($lh);
        $x = $x ?? $this->margin;
        $font = $bold ? '/F2' : '/F1';
        $py = $this->py($this->curY + $this->fontSize);
        $this->stream .= sprintf(
            "BT %s %.1f Tf %.2f %.2f Td (%s) Tj ET\n",
            $font, $this->fontSize, $x, $py, $this->esc($text)
        );
        $this->curY += $lh;
        return $this;
    }

    /** Write text at current curY without advancing the cursor */
    public function writeAt(string $text, float $x, bool $bold = false): self
    {
        $font = $bold ? '/F2' : '/F1';
        $py = $this->py($this->curY + $this->fontSize);
        $this->stream .= sprintf(
            "BT %s %.1f Tf %.2f %.2f Td (%s) Tj ET\n",
            $font, $this->fontSize, $x, $py, $this->esc($text)
        );
        return $this;
    }

    public function spacer(float $height = 10): self
    {
        $this->curY += $height;
        return $this;
    }

    public function hr(): self
    {
        $py = $this->py($this->curY);
        $this->stream .= sprintf(
            "0.85 0.85 0.85 RG 0.5 w %.2f %.2f m %.2f %.2f l S\n",
            $this->margin, $py, $this->pageW - $this->margin, $py
        );
        $this->curY += 6;
        return $this;
    }

    /**
     * Render a grid of label/value pairs (e.g. summary cards).
     * @param array<array{label:string,value:string}> $items
     */
    public function kvGrid(array $items, int $cols = 4): self
    {
        $colW = $this->contentWidth() / $cols;
        $rowH = $this->fontSize * 3.2;
        $chunks = array_chunk($items, $cols);

        foreach ($chunks as $row) {
            $this->needSpace($rowH);
            $x = $this->margin;
            foreach ($row as $item) {
                $labelPy = $this->py($this->curY + $this->fontSize * 0.8);
                $valuePy = $this->py($this->curY + $this->fontSize * 2.2);

                // Label (grey, smaller)
                $this->stream .= sprintf(
                    "BT /F1 %.1f Tf 0.5 0.5 0.5 rg %.2f %.2f Td (%s) Tj 0 0 0 rg ET\n",
                    $this->fontSize * 0.8, $x + 4, $labelPy, $this->esc($item['label'] ?? '')
                );
                // Value (bold)
                $this->stream .= sprintf(
                    "BT /F2 %.1f Tf %.2f %.2f Td (%s) Tj ET\n",
                    $this->fontSize * 1.1, $x + 4, $valuePy, $this->esc($item['value'] ?? '')
                );
                $x += $colW;
            }
            $this->curY += $rowH;
        }
        return $this;
    }

    /**
     * Render a table row.
     * @param string[]       $cells  Cell values
     * @param float[]        $widths Column widths in points
     * @param bool           $header Whether to draw header background
     * @param string[]|null  $aligns 'left' or 'right' per column
     */
    public function tableRow(array $cells, array $widths, bool $header = false, ?array $aligns = null): self
    {
        $rowH = $this->fontSize * 1.8;
        $this->needSpace($rowH);
        $x = $this->margin;
        $py = $this->py($this->curY);

        // Header background
        if ($header) {
            $this->stream .= sprintf(
                "0.95 0.95 0.95 rg %.2f %.2f %.2f %.2f re f 0 0 0 rg\n",
                $x, $py - $rowH, array_sum($widths), $rowH
            );
        }

        // Bottom border
        $this->stream .= sprintf(
            "0.9 0.9 0.9 RG 0.5 w %.2f %.2f m %.2f %.2f l S 0 0 0 RG\n",
            $x, $py - $rowH, $x + array_sum($widths), $py - $rowH
        );

        // Cell text
        $font = $header ? '/F2' : '/F1';
        $textPy = $py - $this->fontSize - ($rowH - $this->fontSize) / 2;
        $count = min(count($cells), count($widths));

        for ($i = 0; $i < $count; $i++) {
            $text = (string)($cells[$i] ?? '');
            $cellW = $widths[$i];
            $align = $aligns[$i] ?? 'left';

            if ($align === 'right') {
                $tw = $this->textWidth($text);
                $textX = $x + $cellW - $tw - 4;
            } else {
                $textX = $x + 4;
            }

            $this->stream .= sprintf(
                "BT %s %.1f Tf %.2f %.2f Td (%s) Tj ET\n",
                $font, $this->fontSize, $textX, $textPy, $this->esc($text)
            );
            $x += $cellW;
        }

        $this->curY += $rowH;
        return $this;
    }

    /* ── Build final PDF bytes ──────────────────────────────── */

    public function build(): string
    {
        // Finalize current page
        if ($this->stream !== '') {
            $this->pageStreams[] = $this->stream;
        }

        $numPages = count($this->pageStreams);
        if ($numPages === 0) {
            $this->pageStreams[] = '';
            $numPages = 1;
        }

        // Pre-assign object IDs
        $catalogId = 1;
        $pagesId   = 2;
        $font1Id   = 3;
        $font2Id   = 4;
        $nextId    = 5;

        $streamIds = [];
        $pageIds   = [];
        for ($i = 0; $i < $numPages; $i++) {
            $streamIds[] = $nextId++;
            $pageIds[]   = $nextId++;
        }

        $maxId = $nextId - 1;

        // Build object bodies
        $bodies = [];

        $kids = implode(' ', array_map(fn(int $id): string => "{$id} 0 R", $pageIds));
        $bodies[$catalogId] = "<< /Type /Catalog /Pages {$pagesId} 0 R >>";
        $bodies[$pagesId]   = "<< /Type /Pages /Kids [{$kids}] /Count {$numPages} >>";
        $bodies[$font1Id]   = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $bodies[$font2Id]   = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        for ($i = 0; $i < $numPages; $i++) {
            $sc = $this->pageStreams[$i];
            $sl = strlen($sc);
            $bodies[$streamIds[$i]] = "<< /Length {$sl} >>\nstream\n{$sc}\nendstream";
            $bodies[$pageIds[$i]]   = sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2f %.2f] /Contents %d 0 R /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >> >> >>",
                $pagesId, $this->pageW, $this->pageH, $streamIds[$i], $font1Id, $font2Id
            );
        }

        // Assemble PDF
        $pdf = "%PDF-1.4\n";
        $offsets = [];

        for ($id = 1; $id <= $maxId; $id++) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$bodies[$id]}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root {$catalogId} 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }
}
